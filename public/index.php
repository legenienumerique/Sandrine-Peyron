<?php
// Point d'entrée unique du site (pages publiques, réservation, webhook Mollie, espace de gestion).
declare(strict_types=1);

require __DIR__ . '/../app/bootstrap.php';
require RACINE . '/app/vues/gabarit.php';
require RACINE . '/app/vues/contenus.php';
require RACINE . '/app/vues/pages.php';

const VERSION_ACTIFS = '1';

$chemin = '/' . trim(rawurldecode((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH)), '/');
$methode = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($chemin === '/gestion' || str_starts_with($chemin, '/gestion/')) {
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
        $chemin === '/psychanalyse' => pagePsychanalyse(),
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
    $pages = ['/', '/groupes', '/seances', '/psychanalyse', '/activites', '/a-propos', '/contact'];
    foreach (array_keys(CONTENUS_GROUPES) as $c) $pages[] = "/groupes/$c";
    foreach (articlesPublies() as $a) $pages[] = '/activites/' . $a['slug'];
    echo '<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
    foreach ($pages as $p) echo '<url><loc>', h($url . $p), '</loc></url>';
    echo '</urlset>';
}
