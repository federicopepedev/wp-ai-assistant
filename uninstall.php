<?php
// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

// Delete options from options table
delete_option('ai_assistant_api_key');
delete_option('ai_assistant_model');
delete_option('ai_assistant_system');
delete_option('ai_assistant_header_bg');
delete_option('ai_assistant_icon_bg');
delete_option('ai_assistant_welcome_message');
delete_option('ai_assistant_streaming');
delete_option('ai_assistant_max_history');

// Delete rate limit transients
global $wpdb;
$prefix          = $wpdb->esc_like('_transient_ai_assistant_req_');
$timeout_prefix  = $wpdb->esc_like('_transient_timeout_ai_assistant_req_');
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        $prefix . '%',
        $timeout_prefix . '%'
    )
);
