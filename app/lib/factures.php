<?php
// Factures et avoirs : numéro continu par année, contenu figé à l'émission, PDF si dompdf est disponible.
declare(strict_types=1);

function prochainNumero(string $prefixe): string
{
    $annee = maintenant()->format('Y');
    $cle = "$prefixe-$annee";
    db()->prepare('INSERT INTO compteurs (cle, valeur) VALUES (?, 1) ON CONFLICT(cle) DO UPDATE SET valeur = valeur + 1')->execute([$cle]);
    $s = db()->prepare('SELECT valeur FROM compteurs WHERE cle = ?');
    $s->execute([$cle]);
    return sprintf('%s-%s-%04d', $prefixe, $annee, (int) $s->fetchColumn());
}

function emetteur(): array
{
    return ['nom' => param('facture_nom'), 'adresse' => param('facture_adresse'), 'siret' => param('facture_siret'),
        'email' => param('email_sandrine'), 'telephone' => param('telephone'), 'mention_tva' => param('facture_mention_tva')];
}

function creerFacture(int $reservationId): ?int
{
    $r = reservation($reservationId);
    $deja = db()->prepare("SELECT id FROM factures WHERE reservation_id = ? AND type = 'facture'");
    $deja->execute([$reservationId]);
    if ($id = $deja->fetchColumn()) return (int) $id;
    return transaction(function () use ($r) {
        $numero = prochainNumero('F');
        $donnees = [
            'emetteur' => emetteur(),
            'client' => ['nom' => $r['prenom'] . ' ' . $r['nom'], 'email' => $r['email']],
            'lignes' => [['libelle' => titreReservation($r), 'detail' => 'Début : ' . dateLongue($r['debut']) . ', ' . $r['lieu'], 'quantite' => 1, 'prix' => (int) $r['montant_centimes']]],
            'paiement' => ['mode' => 'Carte bancaire en ligne (Mollie)', 'date' => $r['paye_le'] ?: horodatage(), 'reference' => $r['reference']],
            'date_prestation' => substr($r['debut'], 0, 10),
        ];
        db()->prepare("INSERT INTO factures (numero, type, reservation_id, client_id, montant_centimes, donnees, emise_le) VALUES (?, 'facture', ?, ?, ?, ?, ?)")
            ->execute([$numero, $r['id'], $r['client_id'], $r['montant_centimes'], json_encode($donnees, JSON_UNESCAPED_UNICODE), horodatage()]);
        return (int) db()->lastInsertId();
    });
}

function creerAvoir(int $factureId, int $centimes, string $motif): int
{
    $f = facture($factureId);
    return transaction(function () use ($f, $centimes, $motif) {
        $numero = prochainNumero('A');
        $d = $f['donnees'];
        $d['emetteur'] = emetteur();
        $d['lignes'] = [['libelle' => "Remboursement : $motif", 'detail' => 'Avoir sur la facture ' . $f['numero'], 'quantite' => 1, 'prix' => -$centimes]];
        $d['paiement'] = ['mode' => 'Remboursement sur la carte bancaire (Mollie)', 'date' => horodatage(), 'reference' => $d['paiement']['reference'] ?? ''];
        db()->prepare("INSERT INTO factures (numero, type, origine_id, reservation_id, client_id, montant_centimes, donnees, emise_le) VALUES (?, 'avoir', ?, ?, ?, ?, ?, ?)")
            ->execute([$numero, $f['id'], $f['reservation_id'], $f['client_id'], -$centimes, json_encode($d, JSON_UNESCAPED_UNICODE), horodatage()]);
        return (int) db()->lastInsertId();
    });
}

function facture(int $id): ?array
{
    $s = db()->prepare('SELECT f.*, o.numero AS origine_numero FROM factures f LEFT JOIN factures o ON o.id = f.origine_id WHERE f.id = ?');
    $s->execute([$id]);
    $f = $s->fetch();
    if (!$f) return null;
    $f['donnees'] = json_decode($f['donnees'], true);
    return $f;
}

