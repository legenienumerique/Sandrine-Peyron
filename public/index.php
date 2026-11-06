<?php
// Point d'entrée unique du site (pages publiques, réservation, webhook Mollie, espace de gestion).
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
require RACINE . '/app/vues/gabarit.php';
require RACINE . '/app/vues/contenus.php';
require RACINE . '/app/vues/pages.php';

const VERSION_ACTIFS = '2';

$chemin = '/' . trim(rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)), '/');
$methode = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$estGestion = $chemin === '/gestion' || str_starts_with($chemin, '/gestion/');
$estWebhook = $chemin === '/webhook/mollie';

// Contrôle de santé du back-office LGN : lecture seule, signé, disponible même pendant une coupure
if ($chemin === '/.well-known/lgn-sante') { pageSante($_SERVER); exit; }

// Gestion sur gestion.sandrinepeyron.com uniquement (aucun lien depuis le site public)
[$aiguillage, $adresse] = aiguillageGestion((string) ($_SERVER['HTTP_HOST'] ?? ''), $chemin);
if ($aiguillage === 'redirige') { header('Location: ' . $adresse, true, 302); exit; }
if ($aiguillage === 'introuvable') { http_response_code(404); header('X-Robots-Tag: noindex'); exit('Page introuvable.'); }

// Mise hors ligne décidée depuis le back-office LGN (niveaux 1, 2, 3a, 3b ; expiration vérifiée ici)
switch (decisionCoupure(etatCoupure(), $chemin, $estGestion, $estWebhook)) {
    case 'page503': repondre503('Site momentanément indisponible', 'Une opération de maintenance est en cours. Le site revient très vite.'); exit;
    case 'refus503': repondre503('Accès temporairement suspendu', 'Le site est fermé par précaution. Il sera rétabli dès la fin des vérifications.'); exit;
    case 'webhook_file': http_response_code(mettreWebhookEnFile($_SERVER, (string) file_get_contents('php://input'), $_GET, fn ($id) => (bool) reservationPar('mollie', $id))); exit;
}

// Verrou d'écriture partagé, tenu jusqu'à la fin de la requête (libéré par PHP, même après exit).
// Sauvegarde ou restauration en cours : webhook mis en file, autres requêtes invitées à réessayer.
$GLOBALS['VERROU_TENU'] = prendreVerrou(false, $estWebhook ? 0.5 : 3.0);
if ($GLOBALS['VERROU_TENU'] === false) {
    unset($GLOBALS['VERROU_TENU']);
    if ($estWebhook) { http_response_code(mettreWebhookEnFile($_SERVER, (string) file_get_contents('php://input'), $_GET)); exit; }
    repondre503('Un instant', 'Une sauvegarde est en cours. Réessayez dans une minute.', 60);
    exit;
}

if ($estGestion) {
    entetesSecurite(true);
    require RACINE . '/app/gestion.php';
    exit;
}
entetesSecurite();

try {
    match (true) {
        $chemin === '/' => pageAccueil(),
        $chemin === '/groupes' => pageGroupes(),
        (bool) preg_match('#^/groupes/([a-z0-9-]+)$#', $chemin, $m) => pageGroupe($m[1]),
        $chemin === '/seances' => pageSeances(),
        $chemin === '/psychanalyse' => (function () { header('Location: /seances', true, 301); })(),
        $chemin === '/activites' => pageActivites(),
        (bool) preg_match('#^/activites/([a-z0-9-]+)$#', $chemin, $m) => pageArticle($m[1]),
        $chemin === '/a-propos' => pageAPropos(),
        $chemin === '/contact' => pageContact(),
        in_array($chemin, ['/mentions-legales', '/confidentialite', '/conditions-de-vente'], true) => pageLegale(substr($chemin, 1)),
        $chemin === '/reserver' && $methode === 'POST' => reserver(),
        (bool) preg_match('#^/reservation/([A-Z0-9-]+)$#', $chemin, $m) => pageReservation($m[1]),
        (bool) preg_match('#^/annuler/([A-Za-z0-9_-]{20,})$#', $chemin, $m) => pageAnnuler($m[1]),
        $chemin === '/webhook/mollie' && $methode === 'POST' => webhookMollie(),
        $chemin === '/api/creneaux' => apiCreneaux(),
        $chemin === '/sitemap.xml' => sitemap(),
        default => pageErreur(404, 'Page introuvable', 'Cette page n’existe pas ou a changé d’adresse.'),
    };
} catch (Throwable $e) {
    journaliserErreur($e);
    if (!headers_sent()) pageErreur(500, 'Un souci technique', 'La page n’a pas pu s’afficher. Réessayez dans un instant, ou appelez Sandrine.');
}

function reserver(): void
{
    if (!empty($_POST['site_web']) || !verifierJetonFormulaire((string) ($_POST['jeton'] ?? ''))) {
        pageErreur(400, 'Formulaire expiré', 'Rechargez la page et recommencez votre réservation.');
        return;
    }
    try {
        $r = ($_POST['type'] ?? '') === 'groupe'
            ? reserverGroupe((int) ($_POST['groupe'] ?? 0), $_POST)
            : reserverRdv((string) ($_POST['activite'] ?? ''), (string) ($_POST['debut'] ?? ''), $_POST);
        header('Location: ' . $r['checkout'], true, 303);
    } catch (ErreurReservation $e) {
        pageErreur(409, 'Réservation impossible', $e->getMessage());
    }
}

// Mollie envoie seulement l'identifiant : on relit le paiement chez Mollie, rien n'est cru sur parole.
function webhookMollie(): void
{
    $id = (string) ($_POST['id'] ?? '');
    if (!preg_match('/^tr_\w+$/', $id)) { http_response_code(400); return; }
    try { traiterPaiement($id); http_response_code(200); }
    catch (Throwable $e) { journaliserErreur($e); http_response_code(500); } // Mollie réessaiera
}

function apiCreneaux(): void
{
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    $a = activite((string) ($_GET['activite'] ?? ''));
    if (!$a || $a['type'] !== 'individuel' || !$a['actif'] || $a['tarif_centimes'] === null) { http_response_code(404); echo '{"jours":[]}'; return; }
    $jours = array_map(fn ($j) => ['jour' => $j['jour'], 'libelle' => dateLongue($j['jour'] . ' 00:00:00', false),
        'creneaux' => array_map(fn ($c) => ['debut' => $c['debut'], 'heure' => heure($c['debut']), 'lieu' => $c['lieu']], $j['creneaux'])], creneauxAVenir($a, 28));
    echo json_encode(['jours' => $jours], JSON_UNESCAPED_UNICODE);
}

function sitemap(): void
{
    header('Content-Type: application/xml; charset=utf-8');
    $url = rtrim(config()['url'], '/');
    $pages = ['/', '/groupes', '/seances', '/activites', '/a-propos', '/contact'];
    foreach (array_keys(CONTENUS_GROUPES) as $c) $pages[] = "/groupes/$c";
    foreach (articlesPublies() as $a) $pages[] = '/activites/' . $a['slug'];
    echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    foreach ($pages as $p) echo '<url><loc>', h($url . $p), '</loc></url>';
    echo '</urlset>';
}
