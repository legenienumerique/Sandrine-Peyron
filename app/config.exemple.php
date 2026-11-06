<?php
// Copier en config.php (jamais publié dans le dépôt). En ligne, config.php est écrit à partir des secrets.
// GitHub ne rend jamais un secret : garder une copie de récupération chiffrée (php outils/reconstruire-config.php sauver …).
// Essai (essai.<domaine>) : config.php DISTINCT — base fictive, clé Mollie test_, e-mails en journal, aucun secret de production.
return [
    'dev' => false,                                   // true seulement sur l'ordinateur
    'url' => 'https://sandrinepeyron.com',            // adresse publique, sans / final
    'url_gestion' => 'https://gestion.sandrinepeyron.com', // espace de gestion (vide sur l'ordinateur : /gestion sur le site)
    'secret' => '',                                   // 64 caractères aléatoires : php -r "echo bin2hex(random_bytes(32));"
    'donnees' => '/home/COMPTE/donnees-sandrine',     // dossier hors du site public : base, sessions, journaux, sauvegardes
    'mollie_cle' => '',                               // test_… puis live_… (compte Mollie de Sandrine)
    'smtp_hote' => '', 'smtp_port' => 587, 'smtp_utilisateur' => '', 'smtp_mot_de_passe' => '',
    'expediteur' => '', 'expediteur_nom' => 'Sandrine Peyron',
    'essai' => false,                                 // true sur le site d'essai : e-mails écrits en journal, pages non indexées
    // 'version' : écrite par la publication dans app/version.txt (commit), affichée par le contrôle de santé

    // --- Kit LGN (back-office admin.legenienumerique.fr) ---
    'alerte_lgn' => '',                               // e-mail LGN pour les alertes techniques (rétrofacturation…), sans donnée personnelle
    'lgn_demandes' => [],                             // ['url' => 'https://admin.legenienumerique.fr/api/site.php', 'cle' => 'demandes-…', 'secret' => 64 hex] : page Assistance
    'lgn_cles_sante' => [],                           // [identifiant => secret hexadécimal 64] : clé « sante » du back-office (2 pendant une rotation)
    // 'coupure_fichier' => '',                       // par défaut /home/legenienumerique/coupure/<domaine sans www>
    'sauvegarde' => [
        'cle_publique' => '',                         // clé publique de chiffrement LGN (base64) : php outils/cles.php chiffrement <fichier> sur le PC
        'cles_signature_publiques' => [],             // clés publiques de signature acceptées (base64), anciennes comprises
        'retention_jours' => 30,
        // 'cle_signature' => '',                     // par défaut <donnees>/cles/signature.key (php outils/cles.php signature)
        // Copie externe Scaleway (écriture seule, bucket à rétention) ; absente = copie locale seulement
        // 'externe' => ['endpoint' => 'https://s3.fr-par.scw.cloud', 'region' => 'fr-par', 'bucket' => '', 'prefixe' => 'sandrine', 'cle' => '', 'secret' => ''],
    ],
];
