<?php
// E-mails automatiques (SMTP via PHPMailer). En local : écrits dans donnees/journaux/emails-dev.log.
declare(strict_types=1);

// $joints : [[nom, contenu, type MIME], …]
function expedier(string $a, string $sujet, string $texte, array $joints = []): bool
{
    if (isset($GLOBALS['EMAILS_TEST'])) { $GLOBALS['EMAILS_TEST'][] = compact('a', 'sujet', 'texte', 'joints'); return true; }
    $c = config();
    if ($c['dev']) {
        $pj = implode('', array_map(fn ($j) => "\n[PJ] {$j[0]} (" . strlen($j[1]) . ' octets)', $joints));
        file_put_contents(dossierDonnees('journaux') . '/emails-dev.log', date('c') . " → $a\n$sujet\n\n$texte$pj\n-----\n", FILE_APPEND);
        return true;
    }
    if ($c['smtp_hote'] === '' || !is_file(RACINE . '/vendor/autoload.php')) { journaliserErreur("E-mail non envoyé (SMTP non configuré) : $sujet"); return false; }
    try {
        require_once RACINE . '/vendor/autoload.php';
        $m = new \PHPMailer\PHPMailer\PHPMailer(true);
        $m->isSMTP();
        $m->Host = $c['smtp_hote'];
        $m->Port = (int) $c['smtp_port'];
        $m->SMTPSecure = (int) $c['smtp_port'] === 465 ? 'ssl' : 'tls';
        $m->SMTPAuth = true;
        $m->Username = $c['smtp_utilisateur'];
        $m->Password = $c['smtp_mot_de_passe'];
        $m->CharSet = 'UTF-8';
        $m->setFrom($c['expediteur'], $c['expediteur_nom']);
        $m->addReplyTo(param('email_sandrine'), 'Sandrine Peyron');
        $m->addAddress($a);
        $m->Subject = $sujet;
        $m->Body = $texte;
        foreach ($joints as [$nom, $contenu, $type]) $m->addStringAttachment($contenu, $nom, 'base64', $type);
        return $m->send();
    } catch (Throwable $e) {
        journaliserErreur('E-mail : ' . $e->getMessage());
        return false;
    }
}

function signature(): string
{
    return "\n\nSandrine Peyron\nSophrologie, EMDR-DSA, groupes de parole\n" . param('telephone') . "\n" . rtrim(config()['url'], '/');
}

// Invitation de calendrier (.ics) pour une ou plusieurs dates
function ics(string $titre, array $creneaux, string $lieu, string $uid): string
{
    $fmt = fn (string $d) => (new DateTimeImmutable($d))->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
    $esc = fn (string $s) => addcslashes(str_replace("\n", '\n', $s), ',;');
    $l = ["BEGIN:VCALENDAR", "VERSION:2.0", "PRODID:-//Sandrine Peyron//Reservations//FR", "METHOD:PUBLISH"];
    foreach ($creneaux as $i => [$debut, $fin]) {
        array_push($l, "BEGIN:VEVENT", "UID:$uid-$i@sandrinepeyron", "DTSTAMP:" . $fmt(horodatage()), "DTSTART:" . $fmt($debut), "DTEND:" . $fmt($fin), "SUMMARY:" . $esc($titre), "LOCATION:" . $esc($lieu), "END:VEVENT");
    }
    $l[] = "END:VCALENDAR";
    return implode("\r\n", $l) . "\r\n";
}
