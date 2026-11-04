# Maquette Sandrine Peyron : écrit toutes les pages. Usage : python build.py [dossier]
import json, os, sys, html

OUT = sys.argv[1] if len(sys.argv) > 1 else os.path.dirname(os.path.abspath(__file__))
V = 5
TEL = "06 99 23 52 17"
MAIL = "sophro.zen.relax@gmail.com"
ADRESSE = "Cabinet du Petit Prince<br>50 rue Victor Hugo, allée du Petit Prince<br>69220 Belleville-en-Beaujolais"

NAV = [("groupes.html", "Groupes"), ("seances.html", "Séances individuelles"), ("activites.html", "Activités"), ("a-propos.html", "À propos"), ("contact.html", "Contact")]


def head(titre, desc, page):
    nav = "".join(f'<a href="{h}"{" aria-current=page" if h == page else ""}>{t}</a>' for h, t in NAV)
    mnav = "".join(f'<a href="{h}">{t}</a>' for h, t in NAV)
    return f"""<!doctype html>
<html lang="fr"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>{titre}</title><meta name="description" content="{desc}">
<link rel="icon" href="assets/ph/logo.webp">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible+Next:wght@400;600&family=Newsreader:ital,opsz,wght@0,6..72,300..500;1,6..72,300..400&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/style.css?v={V}">
</head><body>
<a class="saut" href="#contenu">Aller au contenu</a>
<header class="top"><div class="wrap">
<a class="marque" href="index.html"><img src="assets/ph/logo.webp" alt="" width="44" height="44"><span><b>Sandrine Peyron</b><small>Accompagnement individuel et en groupe</small></span></a>
<nav class="nav" aria-label="Menu principal">{nav}</nav>
<a class="btn plein" href="seances.html">Réserver</a>
<button class="burger" aria-expanded="false" aria-controls="mnav">Menu</button>
</div></header>
<nav class="mnav" id="mnav" aria-label="Menu mobile">{mnav}<a class="btn plein" href="seances.html">Réserver une séance</a></nav>
<main id="contenu">"""


def foot():
    liens = "".join(f'<li><a href="{h}">{t}</a></li>' for h, t in NAV)
    return f"""</main>
<footer class="pied"><div class="wrap">
<div class="cols">
<div><h3>Sandrine Peyron</h3><p>Accompagnement individuel et en groupe, au cabinet de Belleville-en-Beaujolais ou en visio.</p></div>
<div><h3>Cabinet</h3><p>{ADRESSE}</p></div>
<div><h3>Contact</h3><ul><li><a href="tel:+33699235217">{TEL}</a></li><li><a href="mailto:{MAIL}">{MAIL}</a></li></ul></div>
<div><h3>Pages</h3><ul>{liens}<li><a href="gestion.html">Espace de gestion</a></li></ul></div>
</div>
<div class="bas"><span>© 2026 Sandrine Peyron. Mentions légales, confidentialité et conditions de vente : à rédiger.</span><span>Maquette non référencée, réalisée par <a href="https://legenienumerique.fr">Le Génie Numérique</a></span></div>
</div></footer>
{DIALOGUE}
<script src="assets/main.js?v={V}"></script>
</body></html>"""


DIALOGUE = """<dialog class="resa" aria-labelledby="resa-t1">
<button class="x" type="button" aria-label="Fermer">×</button>
<form class="in" onsubmit="return false">
<ol class="etapes"><li>Récapitulatif</li><li>Coordonnées</li><li>Paiement</li></ol>
<div data-etape="1"><h2 id="resa-t1" tabindex="-1">Votre réservation</h2><div class="recap"></div>
<p class="small muted">Vous pourrez annuler selon les conditions de vente (règle à fixer avec Sandrine).</p>
<div class="btns"><span></span><button class="btn plein" type="button" data-suite>Continuer</button></div></div>
<div data-etape="2" hidden><h2 tabindex="-1">Vos coordonnées</h2>
<label class="champ"><span>Prénom</span><input name="prenom" autocomplete="given-name" required></label>
<label class="champ"><span>Nom</span><input name="nom" autocomplete="family-name" required></label>
<label class="champ"><span>E-mail</span><input name="email" type="email" autocomplete="email" required></label>
<label class="champ"><span>Téléphone</span><input name="tel" type="tel" autocomplete="tel" required></label>
<label class="coche"><input type="checkbox" required><span>J’accepte que ces informations servent uniquement à gérer ma réservation.</span></label>
<p class="small muted">Aucune information sur votre santé ne vous est demandée.</p>
<div class="btns"><button class="btn" type="button" data-retour>Retour</button><button class="btn plein" type="button" data-suite>Continuer vers le paiement</button></div></div>
<div data-etape="3" hidden><h2 tabindex="-1">Paiement</h2><div class="recap"></div>
<p class="avis"><b>Maquette.</b> Le paiement en ligne sécurisé (Mollie) sera branché lors du développement. Rien n’est enregistré ni envoyé ici.</p>
<div class="btns"><button class="btn" type="button" data-retour>Retour</button><button class="btn plein" type="button" disabled>Payer par carte</button></div></div>
</form></dialog>"""

