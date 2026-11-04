<?php
// Pages du site public.
declare(strict_types=1);

const VAGUES = '<svg viewBox="0 0 900 1000" preserveAspectRatio="xMidYMax slice" aria-hidden="true">
<g class="vagues v1"><path fill="#DAD8F0" d="M0 470 C150 420 300 520 450 470 S750 420 900 470 V1000 H0Z"/></g>
<g class="vagues v2"><path fill="#BEBBE2" d="M0 580 C160 540 290 630 450 585 S760 530 900 590 V1000 H0Z"/></g>
<g class="vagues v3"><path fill="#9B97CC" d="M0 690 C140 650 310 740 470 695 S770 650 900 700 V1000 H0Z"/></g>
<g class="vagues v4"><path fill="#6F6BAE" d="M0 800 C170 760 300 850 460 805 S760 760 900 810 V1000 H0Z"/></g></svg>';

function carteArticle(array $a): string
{
    $img = $a['image'] ? '/' . $a['image'] : '/assets/ph/foret.webp';
    $cat = $a['categorie'] === 'compte_rendu' ? 'Compte rendu' : 'Article';
    return '<a class="art" href="/activites/' . h($a['slug']) . '" data-cat="' . h($a['categorie']) . '"><div class="img"><img src="' . h($img) . '" alt="" loading="lazy"></div>'
        . '<div class="meta"><span class="tag">' . $cat . '</span><span>' . h(dateLongue($a['publie_le'], false)) . '</span></div><h3>' . h($a['titre']) . '</h3><p class="muted">' . h($a['resume']) . '</p></a>';
}

function blocCabinet(): void
{
    $tel = param('telephone');
    echo '<div class="cabinet"><div><h3>Adresse</h3><p>', nl2br(h(param('facture_adresse'))), '</p><p style="margin-top:12px"><a href="https://www.openstreetmap.org/search?query=50%20rue%20Victor%20Hugo%2069220%20Belleville-en-Beaujolais">Voir sur la carte</a></p></div>',
        '<div><h3>Accès</h3><p>15 min de Villefranche-sur-Saône<br>20 min de Mâcon<br>45 min de Lyon et de Bourg-en-Bresse</p></div>',
        '<div><h3>Contact</h3><p><a href="tel:', h(telLien($tel)), '">', h($tel), '</a><br><a href="mailto:', h(param('email_sandrine')), '">', h(param('email_sandrine')), '</a></p></div></div>';
}

