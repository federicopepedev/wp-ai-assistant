<?php
/*
 * Plugin Name:       AI Assistant: GPT ChatBot
 * Plugin URI:        https://github.com/federicopepedev/wp-ai-assistant
 * Description:       Integrates an AI-driven chat feature on your WordPress site
 * Version:           1.2.0
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
    // AI Assistant options
    add_option('ai_assistant_api_key', '');
    add_option('ai_assistant_model', 'gpt-5');
    add_option('ai_assistant_system', 'You are a helpful assistant.');
    add_option('ai_assistant_welcome_message', 'How can I assist you today?');
    add_option('ai_assistant_header_bg', '#000000');
    add_option('ai_assistant_icon_bg', '#000000');
    add_option('ai_assistant_streaming', '1');
    add_option('ai_assistant_max_history', '20');
    // Register AI Assistant options
    register_setting('ai_assistant_options_group', 'ai_assistant_api_key', 'sanitize_text_field');
    register_setting('ai_assistant_options_group', 'ai_assistant_model', 'sanitize_text_field');
    register_setting('ai_assistant_options_group', 'ai_assistant_system', 'sanitize_textarea_field');
    register_setting('ai_assistant_options_group', 'ai_assistant_welcome_message', 'sanitize_textarea_field');
    register_setting('ai_assistant_options_group', 'ai_assistant_streaming', 'absint');
    register_setting('ai_assistant_options_group', 'ai_assistant_max_history', 'absint');
    register_setting('ai_assistant_style_options_group', 'ai_assistant_header_bg', 'sanitize_hex_color');
    register_setting('ai_assistant_style_options_group', 'ai_assistant_icon_bg', 'sanitize_hex_color');
}
add_action('admin_init', 'ai_assistant_register_settings');

// AI Assistant admin menu
function ai_assistant_admin_menu() {
    add_menu_page('AI Assistant Settings', 'AI Assistant', 'manage_options', 'ai-assistant', 'ai_assistant_admin_page');
}

// AI Assistant admin page
function ai_assistant_admin_page() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have sufficient permissions to access this page.'));
    }
    include_once plugin_dir_path(__FILE__) . 'admin/ai-assistant-admin-page.php';
}
add_action('admin_menu', 'ai_assistant_admin_menu');

// Enqueue admin assets
function ai_assistant_enqueue_admin_assets($hook) {
    if ($hook !== 'toplevel_page_ai-assistant') {
        return;
    }
    wp_enqueue_style('ai-assistant-admin-css', plugin_dir_url(__FILE__) . 'admin/ai-assistant-admin.css', array(), '1.2.0');
}
add_action('admin_enqueue_scripts', 'ai_assistant_enqueue_admin_assets');

// Enqueue assets
function ai_assistant_enqueue_assets() {
    // Bootstrap
    wp_enqueue_style('bootstrap-css', plugin_dir_url(__FILE__) . 'public/css/bootstrap.min.css', array(), '5.3.3');
    wp_enqueue_script('bootstrap-js', plugin_dir_url(__FILE__) . 'public/js/bootstrap.min.js', array(), '5.3.3', true);
    // DOMPurify
    wp_enqueue_script('dompurify-js', plugin_dir_url(__FILE__) . 'public/js/purify.min.js', array(), '3.2.4', true);
    // Font Awesome
    wp_enqueue_style('fontawesome-css', plugin_dir_url(__FILE__) . 'public/css/all.min.css', array(), '6.5.2');
    // Custom assets
    wp_enqueue_style('ai-assistant-css', plugin_dir_url(__FILE__) . 'public/css/style.css', array(), '1.2.0');
    wp_enqueue_script('ai-assistant-js', plugin_dir_url(__FILE__) . 'public/js/script.js', array(), '1.2.0', true);
    // Pass config to JS
    wp_localize_script('ai-assistant-js', 'aiAssistant', [
        'ajax_url'    => admin_url('admin-ajax.php'),
        'nonce'       => wp_create_nonce('ai-assistant-nonce'),
        'images'      => plugin_dir_url(__FILE__) . 'public/images/',
        'streaming'   => get_option('ai_assistant_streaming', '1'),
        'max_history' => (int) get_option('ai_assistant_max_history', 20),
    ]);
}
add_action('wp_enqueue_scripts', 'ai_assistant_enqueue_assets');

// Chat markup
function ai_assistant_markup() {
    // Include chat.php
    include_once plugin_dir_path(__FILE__) . 'public/chat.php';
}
add_action('wp_footer', 'ai_assistant_markup');

// Shared validation and rate-limit logic. Returns $user_message or calls wp_send_json_error and returns false.
function ai_assistant_validate_request() {
    check_ajax_referer('ai-assistant-nonce', 'nonce');

    $yourApiKey = get_option('ai_assistant_api_key');
    $model      = get_option('ai_assistant_model');

    if (empty($yourApiKey) || empty($model)) {
        wp_send_json_error('API Key or model is not set.');
        return false;
    }

    if (!isset($_POST['user_message'])) {
        wp_send_json_error('Invalid request.');
        return false;
    }

    $user_message = sanitize_text_field(wp_unslash($_POST['user_message']));

    if (empty($user_message) || strlen($user_message) >= 10000) {
        wp_send_json_error('Please enter a valid message.');
        return false;
    }

    $ip    = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'] ?? ''));
    $key   = 'ai_assistant_req_' . md5($ip);
    $count = (int) get_transient($key);

    if ($count >= 30) {
        wp_send_json_error('Too many requests. Please wait.');
        return false;
    }

    set_transient($key, $count + 1, 60);

    return $user_message;
}

// Build the messages array for the OpenAI API, including conversation history.
function ai_assistant_build_messages($system, $user_message) {
    $messages = [['role' => 'system', 'content' => $system]];

    if (!empty($_POST['history'])) {
        $max_history   = max(2, min(40, (int) get_option('ai_assistant_max_history', 20)));
        $allowed_roles = ['user', 'assistant'];
        $raw_history   = json_decode(wp_unslash($_POST['history']), true);

        if (is_array($raw_history)) {
            foreach (array_slice($raw_history, -$max_history) as $entry) {
                if (
                    isset($entry['role'], $entry['content']) &&
                    in_array($entry['role'], $allowed_roles, true)
                ) {
                    $messages[] = [
                        'role'    => $entry['role'],
                        'content' => sanitize_textarea_field($entry['content']),
                    ];
                }
            }
        }
    }

    $messages[] = ['role' => 'user', 'content' => $user_message];

    return $messages;
}

// Handle AJAX request (fallback, non-streaming)
function ai_assistant_handle_request() {
    $user_message = ai_assistant_validate_request();
    if ($user_message === false) {
        return;
    }

    $yourApiKey = get_option('ai_assistant_api_key');
    $model      = get_option('ai_assistant_model');
    $system     = get_option('ai_assistant_system');
    $messages   = ai_assistant_build_messages($system, $user_message);

    $client = OpenAI::client($yourApiKey);
    try {
        $result      = $client->chat()->create(['model' => $model, 'messages' => $messages]);
        $message_raw = $result->choices[0]->message->content;
        wp_send_json_success(wp_kses_post($message_raw));
    } catch (Exception $e) {
        wp_send_json_error('Failed to connect to OpenAI API.');
    }
}
add_action('wp_ajax_ai_assistant_chat', 'ai_assistant_handle_request');
add_action('wp_ajax_nopriv_ai_assistant_chat', 'ai_assistant_handle_request');

// Handle streaming AJAX request
function ai_assistant_handle_stream() {
    $user_message = ai_assistant_validate_request();
    if ($user_message === false) {
        return;
    }

    $yourApiKey = get_option('ai_assistant_api_key');
    $model      = get_option('ai_assistant_model');
    $system     = get_option('ai_assistant_system');
    $messages   = ai_assistant_build_messages($system, $user_message);

    // Clear output buffers and set SSE headers
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: text/event-stream');
    header('Cache-Control: no-cache');
    header('X-Accel-Buffering: no');

    $client = OpenAI::client($yourApiKey);

    try {
        $stream = $client->chat()->createStreamed(['model' => $model, 'messages' => $messages]);

        foreach ($stream as $response) {
            $text = $response->choices[0]->delta->content ?? '';
            if ($text !== '') {
                echo 'data: ' . wp_json_encode(['text' => $text]) . "\n\n";
                flush();
            }
        }

        echo "data: [DONE]\n\n";
        flush();
    } catch (Exception $e) {
        echo 'data: ' . wp_json_encode(['error' => 'Failed to connect to OpenAI API.']) . "\n\n";
        flush();
    }

    wp_die();
}
add_action('wp_ajax_ai_assistant_stream', 'ai_assistant_handle_stream');
add_action('wp_ajax_nopriv_ai_assistant_stream', 'ai_assistant_handle_stream');