LIEU = "Cabinet, Belleville-en-Beaujolais"

# Groupes d'exemple : dates et effectifs fictifs, pour montrer l'ouverture séquentielle
GROUPES = [
    ("parole", "Soutien à la parentalité", "Groupe 1", "6 jeudis, 18 h 30 à 20 h", "du 19 novembre au 7 janvier", 8, 5, "ouvert"),
    ("parole", "Soutien à la parentalité", "Groupe 2", "6 jeudis, 18 h 30 à 20 h", "à partir de janvier", 8, 0, "ferme"),
    ("parole", "Soutien aux aidants familiaux", "Groupe 1", "5 mardis, 14 h à 15 h 30", "du 10 novembre au 8 décembre", 8, 8, "complet"),
    ("parole", "Soutien aux aidants familiaux", "Groupe 2", "5 mardis, 14 h à 15 h 30", "du 12 janvier au 9 février", 8, 2, "ouvert"),
    ("parole", "Mieux traverser son deuil", "Groupe 1", "6 lundis, 18 h à 19 h 30", "du 23 novembre au 4 janvier", 6, 3, "ouvert"),
    ("sophro", "Santé mentale, adolescents", "Groupe 1", "8 mercredis, 14 h à 15 h", "du 18 novembre au 20 janvier", 8, 4, "ouvert"),
    ("sophro", "Santé mentale, adultes", "Groupe 1", "8 vendredis, 18 h à 19 h", "du 20 novembre au 22 janvier", 10, 7, "ouvert"),
    ("sophro", "Santé mentale, adultes", "Groupe 2", "8 vendredis, 18 h à 19 h", "à partir de février", 10, 0, "ferme"),
]


NOM2CODE = {"Soutien à la parentalité": "parentalite", "Soutien aux aidants familiaux": "aidants", "Mieux traverser son deuil": "deuil",
            "Santé mentale, adolescents": "sophro-ados", "Santé mentale, adultes": "sophro-adultes"}


def groupe(g):
    cat, nom, num, rythme, dates, cap, pris, etat = g
    points = "".join('<i class="pris"></i>' if i < pris else "<i></i>" for i in range(cap))
    reste = cap - pris
    if etat == "ouvert":
        info = f"{reste} place{'s' if reste > 1 else ''} sur {cap}"
        resa = json.dumps({"titre": f"{nom}, {num.lower()}", "quand": f"{rythme}, {dates}", "lieu": LIEU, "prix": "à fixer avec Sandrine"}, ensure_ascii=False)
        action = f'<button class="btn plein" type="button" data-resa="{html.escape(resa)}">Réserver</button>'
    elif etat == "complet":
        info, action = "Complet", '<span class="verrou">Groupe complet. Le suivant est ouvert.</span>'
    else:
        info, action = "Pas encore ouvert", f'<span class="verrou">Ouvre quand le groupe précédent est complet.</span>'
    return f"""<article class="gr {etat if etat != 'ouvert' else ''}" data-cat="{cat}">
<div><h3><a href="groupe-{NOM2CODE[nom]}.html">{nom}</a></h3><span class="muted small">{num}, tarif à fixer</span></div>
<div class="quand"><b>{rythme}</b>{dates}</div>
<div><div class="places" aria-hidden="true">{points}</div><span class="etat">{info}</span></div>
{action}</article>"""


def liste_groupes(filtre=None, nom=None):
    gs = [g for g in GROUPES if (not filtre or g[0] == filtre) and (not nom or g[1] == nom)]
    return f'<div class="groupes" id="liste-groupes">{"".join(groupe(g) for g in gs)}</div>'


