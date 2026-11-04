<?php
// Tests des parcours : php tests/tests.php
// Base en mémoire, faux Mollie en mémoire (aucun appel réseau), e-mails capturés.
declare(strict_types=1);

$GLOBALS['CONFIG_TEST'] = ['secret' => str_repeat('t', 40), 'url' => 'https://exemple.test', 'donnees' => sys_get_temp_dir() . '/sp-tests', 'dompdf' => '-'];
require __DIR__ . '/../app/bootstrap.php';

$ok = 0; $ko = 0;
function verifier(string $nom, bool $condition): void
{
    global $ok, $ko;
    if ($condition) { $ok++; echo "  ok  $nom\n"; } else { $ko++; echo "  ÉCHEC  $nom\n"; }
}
function attendreErreur(string $nom, callable $f, string $contient = ''): void
{
    try { $f(); verifier($nom . ' (aucune erreur levée)', false); }
    catch (Throwable $e) { verifier($nom, $contient === '' || str_contains($e->getMessage(), $contient)); }
}

// Faux Mollie : garde les paiements en mémoire
$GLOBALS['FAUX'] = [];
$GLOBALS['MOLLIE_TEST'] = function (string $m, string $chemin, ?array $corps) {
    if ($m === 'POST' && $chemin === 'payments') {
        $id = 'tr_' . count($GLOBALS['FAUX']) . 'x';
        $GLOBALS['FAUX'][$id] = ['id' => $id, 'status' => 'open', 'amount' => $corps['amount'], 'amountRefunded' => ['value' => '0.00'], '_links' => ['checkout' => ['href' => "https://mollie.test/$id"]], 'webhook' => $corps['webhookUrl'] ?? null];
        return $GLOBALS['FAUX'][$id];
    }
    if ($m === 'GET' && preg_match('#^payments/(\w+)$#', $chemin, $x)) return $GLOBALS['FAUX'][$x[1]];
    if ($m === 'POST' && preg_match('#^payments/(\w+)/refunds$#', $chemin, $x)) {
        $p = &$GLOBALS['FAUX'][$x[1]];
        $p['amountRefunded']['value'] = number_format((float) $p['amountRefunded']['value'] + (float) $corps['amount']['value'], 2, '.', '');
        return ['id' => 're_1', 'status' => 'pending'];
    }
    throw new RuntimeException("Faux Mollie : appel inattendu $m $chemin");
};
function payer(string $mollieId, string $statut = 'paid'): ?array { $GLOBALS['FAUX'][$mollieId]['status'] = $statut; return traiterPaiement($mollieId); }

function nouvelleBase(string $date): void
{
    $GLOBALS['MAINTENANT_TEST'] = $date;
    $GLOBALS['DB_TEST'] = ouvrirBase(':memory:');
    $GLOBALS['EMAILS_TEST'] = [];
    $GLOBALS['FAUX'] = [];
}
function client(int $n): array { return ['prenom' => "Prénom$n", 'nom' => "Nom$n", 'email' => "client$n@exemple.test", 'telephone' => '06 00 00 00 0' . ($n % 10), 'consentement' => '1']; }
function creerGroupe(string $code, int $capacite, string $premiere, int $nb = 3, ?int $tarif = null): int
{
    $a = activite($code);
    db()->prepare("INSERT INTO groupes (activite_id, capacite, tarif_centimes, statut, cree_le) VALUES (?, ?, ?, 'publie', ?)")->execute([$a['id'], $capacite, $tarif, horodatage()]);
    $g = (int) db()->lastInsertId();
    for ($i = 0; $i < $nb; $i++) {
        $d = (new DateTimeImmutable($premiere))->modify("+$i week");
        db()->prepare('INSERT INTO seances_groupe (groupe_id, debut, fin) VALUES (?, ?, ?)')->execute([$g, horodatage($d), horodatage($d->modify('+90 minutes'))]);
    }
    return $g;
}
function etat(string $code, int $groupeId): ?array { foreach (groupesActivite(activite($code)) as $g) if ((int) $g['id'] === $groupeId) return $g; return null; }

