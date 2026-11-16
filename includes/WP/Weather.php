<?php
namespace Marberger\Signage\WP;

/** Aktuelles Wetter je Standort über Open-Meteo (serverseitig, 30 min zwischengespeichert). */
final class Weather
{
    public static function current(?float $lat, ?float $lon): ?array
    {
        if ($lat === null || $lon === null) {
            return null;
        }
        $key = 'mms_wx_' . md5(round($lat, 3) . ',' . round($lon, 3));
        $cached = get_transient($key);
        if (is_array($cached)) {
            return $cached['data'];
        }
        $url = add_query_arg([
            'latitude' => round($lat, 4), 'longitude' => round($lon, 4),
            'current' => 'temperature_2m,weather_code,is_day', 'timezone' => 'Europe/Vienna',
        ], 'https://api.open-meteo.com/v1/forecast');
        $res = wp_remote_get($url, ['timeout' => 5]);
        $data = null;
        if (!is_wp_error($res) && wp_remote_retrieve_response_code($res) === 200) {
            $j = json_decode(wp_remote_retrieve_body($res), true);
            if (isset($j['current']['temperature_2m'], $j['current']['weather_code'])) {
                $data = [
                    'temp' => (int) round((float) $j['current']['temperature_2m']),
                    'icon' => self::icon((int) $j['current']['weather_code'], !empty($j['current']['is_day'])),
                ];
            }
        }
        set_transient($key, ['data' => $data], $data ? 30 * MINUTE_IN_SECONDS : 10 * MINUTE_IN_SECONDS);
        return $data;
    }

    /** WMO-Wettercode → Symbol des Players. */
    public static function icon(int $code, bool $day = true): string
    {
        if ($code === 0) {
            return $day ? 'sun' : 'moon';
        }
        if ($code <= 2) {
            return $day ? 'partly' : 'cloudy';
        }
        if ($code === 3) {
            return 'cloudy';
        }
        if ($code === 45 || $code === 48) {
            return 'fog';
        }
        if (($code >= 71 && $code <= 77) || $code === 85 || $code === 86) {
            return 'snow';
        }
        if ($code >= 95) {
            return 'thunder';
        }
        return 'rain';
    }
}
