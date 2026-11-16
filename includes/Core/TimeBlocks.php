<?php
namespace Marberger\Signage\Core;

/**
 * Parsen und Formatieren von Öffnungsblöcken in Freitext, z. B. „07:00-12:00, 13:00-17:00“ oder „7-12, 13–17“.
 */
final class TimeBlocks
{
    /**
     * @return array{blocks: list<array{0:string,1:string}>, errors: list<string>}
     */
    public static function parse(string $input): array
    {
        $input = trim(str_replace(['–', '—', ' bis '], '-', $input));
        if ($input === '') {
            return ['blocks' => [], 'errors' => []];
        }
        $blocks = [];
        $errors = [];
        foreach (preg_split('/[,;\/]+/', $input) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }
            if (!preg_match('/^(\d{1,2}(?:[:.]\d{2})?)\s*-\s*(\d{1,2}(?:[:.]\d{2})?)$/', $part, $m)) {
                $errors[] = sprintf('„%s“ ist keine gültige Zeitspanne (Beispiel: 07:00-12:00).', $part);
                continue;
            }
            $from = self::time($m[1]);
            $to = self::time($m[2]);
            if ($from === null || $to === null) {
                $errors[] = sprintf('„%s“ enthält eine ungültige Uhrzeit.', $part);
                continue;
            }
            if ($to <= $from) {
                $errors[] = sprintf('„%s“: Das Ende liegt vor dem Beginn.', $part);
                continue;
            }
            $blocks[] = [$from, $to];
        }
        usort($blocks, static fn ($a, $b) => strcmp($a[0], $b[0]));
        for ($i = 1, $n = count($blocks); $i < $n; $i++) {
            if ($blocks[$i][0] < $blocks[$i - 1][1]) {
                $errors[] = sprintf('Die Zeiten %s und %s überschneiden sich.', self::format([$blocks[$i - 1]]), self::format([$blocks[$i]]));
            }
        }
        return ['blocks' => $errors ? [] : $blocks, 'errors' => $errors];
    }

    /** Normalisiert eine Uhrzeit auf HH:MM, null bei ungültigen Werten. */
    public static function time(string $t): ?string
    {
        $t = trim(str_replace('.', ':', $t));
        if (!preg_match('/^(\d{1,2})(?::(\d{2}))?$/', $t, $m)) {
            return null;
        }
        $h = (int) $m[1];
        $min = isset($m[2]) ? (int) $m[2] : 0;
        if ($h > 24 || $min > 59 || ($h === 24 && $min > 0)) {
            return null;
        }
        return sprintf('%02d:%02d', $h, $min);
    }

    /** @param list<array{0:string,1:string}> $blocks */
    public static function format(array $blocks): string
    {
        return implode(', ', array_map(static fn ($b) => $b[0] . '–' . $b[1], $blocks));
    }
}
