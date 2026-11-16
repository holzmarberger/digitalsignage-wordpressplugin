<?php
use Marberger\Signage\Core\OpeningCalendar;
use Marberger\Signage\Core\TimeBlocks;
use PHPUnit\Framework\TestCase;

final class OpeningCalendarTest extends TestCase
{
    private \DateTimeZone $tz;

    protected function setUp(): void
    {
        $this->tz = new \DateTimeZone('Europe/Vienna');
    }

    public static function config(array $over = []): array
    {
        $mt = [['07:00', '12:00'], ['13:00', '17:00']];
        return array_merge([
            'weekly' => [1 => $mt, 2 => $mt, 3 => $mt, 4 => $mt, 5 => [['07:00', '12:00']], 6 => [], 7 => []],
            'calendar' => 'AT',
            'holiday_open' => [],
            'exceptions' => [],
            'closures' => [[
                'id' => 1, 'from' => '2026-12-24', 'to' => '2027-01-06',
                'label_de' => 'Betriebsurlaub', 'label_en' => 'Company holidays',
                'text_de' => '', 'text_en' => '', 'image' => '', 'announce_days' => 14,
            ]],
            'min_gap' => 15,
        ], $over);
    }

    private function cal(array $over = []): OpeningCalendar
    {
        return new OpeningCalendar(self::config($over), $this->tz);
    }

    private function t(string $s): \DateTimeImmutable
    {
        return new \DateTimeImmutable($s, $this->tz);
    }

    private function iso(?\DateTimeImmutable $d): ?string
    {
        return $d ? $d->format('Y-m-d H:i') : null;
    }

    // --- TimeBlocks ---------------------------------------------------------

    public function test_parse_normal_and_lenient_input(): void
    {
        $this->assertSame([['07:00', '12:00'], ['13:00', '17:00']], TimeBlocks::parse('07:00-12:00, 13:00-17:00')['blocks']);
        $this->assertSame([['07:00', '12:00'], ['13:00', '17:00']], TimeBlocks::parse('7-12, 13–17')['blocks']);
        $this->assertSame([['07:30', '12:00']], TimeBlocks::parse('7.30 - 12')['blocks']);
        $this->assertSame(['blocks' => [], 'errors' => []], TimeBlocks::parse('  '));
    }

    public function test_parse_errors(): void
    {
        $this->assertNotEmpty(TimeBlocks::parse('12-7')['errors']);
        $this->assertNotEmpty(TimeBlocks::parse('07:00-12:00, 11:00-13:00')['errors']);
        $this->assertNotEmpty(TimeBlocks::parse('abc')['errors']);
        $this->assertNotEmpty(TimeBlocks::parse('25:00-26:00')['errors']);
    }

    public function test_format_blocks(): void
    {
        $this->assertSame('07:00–12:00, 13:00–17:00', TimeBlocks::format([['07:00', '12:00'], ['13:00', '17:00']]));
    }

    // --- Status -------------------------------------------------------------

    public function test_open_pause_after_hours(): void
    {
        $c = $this->cal();
        $s = $c->status($this->t('2026-11-17 10:00'));
        $this->assertSame('open', $s['state']);
        $this->assertSame('2026-11-17 12:00', $this->iso($s['block_end']));
        $this->assertFalse($s['last_block']);

        $s = $c->status($this->t('2026-11-17 12:30'));
        $this->assertSame('pause', $s['state']);
        $this->assertSame('2026-11-17 13:00', $this->iso($s['next_open']));
        $this->assertSame('Mittagspause', $s['reason_de']);

        $s = $c->status($this->t('2026-11-17 17:30'));
        $this->assertSame('after_hours', $s['state']);
        $this->assertSame('2026-11-18 07:00', $this->iso($s['next_open']));

        $s = $c->status($this->t('2026-11-17 06:30'));
        $this->assertSame('after_hours', $s['state']);
        $this->assertSame('2026-11-17 07:00', $this->iso($s['next_open']));
    }

    public function test_block_end_is_exclusive(): void
    {
        $s = $this->cal()->status($this->t('2026-11-17 12:00'));
        $this->assertSame('pause', $s['state']);
    }

    public function test_weekend(): void
    {
        $s = $this->cal()->status($this->t('2026-11-21 10:00'));
        $this->assertSame('closed_day', $s['state']);
        $this->assertSame('2026-11-23 07:00', $this->iso($s['next_open']));
    }

    public function test_holiday_and_override(): void
    {
        $s = $this->cal()->status($this->t('2026-12-08 10:00'));
        $this->assertSame('closed_day', $s['state']);
        $this->assertSame('Mariä Empfängnis', $s['reason_de']);
        $this->assertSame('holiday', $this->cal()->day($this->t('2026-12-08'))['kind']);

        $s = $this->cal(['holiday_open' => ['mariae_empfaengnis']])->status($this->t('2026-12-08 10:00'));
        $this->assertSame('open', $s['state']);
    }

