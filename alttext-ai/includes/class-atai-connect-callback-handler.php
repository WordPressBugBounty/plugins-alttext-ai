<?php
/**
 * The file that completes the AltText.ai connect handshake.
 *
 * @link       https://alttext.ai
 * @since      1.10.37
 *
 * @package    ATAI
 * @subpackage ATAI/includes
 */

/**
 * Connect Callback Handler class.
 *
 * Validates the returned state against the stored transient, exchanges
 * the one-time code for an API key server-to-server, and stores the
 * result.
 *
 * @package    ATAI
 * @subpackage ATAI/includes
 * @author     AltText.ai <info@alttext.ai>
 */
if ( ! class_exists( 'ATAI_Connect_Callback_Handler' ) ) {
class ATAI_Connect_Callback_Handler {
  /**
   * The exchange endpoint URL.
   *
   * @since    1.10.37
   * @access   private
   * @var      string    $exchange_url
   */
  private $exchange_url;

  /**
   * Initialize the class and set its properties.
   *
   * @since    1.10.37
   */
  public function __construct() {
    $this->exchange_url = 'https://alttext.ai/api/v1/integrations/wordpress/exchange';
  }

  /**
   * Handle the browser redirect back from the AltText.ai authorize page.
   *
   * @since 1.10.37
   * @access public
   */
  public function handle_callback() {
    if ( ! isset( $_GET['atai_callback'] ) || $_GET['atai_callback'] !== '1' ) {
      return;
    }

    $required_capability = ATAI_Utility::get_setting( 'atai_admin_capability', 'manage_options' );
    if ( ! current_user_can( $required_capability ) ) {
      wp_die( esc_html__( 'You do not have permission to perform this action.', 'alttext-ai' ) );
    }

    if ( ! isset( $_GET['code'] ) || ! isset( $_GET['state'] ) ) {
      return $this->fail_and_redirect();
    }

    $code  = sanitize_text_field( wp_unslash( $_GET['code'] ) );
    $state = sanitize_text_field( wp_unslash( $_GET['state'] ) );

    $stored_state = get_transient( ATAI_Connect_Service::STATE_TRANSIENT_PREFIX . get_current_user_id() );

    if ( empty( $stored_state ) || ! hash_equals( $stored_state, $state ) ) {
      return $this->fail_and_redirect();
    }

    delete_transient( ATAI_Connect_Service::STATE_TRANSIENT_PREFIX . get_current_user_id() );

    $response = wp_remote_post(
      $this->exchange_url,
      array(
        'headers' => array(
          'Content-Type' => 'application/json',
          'X-Client'     => 'wordpress/' . ATAI_VERSION,
        ),
        'timeout' => 15,
        'body'    => wp_json_encode(
          array(
            'code'     => $code,
            'state'    => $state,
            'site_url' => home_url(),
          )
        ),
      )
    );

    if ( ! is_array( $response ) || is_wp_error( $response ) ) {
      return $this->fail_and_redirect();
    }

    $response_code = (int) wp_remote_retrieve_response_code( $response );
    if ( substr( (string) $response_code, 0, 1 ) !== '2' ) {
      return $this->fail_and_redirect();
    }

    $body = json_decode( wp_remote_retrieve_body( $response ), true );
    if ( ! is_array( $body ) || empty( $body['api_key'] ) ) {
      return $this->fail_and_redirect();
    }

    update_option( 'atai_api_key', sanitize_text_field( $body['api_key'] ), false );

    wp_safe_redirect(
      add_query_arg(
        array(
          'page'           => 'atai',
          'atai_connected' => '1',
        ),
        admin_url( 'admin.php' )
      )
    );
    exit;
  }

  /**
   * Redirect back to the settings page after a failed handshake.
   *
   * @since 1.10.37
   * @access private
   */
  private function fail_and_redirect() {
    wp_safe_redirect(
      add_query_arg(
        array(
          'page'                 => 'atai',
          'atai_connect_failed'  => '1',
        ),
        admin_url( 'admin.php' )
      )
    );
    exit;
  }

  /**
   * Display a failure notice when the connect handshake could not complete.
   *
   * @since 1.10.37
   * @access public
   */
  public function display_connect_failure_notice() {
    if ( ! isset( $_GET['atai_connect_failed'] ) || $_GET['atai_connect_failed'] !== '1' ) {
      return;
    }

    echo '<div class="notice notice--atai notice-error is-dismissible"><p>';
    echo esc_html__( '[AltText.ai] Could not connect to AltText.ai. Please try again.', 'alttext-ai' );
    echo '</p></div>';
  }
}
} // End if class_exists check
