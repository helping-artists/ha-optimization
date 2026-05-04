<?php
/**
 * Plugin Name: » Helping Artists » Optimization
 * Plugin URI: https://github.com/helping-artists/ha-optimization
 * Description: Seguretat i rendiment per WordPress amb opcions activables.
 * Version: 1.3.0
 * Author: dídac gilabert
 * Author URI: https://didacgilabert.com
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * GitHub Plugin URI: helping-artists/ha-optimization
 * GitHub Branch: main
 */

defined('ABSPATH') || exit;

class HA_Optimization {

    const OPT_KEY   = 'ha_optimization';
    const PAGE_SLUG = 'ha-optimization';

    public static function defaults(): array {
        return [
            // Security
            'brute_force'       => '1',
            'block_xmlrpc'      => '1',
            'hide_wp_version'   => '1',
            'disable_file_edit' => '1',
            'clean_headers'     => '1',
            // Performance
            'limit_heartbeat'       => '1',
            'disable_google_fonts'  => '1',
            'disable_emojis'        => '1',
            'disable_oembed'        => '1',
        ];
    }

    public static function get_options(): array {
        $saved = get_option(self::OPT_KEY, []);
        if (!is_array($saved)) $saved = [];
        return array_merge(self::defaults(), $saved);
    }

    public static function is_on(string $key): bool {
        $opts = self::get_options();
        return ($opts[$key] ?? '0') === '1';
    }

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        add_action('admin_init', [__CLASS__, 'register_settings']);
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), [__CLASS__, 'add_settings_link']);

        require_once __DIR__ . '/modules/security.php';
        require_once __DIR__ . '/modules/performance.php';
        require_once __DIR__ . '/modules/media.php';

        HA_Security::init();
        HA_Performance::init();
        HA_Media::init();
    }

    public static function add_settings_link(array $links): array {
        $url = admin_url('options-general.php?page=' . self::PAGE_SLUG);
        array_unshift($links, '<a href="' . esc_url($url) . '">Ajustos</a>');
        return $links;
    }

    public static function admin_menu(): void {
        add_options_page(
            'HA Optimization',
            'HA Optimization',
            'manage_options',
            self::PAGE_SLUG,
            [__CLASS__, 'render_page']
        );
    }

    public static function register_settings(): void {
        register_setting('ha_optimization_group', self::OPT_KEY, [
            'type'              => 'array',
            'sanitize_callback' => [__CLASS__, 'sanitize'],
        ]);
    }

    public static function sanitize($input): array {
        $out = [];
        $defaults = self::defaults();
        foreach (array_keys($defaults) as $key) {
            $out[$key] = !empty($input[$key]) ? '1' : '0';
        }
        return $out;
    }

    public static function render_page(): void {
        if (!current_user_can('manage_options')) return;

        $opts = self::get_options();
        $fields = [
            'security' => [
                'label'  => 'Seguretat',
                'fields' => [
                    'brute_force'       => 'Limitar intents de login (3 intents, 15 min bloqueig)',
                    'block_xmlrpc'      => 'Bloquejar XMLRPC',
                    'hide_wp_version'   => 'Ocultar versió de WordPress',
                    'disable_file_edit' => 'Desactivar editor de fitxers del backend',
                    'clean_headers'     => 'Netejar headers innecessaris (wlwmanifest, shortlink, RSD)',
                ],
            ],
            'performance' => [
                'label'  => 'Rendiment',
                'fields' => [
                    'limit_heartbeat'       => 'Limitar Heartbeat API (120s al backend, desactivat al frontend)',
                    'disable_google_fonts'  => 'Desactivar col·lecció Google Fonts a l\'editor (WP 6.9+)',
                    'disable_emojis'        => 'Desactivar emojis de WordPress',
                    'disable_oembed'        => 'Desactivar oEmbed discovery',
                ],
            ],
        ];
        ?>
        <div class="wrap">
            <h1>» Helping Artists » Optimization</h1>
            <p>Activa o desactiva cada funció segons les teves necessitats.</p>

            <form method="post" action="options.php">
                <?php settings_fields('ha_optimization_group'); ?>

                <?php foreach ($fields as $section): ?>
                    <h2><?php echo esc_html($section['label']); ?></h2>
                    <table class="form-table" role="presentation">
                        <?php foreach ($section['fields'] as $key => $label): ?>
                            <tr>
                                <th scope="row"><?php echo esc_html($label); ?></th>
                                <td>
                                    <label>
                                        <input type="checkbox"
                                            name="<?php echo esc_attr(self::OPT_KEY); ?>[<?php echo esc_attr($key); ?>]"
                                            value="1"
                                            <?php checked(($opts[$key] ?? '0') === '1'); ?>>
                                        Actiu
                                    </label>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endforeach; ?>

                <?php submit_button('Desa'); ?>
            </form>

            <hr>
            <h2>Eines addicionals</h2>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">Media per mida</th>
                    <td>
                        <a href="<?php echo admin_url('upload.php?page=ha-media-size'); ?>" class="button">
                            Obre Media per mida
                        </a>
                        <p class="description">Visualitza tots els arxius de la biblioteca ordenats per pes, amb reproductor de vídeo inline, path del fitxer al servidor i post associat. Permet esborrar arxius directament.</p>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
}

