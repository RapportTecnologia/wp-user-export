<?php
/**
 * Plugin Name: WP Users Export
 * Description: Exporta usuários do WordPress. Fornece dois botões: (1) Exportar todos os usuários com dados e estatísticas (CSV). (2) Exportar todos os e-mails, um por linha (TXT).
 * Version: 1.0.21
 * Author: Carlos Delfino
 * Text Domain: wp-users-export
 * Domain Path: /languages
 * Requires at least: 5.8
 * Tested up to: 6.5
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define constants
if (!defined('WPUE_PLUGIN_DIR')) {
    define('WPUE_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
if (!defined('WPUE_PLUGIN_URL')) {
    define('WPUE_PLUGIN_URL', plugin_dir_url(__FILE__));
}

// Load text domain
function wpue_load_textdomain() {
    load_plugin_textdomain('wp-users-export', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'wpue_load_textdomain');

// Activation check: minimum PHP/WP and ZipArchive
function wpue_on_activation() {
    global $wp_version;

    $min_wp = '5.8';
    $min_php = '7.4';

    $errors = [];

    if (version_compare(PHP_VERSION, $min_php, '<')) {
        $errors[] = sprintf(__('Este plugin requer PHP %s ou superior. Versão atual: %s.', 'wp-users-export'), $min_php, PHP_VERSION);
    }
    if (isset($wp_version) && version_compare($wp_version, $min_wp, '<')) {
        $errors[] = sprintf(__('Este plugin requer WordPress %s ou superior. Versão atual: %s.', 'wp-users-export'), $min_wp, $wp_version);
    }
    if (!class_exists('ZipArchive')) {
        $errors[] = __('A extensão PHP Zip (ZipArchive) é necessária para exportar arquivos compactados.', 'wp-users-export');
    }

    if (!empty($errors)) {
        $message = '<h1>' . esc_html__('Requisitos não atendidos', 'wp-users-export') . '</h1><ul>'; 
        foreach ($errors as $e) {
            $message .= '<li>' . esc_html($e) . '</li>';
        }
        $message .= '</ul>';
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die($message, __('Erro de ativação', 'wp-users-export'), ['back_link' => true]);
    }
}
register_activation_hook(__FILE__, 'wpue_on_activation');

// Include exporters
require_once WPUE_PLUGIN_DIR . 'includes/exporters.php';

// Add admin menu under Users
function wpue_register_menu() {
    add_users_page(
        __('Exportar Usuários', 'wp-users-export'),
        __('Exportar Usuários', 'wp-users-export'),
        'list_users',
        'wpue-export',
        'wpue_render_export_page'
    );
}
add_action('admin_menu', 'wpue_register_menu');

// Render the admin page
function wpue_render_export_page() {
    if (!current_user_can('list_users')) {
        wp_die(__('Você não tem permissão para acessar esta página.', 'wp-users-export'));
    }

    $users_count = count_users();
    $total_users = isset($users_count['total_users']) ? intval($users_count['total_users']) : 0;

    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('Exportar Usuários', 'wp-users-export'); ?></h1>
        <p><?php echo esc_html(sprintf(__('Total de usuários: %d', 'wp-users-export'), $total_users)); ?></p>

        <div style="display:flex; gap:24px; align-items:flex-start; flex-wrap:wrap;">
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('wpue_export_users_csv', 'wpue_nonce'); ?>
                <input type="hidden" name="action" value="wpue_export_users_csv" />
                <fieldset style="border:1px solid #ddd; padding:12px; margin:12px 0;">
                    <legend><?php echo esc_html__('Filtros de exportação', 'wp-users-export'); ?></legend>
                    <p>
                        <label>
                            <input type="checkbox" name="wpue_only_new" value="1" />
                            <?php echo esc_html__('Exportar apenas novos desde a última exportação', 'wp-users-export'); ?>
                        </label>
                    </p>
                    <p>
                        <label>
                            <?php echo esc_html__('Data inicial de registro', 'wp-users-export'); ?>
                            <input type="date" name="wpue_start_date" />
                        </label>
                        &nbsp;&nbsp;
                        <label>
                            <?php echo esc_html__('Data final de registro', 'wp-users-export'); ?>
                            <input type="date" name="wpue_end_date" />
                        </label>
                    </p>
                    <p>
                        <label>
                            <?php echo esc_html__('Regex para filtrar (email, login ou nome de exibição)', 'wp-users-export'); ?>
                            <input type="text" name="wpue_regex" placeholder="ex: ^.*@dominio\\.com$" style="width:320px;" />
                        </label>
                    </p>
                    <p class="description">
                        <?php echo esc_html__('Se marcar "apenas novos", usa a data/hora registrada da última exportação deste tipo. Datas informadas limitam pelo período do registro do usuário. O regex é aplicado a email, login e nome de exibição (case-insensitive).', 'wp-users-export'); ?>
                    </p>
                </fieldset>
                <p>
                    <button type="submit" class="button button-primary">
                        <?php echo esc_html__('Exportar usuários (CSV)', 'wp-users-export'); ?>
                    </button>
                </p>
                <p class="description">
                    <?php echo esc_html__('Exporta: ID, usuário, nome, sobrenome, nome de exibição, e-mail, função(s), data de registro, posts (contagem), comentários (contagem), URL.', 'wp-users-export'); ?>
                </p>
            </form>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('wpue_export_emails_txt', 'wpue_nonce'); ?>
                <input type="hidden" name="action" value="wpue_export_emails_txt" />
                <fieldset style="border:1px solid #ddd; padding:12px; margin:12px 0;">
                    <legend><?php echo esc_html__('Filtros de exportação', 'wp-users-export'); ?></legend>
                    <p>
                        <label>
                            <input type="checkbox" name="wpue_only_new" value="1" />
                            <?php echo esc_html__('Exportar apenas novos desde a última exportação', 'wp-users-export'); ?>
                        </label>
                    </p>
                    <p>
                        <label>
                            <?php echo esc_html__('Data inicial de registro', 'wp-users-export'); ?>
                            <input type="date" name="wpue_start_date" />
                        </label>
                        &nbsp;&nbsp;
                        <label>
                            <?php echo esc_html__('Data final de registro', 'wp-users-export'); ?>
                            <input type="date" name="wpue_end_date" />
                        </label>
                    </p>
                    <p>
                        <label>
                            <?php echo esc_html__('Regex para filtrar (email, login ou nome de exibição)', 'wp-users-export'); ?>
                            <input type="text" name="wpue_regex" placeholder="ex: ^.*@dominio\\.com$" style="width:320px;" />
                        </label>
                    </p>
                    <p class="description">
                        <?php echo esc_html__('Se marcar "apenas novos", usa a data/hora registrada da última exportação deste tipo. Datas informadas limitam pelo período do registro do usuário. O regex é aplicado a email, login e nome de exibição (case-insensitive).', 'wp-users-export'); ?>
                    </p>
                </fieldset>
                <p>
                    <button type="submit" class="button">
                        <?php echo esc_html__('Exportar e-mails (TXT)', 'wp-users-export'); ?>
                    </button>
                </p>
                <p class="description">
                    <?php echo esc_html__('Exporta apenas os e-mails, um por linha.', 'wp-users-export'); ?>
                </p>
            </form>
        </div>

        <hr />
        <p>
            <small>
                <?php echo esc_html__('Dica: Para filtros avançados, exporte todos e trate no Excel/LibreOffice.', 'wp-users-export'); ?>
            </small>
        </p>
    </div>
    <?php
}

// Hooks for export actions
add_action('admin_post_wpue_export_users_csv', 'wpue_handle_export_users_csv');
add_action('admin_post_wpue_export_emails_txt', 'wpue_handle_export_emails_txt');
