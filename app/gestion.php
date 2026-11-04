<?php
// Espace de gestion de Sandrine (/gestion) : agenda, groupes, clients, paiements, factures, activités, paramètres.
declare(strict_types=1);

demarrerSession();
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$route = substr($chemin, strlen('/gestion')) ?: '/';
$post = $methode === 'POST';
if ($post) verifierCsrf();

// --- Connexion (pages accessibles sans être connecté) ---
if ($route === '/connexion') { gConnexion($post); exit; }
if ($route === '/code') { gCode($post); exit; }
if ($route === '/configurer-code') { gConfigurerCode($post); exit; }
if ($route === '/deconnexion') { $_SESSION = []; session_destroy(); aller('/gestion/connexion'); }

$moi = utilisateurConnecte();
if (!$moi) aller(empty($_SESSION['utilisateur']) ? '/gestion/connexion' : '/gestion/code');

try {
    match (true) {
        $route === '/' => gTableau(),
        $route === '/agenda' => gAgenda($post),
        $route === '/groupes' => gGroupes($post),
        (bool) preg_match('#^/groupes/(\d+)$#', $route, $m) => gGroupe((int) $m[1], $post),
        (bool) preg_match('#^/reservations/(\d+)/annuler$#', $route, $m) && $post => gAnnulerReservation((int) $m[1]),
        $route === '/clients' => gClients(),
        (bool) preg_match('#^/clients/(\d+)(/export)?$#', $route, $m) => gClient((int) $m[1], $post, isset($m[2])),
        $route === '/paiements' => gPaiements(),
        $route === '/factures' => gFactures(),
        (bool) preg_match('#^/factures/(\d+)(/fichier)?$#', $route, $m) => gFacture((int) $m[1], isset($m[2])),
        $route === '/articles' => gArticles(),
        (bool) preg_match('#^/articles/(nouveau|\d+)$#', $route, $m) => gArticle($m[1] === 'nouveau' ? null : (int) $m[1], $post),
        $route === '/parametres' => gParametres($post),
        default => gPage('Page introuvable', '', fn () => print('<p>Cette page n’existe pas.</p>')),
    };
} catch (Throwable $e) {
    journaliserErreur($e);
    flash('Erreur : ' . $e->getMessage(), false);
    aller($_SERVER['HTTP_REFERER'] ?? '/gestion');
}

// ---------- Outils ----------
function aller(string $url): never { header("Location: $url", true, 303); exit; }
function flash(string $msg, bool $ok = true): void { $_SESSION['flash'] = [$msg, $ok]; }
function csrf(): string { return '<input type="hidden" name="csrf" value="' . h(jetonCsrf()) . '">'; }
function ip(): string { return $_SERVER['REMOTE_ADDR'] ?? '?'; }
function qui(): string { return 'Sandrine'; }

function gPage(string $titre, string $actif, callable $contenu, bool $menu = true): void
{
    $liens = [['/gestion', 'Tableau de bord'], ['/gestion/agenda', 'Agenda'], ['/gestion/groupes', 'Groupes'], ['/gestion/clients', 'Clients'],
        ['/gestion/paiements', 'Paiements'], ['/gestion/factures', 'Factures'], ['/gestion/articles', 'Activités'], ['/gestion/parametres', 'Paramètres']];
    echo '<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow">',
        '<title>', h($titre), ', gestion</title><link rel="icon" href="/assets/ph/logo.webp">',
        '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>',
        '<link href="https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible+Next:wght@400;600&family=Newsreader:opsz,wght@6..72,300..500&display=swap" rel="stylesheet">',
        '<link rel="stylesheet" href="/assets/style.css?v=', VERSION_ACTIFS, '"><link rel="stylesheet" href="/assets/gestion.css?v=', VERSION_ACTIFS, '"></head><body class="g">';
    if ($menu) {
        echo '<div class="bo"><aside><a class="marque" href="/gestion"><img src="/assets/ph/logo.webp" alt="" width="40" height="40"><span><b>Gestion</b><small>Sandrine Peyron</small></span></a><nav aria-label="Menu de gestion">';
        foreach ($liens as [$l, $n]) echo '<a class="m" href="', $l, '"', $actif === $l ? ' aria-current="page"' : '', '>', $n, '</a>';
        echo '</nav><p class="pied-menu"><a href="/" target="_blank">Voir le site</a><br><a href="/gestion/deconnexion">Se déconnecter</a></p></aside><main id="contenu">';
    } else {
        echo '<main class="seul" id="contenu">';
    }
    if (!empty($_SESSION['flash'])) {
        [$msg, $ok] = $_SESSION['flash'];
        unset($_SESSION['flash']);
        echo '<p class="', $ok ? 'succes' : 'alerte', '" role="status">', h($msg), '</p>';
    }
    echo '<h1>', h($titre), '</h1>';
    $contenu();
    echo '</main>', $menu ? '</div>' : '', '<script src="/assets/gestion.js?v=', VERSION_ACTIFS, '"></script></body></html>';
}

function champ(string $libelle, string $nom, string $valeur = '', string $type = 'text', string $attr = ''): string
{
    return '<label class="champ"><span>' . $libelle . '</span><input type="' . $type . '" name="' . $nom . '" value="' . h($valeur) . '" ' . $attr . '></label>';
}

function badge(string $statut): string
{
    [$cls, $txt] = match ($statut) {
        'confirmee', 'paid', 'publie' => ['ok', match ($statut) { 'paid' => 'Payé', 'publie' => 'Publié', default => 'Confirmée' }],
        'attente', 'open', 'pending', 'cree', 'brouillon' => ['att', match ($statut) { 'brouillon' => 'Brouillon', 'attente' => 'Paiement en cours', default => 'En attente' }],
        default => ['', match ($statut) { 'annulee', 'annule' => 'Annulée', 'failed' => 'Échoué', 'canceled' => 'Abandonné', 'expired' => 'Expiré', default => $statut }],
    };
    return "<span class=\"etat-p $cls\">$txt</span>";
}

// ---------- Connexion ----------
function gConnexion(bool $post): void
{
    $erreur = '';
    if ($post) {
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        if (tropDEssais('ip:' . ip(), "email:$email")) $erreur = 'Trop d’essais. Réessayez dans 15 minutes.';
        else {
            $s = db()->prepare('SELECT * FROM utilisateurs WHERE email = ?');
            $s->execute([$email]);
            $u = $s->fetch();
            if ($u && password_verify((string) ($_POST['mot_de_passe'] ?? ''), $u['mot_de_passe'])) {
                session_regenerate_id(true);
                $_SESSION = ['utilisateur' => (int) $u['id'], 'code_ok' => false, 'vu' => time()];
                aller($u['totp_actif'] ? '/gestion/code' : '/gestion/configurer-code');
            }
            noterEchec('ip:' . ip(), "email:$email");
            $erreur = 'E-mail ou mot de passe incorrect.';
        }
    }
    gPage('Connexion', '', function () use ($erreur) {
        if ($erreur) echo '<p class="alerte">', h($erreur), '</p>';
        echo '<form method="post" class="carte">', csrf(), champ('E-mail', 'email', '', 'email', 'required autocomplete="username"'),
            champ('Mot de passe', 'mot_de_passe', '', 'password', 'required autocomplete="current-password"'),
            '<div class="btns"><button class="btn plein">Se connecter</button></div></form>';
    }, false);
}

function utilisateurSession(): ?array
{
    if (empty($_SESSION['utilisateur'])) return null;
    $s = db()->prepare('SELECT * FROM utilisateurs WHERE id = ?');
    $s->execute([$_SESSION['utilisateur']]);
    return $s->fetch() ?: null;
}

