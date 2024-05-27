<?php 
// If this file is called directly, abort.
defined( 'ABSPATH' ) or die( 'No script kiddies please!' ); 
?>

<!-- AI Assistant -->
<div id="ai-assistant">
  <div class="ai-wrapper" style="background-color: <?php echo esc_attr(get_option('ai_assistant_icon_bg', '#000000')); ?>">
    AI
  </div>
</div>

<!-- AI Chat -->
<div id="ai-chat" class="card z-3 invisible">
    <div class="card-header-custom d-flex justify-content-between align-items-center p-3 text-white border-bottom-0" style="background-color: <?php echo esc_attr(get_option('ai_assistant_header_bg', '#000000')); ?>">
      <div id="ai-clear-chat"><i class="fa-solid fa-trash-can"></i></div>
      <p class="mb-0 fw-bold">AI Assistant</p>
      <div id="ai-close-chat"><i class="fa-solid fa-xmark"></i></div>
    </div>

    <div class="card-body">
      <div id="ai-chat-wrapper">
          <div class="d-flex flex-row justify-content-start mb-4">
            <img class="avatar-img" src="<?php echo esc_url(plugin_dir_url(__FILE__) . 'images/bot.png') ?>" alt="AI">
            <div class="p-3 ms-3 ai-message">
              <p class="small mb-0">How can i assist you today?</p>
            </div>
          </div>
      </div>

      <form id="ai-chat-form">
        <div class="my-3">
          <textarea id="ai-user-message" name="ai-user-message" class="form-control" rows="3" placeholder="Type your message" required></textarea>
        </div>
        <button id="ai-send-button" name="ai-send-button" type="submit" class="btn btn-primary"><i class="fa-solid fa-paper-plane"></i></button>
      </form>

    </div>
</div>
