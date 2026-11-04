<?php
// Fausse page de paiement, pour tester le parcours en local (jamais en ligne : chargée seulement par routeur-dev.php)
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
if (!mollieFauxLocal()) { http_response_code(404); exit; }
$p = mollie('GET', 'payments/' . $m[1]);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    mollie('POST', 'statut/' . $m[1], ['status' => $_POST['statut'] === 'paid' ? 'paid' : 'canceled']);
    traiterPaiement($m[1]); // remplace le webhook, que Mollie ne peut pas appeler en local
    header('Location: ' . $p['redirectUrl'], true, 303);
    exit;
}
echo '<!doctype html><meta charset="utf-8"><title>Faux paiement (local)</title><body style="font-family:sans-serif;max-width:480px;margin:10vh auto">',
    '<p style="background:#fff3c4;padding:10px">Page de test locale. En ligne, c’est la vraie page Mollie.</p><h1>', h($p['amount']['value']), ' €</h1><p>', h($p['description']), '</p>',
    '<form method="post"><button name="statut" value="paid">Payer</button> <button name="statut" value="canceled">Abandonner</button></form>';
