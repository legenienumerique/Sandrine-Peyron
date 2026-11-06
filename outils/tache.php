<?php
// Tâche planifiée alwaysdata, toutes les 5 minutes : php $HOME/sandrine-peyron/outils/tache.php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
if (PHP_SAPI !== 'cli') exit;

// Coupure 3b (incident de sécurité) : aucune tâche. 3a : seulement les tâches indispensables (aucun e-mail de rappel).
$coupure = etatCoupure();
if ($coupure && $coupure['niveau'] === '3b') { echo date('c'), " coupure 3b : tâche suspendue\n"; exit; }
try {
    avecVerrouEcriture(function () use ($coupure) {
        horodaterTache();
        if ($coupure && $coupure['niveau'] === '3a') { echo date('c'), " coupure 3a : webhooks gardés en file\n"; return; }
        $r = tacheReguliere();
        $f = traiterFileWebhooks(); // après rétablissement : webhooks reçus pendant la coupure ou la sauvegarde
        echo date('c'), " places libérées : {$r['expirees']}, rappels : {$r['rappels']}, webhooks en file traités : {$f['traites']}\n";
    }, 30.0);
} catch (VerrouOccupe) { echo date('c'), " sauvegarde en cours : tâche reportée\n"; }
catch (Throwable $e) { journaliserErreur($e); exit(1); }
