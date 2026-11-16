<?php
namespace Marberger\Signage\WP\Admin;

/** Kleine Helfer für Formularfelder in den Metaboxen. Alle Felder heißen mms[...]. */
final class Fields
{
    public static function row(string $label, string $control, string $hint = ''): string
    {
        return '<tr><th scope="row">' . esc_html($label) . '</th><td>' . $control
            . ($hint !== '' ? '<p class="description">' . wp_kses_post($hint) . '</p>' : '') . '</td></tr>';
    }

    public static function text(string $name, $value, array $attr = []): string
    {
        return '<input type="text" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '"' . self::attrs($attr + ['class' => 'regular-text']) . '>';
    }

    public static function number(string $name, $value, array $attr = []): string
    {
        return '<input type="number" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '"' . self::attrs($attr + ['class' => 'small-text']) . '>';
    }

    public static function date(string $name, $value, array $attr = []): string
    {
        return '<input type="date" name="' . esc_attr($name) . '" value="' . esc_attr((string) $value) . '"' . self::attrs($attr) . '>';
    }

    public static function textarea(string $name, $value, array $attr = []): string
    {
        return '<textarea name="' . esc_attr($name) . '"' . self::attrs($attr + ['rows' => 3, 'class' => 'large-text']) . '>' . esc_textarea((string) $value) . '</textarea>';
    }

    /** @param array<string|int, string> $options */
    public static function select(string $name, $value, array $options, array $attr = []): string
    {
        $h = '<select name="' . esc_attr($name) . '"' . self::attrs($attr) . '>';
        foreach ($options as $k => $label) {
            $h .= '<option value="' . esc_attr((string) $k) . '"' . selected((string) $value, (string) $k, false) . '>' . esc_html($label) . '</option>';
        }
        return $h . '</select>';
    }

    public static function checkbox(string $name, bool $checked, string $label, string $value = '1'): string
    {
        return '<label><input type="checkbox" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '"' . checked($checked, true, false) . '> ' . esc_html($label) . '</label>';
    }

    public static function media(string $name, int $attachmentId, string $button = 'Bild wählen'): string
    {
        $url = $attachmentId ? (string) wp_get_attachment_image_url($attachmentId, 'medium') : '';
        return '<div class="mms-media">'
            . '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr((string) ($attachmentId ?: '')) . '">'
            . '<div class="mms-media-preview">' . ($url ? '<img src="' . esc_url($url) . '" alt="">' : '') . '</div>'
            . '<button type="button" class="button mms-media-pick">' . esc_html($button) . '</button> '
            . '<button type="button" class="button-link mms-media-clear"' . ($url ? '' : ' hidden') . '>Entfernen</button>'
            . '</div>';
    }

    private static function attrs(array $attr): string
    {
        $h = '';
        foreach ($attr as $k => $v) {
            $h .= ' ' . esc_attr($k) . '="' . esc_attr((string) $v) . '"';
        }
        return $h;
    }
}
