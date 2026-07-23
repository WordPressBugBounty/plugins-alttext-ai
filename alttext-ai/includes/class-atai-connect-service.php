<?php
/**
 * The file that initiates the AltText.ai connect handshake.
 *
 * @link       https://alttext.ai
 * @since      1.10.37
 *
 * @package    ATAI
 * @subpackage ATAI/includes
 */

/**
 * Connect Service class.
 *
 * Handles the "Connect to AltText.ai" button flow: generates a state
 * nonce, stores it in a short-TTL transient, and redirects the browser
 * to the AltText.ai authorize page to start the handshake.
 *
 * @package    ATAI
 * @subpackage ATAI/includes
 * @author     AltText.ai <info@alttext.ai>
 */
if ( ! class_exists( 'ATAI_Connect_Service' ) ) {
class ATAI_Connect_Service {
  /**
   * WP action query-arg value that triggers the initiate flow.
   */
  const ACTION = 'connect-wordpress';

  /**
   * Nonce action name used for both the button link and its verification.
   */
  const NONCE_ACTION = 'atai_connect_wordpress';

  /**
   * Transient key prefix for the stored state nonce (suffixed by user ID).
   */
  const STATE_TRANSIENT_PREFIX = 'atai_connect_state_';

  /**
   * How long the state nonce is valid for, in seconds.
   */
  const STATE_TTL = 600;

  /**
   * The host of the AltText.ai authorize page, allow-listed for wp_safe_redirect().
   */
  const AUTHORIZE_HOST = 'alttext.ai';

  /**
   * The AltText.ai authorize page URL.
   *
   * @since    1.10.37
   * @access   private
   * @var      string    $authorize_url
   */
  private $authorize_url;

  /**
   * Initialize the class and set its properties.
   *
   * @since    1.10.37
   */
  public function __construct() {
    $this->authorize_url = 'https://alttext.ai/connect/wordpress/new';
  }

  /**
   * Allow-list the AltText.ai host for wp_safe_redirect().
   *
   * @since 1.10.37
   * @access public
   *
   * @param array $hosts Existing allowed redirect hosts.
   * @return array
   */
  public function allow_redirect_host( $hosts ) {
    $hosts[] = self::AUTHORIZE_HOST;
    return $hosts;
  }

  /**
   * Handle the "Connect to AltText.ai" button click.
   *
   * Nonce + capability gated. Generates a state nonce, stores it in a
   * short-TTL transient, and redirects to the AltText.ai authorize page.
   *
   * @since 1.10.37
   * @access public
   */
  public function initiate_connect() {
    if ( ! isset( $_GET['atai_action'] ) || $_GET['atai_action'] !== self::ACTION ) {
      return;
    }

    $required_capability = ATAI_Utility::get_setting( 'atai_admin_capability', 'manage_options' );
    if ( ! current_user_can( $required_capability ) ) {
      wp_die( esc_html__( 'You do not have permission to perform this action.', 'alttext-ai' ) );
    }

    if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), self::NONCE_ACTION ) ) {
      wp_die(
        esc_html__( 'Security verification failed. Please refresh the page and try again.', 'alttext-ai' ),
        esc_html__( 'AltText.ai', 'alttext-ai' ),
        array( 'back_link' => true )
      );
    }

    $state = bin2hex( random_bytes( 32 ) );
    set_transient( self::STATE_TRANSIENT_PREFIX . get_current_user_id(), $state, self::STATE_TTL );

    $redirect_uri = admin_url( 'admin.php?page=atai&atai_callback=1' );

    $destination = add_query_arg(
      array(
        'state'        => $state,
        'site_url'     => rawurlencode( home_url() ),
        'redirect_uri' => rawurlencode( $redirect_uri ),
      ),
      $this->authorize_url
    );

    // allow-list the authorize host only for this handshake redirect, not admin-wide
    add_filter( 'allowed_redirect_hosts', array( $this, 'allow_redirect_host' ) );

    wp_safe_redirect( esc_url_raw( $destination ) );
    exit;
  }

  /**
   * Build the nonce-protected "Connect to AltText.ai" button URL.
   *
   * @since 1.10.37
   * @access public
   *
   * @return string
   */
  public static function get_connect_url() {
    return wp_nonce_url(
      add_query_arg( 'atai_action', self::ACTION, admin_url( 'admin.php?page=atai' ) ),
      self::NONCE_ACTION
    );
  }

  /**
   * Display a success notice after a completed connect handshake.
   *
   * @since 1.10.37
   * @access public
   */
  public function display_connect_success_notice() {
    if ( ! isset( $_GET['atai_connected'] ) || $_GET['atai_connected'] !== '1' ) {
      return;
    }

    echo '<div class="notice notice--atai notice-success is-dismissible"><p>';
    echo esc_html__( '[AltText.ai] Successfully connected to AltText.ai.', 'alttext-ai' );
    echo '</p></div>';
  }
}
} // End if class_exists check
