<?php
// Kit LGN — envoi de sauvegardes vers un stockage objet S3 (Scaleway, Paris). Copié tel quel dans chaque site PHP.
// Source unique : back-office/kit/php/lgn-s3.php. Écriture seule : aucune lecture, suppression ni rétention depuis le serveur.
// Envoi simple sous 50 Mo, multipart au-delà (parts de 16 Mo), reprise des parts manquantes après interruption.
// La rétention (Object Lock) est posée par défaut sur le bucket par l'administration, jamais par le serveur.
declare(strict_types=1);

const LGN_S3_SEUIL_MULTIPART = 50 * 1024 * 1024;
const LGN_S3_TAILLE_PART = 16 * 1024 * 1024;

final class LgnS3Erreur extends RuntimeException {}

function lgnS3Encoder(string $s): string
{
    return str_replace('%7E', '~', rawurlencode($s));
}

// Signature AWS v4 (service s3). $entetes : [nom => valeur], host compris. Renvoie l'en-tête Authorization.
function lgnS3Autorisation(string $methode, string $chemin, array $requete, array $entetes, string $empreinteCorps, string $cle, string $secret, string $region, string $dateAmz): string
{
    $canonEntetes = [];
    foreach ($entetes as $k => $v) $canonEntetes[strtolower(trim((string) $k))] = preg_replace('/\s+/', ' ', trim((string) $v));
    ksort($canonEntetes, SORT_STRING);
    $signes = implode(';', array_keys($canonEntetes));
    $q = [];
    foreach ($requete as $k => $v) $q[lgnS3Encoder((string) $k)] = lgnS3Encoder((string) $v);
    ksort($q, SORT_STRING);
    $canonRequete = implode('&', array_map(fn ($k, $v) => "$k=$v", array_keys($q), $q));
    $canonChemin = implode('/', array_map('lgnS3Encoder', explode('/', $chemin)));
    $canonique = implode("\n", [strtoupper($methode), $canonChemin, $canonRequete,
        implode('', array_map(fn ($k, $v) => "$k:$v\n", array_keys($canonEntetes), $canonEntetes)), $signes, $empreinteCorps]);
    $jour = substr($dateAmz, 0, 8);
    $portee = "$jour/$region/s3/aws4_request";
    $aSigner = "AWS4-HMAC-SHA256\n$dateAmz\n$portee\n" . hash('sha256', $canonique);
    $k = hash_hmac('sha256', 'aws4_request', hash_hmac('sha256', 's3', hash_hmac('sha256', $region, hash_hmac('sha256', $jour, "AWS4$secret", true), true), true), true);
    return "AWS4-HMAC-SHA256 Credential=$cle/$portee, SignedHeaders=$signes, Signature=" . hash_hmac('sha256', $aSigner, $k);
}

// Requête signée. $cfg : endpoint (https://s3.fr-par.scw.cloud), region, bucket, cle, secret.
// $http(methode, url, entetes[], corps) → ['code', 'corps', 'erreur'] (remplaçable dans les tests)
function lgnS3Requete(array $cfg, string $methode, string $cleObjet, array $requete, string $corps, array $entetesEnPlus = [], ?callable $http = null, ?int $maintenant = null): array
{
    $u = parse_url((string) $cfg['endpoint']);
    if (($u['scheme'] ?? '') !== 'https' || empty($u['host'])) throw new LgnS3Erreur('Adresse du stockage : https:// obligatoire');
    $hote = $u['host'] . (isset($u['port']) ? ':' . $u['port'] : '');
    $chemin = '/' . $cfg['bucket'] . '/' . ltrim($cleObjet, '/');
    $dateAmz = gmdate('Ymd\THis\Z', $maintenant ?? time());
    $empreinte = hash('sha256', $corps);
    $entetes = ['host' => $hote, 'x-amz-content-sha256' => $empreinte, 'x-amz-date' => $dateAmz] + $entetesEnPlus;
    $entetes['authorization'] = lgnS3Autorisation($methode, $chemin, $requete, array_diff_key($entetes, ['authorization' => 1]), $empreinte, (string) $cfg['cle'], (string) $cfg['secret'], (string) $cfg['region'], $dateAmz);
    $q = [];
    foreach ($requete as $k => $v) $q[] = lgnS3Encoder((string) $k) . ($v === '' ? '' : '=' . lgnS3Encoder((string) $v));
    $url = "https://$hote" . implode('/', array_map('lgnS3Encoder', explode('/', $chemin))) . ($q ? '?' . implode('&', $q) : '');
    $lignes = [];
    foreach ($entetes as $k => $v) if ($k !== 'host') $lignes[] = "$k: $v";
    $http ??= 'lgnS3Http';
    return $http($methode, $url, $lignes, $corps);
}

