<?php
namespace Marberger\Signage\WP\Admin;

use Marberger\Signage\WP\Access;
use Marberger\Signage\WP\Mask;
use Marberger\Signage\WP\Player;
use Marberger\Signage\WP\PostTypes;
use Marberger\Signage\WP\Store;

/** Startseite des Menüs „Displays“: alle Displays mit Status. */
final class Overview
{
    public static function render(): void
    {
        $admin = current_user_can(PostTypes::ADMIN_CAP);
        echo '<div class="wrap"><h1 class="wp-heading-inline">Displays – Übersicht</h1> ';
        if ($admin) {
            echo '<a class="page-title-action" href="' . esc_url(admin_url('post-new.php?post_type=' . PostTypes::DISPLAY)) . '">Display anlegen</a>';
        }
        echo '<hr class="wp-header-end">';
        self::quickBox($admin);
        $displays = get_posts(['post_type' => PostTypes::DISPLAY, 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC']);
        if (!$displays) {
            echo '<p>Noch keine Displays angelegt. Reihenfolge zum Start: <strong>Standort</strong> anlegen (Öffnungszeiten) → <strong>Display</strong> anlegen (Kategorie, Standort) → Adresse in Slide Show eintragen.</p></div>';
            return;
        }
        echo '<h2>Displays</h2><table class="widefat striped"><thead><tr><th>Display</th><th>Kennung</th><th>Kategorie</th><th>Standort</th><th>Format</th><th>Status</th><th></th></tr></thead><tbody>';
        foreach ($displays as $p) {
            $d = PostTypes::data($p->ID);
            $key = (string) get_post_meta($p->ID, Store::KEY_META, true);
            $seen = Store::lastSeen($p->ID);
            $online = $seen && time() - $seen < 300;
            $name = '<strong>' . esc_html($p->post_title) . '</strong>';
            echo '<tr><td>' . ($admin ? '<a href="' . esc_url(get_edit_post_link($p->ID)) . '">' . $name . '</a>' : $name) . '</td>'
                . '<td><code>' . esc_html($key) . '</code></td>'
                . '<td>' . esc_html(!empty($d['category']) ? get_the_title((int) $d['category']) : '–') . '</td>'
                . '<td>' . esc_html(!empty($d['location']) ? get_the_title((int) $d['location']) : '–') . '</td>'
                . '<td>' . esc_html(($d['format'] ?? 'hoch') === 'quer' ? 'Quer' : 'Hoch') . '</td>'
                . '<td><span class="mms-dot ' . ($online ? 'on' : 'off') . '"></span>' . esc_html(self::seenLabel($seen)) . '</td>'
                . '<td>' . ($key ? '<a target="_blank" href="' . esc_url(Player::url($key)) . '">Anzeige öffnen</a>' : '') . '</td></tr>';
        }
        echo '</tbody></table></div>';
    }

    private static function quickBox(bool $admin): void
    {
        $pw = Access::password();
        echo '<div class="mms-pwbox"><h2 style="margin-top:4px">Schnellzugriff</h2><p>';
        foreach ([PostTypes::GREETING => 'Gruß anlegen', PostTypes::NOTICE => 'Hinweis anlegen', PostTypes::NEWS => 'News anlegen'] as $type => $label) {
            echo '<a class="button" href="' . esc_url(admin_url('post-new.php?post_type=' . $type)) . '">' . esc_html($label) . '</a> ';
        }
        echo '</p><p><strong>Handy-Pflegemaske:</strong> <input type="text" readonly class="regular-text mms-copy" value="' . esc_attr(Mask::url()) . '"> <a href="' . esc_url(Mask::url()) . '" target="_blank">öffnen</a></p>';
        echo '<p><strong>Gruß-Passwort:</strong> ';
        if ($pw !== '') {
            echo '<input type="password" class="mms-secret" readonly value="' . esc_attr($pw) . '"> <a href="#" class="mms-reveal">anzeigen</a>';
        } else {
            echo '<em>noch nicht festgelegt</em>';
        }
        if ($admin) {
            echo ' &nbsp; <a href="' . esc_url(admin_url('admin.php?page=mms-settings')) . '">ändern</a>';
        }
        echo '</p></div>';
    }

    public static function seenLabel(int $ts): string
    {
        if (!$ts) {
            return 'noch nie';
        }
        $diff = time() - $ts;
        if ($diff < 120) {
            return 'online (vor ' . $diff . ' s)';
        }
        if ($diff < 3600) {
            return 'vor ' . round($diff / 60) . ' min';
        }
        return wp_date('d.m.Y H:i', $ts);
    }
}