    public function test_closure_joins_holidays_and_weekend(): void
    {
        $c = $this->cal();
        $s = $c->status($this->t('2026-12-24 10:00'));
        $this->assertSame('closure', $s['state']);
        $this->assertSame('Betriebsurlaub', $s['reason_de']);
        $this->assertSame('2027-01-07 07:00', $this->iso($s['next_open']));
        $this->assertSame(1, $s['closure']['id']);

        // Abend vor der Schließzeit: nächste Öffnung erst nach dem Urlaub
        $s = $c->status($this->t('2026-12-23 15:00'));
        $this->assertSame('open', $s['state']);
        $s = $c->status($this->t('2026-12-23 18:00'));
        $this->assertSame('after_hours', $s['state']);
        $this->assertSame('2027-01-07 07:00', $this->iso($s['next_open']));
    }

    public function test_exceptions(): void
    {
        $c = $this->cal(['exceptions' => [
            ['date' => '2026-12-23', 'closed' => false, 'blocks' => [['07:00', '12:00']], 'label' => 'Kurzer Tag', 'announce_days' => 3],
            ['date' => '2026-11-27', 'closed' => true, 'blocks' => [], 'label' => 'Inventur', 'announce_days' => 0],
        ]]);
        $this->assertSame('after_hours', $c->status($this->t('2026-12-23 13:30'))['state']);
        $s = $c->status($this->t('2026-11-27 09:00'));
        $this->assertSame('closed_day', $s['state']);
        $this->assertSame('Inventur', $s['reason_de']);
        $this->assertSame('2026-11-30 07:00', $this->iso($s['next_open']));
    }

    public function test_short_gaps_are_merged(): void
    {
        $c = $this->cal(['weekly' => [1 => [['07:00', '12:00'], ['12:10', '17:00']]]]);
        $day = $c->day($this->t('2026-11-16'));
        $this->assertCount(1, $day['blocks']);
        $this->assertSame('open', $c->status($this->t('2026-11-16 12:05'))['state']);
    }

    public function test_no_opening_hours_at_all(): void
    {
        $c = $this->cal(['weekly' => [], 'closures' => []]);
        $s = $c->status($this->t('2026-11-17 10:00'));
        $this->assertSame('closed_day', $s['state']);
        $this->assertNull($s['next_open']);
    }

    public function test_dst_change(): void
    {
        // 25.10.2026 Zeitumstellung, 26.10. Nationalfeiertag
        $next = $this->cal()->nextOpening($this->t('2026-10-24 10:00'));
        $this->assertSame('2026-10-27T07:00:00+01:00', $next->format('c'));
        $before = $this->cal()->nextOpening($this->t('2026-10-23 17:30'));
        $this->assertSame('2026-10-27T07:00:00+01:00', $before->format('c'));
    }

    // --- Hilfsabfragen ------------------------------------------------------

    public function test_closing_time_and_open_day(): void
    {
        $c = $this->cal();
        $this->assertSame('2026-11-20 12:00', $this->iso($c->closingTime($this->t('2026-11-20'))));
        $this->assertSame('2026-11-17 17:00', $this->iso($c->closingTime($this->t('2026-11-17'))));
        $this->assertNull($c->closingTime($this->t('2026-11-21')));
        $this->assertFalse($c->isOpenDay($this->t('2026-11-21')));
        $this->assertTrue($c->isOpenDay($this->t('2026-11-20')));
        $this->assertFalse($c->isOpenDay($this->t('2026-12-28')));
    }

    public function test_warning(): void
    {
        $c = $this->cal();
        $w = $c->warning($this->t('2026-11-20 11:50'), 15);
        $this->assertSame('closing_soon', $w['type']);
        $this->assertSame('2026-11-20 12:00', $this->iso($w['at']));

        $w = $c->warning($this->t('2026-11-17 11:50'), 15);
        $this->assertSame('pause_soon', $w['type']);
        $this->assertSame('2026-11-17 13:00', $this->iso($w['until']));

        $this->assertNull($c->warning($this->t('2026-11-17 11:40'), 15));
        $this->assertNull($c->warning($this->t('2026-11-17 12:30'), 15));
        $this->assertSame('closing_soon', $c->warning($this->t('2026-11-17 16:50'), 15)['type']);
    }

    public function test_announcements(): void
    {
        $c = $this->cal(['exceptions' => [
            ['date' => '2026-12-23', 'closed' => false, 'blocks' => [['07:00', '12:00']], 'label' => 'Kurzer Tag', 'announce_days' => 3],
        ]]);
        $a = $c->announcements($this->t('2026-12-10 08:00'));
        $this->assertCount(1, $a);
        $this->assertSame('closure', $a[0]['type']);
        $this->assertSame('2027-01-07 07:00', $this->iso($a[0]['next_open']));
        $this->assertSame([], $c->announcements($this->t('2026-12-09 23:00')));

        $a = $c->announcements($this->t('2026-12-21 08:00'));
        $this->assertSame(['exception', 'closure'], array_column($a, 'type'));
        $this->assertSame([], $c->announcements($this->t('2026-12-24 08:00')));
    }

    public function test_week_table(): void
    {
        $rows = $this->cal()->weekTable();
        $this->assertSame('Montag – Donnerstag', $rows[0]['days_de']);
        $this->assertSame('07:00–12:00, 13:00–17:00', $rows[0]['times']);
        $this->assertSame('Freitag', $rows[1]['days_de']);
        $this->assertSame('Samstag, Sonntag', $rows[2]['days_de']);
        $this->assertSame('', $rows[2]['times']);
        $this->assertSame('Saturday, Sunday', $rows[2]['days_en']);
    }
}
