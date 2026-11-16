<?php
namespace Marberger\Signage\Core;

/**
 * Gesetzliche Feiertage Österreich (AT) und Bayern (BY).
 * Reine Berechnung ohne WordPress; Ostern nach dem anonymen gregorianischen Algorithmus.
 */
final class Holidays
{
    /** Feste Feiertage: key => [Monat, Tag, de, en] */
    private const FIXED = [
        'AT' => [
            'neujahr'            => [1, 1, 'Neujahr', 'New Year\'s Day'],
            'heilige_drei_koenige' => [1, 6, 'Heilige Drei Könige', 'Epiphany'],
            'staatsfeiertag'     => [5, 1, 'Staatsfeiertag', 'Labour Day'],
            'mariae_himmelfahrt' => [8, 15, 'Mariä Himmelfahrt', 'Assumption Day'],
            'nationalfeiertag'   => [10, 26, 'Nationalfeiertag', 'National Day'],
            'allerheiligen'      => [11, 1, 'Allerheiligen', 'All Saints\' Day'],
            'mariae_empfaengnis' => [12, 8, 'Mariä Empfängnis', 'Immaculate Conception'],
            'christtag'          => [12, 25, 'Christtag', 'Christmas Day'],
            'stefanitag'         => [12, 26, 'Stefanitag', 'St. Stephen\'s Day'],
        ],
        'BY' => [
            'neujahr'            => [1, 1, 'Neujahr', 'New Year\'s Day'],
            'heilige_drei_koenige' => [1, 6, 'Heilige Drei Könige', 'Epiphany'],
            'tag_der_arbeit'     => [5, 1, 'Tag der Arbeit', 'Labour Day'],
            'mariae_himmelfahrt' => [8, 15, 'Mariä Himmelfahrt', 'Assumption Day'],
            'tag_der_einheit'    => [10, 3, 'Tag der Deutschen Einheit', 'German Unity Day'],
            'allerheiligen'      => [11, 1, 'Allerheiligen', 'All Saints\' Day'],
            'weihnachten_1'      => [12, 25, '1. Weihnachtstag', 'Christmas Day'],
            'weihnachten_2'      => [12, 26, '2. Weihnachtstag', 'Boxing Day'],
        ],
    ];

    /** Bewegliche Feiertage: key => [Tage relativ zu Ostersonntag, de, en] */
    private const MOVABLE = [
        'AT' => [
            'ostermontag'        => [1, 'Ostermontag', 'Easter Monday'],
            'christi_himmelfahrt' => [39, 'Christi Himmelfahrt', 'Ascension Day'],
            'pfingstmontag'      => [50, 'Pfingstmontag', 'Whit Monday'],
            'fronleichnam'       => [60, 'Fronleichnam', 'Corpus Christi'],
        ],
        'BY' => [
            'karfreitag'         => [-2, 'Karfreitag', 'Good Friday'],
            'ostermontag'        => [1, 'Ostermontag', 'Easter Monday'],
            'christi_himmelfahrt' => [39, 'Christi Himmelfahrt', 'Ascension Day'],
            'pfingstmontag'      => [50, 'Pfingstmontag', 'Whit Monday'],
            'fronleichnam'       => [60, 'Fronleichnam', 'Corpus Christi'],
        ],
    ];

    /** @var array<string, array<string, array{key:string,de:string,en:string}>> */
    private static array $cache = [];

    /** Verfügbare Kalender: code => Bezeichnung */
    public static function calendars(): array
    {
        return ['AT' => 'Österreich', 'BY' => 'Bayern'];
    }

    /** Ostersonntag als 'Y-m-d'. */
    public static function easter(int $year): string
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /**
     * Feiertage eines Jahres.
     * @return array<string, array{key:string,de:string,en:string}> sortiert nach Datum ('Y-m-d' => …)
     */
    public static function forYear(string $calendar, int $year): array
    {
        $ck = $calendar . $year;
        if (isset(self::$cache[$ck])) {
            return self::$cache[$ck];
        }
        $out = [];
        foreach (self::FIXED[$calendar] ?? [] as $key => [$m, $d, $de, $en]) {
            $out[sprintf('%04d-%02d-%02d', $year, $m, $d)] = ['key' => $key, 'de' => $de, 'en' => $en];
        }
        if (isset(self::MOVABLE[$calendar])) {
            $easter = new \DateTimeImmutable(self::easter($year) . ' 12:00:00', new \DateTimeZone('UTC'));
            foreach (self::MOVABLE[$calendar] as $key => [$offset, $de, $en]) {
                $date = $easter->modify(($offset >= 0 ? '+' : '') . $offset . ' days')->format('Y-m-d');
                $out[$date] = ['key' => $key, 'de' => $de, 'en' => $en];
            }
        }
        ksort($out);
        return self::$cache[$ck] = $out;
    }

    /** Alle Feiertags-Schlüssel eines Kalenders: key => Bezeichnung (de), in Jahresreihenfolge. */
    public static function keys(string $calendar): array
    {
        $out = [];
        foreach (self::forYear($calendar, 2026) as $h) {
            $out[$h['key']] = $h['de'];
        }
        return $out;
    }
}