function pageAccueil(): void
{
    entete('Sandrine Peyron, sophrologie, EMDR-DSA et groupes de parole à Belleville-en-Beaujolais',
        'Séances individuelles de sophrologie et d’EMDR-DSA, groupes de parole et de sophrologie à Belleville-en-Beaujolais. Réservation et paiement en ligne.');
    echo '<section class="hero"><div class="wrap"><div>',
        '<h1 class="entre">Prendre soin de soi, seul ou à plusieurs</h1>',
        '<p class="lead entre d1">Sophrologie, EMDR-DSA et groupes de parole, au cabinet de Belleville-en-Beaujolais ou en visio.</p>',
        '<div class="btns entre d2"><a class="btn plein" href="/groupes">Voir les groupes</a><a class="btn" href="/seances">Réserver une séance</a></div>',
        '<p class="lieu entre d3">15 minutes de Villefranche-sur-Saône, 20 minutes de Mâcon, 45 minutes de Lyon.</p></div>',
        '<div class="souffle"><div class="forme">', VAGUES, '<p class="mot" aria-hidden="true">Inspirez…</p></div><button class="pause" type="button" aria-pressed="false">Pause</button></div>',
        '</div></section>',
        '<section class="sec papier"><div class="wrap"><h2>Trois façons d’être accompagné</h2><ul class="offres">',
        '<li><a href="/groupes#parole"><h3>Groupes de parole</h3><p>Soutien à la parentalité, aux aidants familiaux, et pour mieux traverser un deuil.</p><span class="fl" aria-hidden="true">›</span></a></li>',
        '<li><a href="/groupes#sophro"><h3>Groupes de sophrologie</h3><p>Pour les adolescents et les adultes qui traversent une période de fragilité psychique.</p><span class="fl" aria-hidden="true">›</span></a></li>',
        '<li><a href="/seances"><h3>Séances individuelles</h3><p>Sophrologie caycédienne, EMDR-DSA ou psychanalyse clinique, au cabinet ou en visio.</p><span class="fl" aria-hidden="true">›</span></a></li>',
        '</ul></div></section>',
        '<section class="sec"><div class="wrap"><div class="tete"><h2>Prochains groupes</h2><a href="/groupes">Tous les groupes</a></div>',
        '<p class="regle">Chaque groupe ouvre ses inscriptions quand le précédent est complet : les groupes restent à taille humaine.</p>';
    blocGroupes(array_merge(activites('parole'), activites('sophro_groupe')), false, 4);
    echo '</div></section>',
        '<figure class="paysage"><img src="/assets/ph/beaujolais.webp" alt="Vignes du Beaujolais émergeant de la brume du matin" loading="lazy"></figure>',
        '<section class="sec"><div class="wrap duo"><div class="galet"><img src="/assets/ph/portrait.webp" alt="Portrait de Sandrine Peyron, souriante, en extérieur" loading="lazy"></div><div>',
        '<h2>Sandrine Peyron</h2><p class="lead">Après 36 ans dans le développement social, auprès des familles, j’ai repris mes études à 45 ans pour accompagner autrement.</p>',
        '<p>Sophrologue de courant caycédien, praticienne en EMDR-DSA et psychanalyste clinicienne, je veille à créer un espace sûr, où chacun avance à son rythme.</p><a href="/a-propos">Mon parcours</a>',
        '<div class="labels"><img src="/assets/ph/ecoles-sophro.webp" alt="Écoles de Sophrologie Caycédienne" loading="lazy"><img src="/assets/ph/chambre-syndicale.webp" alt="Chambre syndicale de la sophrologie" loading="lazy"></div>',
        '</div></div></section>';
    $articles = articlesPublies(3);
    if ($articles) {
        echo '<section class="sec papier"><div class="wrap"><div class="tete"><h2>Activités</h2><a href="/activites">Toutes les publications</a></div><div class="articles">',
            implode('', array_map('carteArticle', $articles)), '</div></div></section>';
    }
    echo '<section class="sec"><div class="wrap"><h2>Le cabinet</h2>';
    blocCabinet();
    echo '</div></section>';
    pied(true);
}

function pageGroupes(): void
{
    entete('Groupes de parole et de sophrologie à Belleville-en-Beaujolais, Sandrine Peyron',
        'Groupes de parole (parentalité, aidants familiaux, deuil) et groupes de sophrologie pour adolescents et adultes. Places limitées, réservation en ligne.', '/groupes');
    echo '<section class="ptete"><div class="wrap duo" style="grid-template-columns:1.1fr .9fr"><div><h1>Avancer ensemble, en petit groupe</h1>',
        '<p class="lead">Quelques personnes qui vivent la même chose, un cadre bienveillant, une séance par semaine. Places limitées.</p></div>',
        '<div class="galet" style="aspect-ratio:16/11"><img src="/assets/ph/cercle.webp" alt="Des chaises disposées en cercle"></div></div></section>',
        '<section class="sec papier" style="padding-top:64px"><div class="wrap"><div class="deux">';
    foreach ([['parole', 'Groupes de parole', 'Un lieu pour déposer ce qui pèse, être écouté et entendre d’autres vécus.'], ['sophro', 'Groupes de sophrologie', 'Respiration, relaxation dynamique et visualisation, pour retrouver un appui. Ces groupes complètent un suivi médical, ils ne le remplacent pas.']] as [$id, $titre, $texte]) {
        echo "<div id=\"$id\"><h2>$titre</h2><p>$texte</p><ul class=\"liste-act\">";
        foreach (activites($id === 'parole' ? 'parole' : 'sophro_groupe') as $a) {
            echo '<li><a href="/groupes/', h($a['code']), '"><b>', h($a['nom']), '</b><span>', h($a['resume']), '</span></a></li>';
        }
        echo '</ul></div>';
    }
    echo '</div></div></section><section class="sec"><div class="wrap"><h2>Dates et inscriptions</h2>',
        '<div class="filtres" data-filtres="#liste-groupes" role="group" aria-label="Filtrer les groupes"><button type="button" data-f="tous" aria-pressed="true">Tous</button><button type="button" data-f="parole" aria-pressed="false">Groupes de parole</button><button type="button" data-f="sophro" aria-pressed="false">Sophrologie</button></div>';
    blocGroupes(array_merge(activites('parole'), activites('sophro_groupe')));
    echo '<p class="regle">Comment ça marche : vous réservez et payez en ligne, votre place est confirmée par e-mail avec les dates à ajouter à votre agenda. Le groupe suivant d’une même activité ouvre quand celui-ci est complet.</p></div></section>';
    pied(true);
}