echo "Groupes : ouverture séquentielle et paiement\n";
nouvelleBase('2026-11-03 10:00:00');
db()->exec("UPDATE activites SET tarif_centimes = 12000 WHERE code = 'parentalite'");
$g1 = creerGroupe('parentalite', 2, '2026-11-19 18:30:00');
$g2 = creerGroupe('parentalite', 2, '2026-12-17 18:30:00');
verifier('groupe 1 ouvert', etat('parentalite', $g1)['etat'] === 'ouvert');
verifier('groupe 2 verrouillé', etat('parentalite', $g2)['etat'] === 'verrouille');
attendreErreur('groupe 2 non réservable', fn () => reserverGroupe($g2, client(9)), 'plus de place');
$a = reserverGroupe($g1, client(1));
verifier('lien de paiement Mollie', $a['checkout'] === 'https://mollie.test/tr_0x');
verifier('webhook transmis à Mollie', $GLOBALS['FAUX']['tr_0x']['webhook'] === 'https://exemple.test/webhook/mollie');
verifier('place bloquée pendant le paiement', etat('parentalite', $g1)['restantes'] === 1);
attendreErreur('double inscription refusée', fn () => reserverGroupe($g1, client(1)), 'déjà inscrit');
$r = payer('tr_0x');
verifier('réservation confirmée après paiement', $r['statut'] === 'confirmee');
verifier('facture F-2026-0001 émise', db()->query("SELECT numero FROM factures")->fetchColumn() === 'F-2026-0001');
verifier('e-mail de confirmation au client', (bool) array_filter($GLOBALS['EMAILS_TEST'], fn ($e) => $e['a'] === 'client1@exemple.test' && str_contains($e['sujet'], 'confirmée')));
verifier('e-mail avec invitation .ics et facture', (bool) array_filter($GLOBALS['EMAILS_TEST'], fn ($e) => count($e['joints']) === 2));
verifier('e-mail à Sandrine', (bool) array_filter($GLOBALS['EMAILS_TEST'], fn ($e) => $e['a'] === 'sophro.zen.relax@gmail.com'));
payer('tr_0x');
verifier('webhook rejoué : pas de seconde facture', (int) db()->query('SELECT COUNT(*) FROM factures')->fetchColumn() === 1);
$b = reserverGroupe($g1, client(2));
verifier('dernière place en paiement : état « attente »', etat('parentalite', $g1)['etat'] === 'attente');
verifier('groupe 2 toujours verrouillé tant que non payé', etat('parentalite', $g2)['etat'] === 'verrouille');
attendreErreur('3e personne refusée sur la dernière place bloquée', fn () => reserverGroupe($g1, client(3)), 'plus de place');
payer('tr_1x');
verifier('groupe 1 complet', etat('parentalite', $g1)['etat'] === 'complet');
verifier('groupe 2 ouvert automatiquement', etat('parentalite', $g2)['etat'] === 'ouvert');

echo "Paiement abandonné, délai dépassé, paiement tardif\n";
$c = reserverGroupe($g2, client(3));
payer('tr_2x', 'canceled');
verifier('paiement annulé : place libérée', reservation((int) $c['reservation']['id'])['statut'] === 'annulee' && etat('parentalite', $g2)['restantes'] === 2);
$d = reserverGroupe($g2, client(4));
$GLOBALS['MAINTENANT_TEST'] = '2026-11-03 10:40:00';
$t = tacheReguliere();
verifier('tâche planifiée : place bloquée libérée', $t['expirees'] === 1 && etat('parentalite', $g2)['restantes'] === 2);
payer('tr_3x');
verifier('paiement tardif rattrapé si la place est libre', reservation((int) $d['reservation']['id'])['statut'] === 'confirmee');

echo "Paiement tardif sur un groupe devenu complet : remboursement automatique\n";
$e1 = reserverGroupe($g2, client(5));
$GLOBALS['MAINTENANT_TEST'] = '2026-11-03 11:20:00';
tacheReguliere();
$e2 = reserverGroupe($g2, client(6));
payer('tr_5x');
$r = payer('tr_4x');
verifier('réservation tardive annulée', $r['statut'] === 'annulee');
verifier('remboursement intégral demandé à Mollie', $GLOBALS['FAUX']['tr_4x']['amountRefunded']['value'] === '120.00');
verifier('pas de facture pour la place perdue', (int) db()->query("SELECT COUNT(*) FROM factures WHERE reservation_id = {$e1['reservation']['id']}")->fetchColumn() === 0);

