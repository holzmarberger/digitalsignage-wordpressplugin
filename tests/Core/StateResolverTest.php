<?php
use Marberger\Signage\Core\OpeningCalendar;
use Marberger\Signage\Core\Settings;
use Marberger\Signage\Core\StateResolver;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/OpeningCalendarTest.php';

final class StateResolverTest extends TestCase
{
    private function t(string $s): \DateTimeImmutable
    {
        return new \DateTimeImmutable($s, new \DateTimeZone('Europe/Vienna'));
    }

    private function resolver(string $type, array $over = []): StateResolver
    {
        $cal = new OpeningCalendar(OpeningCalendarTest::config(), new \DateTimeZone('Europe/Vienna'));
        return new StateResolver($cal, array_merge(Settings::defaults($type), $over));
    }

    private function notice(int $prio, string $text): array
    {
        return ['id' => $text, 'text_de' => $text, 'priority' => $prio, 'rule' => ['type' => 'weekly', 'days' => [1, 2, 3, 4, 5, 6, 7], 'from' => '00:00', 'to' => '23:59']];
    }

    public function test_after_hours_reception_vs_info(): void
    {
        $r = $this->resolver('reception')->resolve($this->t('2026-11-17 18:00'));
        $this->assertSame('closed', $r['rank']);
        $this->assertSame('Morgen ab 07:00 Uhr sind wir wieder für Sie da.', $r['closed']['back_de']);

        $r = $this->resolver('info')->resolve($this->t('2026-11-17 18:00'));
        $this->assertSame('standard', $r['rank']);
        $this->assertSame([], $r['banners']);
    }

    public function test_pause_is_banner_for_reception(): void
    {
        $r = $this->resolver('reception')->resolve($this->t('2026-11-17 12:30'));
        $this->assertSame('standard', $r['rank']);
        $this->assertSame('pause', $r['banners'][0]['type']);
        $this->assertSame('Mittagspause – heute ab 13:00 Uhr sind wir wieder für Sie da.', $r['banners'][0]['text_de']);
    }

    public function test_closed_as_banner_when_configured(): void
    {
        $r = $this->resolver('reception', ['closed_mode' => 'banner'])->resolve($this->t('2026-11-17 18:00'));
        $this->assertSame('standard', $r['rank']);
        $this->assertSame('closed', $r['banners'][0]['type']);
    }

    public function test_closure_rank(): void
    {
        $r = $this->resolver('reception')->resolve($this->t('2026-12-28 10:00'));
        $this->assertSame('closure', $r['rank']);
        $this->assertSame('Betriebsurlaub', $r['closed']['closure']['label_de']);
        $r = $this->resolver('info')->resolve($this->t('2026-12-28 10:00'));
        $this->assertSame('standard', $r['rank']);
    }

    public function test_greeting_beats_closed(): void
    {
        $g = ['id' => 7, 'name' => 'Vortrag', 'rule' => ['type' => 'once', 'from' => '2026-11-17 18:30', 'to' => '2026-11-17 21:00']];
        $r = $this->resolver('reception')->resolve($this->t('2026-11-17 19:00'), ['greetings' => [$g]]);
        $this->assertSame('greeting', $r['rank']);
        $this->assertSame(7, $r['greeting']['id']);
        // Info-Displays zeigen keine Grüße
        $r = $this->resolver('info')->resolve($this->t('2026-11-17 19:00'), ['greetings' => [$g]]);
        $this->assertNull($r['greeting']);
    }

    public function test_fullpage_and_below(): void
    {
        $fp = ['id' => 1, 'rule' => ['type' => 'weekly', 'days' => [1, 2, 3, 4, 5], 'from' => '06:55', 'to' => '07:10', 'open_days_only' => true]];
        $r = $this->resolver('reception')->resolve($this->t('2026-11-17 06:58'), ['fullpages' => [$fp]]);
        $this->assertSame('fullpage', $r['rank']);
        $this->assertSame('closed', $r['below']);
        $this->assertSame('Heute ab 07:00 Uhr sind wir wieder für Sie da.', $r['closed']['back_de']);

        $r = $this->resolver('reception')->resolve($this->t('2026-11-17 07:05'), ['fullpages' => [$fp]]);
        $this->assertSame('standard', $r['below']);

        // Feiertag: Vollbild-Seite mit „nur an Öffnungstagen“ inaktiv
        $r = $this->resolver('reception')->resolve($this->t('2026-12-08 07:00'), ['fullpages' => [$fp]]);
        $this->assertSame('closed', $r['rank']);
    }

    public function test_max_three_banners_in_order(): void
    {
        $content = ['notices' => [$this->notice(1, 'B'), $this->notice(5, 'A'), $this->notice(0, 'C')]];
        $r = $this->resolver('reception')->resolve($this->t('2026-11-17 12:30'), $content);
        $this->assertCount(3, $r['banners']);
        $this->assertSame(['pause', 'notice', 'notice'], array_column($r['banners'], 'type'));
        $this->assertSame(['A', 'B'], array_slice(array_column($r['banners'], 'text_de'), 1));
    }

    public function test_warning_banner_and_announcements(): void
    {
        $r = $this->resolver('reception')->resolve($this->t('2026-12-11 11:50')); // Freitag, Ankündigung läuft
        $this->assertSame(['warning', 'announcement'], array_column($r['banners'], 'type'));
        $this->assertSame('Wir schließen heute um 12:00 Uhr.', $r['banners'][0]['text_de']);

        $r = $this->resolver('info')->resolve($this->t('2026-12-11 11:50'));
        $this->assertSame([], $r['banners']);
        $this->assertCount(1, $r['ticker']);
    }

    public function test_settings_merge(): void
    {
        $merged = Settings::merge(['type' => 'reception', 'idle' => '60'], ['idle' => '', 'poll' => '5', 'pause_mode' => 'fullscreen', 'news' => 'inherit']);
        $this->assertSame(60, $merged['idle']);
        $this->assertSame(15, $merged['poll']); // Minimum
        $this->assertSame('fullscreen', $merged['pause_mode']);
        $this->assertFalse($merged['news']);
        $this->assertSame('info', Settings::sanitize(['type' => 'info'])['type']);
    }
}
