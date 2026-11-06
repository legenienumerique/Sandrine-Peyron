<?php
// Restauration guidée (toujours sur l'essai d'abord). Exécutée en SSH ou en local, avec la clé privée LGN :
//   php outils/restaurer.php <archive.lgnb> <fichier-cle-privee>     restaurer
//   php outils/restaurer.php --annuler                               revenir à l'état d'avant (d'après le journal)
// Étapes : vérification complète et extraction isolée (site en ligne), niveau 3a + verrou exclusif, base fermée et WAL vidé,
// bascule journalisée élément par élément, contrôle, retour en ligne. Échec pendant la bascule : retour arrière automatique.
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
require RACINE . '/app/lib/sauvegarde.php';
if (PHP_SAPI !== 'cli') exit;

function etape(string $t): void { echo date('H:i:s'), "  $t\n"; }

$ts = date('Ymd-His');
if (($argv[1] ?? '') === '--annuler') {
    $r = new Restauration('', $ts);
    etape('Retour arrière d\'après le journal : ' . $r->annuler() . ' déplacement(s) annulé(s).');
    exit;
}
[$archive, $fichierCle] = [$argv[1] ?? '', $argv[2] ?? ''];
if (!is_file($archive) || !is_file($fichierCle)) { echo "Usage : php outils/restaurer.php <archive.lgnb> <fichier-cle-privee>\n"; exit(1); }
$paire = base64_decode(trim((string) file_get_contents($fichierCle)), true);
if (!$paire || strlen($paire) !== SODIUM_CRYPTO_BOX_KEYPAIRBYTES) { echo "Clé privée illisible.\n"; exit(1); }

// 1. Vérification complète et extraction dans un dossier isolé (le site reste en ligne)
$prep = dossierDonnees() . "/restauration-$ts";
mkdir($prep, 0700);
try {
    $m = lgnbExtraire($archive, clesSignaturePubliques(), $paire, $prep);
} catch (Throwable $e) { @rmdir($prep); etape('Archive refusée : ' . $e->getMessage()); exit(1); }
sodium_memzero($paire);
etape('Archive vérifiée (signature, empreintes, fin) : ' . count($m['fichiers']) . ' fichier(s).');
if ((new PDO('sqlite:' . "$prep/base/site.sqlite"))->query('PRAGMA integrity_check')->fetchColumn() !== 'ok') { etape('Base extraite corrompue : arrêt.'); exit(1); }
etape('Base extraite intègre.');

// 2. Blocage des écritures : niveau 3a (webhooks en file), restauration signalée, verrou exclusif
$coupure = fichierCoupure();
$coupureCreee = !is_file($coupure);
if ($coupureCreee) file_put_contents($coupure, json_encode(['niveau' => '3a', 'type' => 'maintenance', 'message' => 'Restauration en cours', 'depuis' => date('Y-m-d H:i:s'), 'par' => 'restauration']));
touch(dossierDonnees() . '/restauration-en-cours');
$verrou = prendreVerrou(true, 120.0);
$r = new Restauration($prep, $ts);
try {
    if ($verrou === false) throw new RuntimeException('Écritures encore en cours après 2 minutes.');
    etape('Écritures bloquées (niveau 3a, verrou exclusif).');
    preparerBasePourBascule();
    etape('Base fermée, journal WAL vidé.');
    $r->basculer();
    etape('Bascule faite. Ancienne version gardée : ' . $r->ancien);
    $pdo = new PDO('sqlite:' . fichierBase());
    if ($pdo->query('PRAGMA integrity_check')->fetchColumn() !== 'ok') throw new RuntimeException('Base restaurée illisible.');
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo = null;
    etape('Contrôle de la base restaurée : correct.');
} catch (Throwable $e) {
    etape('Échec : ' . $e->getMessage() . ' — retour arrière.');
    try { etape($r->annuler() . ' déplacement(s) annulé(s).'); } catch (Throwable $e2) { etape('Retour arrière incomplet : ' . $e2->getMessage() . ' (voir ' . $r->journal . ')'); }
    $echec = true;
} finally {
    if ($verrou) { flock($verrou, LOCK_UN); fclose($verrou); }
    @unlink(dossierDonnees() . '/restauration-en-cours');
    if ($coupureCreee) @unlink($coupure);
}
if (!empty($echec)) exit(1);
etape('Site rouvert. Étape suivante : php outils/reconcilier.php (file des webhooks et paiements Mollie).');
