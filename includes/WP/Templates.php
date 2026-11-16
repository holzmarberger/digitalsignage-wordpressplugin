<?php
namespace Marberger\Signage\WP;

/** Schnellvorlagen für Grüße (im Backend unter Einstellungen pflegbar). */
final class Templates
{
    public const OPTION = 'mms_templates';

    public static function defaults(): array
    {
        return [
            ['label' => 'Schulklasse', 'kicker_de' => 'Herzlich willkommen', 'name' => '[Schule] – Klasse [x]', 'sub_de' => 'Schön, dass ihr uns besucht – viel Spaß bei der Führung!', 'kicker_en' => 'Welcome', 'sub_en' => 'Great to have you here – enjoy the tour!'],
            ['label' => 'Kundenbesuch', 'kicker_de' => 'Herzlich willkommen', 'name' => '[Firma]', 'sub_de' => 'Wir freuen uns auf Ihren Besuch.', 'kicker_en' => 'Welcome', 'sub_en' => 'We are delighted to welcome you.'],
            ['label' => 'Delegation / Gruppe', 'kicker_de' => 'Herzlich willkommen', 'name' => '[Gruppe]', 'sub_de' => 'Schön, dass Sie heute bei uns sind.', 'kicker_en' => 'Welcome', 'sub_en' => 'Great to have you with us today.'],
            ['label' => 'Schnuppertag / Lehre', 'kicker_de' => 'Willkommen zum Schnuppertag', 'name' => '[Name(n)]', 'sub_de' => 'Wir freuen uns, euch kennenzulernen!', 'kicker_en' => 'Welcome to your trial day', 'sub_en' => 'We look forward to getting to know you!'],
        ];
    }

    public static function all(): array
    {
        $t = get_option(self::OPTION);
        return is_array($t) && $t ? array_values($t) : self::defaults();
    }

    public static function sanitize($rows): array
    {
        $out = [];
        foreach ((array) $rows as $r) {
            $label = sanitize_text_field((string) ($r['label'] ?? ''));
            if ($label === '') {
                continue;
            }
            $row = ['label' => mb_substr($label, 0, 40)];
            foreach (['kicker_de', 'name', 'sub_de', 'kicker_en', 'sub_en'] as $k) {
                $row[$k] = mb_substr(sanitize_text_field((string) ($r[$k] ?? '')), 0, 140);
            }
            $out[] = $row;
        }
        return $out;
    }
}