VAGUES = """<svg viewBox="0 0 900 1000" preserveAspectRatio="xMidYMax slice" aria-hidden="true">
<g class="vagues v1"><path fill="#DAD8F0" d="M0 470 C150 420 300 520 450 470 S750 420 900 470 V1000 H0Z"/></g>
<g class="vagues v2"><path fill="#BEBBE2" d="M0 580 C160 540 290 630 450 585 S760 530 900 590 V1000 H0Z"/></g>
<g class="vagues v3"><path fill="#9B97CC" d="M0 690 C140 650 310 740 470 695 S770 650 900 700 V1000 H0Z"/></g>
<g class="vagues v4"><path fill="#6F6BAE" d="M0 800 C170 760 300 850 460 805 S760 760 900 810 V1000 H0Z"/></g>
</svg>"""

ARTICLES = [
    ("article", "Exemple d’article", "Comment se déroule une première séance ?", "Un temps d’échange pour comprendre ce qui vous amène et choisir ensemble la façon de travailler.", "assets/ph/regard.webp"),
    ("compte_rendu", "Exemple de compte rendu", "Cycle « Soutien aux aidants familiaux » : retour sur cinq rencontres", "Ce que les participants ont partagé, les outils transmis et la suite prévue.", "assets/ph/foret.webp"),
    ("article", "Exemple d’article", "La relaxation dynamique, pour qui et pourquoi ?", "Des exercices doux associés à la respiration, pour détendre le corps tout en éveillant la conscience.", "assets/ph/portrait.webp"),
]


def article(a):
    cat, ex, titre, resume, img = a
    lib = "Article" if cat == "article" else "Compte rendu"
    return f"""<a class="art" href="#" data-cat="{cat}"><div class="img"><img src="{img}" alt="" loading="lazy"></div>
<div class="meta"><span class="tag ex">{ex}</span><span>{lib}</span></div><h3>{titre}</h3><p class="muted">{resume}</p></a>"""


# Textes des groupes : proposition LGN, à valider avec Sandrine
ACTS = {
    "parentalite": ("parole", "Soutien à la parentalité", 90, "Les questions et les fatigues de parent, partagées en petit groupe.", "Être parent, et pouvoir en parler",
        "Fatigue, doutes, conflits, joies aussi. Un groupe pour déposer ce qui se vit à la maison, sans jugement.", "parentalite", "Un adulte tient la main d’un enfant",
        ["Parents, beaux-parents, grands-parents qui élèvent un enfant", "Enfants de tous âges, du tout-petit à l’adolescent", "Aucune difficulté « suffisante » n’est demandée : venir pour en parler suffit"],
        ["Un temps d’accueil, chacun dit comment il arrive", "Un thème proposé ou la parole libre : sommeil, colères, écrans, séparation, place de chacun…", "Un court exercice de respiration pour repartir plus léger"], None),
    "aidants": ("parole", "Soutien aux aidants familiaux", 90, "Pour celles et ceux qui accompagnent un proche malade, âgé ou en situation de handicap.", "Prendre soin de celui ou celle qui prend soin",
        "Accompagner un proche malade, âgé ou en situation de handicap use en silence. Ici, c’est vous qu’on écoute.", "aidants", "Plusieurs mains se tiennent dans un geste de soutien",
        ["Conjoints, enfants, parents, amis qui accompagnent un proche au quotidien", "Que l’aide soit récente ou dure depuis des années", "Que le proche vive à domicile ou en établissement"],
        ["Un temps pour dire où l’on en est", "Des échanges entre aidants : ce qui épuise, ce qui aide, ce qu’on n’ose pas dire ailleurs", "Des outils simples pour récupérer et poser ses limites"], None),
    "deuil": ("parole", "Mieux traverser son deuil", 90, "Un espace pour la perte et ce qui suit, à son rythme.", "Mieux traverser son deuil",
        "La perte d’un proche bouleverse tout. Le groupe offre un lieu pour en parler avec d’autres qui comprennent.", "deuil", "Un chemin de forêt à l’automne, éclairé par le soleil bas",
        ["Toute personne ayant perdu un proche, que la perte soit récente ou ancienne", "Chacun parle quand il le souhaite, ou écoute seulement", "Le groupe ne remplace pas un suivi individuel si celui-ci est nécessaire"],
        ["Un accueil en douceur, à son rythme", "La parole autour de la personne disparue, des souvenirs, de ce qui change", "Un moment de relaxation guidée pour clore la séance"], None),
    "sophro-ados": ("sophro", "Santé mentale, adolescents", 60, "Respirer, se poser, retrouver un appui quand l’anxiété prend de la place.", "Retrouver un appui, pour les adolescents",
        "Respirer, se poser, retrouver un appui quand l’anxiété, le stress ou le mal-être prennent de la place.", "ados", "Un jeune assis seul au bout d’un ponton, face à l’eau",
        ["Adolescents vivant anxiété, stress scolaire, troubles du sommeil ou passage difficile", "Adolescents suivis pour un trouble de santé mentale, en complément de leur suivi", "L’inscription est faite par un parent ou responsable légal"],
        ["Des exercices de respiration et de relaxation dynamique, doux et accessibles", "Des visualisations pour retrouver confiance et calme", "Un court temps d’échange, sans obligation de parler"],
        "Ces groupes ne sont pas un soin médical : ils complètent un suivi médical ou psychologique, ils ne le remplacent pas."),
    "sophro-adultes": ("sophro", "Santé mentale, adultes", 60, "Des outils simples pour traverser stress, anxiété ou passage difficile.", "Retrouver un appui, pour les adultes",
        "Des outils concrets pour traverser stress, anxiété, épuisement ou une période de fragilité, en petit groupe.", "adultes", "Une personne de dos face à un lac calme, à l’aube",
        ["Adultes vivant stress, anxiété, troubles du sommeil ou épuisement", "Personnes suivies pour un trouble de santé mentale, en complément de leur suivi", "Aucune expérience n’est nécessaire"],
        ["Respiration et relaxation dynamique, assis ou debout", "Visualisations pour ancrer le calme et les ressources", "Partage libre des ressentis, puis un exercice à refaire chez soi"],
        "Ces groupes ne sont pas un soin médical : ils complètent un suivi médical ou psychologique, ils ne le remplacent pas."),
}


