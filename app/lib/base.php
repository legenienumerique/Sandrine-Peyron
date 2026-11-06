<?php
// Socle : configuration, base SQLite, paramètres, journal, petits outils.
declare(strict_types=1);

date_default_timezone_set('Europe/Paris');
mb_internal_encoding('UTF-8');

const RACINE = __DIR__ . '/../..';

function config(): array
{
    static $c = null;
    if ($c === null) {
        $fichier = RACINE . '/app/config.php';
        $c = array_merge([
            'dev' => false,
            'url' => 'http://localhost:4180',
            'secret' => '',
            'donnees' => RACINE . '/donnees',
            'mollie_cle' => '',
            'smtp_hote' => '', 'smtp_port' => 587, 'smtp_utilisateur' => '', 'smtp_mot_de_passe' => '',
            'expediteur' => '', 'expediteur_nom' => 'Sandrine Peyron',
            'dompdf' => '',
            'essai' => false, // site d'essai : e-mails en journal, non indexé
            'version' => '',
        ], is_file($fichier) ? require $fichier : []);
        if (isset($GLOBALS['CONFIG_TEST'])) $c = array_merge($c, $GLOBALS['CONFIG_TEST']);
        // Version écrite par la publication (commit), si config.php n'en donne pas
        if ($c['version'] === '' && is_file(RACINE . '/app/version.txt')) $c['version'] = trim((string) file_get_contents(RACINE . '/app/version.txt'));
        if (strlen($c['secret']) < 32) throw new RuntimeException('Configuration : « secret » manquant (32 caractères minimum).');
    }
    return $c;
}

function dossierDonnees(string $sous = ''): string
{
    $d = rtrim(config()['donnees'], '/\\') . ($sous !== '' ? "/$sous" : '');
    if (!is_dir($d)) mkdir($d, 0700, true);
    return $d;
}

function db(): PDO
{
    static $pdo = null;
    if (isset($GLOBALS['DB_TEST'])) return $GLOBALS['DB_TEST'];
    if ($pdo === null) {
        $pdo = ouvrirBase(dossierDonnees() . '/site.sqlite');
    }
    return $pdo;
}

