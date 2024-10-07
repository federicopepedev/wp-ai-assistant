<?php 
// If this file is called directly, abort.
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

// Get current API key
$apiKey = get_option('ai_assistant_api_key');
// Get current model
$model = get_option('ai_assistant_model');
//Get system role
$system = get_option('ai_assistant_system');
// Get welcome message
$welcome_message = get_option('ai_assistant_welcome_message');

// Get the active tab from the URL, default to 'ai-settings'
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'ai-settings';
?>

<style>
    .wrap .tab-content label {
        display: block;
        margin-bottom: 0.3em;
    }
    .wrap .tab-content input,
    .wrap .tab-content textarea {
        display: block;
        width: 100%;
        max-width: 600px;
        margin-bottom: 1em;
    }
</style>

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
                <input type="text" id="ai_assistant_api_key" name="ai_assistant_api_key" value="<?php echo esc_attr($apiKey); ?>" />
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
            <?php submit_button(); ?>
        </form>
    </div>
    <!-- Style -->
    <div id="tab-style" class="tab-content" style="<?php echo $active_tab == 'style' ? '' : 'display:none;'; ?>">
        <form method="post" action="options.php">
            <?php settings_fields('ai_assistant_style_options_group'); ?>
            <p>
                <label for="ai_assistant_header_bg">Chat Header Color:</label>
                <input type="text" id="ai_assistant_header_bg" name="ai_assistant_header_bg" value="<?php echo esc_attr(get_option('ai_assistant_header_bg')); ?>" />
            </p>
            <p>
                <label for="ai_assistant_icon_bg">AI Icon Color:</label>
                <input type="text" id="ai_assistant_icon_bg" name="ai_assistant_icon_bg" value="<?php echo esc_attr(get_option('ai_assistant_icon_bg')); ?>" />
            </p>
            <?php submit_button(); ?>
        </form>
    </div>
</div>