def liste_act(cat):
    return '<ul class="liste-act">' + "".join(f'<li><a href="groupe-{c}.html"><b>{v[1]}</b><span>{v[3]}</span></a></li>' for c, v in ACTS.items() if v[0] == cat) + "</ul>"


def page(nom, titre, desc, corps):
    with open(os.path.join(OUT, nom), "w", encoding="utf-8") as f:
        f.write(head(titre, desc, nom) + corps + foot())


# ---------- Accueil ----------
page("index.html", "Sandrine Peyron, accompagnement individuel et en groupe à Belleville-en-Beaujolais",
     "Séances individuelles et groupes à Belleville-en-Beaujolais, au cabinet ou en visio. Réservation et paiement en ligne.",
     f"""
<section class="hero"><div class="wrap">
<div>
<h1 class="entre">Prendre soin de soi, seul ou à plusieurs</h1>
<p class="lead entre d1">Un accompagnement global, en séance individuelle ou en groupe, au cabinet de Belleville-en-Beaujolais ou en visio.</p>
<div class="btns entre d2"><a class="btn plein" href="groupes.html">Voir les groupes</a><a class="btn" href="seances.html">Réserver une séance</a></div>
<p class="lieu entre d3">15 minutes de Villefranche-sur-Saône, 20 minutes de Mâcon, 45 minutes de Lyon.</p>
</div>
<div class="souffle"><div class="forme">{VAGUES}<p class="mot" aria-hidden="true">Inspirez…</p></div>
<button class="pause" type="button" aria-pressed="false">Pause</button></div>
</div></section>

<section class="sec papier"><div class="wrap">
<h2>Deux façons d’être accompagné</h2>
<ul class="offres">
<li><a href="seances.html"><h3>Séances individuelles</h3><p>Un temps pour vous, construit à partir de votre demande. Au cabinet ou en visio.</p><span class="fl" aria-hidden="true">›</span></a></li>
<li><a href="groupes.html"><h3>Groupes</h3><p>Parentalité, aidants familiaux, deuil, santé mentale des adolescents et des adultes. En petit effectif.</p><span class="fl" aria-hidden="true">›</span></a></li>
</ul>
</div></section>

<section class="sec"><div class="wrap">
<div class="tete"><h2>Prochains groupes</h2><a href="groupes.html">Tous les groupes</a></div>
<p class="regle">Dates et places présentées à titre d’exemple. Chaque groupe ouvre ses inscriptions quand le précédent est complet : les groupes restent vivants et à taille humaine.</p>
{liste_groupes()}
</div></section>

<figure class="paysage" style="margin-bottom:0"><img src="assets/ph/beaujolais.webp" alt="Vignes du Beaujolais émergeant de la brume du matin" loading="lazy"></figure>

<section class="sec"><div class="wrap duo">
<div class="galet"><img src="assets/ph/portrait.webp" alt="Portrait de Sandrine Peyron, souriante, en extérieur" loading="lazy"></div>
<div>
<h2>Sandrine Peyron</h2>
<p class="lead">Après 36 ans dans le développement social, auprès des familles, j’ai repris mes études à 45 ans pour accompagner autrement.</p>
<p>Formée à plusieurs approches, je les relie pour proposer un accompagnement qui part de la personne, pas d’une méthode. Je veille à créer un espace sûr, où chacun avance à son rythme.</p>
<a href="a-propos.html">Mon parcours</a>
<div class="labels"><img src="assets/ph/ecoles-sophro.webp" alt="Écoles de Sophrologie Caycédienne" loading="lazy"><img src="assets/ph/chambre-syndicale.webp" alt="Chambre syndicale de la sophrologie" loading="lazy"></div>
</div></div></section>

<section class="sec papier"><div class="wrap">
<div class="tete"><h2>Activités</h2><a href="activites.html">Tous les articles</a></div>
<div class="articles">{"".join(article(a) for a in ARTICLES)}</div>
</div></section>

<section class="sec"><div class="wrap">
<h2>Le cabinet</h2>
<div class="cabinet">
<div><h3>Adresse</h3><p>{ADRESSE}</p></div>
<div><h3>Accès</h3><p>15 min de Villefranche-sur-Saône<br>20 min de Mâcon<br>45 min de Lyon et de Bourg-en-Bresse</p></div>
<div><h3>Contact</h3><p><a href="tel:+33699235217">{TEL}</a><br><a href="mailto:{MAIL}">{MAIL}</a></p></div>
</div></div></section>
""")

