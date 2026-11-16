<?php
namespace Marberger\Signage\Core;

/**
 * Öffnungszeiten eines Standorts: Wochenblöcke, Feiertage, Ausnahmen, Schließzeiten.
 *
 * Konfiguration:
 *  weekly       array<int 1..7, list<[HH:MM, HH:MM]>>   (ISO-Wochentag, 1 = Montag)
 *  calendar     'AT' | 'BY'
 *  holiday_open list<string>  Feiertags-Schlüssel, an denen trotzdem geöffnet ist
 *  exceptions   list<{date, closed:bool, blocks, label, announce_days}>
 *  closures     list<{id, from, to, label_de, label_en, text_de, text_en, image, announce_days}>
 *  min_gap      int Minuten – kürzere Lücken zwischen Blöcken gelten nicht als Pause
 *
 * Vorrang pro Tag: Schließzeit > Ausnahme > Feiertag > Wochenplan.
 */
final class OpeningCalendar
{
    public const SEARCH_DAYS = 60;

    private array $weekly;
    private string $calendar;
    private array $holidayOpen;
    /** @var array<string, array> */
    private array $exceptions = [];
    private array $closures;
    private int $minGap;
    private \DateTimeZone $tz;
    /** @var array<string, array> */
    private array $dayCache = [];

    public function __construct(array $config, \DateTimeZone $tz)
    {
        $this->tz = $tz;
        $this->weekly = $config['weekly'] ?? [];
        $this->calendar = (string) ($config['calendar'] ?? 'AT');
        $this->holidayOpen = array_values($config['holiday_open'] ?? []);
        foreach ($config['exceptions'] ?? [] as $e) {
            if (!empty($e['date'])) {
                $this->exceptions[$e['date']] = $e;
            }
        }
        $this->closures = array_values($config['closures'] ?? []);
        usort($this->closures, static fn ($a, $b) => strcmp($a['from'], $b['from']));
        $this->minGap = max(0, (int) ($config['min_gap'] ?? 15));
    }

    public function timezone(): \DateTimeZone
    {
        return $this->tz;
    }

    /**
     * @return array{date:string, kind:string, blocks:list<array{0:\DateTimeImmutable,1:\DateTimeImmutable}>, label_de:string, label_en:string, closure:?array}
     */
    public function day(\DateTimeImmutable $d): array
    {
        $date = $d->setTimezone($this->tz)->format('Y-m-d');
        if (isset($this->dayCache[$date])) {
            return $this->dayCache[$date];
        }
        $res = ['date' => $date, 'kind' => 'open', 'blocks' => [], 'label_de' => '', 'label_en' => '', 'closure' => null];

        foreach ($this->closures as $c) {
            if ($c['from'] <= $date && $date <= $c['to']) {
                $res['kind'] = 'closure';
                $res['label_de'] = (string) ($c['label_de'] ?? '');
                $res['label_en'] = (string) (($c['label_en'] ?? '') ?: ($c['label_de'] ?? ''));
                $res['closure'] = $c;
                return $this->dayCache[$date] = $res;
            }
        }

        $blocks = null;
        if (isset($this->exceptions[$date])) {
            $e = $this->exceptions[$date];
            $res['kind'] = 'exception';
            $res['label_de'] = (string) ($e['label'] ?? '');
            $res['label_en'] = (string) ($e['label'] ?? '');
            $blocks = !empty($e['closed']) ? [] : ($e['blocks'] ?? []);
        } else {
            $holidays = Holidays::forYear($this->calendar, (int) substr($date, 0, 4));
            if (isset($holidays[$date]) && !in_array($holidays[$date]['key'], $this->holidayOpen, true)) {
                $res['kind'] = 'holiday';
                $res['label_de'] = $holidays[$date]['de'];
                $res['label_en'] = $holidays[$date]['en'];
                $blocks = [];
            }
        }
        if ($blocks === null) {
            $weekday = (int) (new \DateTimeImmutable($date, $this->tz))->format('N');
            $blocks = $this->weekly[$weekday] ?? [];
            if (!$blocks) {
                $res['kind'] = 'weekly_closed';
            }
        }
        $res['blocks'] = $this->toDateTimes($date, $blocks);
        return $this->dayCache[$date] = $res;
    }

