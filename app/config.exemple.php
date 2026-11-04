<?php
// Copier en config.php (jamais publié dans le dépôt). En ligne, config.php est écrit par la publication à partir des secrets GitHub.
return [
    'dev' => false,                                   // true seulement sur l'ordinateur
    'url' => 'https://www.exemple.fr',                // adresse publique, sans / final
    'secret' => '',                                   // 64 caractères aléatoires : php -r "echo bin2hex(random_bytes(32));"
    'donnees' => '/home/COMPTE/donnees-sandrine',     // dossier hors du site public : base, sessions, journaux
    'mollie_cle' => '',                               // test_… puis live_… (compte Mollie de Sandrine)
    'smtp_hote' => '', 'smtp_port' => 587, 'smtp_utilisateur' => '', 'smtp_mot_de_passe' => '',
    'expediteur' => '', 'expediteur_nom' => 'Sandrine Peyron',
];
