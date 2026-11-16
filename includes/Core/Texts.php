<?php
namespace Marberger\Signage\Core;

/**
 * Formulierungen für „wieder für Sie da“, Vorwarnungen und Ankündigungen (DE/EN, österreichische Monatsnamen).
 */
final class Texts
{
    public const WEEKDAYS_DE = [1 => 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag', 'Sonntag'];
    public const WEEKDAYS_EN = [1 => 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
    public const MONTHS_DE = [1 => 'Jänner', 'Februar', 'März', 'April', 'Mai', 'Juni', 'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
    public const MONTHS_EN = [1 => 'January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];

    /** „heute ab 13:00 Uhr“ / „today from 13:00“ … (klein geschrieben, ohne Satzende) */
    public static function when(\DateTimeImmutable $now, \DateTimeImmutable $next, string $lang): string
    {
        $now = $now->setTimezone($next->getTimezone());
        $days = (int) (new \DateTimeImmutable($now->format('Y-m-d'), $next->getTimezone()))
            ->diff(new \DateTimeImmutable($next->format('Y-m-d'), $next->getTimezone()))->format('%r%a');
        $time = $next->format('H:i');
        $wd = (int) $next->format('N');
        $de = $lang !== 'en';
        if ($days <= 0) {
            return $de ? "heute ab $time Uhr" : "today from $time";
        }
        if ($days === 1) {
            return $de ? "morgen ab $time Uhr" : "tomorrow from $time";
        }
        if ($days <= 6) {
            return $de ? 'am ' . self::WEEKDAYS_DE[$wd] . " ab $time Uhr" : 'on ' . self::WEEKDAYS_EN[$wd] . " from $time";
        }
        return $de
            ? 'am ' . self::WEEKDAYS_DE[$wd] . ', ' . self::dateLong($next, 'de') . ", ab $time Uhr"
            : 'on ' . self::WEEKDAYS_EN[$wd] . ', ' . self::dateLong($next, 'en') . ", from $time";
    }

    /** Ganzer Satz „Morgen ab 07:00 Uhr sind wir wieder für Sie da.“ */
    public static function backAt(\DateTimeImmutable $now, ?\DateTimeImmutable $next, string $lang): string
    {
        if ($next === null) {
            return $lang === 'en' ? 'You can find our opening hours at holz-marberger.at.' : 'Unsere Öffnungszeiten finden Sie auf holz-marberger.at.';
        }
        return $lang === 'en'
            ? 'We will be back ' . self::when($now, $next, 'en') . '.'
            : self::ucfirst(self::when($now, $next, 'de')) . ' sind wir wieder für Sie da.';
    }

    /** Balken in der Pause. */
    public static function pause(\DateTimeImmutable $now, ?\DateTimeImmutable $next, string $lang): string
    {
        $back = self::backAt($now, $next, $lang);
        if ($lang === 'en') {
            return 'Lunch break – ' . lcfirst($back);
        }
        return 'Mittagspause – ' . ($next ? self::when($now, $next, 'de') . ' sind wir wieder für Sie da.' : $back);
    }

    /** Balken, wenn „geschlossen“ nur als Balken angezeigt wird. */
    public static function closedBanner(\DateTimeImmutable $now, ?\DateTimeImmutable $next, string $lang): string
    {
        $back = self::backAt($now, $next, $lang);
        return $lang === 'en' ? 'We are closed – ' . lcfirst($back) : 'Wir haben geschlossen – ' . self::lcfirstDe($back);
    }

    /** @param array{type:string, at:\DateTimeImmutable, until:?\DateTimeImmutable} $w */
    public static function warning(array $w, string $lang): string
    {
        $at = $w['at']->format('H:i');
        if ($w['type'] === 'pause_soon') {
            $until = $w['until'] ? $w['until']->format('H:i') : '';
            if ($lang === 'en') {
                return $until ? "Lunch break from $at to $until." : "Lunch break from $at.";
            }
            return $until ? "Mittagspause von $at bis $until Uhr." : "Mittagspause ab $at Uhr.";
        }
        return $lang === 'en' ? "We close today at $at." : "Wir schließen heute um $at Uhr.";
    }

    /** Text einer Ankündigung (Schließzeit oder Ausnahme). */
    public static function announcement(array $a, \DateTimeImmutable $now, string $lang): string
    {
        $tz = $now->getTimezone();
        $en = $lang === 'en';
        if ($a['type'] === 'closure') {
            $from = new \DateTimeImmutable($a['from'] . ' 12:00', $tz);
            $to = new \DateTimeImmutable($a['to'] . ' 12:00', $tz);
            $label = $en ? $a['label_en'] : $a['label_de'];
            $range = $en
                ? $from->format('j') . ' ' . substr(self::MONTHS_EN[(int) $from->format('n')], 0, 3) . ' – ' . $to->format('j') . ' ' . substr(self::MONTHS_EN[(int) $to->format('n')], 0, 3)
                : $from->format('j.n.') . '–' . $to->format('j.n.');
            $text = trim($label . ' ' . $range);
            if (!empty($a['next_open'])) {
                $text .= $en ? ' · back ' . self::when($now, $a['next_open'], 'en') . '.' : ' · ' . self::when($now, $a['next_open'], 'de') . ' wieder für Sie da.';
            }
            return $text;
        }
        $d = new \DateTimeImmutable($a['date'] . ' 12:00', $tz);
        $wd = (int) $d->format('N');
        $day = $en ? 'on ' . self::WEEKDAYS_EN[$wd] . ', ' . self::dateLong($d, 'en') : 'am ' . self::WEEKDAYS_DE[$wd] . ', ' . self::dateLong($d, 'de');
        if ($a['closed']) {
            if ($a['label'] === '') {
                return $en ? "We are closed $day." : self::ucfirst($day) . ' haben wir geschlossen.';
            }
            return $en ? "{$a['label']}: closed $day." : "{$a['label']}: $day geschlossen.";
        }
        $label = $a['label'] !== '' ? $a['label'] : ($en ? 'Changed opening hours' : 'Geänderte Öffnungszeiten');
        $times = TimeBlocks::format($a['blocks']);
        return $en ? "$label: open $day, $times." : "$label: $day geöffnet $times Uhr.";
    }

    /** „7. Jänner“ / „7 January“ */
    public static function dateLong(\DateTimeImmutable $d, string $lang): string
    {
        $m = (int) $d->format('n');
        return $lang === 'en' ? $d->format('j') . ' ' . self::MONTHS_EN[$m] : $d->format('j') . '. ' . self::MONTHS_DE[$m];
    }

    private static function ucfirst(string $s): string
    {
        return mb_strtoupper(mb_substr($s, 0, 1)) . mb_substr($s, 1);
    }

    /** Nur „Heute/Morgen/Am“ am Satzanfang klein schreiben. */
    private static function lcfirstDe(string $s): string
    {
        foreach (['Heute ' => 'heute ', 'Morgen ' => 'morgen ', 'Am ' => 'am '] as $from => $to) {
            if (str_starts_with($s, $from)) {
                return $to . substr($s, strlen($from));
            }
        }
        return $s;
    }
}
