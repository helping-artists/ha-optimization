<?php
/**
 * HA Optimization — Mòdul de Rendiment
 */

defined('ABSPATH') || exit;

class HA_Performance {

    public static function init(): void {

        if (HA_Optimization::is_on('limit_heartbeat')) {
            add_filter('heartbeat_settings', [__CLASS__, 'limit_heartbeat']);
            add_action('init', [__CLASS__, 'disable_heartbeat_frontend']);
        }

        if (HA_Optimization::is_on('disable_google_fonts')) {
            add_action('init', [__CLASS__, 'disable_google_fonts_collection']);
        }

        if (HA_Optimization::is_on('disable_emojis')) {
            add_action('init', [__CLASS__, 'disable_emojis']);
        }

        if (HA_Optimization::is_on('disable_oembed')) {
            add_action('init', [__CLASS__, 'disable_oembed_discovery']);
        }
    }

    /**
     * Limita el Heartbeat a 120 segons al backend
     */
    public static function limit_heartbeat(array $settings): array {
        $settings['interval'] = 120;
        return $settings;
    }

    /**
     * Desactiva el Heartbeat al frontend
     */
    public static function disable_heartbeat_frontend(): void {
        if (!is_admin()) {
            wp_deregister_script('heartbeat');
        }
    }

    /**
     * Desactiva la col·lecció de Google Fonts a l'editor (WP 6.9+)
     */
    public static function disable_google_fonts_collection(): void {
        if (function_exists('wp_unregister_font_collection')) {
            wp_unregister_font_collection('google-fonts');
        }
    }

    /**
     * Desactiva els emojis de WordPress
     */
    public static function disable_emojis(): void {
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');

        add_filter('tiny_mce_plugins', function ($plugins) {
            if (is_array($plugins)) {
                return array_diff($plugins, ['wpemoji']);
            }
            return $plugins;
        });

        add_filter('wp_resource_hints', function ($urls, $relation_type) {
            if ($relation_type === 'dns-prefetch') {
                $urls = array_filter($urls, function ($url) {
                    return strpos($url, 'https://s.w.org/images/core/emoji/') === false;
                });
            }
            return $urls;
        }, 10, 2);
    }

    /**
     * Desactiva oEmbed discovery
     */
    public static function disable_oembed_discovery(): void {
        remove_action('wp_head', 'wp_oembed_add_discovery_links');
        remove_action('wp_head', 'wp_oembed_add_host_js');
    }
}
