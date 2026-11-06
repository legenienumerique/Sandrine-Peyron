<?php
declare(strict_types=1);

foreach (['base', 'mollie', 'mail', 'agenda', 'reservations', 'factures', 'articles', 'auth', 'exploitation', 'reconciliation', 'demandes'] as $lib) require_once __DIR__ . "/lib/$lib.php";
