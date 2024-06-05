<?php
// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

// Delete option from options table
delete_option('ai_assistant_api_key');
delete_option('ai_assistant_model');
delete_option('ai_assistant_header_bg');
delete_option('ai_assistant_icon_bg');
delete_option('ai_assistant_welcome_message');