echo "Annulations\n";
$res = reservation((int) $a['reservation']['id']);
verifier('annulation possible plus de 48 h avant', annulationPossibleParClient($res));
annulerReservation((int) $res['id'], 'client', (int) $res['montant_centimes'], 'Annulée par le client');
verifier('remboursé chez Mollie', $GLOBALS['FAUX']['tr_0x']['amountRefunded']['value'] === '120.00');
verifier('avoir A-2026-0001 de -120 €', (int) db()->query("SELECT montant_centimes FROM factures WHERE numero = 'A-2026-0001'")->fetchColumn() === -12000);
verifier('place rendue au groupe 1', etat('parentalite', $g1)['confirmes'] === 1);
attendreErreur('double annulation refusée', fn () => annulerReservation((int) $res['id'], 'client', 0, 'x'), 'déjà annulée');
$GLOBALS['MAINTENANT_TEST'] = '2026-11-18 20:00:00';
verifier('annulation client refusée moins de 48 h avant', !annulationPossibleParClient(reservation((int) $b['reservation']['id'])));
annulerReservation((int) $b['reservation']['id'], 'Sandrine', 6000, 'Geste commercial');
verifier('Sandrine peut rembourser en partie', $GLOBALS['FAUX']['tr_1x']['amountRefunded']['value'] === '60.00');
$GLOBALS['MAINTENANT_TEST'] = '2026-11-20 10:00:00';
verifier('groupe commencé : inscriptions fermées', etat('parentalite', $g1)['etat'] === 'commence');

echo "Tarifs réglables\n";
nouvelleBase('2026-11-03 10:00:00');
$g = creerGroupe('deuil', 6, '2026-11-23 18:00:00');
verifier('sans tarif : groupe non réservable', etat('deuil', $g)['reservable'] === false);
attendreErreur('réservation refusée sans tarif', fn () => reserverGroupe($g, client(1)), 'tarif');
db()->exec("UPDATE activites SET tarif_centimes = 9000 WHERE code = 'deuil'");
verifier('tarif de l’activité appliqué', etat('deuil', $g)['tarif'] === 9000);
db()->exec("UPDATE groupes SET tarif_centimes = 7500 WHERE id = $g");
verifier('tarif propre au groupe prioritaire', etat('deuil', $g)['tarif'] === 7500);
verifier('saisie « 75,50 € » → 7550', versCentimes('75,50 €') === 7550);
attendreErreur('saisie invalide refusée', fn () => versCentimes('abc'), 'invalide');

echo "Rendez-vous individuels\n";
nouvelleBase('2026-11-03 10:00:00'); // mardi
db()->exec("UPDATE activites SET tarif_centimes = 6000 WHERE code = 'seance'");
// Seconde offre individuelle plus longue, créée pour le test (le site n'en propose qu'une)
db()->exec("INSERT INTO activites (code, type, nom, duree_minutes, tarif_centimes) VALUES ('longue', 'individuel', 'Séance longue', 90, 8000)");
db()->exec("INSERT INTO disponibilites (jour, debut, fin, lieu) VALUES (3, '09:00', '12:00', 'Cabinet'), (4, '14:00', '17:00', 'Visio')");
$sophro = activite('seance');
$mer = creneauxDuJour($sophro, '2026-11-04');
verifier('mercredi : moins de 24 h → aucun créneau avant 10 h', $mer[0]['debut'] === '2026-11-04 10:15:00');
$jeu = creneauxDuJour($sophro, '2026-11-05');
verifier('jeudi : créneaux 14 h, 15 h 15 (1 h + 15 min de battement)', array_column($jeu, 'debut') === ['2026-11-05 14:00:00', '2026-11-05 15:15:00']);
verifier('lieu repris de la plage', $jeu[0]['lieu'] === 'Visio');
$x = reserverRdv('seance', '2026-11-05 14:00:00', client(1));
verifier('créneau bloqué pendant le paiement', array_column(creneauxDuJour($sophro, '2026-11-05'), 'debut') === ['2026-11-05 15:15:00']);
attendreErreur('même créneau refusé', fn () => reserverRdv('seance', '2026-11-05 14:00:00', client(2)), 'vient d’être pris');
attendreErreur('créneau inventé refusé', fn () => reserverRdv('seance', '2026-11-05 14:30:00', client(2)), 'vient d’être pris');
payer('tr_0x');
verifier('rendez-vous confirmé', reservation((int) $x['reservation']['id'])['statut'] === 'confirmee');
$emdr = activite('longue');
verifier('séance de 1 h 30 : plus de place le jeudi après le rendez-vous de 14 h', creneauxDuJour($emdr, '2026-11-05') === []);
db()->exec("INSERT INTO absences (debut, fin, motif) VALUES ('2026-11-11 00:00:00', '2026-11-12 00:00:00', 'Congés')");
verifier('absence : aucun créneau', creneauxDuJour($sophro, '2026-11-11') === []);
$gs = creerGroupe('sophro-adultes', 8, '2026-11-18 09:30:00', 1);
verifier('une seule offre individuelle dans une base neuve', (int) ouvrirBase(':memory:')->query("SELECT COUNT(*) FROM activites WHERE type = 'individuel' AND actif = 1")->fetchColumn() === 1);
verifier('séance de groupe : créneaux qui la chevauchent retirés', array_column(creneauxDuJour($sophro, '2026-11-18'), 'debut') === []);
db()->exec("UPDATE disponibilites SET activites = 'longue' WHERE jour = 3");
verifier('plage réservée à une offre : l’autre n’y est pas proposée', creneauxDuJour($sophro, '2026-11-25') === [] && count(creneauxDuJour($emdr, '2026-11-25')) === 1);

