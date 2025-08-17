<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Handle CSV export of users with data and statistics
 */
function wpue_handle_export_users_csv() {
    if (!current_user_can('list_users')) {
        wp_die(__('Você não tem permissão para exportar usuários.', 'wp-users-export'));
    }

    if (!isset($_POST['wpue_nonce']) || !wp_verify_nonce($_POST['wpue_nonce'], 'wpue_export_users_csv')) {
        wp_die(__('Nonce inválido. Atualize a página e tente novamente.', 'wp-users-export'));
    }

    if (!class_exists('ZipArchive')) {
        wp_die(__('O PHP ZIP (ZipArchive) não está disponível no servidor. Habilite a extensão zip para exportar em .zip.', 'wp-users-export'));
    }

    // Read filters
    $only_new = !empty($_POST['wpue_only_new']);
    $start_date = isset($_POST['wpue_start_date']) ? sanitize_text_field(wp_unslash($_POST['wpue_start_date'])) : '';
    $end_date   = isset($_POST['wpue_end_date']) ? sanitize_text_field(wp_unslash($_POST['wpue_end_date'])) : '';
    $regex_raw  = isset($_POST['wpue_regex']) ? trim((string) wp_unslash($_POST['wpue_regex'])) : '';

    // Build date_query based on filters
    $date_query = [];
    if ($only_new) {
        $last = get_option('wpue_last_export_users_csv');
        if (!empty($last)) {
            $date_query['after'] = gmdate('Y-m-d H:i:s', intval($last));
        }
    } elseif (!empty($start_date) || !empty($end_date)) {
        if (!empty($start_date)) {
            $date_query['after'] = $start_date . ' 00:00:00';
        }
        if (!empty($end_date)) {
            $date_query['before'] = $end_date . ' 23:59:59';
        }
        $date_query['inclusive'] = true;
    }

    $use_date_query = !empty($date_query);

    // Prepare regex (case-insensitive)
    $regex = '';
    if ($regex_raw !== '') {
        $regex = '~' . $regex_raw . '~i';
        if (@preg_match($regex, '') === false) {
            wp_die(__('Regex inválido. Verifique a expressão e tente novamente.', 'wp-users-export'));
        }
    }

    // Filenames
    $base_name = 'usuarios-' . date('Y-m-d_H-i-s');
    $csv_name = $base_name . '.csv';
    $zip_name = $base_name . '.zip';

    // Create temp CSV file
    $tmp_csv = function_exists('wp_tempnam') ? wp_tempnam($csv_name) : tempnam(sys_get_temp_dir(), 'wpue_');
    if (!$tmp_csv) {
        wp_die(__('Não foi possível criar arquivo temporário para exportação.', 'wp-users-export'));
    }

    $output = fopen($tmp_csv, 'w');
    if (!$output) {
        @unlink($tmp_csv);
        wp_die(__('Falha ao abrir arquivo temporário para escrita.', 'wp-users-export'));
    }

    // BOM for UTF-8 (helps Excel on Windows)
    fprintf($output, "\xEF\xBB\xBF");

    // CSV header row
    fputcsv($output, [
        'ID',
        'user_login',
        'first_name',
        'last_name',
        'display_name',
        'user_email',
        'roles',
        'user_registered',
        'posts_count',
        'comments_count',
        'user_url',
    ]);

    $paged = 1;
    $per_page = 500; // batch to avoid memory spikes

    do {
        $args = [
            'number' => $per_page,
            'paged' => $paged,
            'fields' => [ 'ID', 'user_login', 'user_email', 'display_name', 'user_registered', 'user_url' ],
        ];
        if ($use_date_query) {
            $args['date_query'] = array_merge(['column' => 'user_registered'], $date_query);
        }
        $user_query = new WP_User_Query($args);
        $users = $user_query->get_results();

        if (empty($users)) {
            break;
        }

        foreach ($users as $user) {
            // Regex filter on email, login, display_name
            if ($regex !== '') {
                $haystack = ($user->user_email ?? '') . "\n" . ($user->user_login ?? '') . "\n" . ($user->display_name ?? '');
                if (!preg_match($regex, $haystack)) {
                    continue;
                }
            }
            $user_id = $user->ID;
            $first_name = get_user_meta($user_id, 'first_name', true);
            $last_name = get_user_meta($user_id, 'last_name', true);
            $roles = [];
            if ($user instanceof WP_User) {
                $roles = $user->roles;
            } else {
                // Fetch full user for roles if object is stdClass
                $u = get_user_by('ID', $user_id);
                $roles = $u ? $u->roles : [];
            }
            $roles_str = implode('|', $roles);

            $posts_count = function_exists('count_user_posts') ? count_user_posts($user_id) : 0;
            $comments_count = get_comments([
                'user_id' => $user_id,
                'count' => true,
                'status' => 'approve',
            ]);

            fputcsv($output, [
                $user_id,
                isset($user->user_login) ? $user->user_login : get_userdata($user_id)->user_login,
                $first_name,
                $last_name,
                $user->display_name,
                $user->user_email,
                $roles_str,
                $user->user_registered,
                intval($posts_count),
                intval($comments_count),
                $user->user_url,
            ]);
        }

        $paged++;
    } while (count($users) === $per_page);

    fclose($output);

    // Create ZIP
    $zip = new ZipArchive();
    $tmp_zip = function_exists('wp_tempnam') ? wp_tempnam($zip_name) : tempnam(sys_get_temp_dir(), 'wpue_');
    if (!$tmp_zip) {
        @unlink($tmp_csv);
        wp_die(__('Não foi possível criar arquivo temporário ZIP.', 'wp-users-export'));
    }
    if ($zip->open($tmp_zip, ZipArchive::OVERWRITE) !== true) {
        @unlink($tmp_csv);
        @unlink($tmp_zip);
        wp_die(__('Falha ao criar o arquivo ZIP.', 'wp-users-export'));
    }
    $zip->addFile($tmp_csv, $csv_name);
    $zip->close();

    // Output ZIP
    nocache_headers();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename=' . $zip_name);
    header('Content-Length: ' . filesize($tmp_zip));
    readfile($tmp_zip);

    // Cleanup
    @unlink($tmp_csv);
    @unlink($tmp_zip);
    // Update last export timestamp
    update_option('wpue_last_export_users_csv', time());
    exit;
}