function pageGroupe(string $code): void
{
    $a = activite($code);
    $c = CONTENUS_GROUPES[$code] ?? null;
    if (!$a || !$c || !in_array($a['type'], ['parole', 'sophro_groupe'], true)) { pageErreur(404, 'Page introuvable', 'Cette page n’existe pas ou plus.'); return; }
    entete($a['nom'] . ' à Belleville-en-Beaujolais, Sandrine Peyron', $c['accroche'], '/groupes');
    $tarif = $a['tarif_centimes'] !== null ? euros((int) $a['tarif_centimes']) . ' le cycle' : 'Tarif communiqué prochainement';
    echo '<section class="ptete"><div class="wrap duo" style="grid-template-columns:1.1fr .9fr"><div>',
        '<p class="fil"><a href="/groupes">Groupes</a></p><h1>', h($c['titre']), '</h1><p class="lead">', h($c['accroche']), '</p>',
        '<div class="btns"><a class="btn plein" href="#dates">Voir les dates</a></div></div>',
        '<div class="galet"><img src="/assets/ph/', h($c['image']), '.webp" alt="', h($c['alt']), '"></div></div></section>',
        '<section class="sec papier"><div class="wrap deux"><div class="prose"><h2 style="margin-top:0">Pour qui ?</h2><ul>';
    foreach ($c['pour_qui'] as $l) echo '<li>', h($l), '</li>';
    echo '</ul><h2>Comment se passe une séance</h2><ol class="etapes-seance">';
    foreach ($c['deroule'] as $l) echo '<li>', h($l), '</li>';
    echo '</ol></div><div><div class="fiche"><dl><dt>Durée</dt><dd>', (int) $a['duree_minutes'], ' minutes par séance</dd><dt>Groupe</dt><dd>Petit effectif, fixé pour chaque groupe</dd><dt>Lieu</dt><dd>Cabinet du Petit Prince, Belleville-en-Beaujolais</dd><dt>Tarif</dt><dd>', h($tarif), '</dd></dl></div>',
        '<div class="prose" style="margin-top:32px"><h2 style="margin-top:0">Le cadre</h2><p>Ce qui se dit dans le groupe reste dans le groupe. Personne n’est obligé de parler. Sandrine anime chaque séance et veille à ce que chacun trouve sa place.</p>',
        isset($c['avertissement']) ? '<p class="small muted">' . h($c['avertissement']) . '</p>' : '', '</div></div></div></section>',
        '<section class="sec" id="dates"><div class="wrap"><h2>Dates et inscriptions</h2>';
    blocGroupes([$a]);
    echo '</div></section>';
    pied(true);
}

