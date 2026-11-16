<?php
namespace Marberger\Signage\WP\Admin;

use Marberger\Signage\Core\Settings;
use Marberger\Signage\WP\Player;
use Marberger\Signage\WP\PostTypes;
use Marberger\Signage\WP\Rest;
use Marberger\Signage\WP\Sequence;
use Marberger\Signage\WP\Store;

final class DisplayBox extends Box
{
    protected function postType(): string
    {
        return PostTypes::DISPLAY;
    }

    protected function title(): string
    {
        return 'Display-Einstellungen';
    }

    public function register(): void
    {
        parent::register();
        add_action('add_meta_boxes_' . PostTypes::DISPLAY, function () {
            add_meta_box('mms-display-info', 'Adresse & Status', [$this, 'renderInfo'], PostTypes::DISPLAY, 'side', 'high');
            add_meta_box('mms-display-preview', 'Zustand testen', [$this, 'renderPreview'], PostTypes::DISPLAY, 'normal', 'default');
        });
    }

    public function render(\WP_Post $post): void
    {
        $d = PostTypes::data($post->ID);
        $key = (string) get_post_meta($post->ID, Store::KEY_META, true);
        $cats = PostTypes::choices(PostTypes::CATEGORY);
        $locs = PostTypes::choices(PostTypes::LOCATION);
        $types = [];
        foreach (array_keys($cats) as $cid) {
            $types[$cid] = ((PostTypes::data((int) $cid)['type'] ?? 'reception') === 'info') ? 'info' : 'reception';
        }
        $scope = static fn (string $s) => '<span class="mms-scope" data-scope="' . $s . '" hidden></span>';
        echo '<table class="form-table mms-form">';
        echo Fields::row('Kennung', Fields::text('mms[key]', $key, ['placeholder' => 'z. B. empfang-oetztal', 'pattern' => '[a-z0-9_\\-]*']),
            'Teil der Adresse, die in Slide Show eingetragen wird. Nur Kleinbuchstaben, Ziffern und Bindestriche. Leer = aus dem Titel gebildet.');
        echo Fields::row('Kategorie', Fields::select('mms[category]', $d['category'] ?? '', ['' => '– bitte wählen –'] + $cats, ['data-types' => wp_json_encode($types)]),
            'Bestimmt, ob es ein Empfangsdisplay (Touch) oder ein Info-Display ist, und das Standardverhalten.');
        echo Fields::row('Standort', Fields::select('mms[location]', $d['location'] ?? '', ['' => '– ohne (immer geöffnet) –'] + $locs),
            'Bestimmt Logo, Wetter und Öffnungszeiten.');
        echo Fields::row('Format', Fields::select('mms[format]', $d['format'] ?? 'hoch', ['hoch' => 'Hochformat', 'quer' => 'Querformat']));
        $nodes = ['' => '– oberste Ebene –'] + PostTypes::choices(PostTypes::NODE, ['post_parent' => 0]);
        echo Fields::row('Wegweiser beginnt bei', Fields::select('mms[start_node]', $d['start_node'] ?? '', $nodes) . $scope('reception'),
            'Standard: alle Wegweiser-Kacheln der obersten Ebene. Für ein Display, das nur einen Teil des Wegweisers zeigen soll, hier die Start-Kachel wählen.');
        echo '</table>';
        echo '<div class="mms-scope-block" data-scope="info"><h3 class="mms-sub">Abfolge</h3>';
        echo '<p>' . Fields::checkbox('mms[sequence_own]', !empty($d['sequence_own']), 'eigene Abfolge statt der Standard-Abfolge der Kategorie') . '</p>';
        echo '<div class="mms-own-seq">' . SequenceEditor::render('mms[sequence]', $d['sequence'] ?? []) . '</div></div>';

        echo '<h3 class="mms-sub">Abweichungen von der Kategorie</h3><p class="description">Nur ausfüllen, wenn dieses Display anders arbeiten soll als seine Kategorie.</p>';
        echo '<table class="form-table mms-form">';
        $ov = $d['overrides'] ?? [];
        foreach (Settings::fields() as $k => $f) {
            echo Fields::row($f['label'], CategoryBox::control("mms[overrides][$k]", $f, $ov[$k] ?? null, true) . ($f['scope'] !== 'both' ? $scope($f['scope']) : ''));
        }
        echo '</table>';
    }

    public function renderInfo(\WP_Post $post): void
    {
        $key = (string) get_post_meta($post->ID, Store::KEY_META, true);
        if ($key === '') {
            echo '<p>Nach dem Speichern erscheint hier die Adresse für Slide Show.</p>';
            return;
        }
        $seen = Store::lastSeen($post->ID);
        echo '<p><strong>Adresse für Slide Show:</strong><br><input type="text" readonly class="widefat mms-copy" value="' . esc_attr(Player::url($key)) . '"></p>';
        echo '<p><a class="button" target="_blank" href="' . esc_url(Player::url($key)) . '">Anzeige öffnen</a></p>';
        echo '<p><strong>Zuletzt gemeldet:</strong><br>' . esc_html(Overview::seenLabel($seen)) . '</p>';
    }

    public function renderPreview(\WP_Post $post): void
    {
        $key = (string) get_post_meta($post->ID, Store::KEY_META, true);
        if ($key === '') {
            echo '<p>Bitte zuerst speichern.</p>';
            return;
        }
        $now = new \DateTimeImmutable('now', Store::timezone());
        echo '<div class="mms-preview" data-url="' . esc_attr(Rest::stateUrl($key)) . '">';
        echo '<p>Zeigt, was dieses Display zu einem beliebigen Zeitpunkt anzeigen würde (ohne das Display zu verändern).</p>';
        echo '<p><input type="datetime-local" class="mms-preview-at" value="' . esc_attr($now->format('Y-m-d\TH:i')) . '"> ';
        echo '<button type="button" class="button button-primary mms-preview-run">Zustand berechnen</button></p>';
        echo '<div class="mms-preview-out"></div></div>';
    }

    protected function sanitize(int $postId, array $in, array $old): array
    {
        $errors = [];
        $key = sanitize_key((string) ($in['key'] ?? ''));
        if ($key === '') {
            $key = sanitize_key(sanitize_title(get_the_title($postId)));
        }
        if ($key === '') {
            $key = 'display-' . $postId;
        }
        $base = $key;
        $n = 2;
        while (($other = Store::displayByKeyAnyStatus($key)) && $other !== $postId) {
            $key = $base . '-' . $n++;
        }
        if ($key !== $base && !empty($in['key'])) {
            $errors[] = sprintf('Die Kennung „%s“ ist bereits vergeben – verwendet wird „%s“.', $base, $key);
        }
        update_post_meta($postId, Store::KEY_META, $key);

        $data = [
            'category' => absint($in['category'] ?? 0),
            'location' => absint($in['location'] ?? 0),
            'format' => ($in['format'] ?? '') === 'quer' ? 'quer' : 'hoch',
            'overrides' => Settings::sanitizeOverrides((array) ($in['overrides'] ?? [])),
            'start_node' => absint($in['start_node'] ?? 0),
            'sequence_own' => !empty($in['sequence_own']),
            'sequence' => Sequence::sanitize($in['sequence'] ?? []),
        ];
        if (!$data['category']) {
            $errors[] = 'Bitte eine Kategorie wählen – bis dahin gelten die Standardwerte für Empfangsdisplays.';
        }
        PostTypes::save($postId, $data);
        return $errors;
    }
}
