<?php
// En-tête, pied de page et morceaux communs du site public.
declare(strict_types=1);

const MENU = [['/groupes', 'Groupes'], ['/seances', 'Séances individuelles'], ['/activites', 'Activités'], ['/a-propos', 'À propos'], ['/contact', 'Contact']];

function entetesSecurite(bool $gestion = false): void
{
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header('X-Frame-Options: DENY');
    if (str_starts_with(config()['url'], 'https://')) header('Strict-Transport-Security: max-age=31536000');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; script-src 'self'"
        . ($gestion ? ' https://cdnjs.cloudflare.com' : '') . "; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self' https://www.mollie.com https://*.mollie.com");
}

function entete(string $titre, string $description, string $actif = '', string $canonique = ''): void
{
    $url = rtrim(config()['url'], '/');
    $nav = $mnav = '';
    foreach (MENU as [$lien, $nom]) {
        $courant = $actif === $lien ? ' aria-current="page"' : '';
        $nav .= "<a href=\"$lien\"$courant>$nom</a>";
        $mnav .= "<a href=\"$lien\">$nom</a>";
    }
    $robots = config()['dev'] || config()['essai'] ? '<meta name="robots" content="noindex,nofollow">' : '';
    echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">', $robots,
        '<title>', h($titre), '</title><meta name="description" content="', h($description), '">',
        '<link rel="canonical" href="', h($url . ($canonique ?: strtok($_SERVER['REQUEST_URI'] ?? '/', '?'))), '">',
        '<meta property="og:title" content="', h($titre), '"><meta property="og:description" content="', h($description), '"><meta property="og:type" content="website"><meta property="og:locale" content="fr_FR">',
        '<meta property="og:image" content="', $url, '/assets/ph/beaujolais.webp">',
        '<link rel="icon" href="/assets/ph/logo.webp">',
        '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>',
        '<link href="https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible+Next:wght@400;600&family=Newsreader:ital,opsz,wght@0,6..72,300..500;1,6..72,300..400&display=swap" rel="stylesheet">',
        '<link rel="stylesheet" href="/assets/style.css?v=', VERSION_ACTIFS, '">',
        '</head><body><a class="saut" href="#contenu">Aller au contenu</a>',
        '<header class="top"><div class="wrap"><a class="marque" href="/"><img src="/assets/ph/logo.webp" alt="" width="44" height="44"><span><b>Sandrine Peyron</b><small>Accompagnement individuel et en groupe</small></span></a>',
        '<nav class="nav" aria-label="Menu principal">', $nav, '</nav><a class="btn plein" href="/seances">Réserver</a>',
        '<button class="burger" type="button" aria-expanded="false" aria-controls="mnav">Menu</button></div></header>',
        '<nav class="mnav" id="mnav" aria-label="Menu mobile">', $mnav, '<a class="btn plein" href="/seances">Réserver une séance</a></nav>',
        '<main id="contenu">';
}

function pied(bool $avecReservation = false): void
{
    $tel = param('telephone');
    $mail = param('email_sandrine');
    $liens = implode('', array_map(fn ($m) => "<li><a href=\"{$m[0]}\">{$m[1]}</a></li>", MENU));
    echo '</main><footer class="pied"><div class="wrap"><div class="cols">',
        '<div><h3>Sandrine Peyron</h3><p>Accompagnement individuel et en groupe, au cabinet de Belleville-en-Beaujolais ou en visio.</p></div>',
        '<div><h3>Cabinet</h3><p>', nl2br(h(param('facture_adresse'))), '</p></div>',
        '<div><h3>Contact</h3><ul><li><a href="tel:', h(telLien($tel)), '">', h($tel), '</a></li><li><a href="mailto:', h($mail), '">', h($mail), '</a></li></ul></div>',
        '<div><h3>Pages</h3><ul>', $liens, '</ul></div>',
        '</div><div class="bas"><span>© ', date('Y'), ' Sandrine Peyron. <a href="/mentions-legales">Mentions légales</a>, <a href="/confidentialite">confidentialité</a>, <a href="/conditions-de-vente">conditions de vente</a>.</span>',
        '<span>Site créé par <a href="https://legenienumerique.fr">Le Génie Numérique</a></span></div></div></footer>';
    if ($avecReservation) fenetreReservation();
    echo '<script src="/assets/main.js?v=', VERSION_ACTIFS, '"></script></body></html>';
}

function telLien(string $tel): string { return '+33' . substr(preg_replace('/\D/', '', $tel), 1); }

