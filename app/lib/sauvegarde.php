<?php
// Sauvegarde et restauration (kit PHP LGN). Chargé seulement par les outils en ligne de commande.
// Archive LGNB chiffrée pour la clé publique LGN (le serveur ne peut pas la déchiffrer) et signée par la clé du site.
declare(strict_types=1);

require_once __DIR__ . '/lgnb.php';

function dossierMedias(): string { return (string) (config()['medias'] ?? RACINE . '/public/medias'); }
function fichierBase(): string { return dossierDonnees() . '/site.sqlite'; }
function cleSignatureSite(): string
{
    $f = (string) (config()['sauvegarde']['cle_signature'] ?? dossierDonnees('cles') . '/signature.key');
    $k = is_file($f) ? base64_decode(trim((string) file_get_contents($f)), true) : false;
    if (!$k || strlen($k) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) throw new RuntimeException('Clé de signature du site absente (outils/cles.php signature).');
    return $k;
}
function clePubliqueChiffrement(): string
{
    $k = base64_decode((string) (config()['sauvegarde']['cle_publique'] ?? ''), true);
    if (!$k || strlen($k) !== SODIUM_CRYPTO_BOX_PUBLICKEYBYTES) throw new RuntimeException('Clé publique de chiffrement absente (config « sauvegarde.cle_publique »).');
    return $k;
}
function clesSignaturePubliques(): array
{
    $cles = [];
    foreach ((array) (config()['sauvegarde']['cles_signature_publiques'] ?? []) as $b64) {
        $k = base64_decode((string) $b64, true);
        if ($k && strlen($k) === SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) $cles[lgnbIdCle($k)] = $k;
    }
    return $cles;
}

function copierArbre(string $source, string $cible, array &$liste, string $prefixe): void
{
    if (!is_dir($source)) return;
    if (!is_dir($cible)) mkdir($cible, 0700, true);
    foreach (scandir($source) as $n) {
        if ($n === '.' || $n === '..' || $n[0] === '.') continue;
        $s = "$source/$n";
        if (is_link($s)) continue; // jamais de lien symbolique dans une sauvegarde
        if (is_dir($s)) copierArbre($s, "$cible/$n", $liste, "$prefixe/$n");
        elseif (is_file($s)) { copy($s, "$cible/$n"); $liste["$prefixe/$n"] = "$cible/$n"; }
    }
}

// Sauvegarde cohérente : base, médias (et factures si stockées en fichiers) copiés ensemble sous verrou exclusif
function sauvegarder(?string $horodatage = null): array
{
    $ts = $horodatage ?? date('Ymd-His');
    $prep = dossierDonnees() . "/preparation-$ts";
    if (is_dir($prep)) throw new RuntimeException('Préparation déjà présente.');
    mkdir($prep, 0700, true);
    $fichiers = [];
    $v = prendreVerrou(true, 120.0);
    if ($v === false) throw new RuntimeException('Verrou exclusif indisponible (écritures en cours depuis plus de 2 minutes).');
    try {
        mkdir("$prep/base", 0700);
        $copieBase = "$prep/base/site.sqlite";
        db()->exec('VACUUM INTO ' . db()->quote($copieBase)); // instantané cohérent de la base (WAL compris)
        $fichiers['base/site.sqlite'] = $copieBase;
        copierArbre(dossierMedias(), "$prep/medias", $fichiers, 'medias');
    } finally {
        flock($v, LOCK_UN);
        fclose($v);
    }
    $controle = (new PDO('sqlite:' . $copieBase))->query('PRAGMA integrity_check')->fetchColumn();
    if ($controle !== 'ok') { lgnbVider($prep); rmdir($prep); throw new RuntimeException('Copie de la base corrompue : sauvegarde refusée.'); }
    $dossier = dossierDonnees('sauvegardes');
    $archive = "$dossier/sauvegarde-$ts.lgnb";
    $r = lgnbCreer($archive, $fichiers, ['site' => (string) parse_url(config()['url'], PHP_URL_HOST), 'date' => date('c'), 'version' => (string) (config()['version'] ?? ''), 'integrity_check' => $controle], clePubliqueChiffrement(), cleSignatureSite());
    lgnbVider($prep);
    rmdir($prep);
    lgnbVerifierSignature($archive, clesSignaturePubliques() ?: [lgnbIdCle(sodium_crypto_sign_publickey_from_secretkey(cleSignatureSite())) => sodium_crypto_sign_publickey_from_secretkey(cleSignatureSite())]);
    $etat = ['date' => date('c'), 'fichier' => basename($archive), 'sha256' => $r['sha256'], 'fichiers' => $r['fichiers'], 'verifiee' => true, 'externe' => envoyerCopieExterne($archive)];
    file_put_contents(dossierDonnees() . '/derniere-sauvegarde.json', json_encode($etat), LOCK_EX);
    $etat['purgees'] = purgerSauvegardes((int) (config()['sauvegarde']['retention_jours'] ?? 30));
    return $etat;
}

