<?php
use Marberger\Signage\Core\Holidays;
use PHPUnit\Framework\TestCase;

final class HolidaysTest extends TestCase
{
    public function test_easter(): void
    {
        $this->assertSame('2026-04-05', Holidays::easter(2026));
        $this->assertSame('2027-03-28', Holidays::easter(2027));
        $this->assertSame('2024-03-31', Holidays::easter(2024));
    }

    public function test_austria_2026(): void
    {
        $h = Holidays::forYear('AT', 2026);
        $this->assertSame('mariae_empfaengnis', $h['2026-12-08']['key']);
        $this->assertSame('Nationalfeiertag', $h['2026-10-26']['de']);
        $this->assertArrayHasKey('2026-04-06', $h); // Ostermontag
        $this->assertArrayHasKey('2026-06-04', $h); // Fronleichnam
        $this->assertArrayNotHasKey('2026-04-03', $h); // Karfreitag kein Feiertag in AT
        $this->assertArrayNotHasKey('2026-12-24', $h);
        $this->assertCount(13, $h);
    }

    public function test_bavaria_2026(): void
    {
        $h = Holidays::forYear('BY', 2026);
        $this->assertSame('karfreitag', $h['2026-04-03']['key']);
        $this->assertArrayHasKey('2026-10-03', $h);
        $this->assertArrayHasKey('2026-08-15', $h);
        $this->assertArrayNotHasKey('2026-10-26', $h);
        $this->assertArrayNotHasKey('2026-12-08', $h);
    }

    public function test_keys_lists_names(): void
    {
        $keys = Holidays::keys('AT');
        $this->assertSame('Mariä Empfängnis', $keys['mariae_empfaengnis']);
    }

    public function test_unknown_calendar_is_empty(): void
    {
        $this->assertSame([], Holidays::forYear('XX', 2026));
    }
}