// Fenêtre de réservation : le formulaire part vers /reserver, qui renvoie vers la page de paiement Mollie.
function fenetreReservation(): void
{
    echo '<dialog class="resa" aria-labelledby="resa-t1"><button class="x" type="button" aria-label="Fermer">×</button>',
        '<form class="in" method="post" action="/reserver">',
        '<input type="hidden" name="jeton" value="', h(jetonFormulaire()), '"><input type="hidden" name="type"><input type="hidden" name="groupe"><input type="hidden" name="activite"><input type="hidden" name="debut">',
        '<p class="piege" aria-hidden="true"><label>Ne pas remplir <input name="site_web" tabindex="-1" autocomplete="off"></label></p>',
        '<ol class="etapes"><li>Récapitulatif</li><li>Coordonnées et paiement</li></ol>',
        '<div data-etape="1"><h2 id="resa-t1" tabindex="-1">Votre réservation</h2><div class="recap"></div>',
        '<p class="small muted">Annulation gratuite jusqu’à ', paramInt('annulation_heures'), ' heures avant le début. <a href="/conditions-de-vente" target="_blank">Conditions de vente</a></p>',
        '<div class="btns"><span></span><button class="btn plein" type="button" data-suite>Continuer</button></div></div>',
        '<div data-etape="2" hidden><h2 tabindex="-1">Vos coordonnées</h2>',
        '<p class="small muted ado-note" hidden>Pour un adolescent, l’inscription est faite par un parent ou responsable légal : indiquez vos coordonnées.</p>',
        '<label class="champ"><span>Prénom</span><input name="prenom" autocomplete="given-name" required maxlength="80"></label>',
        '<label class="champ"><span>Nom</span><input name="nom" autocomplete="family-name" required maxlength="80"></label>',
        '<label class="champ"><span>E-mail</span><input name="email" type="email" autocomplete="email" required maxlength="160"></label>',
        '<label class="champ"><span>Téléphone</span><input name="telephone" type="tel" autocomplete="tel" required maxlength="30" pattern="[\d \+\(\)\.\-]{10,30}"></label>',
        '<label class="coche"><input type="checkbox" name="consentement" value="1" required><span>J’accepte que ces informations servent uniquement à gérer ma réservation (<a href="/confidentialite" target="_blank">confidentialité</a>) et j’accepte les <a href="/conditions-de-vente" target="_blank">conditions de vente</a>.</span></label>',
        '<p class="small muted">Aucune information sur votre santé ne vous est demandée. Paiement sécurisé par carte avec Mollie.</p>',
        '<div class="btns"><button class="btn" type="button" data-retour>Retour</button><button class="btn plein" type="submit">Payer <span class="prix"></span></button></div></div>',
        '</form></dialog>';
}

// Liste des groupes d'une ou plusieurs activités, avec leur état
function blocGroupes(array $listeActivites, bool $filtrable = false, ?int $limite = null): void
{
    $lignes = [];
    foreach ($listeActivites as $a) foreach (groupesActivite($a) as $g) $lignes[] = [$a, $g];
    usort($lignes, fn ($x, $y) => strcmp($x[1]['premiere'], $y[1]['premiere']));
    if ($limite) $lignes = array_slice(array_values(array_filter($lignes, fn ($l) => in_array($l[1]['etat'], ['ouvert', 'attente'], true))), 0, $limite);
    if (!$lignes) {
        echo '<p class="vide">Aucun groupe n’est programmé pour le moment. Les prochaines dates seront annoncées ici. Pour être prévenu, <a href="/contact">contactez Sandrine</a>.</p>';
        return;
    }
    echo '<div class="groupes" id="liste-groupes">';
    foreach ($lignes as [$a, $g]) {
        $seances = seancesGroupe((int) $g['id']);
        $points = '';
        for ($i = 0; $i < (int) $g['capacite']; $i++) $points .= $i < (int) $g['confirmes'] + (int) $g['en_attente'] ? '<i class="pris"></i>' : '<i></i>';
        $nb = count($seances);
        $rythme = $nb . ' séance' . ($nb > 1 ? 's' : '') . ($g['horaire'] !== '' ? ', ' . h($g['horaire']) : ', ' . heure($seances[0]['debut']) . ' à ' . heure($seances[0]['fin']));
        $periode = $nb > 1 ? 'du ' . dateLongue($g['premiere'], false) . ' au ' . dateLongue($seances[$nb - 1]['debut'], false) : dateLongue($g['premiere']);
        $classe = match ($g['etat']) { 'ouvert' => '', 'complet' => 'complet', default => 'ferme' };
        $info = match ($g['etat']) {
            'ouvert' => $g['restantes'] . ' place' . ($g['restantes'] > 1 ? 's' : '') . ' sur ' . $g['capacite'],
            'attente' => 'Dernières places en cours de réservation',
            'complet' => 'Complet',
            'commence' => 'Groupe commencé',
            default => 'Pas encore ouvert',
        };
        if ($g['reservable']) {
            $donnees = ['type' => 'groupe', 'groupe' => (int) $g['id'], 'titre' => $a['nom'], 'quand' => "$rythme, $periode", 'lieu' => $g['lieu'], 'prix' => euros($g['tarif']), 'ado' => $a['code'] === 'sophro-ados'];
            $action = '<button class="btn plein" type="button" data-resa="' . h(json_encode($donnees, JSON_UNESCAPED_UNICODE)) . '">Réserver</button>';
        } else {
            $action = '<span class="verrou">' . match ($g['etat']) {
                'ouvert' => 'Réservation en ligne bientôt ouverte. Appelez le ' . h(param('telephone')) . '.',
                'attente' => 'Revenez dans un quart d’heure : une place peut se libérer.',
                'complet' => 'Groupe complet.',
                'commence' => 'Inscriptions closes.',
                default => 'Ouvre quand le groupe précédent est complet.',
            } . '</span>';
        }
        $cat = $a['type'] === 'parole' ? 'parole' : 'sophro';
        echo "<article class=\"gr $classe\" data-cat=\"$cat\"><div><h3><a href=\"/groupes/", h($a['code']), '">', h($a['nom']), '</a></h3><span class="muted small">Groupe ', $g['rang'], $g['tarif'] !== null ? ', ' . euros($g['tarif']) . ' le cycle' : '', '</span></div>',
            "<div class=\"quand\"><b>$rythme</b>$periode<br><span class=\"muted\">", h($g['lieu']), '</span></div>',
            '<div><div class="places" aria-hidden="true">', $points, '</div><span class="etat">', $info, '</span></div>', $action, '</article>';
    }
    echo '</div>';
}

function pageErreur(int $code, string $titre, string $message): void
{
    http_response_code($code);
    entete($titre, $message);
    echo '<section class="ptete"><div class="wrap"><h1>', h($titre), '</h1><p class="lead">', h($message), '</p><div class="btns"><a class="btn plein" href="/">Revenir à l’accueil</a><a class="btn" href="/contact">Contacter Sandrine</a></div></div></section>';
    pied();
}
