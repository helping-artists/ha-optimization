<?php
/**
 * HA Optimization — Mòdul de Seguretat
 */

defined('ABSPATH') || exit;

class HA_Security {

    public static function init(): void {

        if (HA_Optimization::is_on('brute_force')) {
            add_filter('authenticate', [__CLASS__, 'limit_login_attempts'], 30, 2);
        }

        if (HA_Optimization::is_on('block_xmlrpc')) {
            add_filter('xmlrpc_enabled', '__return_false');
            remove_action('wp_head', 'rsd_link');
        }

        if (HA_Optimization::is_on('hide_wp_version')) {
            add_filter('the_generator', '__return_empty_string');
            remove_action('wp_head', 'wp_generator');
        }

        if (HA_Optimization::is_on('disable_file_edit')) {
            if (!defined('DISALLOW_FILE_EDIT')) {
                define('DISALLOW_FILE_EDIT', true);
            }
        }

        if (HA_Optimization::is_on('clean_headers')) {
            remove_action('wp_head', 'wlwmanifest_link');
            remove_action('wp_head', 'wp_shortlink_wp_head');
        }
    }

    /**
     * Limita intents de login a 3 cada 15 minuts per IP
     */
    public static function limit_login_attempts($user, string $password) {
        if (is_wp_error($user)) {
            return $user;
        }

        $ip  = self::get_client_ip();
        $key = 'ha_login_' . md5($ip);
        $attempts = (int) get_transient($key);

        if ($attempts >= 3) {
            $remaining = self::get_transient_remaining($key);
            return new WP_Error(
                'ha_too_many_attempts',
                sprintf(
                    'Has superat el límit d\'intents de login. Torna-ho a provar d\'aquí a %d minuts.',
                    (int) ceil($remaining / 60)
                )
            );
        }

        add_action('wp_login_failed', function () use ($key, $attempts) {
            set_transient($key, $attempts + 1, 900);
        });

        return $user;
    }

    private static function get_client_ip(): string {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    private static function get_transient_remaining(string $key): int {
        $timeout = get_option('_transient_timeout_' . $key);
        if ($timeout) {
            return max(0, (int) $timeout - time());
        }
        return 900;
    }
}
