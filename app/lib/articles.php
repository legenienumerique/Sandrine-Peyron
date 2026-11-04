<?php
// Activités : articles et comptes rendus. Texte simple mis en forme (titres, listes, gras, liens), jamais de HTML saisi.
declare(strict_types=1);

function slugifier(string $t): string
{
    $t = mb_strtolower(trim($t));
    $t = strtr($t, ['à' => 'a', 'â' => 'a', 'ä' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'œ' => 'oe', 'æ' => 'ae', '’' => '-', "'" => '-']);
    $t = trim(preg_replace('/[^a-z0-9]+/', '-', $t), '-');
    return mb_substr($t, 0, 80) ?: 'article';
}

function slugLibre(string $base, int $sauf = 0): string
{
    $slug = $base;
    for ($i = 2; ; $i++) {
        $s = db()->prepare('SELECT COUNT(*) FROM articles WHERE slug = ? AND id != ?');
        $s->execute([$slug, $sauf]);
        if ((int) $s->fetchColumn() === 0) return $slug;
        $slug = "$base-$i";
    }
}

function enregistrerArticle(array $d, ?int $id = null): int
{
    $titre = trim(mb_substr((string) ($d['titre'] ?? ''), 0, 160));
    if ($titre === '') throw new InvalidArgumentException('Donnez un titre.');
    $categorie = ($d['categorie'] ?? '') === 'compte_rendu' ? 'compte_rendu' : 'article';
    $statut = ($d['statut'] ?? '') === 'publie' ? 'publie' : 'brouillon';
    $resume = trim(mb_substr((string) ($d['resume'] ?? ''), 0, 300));
    $contenu = trim(mb_substr((string) ($d['contenu'] ?? ''), 0, 60000));
    if ($id) {
        $avant = article($id);
        $publieLe = $statut === 'publie' ? ($avant['publie_le'] ?: horodatage()) : $avant['publie_le'];
        db()->prepare('UPDATE articles SET titre = ?, slug = ?, categorie = ?, resume = ?, contenu = ?, statut = ?, publie_le = ?, maj_le = ? WHERE id = ?')
            ->execute([$titre, slugLibre(slugifier($titre), $id), $categorie, $resume, $contenu, $statut, $publieLe, horodatage(), $id]);
        return $id;
    }
    db()->prepare('INSERT INTO articles (titre, slug, categorie, resume, contenu, statut, publie_le, maj_le) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$titre, slugLibre(slugifier($titre)), $categorie, $resume, $contenu, $statut, $statut === 'publie' ? horodatage() : null, horodatage()]);
    return (int) db()->lastInsertId();
}

function article(int $id): ?array
{
    $s = db()->prepare('SELECT * FROM articles WHERE id = ?');
    $s->execute([$id]);
    return $s->fetch() ?: null;
}

function articlePublie(string $slug): ?array
{
    $s = db()->prepare("SELECT * FROM articles WHERE slug = ? AND statut = 'publie'");
    $s->execute([$slug]);
    return $s->fetch() ?: null;
}

function articlesPublies(?int $limite = null): array
{
    return db()->query("SELECT * FROM articles WHERE statut = 'publie' ORDER BY publie_le DESC" . ($limite ? ' LIMIT ' . (int) $limite : ''))->fetchAll();
}

// Photo envoyée : réencodée en WebP (supprime tout contenu caché), 1600 px maximum
function enregistrerImage(array $fichier, int $articleId): string
{
    if (($fichier['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) throw new InvalidArgumentException('La photo n’a pas pu être envoyée.');
    if ($fichier['size'] > 12 * 1024 * 1024) throw new InvalidArgumentException('Photo trop lourde (12 Mo maximum).');
    $info = @getimagesize($fichier['tmp_name']);
    if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) throw new InvalidArgumentException('Format accepté : JPEG, PNG ou WebP.');
    if (!function_exists('imagewebp')) throw new RuntimeException('Traitement des images indisponible sur le serveur (GD).');
    $src = match ($info[2]) { IMAGETYPE_JPEG => imagecreatefromjpeg($fichier['tmp_name']), IMAGETYPE_PNG => imagecreatefrompng($fichier['tmp_name']), default => imagecreatefromwebp($fichier['tmp_name']) };
    [$w, $hh] = [$info[0], $info[1]];
    $k = min(1, 1600 / max($w, $hh));
    $dst = imagecreatetruecolor((int) round($w * $k), (int) round($hh * $k));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, imagesx($dst), imagesy($dst), $w, $hh);
    $dossier = RACINE . '/public/medias';
    if (!is_dir($dossier)) mkdir($dossier, 0755, true);
    $nom = "article-$articleId-" . substr(bin2hex(random_bytes(4)), 0, 8) . '.webp';
    imagewebp($dst, "$dossier/$nom", 80);
    $ancien = article($articleId)['image'] ?? null;
    if ($ancien && is_file("$dossier/" . basename($ancien))) unlink("$dossier/" . basename($ancien));
    db()->prepare('UPDATE articles SET image = ? WHERE id = ?')->execute(["medias/$nom", $articleId]);
    return "medias/$nom";
}

function texteEnHtml(string $texte): string
{
    $html = '';
    foreach (preg_split("/\R{2,}/", trim(str_replace("\r", '', $texte))) as $bloc) {
        $lignes = explode("\n", $bloc);
        if (str_starts_with($bloc, '## ')) { $html .= '<h2>' . enLigne(substr($bloc, 3)) . '</h2>'; continue; }
        if (array_filter($lignes, fn ($l) => !str_starts_with(ltrim($l), '- ')) === []) {
            $html .= '<ul>' . implode('', array_map(fn ($l) => '<li>' . enLigne(substr(ltrim($l), 2)) . '</li>', $lignes)) . '</ul>';
            continue;
        }
        $html .= '<p>' . implode('<br>', array_map('enLigne', $lignes)) . '</p>';
    }
    return $html;
}

function enLigne(string $t): string
{
    $t = h($t);
    $t = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $t);
    return preg_replace_callback('/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/u', fn ($m) => '<a href="' . $m[2] . '" rel="noopener">' . $m[1] . '</a>', $t);
}
