<?php
// Textes des pages de groupes. Proposition rédigée par Le Génie Numérique : à relire et valider avec Sandrine.
declare(strict_types=1);

const CONTENUS_GROUPES = [
    'parentalite' => [
        'titre' => 'Être parent, et pouvoir en parler',
        'accroche' => 'Fatigue, doutes, conflits, joies aussi. Un groupe pour déposer ce qui se vit à la maison, sans jugement.',
        'image' => 'parentalite', 'alt' => 'Un adulte tient la main d’un enfant',
        'pour_qui' => ['Parents, beaux-parents, grands-parents qui élèvent un enfant', 'Enfants de tous âges, du tout-petit à l’adolescent', 'Aucune difficulté « suffisante » n’est demandée : venir pour en parler suffit'],
        'deroule' => ['Un temps d’accueil, chacun dit comment il arrive', 'Un thème proposé ou la parole libre : sommeil, colères, écrans, séparation, place de chacun…', 'Un court exercice de respiration pour repartir plus léger'],
    ],
    'aidants' => [
        'titre' => 'Prendre soin de celui ou celle qui prend soin',
        'accroche' => 'Accompagner un proche malade, âgé ou en situation de handicap use en silence. Ici, c’est vous qu’on écoute.',
        'image' => 'aidants', 'alt' => 'Plusieurs mains se tiennent dans un geste de soutien',
        'pour_qui' => ['Conjoints, enfants, parents, amis qui accompagnent un proche au quotidien', 'Que l’aide soit récente ou dure depuis des années', 'Que le proche vive à domicile ou en établissement'],
        'deroule' => ['Un temps pour dire où l’on en est', 'Des échanges entre aidants : ce qui épuise, ce qui aide, ce qu’on n’ose pas dire ailleurs', 'Des outils simples de sophrologie pour récupérer et poser ses limites'],
    ],
    'deuil' => [
        'titre' => 'Mieux traverser son deuil',
        'accroche' => 'La perte d’un proche bouleverse tout. Le groupe offre un lieu pour en parler avec d’autres qui comprennent.',
        'image' => 'deuil', 'alt' => 'Un chemin de forêt à l’automne, éclairé par le soleil bas',
        'pour_qui' => ['Toute personne ayant perdu un proche, que la perte soit récente ou ancienne', 'Chacun parle quand il le souhaite, ou écoute seulement', 'Le groupe ne remplace pas un suivi individuel si celui-ci est nécessaire'],
        'deroule' => ['Un accueil en douceur, à son rythme', 'La parole autour de la personne disparue, des souvenirs, de ce qui change', 'Un moment de relaxation guidée pour clore la séance'],
    ],
    'sophro-ados' => [
        'titre' => 'Sophrologie pour les adolescents',
        'accroche' => 'Respirer, se poser, retrouver un appui quand l’anxiété, le stress ou le mal-être prennent de la place.',
        'image' => 'ados', 'alt' => 'Un jeune assis seul au bout d’un ponton, face à l’eau',
        'pour_qui' => ['Adolescents vivant anxiété, stress scolaire, troubles du sommeil ou passage difficile', 'Adolescents suivis pour un trouble de santé mentale, en complément de leur suivi', 'L’inscription est faite par un parent ou responsable légal'],
        'deroule' => ['Des exercices de respiration et de relaxation dynamique, doux et accessibles', 'Des visualisations pour retrouver confiance et calme', 'Un court temps d’échange, sans obligation de parler'],
        'avertissement' => 'La sophrologie n’est pas un soin médical. Ces groupes complètent un suivi médical ou psychologique, ils ne le remplacent pas.',
    ],
    'sophro-adultes' => [
        'titre' => 'Sophrologie et santé mentale, adultes',
        'accroche' => 'Des outils concrets pour traverser stress, anxiété, épuisement ou une période de fragilité, en petit groupe.',
        'image' => 'adultes', 'alt' => 'Une personne de dos face à un lac calme, à l’aube',
        'pour_qui' => ['Adultes vivant stress, anxiété, troubles du sommeil ou épuisement', 'Personnes suivies pour un trouble de santé mentale, en complément de leur suivi', 'Aucune expérience de la sophrologie n’est nécessaire'],
        'deroule' => ['Respiration et relaxation dynamique, assis ou debout', 'Visualisations pour ancrer le calme et les ressources', 'Partage libre des ressentis, puis un exercice à refaire chez soi'],
        'avertissement' => 'La sophrologie n’est pas un soin médical. Ces groupes complètent un suivi médical ou psychologique, ils ne le remplacent pas.',
    ],
];