add_action('plugins_loaded', [HA_Optimization::class, 'init']);

/**
 * Actualitzador automàtic des de GitHub
 */
class HA_Updater {

    const GITHUB_USER = 'helping-artists';
    const GITHUB_REPO = 'ha-optimization';
    const PLUGIN_FILE = 'ha-optimization/ha-optimization.php';

    public static function init(): void {
        add_filter('pre_set_site_transient_update_plugins', [__CLASS__, 'check_update']);
        add_filter('plugins_api', [__CLASS__, 'plugin_info'], 10, 3);
    }

    public static function check_update($transient) {
        if (empty($transient->checked)) return $transient;

        $remote = self::get_remote_data();
        if (!$remote) return $transient;

        $remote_version = ltrim($remote->tag_name, 'v');
        $local_version  = $transient->checked[self::PLUGIN_FILE] ?? '0';

        if (version_compare($remote_version, $local_version, '>')) {
            $transient->response[self::PLUGIN_FILE] = (object) [
                'slug'        => 'ha-optimization',
                'plugin'      => self::PLUGIN_FILE,
                'new_version' => $remote_version,
                'url'         => 'https://github.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO,
                'package'     => 'https://github.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO . '/archive/refs/heads/main.zip',
            ];
        }

        return $transient;
    }

    public static function plugin_info($result, $action, $args) {
        if ($action !== 'plugin_information') return $result;
        if ($args->slug !== 'ha-optimization') return $result;

        $remote = self::get_remote_data();
        if (!$remote) return $result;

        return (object) [
            'name'          => '» Helping Artists » Optimization',
            'slug'          => 'ha-optimization',
            'version'       => ltrim($remote->tag_name, 'v'),
            'author'        => 'dídac gilabert',
            'homepage'      => 'https://github.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO,
            'download_link' => 'https://github.com/' . self::GITHUB_USER . '/' . self::GITHUB_REPO . '/archive/refs/heads/main.zip',
            'sections'      => [
                'description' => 'Seguretat i rendiment per WordPress amb opcions activables.',
            ],
        ];
    }

    private static function get_remote_data() {
        $url      = 'https://api.github.com/repos/' . self::GITHUB_USER . '/' . self::GITHUB_REPO . '/releases/latest';
        $response = wp_remote_get($url, [
            'headers' => ['User-Agent' => 'WordPress/' . get_bloginfo('version')],
            'timeout' => 10,
        ]);

        if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
            return null;
        }

        return json_decode(wp_remote_retrieve_body($response));
    }
}

add_action('plugins_loaded', [HA_Updater::class, 'init']);
