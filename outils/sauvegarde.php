<?php
// Sauvegarde de nuit (tâche planifiée alwaysdata) : php $HOME/sandrine-peyron/outils/sauvegarde.php
// Base + médias copiés ensemble sous verrou exclusif, archive LGNB chiffrée et signée, copie externe si configurée.
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
require RACINE . '/app/lib/sauvegarde.php';
if (PHP_SAPI !== 'cli') exit;

try {
    $e = sauvegarder();
    echo date('c'), " sauvegarde {$e['fichier']} ({$e['fichiers']} fichiers), vérifiée, copie externe : {$e['externe']}, anciennes retirées : {$e['purgees']}\n";
    if ($e['externe'] === 'échec') exit(2);
} catch (Throwable $ex) {
    journaliserErreur('Sauvegarde en échec : ' . $ex->getMessage());
    @file_put_contents(dossierDonnees() . '/derniere-sauvegarde-echec.json', json_encode(['date' => date('c')]), LOCK_EX);
    echo date('c'), " échec de la sauvegarde\n";
    exit(1);
}
