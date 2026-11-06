<?php
// Demandes à LGN depuis la gestion (kit LGN) : envoi signé HMAC vers le back-office (clé « demandes » du site).
// config « lgn_demandes » : ['url' => 'https://admin.legenienumerique.fr/api/site.php', 'cle' => identifiant, 'secret' => 64 hex]
// Rien n'est gardé ici : la demande, son statut et les échanges vivent dans le back-office et sont relus à chaque affichage.
declare(strict_types=1);

const DEMANDE_CAPTURE_MAX = 5 * 1024 * 1024;

function demandesConfigurees(): bool
{
    $c = config()['lgn_demandes'] ?? [];
    // https obligatoire ; http seulement vers l'ordinateur lui-même (essais locaux), comme le kit HMAC
    return is_array($c) && preg_match('~^(https://|http://(localhost|127\.0\.0\.1)(:\d+)?/)~', (string) ($c['url'] ?? '')) && preg_match('/^[a-z0-9-]{3,40}$/', (string) ($c['cle'] ?? '')) && preg_match('/^[0-9a-f]{64}$/', (string) ($c['secret'] ?? ''));
}

// $http(methode, url, entetes, corps) → ['code', 'corps'] (remplaçable dans les tests)
function appelLgn(string $methode, string $action, ?array $donnees = null, ?callable $http = null): array
{
    if (!demandesConfigurees()) throw new RuntimeException('Demandes à LGN non configurées sur ce site.');
    $c = config()['lgn_demandes'];
    $url = rtrim((string) $c['url'], '?') . '?action=' . rawurlencode($action);
    $corps = $donnees === null ? '' : (string) json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $entetes = [...lgnHmacSigner((string) $c['cle'], (string) $c['secret'], $methode, $url, $corps), 'Accept: application/json'];
    if ($corps !== '') $entetes[] = 'Content-Type: application/json';
    $http ??= $GLOBALS['LGN_HTTP_TEST'] ?? function (string $m, string $u, array $e, string $b): array {
        $ch = curl_init($u);
        curl_setopt_array($ch, [CURLOPT_CUSTOMREQUEST => $m, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $e, CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS]);
        if ($b !== '') curl_setopt($ch, CURLOPT_POSTFIELDS, $b);
        $r = curl_exec($ch);
        $res = ['code' => (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE), 'corps' => is_string($r) ? $r : ''];
        curl_close($ch);
        return $res;
    };
    $r = $http($methode, $url, $entetes, $corps);
    $json = json_decode($r['corps'], true);
    if ($r['code'] < 200 || $r['code'] >= 300) throw new RuntimeException(is_array($json) && isset($json['erreur']) ? (string) $json['erreur'] : 'Le Génie Numérique ne répond pas. Réessayez plus tard.');
    return is_array($json) ? $json : [];
}

// Capture facultative : vérifiée ici aussi (type réel, taille), ré-encodée par le back-office
function lireCaptureEnvoyee(?array $fichier): string
{
    if (!$fichier || ($fichier['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return '';
    if ($fichier['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($fichier['tmp_name'])) throw new InvalidArgumentException('Capture non reçue.');
    if ($fichier['size'] > DEMANDE_CAPTURE_MAX) throw new InvalidArgumentException('Capture trop lourde (5 Mo au plus).');
    $type = (new finfo(FILEINFO_MIME_TYPE))->file($fichier['tmp_name']);
    if (!in_array($type, ['image/png', 'image/jpeg', 'image/webp'], true)) throw new InvalidArgumentException('Capture : image PNG, JPEG ou WebP seulement.');
    return base64_encode((string) file_get_contents($fichier['tmp_name']));
}

function envoyerDemande(string $texte, string $urgence, string $capture = '', ?callable $http = null): array
{
    $texte = trim(mb_substr($texte, 0, 5000));
    if (mb_strlen($texte) < 3) throw new InvalidArgumentException('Décrivez votre demande.');
    return appelLgn('POST', 'demande', ['texte' => $texte, 'urgence' => $urgence === 'urgente' ? 'urgente' : 'normale'] + ($capture !== '' ? ['capture' => $capture] : []), $http);
}

function listerDemandes(?callable $http = null): array
{
    return (array) (appelLgn('GET', 'demandes', null, $http)['demandes'] ?? []);
}

function repondreDemande(int $demande, string $texte, ?callable $http = null): void
{
    $texte = trim(mb_substr($texte, 0, 5000));
    if ($texte === '') throw new InvalidArgumentException('Message vide.');
    appelLgn('POST', 'message', ['demande' => $demande, 'texte' => $texte], $http);
}
