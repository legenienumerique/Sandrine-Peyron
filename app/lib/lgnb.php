<?php
// Kit LGN — archives de sauvegarde chiffrées et signées, format LGNB version 1 (libsodium).
// Copié tel quel dans chaque site. Source unique : back-office/kit/php/lgnb.php.
//
// .lgnb (entiers big-endian) :
//   0  4  « LGNB »            4  1  version 0x01
//   5  8  id clé de chiffrement (8 premiers octets BLAKE2b-256 de la clé publique)
//   13 8  id clé de signature  (même calcul sur la clé publique de signature)
//   21 2  L = longueur de la clé de fichier scellée (80)
//   23 L  clé de fichier scellée (crypto_box_seal)
//   23+L 24  en-tête crypto_secretstream_xchacha20poly1305
//   puis des blocs : longueur (4 octets) + bloc chiffré. L'en-tête complet est la donnée additionnelle du 1er bloc.
//   Contenu des blocs (1er octet = type) : M manifeste, F en-tête de fichier (JSON), D données (64 Kio max), Z fin (TAG_FINAL).
// .lgnb.sig : « LGNS », 0x01, id signature (8), id chiffrement (8), SHA-256 du .lgnb (32), SHA-256 du manifeste (32),
//   puis signature Ed25519 (64) de « LGNB-SIG-v1\n » + hex des 85 premiers octets.
declare(strict_types=1);

const LGNB_VERSION = 1;
const LGNB_BLOC = 65536;
const LGNB_DOSSIERS = ['base', 'medias', 'factures', 'config']; // config : copie de récupération de config.php (archive séparée)
const LGNB_LIMITES = ['fichier' => 2_000_000_000, 'total' => 20_000_000_000, 'nombre' => 200_000, 'manifeste' => 50_000_000];

final class LgnbErreur extends RuntimeException {}

function lgnbIdCle(string $clePublique): string
{
    return substr(sodium_crypto_generichash($clePublique, '', 32), 0, 8);
}