function blocAgenda(array $a): void
{
    if ($a['tarif_centimes'] === null || !mollieActif()) {
        echo '<div class="agenda"><h3>Prendre rendez-vous</h3><p>La réservation en ligne de cette séance ouvre bientôt. En attendant, appelez Sandrine au <a href="tel:', h(telLien(param('telephone'))), '">', h(param('telephone')), '</a>.</p></div>';
        return;
    }
    echo '<div class="agenda" data-activite="', h($a['code']), '" data-titre="', h($a['nom']), '" data-prix="', h(euros((int) $a['tarif_centimes'])), '" data-duree="', (int) $a['duree_minutes'], ' min">',
        '<h3>Créneaux disponibles</h3><div class="jours" aria-label="Jours"><p class="muted small">Chargement des créneaux…</p></div><p class="choix muted small" aria-live="polite" style="margin:16px 0 0"></p><div class="creneaux"></div>',
        '<noscript><p>Pour réserver, activez JavaScript ou appelez le ', h(param('telephone')), '.</p></noscript></div>';
}

function pageSeances(): void
{
    entete('Séances individuelles de sophrologie et d’EMDR-DSA à Belleville-en-Beaujolais',
        'Réservez en ligne une séance individuelle de sophrologie caycédienne ou d’EMDR-DSA avec Sandrine Peyron, au cabinet ou en visio.', '/seances');
    $sophro = activite('sophrologie');
    $emdr = activite('emdr');
    echo '<section class="ptete"><div class="wrap duo" style="grid-template-columns:1.1fr .9fr"><div><h1>Séances individuelles</h1><p class="lead">Choisissez l’accompagnement, puis un créneau libre. La réservation se règle en ligne.</p>',
        '<p class="small muted">Vous cherchez la psychanalyse clinique ? <a href="/psychanalyse">C’est par ici</a>.</p></div>',
        '<div class="galet" style="aspect-ratio:1"><img src="/assets/ph/the.webp" alt="Deux mains tiennent une tasse de thé chaude"></div></div></section>',
        '<section class="sec papier" style="padding-top:56px"><div class="wrap">',
        '<div class="onglets" role="tablist" aria-label="Type de séance"><button role="tab" id="t-sophro" aria-controls="p-sophro" aria-selected="true">Sophrologie</button><button role="tab" id="t-emdr" aria-controls="p-emdr" aria-selected="false" tabindex="-1">EMDR-DSA</button></div>',
        '<div class="panneau" id="p-sophro" role="tabpanel" aria-labelledby="t-sophro"><div class="deux"><div class="prose"><h2 style="margin-top:0">Sophrologie caycédienne</h2>',
        '<p>Une méthode fondée par le neuropsychiatre Alfonso Caycedo, qui associe respiration, relaxation dynamique, visualisation et méditation.</p>',
        '<p>Pour gagner en confiance et en estime de soi, diminuer le stress et l’anxiété, gérer la douleur ou les acouphènes, mieux dormir, préparer un examen ou une prise de parole.</p>',
        '<p class="small muted">La sophrologie n’est pas une pratique médicale et ne remplace pas un traitement. Elle peut compléter un suivi médical.</p></div>',
        '<div class="fiche"><dl><dt>Durée</dt><dd>', (int) $sophro['duree_minutes'], ' minutes</dd><dt>Lieu</dt><dd>Selon le créneau : cabinet ou visio</dd><dt>Tarif</dt><dd>', h(euros($sophro['tarif_centimes'] === null ? null : (int) $sophro['tarif_centimes'])), '</dd></dl></div></div>';
    blocAgenda($sophro);
    echo '</div><div class="panneau" id="p-emdr" role="tabpanel" aria-labelledby="t-emdr" hidden><div class="deux"><div class="prose"><h2 style="margin-top:0">EMDR-DSA</h2>',
        '<p>Une thérapie brève par stimulations alternées (visuelles, auditives ou tactiles), pour retraiter des souvenirs qui pèsent encore sur le présent.</p>',
        '<p>On part d’une « clé d’entrée » : une situation passée ou à venir, une émotion, un ressenti dans le corps ou une pensée négative.</p>',
        '<p>Du simple stress aux traumatismes complexes, mais aussi douleurs chroniques, deuils ou préparation mentale. Certains troubles relèvent d’un suivi par un spécialiste de santé.</p></div>',
        '<div class="fiche"><dl><dt>Durée</dt><dd>', (int) $emdr['duree_minutes'], ' minutes</dd><dt>Lieu</dt><dd>Selon le créneau : cabinet ou visio</dd><dt>Tarif</dt><dd>', h(euros($emdr['tarif_centimes'] === null ? null : (int) $emdr['tarif_centimes'])), '</dd></dl></div></div>',
        '<figure class="paysage" style="margin:40px 0 0;aspect-ratio:21/7"><img src="/assets/ph/regard.webp" alt="Gros plan sur un œil" loading="lazy"></figure>';
    blocAgenda($emdr);
    echo '</div></div></section>';
    pied(true);
}