    /**
     * @return array{state:string, block_end:?\DateTimeImmutable, last_block:bool, next_open:?\DateTimeImmutable, reason_de:string, reason_en:string, closure:?array}
     */
    public function status(\DateTimeImmutable $t): array
    {
        $t = $t->setTimezone($this->tz);
        $day = $this->day($t);
        $blocks = $day['blocks'];
        $res = ['state' => '', 'block_end' => null, 'last_block' => false, 'next_open' => null, 'reason_de' => '', 'reason_en' => '', 'closure' => null];
        $n = count($blocks);
        foreach ($blocks as $i => [$start, $end]) {
            if ($t >= $start && $t < $end) {
                $res['state'] = 'open';
                $res['block_end'] = $end;
                $res['last_block'] = $i === $n - 1;
                return $res;
            }
            if ($t < $start) {
                if ($i === 0) {
                    $res['state'] = 'after_hours';
                } else {
                    $res['state'] = 'pause';
                    $res['reason_de'] = 'Mittagspause';
                    $res['reason_en'] = 'Lunch break';
                }
                $res['next_open'] = $start;
                return $res;
            }
        }
        $res['next_open'] = $this->nextOpening($t);
        if ($day['kind'] === 'closure') {
            $res['state'] = 'closure';
            $res['closure'] = $day['closure'];
        } elseif ($n === 0) {
            $res['state'] = 'closed_day';
        } else {
            $res['state'] = 'after_hours';
        }
        if ($n === 0) {
            $res['reason_de'] = $day['label_de'];
            $res['reason_en'] = $day['label_en'];
        }
        return $res;
    }

    /** Nächster Öffnungsbeginn nach $t (max. 60 Tage voraus), null wenn keiner gefunden. */
    public function nextOpening(\DateTimeImmutable $t): ?\DateTimeImmutable
    {
        $t = $t->setTimezone($this->tz);
        $d = $t->setTime(12, 0);
        for ($i = 0; $i <= self::SEARCH_DAYS; $i++) {
            foreach ($this->day($d)['blocks'] as [$start]) {
                if ($start > $t) {
                    return $start;
                }
            }
            $d = $d->modify('+1 day');
        }
        return null;
    }

    public function isOpenDay(\DateTimeImmutable $d): bool
    {
        return (bool) $this->day($d)['blocks'];
    }

    /** Ende des letzten Öffnungsblocks des Tages („Geschäftsschluss“), null an geschlossenen Tagen. */
    public function closingTime(\DateTimeImmutable $d): ?\DateTimeImmutable
    {
        $blocks = $this->day($d)['blocks'];
        return $blocks ? $blocks[count($blocks) - 1][1] : null;
    }

    /**
     * Vorwarnung, wenn ein Öffnungsblock in höchstens $minutes Minuten endet.
     * @return array{type:'pause_soon'|'closing_soon', at:\DateTimeImmutable, until:?\DateTimeImmutable}|null
     */
    public function warning(\DateTimeImmutable $t, int $minutes): ?array
    {
        if ($minutes <= 0) {
            return null;
        }
        $s = $this->status($t);
        if ($s['state'] !== 'open') {
            return null;
        }
        $left = $s['block_end']->getTimestamp() - $t->getTimestamp();
        if ($left > $minutes * 60) {
            return null;
        }
        if ($s['last_block']) {
            return ['type' => 'closing_soon', 'at' => $s['block_end'], 'until' => null];
        }
        return ['type' => 'pause_soon', 'at' => $s['block_end'], 'until' => $this->nextOpening($s['block_end']->modify('-1 second'))];
    }