/**
 * Handle TXT export of all emails (one per line)
 */
function wpue_handle_export_emails_txt() {
    if (!current_user_can('list_users')) {
        wp_die(__('Você não tem permissão para exportar usuários.', 'wp-users-export'));
    }

    if (!isset($_POST['wpue_nonce']) || !wp_verify_nonce($_POST['wpue_nonce'], 'wpue_export_emails_txt')) {
        wp_die(__('Nonce inválido. Atualize a página e tente novamente.', 'wp-users-export'));
    }

    if (!class_exists('ZipArchive')) {
        wp_die(__('O PHP ZIP (ZipArchive) não está disponível no servidor. Habilite a extensão zip para exportar em .zip.', 'wp-users-export'));
    }

    // Read filters
    $only_new = !empty($_POST['wpue_only_new']);
    $start_date = isset($_POST['wpue_start_date']) ? sanitize_text_field(wp_unslash($_POST['wpue_start_date'])) : '';
    $end_date   = isset($_POST['wpue_end_date']) ? sanitize_text_field(wp_unslash($_POST['wpue_end_date'])) : '';
    $regex_raw  = isset($_POST['wpue_regex']) ? trim((string) wp_unslash($_POST['wpue_regex'])) : '';

    // Build date_query based on filters
    $date_query = [];
    if ($only_new) {
        $last = get_option('wpue_last_export_emails_txt');
        if (!empty($last)) {
            $date_query['after'] = gmdate('Y-m-d H:i:s', intval($last));
        }
    } elseif (!empty($start_date) || !empty($end_date)) {
        if (!empty($start_date)) {
            $date_query['after'] = $start_date . ' 00:00:00';
        }
        if (!empty($end_date)) {
            $date_query['before'] = $end_date . ' 23:59:59';
        }
        $date_query['inclusive'] = true;
    }
    $use_date_query = !empty($date_query);

    // Prepare regex (case-insensitive)
    $regex = '';
    if ($regex_raw !== '') {
        $regex = '~' . $regex_raw . '~i';
        if (@preg_match($regex, '') === false) {
            wp_die(__('Regex inválido. Verifique a expressão e tente novamente.', 'wp-users-export'));
        }
    }

    $base_name = 'emails-usuarios-' . date('Y-m-d_H-i-s');
    $txt_name = $base_name . '.txt';
    $zip_name = $base_name . '.zip';

    // Create temp TXT file
    $tmp_txt = function_exists('wp_tempnam') ? wp_tempnam($txt_name) : tempnam(sys_get_temp_dir(), 'wpue_');
    if (!$tmp_txt) {
        wp_die(__('Não foi possível criar arquivo temporário para exportação.', 'wp-users-export'));
    }
    $fh = fopen($tmp_txt, 'w');
    if (!$fh) {
        @unlink($tmp_txt);
        wp_die(__('Falha ao abrir arquivo temporário para escrita.', 'wp-users-export'));
    }

    $paged = 1;
    $per_page = 1000;

    do {
        $args = [
            'number' => $per_page,
            'paged' => $paged,
            'fields' => [ 'ID', 'user_login', 'display_name', 'user_email', 'user_registered' ],
        ];
        if ($use_date_query) {
            $args['date_query'] = array_merge(['column' => 'user_registered'], $date_query);
        }
        $user_query = new WP_User_Query($args);
        $users = $user_query->get_results();

        if (empty($users)) {
            break;
        }

        foreach ($users as $user) {
            // Regex filter on email, login, display_name
            if ($regex !== '') {
                $haystack = ($user->user_email ?? '') . "\n" . ($user->user_login ?? '') . "\n" . ($user->display_name ?? '');
                if (!preg_match($regex, $haystack)) {
                    continue;
                }
            }
            if (!empty($user->user_email)) {
                fwrite($fh, $user->user_email . "\n");
            }
        }

        $paged++;
    } while (count($users) === $per_page);

    fclose($fh);

    // Create ZIP
    $zip = new ZipArchive();
    $tmp_zip = function_exists('wp_tempnam') ? wp_tempnam($zip_name) : tempnam(sys_get_temp_dir(), 'wpue_');
    if (!$tmp_zip) {
        @unlink($tmp_txt);
        wp_die(__('Não foi possível criar arquivo temporário ZIP.', 'wp-users-export'));
    }
    if ($zip->open($tmp_zip, ZipArchive::OVERWRITE) !== true) {
        @unlink($tmp_txt);
        @unlink($tmp_zip);
        wp_die(__('Falha ao criar o arquivo ZIP.', 'wp-users-export'));
    }
    $zip->addFile($tmp_txt, $txt_name);
    $zip->close();

    // Output ZIP
    nocache_headers();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename=' . $zip_name);
    header('Content-Length: ' . filesize($tmp_zip));
    readfile($tmp_zip);

    // Cleanup
    @unlink($tmp_txt);
    @unlink($tmp_zip);
    // Update last export timestamp
    update_option('wpue_last_export_emails_txt', time());
    exit;
}