function pagePsychanalyse(): void
{
    entete('Psychanalyse clinique à Belleville-en-Beaujolais, Sandrine Peyron', 'Un dispositif d’écoute fondé sur la parole, pour interroger son rapport à soi, aux autres et à son histoire.', '/seances');
    $a = activite('psychanalyse');
    echo '<section class="ptete"><div class="wrap duo" style="grid-template-columns:1.1fr .9fr"><div><h1>Psychanalyse clinique</h1>',
        '<p class="lead">Un dispositif d’écoute et d’élaboration fondé sur la parole, pour interroger son rapport à soi, aux autres et à son histoire.</p></div>',
        '<div class="galet"><img src="/assets/ph/psychanalyse.webp" alt="Un étang dans la brume du matin, entre les arbres"></div></div></section>',
        '<section class="sec papier"><div class="wrap deux"><div class="prose">',
        '<p>La psychanalyse clinique s’adresse à toute personne qui souhaite comprendre ce qui se répète dans sa vie : symptômes, conflits internes, difficultés relationnelles.</p>',
        '<p>Par l’association libre et dans le cadre du transfert, ces manifestations de la souffrance psychique peuvent prendre sens. Le travail permet peu à peu de déplacer sa manière de se penser, de désirer et de vivre.</p>',
        '<p>Sandrine est reconnue psychanalyste clinicienne au sein de la Collégiale Psychanalytique de l’Ain, après 19 ans de réflexion en psychanalyse lacanienne.</p></div><div>';
    blocAgenda($a);
    echo '</div></div></section>';
    pied(true);
}

function pageActivites(): void
{
    entete('Activités, articles et comptes rendus, Sandrine Peyron', 'Articles et comptes rendus des groupes et ateliers de Sandrine Peyron.', '/activites');
    $articles = articlesPublies();
    echo '<section class="ptete"><div class="wrap"><h1>Activités</h1><p class="lead">Articles, comptes rendus de groupes et d’ateliers.</p></div></section><section class="sec papier" style="padding-top:56px"><div class="wrap">';
    if (!$articles) echo '<p class="vide">Les premières publications arrivent bientôt.</p>';
    else {
        echo '<div class="filtres" data-filtres="#liste-art" role="group" aria-label="Filtrer les publications"><button type="button" data-f="tous" aria-pressed="true">Tout</button><button type="button" data-f="article" aria-pressed="false">Articles</button><button type="button" data-f="compte_rendu" aria-pressed="false">Comptes rendus</button></div>',
            '<div class="articles" id="liste-art">', implode('', array_map('carteArticle', $articles)), '</div>';
    }
    echo '</div></section>';
    pied();
}