# ---------- Groupes ----------
page("groupes.html", "Groupes, Sandrine Peyron",
     "Groupes en petit effectif à Belleville-en-Beaujolais : parentalité, aidants familiaux, deuil, santé mentale des adolescents et des adultes.",
     f"""
<section class="ptete"><div class="wrap duo" style="grid-template-columns:1.1fr .9fr"><div>
<h1>Avancer ensemble, en petit groupe</h1>
<p class="lead">Quelques personnes qui vivent la même chose, un cadre bienveillant, une séance par semaine. Places limitées.</p></div>
<div class="galet" style="aspect-ratio:16/11"><img src="assets/ph/cercle.webp" alt="Des chaises disposées en cercle"></div></div></section>
<section class="sec papier" style="padding-top:64px"><div class="wrap"><h2>Les groupes</h2>
<p>Un lieu pour déposer ce qui pèse, être écouté, entendre d’autres vécus et repartir avec des outils simples.</p>
<ul class="liste-act colonnes">{"".join(f'<li><a href="groupe-{c}.html"><b>{v[1]}</b><span>{v[3]}</span></a></li>' for c, v in ACTS.items())}</ul>
</div></section>
<section class="sec"><div class="wrap">
<div class="tete"><h2>Dates et inscriptions</h2></div>
{liste_groupes()}
<p class="regle">Comment ça marche : vous réservez et payez en ligne, votre place est confirmée par e-mail. Le groupe suivant d’un même thème ouvre quand celui-ci est complet. Dates, effectifs et tarifs affichés : exemples à remplacer.</p>
</div></section>
""")

