<?php
/**
 * Plugin Name: WP Users Export
 * Description: Exporta usuários do WordPress. Fornece dois botões: (1) Exportar todos os usuários com dados e estatísticas (CSV). (2) Exportar todos os e-mails, um por linha (TXT).
 * Version: 1.0.50
 * Author: Carlos Delfino <consultoria@carlosdelfino.eti.br>
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

// GitHub repo metadata
if (!defined('WPUE_GH_OWNER')) {
    define('WPUE_GH_OWNER', 'RapportTecnologia');
}
if (!defined('WPUE_GH_REPO')) {
    define('WPUE_GH_REPO', 'wp-user-export');
}

// Load text domain
function wpue_load_textdomain() {
    load_plugin_textdomain('wp-users-export', false, dirname(plugin_basename(__FILE__)) . '/languages');
}
add_action('plugins_loaded', 'wpue_load_textdomain');

// Add action links in Plugins list (Settings and Auto-Update toggle)
function wpue_plugin_action_links($links) {
    $settings_url = add_query_arg(['page' => 'wpue-export', 'tab' => 'settings'], admin_url('users.php'));
    $settings_link = '<a href="' . esc_url($settings_url) . '">' . esc_html__('Configurações', 'wp-users-export') . '</a>';

    $enabled = (bool) get_option('wpue_enable_auto_update');
    $action = $enabled ? 'disable' : 'enable';
    $label = $enabled ? __('Desativar atualização automática', 'wp-users-export') : __('Ativar atualização automática', 'wp-users-export');
    $toggle_url = wp_nonce_url(admin_url('admin-post.php?action=wpue_toggle_auto_update&do=' . $action), 'wpue_toggle_auto_update', 'wpue_nonce');
    $toggle_link = '<a href="' . esc_url($toggle_url) . '">' . esc_html($label) . '</a>';

    array_unshift($links, $settings_link, $toggle_link);
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'wpue_plugin_action_links');

// Handler to toggle auto-update from Plugins list
function wpue_toggle_auto_update() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Sem permissão.', 'wp-users-export'));
    }
    if (!isset($_GET['wpue_nonce']) || !wp_verify_nonce($_GET['wpue_nonce'], 'wpue_toggle_auto_update')) {
        wp_die(__('Nonce inválido.', 'wp-users-export'));
    }
    $do = isset($_GET['do']) ? sanitize_key($_GET['do']) : '';
    if ($do === 'enable') {
        update_option('wpue_enable_auto_update', '1');
    } elseif ($do === 'disable') {
        update_option('wpue_enable_auto_update', '0');
    }
    wp_safe_redirect(admin_url('plugins.php'));
    exit;
}
add_action('admin_post_wpue_toggle_auto_update', 'wpue_toggle_auto_update');

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

// ========= Update Checker (GitHub) =========
/**
 * Fetch latest release info from GitHub API
 */
function wpue_get_github_latest_release() {
    $transient_key = 'wpue_github_latest_release';
    $cached = get_transient($transient_key);
    if ($cached) {
        return $cached;
    }

    $api = sprintf('https://api.github.com/repos/%s/%s/releases/latest', WPUE_GH_OWNER, WPUE_GH_REPO);
    $resp = wp_remote_get($api, [
        'headers' => [ 'Accept' => 'application/vnd.github+json', 'User-Agent' => 'WordPress-WPUE' ],
        'timeout' => 15,
    ]);
    if (is_wp_error($resp)) {
        return false;
    }
    $code = wp_remote_retrieve_response_code($resp);
    if ($code !== 200) {
        return false;
    }
    $body = json_decode(wp_remote_retrieve_body($resp), true);
    if (!is_array($body) || empty($body['tag_name'])) {
        return false;
    }
    $tag = $body['tag_name']; // e.g., v1.0.21 or 1.0.21
    $ver = ltrim(trim($tag), 'vV');
    $zip = sprintf('https://github.com/%s/%s/archive/refs/tags/%s.zip', WPUE_GH_OWNER, WPUE_GH_REPO, rawurlencode($body['tag_name']));
    $result = [
        'version' => $ver,
        'tag' => $body['tag_name'],
        'zipball' => $zip,
        'html_url' => isset($body['html_url']) ? $body['html_url'] : sprintf('https://github.com/%s/%s/releases', WPUE_GH_OWNER, WPUE_GH_REPO),
    ];
    // Cache for 1 week and record last check timestamp
    set_transient($transient_key, $result, WEEK_IN_SECONDS);
    set_transient('wpue_github_last_check', time(), WEEK_IN_SECONDS);
    return $result;
}

/**
 * Inject update info into WordPress updates
 */
