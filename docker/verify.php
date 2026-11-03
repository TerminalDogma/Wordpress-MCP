<?php
/** Run with: wp eval-file /opt/wordpress/verify.php */
if (!function_exists('acf_add_local_field_group') || !function_exists('update_field')) {
    throw new RuntimeException('ACF is not active.');
}

acf_add_local_field_group([
    'key' => 'group_installation_smoke',
    'title' => 'Installation smoke test',
    'fields' => [[
        'key' => 'field_installation_smoke',
        'label' => 'Smoke test',
        'name' => 'installation_smoke',
        'type' => 'text',
    ]],
    'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'page']]],
]);

$site_ids = [get_current_blog_id()];
if (is_multisite()) {
    $sites = get_sites(['number' => 0]);
    $paths = wp_list_pluck($sites, 'path');
    foreach (['/', '/site-a/', '/site-b/'] as $path) {
        if (!in_array($path, $paths, true)) {
            throw new RuntimeException('Missing test site: ' . $path);
        }
    }
    if (!is_plugin_active_for_network('advanced-custom-fields/acf.php')) {
        throw new RuntimeException('ACF is not network activated.');
    }
    $site_ids = wp_list_pluck($sites, 'blog_id');
}

foreach ($site_ids as $site_id) {
    if (is_multisite()) {
        switch_to_blog((int) $site_id);
    }
    try {
        $post_id = wp_insert_post([
            'post_title' => 'Temporary ACF installation check',
            'post_type' => 'page',
            'post_status' => 'draft',
        ], true);
        if (is_wp_error($post_id)) {
            throw new RuntimeException($post_id->get_error_message());
        }
        try {
            $value = 'site-' . $site_id . '-' . wp_generate_password(16, false);
            update_field('field_installation_smoke', $value, $post_id);
            acf_flush_value_cache($post_id, 'installation_smoke');
            wp_cache_delete($post_id, 'post_meta');
            if (get_field('installation_smoke', $post_id) !== $value) {
                throw new RuntimeException('ACF database round-trip failed on site ' . $site_id);
            }
            WP_CLI::success('ACF database write/read verified on site ' . $site_id);
        } finally {
            wp_delete_post($post_id, true);
        }
    } finally {
        if (is_multisite()) {
            restore_current_blog();
        }
    }
}