function ouvrirBase(string $chemin): PDO
{
    $pdo = new PDO('sqlite:' . $chemin, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON; PRAGMA busy_timeout = 5000;');
    if ($chemin !== ':memory:') $pdo->exec('PRAGMA journal_mode = WAL;');
    $pdo->exec(file_get_contents(RACINE . '/app/schema.sql'));
    initialiser($pdo);
    return $pdo;
}

// Activités et réglages de départ (une seule fois). Les tarifs restent vides : Sandrine les fixe dans Paramètres.
function initialiser(PDO $pdo): void
{
    if ((int) $pdo->query('SELECT COUNT(*) FROM activites')->fetchColumn() === 0) {
        $ins = $pdo->prepare('INSERT INTO activites (code, type, nom, resume, duree_minutes, ordre) VALUES (?, ?, ?, ?, ?, ?)');
        foreach ([
            ['parentalite', 'parole', 'Soutien à la parentalité', 'Les questions et les fatigues de parent, partagées en petit groupe.', 90, 1],
            ['aidants', 'parole', 'Soutien aux aidants familiaux', 'Pour celles et ceux qui accompagnent un proche malade, âgé ou en situation de handicap.', 90, 2],
            ['deuil', 'parole', 'Mieux traverser son deuil', 'Un espace pour la perte et ce qui suit, à son rythme.', 90, 3],
            ['sophro-ados', 'sophro_groupe', 'Santé mentale, adolescents', 'Respirer, se poser, retrouver un appui quand l’anxiété prend de la place.', 60, 4],
            ['sophro-adultes', 'sophro_groupe', 'Santé mentale, adultes', 'Des outils simples pour traverser stress, anxiété ou passage difficile.', 60, 5],
            ['seance', 'individuel', 'Séance individuelle', 'Un accompagnement personnalisé, au cabinet ou en visio.', 60, 6],
        ] as $a) $ins->execute($a);
    }
    // Demande de Sandrine (04/11/2026) : pas de catégories par méthode, une seule « séance individuelle »
    if (!$pdo->query("SELECT COUNT(*) FROM activites WHERE code = 'seance'")->fetchColumn()) {
        $pdo->exec("UPDATE activites SET code = 'seance', nom = 'Séance individuelle', resume = 'Un accompagnement personnalisé, au cabinet ou en visio.' WHERE code = 'sophrologie'");
        $pdo->exec("UPDATE activites SET actif = 0 WHERE code IN ('emdr', 'psychanalyse')");
        $pdo->exec("UPDATE activites SET nom = 'Santé mentale, adolescents' WHERE code = 'sophro-ados'; UPDATE activites SET nom = 'Santé mentale, adultes' WHERE code = 'sophro-adultes'");
    }
    $defauts = [
        'blocage_minutes' => '15',
        'annulation_heures' => '48',
        'delai_min_heures' => '24',
        'horizon_jours' => '60',
        'battement_minutes' => '15',
        'email_sandrine' => 'sophro.zen.relax@gmail.com',
        'telephone' => '06 99 23 52 17',
        'facture_nom' => 'Sandrine Peyron',
        'facture_adresse' => "Cabinet du Petit Prince\n50 rue Victor Hugo, allée du Petit Prince\n69220 Belleville-en-Beaujolais",
        'facture_siret' => '',
        'facture_mention_tva' => 'TVA non applicable, article 293 B du Code général des impôts',
    ];
    $ins = $pdo->prepare('INSERT OR IGNORE INTO parametres (cle, valeur) VALUES (?, ?)');
    foreach ($defauts as $k => $v) $ins->execute([$k, $v]);
}

function param(string $cle): string
{
    $s = db()->prepare('SELECT valeur FROM parametres WHERE cle = ?');
    $s->execute([$cle]);
    return (string) ($s->fetchColumn() ?: '');
}

function paramInt(string $cle): int { return (int) param($cle); }

function definirParam(string $cle, string $valeur): void
{
    db()->prepare('INSERT INTO parametres (cle, valeur) VALUES (?, ?) ON CONFLICT(cle) DO UPDATE SET valeur = excluded.valeur')->execute([$cle, $valeur]);
}

// Heure courante, remplaçable dans les tests
function maintenant(): DateTimeImmutable
{
    return isset($GLOBALS['MAINTENANT_TEST']) ? new DateTimeImmutable($GLOBALS['MAINTENANT_TEST']) : new DateTimeImmutable('now');
}

function horodatage(?DateTimeInterface $d = null): string { return ($d ?? maintenant())->format('Y-m-d H:i:s'); }

function journal(string $qui, string $action, string $detail = ''): void
{
    db()->prepare('INSERT INTO journal (quand, qui, action, detail) VALUES (?, ?, ?, ?)')->execute([horodatage(), $qui, $action, mb_substr($detail, 0, 500)]);
}

function journaliserErreur(Throwable|string $e): void
{
    $msg = $e instanceof Throwable ? $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine() : $e;
    $msg = filtrerDonneesPerso(preg_replace('/\s+/', ' ', $msg)); // une ligne, jamais d'e-mail, de téléphone ni de clé
    @file_put_contents(dossierDonnees('journaux') . '/erreurs.log', date('c') . " $msg\n", FILE_APPEND);
}

function h(?string $s): string { return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

function jeton(int $octets = 16): string { return rtrim(strtr(base64_encode(random_bytes($octets)), '+/', '-_'), '='); }

function euros(?int $centimes): string
{
    if ($centimes === null) return 'tarif à venir';
    return number_format($centimes / 100, $centimes % 100 ? 2 : 0, ',', ' ') . ' €';
}

// « 12,50 » ou « 12 » → 1250 ; vide → null
function versCentimes(string $saisie): ?int
{
    $s = str_replace([' ', ' ', '€'], '', trim($saisie));
    if ($s === '') return null;
    if (!preg_match('/^\d{1,6}([.,]\d{1,2})?$/', $s)) throw new InvalidArgumentException('Montant invalide : « ' . $saisie . ' ».');
    return (int) round((float) str_replace(',', '.', $s) * 100);
}

function dateLongue(string $date, bool $heure = true): string
{
    $d = new DateTimeImmutable($date);
    $jours = ['', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];
    $mois = ['', 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $t = $jours[(int) $d->format('N')] . ' ' . (int) $d->format('j') . ' ' . $mois[(int) $d->format('n')];
    if ($d->format('Y') !== maintenant()->format('Y')) $t .= ' ' . $d->format('Y');
    return $heure ? $t . ' à ' . heure($date) : $t;
}

function heure(string $date): string
{
    $d = new DateTimeImmutable($date);
    return $d->format('G') . ' h' . ($d->format('i') !== '00' ? ' ' . $d->format('i') : '');
}

function activite(string|int $codeOuId): ?array
{
    $s = db()->prepare(is_int($codeOuId) ? 'SELECT * FROM activites WHERE id = ?' : 'SELECT * FROM activites WHERE code = ?');
    $s->execute([$codeOuId]);
    return $s->fetch() ?: null;
}

// Tous les groupes, sans distinction de thème (parole, santé mentale)
function activitesGroupes(): array
{
    return db()->query("SELECT * FROM activites WHERE type != 'individuel' AND actif = 1 ORDER BY ordre")->fetchAll();
}

function activites(?string $type = null): array
{
    if ($type === null) return db()->query('SELECT * FROM activites ORDER BY ordre')->fetchAll();
    $s = db()->prepare('SELECT * FROM activites WHERE type = ? ORDER BY ordre');
    $s->execute([$type]);
    return $s->fetchAll();
}

// Jeton signé pour les formulaires publics (anti-robot et anti-envoi depuis un autre site), sans cookie
function jetonFormulaire(): string
{
    $t = (string) time();
    return $t . '.' . hash_hmac('sha256', "form|$t", config()['secret']);
}

function verifierJetonFormulaire(string $jeton): bool
{
    [$t, $sig] = array_pad(explode('.', $jeton, 2), 2, '');
    if (!ctype_digit($t) || !hash_equals(hash_hmac('sha256', "form|$t", config()['secret']), $sig)) return false;
    $age = time() - (int) $t;
    return $age >= 2 && $age <= 7200; // un humain met au moins 2 s ; le formulaire reste valable 2 h
}
