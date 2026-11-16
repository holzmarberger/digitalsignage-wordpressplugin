<?php
namespace Marberger\Signage\Core;

/**
 * Einheitliche Zeitregeln für Grüße, Hinweise, Vollbild-Seiten und Abfolge-Einträge.
 *
 * once:   {type:'once', from:'Y-m-d H:i', to:'Y-m-d H:i', daily?:bool}
 *         daily = mehrtägig, jeweils nur im Tagesfenster time(from)–time(to)
 * weekly: {type:'weekly', days:list<1..7>, from:'H:i', to:'H:i'|'close', valid_from?:'Y-m-d', valid_to?:'Y-m-d', open_days_only?:bool}
 *         to < from → Fenster über Mitternacht; 'close' → bis Geschäftsschluss (braucht Kalender)
 */
final class TimeRule
{
    public static function isActive(array $rule, \DateTimeImmutable $t, ?OpeningCalendar $cal = null): bool
    {
        $tz = $cal ? $cal->timezone() : $t->getTimezone();
        $t = $t->setTimezone($tz);
        $type = $rule['type'] ?? '';

        if ($type === 'once') {
            $from = self::dt($rule['from'] ?? '', $tz);
            $to = self::dt($rule['to'] ?? '', $tz);
            if (!$from || !$to) {
                return false;
            }
            if (empty($rule['daily'])) {
                return $t >= $from && $t < $to;
            }
            $firstDay = $from->format('Y-m-d');
            $lastDay = $to->format('Y-m-d');
            foreach ([$t, $t->modify('-1 day')] as $cand) {
                $day = $cand->format('Y-m-d');
                if ($day < $firstDay || $day > $lastDay) {
                    continue;
                }
                [$ws, $we] = self::window($day, $from->format('H:i'), $to->format('H:i'), $tz);
                if ($t >= $ws && $t < $we) {
                    return true;
                }
            }
            return false;
        }

        if ($type === 'weekly') {
            $days = array_map('intval', $rule['days'] ?? []);
            $fromT = TimeBlocks::time((string) ($rule['from'] ?? ''));
            if (!$days || $fromT === null) {
                return false;
            }
            foreach ([$t, $t->modify('-1 day')] as $cand) {
                $day = $cand->format('Y-m-d');
                if (!in_array((int) $cand->format('N'), $days, true)) {
                    continue;
                }
                if (!empty($rule['valid_from']) && $day < $rule['valid_from']) {
                    continue;
                }
                if (!empty($rule['valid_to']) && $day > $rule['valid_to']) {
                    continue;
                }
                $dayStart = new \DateTimeImmutable($day . ' 12:00', $tz);
                if (!empty($rule['open_days_only']) && $cal && !$cal->isOpenDay($dayStart)) {
                    continue;
                }
                if (($rule['to'] ?? '') === 'close') {
                    $end = $cal ? $cal->closingTime($dayStart) : null;
                    if (!$end) {
                        continue;
                    }
                    $start = new \DateTimeImmutable($day . ' ' . $fromT, $tz);
                } else {
                    $toT = TimeBlocks::time((string) ($rule['to'] ?? ''));
                    if ($toT === null) {
                        continue;
                    }
                    [$start, $end] = self::window($day, $fromT, $toT, $tz);
                }
                if ($t >= $start && $t < $end) {
                    return true;
                }
            }
            return false;
        }
        return false;
    }

    /** @return list<string> Fehlermeldungen (leer = gültig) */
    public static function validate(array $rule): array
    {
        $errors = [];
        $type = $rule['type'] ?? '';
        if ($type === 'once') {
            $tz = new \DateTimeZone('UTC');
            $from = self::dt($rule['from'] ?? '', $tz);
            $to = self::dt($rule['to'] ?? '', $tz);
            if (!$from || !$to) {
                return ['Bitte Beginn und Ende vollständig angeben.'];
            }
            if (empty($rule['daily']) && $to <= $from) {
                $errors[] = 'Das Ende liegt vor dem Beginn.';
            }
            if (!empty($rule['daily']) && $to->format('Y-m-d') < $from->format('Y-m-d')) {
                $errors[] = 'Der letzte Tag liegt vor dem ersten Tag.';
            }
            return $errors;
        }
        if ($type === 'weekly') {
            if (empty($rule['days'])) {
                $errors[] = 'Bitte mindestens einen Wochentag wählen.';
            }
            if (TimeBlocks::time((string) ($rule['from'] ?? '')) === null) {
                $errors[] = 'Ungültige Startzeit.';
            }
            if (($rule['to'] ?? '') !== 'close' && TimeBlocks::time((string) ($rule['to'] ?? '')) === null) {
                $errors[] = 'Ungültige Endzeit.';
            }
            if (!empty($rule['valid_from']) && !empty($rule['valid_to']) && $rule['valid_to'] < $rule['valid_from']) {
                $errors[] = '„Gültig bis“ liegt vor „gültig ab“.';
            }
            return $errors;
        }
        return ['Unbekannte Zeitregel.'];
    }

    private static function dt(string $s, \DateTimeZone $tz): ?\DateTimeImmutable
    {
        $d = \DateTimeImmutable::createFromFormat('Y-m-d H:i', trim($s), $tz);
        return $d ?: null;
    }

    /** @return array{0:\DateTimeImmutable,1:\DateTimeImmutable} */
    private static function window(string $day, string $from, string $to, \DateTimeZone $tz): array
    {
        $start = new \DateTimeImmutable($day . ' ' . $from, $tz);
        $end = new \DateTimeImmutable($day . ' ' . ($to === '24:00' ? '00:00' : $to), $tz);
        if ($end <= $start) {
            $end = $end->modify('+1 day');
        }
        return [$start, $end];
    }
}
