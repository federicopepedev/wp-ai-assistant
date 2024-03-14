<?php
// If uninstall not called from WordPress, then exit.
if (!defined('WP_UNINSTALL_PLUGIN')) {
    die;
}

// Delete option from options table
delete_option('ai_assistant_api_key');
delete_option('ai_assistant_model');
