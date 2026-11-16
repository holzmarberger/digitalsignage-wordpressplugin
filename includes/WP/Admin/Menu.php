<?php
namespace Marberger\Signage\WP\Admin;

use Marberger\Signage\Core\Settings;
use Marberger\Signage\Plugin;
use Marberger\Signage\WP\Access;
use Marberger\Signage\WP\PostTypes;
use Marberger\Signage\WP\Templates;

require_once __DIR__ . '/EditorialBoxes.php';

final class Menu
{
    private const TITLE_PLACEHOLDERS = [
        PostTypes::GREETING => 'Name / Gruppe, z. B. HTL Imst – Klasse 3B',
        PostTypes::NOTICE => 'Text des Hinweises (Deutsch)',
        PostTypes::NODE => 'Kachel-Titel (Deutsch), z. B. Verkauf konstruktiv',
        PostTypes::FULLPAGE => 'Überschrift, z. B. Guten Morgen, liebes Team!',
        PostTypes::NEWS => 'Titel der News',
        PostTypes::PAGE => 'Name der Seite (erscheint als Titel in der Kopfzeile)',
        PostTypes::DISPLAY => 'Name des Displays, z. B. Empfang Ötztal Bahnhof',
        PostTypes::CATEGORY => 'Name der Kategorie',
        PostTypes::LOCATION => 'Name des Standorts',
        PostTypes::CLOSURE => 'Bezeichnung, z. B. Betriebsurlaub Weihnachten',
    ];

    public static function init(): void
    {
        add_action('admin_menu', [self::class, 'menu'], 9);
        add_action('admin_menu', [self::class, 'settingsMenu'], 20);
        add_action('admin_post_mms_settings', [self::class, 'saveSettings']);
        add_action('admin_head', [self::class, 'hideNewEntries']);
        add_action('admin_enqueue_scripts', [self::class, 'assets']);
        add_action('admin_notices', [Box::class, 'notices']);
        add_filter('enter_title_here', [self::class, 'titlePlaceholder'], 10, 2);
        foreach ([new LocationBox(), new ClosureBox(), new CategoryBox(), new DisplayBox(), new GreetingBox(), new NoticeBox(), new FullpageBox(), new NewsBox(), new NodeBox(), new PageBox()] as $box) {
            $box->register();
        }
    }

    public static function menu(): void
    {
        add_menu_page('Displays', 'Displays', PostTypes::EDIT_CAP, 'mms', [Overview::class, 'render'], 'dashicons-desktop', 58);
        add_submenu_page('mms', 'Übersicht', 'Übersicht', PostTypes::EDIT_CAP, 'mms', [Overview::class, 'render']);
        // WordPress prüft post-new.php bei Nicht-Administratoren gegen einen Menüeintrag; ohne ihn gibt es
        // „Sorry, you are not allowed“. Deshalb unsichtbare „Neu anlegen“-Einträge für die Redaktionstypen.
        foreach (PostTypes::definitions() as $type => $def) {
            if ($def[2] === PostTypes::EDIT_CAP) {
                add_submenu_page('mms', $def[1] . ' anlegen', $def[1] . ' anlegen', PostTypes::EDIT_CAP, 'post-new.php?post_type=' . $type);
            }
        }
    }

    public static function hideNewEntries(): void
    {
        echo '<style>#toplevel_page_mms .wp-submenu a[href^="post-new.php?post_type=mms_"]{display:none}</style>';
    }

    public static function settingsMenu(): void
    {
        add_submenu_page('mms', 'Einstellungen', 'Einstellungen', PostTypes::ADMIN_CAP, 'mms-settings', [self::class, 'settingsPage']);
    }

    public static function titlePlaceholder(string $text, \WP_Post $post): string
    {
        return self::TITLE_PLACEHOLDERS[$post->post_type] ?? $text;
    }

