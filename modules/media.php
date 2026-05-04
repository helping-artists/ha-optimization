<?php
/**
 * HA Optimization — Mòdul de Media
 * Mostra els attachments ordenats per mida de fitxer
 */

defined('ABSPATH') || exit;

class HA_Media {

    public static function init(): void {
        add_action('admin_menu', [__CLASS__, 'admin_menu']);
        add_action('admin_init', [__CLASS__, 'handle_delete']);
    }

    public static function handle_delete(): void {
        if (!isset($_GET['ha_delete_media'], $_GET['_wpnonce'])) return;
        if (!current_user_can('manage_options')) return;

        $id = (int) $_GET['ha_delete_media'];
        if (!wp_verify_nonce($_GET['_wpnonce'], 'ha_delete_media_' . $id)) {
            wp_die('Nonce invàlid.');
        }

        wp_delete_attachment($id, true);

        $redirect = add_query_arg([
            'page'    => 'ha-media-size',
            'deleted' => 1,
            'mime'    => $_GET['mime'] ?? 'all',
        ], admin_url('upload.php'));
        wp_redirect($redirect);
        exit;
    }

    public static function admin_menu(): void {
        add_media_page(
            'Media per mida',
            'Media per mida',
            'manage_options',
            'ha-media-size',
            [__CLASS__, 'render_page']
        );
    }