    /**
     * Anstehende Schließzeiten und Ausnahmen, deren Ankündigungszeitraum heute läuft. Sortiert nach Beginn.
     * @return list<array>
     */
    public function announcements(\DateTimeImmutable $t): array
    {
        $today = $t->setTimezone($this->tz)->format('Y-m-d');
        $out = [];
        foreach ($this->closures as $c) {
            $days = (int) ($c['announce_days'] ?? 0);
            if ($days > 0 && $c['from'] > $today && $this->shift($c['from'], -$days) <= $today) {
                $out[] = [
                    'type' => 'closure', 'start' => $c['from'], 'closure' => $c,
                    'label_de' => (string) $c['label_de'], 'label_en' => (string) (($c['label_en'] ?? '') ?: $c['label_de']),
                    'from' => $c['from'], 'to' => $c['to'],
                    'next_open' => $this->nextOpening(new \DateTimeImmutable($c['from'] . ' 00:00', $this->tz)),
                ];
            }
        }
        foreach ($this->exceptions as $date => $e) {
            $days = (int) ($e['announce_days'] ?? 0);
            if ($days > 0 && $date > $today && $this->shift($date, -$days) <= $today) {
                $out[] = [
                    'type' => 'exception', 'start' => $date, 'date' => $date,
                    'closed' => !empty($e['closed']), 'blocks' => $e['blocks'] ?? [], 'label' => (string) ($e['label'] ?? ''),
                ];
            }
        }
        usort($out, static fn ($a, $b) => strcmp($a['start'], $b['start']));
        return $out;
    }

    /**
     * Wochentabelle mit zusammengefassten gleichen, aufeinanderfolgenden Tagen.
     * @return list<array{days_de:string, days_en:string, times:string}>
     */
    public function weekTable(): array
    {
        $rows = [];
        $group = [];
        $key = null;
        for ($d = 1; $d <= 7; $d++) {
            $times = TimeBlocks::format($this->weekly[$d] ?? []);
            if ($key !== null && $times !== $key) {
                $rows[] = $this->row($group, $key);
                $group = [];
            }
            $group[] = $d;
            $key = $times;
        }
        $rows[] = $this->row($group, (string) $key);
        return $rows;
    }

    private function row(array $days, string $times): array
    {
        $de = Texts::WEEKDAYS_DE;
        $en = Texts::WEEKDAYS_EN;
        if (count($days) === 1) {
            $dDe = $de[$days[0]];
            $dEn = $en[$days[0]];
        } elseif (count($days) === 2) {
            $dDe = $de[$days[0]] . ', ' . $de[$days[1]];
            $dEn = $en[$days[0]] . ', ' . $en[$days[1]];
        } else {
            $last = $days[count($days) - 1];
            $dDe = $de[$days[0]] . ' – ' . $de[$last];
            $dEn = $en[$days[0]] . ' – ' . $en[$last];
        }
        return ['days_de' => $dDe, 'days_en' => $dEn, 'times' => $times];
    }

    /** @return list<array{0:\DateTimeImmutable,1:\DateTimeImmutable}> */
    private function toDateTimes(string $date, array $blocks): array
    {
        $out = [];
        foreach ($blocks as [$from, $to]) {
            $start = new \DateTimeImmutable($date . ' ' . $from, $this->tz);
            $end = $to === '24:00'
                ? (new \DateTimeImmutable($date . ' 00:00', $this->tz))->modify('+1 day')
                : new \DateTimeImmutable($date . ' ' . $to, $this->tz);
            if ($out && ($start->getTimestamp() - $out[count($out) - 1][1]->getTimestamp()) < $this->minGap * 60) {
                $out[count($out) - 1][1] = $end > $out[count($out) - 1][1] ? $end : $out[count($out) - 1][1];
                continue;
            }
            $out[] = [$start, $end];
        }
        return $out;
    }

    private function shift(string $date, int $days): string
    {
        return (new \DateTimeImmutable($date . ' 12:00', $this->tz))->modify(($days >= 0 ? '+' : '') . $days . ' days')->format('Y-m-d');
    }
}
