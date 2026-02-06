<?php

/**
 * Network Settings page for the AltText.ai plugin
 *
 * @link       https://www.alttext.ai
 * @since      1.10.16
 *
 * @package    AltText_AI
 * @subpackage AltText_AI/admin/partials
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
  die;
}
?>

<div class="wrap">
  <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
  
  <?php if ( isset( $_GET['updated'] ) && sanitize_text_field( wp_unslash( $_GET['updated'] ) ) === 'true' ) : ?>
    <div class="notice notice-success is-dismissible">
      <p><?php esc_html_e('Network settings saved successfully.', 'alttext-ai'); ?></p>
    </div>
  <?php endif; ?>
  
  <div class="atai-network-settings-container">
    <form method="post" action="edit.php?action=atai_update_network_settings">
      <?php wp_nonce_field('atai_network_settings_nonce', 'atai_network_settings_nonce'); ?>
      
      <div class="atai-card mb-8">
        <div class="atai-card-header">
          <h2 class="atai-card-title"><?php esc_html_e('Network Settings', 'alttext-ai'); ?></h2>
          <p class="atai-card-description"><?php esc_html_e('Configure network-wide settings for AltText.ai', 'alttext-ai'); ?></p>
        </div>
        
        <div class="atai-card-body">
          <div class="mb-6">
            <h3 class="text-lg font-medium mb-2"><?php esc_html_e('API Key Management', 'alttext-ai'); ?></h3>
            
            <div class="mb-4 flex items-center relative gap-x-2">

                <input
                  id="atai_network_api_key"
                  name="atai_network_api_key"
                  type="checkbox"
                  value="yes"
                  class="w-4 h-4 !m-0 rounded border-gray-300 checked:bg-white text-primary-600 focus:ring-primary-600"
                  <?php checked('yes', get_site_option('atai_network_api_key', 'no')); ?>
                >
                <label for="atai_network_api_key" class="text-gray-600"><?php esc_html_e('Apply main site API key to all subsites', 'alttext-ai'); ?></label>

            </div>
            <div class="-mt-1 text-sm leading-6">
              <p class="text-xs text-gray-500 mt-1"><?php esc_html_e('When enabled, all subsites will use the API key from the main site.', 'alttext-ai'); ?></p>
            </div>
          </div>
          
          <div class="mb-6">
            <h3 class="text-lg font-medium mb-2"><?php esc_html_e('Settings Synchronization', 'alttext-ai'); ?></h3>
            
            <div class="mb-4 flex items-center relative gap-x-2">

                <input
                  id="atai_network_all_settings"
                  name="atai_network_all_settings"
                  type="checkbox"
                  value="yes"
                  class="w-4 h-4 !m-0 rounded border-gray-300 checked:bg-white text-primary-600 focus:ring-primary-600"
                  <?php checked('yes', get_site_option('atai_network_all_settings', 'no')); ?>
                >
                <label for="atai_network_all_settings" class="text-gray-600"><?php esc_html_e('Apply all settings from main site to all subsites', 'alttext-ai'); ?></label>

              </div>
              <div class="-mt-1 text-sm leading-6">
                <p class="text-xs text-gray-500 mt-1"><?php esc_html_e('When enabled, all plugin settings from the main site will be applied to all subsites. Settings on subsites will be disabled and they will use the main site settings.', 'alttext-ai'); ?></p>
              </div>
          </div>
          
          <div class="mb-6">
            <h3 class="text-lg font-medium mb-2"><?php esc_html_e('Credits Display', 'alttext-ai'); ?></h3>
            
            <div class="mb-4 flex items-center relative gap-x-2">
                <input
                  id="atai_network_hide_credits"
                  name="atai_network_hide_credits"
                  type="checkbox"
                  value="yes"
                  class="w-4 h-4 !m-0 rounded border-gray-300 checked:bg-white text-primary-600 focus:ring-primary-600"
                  <?php checked('yes', get_site_option('atai_network_hide_credits', 'no')); ?>
                >
                <label for="atai_network_hide_credits" class="text-gray-600"><?php esc_html_e('Hide credits display on subsites', 'alttext-ai'); ?></label>
              </div>
              <div class="-mt-1 text-sm leading-6">
                <p class="text-xs text-gray-500 mt-1"><?php esc_html_e('When enabled, the "You have X credits available out of Y" message will be hidden on all subsites.', 'alttext-ai'); ?></p>
              </div>
          </div>
        </div>
      </div>
      
      <div class="atai-form-actions">
        <button type="submit" class="button button-primary"><?php esc_html_e('Save Network Settings', 'alttext-ai'); ?></button>
      </div>
    </form>
  </div>
</div>
