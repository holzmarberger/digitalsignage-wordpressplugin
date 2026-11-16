<?php
namespace Marberger\Signage\WP;

use Marberger\Signage\Core\TimeRule;

/** Kurzer Status eines redaktionellen Eintrags für Listen und Pflegemaske. */
final class Status
{
    public const CSS = [
        'läuft jetzt' => 'run', 'läuft' => 'run', 'aktiv' => 'run',
        'geplant' => 'plan', 'heute später' => 'plan', 'wiederkehrend' => 'plan',
        'unvollständig' => 'bad', 'pausiert' => 'off', 'abgelaufen' => 'off',
    ];

    public static function of(string $type, array $d): string
    {
        if (empty($d['valid']) && $type !== PostTypes::NODE) {
            return 'unvollständig';
        }
        $now = new \DateTimeImmutable('now', Store::timezone());
        $nowStr = $now->format('Y-m-d H:i');
        $today = $now->format('Y-m-d');
        switch ($type) {
            case PostTypes::GREETING:
                if (($d['to'] ?? '') !== '' && $nowStr >= $d['to']) {
                    return 'abgelaufen';
                }
                if (TimeRule::isActive(Editorial::greetingRule($d), $now)) {
                    return 'läuft jetzt';
                }
                return ($d['from'] ?? '') <= $nowStr ? 'heute später' : 'geplant';
            case PostTypes::NOTICE:
                if (empty($d['active'])) {
                    return 'pausiert';
                }
                if (($d['valid_to'] ?? '') !== '' && $d['valid_to'] < $today) {
                    return 'abgelaufen';
                }
                return ($d['valid_from'] ?? '') > $today ? 'geplant' : 'aktiv';
            case PostTypes::FULLPAGE:
                if (($d['rule_type'] ?? '') === 'once') {
                    if (($d['once_to'] ?? '') !== '' && $nowStr >= $d['once_to']) {
                        return 'abgelaufen';
                    }
                    return ($d['once_from'] ?? '') <= $nowStr ? 'läuft jetzt' : 'geplant';
                }
                return 'wiederkehrend';
            case PostTypes::NEWS:
                if (($d['to'] ?? '') < $today) {
                    return 'abgelaufen';
                }
                return ($d['from'] ?? '') > $today ? 'geplant' : 'läuft';
        }
        return '';
    }

    public static function chip(string $status): string
    {
        return $status === '' ? '' : '<span class="mms-chip mms-chip-' . esc_attr(self::CSS[$status] ?? 'off') . '">' . esc_html($status) . '</span>';
    }
}