function pageArticle(string $slug): void
{
    $a = articlePublie($slug);
    if (!$a) { pageErreur(404, 'Publication introuvable', 'Cette publication n’existe pas ou a été retirée.'); return; }
    entete($a['titre'] . ', Sandrine Peyron', $a['resume'] ?: mb_substr(strip_tags(texteEnHtml($a['contenu'])), 0, 155), '/activites');
    echo '<article><section class="ptete"><div class="wrap"><p class="fil"><a href="/activites">Activités</a></p><h1>', h($a['titre']), '</h1>',
        '<p class="muted">', $a['categorie'] === 'compte_rendu' ? 'Compte rendu' : 'Article', ', publié le ', h(dateLongue($a['publie_le'], false)), '</p></div></section>';
    if ($a['image']) echo '<figure class="paysage" style="margin-bottom:0"><img src="/', h($a['image']), '" alt=""></figure>';
    echo '<section class="sec"><div class="wrap prose texte-article">', $a['resume'] ? '<p class="lead">' . h($a['resume']) . '</p>' : '', texteEnHtml($a['contenu']), '</div></section></article>';
    pied();
}

function pageAPropos(): void
{
    entete('À propos de Sandrine Peyron, sophrologue et praticienne EMDR-DSA', 'Parcours, formations et approche de Sandrine Peyron, sophrologue caycédienne, praticienne EMDR-DSA et psychanalyste clinicienne.', '/a-propos');
    echo '<section class="ptete"><div class="wrap duo"><div><h1>Un chemin vers l’autre</h1><p class="lead">Sophrologue de courant caycédien, praticienne en EMDR-DSA et psychanalyste clinicienne, au cabinet de Belleville-en-Beaujolais.</p></div>',
        '<div class="galet"><img src="/assets/ph/portrait.webp" alt="Portrait de Sandrine Peyron"></div></div></section>',
        '<section class="sec papier"><div class="wrap deux"><div class="prose"><h2 style="margin-top:0">Mon parcours</h2>',
        '<p>J’ai consacré 36 années au développement social des territoires, pour accompagner la famille sous toutes ses formes.</p>',
        '<p>À 45 ans, j’ai repris mes études : certification EMDR niveaux I et II, master de sophrologie et validation des quatre degrés de la méthode caycédienne.</p>',
        '<p>En parallèle, 19 ans de réflexion en psychanalyse lacanienne, dont quatre en cartel de lecture au sein de la Collégiale Psychanalytique de l’Ain, où je suis reconnue psychanalyste clinicienne.</p>',
        '<h2>Ma façon d’accompagner</h2><p>Créer un espace sûr, écouter, transmettre des outils simples que chacun peut garder. Je reçois en individuel et en groupe, au cabinet, à domicile ou en visio, des particuliers comme des professionnels.</p>',
        '<h2>Et sinon…</h2><p>J’aime le jazz, la musique classique, la nature, les animaux, le thé bergamote, le petit café chaud et le chocolat.</p></div>',
        '<div><h2 style="margin-top:0">Formations</h2><ul class="prose"><li>CLK Formation, Sophrologie Caycédienne, Lyon</li><li>EMDR-DSA Alpes Savoie</li><li>AHTMA Formation, Académie d’Hypnose Thérapeutique, Nantes</li><li>Membre de la Chambre syndicale de la sophrologie</li></ul>',
        '<img src="/assets/ph/certif-sophro.webp" alt="Certificat de sophrologue de Sandrine Peyron" loading="lazy" style="border-radius:16px;margin-top:28px">',
        '<img src="/assets/ph/certif-emdr.webp" alt="Certification de praticienne en EMDR-DSA, niveau 1" loading="lazy" style="border-radius:16px;margin-top:16px"></div></div></section>';
    pied();
}

function pageContact(): void
{
    entete('Contact et accès au cabinet, Sandrine Peyron', 'Cabinet du Petit Prince, 50 rue Victor Hugo, 69220 Belleville-en-Beaujolais. Téléphone et e-mail.', '/contact');
    $tel = param('telephone');
    echo '<section class="ptete"><div class="wrap"><h1>Me contacter</h1><p class="lead">Une question avant de réserver ? Appelez-moi ou écrivez-moi.</p>',
        '<div class="btns"><a class="btn plein" href="tel:', h(telLien($tel)), '">Appeler le ', h($tel), '</a><a class="btn" href="mailto:', h(param('email_sandrine')), '">Écrire un e-mail</a></div></div></section>',
        '<section class="sec papier"><div class="wrap">';
    blocCabinet();
    echo '</div></section>';
    pied();
}