    public static function render_page(): void {
        if (!current_user_can('manage_options')) return;

        global $wpdb;

        if (!empty($_GET['deleted'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Arxiu esborrat correctament.</p></div>';
        }

        $results = $wpdb->get_results("
            SELECT 
                p.ID,
                p.post_title,
                p.post_mime_type,
                p.post_date,
                p.post_parent
            FROM {$wpdb->posts} p
            WHERE p.post_type = 'attachment'
            AND p.post_status = 'inherit'
            ORDER BY p.post_date DESC
        ");

        $attachments = [];
        foreach ($results as $row) {
            $file = get_attached_file($row->ID);
            $size = $file && file_exists($file) ? filesize($file) : 0;

            $parent_title = '';
            $parent_url   = '';
            if ($row->post_parent) {
                $parent = get_post($row->post_parent);
                if ($parent) {
                    $parent_title = $parent->post_title;
                    $parent_url   = get_permalink($parent->ID);
                }
            }

            $attachments[] = [
                'id'           => $row->ID,
                'title'        => $row->post_title,
                'mime'         => $row->post_mime_type,
                'date'         => $row->post_date,
                'size'         => $size,
                'url'          => wp_get_attachment_url($row->ID),
                'edit_url'     => get_edit_post_link($row->ID),
                'file_path'    => $file ? str_replace(wp_upload_dir()['basedir'], 'uploads', $file) : '',
                'parent_title' => $parent_title,
                'parent_url'   => $parent_url,
            ];
        }

        usort($attachments, fn($a, $b) => $b['size'] - $a['size']);

        $filter = $_GET['mime'] ?? 'all';
        $current_url = admin_url('upload.php?page=ha-media-size');

        ?>
        <div class="wrap">
            <h1>Media per mida</h1>
            <p>Tots els arxius de la biblioteca ordenats de més gran a més petit. Els arxius en vermell superen 500 KB, en taronja superen 200 KB.</p>

            <ul class="subsubsub">
                <li><a href="<?php echo $current_url; ?>" <?php echo $filter === 'all' ? 'class="current"' : ''; ?>>Tots</a> |</li>
                <li><a href="<?php echo $current_url; ?>&mime=image" <?php echo $filter === 'image' ? 'class="current"' : ''; ?>>Imatges</a> |</li>
                <li><a href="<?php echo $current_url; ?>&mime=video" <?php echo $filter === 'video' ? 'class="current"' : ''; ?>>Vídeo</a> |</li>
                <li><a href="<?php echo $current_url; ?>&mime=application" <?php echo $filter === 'application' ? 'class="current"' : ''; ?>>Documents</a></li>
            </ul>

            <table class="wp-list-table widefat fixed striped" style="margin-top: 1em;">
                <thead>
                    <tr>
                        <th style="width:50px">Prev.</th>
                        <th>Títol / Path</th>
                        <th style="width:80px">Tipus</th>
                        <th style="width:80px">Mida</th>
                        <th style="width:150px">Post associat</th>
                        <th style="width:90px">Data</th>
                        <th style="width:100px">Accions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $total_size = 0;
                    $count = 0;
                    foreach ($attachments as $att):
                        if ($filter !== 'all' && strpos($att['mime'], $filter) === false) continue;
                        $total_size += $att['size'];
                        $count++;
                        $size_label = $att['size'] > 0 ? self::format_size($att['size']) : '—';
                        $is_image   = strpos($att['mime'], 'image') !== false;
                        $is_video   = strpos($att['mime'], 'video') !== false;

                        if ($is_image) {
                            $thumb = wp_get_attachment_image($att['id'], [50, 50], false, ['style' => 'width:50px;height:50px;object-fit:cover;']);
                        } elseif ($is_video) {
                            $thumb = '<span class="dashicons dashicons-video-alt3" style="font-size:36px;width:36px;height:36px;color:#666;"></span>';
                        } else {
                            $thumb = '<span class="dashicons dashicons-media-default" style="font-size:36px;width:36px;height:36px;color:#666;"></span>';
                        }

                        $color = '';
                        if ($att['size'] > 500 * 1024)      $color = 'color:#c00;font-weight:bold;';
                        elseif ($att['size'] > 200 * 1024)  $color = 'color:#d46b08;font-weight:bold;';

                        $delete_url = wp_nonce_url(
                            add_query_arg([
                                'page'             => 'ha-media-size',
                                'ha_delete_media'  => $att['id'],
                                'mime'             => $filter,
                            ], admin_url('upload.php')),
                            'ha_delete_media_' . $att['id']
                        );
                    ?>
                        <tr>
                            <td><?php echo $thumb; ?></td>
                            <td>
                                <a href="<?php echo esc_url($att['url']); ?>" target="_blank">
                                    <?php echo esc_html($att['title'] ?: '(sense títol)'); ?>
                                </a>
                                <?php if ($att['file_path']): ?>
                                    <br><small style="color:#999;word-break:break-all;"><?php echo esc_html($att['file_path']); ?></small>
                                <?php endif; ?>
                                <?php if ($is_video && $att['url']): ?>
                                    <br>
                                    <video controls style="max-width:300px;margin-top:4px;">
                                        <source src="<?php echo esc_url($att['url']); ?>" type="<?php echo esc_attr($att['mime']); ?>">
                                    </video>
                                <?php endif; ?>
                            </td>
                            <td><small><?php echo esc_html($att['mime']); ?></small></td>
                            <td style="<?php echo $color; ?>"><?php echo $size_label; ?></td>
                            <td>
                                <?php if ($att['parent_title']): ?>
                                    <a href="<?php echo esc_url($att['parent_url']); ?>" target="_blank">
                                        <?php echo esc_html($att['parent_title']); ?>
                                    </a>
                                <?php else: ?>
                                    <span style="color:#999;">—</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo esc_html(date('d/m/Y', strtotime($att['date']))); ?></td>
                            <td>
                                <a href="<?php echo esc_url($att['edit_url']); ?>">Edita</a>
                                &nbsp;|&nbsp;
                                <a href="<?php echo esc_url($delete_url); ?>"
                                   style="color:#c00;"
                                   onclick="return confirm('Segur que vols esborrar aquest arxiu? Aquesta acció no es pot desfer.');">
                                    Esborra
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="4"><strong><?php echo $count; ?> arxius</strong></td>
                        <td colspan="3"><strong>Total: <?php echo self::format_size($total_size); ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php
    }

    private static function format_size(int $bytes): string {
        if ($bytes >= 1024 * 1024) {
            return round($bytes / (1024 * 1024), 2) . ' MB';
        }
        return round($bytes / 1024, 1) . ' KB';
    }
}
