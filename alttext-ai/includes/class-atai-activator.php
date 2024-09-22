<?php
/**
 * Fired during plugin activation
 *
 * @link       https://alttext.ai
 * @since      1.0.0
 *
 * @package    ATAI
 * @subpackage ATAI/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    ATAI
 * @subpackage ATAI/includes
 * @author     AltText.ai <info@alttext.ai>
 */
class ATAI_Activator {
  /**
   * Runs when the plugin has been activated.
   *
   * @since 1.0.33
   * @access public
   *
   * @param string $plugin The plugin that was activated.
   */
  public static function activate( $plugin ) {
    // Bail early if the plugin being activated is not ALT Text AI
    if ( $plugin != plugin_basename( ATAI_PLUGIN_FILE ) ) {
      return;
    }

    // Create the database table
    require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-atai-database.php';
    $database = new ATAI_Database();
    $database->check_database_schema();

    // If the site is publicly accessible, set the atai_public option to 'yes'
    if (ATAI_Utility::is_publicly_accessible()) {
      update_option( 'atai_public', 'yes' );
    }

    // Set a transient to trigger the setup instruction notice:
    set_transient( 'atai_show_setup_notice', true, MINUTE_IN_SECONDS );
  }
}