// Copie externe (stockage objet distinct, verrouillé) : prérequis non encore choisi. Échec = copie locale gardée.
function envoyerCopieExterne(string $archive): string
{
    if (empty(config()['sauvegarde']['externe'])) return 'non configurée';
    $f = config()['sauvegarde']['externe'];
    if (!is_callable($f)) return 'non configurée';
    try { $f($archive); $f($archive . '.sig'); return 'envoyée'; }
    catch (Throwable $e) { journaliserErreur('Copie externe en échec : ' . $e->getMessage()); return 'échec'; }
}

// Conservation : archives de plus de N jours retirées du stockage local (la copie externe suit sa propre règle)
function purgerSauvegardes(int $jours): int
{
    $n = 0;
    $limite = time() - max(1, $jours) * 86400;
    foreach (glob(dossierDonnees('sauvegardes') . '/sauvegarde-*.lgnb') ?: [] as $a) {
        if (filemtime($a) < $limite) { unlink($a); @unlink("$a.sig"); $n++; }
    }
    return $n;
}

// ---------- Restauration ----------
// Bascule contrôlée, non atomique dans son ensemble : chaque élément est déplacé par rename (atomique par élément),
// chaque étape est écrite au journal avant et après, et peut être annulée dans l'ordre inverse.
final class Restauration
{
    public string $journal;
    public string $ancien;
    public function __construct(public string $preparation, string $horodatage)
    {
        $this->journal = dossierDonnees() . '/restauration.journal';
        $this->ancien = dossierDonnees() . "/ancien-$horodatage";
    }

    public function noter(array $e): void
    {
        $f = fopen($this->journal, 'a');
        fwrite($f, json_encode($e + ['t' => time()]) . "\n");
        fflush($f);
        if (function_exists('fsync')) fsync($f);
        fclose($f);
    }

    public function lire(): array
    {
        return is_file($this->journal) ? array_map(fn ($l) => json_decode($l, true), file($this->journal, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)) : [];
    }

    // Déplacements prévus : ancien en lieu sûr, puis nouveau à sa place (base, -wal, -shm, médias)
    public function deplacements(): array
    {
        $base = fichierBase();
        $d = [];
        foreach (['', '-wal', '-shm'] as $suffixe) if (file_exists("$base$suffixe")) $d[] = ["$base$suffixe", "{$this->ancien}/base/site.sqlite$suffixe"];
        $d[] = ["{$this->preparation}/base/site.sqlite", $base];
        if (file_exists(dossierMedias())) $d[] = [dossierMedias(), "{$this->ancien}/medias"];
        if (is_dir("{$this->preparation}/medias")) $d[] = ["{$this->preparation}/medias", dossierMedias()];
        return $d;
    }

    public function basculer(?callable $interruption = null): void
    {
        // Seul le dossier de la base est créé d'avance : « medias » y est déplacé en entier (la cible ne doit pas exister)
        if (!is_dir("{$this->ancien}/base")) mkdir("{$this->ancien}/base", 0700, true);
        foreach ($this->deplacements() as $i => [$de, $vers]) {
            if (is_link($de) || is_link($vers)) throw new RuntimeException('Lien symbolique refusé pendant la bascule.');
            if (file_exists($vers)) throw new RuntimeException("Cible déjà présente : $vers");
            $this->noter(['etape' => $i, 'de' => $de, 'vers' => $vers, 'etat' => 'prevu']);
            if ($interruption) $interruption($i);
            if (!rename($de, $vers)) throw new RuntimeException("Déplacement impossible : $de");
            $this->noter(['etape' => $i, 'de' => $de, 'vers' => $vers, 'etat' => 'fait']);
        }
        $this->noter(['etape' => 'fin', 'etat' => 'fait']);
    }

    // Retour arrière : déplacements faits, annulés dans l'ordre inverse (d'après le journal)
    public function annuler(): int
    {
        $faits = array_values(array_filter($this->lire(), fn ($e) => ($e['etat'] ?? '') === 'fait' && is_int($e['etape'] ?? null)));
        $n = 0;
        foreach (array_reverse($faits) as $e) {
            if (file_exists($e['de'])) throw new RuntimeException("Retour impossible, place occupée : {$e['de']}");
            if (!rename($e['vers'], $e['de'])) throw new RuntimeException("Retour impossible : {$e['vers']}");
            $this->noter(['etape' => $e['etape'], 'etat' => 'annule']);
            $n++;
        }
        return $n;
    }
}

// SQLite : plus aucun écrivain (verrou exclusif tenu), connexions fermées, journal WAL vidé et vérifié avant tout renommage
function preparerBasePourBascule(): void
{
    $GLOBALS['DB_FERMEE'] = true;
    $base = fichierBase();
    if (!is_file($base)) return;
    $pdo = new PDO('sqlite:' . $base, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $r = $pdo->query('PRAGMA wal_checkpoint(TRUNCATE)')->fetch(PDO::FETCH_NUM);
    $pdo->exec('PRAGMA journal_mode = DELETE'); // plus de -wal ni de -shm tant que la bascule n'est pas faite
    $pdo = null;
    if ((int) $r[0] !== 0) throw new RuntimeException('Base encore utilisée (point de contrôle WAL refusé) : restauration arrêtée.');
    if (is_file("$base-wal") && filesize("$base-wal") > 0) throw new RuntimeException('Journal WAL non vide : restauration arrêtée.');
}