# ---------- Séances individuelles (une seule offre, présentation globale) ----------
page("seances.html", "Séances individuelles, Sandrine Peyron",
     "Un accompagnement individuel global, construit à partir de votre demande, au cabinet ou en visio. Réservation en ligne.",
     f"""
<section class="ptete"><div class="wrap duo" style="grid-template-columns:1.1fr .9fr"><div>
<h1>Séances individuelles</h1>
<p class="lead">Un temps pour vous, construit à partir de votre demande. Vous n’avez pas à choisir une méthode : on en parle ensemble.</p></div>
<div class="galet" style="aspect-ratio:1"><img src="assets/ph/the.webp" alt="Deux mains tiennent une tasse de thé chaude"></div></div></section>
<section class="sec papier" style="padding-top:56px"><div class="wrap">
<div class="deux"><div class="prose">
<h2 style="margin-top:0">Un accompagnement global</h2>
<p>Chaque personne arrive avec son histoire et sa demande. Sandrine s’appuie sur l’ensemble de ses formations, l’écoute, le travail sur le corps et la respiration, le retraitement des souvenirs difficiles, pour construire avec vous l’accompagnement qui vous convient.</p>
<h2>Pour quoi venir ?</h2>
<ul><li>Stress, anxiété, émotions qui débordent</li><li>Confiance et estime de soi</li><li>Sommeil, douleurs, acouphènes</li><li>Événements difficiles, traumatismes, deuils</li><li>Préparer un examen, une prise de parole, une échéance</li><li>Comprendre ce qui se répète dans sa vie</li></ul>
<p class="small muted">Cet accompagnement ne remplace pas un traitement médical. Il peut le compléter.</p></div>
<div><div class="fiche"><dl><dt>Durée</dt><dd>1 h environ</dd><dt>Lieu</dt><dd>Cabinet ou visio</dd><dt>Tarif</dt><dd>À fixer avec Sandrine</dd></dl></div>
<div class="prose" style="margin-top:32px"><h2 style="margin-top:0">La première séance</h2><p>Un temps d’échange pour comprendre ce qui vous amène et définir ensemble la façon de travailler.</p></div></div></div>
<div class="agenda" data-type="seance" data-lieu="Cabinet, Belleville-en-Beaujolais"><h3>Créneaux disponibles <span class="muted small">(exemples)</span></h3><div class="jours" aria-label="Jours"></div><p class="choix muted small" aria-live="polite" style="margin:16px 0 0"></p><div class="creneaux"></div></div>
</div></section>
""")

# ---------- Activités ----------
page("activites.html", "Activités, articles et comptes rendus, Sandrine Peyron",
     "Articles et comptes rendus des groupes et ateliers de Sandrine Peyron.",
     f"""
<section class="ptete"><div class="wrap">
<h1>Activités</h1>
<p class="lead">Articles, comptes rendus de groupes et d’ateliers. Sandrine les publie elle-même depuis son espace de gestion.</p>
</div></section>
<section class="sec papier" style="padding-top:56px"><div class="wrap">
<div class="filtres" data-filtres="#liste-art" role="group" aria-label="Filtrer les publications">
<button type="button" data-f="tous" aria-pressed="true">Tout</button><button type="button" data-f="article" aria-pressed="false">Articles</button><button type="button" data-f="compte_rendu" aria-pressed="false">Comptes rendus</button></div>
<div class="articles" id="liste-art">{"".join(article(a) for a in ARTICLES)}</div>
</div></section>
""")

# ---------- À propos ----------
page("a-propos.html", "À propos de Sandrine Peyron, sophrologue et praticienne EMDR-DSA",
     "Parcours, formations et approche de Sandrine Peyron, sophrologue caycédienne, praticienne EMDR-DSA et psychanalyste clinicienne.",
     f"""
<section class="ptete"><div class="wrap duo">
<div><h1>Un chemin vers l’autre</h1>
<p class="lead">Sophrologue de courant caycédien, praticienne en EMDR-DSA et psychanalyste clinicienne, au cabinet de Belleville-en-Beaujolais.</p></div>
<div class="galet"><img src="assets/ph/portrait.webp" alt="Portrait de Sandrine Peyron"></div>
</div></section>
<section class="sec papier"><div class="wrap deux">
<div class="prose">
<h2 style="margin-top:0">Mon parcours</h2>
<p>J’ai consacré 36 années au développement social des territoires, pour accompagner la famille sous toutes ses formes.</p>
<p>À 45 ans, j’ai repris mes études : certification EMDR niveaux I et II, master de sophrologie et validation des quatre degrés de la méthode caycédienne.</p>
<p>En parallèle, 19 ans de réflexion en psychanalyse lacanienne, dont quatre en cartel de lecture au sein de la Collégiale Psychanalytique de l’Ain, où je suis reconnue psychanalyste clinicienne.</p>
<h2>Ma façon d’accompagner</h2>
<p>Créer un espace sûr, écouter, transmettre des outils simples que chacun peut garder. Je reçois en individuel et en groupe, au cabinet, à domicile ou en visio, des particuliers comme des professionnels.</p>
<h2>Et sinon…</h2>
<p>J’aime le jazz, la musique classique, la nature, les animaux, le thé bergamote, le petit café chaud et le chocolat.</p>
</div>
<div>
<h2 style="margin-top:0">Formations</h2>
<ul class="prose"><li>CLK Formation, Sophrologie Caycédienne, Lyon</li><li>EMDR-DSA Alpes Savoie</li><li>AHTMA Formation, Académie d’Hypnose Thérapeutique, Nantes</li><li>Membre de la Chambre syndicale de la sophrologie</li></ul>
<img src="assets/ph/certif-sophro.webp" alt="Certificat de sophrologue de Sandrine Peyron" loading="lazy" style="border-radius:16px;margin-top:28px">
<img src="assets/ph/certif-emdr.webp" alt="Certification de praticienne en EMDR-DSA, niveau 1" loading="lazy" style="border-radius:16px;margin-top:16px">
</div>
</div></section>
""")