function lgnS3Http(string $methode, string $url, array $entetes, string $corps): array
{
    $etag = '';
    $c = curl_init($url);
    curl_setopt_array($c, [CURLOPT_CUSTOMREQUEST => $methode, CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => [...$entetes, 'Expect:', 'Content-Type:'],
        CURLOPT_TIMEOUT => 300, CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_HEADERFUNCTION => function ($c, $l) use (&$etag) { if (stripos($l, 'etag:') === 0) $etag = trim(substr($l, 5)); return strlen($l); }]);
    if ($methode !== 'GET') curl_setopt($c, CURLOPT_POSTFIELDS, $corps);
    $r = curl_exec($c);
    $res = ['code' => (int) curl_getinfo($c, CURLINFO_RESPONSE_CODE), 'corps' => is_string($r) ? $r : '', 'erreur' => curl_error($c), 'etag' => $etag];
    curl_close($c);
    return $res;
}

function lgnS3Verifier(array $r, string $quoi): array
{
    if ($r['code'] < 200 || $r['code'] >= 300) {
        $code = preg_match('~<Code>([^<]+)</Code>~', $r['corps'], $m) ? $m[1] : ($r['erreur'] ?: 'sans détail');
        throw new LgnS3Erreur("$quoi refusé (HTTP {$r['code']}, $code)");
    }
    return $r;
}

// Envoie un fichier. Renvoie 'simple' ou 'multipart'. L'état d'un multipart est gardé dans <fichier>.envoi.json pour la reprise.
function lgnS3Envoyer(array $cfg, string $fichier, string $cleObjet, ?callable $http = null, int $seuil = LGN_S3_SEUIL_MULTIPART, int $taillePart = LGN_S3_TAILLE_PART): string
{
    $taille = filesize($fichier);
    if ($taille === false) throw new LgnS3Erreur('Fichier illisible');
    if ($taille < $seuil) {
        $corps = (string) file_get_contents($fichier);
        // Content-MD5 : exigé par S3 pour écrire dans un bucket à rétention (Object Lock)
        lgnS3Verifier(lgnS3Requete($cfg, 'PUT', $cleObjet, [], $corps, ['content-md5' => base64_encode(md5($corps, true))], $http), 'Envoi');
        return 'simple';
    }
    $etat = "$fichier.envoi.json";
    $e = is_file($etat) ? (json_decode((string) file_get_contents($etat), true) ?: []) : [];
    $faites = [];
    if (($e['cle'] ?? '') === $cleObjet && !empty($e['upload'])) {
        // Reprise : parts déjà reçues par le stockage (ListParts), le reste est renvoyé
        $r = lgnS3Requete($cfg, 'GET', $cleObjet, ['uploadId' => $e['upload']], '', [], $http);
        if ($r['code'] === 200) {
            preg_match_all('~<Part>.*?<PartNumber>(\d+)</PartNumber>.*?<ETag>([^<]+)</ETag>.*?</Part>~s', $r['corps'], $m, PREG_SET_ORDER);
            foreach ($m as [, $n, $etag]) $faites[(int) $n] = html_entity_decode($etag, ENT_QUOTES | ENT_XML1);
        } else $e = []; // envoi inconnu (abandonné ou expiré) : on recommence
    } else $e = [];
    if (!$e) {
        $r = lgnS3Verifier(lgnS3Requete($cfg, 'POST', $cleObjet, ['uploads' => ''], '', [], $http), 'Début de l\'envoi');
        if (!preg_match('~<UploadId>([^<]+)</UploadId>~', $r['corps'], $m)) throw new LgnS3Erreur('Identifiant d\'envoi absent');
        $e = ['cle' => $cleObjet, 'upload' => html_entity_decode($m[1], ENT_QUOTES | ENT_XML1)];
        file_put_contents($etat, json_encode($e), LOCK_EX);
    }
    $nbParts = (int) ceil($taille / $taillePart);
    $f = fopen($fichier, 'rb');
    try {
        for ($n = 1; $n <= $nbParts; $n++) {
            if (isset($faites[$n])) continue;
            fseek($f, ($n - 1) * $taillePart);
            $morceau = (string) fread($f, $taillePart);
            $r = lgnS3Verifier(lgnS3Requete($cfg, 'PUT', $cleObjet, ['partNumber' => (string) $n, 'uploadId' => $e['upload']], $morceau, ['content-md5' => base64_encode(md5($morceau, true))], $http), "Part $n");
            $faites[$n] = ($r['etag'] ?? '') ?: '"' . md5($morceau) . '"';
        }
    } finally { fclose($f); }
    ksort($faites);
    $xml = '<CompleteMultipartUpload>' . implode('', array_map(fn ($n, $t) => "<Part><PartNumber>$n</PartNumber><ETag>" . htmlspecialchars($t, ENT_XML1) . '</ETag></Part>', array_keys($faites), $faites)) . '</CompleteMultipartUpload>';
    $r = lgnS3Verifier(lgnS3Requete($cfg, 'POST', $cleObjet, ['uploadId' => $e['upload']], $xml, [], $http), 'Fin de l\'envoi');
    if (str_contains($r['corps'], '<Error>')) throw new LgnS3Erreur('Fin de l\'envoi refusée'); // S3 peut répondre 200 avec une erreur
    @unlink($etat);
    return 'multipart';
}
