<?php
// Textes des pages de groupes. Proposition rédigée par Le Génie Numérique : à relire et valider avec Sandrine.
// Les textes vivent dans app/contenus/groupes.json (données seulement : lisibles et modifiables par l'éditeur IA, jamais exécutées).
// Texte brut uniquement : il est échappé à l'affichage (aucun HTML interprété).
declare(strict_types=1);

define('CONTENUS_GROUPES', json_decode((string) file_get_contents(RACINE . '/app/contenus/groupes.json'), true, 16, JSON_THROW_ON_ERROR));