# ---------- Contact ----------
page("contact.html", "Contact et accès au cabinet, Sandrine Peyron",
     "Cabinet du Petit Prince, 50 rue Victor Hugo, 69220 Belleville-en-Beaujolais. Téléphone et e-mail.",
     f"""
<section class="ptete"><div class="wrap">
<h1>Me contacter</h1>
<p class="lead">Une question avant de réserver ? Appelez-moi ou écrivez-moi.</p>
<div class="btns"><a class="btn plein" href="tel:+33699235217">Appeler le {TEL}</a><a class="btn" href="mailto:{MAIL}">Écrire un e-mail</a></div>
</div></section>
<section class="sec papier"><div class="wrap cabinet" style="margin-top:0">
<div><h3>Adresse</h3><p>{ADRESSE}</p><p style="margin-top:12px"><a href="https://www.openstreetmap.org/search?query=50%20rue%20Victor%20Hugo%2069220%20Belleville-en-Beaujolais">Voir sur la carte</a></p></div>
<div><h3>Accès</h3><p>15 min de Villefranche-sur-Saône<br>20 min de Mâcon<br>45 min de Lyon et de Bourg-en-Bresse</p></div>
<div><h3>Où se voir</h3><p>Au cabinet, à domicile ou en visio, selon vos besoins.</p></div>
</div></section>
""")


# ---------- Pages de chaque groupe ----------
for code, (cat, nom, duree, resume, titre, accroche, img, alt, pour_qui, deroule, avert) in ACTS.items():
    lis = "".join(f"<li>{x}</li>" for x in pour_qui)
    ols = "".join(f"<li>{x}</li>" for x in deroule)
    av = f'<p class="small muted">{avert}</p>' if avert else ""
    page(f"groupe-{code}.html", f"{nom}, Sandrine Peyron", accroche, f"""
<section class="ptete"><div class="wrap duo" style="grid-template-columns:1.1fr .9fr"><div>
<p class="fil"><a href="groupes.html">Groupes</a></p><h1>{titre}</h1><p class="lead">{accroche}</p>
<div class="btns"><a class="btn plein" href="#dates">Voir les dates</a></div></div>
<div class="galet"><img src="assets/ph/{img}.webp" alt="{alt}"></div></div></section>
<section class="sec papier"><div class="wrap deux"><div class="prose"><h2 style="margin-top:0">Pour qui ?</h2><ul>{lis}</ul>
<h2>Comment se passe une séance</h2><ol class="etapes-seance">{ols}</ol></div>
<div><div class="fiche"><dl><dt>Durée</dt><dd>{duree} minutes par séance</dd><dt>Groupe</dt><dd>Petit effectif, fixé pour chaque groupe</dd><dt>Lieu</dt><dd>Cabinet du Petit Prince, Belleville-en-Beaujolais</dd><dt>Tarif</dt><dd>À fixer avec Sandrine</dd></dl></div>
<div class="prose" style="margin-top:32px"><h2 style="margin-top:0">Le cadre</h2><p>Ce qui se dit dans le groupe reste dans le groupe. Personne n’est obligé de parler. Sandrine anime chaque séance et veille à ce que chacun trouve sa place.</p>{av}</div></div></div></section>
<section class="sec" id="dates"><div class="wrap"><h2>Dates et inscriptions</h2>
<p class="regle">Dates et places présentées à titre d’exemple.</p>
{liste_groupes(nom=nom)}</div></section>
""")

with open(os.path.join(OUT, "robots.txt"), "w", encoding="utf-8") as f:
    f.write("User-agent: *\nDisallow: /\n")
print("Pages écrites dans", OUT)