    public static function settingsPage(): void
    {
        if (!current_user_can(PostTypes::ADMIN_CAP)) {
            return;
        }
        echo '<div class="wrap"><h1>Displays – Einstellungen</h1>';
        if (isset($_GET['updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Einstellungen gespeichert.' . (!empty($_GET['pw']) ? ' Das Gruß-Passwort wurde geändert – alle Handys sind abgemeldet.' : '') . '</p></div>';
        }
        echo '<form method="post" class="mms-settings" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="mms_settings">';
        wp_nonce_field('mms_settings', '_mms_settings_nonce');

        echo '<h2>Öffnungszeiten</h2><table class="form-table">';
        echo Fields::row('Kurze Lücken ignorieren', Fields::number('mms_set[min_gap]', Plugin::option('min_gap'), ['min' => 0, 'max' => 120, 'class' => 'small-text']) . ' Minuten',
            'Lücken zwischen zwei Öffnungsblöcken, die kürzer sind, gelten nicht als Pause.');
        echo '</table>';

        $pw = Access::password();
        echo '<h2>Gruß-Passwort (Handy-Pflegemaske)</h2><table class="form-table">';
        echo Fields::row('Aktuelles Passwort', $pw !== ''
            ? '<input type="password" class="mms-secret" readonly value="' . esc_attr($pw) . '"> <a href="#" class="mms-reveal">anzeigen</a>'
            : '<em>noch keines festgelegt – die Maske ist nur mit WordPress-Konto nutzbar</em>');
        echo Fields::row('Neues Passwort', '<input type="text" name="mms_set[greet_pw]" value="" autocomplete="off" class="regular-text">',
            'Leer lassen = unverändert. Beim Ändern werden alle angemeldeten Handys abgemeldet.');
        if ($pw !== '') {
            echo Fields::row('', Fields::checkbox('mms_set[greet_pw_clear]', false, 'Passwort entfernen (Maske nur noch mit WordPress-Konto)'));
        }
        echo Fields::row('Adresse der Maske', '<input type="text" readonly class="regular-text mms-copy" value="' . esc_attr(\Marberger\Signage\WP\Mask::url()) . '">',
            'Nur aus dem internen Netz erreichbar. Als Lesezeichen am Handy-Startbildschirm ablegen.');
        echo '</table>';

        echo '<h2>Schnellvorlagen für Grüße</h2><p class="description">Platzhalter in [eckigen Klammern] müssen vor dem Speichern ersetzt werden.</p>';
        echo '<div class="mms-rep-wrap"><table class="widefat mms-repeater mms-tpl-table" data-next="' . count(Templates::all()) . '"><thead><tr><th style="width:14%">Bezeichnung</th><th>Begrüßungszeile</th><th>Name / Gruppe</th><th>Zusatzzeile</th><th>Begrüßungszeile EN</th><th>Zusatzzeile EN</th><th></th></tr></thead><tbody>';
        foreach (Templates::all() as $i => $t) {
            echo self::templateRow((string) $i, $t);
        }
        echo '</tbody></table><template class="mms-row-template">' . self::templateRow('__i__', []) . '</template>';
        echo '<p><button type="button" class="button mms-add-row">Vorlage hinzufügen</button></p></div>';

        submit_button('Einstellungen speichern');
        echo '</form></div>';
    }

    private static function templateRow(string $i, array $t): string
    {
        $h = '<tr>';
        foreach (['label', 'kicker_de', 'name', 'sub_de', 'kicker_en', 'sub_en'] as $k) {
            $h .= '<td><input type="text" name="mms_tpl[' . $i . '][' . $k . ']" value="' . esc_attr($t[$k] ?? '') . '"></td>';
        }
        return $h . '<td class="mms-row-tools"><button type="button" class="button-link mms-up">▲</button><button type="button" class="button-link mms-down">▼</button><button type="button" class="button-link mms-remove-row">✕</button></td></tr>';
    }

    public static function saveSettings(): void
    {
        if (!current_user_can(PostTypes::ADMIN_CAP) || !check_admin_referer('mms_settings', '_mms_settings_nonce')) {
            wp_die('Keine Berechtigung.');
        }
        $in = isset($_POST['mms_set']) ? (array) wp_unslash($_POST['mms_set']) : [];
        $opt = (array) get_option(Plugin::OPTION, []);
        $opt['min_gap'] = min(120, absint($in['min_gap'] ?? 15));
        update_option(Plugin::OPTION, $opt);

        $pwChanged = false;
        if (!empty($in['greet_pw_clear'])) {
            Access::setPassword('');
            $pwChanged = true;
        } elseif (trim((string) ($in['greet_pw'] ?? '')) !== '') {
            Access::setPassword(sanitize_text_field((string) $in['greet_pw']));
            $pwChanged = true;
        }
        $tpl = Templates::sanitize(isset($_POST['mms_tpl']) ? wp_unslash($_POST['mms_tpl']) : []);
        update_option(Templates::OPTION, $tpl, false);

        wp_safe_redirect(add_query_arg(['page' => 'mms-settings', 'updated' => 1, 'pw' => $pwChanged ? 1 : 0], admin_url('admin.php')));
        exit;
    }

    public static function assets(string $hook): void
    {
        $screen = get_current_screen();
        $ours = $screen && (isset(PostTypes::definitions()[$screen->post_type]) || strpos($hook, 'mms') !== false);
        if (!$ours) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_style('mms-admin', MMS_URL . 'assets/admin/admin.css', [], MMS_VERSION);
        wp_enqueue_script('mms-admin', MMS_URL . 'assets/admin/admin.js', ['jquery'], MMS_VERSION, true);
        wp_localize_script('mms-admin', 'MMS', [
            'nonce' => wp_create_nonce('wp_rest'),
            'defaults' => ['reception' => Settings::defaults('reception'), 'info' => Settings::defaults('info')],
        ]);
    }
}
