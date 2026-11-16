<?php
namespace Marberger\Signage\WP\Admin;

use Marberger\Signage\Core\Settings;
use Marberger\Signage\WP\PostTypes;

final class CategoryBox extends Box
{
    private const SCOPES = ['both' => '', 'reception' => ' (nur Empfang)', 'info' => ' (nur Info)'];

    protected function postType(): string
    {
        return PostTypes::CATEGORY;
    }

    protected function title(): string
    {
        return 'Standardwerte dieser Kategorie';
    }

    public function render(\WP_Post $post): void
    {
        $d = Settings::sanitize(PostTypes::data($post->ID));
        echo '<table class="form-table mms-form">';
        echo Fields::row('Typ', Fields::select('mms[type]', $d['type'], ['reception' => 'Empfang (Touch)', 'info' => 'Info (ohne Touch)']),
            'Beim Wechsel des Typs bitte die Werte prüfen; neue Kategorien starten mit passenden Standardwerten.');
        foreach (Settings::fields() as $key => $f) {
            echo Fields::row($f['label'] . self::SCOPES[$f['scope']], self::control("mms[$key]", $f, $d[$key]));
        }
        echo '</table>';
    }

    public static function control(string $name, array $f, $value, bool $inherit = false): string
    {
        switch ($f['type']) {
            case 'int':
                return Fields::number($name, $value, ['min' => $f['min'] ?? 0, 'placeholder' => $inherit ? 'von Kategorie' : '', 'class' => 'mms-num']);
            case 'bool':
                $opts = ['1' => 'ja', '0' => 'nein'];
                if ($inherit) {
                    $opts = ['' => '– von Kategorie –'] + $opts;
                    $value = $value === null ? '' : ($value ? '1' : '0');
                } else {
                    $value = $value ? '1' : '0';
                }
                return Fields::select($name, $value, $opts);
            case 'select':
                $opts = $inherit ? ['' => '– von Kategorie –'] + $f['options'] : $f['options'];
                return Fields::select($name, $value ?? '', $opts);
        }
        return '';
    }

    protected function sanitize(int $postId, array $in, array $old): array
    {
        $isNew = !$old;
        $type = ($in['type'] ?? '') === 'info' ? 'info' : 'reception';
        // Neue Kategorie oder Typwechsel ohne Eingaben: Standardwerte des Typs verwenden
        $values = ($isNew && empty($in['poll'])) ? Settings::defaults($type) : $in;
        $values['type'] = $type;
        PostTypes::save($postId, Settings::sanitize($values));
        return [];
    }
}