function gCode(bool $post): void
{
    $u = utilisateurSession();
    if (!$u) aller('/gestion/connexion');
    if (!$u['totp_actif']) aller('/gestion/configurer-code');
    $erreur = '';
    if ($post) {
        if (tropDEssais('code:' . $u['id'], 'ip:' . ip())) $erreur = 'Trop d’essais. Réessayez dans 15 minutes.';
        elseif (($pas = verifierTotp($u['totp_secret'], (string) ($_POST['code'] ?? ''), (int) $u['totp_dernier'])) !== null) {
            db()->prepare('UPDATE utilisateurs SET totp_dernier = ? WHERE id = ?')->execute([$pas, $u['id']]);
            session_regenerate_id(true);
            $_SESSION['code_ok'] = true;
            journal(qui(), 'connexion');
            aller('/gestion');
        } else { noterEchec('code:' . $u['id'], 'ip:' . ip()); $erreur = 'Code incorrect ou expiré.'; }
    }
    gPage('Code de sécurité', '', function () use ($erreur) {
        if ($erreur) echo '<p class="alerte">', h($erreur), '</p>';
        echo '<form method="post" class="carte">', csrf(), '<p>Ouvrez votre application d’authentification et saisissez le code à 6 chiffres.</p>',
            champ('Code', 'code', '', 'text', 'inputmode="numeric" autocomplete="one-time-code" pattern="[0-9 ]{6,7}" required autofocus'),
            '<div class="btns"><button class="btn plein">Valider</button><a class="btn" href="/gestion/deconnexion">Annuler</a></div></form>';
    }, false);
}

function gConfigurerCode(bool $post): void
{
    $u = utilisateurSession();
    if (!$u) aller('/gestion/connexion');
    if ($u['totp_actif']) aller('/gestion/code');
    $_SESSION['totp_nouveau'] ??= base32Encoder(random_bytes(20));
    $secret = $_SESSION['totp_nouveau'];
    $erreur = '';
    if ($post) {
        if (($pas = verifierTotp($secret, (string) ($_POST['code'] ?? ''), 0)) !== null) {
            db()->prepare('UPDATE utilisateurs SET totp_secret = ?, totp_actif = 1, totp_dernier = ? WHERE id = ?')->execute([$secret, $pas, $u['id']]);
            unset($_SESSION['totp_nouveau']);
            session_regenerate_id(true);
            $_SESSION['code_ok'] = true;
            journal(qui(), 'code de sécurité activé');
            flash('Code de sécurité activé. Il vous sera demandé à chaque connexion.');
            aller('/gestion');
        }
        $erreur = 'Code incorrect. Vérifiez l’heure du téléphone et réessayez.';
    }
    gPage('Activer le code de sécurité', '', function () use ($erreur, $secret, $u) {
        if ($erreur) echo '<p class="alerte">', h($erreur), '</p>';
        echo '<div class="carte"><p>1. Installez une application d’authentification sur votre téléphone (Google Authenticator, Microsoft Authenticator…).</p>',
            '<p>2. Dans l’application, ajoutez un compte en scannant ce code :</p><div id="qr" data-lien="', h(lienTotp($u['email'], $secret)), '"></div>',
            '<p class="small muted">Ou saisissez la clé : <code>', h(trim(chunk_split($secret, 4, ' '))), '</code></p>',
            '<form method="post">', csrf(), '<p>3. Saisissez le code à 6 chiffres affiché :</p>', champ('Code', 'code', '', 'text', 'inputmode="numeric" pattern="[0-9 ]{6,7}" required'),
            '<div class="btns"><button class="btn plein">Activer</button></div></form></div>',
            '<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" integrity="sha384-3zSEDfvllQohrq0PHL1fOXJuC/jSOO34H46t6UQfobFOmxE5BpjjaIJY5F2/bMnU" crossorigin="anonymous" referrerpolicy="no-referrer"></script>';
    }, false);
}

// ---------- Tableau de bord ----------
function gTableau(): void
{
    gPage('Bonjour Sandrine', '/gestion', function () {
        $aujourdhui = maintenant()->format('Y-m-d');
        $alertes = [];
        if (!mollieActif()) $alertes[] = 'Paiement en ligne non branché : la clé Mollie n’est pas encore configurée. Les réservations en ligne sont fermées.';
        $sansTarif = array_filter(activites(), fn ($a) => $a['actif'] && $a['tarif_centimes'] === null);
        if ($sansTarif) $alertes[] = 'Tarif à fixer pour : ' . implode(', ', array_column($sansTarif, 'nom')) . '. <a href="/gestion/parametres">Fixer les tarifs</a>';
        if (param('facture_siret') === '') $alertes[] = 'SIRET manquant sur les factures. <a href="/gestion/parametres#facturation">Le compléter</a>';
        foreach ($alertes as $a) echo '<p class="alerte">', $a, '</p>';

        echo '<div class="grille"><section class="carte c7"><h2>Aujourd’hui</h2>';
        $lignes = evenements($aujourdhui . ' 00:00:00', $aujourdhui . ' 23:59:59');
        if (!$lignes) echo '<p class="muted">Rien de prévu aujourd’hui.</p>';
        foreach ($lignes as $l) echo '<div class="ligne"><b>', h(heure($l['debut'])), '</b><span>', h($l['texte']), '</span>', $l['badge'], '</div>';
        echo '</section><section class="carte c5"><h2>Encaissé ce mois-ci</h2>';
        $mois = maintenant()->format('Y-m');
        $s = db()->prepare("SELECT COALESCE(SUM(montant_centimes - rembourse_centimes), 0), COUNT(*) FROM paiements WHERE statut = 'paid' AND substr(paye_le, 1, 7) = ?");
        $s->execute([$mois]);
        [$total, $nb] = $s->fetch(PDO::FETCH_NUM);
        echo '<p class="chiffre">', h(euros((int) $total)), '</p><p class="muted small">', (int) $nb, ' paiement', $nb > 1 ? 's' : '', ', remboursements déduits.</p><a class="btn" href="/gestion/paiements">Voir les paiements</a></section>';

        echo '<section class="carte c7"><h2>Groupes en cours d’inscription</h2>';
        $vu = false;
        foreach (array_merge(activites('parole'), activites('sophro_groupe')) as $a) {
            foreach (groupesActivite($a) as $g) {
                if (!in_array($g['etat'], ['ouvert', 'attente', 'complet', 'verrouille'], true)) continue;
                $vu = true;
                $pct = (int) round(100 * $g['confirmes'] / $g['capacite']);
                echo '<div class="gl"><a href="/gestion/groupes/', $g['id'], '"><b>', h($a['nom']), ', groupe ', $g['rang'], '</b></a><div class="barre"><i style="width:', $pct, '%"></i></div><span class="small muted">',
                    $g['confirmes'], ' inscrit', $g['confirmes'] > 1 ? 's' : '', ' sur ', $g['capacite'], ', début ', h(dateLongue($g['premiere'])), $g['etat'] === 'verrouille' ? ' (ouvrira quand le précédent sera complet)' : '', '</span></div>';
            }
        }
        if (!$vu) echo '<p class="muted">Aucun groupe publié. <a href="/gestion/groupes">Créer un groupe</a></p>';
        echo '</section><section class="carte c5"><h2>Les 7 prochains jours</h2>';
        $semaine = evenements(maintenant()->modify('+1 day')->format('Y-m-d 00:00:00'), maintenant()->modify('+7 days')->format('Y-m-d 23:59:59'));
        if (!$semaine) echo '<p class="muted">Rien de prévu.</p>';
        foreach (array_slice($semaine, 0, 8) as $l) echo '<div class="ligne" style="grid-template-columns:1fr auto"><span><b>', h(dateLongue($l['debut'])), '</b><br>', h($l['texte']), '</span>', $l['badge'], '</div>';
        echo '<a href="/gestion/agenda">Tout l’agenda</a></section></div>';
    });
}

