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
   */
  public static function activate() {

    // Create the database table
    require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-atai-database.php';
    $database = new ATAI_Database();
    $database->check_database_schema();

    // Set the atai_public option if not set already:
    if ( get_option( 'atai_public' ) === false ) {
      update_option( 'atai_public', ATAI_Utility::is_publicly_accessible() ? 'yes' : 'no' );
    }

    // Set a transient to trigger the setup instruction notice:
    set_transient( 'atai_show_setup_notice', true, MINUTE_IN_SECONDS );
  }
}