function wpue_inject_update_info($transient) {
    if (empty($transient) || empty($transient->checked)) {
        return $transient;
    }

    if (!function_exists('get_plugin_data')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $plugin_file = plugin_basename(__FILE__);
    $plugin_data = get_plugin_data(__FILE__, false, false);
    $current_version = isset($plugin_data['Version']) ? $plugin_data['Version'] : '0.0.0';

    $release = wpue_get_github_latest_release();
    if (!$release) {
        return $transient;
    }
    if (version_compare($release['version'], $current_version, '>')) {
        $obj = (object) [
            'slug' => dirname($plugin_file),
            'plugin' => $plugin_file,
            'new_version' => $release['version'],
            'package' => $release['zipball'],
            'url' => $release['html_url'],
            'tested' => '6.5',
            'requires' => '5.8',
        ];
        $transient->response[$plugin_file] = $obj;
        // Flag for admin notice
        update_option('wpue_latest_available', $release, false);
    } else {
        delete_option('wpue_latest_available');
    }

    return $transient;
}
add_filter('pre_set_site_transient_update_plugins', 'wpue_inject_update_info');

/**
 * Optional: info shown on the update details lightbox
 */
function wpue_plugins_api($result, $action, $args) {
    if ($action !== 'plugin_information') {
        return $result;
    }
    $plugin_file = plugin_basename(__FILE__);
    if (empty($args->slug) || $args->slug !== dirname($plugin_file)) {
        return $result;
    }
    $release = wpue_get_github_latest_release();
    if (!$release) {
        return $result;
    }
    $res = (object) [
        'name' => 'WP Users Export',
        'slug' => dirname($plugin_file),
        'version' => $release['version'],
        'download_link' => $release['zipball'],
        'homepage' => $release['html_url'],
        'sections' => [
            'description' => __('Exporta usuários do WordPress e e-mails com filtros e saída compactada.', 'wp-users-export'),
            'changelog' => __('Veja o CHANGELOG.md no repositório para detalhes.', 'wp-users-export'),
        ],
    ];
    return $res;
}
add_filter('plugins_api', 'wpue_plugins_api', 10, 3);

/**
 * Admin notice when new release is available
 */
function wpue_admin_notice_new_release() {
    if (!current_user_can('update_plugins')) {
        return;
    }
    $release = get_option('wpue_latest_available');
    if (!$release || empty($release['version'])) {
        return;
    }
    if (!function_exists('get_plugin_data')) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $plugin_data = get_plugin_data(__FILE__, false, false);
    $current_version = isset($plugin_data['Version']) ? $plugin_data['Version'] : '0.0.0';
    if (version_compare($release['version'], $current_version, '>')) {
        $update_url = admin_url('plugins.php');
        echo '<div class="notice notice-info is-dismissible"><p>'
            . esc_html(sprintf(__('Nova versão do WP Users Export disponível: %s (você está na %s). Atualize em Plugins.', 'wp-users-export'), $release['version'], $current_version))
            . ' <a href="' . esc_url($release['html_url']) . '" target="_blank">' . esc_html__('Notas da versão', 'wp-users-export') . '</a>'
            . ' | <a href="' . esc_url($update_url) . '">' . esc_html__('Ir para Plugins', 'wp-users-export') . '</a>'
            . '</p></div>';
    } else {
        delete_option('wpue_latest_available');
    }
}
add_action('admin_notices', 'wpue_admin_notice_new_release');

/**
 * Auto-update toggle (when enabled in settings)
 */
function wpue_maybe_auto_update($update, $item) {
    $plugin_file = plugin_basename(__FILE__);
    if (!empty($item->plugin) && $item->plugin === $plugin_file) {
        $enabled = get_option('wpue_enable_auto_update') ? true : false;
        return $enabled;
    }
    return $update;
}
add_filter('auto_update_plugin', 'wpue_maybe_auto_update', 10, 2);

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

    $tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'export';
    if (!in_array($tab, ['export', 'settings'], true)) { $tab = 'export'; }
    ?>
    <div class="wrap">
        <h1><?php echo esc_html__('WP Users Export', 'wp-users-export'); ?></h1>
        <h2 class="nav-tab-wrapper">
            <a href="<?php echo esc_url(add_query_arg(['page' => 'wpue-export', 'tab' => 'export'], admin_url('users.php'))); ?>" class="nav-tab <?php echo $tab === 'export' ? 'nav-tab-active' : ''; ?>"><?php echo esc_html__('Exportação', 'wp-users-export'); ?></a>
            <a href="<?php echo esc_url(add_query_arg(['page' => 'wpue-export', 'tab' => 'settings'], admin_url('users.php'))); ?>" class="nav-tab <?php echo $tab === 'settings' ? 'nav-tab-active' : ''; ?>"><?php echo esc_html__('Configuração', 'wp-users-export'); ?></a>
        </h2>

        <?php if ($tab === 'export') : ?>
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

        <p>
            <small>
                <?php echo esc_html__('Dica: Para filtros avançados, exporte todos e trate no Excel/LibreOffice.', 'wp-users-export'); ?>
            </small>
        </p>

        <?php else : // settings tab ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="max-width:680px;">
            <?php wp_nonce_field('wpue_save_settings', 'wpue_nonce'); ?>
            <input type="hidden" name="action" value="wpue_save_settings" />
            <p>
                <label>
                    <input type="checkbox" name="wpue_enable_auto_update" value="1" <?php checked((bool) get_option('wpue_enable_auto_update')); ?> />
                    <?php echo esc_html__('Habilitar atualização automática deste plugin', 'wp-users-export'); ?>
                </label>
            </p>
            <p class="description">
                <?php echo esc_html__('Quando habilitado, o WordPress poderá atualizar automaticamente o plugin ao detectar novas versões no GitHub.', 'wp-users-export'); ?>
            </p>
            <p>
                <button type="submit" class="button button-secondary"><?php echo esc_html__('Salvar configurações', 'wp-users-export'); ?></button>
            </p>
        </form>

        <?php
            // Update status info
            $last_check_ts = get_transient('wpue_github_last_check');
            $last_check = $last_check_ts ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_check_ts) : __('(ainda não verificado)', 'wp-users-export');
            $latest = get_transient('wpue_github_latest_release');
            $latest_ver = $latest && !empty($latest['version']) ? $latest['version'] : __('(indisponível)', 'wp-users-export');
            $latest_url = $latest && !empty($latest['html_url']) ? $latest['html_url'] : '';
        ?>
        <h2><?php echo esc_html__('Estado de atualização', 'wp-users-export'); ?></h2>
        <div style="max-width:680px; border:1px solid #ddd; padding:12px;">
            <p><strong><?php echo esc_html__('Última verificação:', 'wp-users-export'); ?></strong> <?php echo esc_html($last_check); ?></p>
            <p><strong><?php echo esc_html__('Última versão disponível:', 'wp-users-export'); ?></strong>
                <?php echo esc_html($latest_ver); ?>
                <?php if ($latest_url) : ?>
                    — <a href="<?php echo esc_url($latest_url); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html__('Notas da versão', 'wp-users-export'); ?></a>
                <?php endif; ?>
            </p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('wpue_force_check', 'wpue_nonce'); ?>
                <input type="hidden" name="action" value="wpue_force_check" />
                <button type="submit" class="button"><?php echo esc_html__('Verificar agora', 'wp-users-export'); ?></button>
            </form>
        </div>
        <?php endif; ?>

    </div>
    <?php
}

