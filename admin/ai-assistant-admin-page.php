<?php 
// If this file is called directly, abort.
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

// Get current API key
$apiKey          = get_option('ai_assistant_api_key');
// Get current model
$model           = get_option('ai_assistant_model');
// Get system role
$system          = get_option('ai_assistant_system');
// Get welcome message
$welcome_message = get_option('ai_assistant_welcome_message');
// Get streaming setting
$streaming       = get_option('ai_assistant_streaming', '1');
// Get max history setting
$max_history     = get_option('ai_assistant_max_history', '20');

// Get the active tab from the URL, sanitized, default to 'ai-settings'
$active_tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'ai-settings';
$allowed_tabs = array('ai-settings', 'style');
if (!in_array($active_tab, $allowed_tabs, true)) {
    $active_tab = 'ai-settings';
}
?>

<div class="wrap">
    <h2>AI Assistant Settings</h2>
    <h2 class="nav-tab-wrapper">
        <a href="?page=ai-assistant&tab=ai-settings" class="nav-tab <?php echo $active_tab == 'ai-settings' ? 'nav-tab-active' : ''; ?>">AI Settings</a>
        <a href="?page=ai-assistant&tab=style" class="nav-tab <?php echo $active_tab == 'style' ? 'nav-tab-active' : ''; ?>">Style</a>
    </h2>
    <!-- AI Settings -->
    <div id="tab-ai-settings" class="tab-content" style="<?php echo $active_tab == 'ai-settings' ? '' : 'display:none;'; ?>">
        <form method="post" action="options.php">
            <?php settings_fields('ai_assistant_options_group'); ?>
            <p>
                <label for="ai_assistant_api_key">API Key:</label>
                <input type="password" id="ai_assistant_api_key" name="ai_assistant_api_key" value="<?php echo esc_attr($apiKey); ?>" />
            </p>
            <p>
                <label for="ai_assistant_model">Model:</label>
                <input type="text" id="ai_assistant_model" name="ai_assistant_model" value="<?php echo esc_attr($model); ?>" />
            </p>
            <p>
                <label for="ai_assistant_system">System Role:</label>
                <input type="text" id="ai_assistant_system" name="ai_assistant_system" value="<?php echo esc_attr($system); ?>" />
            </p>
            <p>
                <label for="ai_assistant_welcome_message">Welcome Message:</label>
                <textarea id="ai_assistant_welcome_message" name="ai_assistant_welcome_message"><?php echo esc_attr($welcome_message); ?></textarea>
            </p>
            <p>
                <label for="ai_assistant_streaming">Enable Streaming:</label>
                <input type="checkbox" id="ai_assistant_streaming" name="ai_assistant_streaming" value="1" <?php checked('1', $streaming); ?> />
                <span class="description">Streams the response word by word. Disable if your hosting buffers output.</span>
            </p>
            <p>
                <label for="ai_assistant_max_history">Conversation History (messages):</label>
                <input type="number" id="ai_assistant_max_history" name="ai_assistant_max_history" value="<?php echo esc_attr($max_history); ?>" min="2" max="40" step="2" style="max-width:80px;" />
                <span class="description">How many past messages to send to the AI (2–40). Higher = more context, higher API cost.</span>
            </p>
            <?php submit_button(); ?>
        </form>
    </div>
    <!-- Style -->
    <div id="tab-style" class="tab-content" style="<?php echo $active_tab == 'style' ? '' : 'display:none;'; ?>">
        <form method="post" action="options.php">
            <?php settings_fields('ai_assistant_style_options_group'); ?>
            <p>
                <label for="ai_assistant_header_bg">Chat Header Color:</label>
                <input type="color" id="ai_assistant_header_bg" name="ai_assistant_header_bg" value="<?php echo esc_attr(get_option('ai_assistant_header_bg', '#000000')); ?>" />
            </p>
            <p>
                <label for="ai_assistant_icon_bg">AI Icon Color:</label>
                <input type="color" id="ai_assistant_icon_bg" name="ai_assistant_icon_bg" value="<?php echo esc_attr(get_option('ai_assistant_icon_bg', '#000000')); ?>" />
            </p>
            <?php submit_button(); ?>
        </form>
    </div>
</div>