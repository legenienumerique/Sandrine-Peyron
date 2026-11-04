<?php
// Tâche planifiée alwaysdata, toutes les 5 minutes : php $HOME/sandrine-peyron/outils/tache.php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
if (PHP_SAPI !== 'cli') exit;
try { $r = tacheReguliere(); echo date('c'), " places libérées : {$r['expirees']}, rappels : {$r['rappels']}\n"; }
catch (Throwable $e) { journaliserErreur($e); exit(1); }
