<?php
// Création des clés de sauvegarde (jamais de clé privée dans le dépôt ni dans une sauvegarde).
//   php outils/cles.php chiffrement <fichier>   sur le PC d'Eliott : paire de déchiffrement (à ranger hors serveur, 2 copies)
//                                               affiche la clé publique à mettre dans config « sauvegarde.cle_publique »
//   php outils/cles.php signature                sur le serveur du site : clé de signature dans donnees/cles/ (0600)
//                                               affiche la clé publique à mettre dans le back-office et sur le PC
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit;
$type = $argv[1] ?? '';
if ($type === 'chiffrement') {
    $f = $argv[2] ?? '';
    if ($f === '' || file_exists($f)) { echo "Fichier de sortie absent ou déjà présent (jamais d'écrasement).\n"; exit(1); }
    $paire = sodium_crypto_box_keypair();
    file_put_contents($f, base64_encode($paire) . "\n");
    @chmod($f, 0600);
    echo 'Clé publique de chiffrement : ', base64_encode(sodium_crypto_box_publickey($paire)), "\n";
} elseif ($type === 'signature') {
    require __DIR__ . '/../app/bootstrap.php';
    $f = dossierDonnees('cles') . '/signature.key';
    if (file_exists($f)) { echo "Clé de signature déjà présente : rotation = renommer l'ancienne puis relancer.\n"; exit(1); }
    $paire = sodium_crypto_sign_keypair();
    file_put_contents($f, base64_encode(sodium_crypto_sign_secretkey($paire)) . "\n");
    @chmod($f, 0600);
    echo 'Clé publique de signature : ', base64_encode(sodium_crypto_sign_publickey($paire)), "\n";
} else {
    echo "Usage : php outils/cles.php chiffrement <fichier> | signature\n";
    exit(1);
}
