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

$post_id = wp_insert_post([
    'post_title' => 'Temporary ACF installation check',
    'post_type' => 'page',
    'post_status' => 'draft',
], true);
if (is_wp_error($post_id)) {
    throw new RuntimeException($post_id->get_error_message());
}

try {
    $value = 'acf-' . wp_generate_password(16, false);
    update_field('field_installation_smoke', $value, $post_id);
    if (get_field('installation_smoke', $post_id) !== $value) {
        throw new RuntimeException('ACF field did not round-trip through the database.');
    }
    WP_CLI::success('WordPress database and ACF field write/read verified.');
} finally {
    wp_delete_post($post_id, true);
}
