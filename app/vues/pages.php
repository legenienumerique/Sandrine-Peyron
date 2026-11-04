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
    entete('Sandrine Peyron, accompagnement individuel et en groupe à Belleville-en-Beaujolais',
        'Séances individuelles et groupes à Belleville-en-Beaujolais, au cabinet ou en visio. Réservation et paiement en ligne.');
    echo '<section class="hero"><div class="wrap"><div>',
        '<h1 class="entre">Prendre soin de soi, seul ou à plusieurs</h1>',
        '<p class="lead entre d1">Un accompagnement global, en séance individuelle ou en groupe, au cabinet de Belleville-en-Beaujolais ou en visio.</p>',
        '<div class="btns entre d2"><a class="btn plein" href="/seances">Réserver une séance</a><a class="btn" href="/groupes">Voir les groupes</a></div>',
        '<p class="lieu entre d3">15 minutes de Villefranche-sur-Saône, 20 minutes de Mâcon, 45 minutes de Lyon.</p></div>',
        '<div class="souffle"><div class="forme">', VAGUES, '<p class="mot" aria-hidden="true">Inspirez…</p></div><button class="pause" type="button" aria-pressed="false">Pause</button></div>',
        '</div></section>',
        '<section class="sec papier"><div class="wrap"><h2>Deux façons d’être accompagné</h2><ul class="offres">',
        '<li><a href="/seances"><h3>Séances individuelles</h3><p>Un temps pour vous, construit à partir de votre demande. Au cabinet ou en visio.</p><span class="fl" aria-hidden="true">›</span></a></li>',
        '<li><a href="/groupes"><h3>Groupes</h3><p>Parentalité, aidants familiaux, deuil, santé mentale des adolescents et des adultes. En petit effectif.</p><span class="fl" aria-hidden="true">›</span></a></li>',
        '</ul></div></section>',
        '<section class="sec"><div class="wrap"><div class="tete"><h2>Prochains groupes</h2><a href="/groupes">Tous les groupes</a></div>',
        '<p class="regle">Chaque groupe ouvre ses inscriptions quand le précédent est complet : les groupes restent à taille humaine.</p>';
    blocGroupes(activitesGroupes(), false, 4);
    echo '</div></section>',
        '<figure class="paysage"><img src="/assets/ph/beaujolais.webp" alt="Vignes du Beaujolais émergeant de la brume du matin" loading="lazy"></figure>',
        '<section class="sec"><div class="wrap duo"><div class="galet"><img src="/assets/ph/portrait.webp" alt="Portrait de Sandrine Peyron, souriante, en extérieur" loading="lazy"></div><div>',
        '<h2>Sandrine Peyron</h2><p class="lead">Après 36 ans dans le développement social, auprès des familles, j’ai repris mes études à 45 ans pour accompagner autrement.</p>',
        '<p>Formée à plusieurs approches, je les relie pour proposer un accompagnement qui part de la personne, pas d’une méthode. Je veille à créer un espace sûr, où chacun avance à son rythme.</p><a href="/a-propos">Mon parcours</a>',
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
    entete('Groupes à Belleville-en-Beaujolais, Sandrine Peyron',
        'Groupes en petit effectif : parentalité, aidants familiaux, deuil, santé mentale des adolescents et des adultes. Réservation en ligne.', '/groupes');
    echo '<section class="ptete"><div class="wrap duo" style="grid-template-columns:1.1fr .9fr"><div><h1>Avancer ensemble, en petit groupe</h1>',
        '<p class="lead">Quelques personnes qui vivent la même chose, un cadre bienveillant, une séance par semaine. Places limitées.</p></div>',
        '<div class="galet" style="aspect-ratio:16/11"><img src="/assets/ph/cercle.webp" alt="Des chaises disposées en cercle"></div></div></section>',
        '<section class="sec papier" style="padding-top:64px"><div class="wrap"><h2>Les groupes</h2><p>Un lieu pour déposer ce qui pèse, être écouté, entendre d’autres vécus et repartir avec des outils simples.</p><ul class="liste-act colonnes">';
    foreach (activitesGroupes() as $a) {
        echo '<li><a href="/groupes/', h($a['code']), '"><b>', h($a['nom']), '</b><span>', h($a['resume']), '</span></a></li>';
    }
    echo '</ul></div></section><section class="sec"><div class="wrap"><h2>Dates et inscriptions</h2>';
    blocGroupes(activitesGroupes());
    echo '<p class="regle">Comment ça marche : vous réservez et payez en ligne, votre place est confirmée par e-mail avec les dates à ajouter à votre agenda. Le groupe suivant d’un même thème ouvre quand celui-ci est complet.</p></div></section>';
    pied(true);
}

