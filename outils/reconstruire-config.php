<?php
// Copie de récupération de app/config.php (GitHub ne rend jamais un secret enregistré : il faut cette copie).
//   php outils/reconstruire-config.php sauver <sortie.lgnb>               sur le serveur : copie chiffrée (clé publique LGN) et signée
//   php outils/reconstruire-config.php restaurer <copie.lgnb> <cle-privee> sur un serveur vide : reconstruit app/config.php
//   php outils/reconstruire-config.php modele                             sans copie : config.php depuis config.exemple.php,
//                                                                          nouveau secret, clés à saisir à la main
// Jamais d'écrasement d'un config.php existant.
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
require_once __DIR__ . '/../app/lib/lgnb.php';
$cible = getenv('LGN_CONFIG_CIBLE') ?: __DIR__ . '/../app/config.php'; // autre cible : tests seulement
$action = $argv[1] ?? '';

if ($action === 'sauver') {
    require __DIR__ . '/../app/bootstrap.php';
    require RACINE . '/app/lib/sauvegarde.php';
    $sortie = $argv[2] ?? '';
    if ($sortie === '' || file_exists($sortie)) { echo "Sortie absente ou déjà présente.\n"; exit(1); }
    $r = lgnbCreer($sortie, ['config/config.php' => realpath($cible)], ['type' => 'config', 'date' => date('c')], clePubliqueChiffrement(), cleSignatureSite());
    echo "Copie chiffrée créée ({$r['sha256']}). À télécharger et ranger hors serveur, puis supprimer du serveur.\n";
} elseif ($action === 'restaurer') {
    if (file_exists($cible)) { echo "app/config.php existe déjà : rien n'est remplacé.\n"; exit(1); }
    [$copie, $fichierCle] = [$argv[2] ?? '', $argv[3] ?? ''];
    $paire = is_file($fichierCle) ? base64_decode(trim((string) file_get_contents($fichierCle)), true) : false;
    $pubs = [];
    foreach (array_slice($argv, 4) as $b64) { $k = base64_decode($b64, true); if ($k) $pubs[lgnbIdCle($k)] = $k; }
    if (!is_file($copie) || !$paire || !$pubs) { echo "Usage : restaurer <copie.lgnb> <cle-privee> <cle-publique-signature-base64>…\n"; exit(1); }
    $tmp = sys_get_temp_dir() . '/config-' . bin2hex(random_bytes(6));
    mkdir($tmp, 0700);
    try {
        lgnbExtraire($copie, $pubs, $paire, $tmp);
        $contenu = (string) file_get_contents("$tmp/config/config.php");
        // Contrôle sans exécuter le fichier : en-tête PHP et « return [ » attendus
        if (!str_starts_with(ltrim($contenu), '<?php') || !preg_match('/\breturn\s*\[/', $contenu)) throw new RuntimeException('contenu inattendu');
        file_put_contents($cible, $contenu);
        @chmod($cible, 0600);
        echo "app/config.php reconstruit depuis la copie de récupération.\n";
    } catch (Throwable $e) { echo 'Refus : ', $e->getMessage(), "\n"; exit(1); }
    finally { lgnbVider($tmp); @rmdir($tmp); }
} elseif ($action === 'modele') {
    if (file_exists($cible)) { echo "app/config.php existe déjà : rien n'est remplacé.\n"; exit(1); }
    $modele = (string) file_get_contents(__DIR__ . '/../app/config.exemple.php');
    $modele = preg_replace("/'secret' => ''/", "'secret' => '" . bin2hex(random_bytes(32)) . "'", $modele, 1);
    file_put_contents($cible, $modele);
    @chmod($cible, 0600);
    echo "app/config.php créé depuis le modèle (nouveau secret). À compléter : url, donnees, Mollie, SMTP, clés LGN.\n";
} else {
    echo "Usage : php outils/reconstruire-config.php sauver <sortie.lgnb> | restaurer <copie.lgnb> <cle-privee> <cle-publique-signature>… | modele\n";
    exit(1);
}
