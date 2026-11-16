<?php
namespace Marberger\Signage\WP;

/**
 * Bilder aus der Handy-Pflegemaske: Format prüfen, HEIC/HEIF in JPG umwandeln, verkleinern, in die Mediathek legen.
 */
final class Images
{
    public const MAX_BYTES = 25 * 1024 * 1024;
    public const MAX_EDGE = 1600;
    private const ALLOWED = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif', 'image/heic' => 'heic', 'image/heif' => 'heic'];

    /** @return int|\WP_Error Attachment-ID */
    public static function fromUpload(array $file)
    {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name']) || !empty($file['error'])) {
            return new \WP_Error('mms_upload', 'Die Datei konnte nicht hochgeladen werden.', ['status' => 400]);
        }
        if ((int) $file['size'] > self::MAX_BYTES) {
            return new \WP_Error('mms_upload', 'Die Datei ist zu groß (max. 25 MB).', ['status' => 400]);
        }
        $mime = self::mime($file['tmp_name']);
        if (!isset(self::ALLOWED[$mime])) {
            return new \WP_Error('mms_upload', 'Bitte ein Bild hochladen (JPG, PNG, WebP, GIF oder HEIC vom iPhone).', ['status' => 400]);
        }
        $base = sanitize_file_name(pathinfo((string) ($file['name'] ?? 'bild'), PATHINFO_FILENAME)) ?: 'bild';
        $tmp = wp_tempnam($base);
        if (self::ALLOWED[$mime] === 'heic') {
            if (!class_exists('Imagick')) {
                return new \WP_Error('mms_upload', 'HEIC-Bilder können auf diesem Server nicht umgewandelt werden. Bitte als JPG senden (iPhone: Einstellungen → Kamera → Formate → „Maximale Kompatibilität“).', ['status' => 400]);
            }
            try {
                $im = new \Imagick($file['tmp_name']);
                $im->setImageFormat('jpeg');
                $im->setImageCompressionQuality(85);
                $im->writeImage($tmp);
                $im->clear();
            } catch (\Exception $e) {
                return new \WP_Error('mms_upload', 'Das HEIC-Bild konnte nicht umgewandelt werden. Bitte als JPG senden.', ['status' => 400]);
            }
            $ext = 'jpg';
        } else {
            copy($file['tmp_name'], $tmp);
            $ext = self::ALLOWED[$mime];
        }
        $target = $tmp . '.' . $ext;
        rename($tmp, $target);

        if ($ext !== 'gif') {
            $ed = wp_get_image_editor($target);
            if (!is_wp_error($ed)) {
                if (method_exists($ed, 'maybe_exif_rotate')) {
                    $ed->maybe_exif_rotate();
                }
                $size = $ed->get_size();
                if (max($size['width'], $size['height']) > self::MAX_EDGE) {
                    $ed->resize(self::MAX_EDGE, self::MAX_EDGE, false);
                }
                $ed->save($target);
            }
        }

        $id = media_handle_sideload(['name' => $base . '.' . $ext, 'tmp_name' => $target], 0);
        if (is_wp_error($id)) {
            @unlink($target);
            return new \WP_Error('mms_upload', 'Das Bild konnte nicht gespeichert werden: ' . $id->get_error_message(), ['status' => 500]);
        }
        update_post_meta($id, '_mms_upload', 1);
        return (int) $id;
    }

    private static function mime(string $path): string
    {
        $mime = function_exists('finfo_open') ? (string) finfo_file(finfo_open(FILEINFO_MIME_TYPE), $path) : (string) mime_content_type($path);
        if ($mime === 'application/octet-stream') {
            $head = (string) file_get_contents($path, false, null, 4, 8);
            if (in_array(substr($head, 0, 4), ['ftyp'], true) && preg_match('/heic|heix|mif1|msf1/', $head)) {
                return 'image/heic';
            }
        }
        return $mime;
    }
}
