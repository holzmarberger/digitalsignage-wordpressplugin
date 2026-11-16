<?php
namespace Marberger\Signage\WP\Admin;

use Marberger\Signage\WP\PostTypes;

/**
 * Basisklasse der Metaboxen: Registrierung, Nonce, Rechteprüfung, Fehlermeldungen nach dem Speichern.
 */
abstract class Box
{
    abstract protected function postType(): string;

    abstract protected function title(): string;

    abstract public function render(\WP_Post $post): void;

    /** @return list<string> Fehlermeldungen */
    abstract protected function sanitize(int $postId, array $input, array $old): array;

    public function register(): void
    {
        add_action('add_meta_boxes_' . $this->postType(), function () {
            add_meta_box('mms-box', $this->title(), function (\WP_Post $post) {
                wp_nonce_field('mms_save_' . $post->ID, '_mms_nonce');
                $this->render($post);
            }, $this->postType(), 'normal', 'high');
        });
        add_action('save_post_' . $this->postType(), [$this, 'save'], 10, 2);
    }

    public function save(int $postId, \WP_Post $post): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (!isset($_POST['_mms_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_mms_nonce'])), 'mms_save_' . $postId)) {
            return;
        }
        if (!current_user_can(PostTypes::capFor($this->postType()))) {
            return;
        }
        $input = isset($_POST['mms']) && is_array($_POST['mms']) ? wp_unslash($_POST['mms']) : [];
        $errors = $this->sanitize($postId, $input, PostTypes::data($postId));
        if ($errors) {
            set_transient('mms_errors_' . get_current_user_id(), $errors, 120);
        }
    }

    public static function notices(): void
    {
        $key = 'mms_errors_' . get_current_user_id();
        $errors = get_transient($key);
        if (!$errors) {
            return;
        }
        delete_transient($key);
        echo '<div class="notice notice-error"><p><strong>Bitte prüfen – nicht alles wurde übernommen:</strong></p><ul style="list-style:disc;margin-left:20px">';
        foreach ((array) $errors as $e) {
            echo '<li>' . esc_html($e) . '</li>';
        }
        echo '</ul></div>';
    }

    /** Titel aus dem WP-Titelfeld (für Typen, deren Titel ein Inhaltsfeld ist). */
    protected static function postTitle(): string
    {
        return isset($_POST['post_title']) ? sanitize_text_field(wp_unslash($_POST['post_title'])) : '';
    }

    /** Checkboxen für Displays (Typ 'reception' | 'info' | '' = alle). */
    protected static function displayChecks(string $name, array $selected, string $type = ''): string
    {
        $h = '';
        foreach (PostTypes::displays($type) as $id => $title) {
            $h .= '<span class="mms-inline">' . Fields::checkbox($name . '[]', in_array($id, array_map('intval', $selected), true), $title, (string) $id) . '</span>';
        }
        return $h !== '' ? $h : '<em>Noch keine passenden Displays angelegt.</em>';
    }

    protected static function dayChecks(string $name, array $selected): string
    {
        $h = '';
        foreach (\Marberger\Signage\Core\Texts::WEEKDAYS_DE as $i => $d) {
            $h .= '<span class="mms-inline">' . Fields::checkbox($name . '[]', in_array($i, array_map('intval', $selected), true), mb_substr($d, 0, 2), (string) $i) . '</span>';
        }
        return $h;
    }

    protected static function str(array $in, string $key, int $max = 500): string
    {
        return mb_substr(sanitize_text_field((string) ($in[$key] ?? '')), 0, $max);
    }

    protected static function date(array $in, string $key): string
    {
        $v = (string) ($in[$key] ?? '');
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) ? $v : '';
    }
}