function htmlFacture(array $f): string
{
    $d = $f['donnees'];
    $e = $d['emetteur'];
    $titre = $f['type'] === 'avoir' ? 'Avoir' : 'Facture';
    $lignes = '';
    foreach ($d['lignes'] as $l) {
        $lignes .= '<tr><td>' . h($l['libelle']) . '<br><small>' . h($l['detail']) . '</small></td><td class="n">' . (int) $l['quantite'] . '</td><td class="n">' . h(euros((int) $l['prix'])) . '</td></tr>';
    }
    return '<!doctype html><html lang="fr"><head><meta charset="utf-8"><title>' . $titre . ' ' . h($f['numero']) . '</title><style>
body{font-family:DejaVu Sans,Arial,sans-serif;font-size:12px;color:#1C3533;margin:32px}
h1{font-size:22px;margin:0 0 4px}.haut{width:100%}.haut td{vertical-align:top;width:50%}
table.l{width:100%;border-collapse:collapse;margin-top:28px}table.l th,table.l td{border-bottom:1px solid #ccd;padding:8px;text-align:left}
.n{text-align:right;white-space:nowrap}.total td{font-weight:bold;border:0}small{color:#526C66}.pied{margin-top:36px;color:#526C66;font-size:11px}
</style></head><body>
<table class="haut"><tr><td><strong>' . h($e['nom']) . '</strong><br>' . nl2br(h($e['adresse'])) . '<br>' . h($e['email']) . '<br>' . h($e['telephone']) .
        ($e['siret'] ? '<br>SIRET : ' . h($e['siret']) : '<br><em>SIRET à compléter</em>') . '</td>
<td class="n"><h1>' . $titre . ' ' . h($f['numero']) . '</h1>Date : ' . h(dateLongue($f['emise_le'], false)) .
        ($f['origine_numero'] ? '<br>Sur la facture ' . h($f['origine_numero']) : '') . '<br><br><strong>' . h($d['client']['nom']) . '</strong><br>' . h($d['client']['email']) . '</td></tr></table>
<table class="l"><tr><th>Désignation</th><th class="n">Qté</th><th class="n">Montant</th></tr>' . $lignes . '
<tr class="total"><td></td><td class="n">Total</td><td class="n">' . h(euros((int) $f['montant_centimes'])) . '</td></tr></table>
<p>' . h($e['mention_tva']) . '</p>
<p>' . h($d['paiement']['mode']) . ', le ' . h(dateLongue($d['paiement']['date'], false)) . ($f['type'] === 'facture' ? ' : facture acquittée.' : '.') . '<br>Référence : ' . h($d['paiement']['reference']) . '</p>
<p class="pied">Date de la prestation : ' . h(dateLongue($d['date_prestation'] . ' 00:00:00', false)) . '. Pas d’escompte pour paiement anticipé.</p>
</body></html>';
}

// Pièce jointe : PDF avec dompdf s'il est installé, sinon page HTML imprimable
function fichierFacture(int $id): ?array
{
    $f = facture($id);
    if (!$f) return null;
    $html = htmlFacture($f);
    $nom = ($f['type'] === 'avoir' ? 'avoir-' : 'facture-') . $f['numero'];
    $chargeur = config()['dompdf'] ?: RACINE . '/vendor/autoload.php';
    if (is_file($chargeur)) {
        try {
            require_once $chargeur;
            $pdf = new \Dompdf\Dompdf(['isRemoteEnabled' => false]);
            $pdf->loadHtml($html, 'UTF-8');
            $pdf->setPaper('A4');
            $pdf->render();
            return ["$nom.pdf", $pdf->output(), 'application/pdf'];
        } catch (Throwable $e) { journaliserErreur($e); }
    }
    return ["$nom.html", $html, 'text/html'];
}
