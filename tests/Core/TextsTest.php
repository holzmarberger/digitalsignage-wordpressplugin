<?php
use Marberger\Signage\Core\Texts;
use PHPUnit\Framework\TestCase;

final class TextsTest extends TestCase
{
    private function t(string $s): \DateTimeImmutable
    {
        return new \DateTimeImmutable($s, new \DateTimeZone('Europe/Vienna'));
    }

    public function test_back_at_german(): void
    {
        $now = $this->t('2026-11-17 12:30');
        $this->assertSame('Heute ab 13:00 Uhr sind wir wieder für Sie da.', Texts::backAt($now, $this->t('2026-11-17 13:00'), 'de'));
        $this->assertSame('Morgen ab 07:00 Uhr sind wir wieder für Sie da.', Texts::backAt($now, $this->t('2026-11-18 07:00'), 'de'));
        $this->assertSame('Am Montag ab 07:00 Uhr sind wir wieder für Sie da.', Texts::backAt($this->t('2026-11-20 13:00'), $this->t('2026-11-23 07:00'), 'de'));
        $this->assertSame('Am Donnerstag, 7. Jänner, ab 07:00 Uhr sind wir wieder für Sie da.', Texts::backAt($this->t('2026-12-24 10:00'), $this->t('2027-01-07 07:00'), 'de'));
        $this->assertSame('Unsere Öffnungszeiten finden Sie auf holz-marberger.at.', Texts::backAt($now, null, 'de'));
    }

    public function test_back_at_english(): void
    {
        $this->assertSame('We will be back today from 13:00.', Texts::backAt($this->t('2026-11-17 12:30'), $this->t('2026-11-17 13:00'), 'en'));
        $this->assertSame('We will be back on Thursday, 7 January, from 07:00.', Texts::backAt($this->t('2026-12-24 10:00'), $this->t('2027-01-07 07:00'), 'en'));
    }

    public function test_pause_and_closed_banner(): void
    {
        $now = $this->t('2026-11-17 12:30');
        $this->assertSame('Mittagspause – heute ab 13:00 Uhr sind wir wieder für Sie da.', Texts::pause($now, $this->t('2026-11-17 13:00'), 'de'));
        $this->assertSame('Lunch break – we will be back today from 13:00.', Texts::pause($now, $this->t('2026-11-17 13:00'), 'en'));
        $this->assertSame('Wir haben geschlossen – morgen ab 07:00 Uhr sind wir wieder für Sie da.', Texts::closedBanner($this->t('2026-11-17 18:00'), $this->t('2026-11-18 07:00'), 'de'));
    }

    public function test_warnings(): void
    {
        $this->assertSame('Mittagspause von 12:00 bis 13:00 Uhr.', Texts::warning(['type' => 'pause_soon', 'at' => $this->t('2026-11-17 12:00'), 'until' => $this->t('2026-11-17 13:00')], 'de'));
        $this->assertSame('Wir schließen heute um 12:00 Uhr.', Texts::warning(['type' => 'closing_soon', 'at' => $this->t('2026-11-20 12:00'), 'until' => null], 'de'));
        $this->assertSame('We close today at 12:00.', Texts::warning(['type' => 'closing_soon', 'at' => $this->t('2026-11-20 12:00'), 'until' => null], 'en'));
    }

    public function test_announcements(): void
    {
        $now = $this->t('2026-12-10 08:00');
        $closure = ['type' => 'closure', 'label_de' => 'Betriebsurlaub', 'label_en' => 'Company holidays', 'from' => '2026-12-24', 'to' => '2027-01-06', 'next_open' => $this->t('2027-01-07 07:00')];
        $this->assertSame('Betriebsurlaub 24.12.–6.1. · am Donnerstag, 7. Jänner, ab 07:00 Uhr wieder für Sie da.', Texts::announcement($closure, $now, 'de'));
        $this->assertSame('Company holidays 24 Dec – 6 Jan · back on Thursday, 7 January, from 07:00.', Texts::announcement($closure, $now, 'en'));

        $ex = ['type' => 'exception', 'date' => '2026-12-23', 'closed' => false, 'blocks' => [['07:00', '12:00']], 'label' => 'Kurzer Tag'];
        $this->assertSame('Kurzer Tag: am Mittwoch, 23. Dezember geöffnet 07:00–12:00 Uhr.', Texts::announcement($ex, $now, 'de'));
        $ex2 = ['type' => 'exception', 'date' => '2026-12-09', 'closed' => true, 'blocks' => [], 'label' => ''];
        $this->assertSame('Am Mittwoch, 9. Dezember haben wir geschlossen.', Texts::announcement($ex2, $now, 'de'));
    }
}