function pageLegale(string $quoi): void
{
    $nom = h(param('facture_nom'));
    $siret = param('facture_siret') !== '' ? h(param('facture_siret')) : '[à compléter]';
    $adresse = nl2br(h(param('facture_adresse')));
    $mail = h(param('email_sandrine'));
    $h = paramInt('annulation_heures');
    [$titre, $corps] = match ($quoi) {
        'mentions-legales' => ['Mentions légales', "<h2>Éditrice</h2><p>$nom, entrepreneure individuelle<br>$adresse<br>SIRET : $siret<br>$mail</p><h2>Hébergement</h2><p>alwaysdata, 91 rue du Faubourg Saint-Honoré, 75008 Paris, France.</p><h2>Création</h2><p>Le Génie Numérique, Châtillon-sur-Chalaronne.</p><h2>Photos</h2><p>Portrait et certificats : Sandrine Peyron. Paysages : Unsplash (licence Unsplash).</p>"],
        'confidentialite' => ['Confidentialité', "<p>Responsable du traitement : $nom, $mail.</p><h2>Données collectées</h2><p>Prénom, nom, e-mail, téléphone et réservations. Aucune information sur votre santé n’est demandée.</p><h2>Pourquoi</h2><p>Gérer vos réservations, vous envoyer confirmations, rappels et factures (exécution du contrat), tenir la comptabilité (obligation légale).</p><h2>Combien de temps</h2><p>Coordonnées : 3 ans après votre dernière réservation. Factures : 10 ans (obligation comptable).</p><h2>Qui y a accès</h2><p>Sandrine Peyron uniquement. Les paiements sont traités par Mollie (Pays-Bas), qui ne nous transmet aucun numéro de carte. Les données sont hébergées en France.</p><h2>Cookies</h2><p>Aucun cookie de mesure d’audience ni de publicité.</p><h2>Vos droits</h2><p>Accès, rectification, effacement, opposition : écrivez à $mail. Vous pouvez aussi saisir la CNIL (cnil.fr).</p>"],
        default => ['Conditions de vente', "<p>Prestataire : $nom, $adresse, SIRET $siret.</p><h2>Réservation</h2><p>La réservation est ferme après paiement en ligne. Une confirmation est envoyée par e-mail avec la facture.</p><h2>Prix</h2><p>Prix en euros, toutes taxes comprises. " . h(param('facture_mention_tva')) . ".</p><h2>Annulation par le client</h2><p>Annulation gratuite jusqu’à $h heures avant le début (première séance pour un groupe), avec remboursement intégral, depuis le lien reçu par e-mail. Après ce délai, contactez Sandrine.</p><h2>Annulation par Sandrine</h2><p>En cas d’empêchement de Sandrine ou d’annulation d’un groupe, le montant est intégralement remboursé.</p><h2>Rétractation</h2><p>[Droit de rétractation pour une séance réservée à moins de 14 jours : clause à valider juridiquement.]</p><h2>Médiation</h2><p>[Médiateur de la consommation à compléter.]</p>"],
    };
    entete("$titre, Sandrine Peyron", "$titre du site de Sandrine Peyron.");
    echo '<section class="ptete"><div class="wrap"><h1>', $titre, '</h1></div></section><section class="sec papier" style="padding-top:48px"><div class="wrap prose">', $corps, '</div></section>';
    pied();
}

