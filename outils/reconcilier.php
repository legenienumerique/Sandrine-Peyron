<?php
// Réconciliation Mollie : chaque nuit (tâche planifiée) et à la main après une coupure 3a ou 3b.
//   php $HOME/sandrine-peyron/outils/reconcilier.php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
if (PHP_SAPI !== 'cli') exit;

$coupure = etatCoupure();
if ($coupure && in_array($coupure['niveau'], ['3a', '3b'], true)) { echo date('c'), " coupure {$coupure['niveau']} en cours : réconciliation après rétablissement\n"; exit; }
try {
    avecVerrouEcriture(function () {
        $f = traiterFileWebhooks();
        $r = reconcilierMollie();
        echo date('c'), " file : {$f['traites']} traité(s), {$f['echecs']} échec(s) ; paiements relus : {$r['paiements']}, remboursements : {$r['remboursements']}, rétrofacturations : {$r['retrofacturations']}, anomalies : {$r['anomalies']}\n";
    }, 60.0);
} catch (VerrouOccupe) { echo date('c'), " sauvegarde en cours : réconciliation reportée\n"; exit(1); }
catch (Throwable $e) { journaliserErreur($e); echo date('c'), " échec (curseurs inchangés, reprise au prochain passage)\n"; exit(1); }