echo "Factures\n";
$f = facture(1);
verifier('facture figée : client et montant', $f['donnees']['client']['nom'] === 'Prénom1 Nom1' && (int) $f['montant_centimes'] === 6000);
verifier('mention TVA présente', str_contains(htmlFacture($f), '293 B'));
definirParam('facture_nom', 'Autre nom');
verifier('changement de paramètre sans effet sur une facture émise', facture(1)['donnees']['emetteur']['nom'] === 'Sandrine Peyron');

echo "Articles\n";
$id = enregistrerArticle(['titre' => 'Première séance d’EMDR', 'categorie' => 'article', 'contenu' => "## Titre\n\nTexte **gras** <script>x</script>\n\n- un\n- deux", 'statut' => 'brouillon']);
verifier('brouillon invisible sur le site', articlesPublies() === []);
enregistrerArticle(['titre' => 'Première séance d’EMDR', 'categorie' => 'article', 'contenu' => article($id)['contenu'], 'statut' => 'publie'], $id);
verifier('publié : visible', count(articlesPublies()) === 1 && articlePublie('premiere-seance-d-emdr') !== null);
$html = texteEnHtml(article($id)['contenu']);
verifier('mise en forme : titre, gras, liste', str_contains($html, '<h2>Titre</h2>') && str_contains($html, '<strong>gras</strong>') && str_contains($html, '<li>deux</li>'));
verifier('HTML saisi neutralisé', !str_contains($html, '<script>'));
verifier('lien javascript: refusé', !str_contains(texteEnHtml('[x](javascript:alert(1))'), 'href'));
$id2 = enregistrerArticle(['titre' => 'Première séance d’EMDR', 'categorie' => 'compte_rendu', 'statut' => 'publie']);
verifier('adresse unique pour un même titre', article($id2)['slug'] === 'premiere-seance-d-emdr-2');

echo "Connexion\n";
attendreErreur('mot de passe trop court refusé', fn () => creerUtilisateur('s@exemple.test', 'court'), '12 caractères');
creerUtilisateur('s@exemple.test', 'un mot de passe long');
$secret = base32Encoder(random_bytes(20));
verifier('base32 aller-retour', base32Encoder(base32Decoder($secret)) === $secret);
verifier('code RFC 6238 de référence', codeTotp(base32Encoder('12345678901234567890'), intdiv(59, 30)) === '287082');
$pas = intdiv(maintenant()->getTimestamp(), 30);
verifier('code courant accepté', verifierTotp($secret, codeTotp($secret, $pas), 0) === $pas);
verifier('code rejoué refusé', verifierTotp($secret, codeTotp($secret, $pas), $pas) === null);
verifier('code faux refusé', verifierTotp($secret, '000000', 0) === null || codeTotp($secret, $pas) === '000000');
for ($i = 0; $i < 5; $i++) noterEchec('ip:1');
verifier('blocage après 5 échecs', tropDEssais('ip:1') && !tropDEssais('ip:2'));
verifier('jeton de formulaire falsifié refusé', !verifierJetonFormulaire('123.abc'));
$t = (string) (time() - 10);
verifier('jeton de formulaire valide accepté', verifierJetonFormulaire($t . '.' . hash_hmac('sha256', "form|$t", config()['secret'])));
verifier('formulaire envoyé en moins de 2 s refusé (robot)', !verifierJetonFormulaire(jetonFormulaire()));

echo "\n$ok réussis, $ko échoués\n";
exit($ko ? 1 : 0);
