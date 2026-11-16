<?php
namespace Marberger\Signage\Core;

/**
 * Ermittelt, was ein Display zu einem Zeitpunkt zeigt (Konzept Abschnitt 6).
 *
 * Ränge: fullpage > greeting > closure > closed > standard. Balken liegen darüber (max. 3).
 * $content = ['fullpages' => [...], 'greetings' => [...], 'notices' => [...]], jeder Eintrag mit
 *   'rule' (TimeRule), 'priority' (int, optional), 'modified' (int Timestamp, optional) und eigenen Daten.
 */
final class StateResolver
{
    public const MAX_BANNERS = 3;

    private OpeningCalendar $cal;
    private array $settings;

    public function __construct(OpeningCalendar $cal, array $settings)
    {
        $this->cal = $cal;
        $this->settings = $settings;
    }

    public function resolve(\DateTimeImmutable $t, array $content = []): array
    {
        $t = $t->setTimezone($this->cal->timezone());
        $status = $this->cal->status($t);
        $closed = $this->closedInfo($t, $status);

        // --- Balken ---------------------------------------------------------
        $system = [];
        if ($closed) {
            $mode = $this->modeFor($closed['kind']);
            if ($mode === 'banner') {
                $system[] = $closed['kind'] === 'pause'
                    ? $this->banner('pause', 'info', Texts::pause($t, $closed['next_open'], 'de'), Texts::pause($t, $closed['next_open'], 'en'))
                    : $this->banner('closed', 'info', Texts::closedBanner($t, $closed['next_open'], 'de'), Texts::closedBanner($t, $closed['next_open'], 'en'));
            }
        }
        if (!empty($this->settings['warn_enabled'])) {
            $w = $this->cal->warning($t, (int) ($this->settings['warn_minutes'] ?? 15));
            if ($w) {
                $system[] = $this->banner('warning', 'warn', Texts::warning($w, 'de'), Texts::warning($w, 'en'));
            }
        }
        $notices = [];
        foreach ($this->active($content['notices'] ?? [], $t) as $n) {
            $notices[] = $this->banner('notice', (string) ($n['icon'] ?? 'info'), (string) ($n['text_de'] ?? ''), (string) (($n['text_en'] ?? '') ?: ($n['text_de'] ?? '')), $n['id'] ?? null);
        }
        $announce = [];
        $ticker = [];
        $mode = $this->settings['announce_mode'] ?? 'banner';
        foreach ($this->cal->announcements($t) as $a) {
            $de = Texts::announcement($a, $t, 'de');
            $en = Texts::announcement($a, $t, 'en');
            if ($mode === 'banner') {
                $announce[] = $this->banner('announcement', 'info', $de, $en);
            } elseif ($mode === 'ticker') {
                $ticker[] = ['text_de' => $de, 'text_en' => $en];
            }
        }
        $banners = array_slice(array_merge($system, $notices, $announce), 0, self::MAX_BANNERS);

        // --- Rang -----------------------------------------------------------
        $greetings = ($this->settings['type'] ?? 'reception') === 'reception' ? $this->active($content['greetings'] ?? [], $t) : [];
        $greeting = $greetings[0] ?? null;
        $lower = $this->rankBelowFullpage($greeting, $closed);

        $fullpages = $this->active($content['fullpages'] ?? [], $t);
        $fullpage = $fullpages[0] ?? null;

        return [
            'rank' => $fullpage ? 'fullpage' : $lower,
            'below' => $fullpage ? $lower : null,
            'status' => $status,
            'closed' => $closed,
            'fullpage' => $fullpage,
            'greeting' => $greeting,
            'banners' => $banners,
            'ticker' => $ticker,
        ];
    }

    private function rankBelowFullpage(?array $greeting, ?array $closed): string
    {
        if ($greeting) {
            return 'greeting';
        }
        if ($closed && $this->modeFor($closed['kind']) === 'fullscreen') {
            return $closed['kind'] === 'closure' ? 'closure' : 'closed';
        }
        return 'standard';
    }

    private function modeFor(string $kind): string
    {
        $key = ['pause' => 'pause_mode', 'closed' => 'closed_mode', 'closure' => 'closure_mode'][$kind];
        return (string) ($this->settings[$key] ?? 'none');
    }

    private function closedInfo(\DateTimeImmutable $t, array $status): ?array
    {
        $map = ['pause' => 'pause', 'after_hours' => 'closed', 'closed_day' => 'closed', 'closure' => 'closure'];
        if (!isset($map[$status['state']])) {
            return null;
        }
        return [
            'kind' => $map[$status['state']],
            'next_open' => $status['next_open'],
            'back_de' => Texts::backAt($t, $status['next_open'], 'de'),
            'back_en' => Texts::backAt($t, $status['next_open'], 'en'),
            'reason_de' => $status['reason_de'],
            'reason_en' => $status['reason_en'],
            'closure' => $status['closure'],
        ];
    }

    /** Aktive Einträge, sortiert nach Priorität (absteigend), dann zuletzt geändert. */
    private function active(array $items, \DateTimeImmutable $t): array
    {
        $out = array_values(array_filter($items, fn ($i) => isset($i['rule']) && TimeRule::isActive($i['rule'], $t, $this->cal)));
        usort($out, static function ($a, $b) {
            return [(int) ($b['priority'] ?? 0), (int) ($b['modified'] ?? 0)] <=> [(int) ($a['priority'] ?? 0), (int) ($a['modified'] ?? 0)];
        });
        return $out;
    }

    private function banner(string $type, string $icon, string $de, string $en, $id = null): array
    {
        return ['type' => $type, 'icon' => $icon, 'text_de' => $de, 'text_en' => $en, 'id' => $id];
    }
}
