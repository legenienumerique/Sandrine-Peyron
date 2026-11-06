<?php
// Réconciliation Mollie (chaque nuit et après une coupure) : ne dépend pas des nouvelles tentatives de webhook.
// 1. Tous les paiements locaux non terminés, sans limite de date, relus chez Mollie (correspondance vérifiée).
// 2. Remboursements et rétrofacturations : listes globales paginées, depuis un curseur enregistré
//    seulement après le traitement complet de toutes les pages. Reprise sans perte ni doublon (mollie_evenements).
declare(strict_types=1);

const STATUTS_NON_TERMINES = ['cree', 'open', 'pending', 'authorized'];

function reconcilierMollie(): array
{
    $r = ['paiements' => 0, 'remboursements' => 0, 'retrofacturations' => 0, 'anomalies' => 0];
    $s = db()->prepare('SELECT mollie_id FROM paiements WHERE mollie_id IS NOT NULL AND statut IN (' . implode(',', array_fill(0, count(STATUTS_NON_TERMINES), '?')) . ') ORDER BY id');
    $s->execute(STATUTS_NON_TERMINES);
    foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $id) {
        traiterPaiement($id) === null ? $r['anomalies']++ : $r['paiements']++;
    }
    $r['remboursements'] = parcourirListeMollie('refunds', function (array $x): void {
        if (($pid = (string) ($x['paymentId'] ?? '')) !== '' && reservationPar('mollie', $pid)) traiterPaiement($pid);
    });
    $r['retrofacturations'] = parcourirListeMollie('chargebacks', function (array $x): void {
        $pid = (string) ($x['paymentId'] ?? '');
        if ($pid === '' || !($res = reservationPar('mollie', $pid))) return;
        journal('systeme', 'retrofacturation', "Rétrofacturation sur le paiement $pid (réservation {$res['id']})");
        // Alerte sans donnée personnelle ni contenu de santé ; aucune annulation automatique
        foreach (array_filter([param('email_sandrine'), (string) (config()['alerte_lgn'] ?? '')]) as $a) {
            expedier($a, 'Rétrofacturation Mollie à vérifier', "Bonjour,\n\nMollie signale une rétrofacturation (contestation de paiement) sur le paiement $pid.\nAucune réservation n'a été annulée automatiquement. Voir l'espace de gestion et le tableau de bord Mollie.\n");
        }
    });
    return $r;
}

// Parcourt une liste Mollie (la plus récente d'abord) jusqu'au dernier élément déjà traité.
// Le curseur n'avance qu'après traitement complet ; chaque élément est enregistré une seule fois.
function parcourirListeMollie(string $ressource, callable $traiter): int
{
    $cleCurseur = "mollie_curseur_$ressource";
    $curseur = param($cleCurseur);
    $chemin = "$ressource?limit=250";
    $premier = null;
    $n = 0;
    $pages = 0;
    while ($chemin !== null) {
        if (++$pages > 1000) throw new RuntimeException("Mollie : pagination anormale ($ressource)");
        $page = mollie('GET', $chemin);
        $elements = $page['_embedded'][$ressource] ?? [];
        $fin = false;
        foreach ($elements as $x) {
            $id = (string) ($x['id'] ?? '');
            if ($id === '') continue;
            $premier ??= $id;
            if ($id === $curseur) { $fin = true; break; }
            if (evenementDejaTraite($id)) continue;
            $traiter($x);
            db()->prepare('INSERT OR IGNORE INTO mollie_evenements (id_mollie, type, traite_le) VALUES (?, ?, ?)')->execute([$id, $ressource, horodatage()]);
            $n++;
        }
        $chemin = $fin ? null : cheminSuivantMollie($page['_links']['next']['href'] ?? null);
    }
    if ($premier !== null && $premier !== $curseur) definirParam($cleCurseur, $premier); // toutes les pages traitées
    return $n;
}

function evenementDejaTraite(string $id): bool
{
    $s = db()->prepare('SELECT 1 FROM mollie_evenements WHERE id_mollie = ?');
    $s->execute([$id]);
    return (bool) $s->fetchColumn();
}

// Lien « page suivante » : uniquement vers l'API Mollie, converti en chemin relatif
function cheminSuivantMollie(?string $href): ?string
{
    if (!$href) return null;
    if (!str_starts_with($href, 'https://api.mollie.com/v2/')) throw new RuntimeException('Mollie : lien de pagination inattendu');
    return substr($href, strlen('https://api.mollie.com/v2/'));
}