// Manifeste canonique : clés triées à tous les niveaux, UTF-8, sans espaces
function lgnbCanonique(array $m): string
{
    $trier = function (array $a) use (&$trier): array {
        if (!array_is_list($a)) ksort($a, SORT_STRING);
        foreach ($a as &$v) if (is_array($v)) $v = $trier($v);
        return $a;
    };
    return json_encode($trier($m), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

// Chemin relatif sûr : dossier autorisé, segments simples, ni « .. », ni chemin absolu, ni lettre de lecteur, ni NUL
function lgnbCheminSur(string $chemin): bool
{
    if (strlen($chemin) > 240 || str_contains($chemin, "\0") || str_contains($chemin, '\\')) return false;
    if (!preg_match('#^(' . implode('|', LGNB_DOSSIERS) . ')(/[A-Za-z0-9][A-Za-z0-9._-]{0,120})+$#', $chemin)) return false;
    foreach (explode('/', $chemin) as $seg) if ($seg === '..' || $seg === '.') return false;
    return true;
}

// $fichiers : [chemin relatif => chemin absolu]. Écrit $sortie et $sortie.sig. Renvoie les empreintes.
function lgnbCreer(string $sortie, array $fichiers, array $infos, string $clePubChiffrement, string $cleSecSignature): array
{
    ksort($fichiers, SORT_STRING);
    $liste = [];
    foreach ($fichiers as $rel => $abs) {
        if (!lgnbCheminSur($rel)) throw new LgnbErreur("chemin refusé : $rel");
        if (is_link($abs) || !is_file($abs)) throw new LgnbErreur("fichier absent ou lien : $rel");
        $liste[] = ['chemin' => $rel, 'taille' => filesize($abs), 'sha256' => hash_file('sha256', $abs)];
    }
    $manifeste = lgnbCanonique(['format' => LGNB_VERSION, 'infos' => $infos, 'fichiers' => $liste]);
    $clePubSignature = sodium_crypto_sign_publickey_from_secretkey($cleSecSignature);
    $cleFichier = sodium_crypto_secretstream_xchacha20poly1305_keygen();
    $scellee = sodium_crypto_box_seal($cleFichier, $clePubChiffrement);
    [$etat, $enteteFlux] = sodium_crypto_secretstream_xchacha20poly1305_init_push($cleFichier);
    $entete = 'LGNB' . chr(LGNB_VERSION) . lgnbIdCle($clePubChiffrement) . lgnbIdCle($clePubSignature) . pack('n', strlen($scellee)) . $scellee . $enteteFlux;
    sodium_memzero($cleFichier);

    $tmp = $sortie . '.partiel';
    $f = fopen($tmp, 'xb');
    if (!$f) throw new LgnbErreur('écriture impossible');
    $ecrire = function (string $type, string $donnees, string $ad = '', int $tag = SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE) use (&$etat, $f) {
        $c = sodium_crypto_secretstream_xchacha20poly1305_push($etat, $type . $donnees, $ad, $tag);
        fwrite($f, pack('N', strlen($c)) . $c);
    };
    fwrite($f, $entete);
    $ecrire('M', $manifeste, $entete);
    foreach ($liste as $l) {
        $ecrire('F', json_encode(['chemin' => $l['chemin'], 'taille' => $l['taille']], JSON_UNESCAPED_SLASHES));
        $src = fopen($fichiers[$l['chemin']], 'rb');
        while (!feof($src)) { $morceau = fread($src, LGNB_BLOC); if ($morceau !== '' && $morceau !== false) $ecrire('D', $morceau); }
        fclose($src);
    }
    $ecrire('Z', '', '', SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL);
    fflush($f);
    fclose($f);
    rename($tmp, $sortie);

    $sha = hash_file('sha256', $sortie, true);
    $tete = 'LGNS' . chr(LGNB_VERSION) . lgnbIdCle($clePubSignature) . lgnbIdCle($clePubChiffrement) . $sha . hash('sha256', $manifeste, true);
    $sig = sodium_crypto_sign_detached("LGNB-SIG-v1\n" . bin2hex($tete), $cleSecSignature);
    file_put_contents($sortie . '.sig', $tete . $sig, LOCK_EX);
    return ['sha256' => bin2hex($sha), 'manifeste_sha256' => hash('sha256', $manifeste), 'fichiers' => count($liste)];
}

// Étapes 1 à 3, avant tout déchiffrement. $clesSignature : [id binaire 8 octets => clé publique].
function lgnbVerifierSignature(string $archive, array $clesSignature): array
{
    $sig = @file_get_contents($archive . '.sig');
    if ($sig === false || strlen($sig) !== 149 || substr($sig, 0, 4) !== 'LGNS' || ord($sig[4]) !== LGNB_VERSION) throw new LgnbErreur('signature absente ou mal formée');
    $tete = substr($sig, 0, 85);
    $idSign = substr($tete, 5, 8);
    $idChiffr = substr($tete, 13, 8);
    if (!isset($clesSignature[$idSign])) throw new LgnbErreur('clé de signature inconnue');
    if (!sodium_crypto_sign_verify_detached(substr($sig, 85), "LGNB-SIG-v1\n" . bin2hex($tete), $clesSignature[$idSign])) throw new LgnbErreur('signature invalide');
    $f = fopen($archive, 'rb');
    $debut = $f ? fread($f, 21) : '';
    if ($f) fclose($f);
    if (strlen($debut) !== 21 || substr($debut, 0, 4) !== 'LGNB' || ord($debut[4]) !== LGNB_VERSION) throw new LgnbErreur('archive mal formée');
    if (!hash_equals(substr($debut, 5, 8), $idChiffr) || !hash_equals(substr($debut, 13, 8), $idSign)) throw new LgnbErreur('identifiants de clés différents entre archive et signature');
    if (!hash_equals(substr($tete, 21, 32), hash_file('sha256', $archive, true))) throw new LgnbErreur('empreinte de l\'archive différente');
    return ['manifeste_sha256' => substr($tete, 53, 32), 'id_chiffrement' => $idChiffr];
}

// Vérification complète puis extraction dans $destination (dossier vide, isolé). Renvoie le manifeste.
// $paireChiffrement : paire de clés crypto_box (clé privée gardée hors serveur).
function lgnbExtraire(string $archive, array $clesSignature, string $paireChiffrement, string $destination, array $limites = LGNB_LIMITES): array
{
    $signe = lgnbVerifierSignature($archive, $clesSignature);
    if (!is_dir($destination) || is_link($destination) || count(scandir($destination)) > 2) throw new LgnbErreur('destination non vide ou invalide');
    if (!hash_equals($signe['id_chiffrement'], lgnbIdCle(sodium_crypto_box_publickey($paireChiffrement)))) throw new LgnbErreur('mauvaise clé de déchiffrement');
    $f = fopen($archive, 'rb');
    try {
        $debut = fread($f, 23);
        $L = unpack('n', substr($debut, 21, 2))[1];
        if ($L !== SODIUM_CRYPTO_BOX_SEALBYTES + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) throw new LgnbErreur('en-tête mal formé');
        $scellee = fread($f, $L);
        $enteteFlux = fread($f, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
        $entete = $debut . $scellee . $enteteFlux;
        $cleFichier = sodium_crypto_box_seal_open($scellee, $paireChiffrement);
        if ($cleFichier === false) throw new LgnbErreur('clé de fichier illisible');
        $etat = sodium_crypto_secretstream_xchacha20poly1305_init_pull($enteteFlux, $cleFichier);
        sodium_memzero($cleFichier);
        $lire = function (string $ad = '') use ($f, &$etat, $limites): array {
            $t = fread($f, 4);
            if (strlen($t) !== 4) throw new LgnbErreur('archive tronquée');
            $n = unpack('N', $t)[1];
            if ($n < 1 + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES || $n > max(LGNB_BLOC, $limites['manifeste']) + 1 + SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_ABYTES) throw new LgnbErreur('bloc de taille invalide');
            $c = fread($f, $n);
            if (strlen($c) !== $n) throw new LgnbErreur('archive tronquée');
            $r = sodium_crypto_secretstream_xchacha20poly1305_pull($etat, $c, $ad);
            if ($r === false) throw new LgnbErreur('bloc altéré');
            return [$r[0][0] ?? '', substr($r[0], 1), $r[1]];
        };
        [$type, $manifesteBrut] = $lire($entete);
        if ($type !== 'M') throw new LgnbErreur('manifeste absent');
        if (!hash_equals($signe['manifeste_sha256'], hash('sha256', $manifesteBrut, true))) throw new LgnbErreur('manifeste différent de celui signé');
        $m = json_decode($manifesteBrut, true);
        if (!is_array($m) || ($m['format'] ?? 0) !== LGNB_VERSION || !is_array($m['fichiers'] ?? null)) throw new LgnbErreur('manifeste invalide');
        if (count($m['fichiers']) > $limites['nombre']) throw new LgnbErreur('trop de fichiers');
        $total = 0;
        foreach ($m['fichiers'] as $x) {
            if (!lgnbCheminSur((string) ($x['chemin'] ?? '')) || !is_int($x['taille'] ?? null) || $x['taille'] < 0 || $x['taille'] > $limites['fichier']) throw new LgnbErreur('entrée de manifeste refusée');
            $total += $x['taille'];
        }
        if ($total > $limites['total']) throw new LgnbErreur('archive trop volumineuse');
        foreach ($m['fichiers'] as $x) {
            [$type, $json] = $lire();
            $e = json_decode($json, true);
            if ($type !== 'F' || ($e['chemin'] ?? null) !== $x['chemin'] || ($e['taille'] ?? null) !== $x['taille']) throw new LgnbErreur('ordre des fichiers différent du manifeste');
            $cible = lgnbPreparerCible($destination, $x['chemin']);
            $out = fopen($cible, 'xb'); // création exclusive : jamais d'écrasement ni de suivi de lien
            if (!$out) throw new LgnbErreur('création impossible : ' . $x['chemin']);
            $ctx = hash_init('sha256');
            $ecrit = 0;
            while ($ecrit < $x['taille']) {
                [$type, $donnees] = $lire();
                if ($type !== 'D' || $donnees === '') throw new LgnbErreur('données manquantes');
                $ecrit += strlen($donnees);
                if ($ecrit > $x['taille']) throw new LgnbErreur('fichier plus long que prévu');
                hash_update($ctx, $donnees);
                fwrite($out, $donnees);
            }
            fclose($out);
            if (!hash_equals($x['sha256'], hash_final($ctx))) throw new LgnbErreur('empreinte différente : ' . $x['chemin']);
        }
        [$type, , $tag] = $lire();
        if ($type !== 'Z' || $tag !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) throw new LgnbErreur('fin d\'archive absente (archive tronquée)');
        if (fread($f, 1) !== '') throw new LgnbErreur('données après la fin');
        return $m;
    } catch (Throwable $e) {
        fclose($f);
        lgnbVider($destination);
        throw $e instanceof LgnbErreur ? $e : new LgnbErreur($e->getMessage());
    } finally {
        if (is_resource($f)) fclose($f);
    }
}

// Crée les dossiers intermédiaires en refusant tout lien symbolique sur le chemin
function lgnbPreparerCible(string $destination, string $chemin): string
{
    $courant = rtrim($destination, '/\\');
    $segments = explode('/', $chemin);
    $nom = array_pop($segments);
    foreach ($segments as $seg) {
        $courant .= '/' . $seg;
        if (is_link($courant)) throw new LgnbErreur('lien symbolique refusé');
        if (!is_dir($courant) && !mkdir($courant, 0700)) throw new LgnbErreur('dossier impossible');
    }
    $cible = "$courant/$nom";
    if (file_exists($cible) || is_link($cible)) throw new LgnbErreur('fichier déjà présent : ' . $chemin);
    return $cible;
}

// Efface le contenu d'un dossier temporaire d'extraction (jamais en suivant un lien)
function lgnbVider(string $dossier): void
{
    if (!is_dir($dossier) || is_link($dossier)) return;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dossier, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($it as $x) { if ($x->isLink() || $x->isFile()) unlink($x->getPathname()); elseif ($x->isDir()) rmdir($x->getPathname()); }
}
