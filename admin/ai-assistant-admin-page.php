<?php 
// If this file is called directly, abort.
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

// Get current API key
$apiKey = get_option('ai_assistant_api_key');
// Get current model
$model = get_option('ai_assistant_model');
?>

<div class="wrapper">
    <h2>AI Assistant Settings</h2>
    <form method="post" action="options.php">
        <?php settings_fields('ai_assistant_options_group'); ?>
        <p>
            <label for="ai_assistant_api_key">API Key:</label>
            <input type="text" id="ai_assistant_api_key" name="ai_assistant_api_key" value="<?php echo esc_attr($apiKey); ?>" class="regular-text" />
        </p>
        <p>
            <label for="ai_assistant_model">Model:</label>
            <input type="text" id="ai_assistant_model" name="ai_assistant_model" value="<?php echo esc_attr($model); ?>" class="regular-text" />
        </p>
        <?php submit_button(); ?>
    </form>
</div>
