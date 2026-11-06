<?php
// Connexion à l'espace de gestion : mot de passe + code à 6 chiffres (application d'authentification), limitation des essais.
declare(strict_types=1);

const SESSION_INACTIVITE = 1800; // 30 min

function demarrerSession(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $https = str_starts_with(config()['url'], 'https://');
    session_name($https ? '__Host-gestion' : 'gestion');
    session_save_path(dossierDonnees('sessions'));
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Strict']);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.gc_maxlifetime', (string) SESSION_INACTIVITE);
    session_start();
    if (isset($_SESSION['vu']) && time() - $_SESSION['vu'] > SESSION_INACTIVITE) { $_SESSION = []; session_regenerate_id(true); }
    // Gestion fermée depuis le back-office LGN (niveau 2 ou 3) : toute session ouverte avant est invalidée
    if (isset($_SESSION['ouverte_le']) && $_SESSION['ouverte_le'] < sessionsValidesDepuis()) { $_SESSION = []; session_regenerate_id(true); }
    $_SESSION['ouverte_le'] ??= time();
    $_SESSION['vu'] = time();
}

function utilisateurConnecte(): ?array
{
    if (empty($_SESSION['utilisateur']) || empty($_SESSION['code_ok'])) return null;
    $s = db()->prepare('SELECT * FROM utilisateurs WHERE id = ?');
    $s->execute([$_SESSION['utilisateur']]);
    return $s->fetch() ?: null;
}

function jetonCsrf(): string
{
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = jeton(24);
    return $_SESSION['csrf'];
}

function verifierCsrf(): void
{
    if (!hash_equals($_SESSION['csrf'] ?? '', (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Formulaire expiré. Revenez en arrière et rechargez la page.');
    }
}

// 5 échecs en 15 minutes par adresse IP ou par e-mail → blocage temporaire
function tropDEssais(string ...$cles): bool
{
    db()->prepare('DELETE FROM tentatives WHERE quand < ?')->execute([time() - 900]);
    foreach ($cles as $c) {
        $s = db()->prepare('SELECT COUNT(*) FROM tentatives WHERE cle = ?');
        $s->execute([$c]);
        if ((int) $s->fetchColumn() >= 5) return true;
    }
    return false;
}

function noterEchec(string ...$cles): void
{
    foreach ($cles as $c) db()->prepare('INSERT INTO tentatives (cle, quand) VALUES (?, ?)')->execute([$c, time()]);
}

function creerUtilisateur(string $email, string $motDePasse): int
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Adresse e-mail invalide.');
    verifierRobustesse($motDePasse);
    db()->prepare('INSERT INTO utilisateurs (email, mot_de_passe, cree_le) VALUES (?, ?, ?)')->execute([$email, password_hash($motDePasse, PASSWORD_DEFAULT), horodatage()]);
    return (int) db()->lastInsertId();
}

function verifierRobustesse(string $mdp): void
{
    if (mb_strlen($mdp) < 12) throw new InvalidArgumentException('Le mot de passe doit faire au moins 12 caractères.');
}

// --- Codes à usage unique (RFC 6238, 30 s, 6 chiffres) ---
function base32Encoder(string $octets): string
{
    $alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($octets) as $c) $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    $res = '';
    foreach (str_split($bits, 5) as $b) $res .= $alpha[bindec(str_pad($b, 5, '0'))];
    return $res;
}

function base32Decoder(string $s): string
{
    $alpha = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split(strtoupper(rtrim($s, '='))) as $c) {
        $p = strpos($alpha, $c);
        if ($p === false) continue;
        $bits .= str_pad(decbin($p), 5, '0', STR_PAD_LEFT);
    }
    $res = '';
    foreach (str_split($bits, 8) as $b) if (strlen($b) === 8) $res .= chr(bindec($b));
    return $res;
}

function codeTotp(string $secret, int $pas): string
{
    $h = hash_hmac('sha1', pack('J', $pas), base32Decoder($secret), true);
    $o = ord($h[19]) & 0xf;
    $n = ((ord($h[$o]) & 0x7f) << 24) | (ord($h[$o + 1]) << 16) | (ord($h[$o + 2]) << 8) | ord($h[$o + 3]);
    return str_pad((string) ($n % 1000000), 6, '0', STR_PAD_LEFT);
}

// Accepte le code courant et ses voisins (±30 s), refuse un code déjà utilisé. Renvoie le pas validé ou null.
function verifierTotp(string $secret, string $code, int $dernierPas): ?int
{
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== 6) return null;
    $pas = intdiv(maintenant()->getTimestamp(), 30);
    foreach ([0, -1, 1] as $d) {
        if ($pas + $d > $dernierPas && hash_equals(codeTotp($secret, $pas + $d), $code)) return $pas + $d;
    }
    return null;
}

function lienTotp(string $email, string $secret): string
{
    return 'otpauth://totp/' . rawurlencode('Sandrine Peyron:' . $email) . '?secret=' . $secret . '&issuer=' . rawurlencode('Sandrine Peyron');
}
