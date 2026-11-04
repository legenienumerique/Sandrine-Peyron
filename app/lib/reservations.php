<?php
// Réservations : groupes (ouverture séquentielle) et rendez-vous, paiement Mollie, confirmations, annulations.
declare(strict_types=1);

class ErreurReservation extends RuntimeException {}

function seancesGroupe(int $groupeId): array
{
    $s = db()->prepare('SELECT debut, fin FROM seances_groupe WHERE groupe_id = ? ORDER BY debut');
    $s->execute([$groupeId]);
    return $s->fetchAll();
}

function tarifGroupe(array $g, array $activite): ?int
{
    return $g['tarif_centimes'] !== null ? (int) $g['tarif_centimes'] : ($activite['tarif_centimes'] !== null ? (int) $activite['tarif_centimes'] : null);
}

/*
 * États des groupes publiés d'une activité, dans l'ordre des dates.
 * complet = places payées ≥ capacité. Seul le premier groupe non complet et non commencé est « ouvert » ;
 * les suivants sont « verrouillés ». « attente » = places restantes bloquées par des paiements en cours.
 */
function groupesActivite(array $activite): array
{
    $s = db()->prepare("SELECT g.*,
            (SELECT MIN(debut) FROM seances_groupe WHERE groupe_id = g.id) AS premiere,
            (SELECT MAX(fin) FROM seances_groupe WHERE groupe_id = g.id) AS derniere,
            (SELECT COUNT(*) FROM seances_groupe WHERE groupe_id = g.id) AS nb_seances,
            (SELECT COUNT(*) FROM reservations WHERE groupe_id = g.id AND statut = 'confirmee') AS confirmes,
            (SELECT COUNT(*) FROM reservations WHERE groupe_id = g.id AND statut = 'attente' AND expire_le > :now) AS en_attente
        FROM groupes g WHERE g.activite_id = :a AND g.statut = 'publie' ORDER BY premiere, g.id");
    $s->execute([':a' => $activite['id'], ':now' => horodatage()]);
    $now = horodatage();
    $ouvert = false;
    $groupes = [];
    $rang = 0;
    foreach ($s->fetchAll() as $g) {
        if (!$g['premiere'] || $g['derniere'] < $now) continue; // sans séance ou terminé : plus affiché
        $g['rang'] = ++$rang;
        $g['restantes'] = max(0, (int) $g['capacite'] - (int) $g['confirmes'] - (int) $g['en_attente']);
        $g['tarif'] = tarifGroupe($g, $activite);
        if ($g['premiere'] <= $now) $g['etat'] = 'commence';
        elseif ((int) $g['confirmes'] >= (int) $g['capacite']) $g['etat'] = 'complet';
        elseif (!$ouvert) { $g['etat'] = $g['restantes'] > 0 ? 'ouvert' : 'attente'; $ouvert = true; }
        else $g['etat'] = 'verrouille';
        $g['reservable'] = $g['etat'] === 'ouvert' && $g['tarif'] !== null && mollieActif();
        $groupes[] = $g;
    }
    return $groupes;
}

function validerClient(array $d): array
{
    $c = [
        'prenom' => trim(mb_substr((string) ($d['prenom'] ?? ''), 0, 80)),
        'nom' => trim(mb_substr((string) ($d['nom'] ?? ''), 0, 80)),
        'email' => mb_strtolower(trim(mb_substr((string) ($d['email'] ?? ''), 0, 160))),
        'telephone' => trim(mb_substr((string) ($d['telephone'] ?? ''), 0, 30)),
    ];
    if ($c['prenom'] === '' || $c['nom'] === '') throw new ErreurReservation('Indiquez votre prénom et votre nom.');
    if (!filter_var($c['email'], FILTER_VALIDATE_EMAIL)) throw new ErreurReservation('Indiquez une adresse e-mail valide.');
    if (!preg_match('/^[0-9 +().-]{10,30}$/', $c['telephone'])) throw new ErreurReservation('Indiquez un numéro de téléphone valide.');
    if (empty($d['consentement'])) throw new ErreurReservation('Cochez la case d’accord pour continuer.');
    return $c;
}

function enregistrerClient(array $c): int
{
    $s = db()->prepare('SELECT id FROM clients WHERE email = ?');
    $s->execute([$c['email']]);
    $id = $s->fetchColumn();
    if ($id) {
        db()->prepare('UPDATE clients SET prenom = ?, nom = ?, telephone = ?, consentement_le = ?, anonymise = 0 WHERE id = ?')
            ->execute([$c['prenom'], $c['nom'], $c['telephone'], horodatage(), $id]);
        return (int) $id;
    }
    db()->prepare('INSERT INTO clients (prenom, nom, email, telephone, consentement_le, cree_le) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$c['prenom'], $c['nom'], $c['email'], $c['telephone'], horodatage(), horodatage()]);
    return (int) db()->lastInsertId();
}

function transaction(callable $f): mixed
{
    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE'); // verrou d'écriture : deux personnes ne peuvent pas prendre la même dernière place
    try { $r = $f(); $pdo->exec('COMMIT'); return $r; }
    catch (Throwable $e) { $pdo->exec('ROLLBACK'); throw $e; }
}

function insererReservation(array $r): int
{
    $ref = 'R' . maintenant()->format('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
    db()->prepare('INSERT INTO paiements (reference, montant_centimes, cree_le, maj_le) VALUES (?, ?, ?, ?)')
        ->execute([$ref, $r['montant'], horodatage(), horodatage()]);
    $paiementId = (int) db()->lastInsertId();
    $expire = maintenant()->modify('+' . paramInt('blocage_minutes') . ' minutes');
    db()->prepare("INSERT INTO reservations (type, activite_id, groupe_id, client_id, debut, fin, lieu, montant_centimes, statut, expire_le, paiement_id, jeton, cree_le)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'attente', ?, ?, ?, ?)")
        ->execute([$r['type'], $r['activite_id'], $r['groupe_id'] ?? null, $r['client_id'], $r['debut'], $r['fin'], $r['lieu'], $r['montant'], horodatage($expire), $paiementId, jeton(24), horodatage()]);
    return (int) db()->lastInsertId();
}

function reserverGroupe(int $groupeId, array $donnees): array
{
    if (!mollieActif()) throw new ErreurReservation('La réservation en ligne n’est pas encore ouverte. Appelez Sandrine au ' . param('telephone') . '.');
    $client = validerClient($donnees);
    $resId = transaction(function () use ($groupeId, $client) {
        $s = db()->prepare("SELECT g.*, a.id AS aid FROM groupes g JOIN activites a ON a.id = g.activite_id WHERE g.id = ? AND g.statut = 'publie'");
        $s->execute([$groupeId]);
        $g = $s->fetch();
        if (!$g) throw new ErreurReservation('Ce groupe n’est plus proposé.');
        $activite = activite((int) $g['aid']);
        $etat = null;
        foreach (groupesActivite($activite) as $x) if ((int) $x['id'] === $groupeId) $etat = $x;
        if (!$etat || $etat['etat'] !== 'ouvert' || $etat['restantes'] < 1) throw new ErreurReservation('Ce groupe n’a plus de place disponible. Regardez le groupe suivant.');
        if ($etat['tarif'] === null) throw new ErreurReservation('Le tarif de ce groupe n’est pas encore fixé.');
        $clientId = enregistrerClient($client);
        $deja = db()->prepare("SELECT COUNT(*) FROM reservations WHERE groupe_id = ? AND client_id = ? AND (statut = 'confirmee' OR (statut = 'attente' AND expire_le > ?))");
        $deja->execute([$groupeId, $clientId, horodatage()]);
        if ((int) $deja->fetchColumn() > 0) throw new ErreurReservation('Vous êtes déjà inscrit à ce groupe, ou un paiement est en cours.');
        return insererReservation(['type' => 'groupe', 'activite_id' => $activite['id'], 'groupe_id' => $groupeId, 'client_id' => $clientId,
            'debut' => $etat['premiere'], 'fin' => $etat['derniere'], 'lieu' => $g['lieu'], 'montant' => $etat['tarif']]);
    });
    return demarrerPaiement($resId);
}

function reserverRdv(string $code, string $debut, array $donnees): array
{
    if (!mollieActif()) throw new ErreurReservation('La réservation en ligne n’est pas encore ouverte. Appelez Sandrine au ' . param('telephone') . '.');
    $client = validerClient($donnees);
    $activite = activite($code);
    if (!$activite || $activite['type'] !== 'individuel' || !$activite['actif']) throw new ErreurReservation('Cette séance n’est pas proposée en ligne.');
    if ($activite['tarif_centimes'] === null) throw new ErreurReservation('Le tarif de cette séance n’est pas encore fixé.');
    $resId = transaction(function () use ($activite, $debut, $client) {
        $creneau = creneauEstLibre($activite, $debut);
        if (!$creneau) throw new ErreurReservation('Ce créneau vient d’être pris. Choisissez-en un autre.');
        $clientId = enregistrerClient($client);
        return insererReservation(['type' => 'rdv', 'activite_id' => $activite['id'], 'client_id' => $clientId,
            'debut' => $creneau['debut'], 'fin' => $creneau['fin'], 'lieu' => $creneau['lieu'], 'montant' => (int) $activite['tarif_centimes']]);
    });
    return demarrerPaiement($resId);
}

function reservation(int $id): ?array
{
    $s = db()->prepare('SELECT r.*, a.nom AS activite_nom, a.code AS activite_code, c.prenom, c.nom, c.email, c.telephone,
            p.reference, p.mollie_id, p.statut AS paiement_statut, p.rembourse_centimes, p.checkout_url, p.paye_le
        FROM reservations r JOIN activites a ON a.id = r.activite_id JOIN clients c ON c.id = r.client_id
        LEFT JOIN paiements p ON p.id = r.paiement_id WHERE r.id = ?');
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

function reservationPar(string $champ, string $valeur): ?array
{
    $sql = $champ === 'jeton' ? 'SELECT id FROM reservations WHERE jeton = ?' : 'SELECT r.id FROM reservations r JOIN paiements p ON p.id = r.paiement_id WHERE p.' . ($champ === 'mollie' ? 'mollie_id' : 'reference') . ' = ?';
    $s = db()->prepare($sql);
    $s->execute([$valeur]);
    $id = $s->fetchColumn();
    return $id ? reservation((int) $id) : null;
}

function titreReservation(array $r): string
{
    if ($r['type'] === 'rdv') return $r['activite_nom'];
    return $r['activite_nom'] . ', groupe du ' . dateLongue($r['debut'], false);
}

function demarrerPaiement(int $resId): array
{
    $r = reservation($resId);
    try {
        $p = mollieCreerPaiement((int) $r['montant_centimes'], titreReservation($r), $r['reference']);
    } catch (Throwable $e) {
        journaliserErreur($e);
        db()->prepare("UPDATE reservations SET statut = 'annulee', motif_annulation = 'paiement indisponible', annule_le = ? WHERE id = ?")->execute([horodatage(), $resId]);
        throw new ErreurReservation('Le paiement en ligne est momentanément indisponible. Réessayez dans quelques minutes.');
    }
    db()->prepare('UPDATE paiements SET mollie_id = ?, statut = ?, checkout_url = ?, maj_le = ? WHERE id = ?')
        ->execute([$p['id'], $p['status'] ?? 'open', $p['_links']['checkout']['href'] ?? null, horodatage(), $r['paiement_id']]);
    journal('site', 'réservation créée', "#$resId " . titreReservation($r));
    return ['reservation' => reservation($resId), 'checkout' => $p['_links']['checkout']['href'] ?? null];
}

// Appelé par le webhook Mollie et au retour du client : relit le paiement chez Mollie et met tout à jour.
function traiterPaiement(string $mollieId): ?array
{
    $r = reservationPar('mollie', $mollieId);
    if (!$r) return null;
    $p = mollieLirePaiement($mollieId);
    $statut = (string) ($p['status'] ?? 'open');
    $rembourse = isset($p['amountRefunded']['value']) ? (int) round((float) $p['amountRefunded']['value'] * 100) : (int) $r['rembourse_centimes'];
    db()->prepare('UPDATE paiements SET statut = ?, rembourse_centimes = MAX(rembourse_centimes, ?), paye_le = COALESCE(paye_le, ?), maj_le = ? WHERE id = ?')
        ->execute([$statut, $rembourse, $statut === 'paid' ? ($p['paidAt'] ?? null ? horodatage(new DateTimeImmutable($p['paidAt'])) : horodatage()) : null, horodatage(), $r['paiement_id']]);
    if ($statut === 'paid') confirmerReservation((int) $r['id']);
    elseif (in_array($statut, ['failed', 'canceled', 'expired'], true) && $r['statut'] === 'attente') {
        db()->prepare("UPDATE reservations SET statut = 'annulee', motif_annulation = 'paiement non abouti', annule_le = ? WHERE id = ? AND statut = 'attente'")->execute([horodatage(), $r['id']]);
    }
    return reservation((int) $r['id']);
}

function confirmerReservation(int $id): void
{
    $resultat = transaction(function () use ($id) {
        $r = reservation($id);
        if ($r['statut'] === 'confirmee') return 'deja';
        // Une réservation annulée par le site (délai dépassé, paiement abandonné) est rattrapée si la place est encore libre ;
        // une réservation annulée par Sandrine ou par le client ne l'est jamais.
        if ($r['statut'] === 'annulee' && !in_array($r['motif_annulation'], ['délai de paiement dépassé', 'paiement non abouti'], true)) return 'rembourser';
        if ($r['type'] === 'groupe') {
            $s = db()->prepare("SELECT g.capacite, (SELECT COUNT(*) FROM reservations WHERE groupe_id = g.id AND statut = 'confirmee') AS n FROM groupes g WHERE g.id = ?");
            $s->execute([$r['groupe_id']]);
            $g = $s->fetch();
            $libre = $g && (int) $g['n'] < (int) $g['capacite'];
        } else {
            $libre = !occupeParRdvConfirme($r['debut'], $r['fin'], $id);
        }
        if (!$libre) {
            db()->prepare("UPDATE reservations SET statut = 'annulee', motif_annulation = 'complet au moment du paiement', annule_le = ? WHERE id = ?")->execute([horodatage(), $id]);
            return 'rembourser';
        }
        db()->prepare("UPDATE reservations SET statut = 'confirmee', expire_le = NULL, motif_annulation = NULL, annule_le = NULL WHERE id = ?")->execute([$id]);
        return 'ok';
    });
    $r = reservation($id);
    if ($resultat === 'ok') {
        $factureId = creerFacture($id);
        journal('site', 'réservation confirmée', "#$id " . titreReservation($r));
        envoyerConfirmation($r, $factureId);
    } elseif ($resultat === 'rembourser') {
        $deja = (int) $r['rembourse_centimes'];
        if ($deja < (int) $r['montant_centimes']) {
            rembourser($r, (int) $r['montant_centimes'] - $deja, 'Place plus disponible');
            expedier($r['email'], 'Votre réservation n’a pas pu être confirmée',
                "Bonjour {$r['prenom']},\n\nLa place que vous vouliez réserver (" . titreReservation($r) . ") a été prise juste avant la fin de votre paiement. Vous êtes intégralement remboursé(e) : le montant apparaîtra sur votre compte sous quelques jours.\n\nN’hésitez pas à choisir une autre date sur le site." . signature());
            journal('site', 'paiement remboursé (place prise)', "#$id");
        }
    }
}

function occupeParRdvConfirme(string $debut, string $fin, int $sauf): bool
{
    $s = db()->prepare("SELECT COUNT(*) FROM reservations WHERE type = 'rdv' AND statut = 'confirmee' AND id != ? AND debut < ? AND fin > ?");
    $s->execute([$sauf, $fin, $debut]);
    return (int) $s->fetchColumn() > 0;
}

function rembourser(array $r, int $centimes, string $motif): void
{
    if ($centimes <= 0 || !$r['mollie_id']) return;
    mollieRembourser($r['mollie_id'], $centimes, $motif . ' – ' . $r['reference']);
    db()->prepare('UPDATE paiements SET rembourse_centimes = rembourse_centimes + ?, maj_le = ? WHERE id = ?')->execute([$centimes, horodatage(), $r['paiement_id']]);
    $f = db()->prepare("SELECT id FROM factures WHERE reservation_id = ? AND type = 'facture'");
    $f->execute([$r['id']]);
    if ($fid = $f->fetchColumn()) creerAvoir((int) $fid, $centimes, $motif);
}

function annulationPossibleParClient(array $r): bool
{
    if ($r['statut'] !== 'confirmee') return false;
    $limite = (new DateTimeImmutable($r['debut']))->modify('-' . paramInt('annulation_heures') . ' hours');
    return maintenant() <= $limite;
}

// $remboursement : montant en centimes à rendre (0 = aucun)
function annulerReservation(int $id, string $qui, int $remboursement, string $motif): void
{
    $r = reservation($id);
    if (!$r || $r['statut'] === 'annulee') throw new ErreurReservation('Cette réservation est déjà annulée.');
    $paye = $r['paiement_statut'] === 'paid' ? (int) $r['montant_centimes'] - (int) $r['rembourse_centimes'] : 0;
    $remboursement = max(0, min($remboursement, $paye));
    db()->prepare("UPDATE reservations SET statut = 'annulee', motif_annulation = ?, annule_le = ? WHERE id = ?")->execute([$motif, horodatage(), $id]);
    if ($remboursement > 0) rembourser($r, $remboursement, 'Annulation');
    journal($qui, 'réservation annulée', "#$id " . titreReservation($r) . ($remboursement ? ', remboursé ' . euros($remboursement) : ''));
    expedier($r['email'], 'Annulation de votre réservation',
        "Bonjour {$r['prenom']},\n\nVotre réservation est annulée : " . titreReservation($r) . ".\n" .
        ($remboursement > 0 ? 'Vous êtes remboursé(e) de ' . euros($remboursement) . ". Le montant apparaîtra sur votre compte sous quelques jours.\n" : '') . signature());
    expedier(param('email_sandrine'), 'Annulation : ' . titreReservation($r), "{$r['prenom']} {$r['nom']} : réservation annulée ($qui).\nMotif : $motif\nRemboursement : " . euros($remboursement));
}

function datesReservation(array $r): array
{
    return $r['type'] === 'groupe' && $r['groupe_id'] ? array_map(fn ($s) => [$s['debut'], $s['fin']], seancesGroupe((int) $r['groupe_id'])) : [[$r['debut'], $r['fin']]];
}

function envoyerConfirmation(array $r, ?int $factureId): void
{
    $url = rtrim(config()['url'], '/');
    $texte = "Bonjour {$r['prenom']},\n\nVotre réservation est confirmée.\n\n" . titreReservation($r) . "\n" .
        implode("\n", array_map(fn ($d) => '– ' . dateLongue($d[0]) . ', fin à ' . heure($d[1]), datesReservation($r))) .
        "\nLieu : {$r['lieu']}\nMontant réglé : " . euros((int) $r['montant_centimes']) .
        "\n\nVous pouvez annuler jusqu’à " . paramInt('annulation_heures') . " heures avant le début, avec remboursement intégral :\n$url/annuler/{$r['jeton']}\n\nVotre facture est jointe à cet e-mail." . signature();
    $joints = [['rendez-vous.ics', ics(titreReservation($r), datesReservation($r), $r['lieu'], 'res' . $r['id']), 'text/calendar']];
    if ($factureId && ($f = fichierFacture($factureId))) $joints[] = $f;
    expedier($r['email'], 'Réservation confirmée : ' . $r['activite_nom'], $texte, $joints);
    expedier(param('email_sandrine'), 'Nouvelle réservation : ' . titreReservation($r),
        "{$r['prenom']} {$r['nom']} ({$r['email']}, {$r['telephone']})\n" . titreReservation($r) . "\nDébut : " . dateLongue($r['debut']) . "\nMontant : " . euros((int) $r['montant_centimes']));
}

// Tâche planifiée : libère les places bloquées sans paiement, envoie les rappels de la veille.
function tacheReguliere(): array
{
    $n = db()->prepare("UPDATE reservations SET statut = 'annulee', motif_annulation = 'délai de paiement dépassé', annule_le = ?
        WHERE statut = 'attente' AND expire_le <= ?");
    $n->execute([horodatage(), horodatage(maintenant()->modify('-5 minutes'))]);
    $expirees = $n->rowCount();
    $demain = maintenant()->modify('+1 day')->format('Y-m-d');
    $s = db()->prepare("SELECT id FROM reservations WHERE statut = 'confirmee' AND rappel_envoye = 0 AND substr(debut, 1, 10) = ?");
    $s->execute([$demain]);
    $rappels = 0;
    foreach ($s->fetchAll(PDO::FETCH_COLUMN) as $id) {
        $r = reservation((int) $id);
        expedier($r['email'], 'Rappel : ' . $r['activite_nom'] . ' demain',
            "Bonjour {$r['prenom']},\n\nPetit rappel : " . titreReservation($r) . ', ' . dateLongue($r['debut']) . ".\nLieu : {$r['lieu']}\n\nEn cas d’empêchement, prévenez Sandrine au " . param('telephone') . '.' . signature());
        db()->prepare('UPDATE reservations SET rappel_envoye = 1 WHERE id = ?')->execute([$id]);
        $rappels++;
    }
    return ['expirees' => $expirees, 'rappels' => $rappels];
}
