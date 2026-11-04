<?php
// Données de démonstration, sur l'ordinateur uniquement (refusé si dev = false). Tarifs fictifs.
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
if (PHP_SAPI !== 'cli' || !config()['dev']) exit("Refusé : réservé au développement local.\n");
$pdo = db();
$pdo->exec("UPDATE activites SET tarif_centimes = CASE type WHEN 'individuel' THEN 6000 ELSE 12000 END WHERE code != 'psychanalyse'");
$pdo->exec("DELETE FROM disponibilites; INSERT INTO disponibilites (jour, debut, fin, lieu, activites) VALUES
  (1, '09:00', '12:00', 'Cabinet, Belleville-en-Beaujolais', ''), (3, '14:00', '18:00', 'Cabinet, Belleville-en-Beaujolais', ''), (5, '09:00', '12:00', 'Visio', 'sophrologie')");
$lundi = new DateTimeImmutable('monday next week');
$groupes = [['parentalite', 8, '+3 days 18:30', 6], ['parentalite', 8, '+52 days 18:30', 6], ['aidants', 6, '+1 day 14:00', 5], ['deuil', 6, '+7 days 18:00', 6], ['sophro-ados', 8, '+2 days 14:00', 8], ['sophro-adultes', 10, '+4 days 18:00', 8]];
foreach ($groupes as [$code, $cap, $quand, $nb]) {
    $a = activite($code);
    $pdo->prepare("INSERT INTO groupes (activite_id, capacite, statut, cree_le) VALUES (?, ?, 'publie', ?)")->execute([$a['id'], $cap, horodatage()]);
    $g = (int) $pdo->lastInsertId();
    $d = $lundi->modify($quand);
    for ($i = 0; $i < $nb; $i++) $pdo->prepare('INSERT INTO seances_groupe (groupe_id, debut, fin) VALUES (?, ?, ?)')->execute([$g, horodatage($d->modify("+$i week")), horodatage($d->modify("+$i week")->modify('+' . $a['duree_minutes'] . ' minutes'))]);
}
enregistrerArticle(['titre' => 'Comment se déroule une première séance d’EMDR-DSA ?', 'categorie' => 'article', 'statut' => 'publie',
    'resume' => 'Un temps d’échange, puis le choix d’une « clé d’entrée » : une situation, une émotion, un ressenti ou une pensée.',
    'contenu' => "Publication de démonstration, à remplacer par un vrai texte de Sandrine.\n\n## La première rencontre\n\nOn commence par **faire connaissance** et par définir ce qui vous amène.\n\n- une situation passée ou à venir\n- une émotion\n- un ressenti dans le corps\n- une pensée négative"]);
if (!$pdo->query('SELECT COUNT(*) FROM utilisateurs')->fetchColumn()) creerUtilisateur('sandrine@exemple.test', 'demo-local-uniquement');
echo "Démo créée. Connexion : sandrine@exemple.test / demo-local-uniquement\n";
