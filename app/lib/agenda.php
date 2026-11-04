<?php
// Créneaux des séances individuelles : plages de Sandrine, moins les absences, les rendez-vous et les groupes.
declare(strict_types=1);

// Vrai si [debut, fin[ chevauche un rendez-vous, une séance de groupe ou une absence (battement compris)
function occupe(string $debut, string $fin, ?int $saufReservation = null): bool
{
    $batt = paramInt('battement_minutes');
    $avant = (new DateTimeImmutable($debut))->modify("-$batt minutes")->format('Y-m-d H:i:s');
    $apres = (new DateTimeImmutable($fin))->modify("+$batt minutes")->format('Y-m-d H:i:s');
    $s = db()->prepare("SELECT COUNT(*) FROM reservations WHERE type = 'rdv' AND id != ? AND debut < ? AND fin > ?
        AND (statut = 'confirmee' OR (statut = 'attente' AND expire_le > ?))");
    $s->execute([$saufReservation ?? 0, $apres, $avant, horodatage()]);
    if ((int) $s->fetchColumn() > 0) return true;
    $s = db()->prepare("SELECT COUNT(*) FROM seances_groupe sg JOIN groupes g ON g.id = sg.groupe_id WHERE g.statut = 'publie' AND sg.debut < ? AND sg.fin > ?");
    $s->execute([$apres, $avant]);
    if ((int) $s->fetchColumn() > 0) return true;
    $s = db()->prepare('SELECT COUNT(*) FROM absences WHERE debut < ? AND fin > ?');
    $s->execute([$fin, $debut]);
    return (int) $s->fetchColumn() > 0;
}

// Créneaux libres d'une activité individuelle pour un jour (AAAA-MM-JJ)
function creneauxDuJour(array $activite, string $jour): array
{
    $d = new DateTimeImmutable($jour);
    $min = maintenant()->modify('+' . paramInt('delai_min_heures') . ' hours');
    $max = maintenant()->modify('+' . paramInt('horizon_jours') . ' days');
    $duree = (int) $activite['duree_minutes'];
    $pas = $duree + paramInt('battement_minutes');
    $s = db()->prepare('SELECT * FROM disponibilites WHERE jour = ? ORDER BY debut');
    $s->execute([(int) $d->format('N')]);
    $libres = [];
    foreach ($s->fetchAll() as $dispo) {
        $codes = array_filter(array_map('trim', explode(',', $dispo['activites'])));
        if ($codes && !in_array($activite['code'], $codes, true)) continue;
        $debut = new DateTimeImmutable("$jour {$dispo['debut']}");
        $limite = new DateTimeImmutable("$jour {$dispo['fin']}");
        for (; $debut->modify("+$duree minutes") <= $limite; $debut = $debut->modify("+$pas minutes")) {
            $fin = $debut->modify("+$duree minutes");
            if ($debut < $min || $debut > $max) continue;
            if (occupe(horodatage($debut), horodatage($fin))) continue;
            $libres[] = ['debut' => horodatage($debut), 'fin' => horodatage($fin), 'lieu' => $dispo['lieu']];
        }
    }
    usort($libres, fn ($a, $b) => strcmp($a['debut'], $b['debut']));
    return $libres;
}

// Jours à venir avec leurs créneaux (pour l'agenda public)
function creneauxAVenir(array $activite, int $nbJours = 21): array
{
    $jours = [];
    $d = maintenant()->setTime(0, 0);
    $nbJours = min($nbJours, paramInt('horizon_jours') + 1);
    for ($i = 0; $i <= $nbJours; $i++) {
        $jour = $d->modify("+$i days")->format('Y-m-d');
        $jours[] = ['jour' => $jour, 'creneaux' => creneauxDuJour($activite, $jour)];
    }
    return $jours;
}

function creneauEstLibre(array $activite, string $debut): ?array
{
    foreach (creneauxDuJour($activite, substr($debut, 0, 10)) as $c) if ($c['debut'] === $debut) return $c;
    return null;
}
