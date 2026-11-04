# Sandrine Peyron — site et espace de gestion

Site public (groupes, séances individuelles, activités) avec réservation et paiement en ligne (Mollie),
et espace de gestion privé `/gestion` pour Sandrine. PHP 8.2+ et SQLite, sans framework, hébergé chez alwaysdata.

Plan et décisions : `OneDrive\Documents\#2 - Le Génie Numérique\LGN\Clients\Sandrine PEYRON\ARCHITECTURE.md`.

## Organisation
| Dossier | Rôle |
|---|---|
| `public/` | Racine web : `index.php` (point d'entrée unique), `assets/`, `medias/` (photos des articles) |
| `app/lib/` | `reservations.php` (groupes, ouverture séquentielle, paiements, annulations), `agenda.php` (créneaux), `factures.php`, `articles.php`, `auth.php` (mot de passe + code à 6 chiffres), `mollie.php`, `mail.php` |
| `app/vues/` | Pages publiques ; `contenus.php` = textes des pages de groupes |
| `app/gestion.php` | Espace de gestion |
| `app/schema.sql` | Base de données |
| `outils/` | `compte.php` (créer le compte de Sandrine), `tache.php` (tâche planifiée), `demo-local.php` (démo, local uniquement) |
| `tests/tests.php` | Tests des parcours (faux Mollie en mémoire) |

## En local
```
php -c php-local.ini outils/demo-local.php
php -c php-local.ini -S localhost:4180 -t public public/routeur-dev.php
php -c php-local.ini tests/tests.php
```
`app/config.php` (non publié) avec `'dev' => true` et `'mollie_cle' => 'faux-local'` : une fausse page de paiement locale remplace Mollie, les e-mails sont écrits dans `donnees/journaux/emails-dev.log`.

## Mise en ligne (à faire, avec l'accord d'Eliott)
1. alwaysdata : site PHP, racine `public/`, HTTPS forcé. `app/config.php` d'après `app/config.exemple.php`, `donnees` hors du dossier public.
2. `composer install --no-dev` (PHPMailer, dompdf).
3. `php outils/compte.php <email de Sandrine>`, puis première connexion sur `/gestion` pour activer le code de sécurité.
4. Tâche planifiée toutes les 5 minutes : `php $HOME/sandrine-peyron/outils/tache.php`.
5. Mollie : compte au nom de Sandrine ; clé `test_…` pour les essais, puis `live_…`.
6. Sauvegarde quotidienne du dossier `donnees`.
