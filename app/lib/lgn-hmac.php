<?php
// Kit LGN — authentification HMAC entre un site client et le back-office LGN (version 1).
// Copié tel quel dans chaque site (aucune dépendance). Source unique : back-office/kit/php/lgn-hmac.php.
// Spécification : OneDrive\…\LGN\Site-web\integration-site-client.md, section « HMAC ».
declare(strict_types=1);

const LGN_HMAC_VERSION = 'LGN-HMAC-v1';
const LGN_HMAC_FENETRE = 300;   // ±5 minutes
const LGN_HMAC_NONCE_TTL = 600; // nonces gardés 10 minutes

final class LgnHmacRefus extends RuntimeException {}

function lgnHmacEncoder(string $s): string
{
    return rawurlencode($s); // RFC 3986 : seuls A-Z a-z 0-9 - _ . ~ restent tels quels
}

// Hôte : minuscules, sans port par défaut. Jamais X-Forwarded-Host (aucun proxy de confiance).
function lgnHmacHote(string $hote): string
{
    $hote = strtolower(trim($hote));
    return preg_replace('/:(443|80)$/', '', $hote);
}

// Chemin : chaque segment décodé puis réencodé RFC 3986 (une seule forme possible)
function lgnHmacChemin(string $chemin): string
{
    if ($chemin === '' || $chemin[0] !== '/') throw new LgnHmacRefus('chemin invalide');
    $segments = array_map(function (string $seg): string {
        $d = rawurldecode($seg);
        if (!mb_check_encoding($d, 'UTF-8') || str_contains($d, "\0")) throw new LgnHmacRefus('chemin non encodable');
        return lgnHmacEncoder($d);
    }, explode('/', $chemin));
    return implode('/', $segments);
}

// Requête : paires décodées, réencodées RFC 3986, triées par clé puis valeur. Clé dupliquée = refus.
function lgnHmacRequete(string $requete): string
{
    if ($requete === '') return '';
    $paires = [];
    $vues = [];
    foreach (explode('&', $requete) as $morceau) {
        if ($morceau === '') throw new LgnHmacRefus('requête mal formée');
        [$k, $v] = array_pad(explode('=', $morceau, 2), 2, '');
        $k = rawurldecode($k);
        $v = rawurldecode($v);
        foreach ([$k, $v] as $x) if (!mb_check_encoding($x, 'UTF-8') || str_contains($x, "\0")) throw new LgnHmacRefus('requête non encodable');
        if ($k === '' || isset($vues[$k])) throw new LgnHmacRefus('paramètre vide ou dupliqué');
        $vues[$k] = true;
        $paires[] = [lgnHmacEncoder($k), lgnHmacEncoder($v)];
    }
    usort($paires, fn ($a, $b) => strcmp($a[0], $b[0]) ?: strcmp($a[1], $b[1]));
    return implode('&', array_map(fn ($p) => $p[0] . '=' . $p[1], $paires));
}

function lgnHmacChaine(string $methode, string $hote, string $chemin, string $requete, string $date, string $nonce, string $cleId, string $corps): string
{
    return implode("\n", [
        LGN_HMAC_VERSION, strtoupper($methode), lgnHmacHote($hote), lgnHmacChemin($chemin), lgnHmacRequete($requete),
        $date, $nonce, $cleId, hash('sha256', $corps),
    ]);
}

// Côté appelant : en-têtes à joindre à la requête
function lgnHmacSigner(string $cleId, string $secretHex, string $methode, string $url, string $corps = '', ?int $maintenant = null): array
{
    $u = parse_url($url);
    if (($u['scheme'] ?? '') !== 'https' && !in_array($u['host'] ?? '', ['localhost', '127.0.0.1'], true)) throw new LgnHmacRefus('HTTPS obligatoire');
    $date = (string) ($maintenant ?? time());
    $nonce = bin2hex(random_bytes(16));
    $hote = ($u['host'] ?? '') . (isset($u['port']) ? ':' . $u['port'] : '');
    $sig = hash_hmac('sha256', lgnHmacChaine($methode, $hote, $u['path'] ?? '/', $u['query'] ?? '', $date, $nonce, $cleId, $corps), hex2bin($secretHex));
    return ["X-LGN-Cle: $cleId", "X-LGN-Date: $date", "X-LGN-Nonce: $nonce", "X-LGN-Signature: $sig"];
}