// Rendez-vous confirmés et séances de groupes entre deux dates
function evenements(string $de, string $a): array
{
    $l = [];
    $s = db()->prepare("SELECT r.id, r.debut, r.lieu, r.statut, c.prenom, c.nom, act.nom AS act FROM reservations r JOIN clients c ON c.id = r.client_id JOIN activites act ON act.id = r.activite_id
        WHERE r.type = 'rdv' AND r.statut IN ('confirmee', 'attente') AND r.debut BETWEEN ? AND ? ORDER BY r.debut");
    $s->execute([$de, $a]);
    foreach ($s->fetchAll() as $r) {
        if ($r['statut'] === 'attente') continue;
        $l[] = ['debut' => $r['debut'], 'texte' => "{$r['prenom']} {$r['nom']}, {$r['act']}, {$r['lieu']}", 'badge' => badge('paid')];
    }
    $s = db()->prepare("SELECT sg.debut, g.id, a.nom, (SELECT COUNT(*) FROM reservations WHERE groupe_id = g.id AND statut = 'confirmee') AS n,
            (SELECT COUNT(*) FROM seances_groupe x WHERE x.groupe_id = g.id AND x.debut <= sg.debut) AS rang, (SELECT COUNT(*) FROM seances_groupe x WHERE x.groupe_id = g.id) AS total
        FROM seances_groupe sg JOIN groupes g ON g.id = sg.groupe_id JOIN activites a ON a.id = g.activite_id WHERE g.statut = 'publie' AND sg.debut BETWEEN ? AND ?");
    $s->execute([$de, $a]);
    foreach ($s->fetchAll() as $g) $l[] = ['debut' => $g['debut'], 'texte' => "{$g['nom']}, séance {$g['rang']} sur {$g['total']} ({$g['n']} inscrits)", 'badge' => '<span class="etat-p">Groupe</span>'];
    usort($l, fn ($x, $y) => strcmp($x['debut'], $y['debut']));
    return $l;
}

// ---------- Agenda : disponibilités, absences, rendez-vous ----------
function gAgenda(bool $post): void
{
    $jours = [1 => 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi', 'Dimanche'];
    $indiv = activites('individuel');
    if ($post) {
        $action = $_POST['action'] ?? '';
        if ($action === 'ajouter_dispo') {
            $j = (int) $_POST['jour']; $d = (string) $_POST['debut']; $f = (string) $_POST['fin'];
            if ($j < 1 || $j > 7 || !preg_match('/^\d\d:\d\d$/', $d) || !preg_match('/^\d\d:\d\d$/', $f) || $d >= $f) { flash('Plage invalide : vérifiez le jour et les heures.', false); aller('/gestion/agenda'); }
            $codes = array_values(array_intersect((array) ($_POST['activites'] ?? []), array_column($indiv, 'code')));
            db()->prepare('INSERT INTO disponibilites (jour, debut, fin, lieu, activites) VALUES (?, ?, ?, ?, ?)')
                ->execute([$j, $d, $f, trim(mb_substr((string) $_POST['lieu'], 0, 120)) ?: 'Cabinet, Belleville-en-Beaujolais', count($codes) === count($indiv) ? '' : implode(',', $codes)]);
            flash('Plage ajoutée.');
        } elseif ($action === 'supprimer_dispo') {
            db()->prepare('DELETE FROM disponibilites WHERE id = ?')->execute([(int) $_POST['id']]);
            flash('Plage supprimée. Les rendez-vous déjà pris sont conservés.');
        } elseif ($action === 'ajouter_absence') {
            $d = (string) $_POST['du']; $f = (string) $_POST['au'];
            if (!preg_match('/^\d{4}-\d\d-\d\d$/', $d) || !preg_match('/^\d{4}-\d\d-\d\d$/', $f) || $d > $f) { flash('Dates d’absence invalides.', false); aller('/gestion/agenda'); }
            db()->prepare('INSERT INTO absences (debut, fin, motif) VALUES (?, ?, ?)')->execute(["$d 00:00:00", (new DateTimeImmutable($f))->modify('+1 day')->format('Y-m-d 00:00:00'), trim(mb_substr((string) $_POST['motif'], 0, 120))]);
            flash('Absence ajoutée : aucun créneau ne sera proposé sur cette période.');
        } elseif ($action === 'supprimer_absence') {
            db()->prepare('DELETE FROM absences WHERE id = ?')->execute([(int) $_POST['id']]);
            flash('Absence supprimée.');
        }
        aller('/gestion/agenda');
    }
    gPage('Agenda', '/gestion/agenda', function () use ($jours, $indiv) {
        echo '<section class="carte"><h2>Les 30 prochains jours</h2>';
        $ev = evenements(horodatage(), maintenant()->modify('+30 days')->format('Y-m-d 23:59:59'));
        if (!$ev) echo '<p class="muted">Aucun rendez-vous ni séance de groupe.</p>';
        foreach ($ev as $l) echo '<div class="ligne" style="grid-template-columns:minmax(150px,auto) 1fr auto"><b>', h(dateLongue($l['debut'])), '</b><span>', h($l['texte']), '</span>', $l['badge'], '</div>';
        echo '</section><div class="grille"><section class="carte c7"><h2>Mes plages de rendez-vous</h2><p class="muted small">Chaque semaine, le site propose des créneaux dans ces plages (durée de la séance + ',
            paramInt('battement_minutes'), ' min de battement), sauf pendant vos absences, vos groupes et les rendez-vous déjà pris.</p>';
        $dispos = db()->query('SELECT * FROM disponibilites ORDER BY jour, debut')->fetchAll();
        if (!$dispos) echo '<p class="alerte">Aucune plage : aucun créneau n’est proposé sur le site.</p>';
        foreach ($dispos as $d) {
            $quoi = $d['activites'] === '' ? 'Toutes les séances' : implode(', ', array_map(fn ($c) => activite($c)['nom'] ?? $c, explode(',', $d['activites'])));
            echo '<form method="post" class="ligne" style="grid-template-columns:1fr auto">', csrf(), '<input type="hidden" name="action" value="supprimer_dispo"><input type="hidden" name="id" value="', $d['id'], '">',
                '<span><b>', $jours[$d['jour']], ' de ', h(str_replace(':', ' h ', $d['debut'])), ' à ', h(str_replace(':', ' h ', $d['fin'])), '</b><br><span class="small muted">', h($d['lieu']), '. ', h($quoi), '</span></span>',
                '<button class="btn petit" data-confirmer="Supprimer cette plage ?">Supprimer</button></form>';
        }
        echo '<h3 style="margin-top:28px">Ajouter une plage</h3><form method="post" class="form2">', csrf(), '<input type="hidden" name="action" value="ajouter_dispo">',
            '<label class="champ"><span>Jour</span><select name="jour">';
        foreach ($jours as $n => $j) echo "<option value=\"$n\">$j</option>";
        echo '</select></label>', champ('Lieu', 'lieu', 'Cabinet, Belleville-en-Beaujolais'), champ('De', 'debut', '09:00', 'time', 'required'), champ('À', 'fin', '12:00', 'time', 'required'),
            '<fieldset class="plein-l"><legend>Séances proposées</legend>';
        foreach ($indiv as $a) echo '<label class="coche"><input type="checkbox" name="activites[]" value="', h($a['code']), '" checked><span>', h($a['nom']), '</span></label>';
        echo '</fieldset><div class="btns plein-l"><button class="btn plein">Ajouter la plage</button></div></form></section>',
            '<section class="carte c5"><h2>Absences</h2>';
        $abs = db()->prepare('SELECT * FROM absences WHERE fin >= ? ORDER BY debut');
        $abs->execute([horodatage()]);
        foreach ($abs->fetchAll() as $a) {
            $fin = (new DateTimeImmutable($a['fin']))->modify('-1 day')->format('Y-m-d H:i:s');
            echo '<form method="post" class="ligne" style="grid-template-columns:1fr auto">', csrf(), '<input type="hidden" name="action" value="supprimer_absence"><input type="hidden" name="id" value="', $a['id'], '">',
                '<span>Du ', h(dateLongue($a['debut'], false)), ' au ', h(dateLongue($fin, false)), $a['motif'] ? '<br><span class="small muted">' . h($a['motif']) . '</span>' : '', '</span><button class="btn petit">Supprimer</button></form>';
        }
        echo '<form method="post">', csrf(), '<input type="hidden" name="action" value="ajouter_absence">', champ('Du', 'du', '', 'date', 'required'), champ('Au (inclus)', 'au', '', 'date', 'required'),
            champ('Motif (pour vous)', 'motif', '', 'text', 'maxlength="120"'), '<div class="btns"><button class="btn plein">Ajouter l’absence</button></div></form></section></div>';
    });
}

// ---------- Groupes ----------
function gGroupes(bool $post): void
{
    $types = array_merge(activites('parole'), activites('sophro_groupe'));
    if ($post) {
        $a = activite((int) ($_POST['activite'] ?? 0));
        $date = (string) ($_POST['date'] ?? ''); $h = (string) ($_POST['heure'] ?? '');
        $nb = (int) ($_POST['nb'] ?? 0); $ecart = (int) ($_POST['ecart'] ?? 7); $cap = (int) ($_POST['capacite'] ?? 0);
        if (!$a || !in_array($a['type'], ['parole', 'sophro_groupe'], true) || !preg_match('/^\d{4}-\d\d-\d\d$/', $date) || !preg_match('/^\d\d:\d\d$/', $h) || $nb < 1 || $nb > 30 || $cap < 1 || $cap > 50 || !in_array($ecart, [7, 14], true)) {
            flash('Groupe non créé : vérifiez l’activité, la date, l’heure, le nombre de séances et les places.', false);
            aller('/gestion/groupes');
        }
        $tarif = versCentimes((string) ($_POST['tarif'] ?? ''));
        $statut = isset($_POST['publier']) ? 'publie' : 'brouillon';
        $id = transaction(function () use ($a, $date, $h, $nb, $ecart, $cap, $tarif, $statut) {
            db()->prepare('INSERT INTO groupes (activite_id, capacite, tarif_centimes, lieu, horaire, statut, cree_le) VALUES (?, ?, ?, ?, ?, ?, ?)')
                ->execute([$a['id'], $cap, $tarif, trim(mb_substr((string) $_POST['lieu'], 0, 120)) ?: 'Cabinet, Belleville-en-Beaujolais', trim(mb_substr((string) ($_POST['horaire'] ?? ''), 0, 80)), $statut, horodatage()]);
            $id = (int) db()->lastInsertId();
            $d = new DateTimeImmutable("$date $h");
            for ($i = 0; $i < $nb; $i++) {
                $debut = $d->modify('+' . ($i * $ecart) . ' days');
                db()->prepare('INSERT INTO seances_groupe (groupe_id, debut, fin) VALUES (?, ?, ?)')->execute([$id, horodatage($debut), horodatage($debut->modify('+' . (int) $a['duree_minutes'] . ' minutes'))]);
            }
            return $id;
        });
        journal(qui(), 'groupe créé', "#$id {$a['nom']}");
        flash($statut === 'publie' ? 'Groupe créé et affiché sur le site.' : 'Groupe créé en brouillon (non visible sur le site).');
        aller("/gestion/groupes/$id");
    }
    gPage('Groupes', '/gestion/groupes', function () use ($types) {
        $groupes = db()->query("SELECT g.*, a.nom, (SELECT MIN(debut) FROM seances_groupe WHERE groupe_id = g.id) AS premiere, (SELECT MAX(fin) FROM seances_groupe WHERE groupe_id = g.id) AS derniere,
            (SELECT COUNT(*) FROM reservations WHERE groupe_id = g.id AND statut = 'confirmee') AS n FROM groupes g JOIN activites a ON a.id = g.activite_id ORDER BY premiere DESC")->fetchAll();
        echo '<section class="carte"><h2>Tous les groupes</h2>';
        if (!$groupes) echo '<p class="muted">Aucun groupe pour l’instant.</p>';
        echo '<div class="tableau"><table><thead><tr><th>Activité</th><th>Début</th><th>Inscrits</th><th>Statut</th></tr></thead><tbody>';
        foreach ($groupes as $g) {
            $fini = $g['derniere'] && $g['derniere'] < horodatage();
            echo '<tr><td><a href="/gestion/groupes/', $g['id'], '">', h($g['nom']), '</a></td><td>', $g['premiere'] ? h(dateLongue($g['premiere'])) : '–', '</td><td>', $g['n'], ' / ', $g['capacite'], '</td><td>',
                $fini ? '<span class="etat-p">Terminé</span>' : badge($g['statut']), '</td></tr>';
        }
        echo '</tbody></table></div></section><section class="carte"><h2>Créer un groupe</h2><p class="muted small">Pour une même activité, le site n’ouvre qu’un groupe à la fois, dans l’ordre des dates : le suivant ouvre quand le précédent est complet.</p>',
            '<form method="post" class="form2">', csrf(), '<label class="champ plein-l"><span>Activité</span><select name="activite">';
        foreach ($types as $a) echo '<option value="', $a['id'], '">', h($a['nom']), ' (', $a['duree_minutes'], ' min, ', h(euros($a['tarif_centimes'] === null ? null : (int) $a['tarif_centimes'])), ')</option>';
        echo '</select></label>', champ('Date de la première séance', 'date', '', 'date', 'required'), champ('Heure', 'heure', '18:30', 'time', 'required'),
            champ('Nombre de séances', 'nb', '6', 'number', 'min="1" max="30" required'), '<label class="champ"><span>Rythme</span><select name="ecart"><option value="7">Chaque semaine</option><option value="14">Toutes les deux semaines</option></select></label>',
            champ('Places', 'capacite', '8', 'number', 'min="1" max="50" required'), champ('Tarif du cycle (vide = tarif de l’activité)', 'tarif', '', 'text', 'inputmode="decimal" placeholder="ex. 120"'),
            champ('Lieu', 'lieu', 'Cabinet, Belleville-en-Beaujolais'), champ('Horaire affiché (facultatif)', 'horaire', '', 'text', 'placeholder="ex. le jeudi, 18 h 30 à 20 h"'),
            '<label class="coche plein-l"><input type="checkbox" name="publier" value="1" checked><span>Afficher sur le site dès maintenant</span></label>',
            '<div class="btns plein-l"><button class="btn plein">Créer le groupe</button></div></form></section>';
    });
}

function gGroupe(int $id, bool $post): void
{
    $s = db()->prepare('SELECT g.*, a.nom, a.duree_minutes, a.tarif_centimes AS tarif_activite FROM groupes g JOIN activites a ON a.id = g.activite_id WHERE g.id = ?');
    $s->execute([$id]);
    $g = $s->fetch();
    if (!$g) aller('/gestion/groupes');
    $inscrits = db()->prepare("SELECT r.id, r.statut, r.montant_centimes, c.id AS cid, c.prenom, c.nom, c.email, c.telephone FROM reservations r JOIN clients c ON c.id = r.client_id WHERE r.groupe_id = ? AND r.statut != 'annulee' ORDER BY r.cree_le");
    $inscrits->execute([$id]);
    $inscrits = $inscrits->fetchAll();
    $confirmes = count(array_filter($inscrits, fn ($r) => $r['statut'] === 'confirmee'));
    if ($post) {
        $action = $_POST['action'] ?? '';
        if ($action === 'modifier') {
            $cap = (int) $_POST['capacite'];
            if ($cap < max(1, $confirmes)) { flash("Impossible : $confirmes personnes sont déjà inscrites.", false); aller("/gestion/groupes/$id"); }
            db()->prepare('UPDATE groupes SET capacite = ?, tarif_centimes = ?, lieu = ?, horaire = ? WHERE id = ?')
                ->execute([$cap, versCentimes((string) $_POST['tarif']), trim(mb_substr((string) $_POST['lieu'], 0, 120)), trim(mb_substr((string) $_POST['horaire'], 0, 80)), $id]);
            flash('Groupe mis à jour. Les inscriptions déjà payées gardent leur tarif.');
        } elseif ($action === 'publier' || $action === 'masquer') {
            if ($action === 'masquer' && $inscrits) { flash('Ce groupe a des inscrits : annulez-le plutôt que de le masquer.', false); aller("/gestion/groupes/$id"); }
            db()->prepare('UPDATE groupes SET statut = ? WHERE id = ?')->execute([$action === 'publier' ? 'publie' : 'brouillon', $id]);
            flash($action === 'publier' ? 'Groupe affiché sur le site.' : 'Groupe retiré du site.');
        } elseif ($action === 'seance') {
            $sid = (int) $_POST['seance']; $d = (string) $_POST['date']; $h = (string) $_POST['heure'];
            if (!preg_match('/^\d{4}-\d\d-\d\d$/', $d) || !preg_match('/^\d\d:\d\d$/', $h)) { flash('Date ou heure invalide.', false); aller("/gestion/groupes/$id"); }
            $debut = new DateTimeImmutable("$d $h");
            db()->prepare('UPDATE seances_groupe SET debut = ?, fin = ? WHERE id = ? AND groupe_id = ?')->execute([horodatage($debut), horodatage($debut->modify('+' . (int) $g['duree_minutes'] . ' minutes')), $sid, $id]);
            db()->prepare("UPDATE reservations SET debut = (SELECT MIN(debut) FROM seances_groupe WHERE groupe_id = ?), fin = (SELECT MAX(fin) FROM seances_groupe WHERE groupe_id = ?) WHERE groupe_id = ?")->execute([$id, $id, $id]);
            flash('Séance déplacée. Pensez à prévenir les inscrits.');
        } elseif ($action === 'annuler_groupe') {
            foreach ($inscrits as $r) if ($r['statut'] === 'confirmee') annulerReservation((int) $r['id'], qui(), (int) $r['montant_centimes'], 'Groupe annulé par Sandrine');
            db()->prepare("UPDATE groupes SET statut = 'annule' WHERE id = ?")->execute([$id]);
            journal(qui(), 'groupe annulé', "#$id");
            flash('Groupe annulé. Les inscrits sont remboursés et prévenus par e-mail.');
        } elseif ($action === 'supprimer' && !$inscrits) {
            $n = db()->prepare('SELECT COUNT(*) FROM reservations WHERE groupe_id = ?');
            $n->execute([$id]);
            if ((int) $n->fetchColumn() === 0) { db()->prepare('DELETE FROM groupes WHERE id = ?')->execute([$id]); flash('Groupe supprimé.'); aller('/gestion/groupes'); }
            flash('Ce groupe a un historique de réservations : annulez-le plutôt.', false);
        }
        aller("/gestion/groupes/$id");
    }
    gPage($g['nom'], '/gestion/groupes', function () use ($g, $inscrits, $confirmes, $id) {
        echo '<p>', badge($g['statut']), ' ', $confirmes, ' inscrit', $confirmes > 1 ? 's' : '', ' sur ', $g['capacite'], '. <a href="/groupes/', h(activite((int) $g['activite_id'])['code']), '" target="_blank">Voir sur le site</a></p>';
        echo '<div class="grille"><section class="carte c7"><h2>Inscrits</h2>';
        if (!$inscrits) echo '<p class="muted">Personne pour l’instant.</p>';
        foreach ($inscrits as $r) {
            echo '<div class="ligne" style="grid-template-columns:1fr auto auto"><span><a href="/gestion/clients/', $r['cid'], '"><b>', h($r['prenom'] . ' ' . $r['nom']), '</b></a><br><span class="small muted">', h($r['email']), ', ', h($r['telephone']), '</span></span>', badge($r['statut']),
                $r['statut'] === 'confirmee' ? formAnnulation($r) : '', '</div>';
        }
        if ($inscrits) echo '<p class="small"><a href="mailto:?bcc=', h(implode(',', array_column(array_filter($inscrits, fn ($r) => $r['statut'] === 'confirmee'), 'email'))), '">Écrire à tous les inscrits</a></p>';
        echo '</section><section class="carte c5"><h2>Séances</h2>';
        foreach (seancesGroupeAvecId($id) as $i => $se) {
            $d = new DateTimeImmutable($se['debut']);
            echo '<form method="post" class="seance">', csrf(), '<input type="hidden" name="action" value="seance"><input type="hidden" name="seance" value="', $se['id'], '"><span>', $i + 1, '.</span>',
                '<input type="date" name="date" value="', $d->format('Y-m-d'), '" aria-label="Date de la séance ', $i + 1, '"><input type="time" name="heure" value="', $d->format('H:i'), '" aria-label="Heure"><button class="btn petit">Déplacer</button></form>';
        }
        echo '</section><section class="carte c7"><h2>Réglages du groupe</h2><form method="post" class="form2">', csrf(), '<input type="hidden" name="action" value="modifier">',
            champ('Places', 'capacite', (string) $g['capacite'], 'number', 'min="1" max="50"'), champ('Tarif du cycle (vide = tarif de l’activité : ' . euros($g['tarif_activite'] === null ? null : (int) $g['tarif_activite']) . ')', 'tarif', $g['tarif_centimes'] === null ? '' : number_format($g['tarif_centimes'] / 100, 2, ',', ''), 'text', 'inputmode="decimal"'),
            champ('Lieu', 'lieu', $g['lieu']), champ('Horaire affiché', 'horaire', $g['horaire']), '<div class="btns plein-l"><button class="btn plein">Enregistrer</button></div></form></section>',
            '<section class="carte c5"><h2>Actions</h2><div class="btns">';
        if ($g['statut'] === 'brouillon') echo '<form method="post">', csrf(), '<input type="hidden" name="action" value="publier"><button class="btn plein">Afficher sur le site</button></form>';
        if ($g['statut'] === 'publie' && !$inscrits) echo '<form method="post">', csrf(), '<input type="hidden" name="action" value="masquer"><button class="btn">Retirer du site</button></form>';
        if ($g['statut'] !== 'annule' && $inscrits) echo '<form method="post">', csrf(), '<input type="hidden" name="action" value="annuler_groupe"><button class="btn danger" data-confirmer="Annuler le groupe ? Tous les inscrits seront remboursés intégralement et prévenus par e-mail.">Annuler le groupe et rembourser</button></form>';
        if (!$inscrits) echo '<form method="post">', csrf(), '<input type="hidden" name="action" value="supprimer"><button class="btn danger" data-confirmer="Supprimer définitivement ce groupe ?">Supprimer</button></form>';
        echo '</div></section></div>';
    });
}

function seancesGroupeAvecId(int $id): array
{
    $s = db()->prepare('SELECT id, debut, fin FROM seances_groupe WHERE groupe_id = ? ORDER BY debut');
    $s->execute([$id]);
    return $s->fetchAll();
}

function formAnnulation(array $r): string
{
    $m = number_format($r['montant_centimes'] / 100, 2, ',', '');
    return '<details class="annul"><summary class="btn petit">Annuler</summary><form method="post" action="/gestion/reservations/' . $r['id'] . '/annuler">' . csrf() .
        champ('Montant remboursé (€)', 'rembourse', $m, 'text', 'inputmode="decimal"') . champ('Motif (envoyé au client)', 'motif', 'Annulation', 'text', 'maxlength="120"') .
        '<button class="btn danger" data-confirmer="Confirmer l’annulation ? Le client sera prévenu par e-mail.">Confirmer l’annulation</button></form></details>';
}

function gAnnulerReservation(int $id): void
{
    try {
        annulerReservation($id, qui(), versCentimes((string) ($_POST['rembourse'] ?? '0')) ?? 0, trim(mb_substr((string) ($_POST['motif'] ?? ''), 0, 120)) ?: 'Annulation');
        flash('Réservation annulée. Le client est prévenu par e-mail.');
    } catch (ErreurReservation $e) { flash($e->getMessage(), false); }
    aller($_SERVER['HTTP_REFERER'] ?? '/gestion');
}

// ---------- Clients ----------
function gClients(): void
{
    $q = trim((string) ($_GET['q'] ?? ''));
    gPage('Clients', '/gestion/clients', function () use ($q) {
        echo '<form class="recherche" role="search"><input type="search" name="q" value="', h($q), '" placeholder="Nom, prénom ou e-mail" aria-label="Rechercher un client"><button class="btn">Rechercher</button></form>';
        $s = db()->prepare("SELECT c.*, (SELECT COUNT(*) FROM reservations WHERE client_id = c.id AND statut = 'confirmee') AS n, (SELECT MAX(debut) FROM reservations WHERE client_id = c.id AND statut = 'confirmee') AS dernier
            FROM clients c WHERE c.anonymise = 0 AND (? = '' OR c.nom LIKE ? OR c.prenom LIKE ? OR c.email LIKE ?) ORDER BY c.nom, c.prenom LIMIT 200");
        $like = "%$q%";
        $s->execute([$q, $like, $like, $like]);
        $l = $s->fetchAll();
        echo '<section class="carte"><div class="tableau"><table><thead><tr><th>Nom</th><th>Contact</th><th>Réservations</th><th>Dernière</th></tr></thead><tbody>';
        foreach ($l as $c) echo '<tr><td><a href="/gestion/clients/', $c['id'], '">', h($c['nom'] . ' ' . $c['prenom']), '</a></td><td>', h($c['email']), '<br>', h($c['telephone']), '</td><td>', $c['n'], '</td><td>', $c['dernier'] ? h(dateLongue($c['dernier'], false)) : '–', '</td></tr>';
        echo '</tbody></table></div>', $l ? '' : '<p class="muted">Aucun client.</p>', '</section>';
    });
}

function gClient(int $id, bool $post, bool $export): void
{
    $s = db()->prepare('SELECT * FROM clients WHERE id = ?');
    $s->execute([$id]);
    $c = $s->fetch();
    if (!$c) aller('/gestion/clients');
    $res = db()->prepare('SELECT r.id FROM reservations r WHERE r.client_id = ? ORDER BY r.debut DESC');
    $res->execute([$id]);
    $reservations = array_map(fn ($rid) => reservation((int) $rid), $res->fetchAll(PDO::FETCH_COLUMN));
    if ($export) {
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="donnees-client-' . $id . '.json"');
        echo json_encode(['client' => $c, 'reservations' => array_map(fn ($r) => array_intersect_key($r, array_flip(['activite_nom', 'debut', 'fin', 'lieu', 'statut', 'montant_centimes', 'reference', 'paiement_statut', 'rembourse_centimes'])), $reservations)], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        journal(qui(), 'export des données client', "#$id");
        exit;
    }
    if ($post && ($_POST['action'] ?? '') === 'anonymiser') {
        if (array_filter($reservations, fn ($r) => $r['statut'] !== 'annulee' && $r['debut'] > horodatage())) { flash('Ce client a une réservation à venir : annulez-la d’abord.', false); aller("/gestion/clients/$id"); }
        db()->prepare("UPDATE clients SET prenom = 'Client', nom = ?, email = ?, telephone = '', anonymise = 1 WHERE id = ?")->execute(["effacé #$id", "efface-$id@invalid", $id]);
        journal(qui(), 'client anonymisé', "#$id");
        flash('Données effacées. Les factures restent conservées 10 ans (obligation légale).');
        aller('/gestion/clients');
    }
    gPage($c['prenom'] . ' ' . $c['nom'], '/gestion/clients', function () use ($c, $reservations, $id) {
        echo '<div class="grille"><section class="carte c5"><h2>Coordonnées</h2><p><a href="mailto:', h($c['email']), '">', h($c['email']), '</a><br><a href="tel:', h($c['telephone']), '">', h($c['telephone']), '</a></p>',
            '<p class="small muted">Client depuis le ', h(dateLongue($c['cree_le'], false)), '. Accord donné le ', h($c['consentement_le'] ? dateLongue($c['consentement_le']) : '–'), '.</p>',
            '<div class="btns"><a class="btn" href="/gestion/clients/', $id, '/export">Exporter ses données</a><form method="post">', csrf(), '<input type="hidden" name="action" value="anonymiser"><button class="btn danger" data-confirmer="Effacer les coordonnées de ce client ? Les factures sont conservées.">Effacer ses données</button></form></div></section>',
            '<section class="carte c7"><h2>Historique</h2>';
        if (!$reservations) echo '<p class="muted">Aucune réservation.</p>';
        foreach ($reservations as $r) {
            echo '<div class="ligne" style="grid-template-columns:1fr auto auto"><span><b>', h(titreReservation($r)), '</b><br><span class="small muted">', h(dateLongue($r['debut'])), ', ', h(euros((int) $r['montant_centimes'])),
                $r['rembourse_centimes'] > 0 ? ', remboursé ' . h(euros((int) $r['rembourse_centimes'])) : '', $r['motif_annulation'] ? ' (' . h($r['motif_annulation']) . ')' : '', '</span></span>', badge($r['statut']),
                $r['statut'] === 'confirmee' ? formAnnulation($r) : '', '</div>';
        }
        echo '</section></div>';
    });
}

// ---------- Paiements et factures ----------
function gPaiements(): void
{
    gPage('Paiements', '/gestion/paiements', function () {
        $annee = maintenant()->format('Y');
        $mois = db()->prepare("SELECT substr(paye_le, 1, 7) AS m, SUM(montant_centimes) AS brut, SUM(rembourse_centimes) AS remb, COUNT(*) AS n FROM paiements WHERE statut = 'paid' AND substr(paye_le, 1, 4) = ? GROUP BY m ORDER BY m DESC");
        $mois->execute([$annee]);
        echo '<section class="carte"><h2>', $annee, ', mois par mois</h2><div class="tableau"><table><thead><tr><th>Mois</th><th>Paiements</th><th>Encaissé</th><th>Remboursé</th><th>Net</th></tr></thead><tbody>';
        foreach ($mois->fetchAll() as $m) echo '<tr><td>', h(ucfirst(dateLongue($m['m'] . '-01 00:00:00', false))), '</td><td>', $m['n'], '</td><td>', h(euros((int) $m['brut'])), '</td><td>', h(euros((int) $m['remb'])), '</td><td><b>', h(euros((int) $m['brut'] - (int) $m['remb'])), '</b></td></tr>';
        echo '</tbody></table></div><p class="small muted">Les virements vers votre compte bancaire sont faits par Mollie, frais Mollie déduits.</p></section>';
        $l = db()->query("SELECT p.*, r.id AS rid, c.id AS cid, c.prenom, c.nom, a.nom AS act FROM paiements p JOIN reservations r ON r.paiement_id = p.id JOIN clients c ON c.id = r.client_id JOIN activites a ON a.id = r.activite_id
            WHERE p.statut NOT IN ('cree') ORDER BY p.cree_le DESC LIMIT 300")->fetchAll();
        echo '<section class="carte"><h2>Derniers paiements</h2><div class="tableau"><table><thead><tr><th>Date</th><th>Client</th><th>Prestation</th><th>Montant</th><th>Statut</th></tr></thead><tbody>';
        foreach ($l as $p) echo '<tr><td>', h(dateLongue($p['paye_le'] ?? $p['cree_le'])), '</td><td><a href="/gestion/clients/', $p['cid'], '">', h($p['prenom'] . ' ' . $p['nom']), '</a></td><td>', h($p['act']), '</td><td>', h(euros((int) $p['montant_centimes'])),
            $p['rembourse_centimes'] > 0 ? '<br><span class="small muted">remboursé ' . h(euros((int) $p['rembourse_centimes'])) . '</span>' : '', '</td><td>', badge($p['statut']), '</td></tr>';
        echo '</tbody></table></div>', $l ? '' : '<p class="muted">Aucun paiement.</p>', '</section>';
    });
}

function gFactures(): void
{
    gPage('Factures', '/gestion/factures', function () {
        $l = db()->query('SELECT f.id, f.numero, f.type, f.montant_centimes, f.emise_le, f.donnees FROM factures f ORDER BY f.id DESC LIMIT 500')->fetchAll();
        echo '<section class="carte"><p class="small muted">Une facture est créée automatiquement à chaque paiement, un avoir à chaque remboursement. Elles ne sont jamais modifiées ni supprimées (conservation 10 ans).</p>',
            '<div class="tableau"><table><thead><tr><th>Numéro</th><th>Date</th><th>Client</th><th>Montant</th><th></th></tr></thead><tbody>';
        foreach ($l as $f) {
            $d = json_decode($f['donnees'], true);
            echo '<tr><td><a href="/gestion/factures/', $f['id'], '">', h($f['numero']), '</a>', $f['type'] === 'avoir' ? ' <span class="etat-p">Avoir</span>' : '', '</td><td>', h(dateLongue($f['emise_le'], false)), '</td><td>', h($d['client']['nom']), '</td><td>', h(euros((int) $f['montant_centimes'])), '</td><td><a href="/gestion/factures/', $f['id'], '/fichier">Télécharger</a></td></tr>';
        }
        echo '</tbody></table></div>', $l ? '' : '<p class="muted">Aucune facture.</p>', '</section>';
    });
}

function gFacture(int $id, bool $fichier): void
{
    $f = facture($id);
    if (!$f) aller('/gestion/factures');
    if ($fichier) {
        [$nom, $contenu, $type] = fichierFacture($id);
        header("Content-Type: $type");
        header('Content-Disposition: attachment; filename="' . $nom . '"');
        echo $contenu;
        exit;
    }
    header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline'");
    echo htmlFacture($f);
}

// ---------- Activités (articles) ----------
function gArticles(): void
{
    gPage('Activités', '/gestion/articles', function () {
        echo '<p><a class="btn plein" href="/gestion/articles/nouveau">Écrire une publication</a></p><section class="carte"><div class="tableau"><table><thead><tr><th>Titre</th><th>Type</th><th>Statut</th><th>Modifié</th></tr></thead><tbody>';
        $l = db()->query('SELECT * FROM articles ORDER BY maj_le DESC')->fetchAll();
        foreach ($l as $a) echo '<tr><td><a href="/gestion/articles/', $a['id'], '">', h($a['titre']), '</a></td><td>', $a['categorie'] === 'compte_rendu' ? 'Compte rendu' : 'Article', '</td><td>', badge($a['statut']), '</td><td>', h(dateLongue($a['maj_le'])), '</td></tr>';
        echo '</tbody></table></div>', $l ? '' : '<p class="muted">Aucune publication pour l’instant.</p>', '</section>';
    });
}

function gArticle(?int $id, bool $post): void
{
    $a = $id ? article($id) : null;
    if ($id && !$a) aller('/gestion/articles');
    if ($post) {
        if (($_POST['action'] ?? '') === 'supprimer' && $a) {
            if ($a['image'] && is_file(RACINE . '/public/' . $a['image'])) unlink(RACINE . '/public/' . $a['image']);
            db()->prepare('DELETE FROM articles WHERE id = ?')->execute([$id]);
            journal(qui(), 'publication supprimée', $a['titre']);
            flash('Publication supprimée.');
            aller('/gestion/articles');
        }
        try {
            $id = enregistrerArticle($_POST, $id);
            if (($_FILES['image']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) enregistrerImage($_FILES['image'], $id);
            if (!empty($_POST['retirer_image']) && ($x = article($id)) && $x['image']) {
                @unlink(RACINE . '/public/' . $x['image']);
                db()->prepare('UPDATE articles SET image = NULL WHERE id = ?')->execute([$id]);
            }
            $x = article($id);
            journal(qui(), $x['statut'] === 'publie' ? 'publication en ligne' : 'brouillon enregistré', $x['titre']);
            flash($x['statut'] === 'publie' ? 'Publié sur le site.' : 'Brouillon enregistré (non visible sur le site).');
        } catch (InvalidArgumentException|RuntimeException $e) { flash($e->getMessage(), false); }
        aller('/gestion/articles/' . ($id ?: 'nouveau'));
    }
    gPage($a ? 'Modifier la publication' : 'Nouvelle publication', '/gestion/articles', function () use ($a) {
        echo '<form method="post" enctype="multipart/form-data" class="carte">', csrf(), champ('Titre', 'titre', $a['titre'] ?? '', 'text', 'required maxlength="160"'),
            '<label class="champ"><span>Type</span><select name="categorie"><option value="article">Article</option><option value="compte_rendu"', ($a['categorie'] ?? '') === 'compte_rendu' ? ' selected' : '', '>Compte rendu</option></select></label>',
            '<label class="champ"><span>Résumé (affiché sur la liste et dans Google)</span><textarea name="resume" rows="2" maxlength="300">', h($a['resume'] ?? ''), '</textarea></label>',
            '<label class="champ"><span>Texte</span><textarea name="contenu" rows="16">', h($a['contenu'] ?? ''), '</textarea></label>',
            '<p class="small muted aide">Mise en forme : laissez une ligne vide entre deux paragraphes. <code>## Titre</code> pour un intertitre, <code>- </code> en début de ligne pour une liste, <code>**mot**</code> pour du gras, <code>[texte](https://adresse)</code> pour un lien.</p>',
            $a && $a['image'] ? '<p><img src="/' . h($a['image']) . '" alt="" style="max-width:260px;border-radius:12px"></p><label class="coche"><input type="checkbox" name="retirer_image" value="1"><span>Retirer la photo</span></label>' : '',
            '<label class="champ"><span>Photo (JPEG, PNG ou WebP)</span><input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>',
            '<div class="btns"><button class="btn plein" name="statut" value="publie">', ($a['statut'] ?? '') === 'publie' ? 'Mettre à jour' : 'Publier', '</button><button class="btn" name="statut" value="brouillon">', ($a['statut'] ?? '') === 'publie' ? 'Retirer du site (brouillon)' : 'Enregistrer le brouillon', '</button>',
            $a && $a['statut'] === 'publie' ? '<a class="btn" href="/activites/' . h($a['slug']) . '" target="_blank">Voir sur le site</a>' : '', '</div></form>';
        if ($a) echo '<form method="post" style="margin-top:20px">', csrf(), '<input type="hidden" name="action" value="supprimer"><button class="btn danger" data-confirmer="Supprimer définitivement cette publication ?">Supprimer</button></form>';
    });
}

// ---------- Paramètres ----------
function gParametres(bool $post): void
{
    $regles = ['annulation_heures' => ['Annulation gratuite par le client jusqu’à (heures avant)', 1, 720], 'delai_min_heures' => ['Réservation possible au plus tard (heures avant)', 0, 336],
        'horizon_jours' => ['Réservation possible jusqu’à (jours à l’avance)', 7, 365], 'battement_minutes' => ['Battement entre deux rendez-vous (minutes)', 0, 120], 'blocage_minutes' => ['Place bloquée pendant le paiement (minutes)', 10, 60]];
    $coord = ['telephone' => 'Téléphone affiché', 'email_sandrine' => 'E-mail (notifications et réponses des clients)', 'facture_nom' => 'Nom sur les factures', 'facture_siret' => 'SIRET', 'facture_mention_tva' => 'Mention TVA'];
    if ($post) {
        $quoi = $_POST['section'] ?? '';
        if ($quoi === 'tarifs') {
            foreach (activites() as $a) {
                $k = $a['code'];
                $duree = (int) ($_POST["duree_$k"] ?? $a['duree_minutes']);
                if ($duree < 15 || $duree > 480) throw new InvalidArgumentException('Durée invalide pour ' . $a['nom'] . '.');
                db()->prepare('UPDATE activites SET tarif_centimes = ?, duree_minutes = ?, actif = ? WHERE id = ?')
                    ->execute([versCentimes((string) ($_POST["tarif_$k"] ?? '')), $duree, isset($_POST["actif_$k"]) ? 1 : 0, $a['id']]);
            }
            journal(qui(), 'tarifs modifiés');
            flash('Tarifs enregistrés. Ils s’appliquent aux nouvelles réservations.');
        } elseif ($quoi === 'regles') {
            foreach ($regles as $k => [$lib, $min, $max]) {
                $v = (int) ($_POST[$k] ?? -1);
                if ($v < $min || $v > $max) throw new InvalidArgumentException("« $lib » doit être entre $min et $max.");
                definirParam($k, (string) $v);
            }
            journal(qui(), 'règles de réservation modifiées');
            flash('Règles enregistrées.');
        } elseif ($quoi === 'coordonnees') {
            foreach ($coord as $k => $lib) definirParam($k, trim(mb_substr((string) ($_POST[$k] ?? ''), 0, 200)));
            definirParam('facture_adresse', trim(mb_substr((string) ($_POST['facture_adresse'] ?? ''), 0, 300)));
            if (!filter_var(param('email_sandrine'), FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('E-mail invalide.');
            journal(qui(), 'coordonnées modifiées');
            flash('Coordonnées enregistrées. Les factures déjà émises ne changent pas.');
        } elseif ($quoi === 'mot_de_passe') {
            $u = utilisateurConnecte();
            if (!password_verify((string) $_POST['actuel'], $u['mot_de_passe'])) throw new InvalidArgumentException('Mot de passe actuel incorrect.');
            verifierRobustesse((string) $_POST['nouveau']);
            db()->prepare('UPDATE utilisateurs SET mot_de_passe = ? WHERE id = ?')->execute([password_hash((string) $_POST['nouveau'], PASSWORD_DEFAULT), $u['id']]);
            journal(qui(), 'mot de passe changé');
            flash('Mot de passe changé.');
        }
        aller('/gestion/parametres');
    }
    gPage('Paramètres', '/gestion/parametres', function () use ($regles, $coord) {
        echo '<section class="carte" id="tarifs"><h2>Tarifs et durées</h2><p class="small muted">Laissez le tarif vide tant qu’il n’est pas fixé : la réservation en ligne reste fermée pour cette activité. Pour un groupe, le tarif est celui du cycle complet ; vous pouvez aussi fixer un tarif propre à un groupe.</p>',
            '<form method="post">', csrf(), '<input type="hidden" name="section" value="tarifs"><div class="tableau"><table><thead><tr><th>Activité</th><th>Tarif (€)</th><th>Durée (min)</th><th>Proposée</th></tr></thead><tbody>';
        foreach (activites() as $a) {
            $k = h($a['code']);
            echo '<tr><td>', h($a['nom']), '</td><td><input class="court" name="tarif_', $k, '" value="', $a['tarif_centimes'] === null ? '' : number_format($a['tarif_centimes'] / 100, 2, ',', ''), '" inputmode="decimal" aria-label="Tarif ', h($a['nom']), '"></td>',
                '<td><input class="court" type="number" min="15" max="480" name="duree_', $k, '" value="', (int) $a['duree_minutes'], '" aria-label="Durée ', h($a['nom']), '"></td>',
                '<td><input type="checkbox" name="actif_', $k, '" value="1"', $a['actif'] ? ' checked' : '', ' aria-label="Proposer ', h($a['nom']), '"></td></tr>';
        }
        echo '</tbody></table></div><div class="btns"><button class="btn plein">Enregistrer les tarifs</button></div></form></section>',
            '<section class="carte"><h2>Règles de réservation</h2><form method="post" class="form2">', csrf(), '<input type="hidden" name="section" value="regles">';
        foreach ($regles as $k => [$lib, $min, $max]) echo champ($lib, $k, param($k), 'number', "min=\"$min\" max=\"$max\" required");
        echo '<div class="btns plein-l"><button class="btn plein">Enregistrer les règles</button></div></form></section>',
            '<section class="carte" id="facturation"><h2>Coordonnées et facturation</h2><form method="post" class="form2">', csrf(), '<input type="hidden" name="section" value="coordonnees">';
        foreach ($coord as $k => $lib) echo champ($lib, $k, param($k));
        echo '<label class="champ plein-l"><span>Adresse (factures et site)</span><textarea name="facture_adresse" rows="3">', h(param('facture_adresse')), '</textarea></label>',
            '<div class="btns plein-l"><button class="btn plein">Enregistrer</button></div></form></section>',
            '<section class="carte"><h2>Mot de passe</h2><form method="post" class="form2">', csrf(), '<input type="hidden" name="section" value="mot_de_passe">',
            champ('Mot de passe actuel', 'actuel', '', 'password', 'required autocomplete="current-password"'), champ('Nouveau (12 caractères minimum)', 'nouveau', '', 'password', 'required minlength="12" autocomplete="new-password"'),
            '<div class="btns plein-l"><button class="btn plein">Changer le mot de passe</button></div></form></section>',
            '<section class="carte"><h2>Journal</h2>';
        foreach (db()->query('SELECT * FROM journal ORDER BY id DESC LIMIT 30')->fetchAll() as $j) echo '<div class="ligne" style="grid-template-columns:auto 1fr"><span class="small muted">', h(dateLongue($j['quand'])), '</span><span>', h($j['qui']), ' : ', h($j['action']), $j['detail'] ? ' <span class="muted">' . h($j['detail']) . '</span>' : '', '</span></div>';
        echo '</section>';
    });
}