function pageGroupe(string $code): void
{
    $a = activite($code);
    $c = CONTENUS_GROUPES[$code] ?? null;
    if (!$a || !$c || $a['type'] === 'individuel') { pageErreur(404, 'Page introuvable', 'Cette page n’existe pas ou plus.'); return; }
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
        echo '<div class="agenda"><h3>Prendre rendez-vous</h3><p>La réservation en ligne ouvre bientôt. En attendant, appelez Sandrine au <a href="tel:', h(telLien(param('telephone'))), '">', h(param('telephone')), '</a>.</p></div>';
        return;
    }
    echo '<div class="agenda" data-activite="', h($a['code']), '" data-titre="', h($a['nom']), '" data-prix="', h(euros((int) $a['tarif_centimes'])), '" data-duree="', (int) $a['duree_minutes'], ' min">',
        '<h3>Créneaux disponibles</h3><div class="jours" aria-label="Jours"><p class="muted small">Chargement des créneaux…</p></div><p class="choix muted small" aria-live="polite" style="margin:16px 0 0"></p><div class="creneaux"></div>',
        '<noscript><p>Pour réserver, activez JavaScript ou appelez le ', h(param('telephone')), '.</p></noscript></div>';
}

// Une seule offre individuelle : Sandrine choisit avec la personne les outils adaptés (demande du 04/11/2026)
function pageSeances(): void
{
    entete('Séances individuelles à Belleville-en-Beaujolais, Sandrine Peyron',
        'Un accompagnement individuel global, construit à partir de votre demande, au cabinet ou en visio. Réservation en ligne.', '/seances');
    $s = activite('seance');
    echo '<section class="ptete"><div class="wrap duo" style="grid-template-columns:1.1fr .9fr"><div><h1>Séances individuelles</h1>',
        '<p class="lead">Un temps pour vous, construit à partir de votre demande. Vous n’avez pas à choisir une méthode : on en parle ensemble.</p></div>',
        '<div class="galet" style="aspect-ratio:1"><img src="/assets/ph/the.webp" alt="Deux mains tiennent une tasse de thé chaude"></div></div></section>',
        '<section class="sec papier" style="padding-top:56px"><div class="wrap"><div class="deux"><div class="prose">',
        '<h2 style="margin-top:0">Un accompagnement global</h2>',
        '<p>Chaque personne arrive avec son histoire et sa demande. Sandrine s’appuie sur l’ensemble de ses formations, l’écoute, le travail sur le corps et la respiration, le retraitement des souvenirs difficiles, pour construire avec vous l’accompagnement qui vous convient.</p>',
        '<h2>Pour quoi venir ?</h2><ul>',
        '<li>Stress, anxiété, émotions qui débordent</li><li>Confiance et estime de soi</li><li>Sommeil, douleurs, acouphènes</li>',
        '<li>Événements difficiles, traumatismes, deuils</li><li>Préparer un examen, une prise de parole, une échéance</li><li>Comprendre ce qui se répète dans sa vie</li></ul>',
        '<p class="small muted">Cet accompagnement ne remplace pas un traitement médical. Il peut le compléter.</p></div>',
        '<div><div class="fiche"><dl><dt>Durée</dt><dd>', (int) $s['duree_minutes'], ' minutes environ</dd><dt>Lieu</dt><dd>Selon le créneau : cabinet ou visio</dd><dt>Tarif</dt><dd>', h(euros($s['tarif_centimes'] === null ? null : (int) $s['tarif_centimes'])), '</dd></dl></div>',
        '<div class="prose" style="margin-top:32px"><h2 style="margin-top:0">La première séance</h2><p>Un temps d’échange pour comprendre ce qui vous amène et définir ensemble la façon de travailler.</p></div></div></div>';
    blocAgenda($s);
    echo '</div></section>';
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
