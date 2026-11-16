<?php
namespace Marberger\Signage\Core;

/**
 * Einstellungen von Display-Kategorien (Standardwerte) und deren Abweichungen pro Display.
 */
final class Settings
{
    public const MODES = ['fullscreen' => 'Vollbild-Seite', 'banner' => 'Balken', 'none' => 'nichts'];
    public const ANNOUNCE_MODES = ['banner' => 'Balken', 'ticker' => 'Laufband + News-Folie', 'none' => 'nichts'];

    /**
     * Felddefinitionen für das Backend. 'scope': both | reception | info.
     * @return array<string, array{label:string, type:string, options?:array, min?:int, scope:string}>
     */
    public static function fields(): array
    {
        return [
            'poll'             => ['label' => 'Abfrageintervall (Sekunden)', 'type' => 'int', 'min' => 15, 'scope' => 'both'],
            'idle'             => ['label' => 'Rücksprung zur Startseite nach (Sekunden)', 'type' => 'int', 'min' => 10, 'scope' => 'reception'],
            'pause_mode'       => ['label' => 'Anzeige in der Pause', 'type' => 'select', 'options' => self::MODES, 'scope' => 'both'],
            'closed_mode'      => ['label' => 'Anzeige bei Feierabend / geschlossenen Tagen', 'type' => 'select', 'options' => self::MODES, 'scope' => 'both'],
            'closure_mode'     => ['label' => 'Anzeige während einer Schließzeit', 'type' => 'select', 'options' => self::MODES, 'scope' => 'both'],
            'warn_enabled'     => ['label' => 'Vorwarnung vor Schluss', 'type' => 'bool', 'scope' => 'both'],
            'warn_minutes'     => ['label' => 'Vorwarnung (Minuten vorher)', 'type' => 'int', 'min' => 1, 'scope' => 'both'],
            'announce_mode'    => ['label' => 'Vorankündigung von Schließzeiten', 'type' => 'select', 'options' => self::ANNOUNCE_MODES, 'scope' => 'both'],
            'directory_button' => ['label' => 'Knopf „Wegweiser öffnen“ auf Geschlossen-/Schließzeit-Seite', 'type' => 'bool', 'scope' => 'reception'],
            'news'             => ['label' => 'News und Laufband anzeigen', 'type' => 'bool', 'scope' => 'info'],
            'slide_duration'   => ['label' => 'Standard-Dauer pro Abfolge-Eintrag (Sekunden)', 'type' => 'int', 'min' => 5, 'scope' => 'info'],
        ];
    }

    public static function defaults(string $type): array
    {
        if ($type === 'info') {
            return [
                'type' => 'info', 'poll' => 60, 'idle' => 45,
                'pause_mode' => 'none', 'closed_mode' => 'none', 'closure_mode' => 'none',
                'warn_enabled' => false, 'warn_minutes' => 15, 'announce_mode' => 'ticker',
                'directory_button' => false, 'news' => true, 'slide_duration' => 30,
            ];
        }
        return [
            'type' => 'reception', 'poll' => 60, 'idle' => 45,
            'pause_mode' => 'banner', 'closed_mode' => 'fullscreen', 'closure_mode' => 'fullscreen',
            'warn_enabled' => true, 'warn_minutes' => 15, 'announce_mode' => 'banner',
            'directory_button' => true, 'news' => false, 'slide_duration' => 30,
        ];
    }

    /** Bereinigt Kategoriewerte; fehlende Werte kommen aus den Standardwerten des Typs. */
    public static function sanitize(array $values): array
    {
        $type = ($values['type'] ?? '') === 'info' ? 'info' : 'reception';
        $out = self::defaults($type);
        foreach (self::fields() as $key => $f) {
            if (!array_key_exists($key, $values) || $values[$key] === null || $values[$key] === '') {
                continue;
            }
            $v = self::clean($f, $values[$key]);
            if ($v !== null) {
                $out[$key] = $v;
            }
        }
        return $out;
    }

    /** Abweichungen eines Displays: nur gesetzte Werte, null/'' = von Kategorie übernehmen. */
    public static function sanitizeOverrides(array $values): array
    {
        $out = [];
        foreach (self::fields() as $key => $f) {
            if (!array_key_exists($key, $values) || $values[$key] === null || $values[$key] === '' || $values[$key] === 'inherit') {
                continue;
            }
            $v = self::clean($f, $values[$key]);
            if ($v !== null) {
                $out[$key] = $v;
            }
        }
        return $out;
    }

    public static function merge(array $category, array $overrides): array
    {
        $base = self::sanitize($category);
        foreach (self::sanitizeOverrides($overrides) as $k => $v) {
            $base[$k] = $v;
        }
        return $base;
    }

    private static function clean(array $f, $v)
    {
        switch ($f['type']) {
            case 'int':
                if (!is_numeric($v)) {
                    return null;
                }
                return max((int) ($f['min'] ?? 0), (int) $v);
            case 'bool':
                return in_array($v, [true, 1, '1', 'yes', 'on', 'true'], true);
            case 'select':
                return array_key_exists((string) $v, $f['options']) ? (string) $v : null;
        }
        return null;
    }
}
