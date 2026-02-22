<?php
/**
 * Network Bulk Generate page for the AltText.ai plugin.
 *
 * Allows super admins to view missing alt text across all
 * network sites and process them from one central location.
 *
 * @link       https://www.alttext.ai
 * @since      1.10.20
 *
 * @package    AltText_AI
 * @subpackage AltText_AI/admin/partials
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
  die;
}
?>

<div class="wrap">
  <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

  <div class="atai-network-settings-container">
    <div class="atai-card mb-8">
      <div class="atai-card-header">
        <h2 class="atai-card-title"><?php esc_html_e( 'Network Image Statistics', 'alttext-ai' ); ?></h2>
        <p class="atai-card-description"><?php esc_html_e( 'Alt text status across all sites in your network.', 'alttext-ai' ); ?></p>
      </div>

      <div class="atai-card-body">
        <div id="atai-network-stats-loading">
          <p><?php esc_html_e( 'Loading site statistics...', 'alttext-ai' ); ?></p>
        </div>

        <table id="atai-network-stats-table" class="wp-list-table widefat fixed striped" style="display: none;">
          <caption class="sr-only"><?php esc_html_e( 'Network site image statistics', 'alttext-ai' ); ?></caption>
          <thead>
            <tr>
              <th class="check-column"><input type="checkbox" id="atai-select-all" aria-label="<?php esc_attr_e( 'Select all sites', 'alttext-ai' ); ?>" /></th>
              <th><?php esc_html_e( 'Site', 'alttext-ai' ); ?></th>
              <th><?php esc_html_e( 'Total Images', 'alttext-ai' ); ?></th>
              <th><?php esc_html_e( 'Missing Alt Text', 'alttext-ai' ); ?></th>
              <th><?php esc_html_e( 'Status', 'alttext-ai' ); ?></th>
            </tr>
          </thead>
          <tbody id="atai-network-stats-body">
          </tbody>
          <tfoot>
            <tr>
              <td></td>
              <td><strong><?php esc_html_e( 'Total', 'alttext-ai' ); ?></strong></td>
              <td><strong id="atai-total-images">0</strong></td>
              <td><strong id="atai-total-missing">0</strong></td>
              <td></td>
            </tr>
          </tfoot>
        </table>

        <div id="atai-network-stats-error" role="alert" style="display: none;">
          <p class="notice notice-error"><?php esc_html_e( 'Failed to load site statistics.', 'alttext-ai' ); ?></p>
        </div>
      </div>
    </div>

    <div class="atai-card mb-8" id="atai-network-progress-card" style="display: none;">
      <div class="atai-card-header">
        <h2 class="atai-card-title"><?php esc_html_e( 'Processing Progress', 'alttext-ai' ); ?></h2>
      </div>
      <div class="atai-card-body">
        <p id="atai-network-current-site" aria-live="polite"></p>
        <div style="background: #e0e0e0; border-radius: 4px; height: 24px; margin: 10px 0; overflow: hidden;">
          <div id="atai-network-progress-bar"
               role="progressbar"
               aria-valuemin="0"
               aria-valuemax="100"
               aria-valuenow="0"
               aria-label="<?php esc_attr_e( 'Alt text generation progress', 'alttext-ai' ); ?>"
               style="background: #2271b1; height: 100%; width: 0%; transition: width 0.3s; border-radius: 4px;"></div>
        </div>
        <p id="atai-network-progress-text" aria-live="polite"><?php esc_html_e( 'Waiting to start...', 'alttext-ai' ); ?></p>
      </div>
    </div>

    <div class="atai-form-actions">
      <button type="button" id="atai-network-generate-btn" class="atai-button blue" disabled>
        <?php esc_html_e( 'Generate Alt Text for Selected Sites', 'alttext-ai' ); ?>
      </button>
      <button type="button" id="atai-network-cancel-btn" class="atai-button white" style="display: none;">
        <?php esc_html_e( 'Cancel', 'alttext-ai' ); ?>
      </button>
      <button type="button" id="atai-network-refresh-btn" class="atai-button white">
        <?php esc_html_e( 'Refresh Stats', 'alttext-ai' ); ?>
      </button>
    </div>
  </div>
</div>