// Hooks for export actions
add_action('admin_post_wpue_export_users_csv', 'wpue_handle_export_users_csv');
add_action('admin_post_wpue_export_emails_txt', 'wpue_handle_export_emails_txt');

// Save settings handler
function wpue_save_settings() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Sem permissão.', 'wp-users-export'));
    }
    if (!isset($_POST['wpue_nonce']) || !wp_verify_nonce($_POST['wpue_nonce'], 'wpue_save_settings')) {
        wp_die(__('Nonce inválido.', 'wp-users-export'));
    }
    $enabled = !empty($_POST['wpue_enable_auto_update']) ? '1' : '0';
    update_option('wpue_enable_auto_update', $enabled);
    wp_safe_redirect(add_query_arg(['page' => 'wpue-export', 'settings-updated' => '1'], admin_url('users.php')));
    exit;
}
add_action('admin_post_wpue_save_settings', 'wpue_save_settings');

// Force update check handler
function wpue_force_check() {
    if (!current_user_can('manage_options')) {
        wp_die(__('Sem permissão.', 'wp-users-export'));
    }
    if (!isset($_POST['wpue_nonce']) || !wp_verify_nonce($_POST['wpue_nonce'], 'wpue_force_check')) {
        wp_die(__('Nonce inválido.', 'wp-users-export'));
    }
    delete_transient('wpue_github_latest_release');
    delete_transient('wpue_github_last_check');
    // Trigger a fresh check
    wpue_get_github_latest_release();
    wp_safe_redirect(add_query_arg(['page' => 'wpue-export', 'checked' => '1'], admin_url('users.php')));
    exit;
}
add_action('admin_post_wpue_force_check', 'wpue_force_check');

// ---- Admin footer (links) ----
function wpue_admin_footer_links() {
    if (!is_admin()) { return; }
    $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
    if ($page !== 'wpue-export') { return; }
    $owner = defined('WPUE_GH_OWNER') ? WPUE_GH_OWNER : 'RapportTecnologia';
    $repo  = defined('WPUE_GH_REPO')  ? WPUE_GH_REPO  : 'wp-user-export';
    $repo_url = sprintf('https://github.com/%s/%s', $owner, $repo);
    $web_url  = sprintf('https://rapport.tec.br/%s', $repo);
    echo '<div style="margin-top:16px;opacity:.8"><small>'
        . 'Repositório: <a href="' . esc_url($repo_url) . '" target="_blank" rel="noopener">' . esc_html($repo_url) . '</a>'
        . ' — Página: <a href="' . esc_url($web_url) . '" target="_blank" rel="noopener">' . esc_html($web_url) . '</a>'
        . '</small></div>';
}
add_action('admin_footer', 'wpue_admin_footer_links');