function pageReservation(string $reference): void
{
    $r = reservationPar('reference', $reference);
    if (!$r) { pageErreur(404, 'Réservation introuvable', 'Ce lien ne correspond à aucune réservation.'); return; }
    // Retour de Mollie : on relit le statut (utile si le webhook n'est pas encore passé)
    if ($r['mollie_id'] && !in_array($r['paiement_statut'], ['paid', 'failed', 'canceled', 'expired'], true)) {
        try { $r = traiterPaiement($r['mollie_id']) ?? $r; } catch (Throwable $e) { journaliserErreur($e); }
    }
    header('Cache-Control: no-store');
    entete('Votre réservation, Sandrine Peyron', 'Suivi de votre réservation.');
    echo '<section class="ptete"><div class="wrap">';
    if ($r['statut'] === 'confirmee') {
        echo '<h1>C’est réservé</h1><p class="lead">Merci ', h($r['prenom']), '. Votre place est confirmée. Un e-mail de confirmation avec votre facture vient de partir vers ', h($r['email']), '.</p>';
    } elseif ($r['statut'] === 'attente') {
        echo '<h1>Paiement en cours de vérification</h1><p class="lead">Votre paiement n’est pas encore confirmé par la banque. Vous recevrez un e-mail dès qu’il le sera. Inutile de payer une seconde fois.</p><p><a class="btn" href="">Actualiser</a></p>';
    } else {
        echo '<h1>Réservation non aboutie</h1><p class="lead">Le paiement n’a pas été finalisé, aucune somme n’a été prélevée. Vous pouvez recommencer.</p><div class="btns"><a class="btn plein" href="/groupes">Groupes</a><a class="btn" href="/seances">Séances individuelles</a></div>';
    }
    echo '<div class="recap" style="max-width:560px"><p><b>', h(titreReservation($r)), '</b></p><p>Début : ', h(dateLongue($r['debut'])), '</p><p>Lieu : ', h($r['lieu']), '</p><p>Montant : ', h(euros((int) $r['montant_centimes'])), '</p></div></div></section>';
    pied();
}

function pageAnnuler(string $jeton): void
{
    $r = reservationPar('jeton', $jeton);
    if (!$r) { pageErreur(404, 'Lien invalide', 'Ce lien d’annulation ne correspond à aucune réservation.'); return; }
    header('Cache-Control: no-store');
    $message = null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifierJetonFormulaire((string) ($_POST['jeton'] ?? ''))) {
        if (annulationPossibleParClient($r)) {
            try {
                annulerReservation((int) $r['id'], 'client', (int) $r['montant_centimes'] - (int) $r['rembourse_centimes'], 'Annulée par le client');
                $message = 'Votre réservation est annulée. Le remboursement est en cours : il apparaîtra sur votre compte sous quelques jours.';
            } catch (Throwable $e) { journaliserErreur($e); $message = 'L’annulation n’a pas pu aboutir. Contactez Sandrine au ' . param('telephone') . '.'; }
            $r = reservation((int) $r['id']);
        }
    }
    entete('Annuler une réservation, Sandrine Peyron', 'Annulation d’une réservation.');
    echo '<section class="ptete"><div class="wrap"><h1>Annuler ma réservation</h1>';
    echo '<div class="recap" style="max-width:560px"><p><b>', h(titreReservation($r)), '</b></p><p>Début : ', h(dateLongue($r['debut'])), '</p><p>Montant : ', h(euros((int) $r['montant_centimes'])), '</p></div>';
    if ($message) echo '<p class="lead">', h($message), '</p>';
    elseif ($r['statut'] === 'annulee') echo '<p class="lead">Cette réservation est déjà annulée.</p>';
    elseif (annulationPossibleParClient($r)) {
        echo '<p>Vous serez remboursé(e) de ', h(euros((int) $r['montant_centimes'] - (int) $r['rembourse_centimes'])), ' sur votre carte.</p>',
            '<form method="post"><input type="hidden" name="jeton" value="', h(jetonFormulaire()), '"><button class="btn plein" type="submit">Confirmer l’annulation</button></form>';
    } else {
        echo '<p class="lead">Le délai d’annulation en ligne (', paramInt('annulation_heures'), ' heures avant le début) est dépassé. Contactez Sandrine au <a href="tel:', h(telLien(param('telephone'))), '">', h(param('telephone')), '</a>.</p>';
    }
    echo '</div></section>';
    pied();
}
