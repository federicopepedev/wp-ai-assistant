<?php
/*
 * Plugin Name:       AI Assistant: GPT ChatBot
 * Plugin URI:        https://github.com/federicopepedev/wp-ai-assistant
 * Description:       Integrates an AI-driven chat feature on your WordPress site
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.2
 * Author:            federicopepedev
 * Author URI:        http://github.com/federicopepedev/
 * License:           GPL-3.0+
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 */

 // If this file is called directly, abort.
 defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

// Require composer autoloader to enable autoload of packages.
 require_once __DIR__ . '/vendor/autoload.php';

 // AI Assistant admin settings
 function ai_assistant_register_settings() {
    add_option('ai_assistant_api_key', '');
    add_option('ai_assistant_model', 'gpt-3.5-turbo');
    register_setting('ai_assistant_options_group', 'ai_assistant_api_key', 'ai_assistant_callback');
    register_setting('ai_assistant_options_group', 'ai_assistant_model');
}
add_action('admin_init', 'ai_assistant_register_settings');

// AI Assistant admin menu
function ai_assistant_admin_menu() {
    add_menu_page('AI Assistant Settings', 'AI Assistant', 'manage_options', 'ai-assistant', 'ai_assistant_admin_page');
}

// AI Assistant admin page
function ai_assistant_admin_page() {
    // Include ai-assistant-admin-page.php
    include_once plugin_dir_path(__FILE__) . 'admin/ai-assistant-admin-page.php';
}
add_action('admin_menu', 'ai_assistant_admin_menu');

// Enqueue assets
function ai_assistant_enqueue_assets() {
    // Bootstrap
    wp_enqueue_style('bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css', array(), '5.3.2');
    wp_enqueue_script('popper-js', 'https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js', array(), '2.11.8', true);
    wp_enqueue_script('bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.min.js', array('popper-js'), '5.3.2', true);
    // DOMPurify
    wp_enqueue_script('dompurify-js', 'https://cdn.jsdelivr.net/npm/dompurify@3.0.9/dist/purify.min.js', array(), '3.0.9', true);
    // Font Awesome
    wp_enqueue_style('fontawesome-css', 'https://cdn.jsdelivr.net/npm/@fortawesome/fontawesome-free@6.5.1/css/all.min.css', array(), '6.5.1');
    // Custom assets
    wp_enqueue_style('ai-assistant-css', plugin_dir_url(__FILE__) . 'public/css/style.css', array(), '1.0.0');
    wp_enqueue_script('ai-assistant-js', plugin_dir_url(__FILE__) . 'public/js/script.js', array(), '1.0.0', true);
    // Pass nonce and images url
    wp_localize_script('ai-assistant-js', 'aiAssistant', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ai-assistant-nonce'),
        'images' => plugin_dir_url(__FILE__) . 'public/images/'
    ]);
}
add_action('wp_enqueue_scripts', 'ai_assistant_enqueue_assets');

// Chat markup
function ai_assistant_markup() {
    // Include chat.php
    include_once plugin_dir_path(__FILE__) . 'public/chat.php';
}
add_action('wp_footer', 'ai_assistant_markup');

// Handle AJAX request
function ai_assistant_handle_request() {
    // Retrieve API Key
    $yourApiKey = get_option('ai_assistant_api_key');
    // Retrieve model
    $model = get_option('ai_assistant_model');
    // Check if the API key or model is set
    if (empty($yourApiKey) || empty($model)) {
        wp_send_json_error('API Key or model is not set.');
        return;
    }
    // Init API client
    $client = OpenAI::client($yourApiKey);
    // Check nonce for security
    check_ajax_referer('ai-assistant-nonce', 'nonce');
    // Sanitize user message
    $user_message = sanitize_text_field($_POST['user_message']);
    // Check user message length
    if (isset($user_message) && !empty($user_message) && strlen($user_message) < 1000) {
        try {
            // Create chat
            $result = $client->chat()->create([
                'model' => $model,
                'messages' => [
                    ['role' => 'user', 'content' => $user_message],
                ],
            ]);
            // Send response back
            $message = $result->choices[0]->message->content;
            wp_send_json_success($message);
        } catch (Exception $e) {
            // Handle Exception
            wp_send_json_error('Failed to connect to OpenAI API.');
        }
    } else {
        wp_send_json_error('Please enter a valid message.');
        return;
    }
}
add_action('wp_ajax_ai_assistant_chat', 'ai_assistant_handle_request');
add_action('wp_ajax_nopriv_ai_assistant_chat', 'ai_assistant_handle_request');