<?php
// Crée le compte de Sandrine : php outils/compte.php email@exemple.fr
// Le mot de passe est demandé sans s'afficher ; le code de sécurité est configuré à la première connexion.
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
if (PHP_SAPI !== 'cli') exit;
$email = $argv[1] ?? '';
echo 'Mot de passe (12 caractères minimum) : ';
$mdp = getenv('MDP') ?: trim((string) fgets(STDIN));
try { creerUtilisateur($email, $mdp); echo "\nCompte créé pour $email.\n"; }
catch (Throwable $e) { echo "\nErreur : {$e->getMessage()}\n"; exit(1); }
