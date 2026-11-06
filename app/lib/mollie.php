<?php
// Client Mollie minimal (API v2). Le webhook est la seule source de vérité pour « payé ».
declare(strict_types=1);

function mollieActif(): bool { return config()['mollie_cle'] !== '' || isset($GLOBALS['MOLLIE_TEST']); }

function mollie(string $methode, string $chemin, ?array $corps = null): array
{
    // Tests automatiques : un faux Mollie en mémoire remplace l'appel réseau
    if (isset($GLOBALS['MOLLIE_TEST'])) return ($GLOBALS['MOLLIE_TEST'])($methode, $chemin, $corps);
    if (mollieFauxLocal()) return mollieFaux($methode, $chemin, $corps);
    $cle = config()['mollie_cle'];
    if ($cle === '') throw new RuntimeException('Paiement en ligne non configuré (clé Mollie absente).');
    $c = curl_init('https://api.mollie.com/v2/' . ltrim($chemin, '/'));
    curl_setopt_array($c, [
        CURLOPT_CUSTOMREQUEST => $methode,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $cle, 'Content-Type: application/json', 'Accept: application/json'],
    ]);
    if ($corps !== null) curl_setopt($c, CURLOPT_POSTFIELDS, json_encode($corps, JSON_UNESCAPED_UNICODE));
    $rep = curl_exec($c);
    $code = curl_getinfo($c, CURLINFO_RESPONSE_CODE);
    $err = curl_error($c);
    curl_close($c);
    if ($rep === false) throw new RuntimeException("Mollie injoignable : $err");
    $json = json_decode((string) $rep, true) ?: [];
    if ($code >= 400) throw new RuntimeException('Mollie : ' . ($json['detail'] ?? "erreur $code"));
    return $json;
}

function mollieMontant(int $centimes): array
{
    return ['currency' => 'EUR', 'value' => number_format($centimes / 100, 2, '.', '')];
}

function mollieCreerPaiement(int $centimes, string $description, string $reference): array
{
    $url = rtrim(config()['url'], '/');
    $corps = [
        'amount' => mollieMontant($centimes),
        'description' => mb_substr($description, 0, 255),
        'redirectUrl' => "$url/reservation/$reference",
        'metadata' => ['reference' => $reference],
        'locale' => 'fr_FR',
    ];
    // Mollie refuse un webhook local : en développement, le statut est relu au retour du client
    if (!str_contains($url, 'localhost') && !str_contains($url, '127.0.0.1')) $corps['webhookUrl'] = "$url/webhook/mollie";
    return mollie('POST', 'payments', $corps);
}

function mollieLirePaiement(string $id): array { return mollie('GET', 'payments/' . rawurlencode($id)); }

function mollieRembourser(string $id, int $centimes, string $description): array
{
    return mollie('POST', 'payments/' . rawurlencode($id) . '/refunds', ['amount' => mollieMontant($centimes), 'description' => mb_substr($description, 0, 140)]);
}

// --- Développement local uniquement : faux Mollie pour tester le parcours dans le navigateur ---
// Actif seulement si config dev = true ET mollie_cle = 'faux-local'. Jamais en ligne.
function mollieFauxLocal(): bool { return config()['dev'] === true && config()['mollie_cle'] === 'faux-local'; }

function mollieFaux(string $methode, string $chemin, ?array $corps): array
{
    $f = dossierDonnees() . '/faux-mollie.json';
    $base = is_file($f) ? json_decode(file_get_contents($f), true) : [];
    if ($methode === 'POST' && $chemin === 'payments') {
        $id = 'tr_faux' . bin2hex(random_bytes(4));
        $base[$id] = ['id' => $id, 'status' => 'open', 'amount' => $corps['amount'], 'description' => $corps['description'], 'redirectUrl' => $corps['redirectUrl'], 'metadata' => $corps['metadata'] ?? null,
            'amountRefunded' => ['value' => '0.00'], '_links' => ['checkout' => ['href' => rtrim(config()['url'], '/') . "/dev/paiement/$id"]]];
    } elseif (preg_match('#^payments/(\w+)$#', $chemin, $m) && $methode === 'GET') {
        return $base[$m[1]] ?? throw new RuntimeException('Faux Mollie : paiement inconnu');
    } elseif (preg_match('#^payments/(\w+)/refunds$#', $chemin, $m)) {
        $base[$m[1]]['amountRefunded']['value'] = number_format((float) $base[$m[1]]['amountRefunded']['value'] + (float) $corps['amount']['value'], 2, '.', '');
        $id = $m[1];
    } elseif (preg_match('#^statut/(\w+)$#', $chemin, $m)) {
        $base[$m[1]]['status'] = $corps['status'];
        if ($corps['status'] === 'paid') $base[$m[1]]['paidAt'] = date('c');
        $id = $m[1];
    }
    file_put_contents($f, json_encode($base, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    return $base[$id];
}
