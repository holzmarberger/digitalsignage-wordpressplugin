<?php
use Marberger\Signage\Core\OpeningCalendar;
use Marberger\Signage\Core\TimeRule;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/OpeningCalendarTest.php';

final class TimeRuleTest extends TestCase
{
    private function t(string $s): \DateTimeImmutable
    {
        return new \DateTimeImmutable($s, new \DateTimeZone('Europe/Vienna'));
    }

    private function cal(): OpeningCalendar
    {
        return new OpeningCalendar(OpeningCalendarTest::config(), new \DateTimeZone('Europe/Vienna'));
    }

    public function test_once_continuous(): void
    {
        $r = ['type' => 'once', 'from' => '2026-11-20 09:00', 'to' => '2026-11-21 16:00', 'daily' => false];
        $this->assertTrue(TimeRule::isActive($r, $this->t('2026-11-20 22:00')));
        $this->assertFalse(TimeRule::isActive($r, $this->t('2026-11-20 08:59')));
        $this->assertFalse(TimeRule::isActive($r, $this->t('2026-11-21 16:00')));
    }

    public function test_once_daily_window(): void
    {
        $r = ['type' => 'once', 'from' => '2026-11-20 09:00', 'to' => '2026-11-21 16:00', 'daily' => true];
        $this->assertTrue(TimeRule::isActive($r, $this->t('2026-11-21 09:30')));
        $this->assertFalse(TimeRule::isActive($r, $this->t('2026-11-20 22:00')));
        $this->assertFalse(TimeRule::isActive($r, $this->t('2026-11-22 10:00')));
    }

    public function test_weekly_and_over_midnight(): void
    {
        $r = ['type' => 'weekly', 'days' => [1, 2, 3, 4, 5], 'from' => '06:55', 'to' => '07:10'];
        $this->assertTrue(TimeRule::isActive($r, $this->t('2026-11-17 07:00')));
        $this->assertFalse(TimeRule::isActive($r, $this->t('2026-11-21 07:00')));

        $night = ['type' => 'weekly', 'days' => [5], 'from' => '22:00', 'to' => '02:00'];
        $this->assertTrue(TimeRule::isActive($night, $this->t('2026-11-21 01:00')));  // Samstag früh, Start Freitag
        $this->assertFalse(TimeRule::isActive($night, $this->t('2026-11-22 01:00')));
    }

    public function test_weekly_valid_range(): void
    {
        $r = ['type' => 'weekly', 'days' => [2], 'from' => '08:00', 'to' => '10:00', 'valid_from' => '2026-11-20', 'valid_to' => '2026-12-31'];
        $this->assertFalse(TimeRule::isActive($r, $this->t('2026-11-17 09:00')));
        $this->assertTrue(TimeRule::isActive($r, $this->t('2026-11-24 09:00')));
    }

    public function test_until_closing_time(): void
    {
        $r = ['type' => 'weekly', 'days' => [5], 'from' => '11:00', 'to' => 'close'];
        $cal = $this->cal();
        $this->assertTrue(TimeRule::isActive($r, $this->t('2026-11-20 11:30'), $cal));
        $this->assertFalse(TimeRule::isActive($r, $this->t('2026-11-20 12:00'), $cal));
        // Freitag 25.12. ist geschlossen (Schließzeit) -> nie aktiv
        $this->assertFalse(TimeRule::isActive($r, $this->t('2026-12-25 11:30'), $cal));
        // ohne Kalender nie aktiv
        $this->assertFalse(TimeRule::isActive($r, $this->t('2026-11-20 11:30')));
    }

    public function test_open_days_only(): void
    {
        $r = ['type' => 'weekly', 'days' => [1, 2, 3, 4, 5], 'from' => '06:55', 'to' => '07:10', 'open_days_only' => true];
        $cal = $this->cal();
        $this->assertTrue(TimeRule::isActive($r, $this->t('2026-11-17 07:00'), $cal));
        $this->assertFalse(TimeRule::isActive($r, $this->t('2026-12-08 07:00'), $cal));
        $this->assertFalse(TimeRule::isActive($r, $this->t('2026-12-28 07:00'), $cal));
    }

    public function test_validate(): void
    {
        $this->assertSame([], TimeRule::validate(['type' => 'once', 'from' => '2026-11-20 09:00', 'to' => '2026-11-20 12:00']));
        $this->assertNotEmpty(TimeRule::validate(['type' => 'once', 'from' => '2026-11-20 12:00', 'to' => '2026-11-20 09:00']));
        $this->assertNotEmpty(TimeRule::validate(['type' => 'weekly', 'days' => [], 'from' => '08:00', 'to' => '09:00']));
        $this->assertNotEmpty(TimeRule::validate(['type' => 'weekly', 'days' => [1], 'from' => 'x', 'to' => '09:00']));
        $this->assertSame([], TimeRule::validate(['type' => 'weekly', 'days' => [1], 'from' => '22:00', 'to' => '02:00']));
        $this->assertNotEmpty(TimeRule::validate(['type' => 'weekly', 'days' => [1], 'from' => '08:00', 'to' => '09:00', 'valid_from' => '2026-12-01', 'valid_to' => '2026-11-01']));
    }
}