// Côté destinataire. $cles : [identifiant => secret hexadécimal] (courante et, pendant une rotation, précédente).
// $nonceNeuf(cleId, nonce, date) : enregistre le nonce et renvoie false s'il existait déjà (contrainte UNIQUE).
// Lève LgnHmacRefus avec une raison courte (jamais le secret ni la signature).
function lgnHmacVerifier(array $cles, string $hoteAttendu, array $serveur, string $corps, callable $nonceNeuf, ?int $maintenant = null): string
{
    $maintenant ??= time();
    $e = fn (string $nom) => (string) ($serveur['HTTP_' . strtoupper(str_replace('-', '_', $nom))] ?? '');
    $cleId = $e('X-LGN-Cle');
    $date = $e('X-LGN-Date');
    $nonce = $e('X-LGN-Nonce');
    $signature = $e('X-LGN-Signature');
    if (!preg_match('/^[a-z0-9-]{3,40}$/', $cleId) || !isset($cles[$cleId]) || !preg_match('/^[0-9a-f]{64}$/', (string) $cles[$cleId])) throw new LgnHmacRefus('clé inconnue');
    if (!preg_match('/^\d{10}$/', $date) || abs($maintenant - (int) $date) > LGN_HMAC_FENETRE) throw new LgnHmacRefus('date hors fenêtre');
    if (!preg_match('/^[0-9a-f]{32}$/', $nonce)) throw new LgnHmacRefus('nonce invalide');
    if (!preg_match('/^[0-9a-f]{64}$/', $signature)) throw new LgnHmacRefus('signature absente');
    // Hôte réellement reçu (HTTP_HOST), comparé au domaine configuré ; X-Forwarded-Host ignoré
    $hote = lgnHmacHote((string) ($serveur['HTTP_HOST'] ?? ''));
    if ($hote === '' || !hash_equals(lgnHmacHote($hoteAttendu), $hote)) throw new LgnHmacRefus('hôte inattendu');
    $uri = (string) ($serveur['REQUEST_URI'] ?? '/');
    $chemin = (string) parse_url($uri, PHP_URL_PATH);
    $requete = (string) (parse_url($uri, PHP_URL_QUERY) ?? '');
    $attendue = hash_hmac('sha256', lgnHmacChaine((string) ($serveur['REQUEST_METHOD'] ?? ''), $hote, $chemin, $requete, $date, $nonce, $cleId, $corps), hex2bin($cles[$cleId]));
    if (!hash_equals($attendue, $signature)) throw new LgnHmacRefus('signature invalide');
    // Nonce enregistré seulement après une signature valide (un tiers ne peut pas « brûler » des nonces)
    if (!$nonceNeuf($cleId, $nonce, (int) $date)) throw new LgnHmacRefus('requête rejouée');
    return $cleId;
}

// Table des nonces (SQLite) : contrainte UNIQUE, insertion atomique, purge des anciens
function lgnHmacNonceSqlite(PDO $pdo): callable
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS lgn_nonces (cle_id TEXT NOT NULL, nonce TEXT NOT NULL, date INTEGER NOT NULL, UNIQUE (cle_id, nonce))');
    return function (string $cleId, string $nonce, int $date) use ($pdo): bool {
        // Purge relative à la date (déjà contrôlée dans la fenêtre) : un nonce plus ancien ne peut plus être accepté de toute façon
        $pdo->prepare('DELETE FROM lgn_nonces WHERE date < ?')->execute([$date - LGN_HMAC_NONCE_TTL - LGN_HMAC_FENETRE]);
        try { $pdo->prepare('INSERT INTO lgn_nonces (cle_id, nonce, date) VALUES (?, ?, ?)')->execute([$cleId, $nonce, $date]); return true; }
        catch (PDOException $e) { if (str_contains($e->getMessage(), 'UNIQUE')) return false; throw $e; }
    };
}
