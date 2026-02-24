<?php
/**
 * The file that connects with the AltText.ai API
 *
 *
 * @link       https://alttext.ai
 * @since      1.0.0
 *
 * @package    ATAI
 * @subpackage ATAI/includes
 */

/**
 * The API management class.
 *
 * This is used to connect with the AltText.ai API.
 *
 *
 * @since      1.0.0
 * @package    ATAI
 * @subpackage ATAI/includes
 * @author     AltText.ai <info@alttext.ai>
 */
if ( ! class_exists( 'ATAI_API' ) ) {
class ATAI_API {
  /**
	 * The API key used to connect wit hte client.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $api_key    The API key for connecting with the client.
	 */
	private $api_key;

  /**
	 * The API client URL.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $base_url    The base URL of the API client.
	 */
	private $base_url;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct( $api_key ) {
    $this->api_key = $api_key;
    $this->base_url = 'https://alttext.ai/api/v1';
	}

  /**
   * Fetch account information.
   *
   * @since 1.0.0
   * @access public
   */
  public function get_account() {
    $response = wp_remote_get(
      $this->base_url . '/account',
      array(
        'headers'       => array(
          'Content-Type'  => 'application/json',
          'X-Api-Key'     => $this->api_key,
          'X-Client'      => 'wordpress/' . ATAI_VERSION
        )
      )
    );

    if ( ! is_array( $response ) || is_wp_error( $response ) ) {
      return false;
    }

    /**
     * Response code is not 2xx
     */
    if ( substr( wp_remote_retrieve_response_code( $response ), 0, 1 ) != '2' ) {
      return false;
    }

    $response = json_decode( wp_remote_retrieve_body( $response ), true );
    $account = array(
      'plan'          => 'Free Trial',
      'expires_at'    => 'never',
      'usage'         => $response['usage'],
      'quota'         => $response['usage_limit'],
      'whitelabel'    => $response['whitelabel'] ?? false,
      'available'     => ( $response['usage_limit'] > $response['usage'] ) ? $response['usage_limit'] - $response['usage'] : 0,
    );

    // Plan is other than Free Trial
    if ( $response['subscription'] !== null ) {
      $account['plan']        = $response['subscription']['plan_name'];
      $account['expires_at']  = $response['subscription']['expires_at'];
    }

    if ( $account['available'] > 0 ) {
      delete_transient( 'atai_insufficient_credits' );
    }

    return $account;
  }

  /**
   * Get alt text for image.
   *
   * @since 1.0.0
   * @access public
   *
   * @param string  $attachment_id  ID of the image to request alt text for (or NULL to use just URL).
   * @param string  $attachment_url  URL of the image to request alt text for.
   */
  public function create_image( $attachment_id, $attachment_url, $api_options, &$response_code ) {
    if ( empty($attachment_id) || ATAI_Utility::get_setting( 'atai_public' ) === 'yes' ) {
      // If the site is public, get ALT by sending the image URL to the server
      $body = array(
        'webhook_url' => '',
        'image' => array(
          'url' => $attachment_url
        )
      );
    } else {
      // If the site is private, get ALT by sending the image file (base64) to the server
      $file_path = get_attached_file( $attachment_id );
      
      // Validate file exists and is readable before attempting to read
      if ( ! $file_path || ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
        // Fallback to URL for offloaded media (S3, Cloudinary, etc.)
        if ( ! empty( $attachment_url ) ) {
          error_log( "ATAI: File not accessible for attachment {$attachment_id}" ); // phpcs:ignore QITStandard.PHP.DebugCode.DebugFunctionFound -- Production error logging
          $body = array(
            'webhook_url' => '',
            'image' => array(
              'url' => $attachment_url
            )
          );
        } else {
          error_log( "ATAI: Attachment #{$attachment_id}: Local file not accessible and no URL available." ); // phpcs:ignore QITStandard.PHP.DebugCode.DebugFunctionFound -- Production error logging
          return false;
        }
      } else {
        // Use WordPress functions when possible, with error handling
        $file_contents = @file_get_contents( $file_path );
        if ( $file_contents === false ) {
          // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, QITStandard.PHP.DebugCode.DebugFunctionFound -- Production error logging
          error_log( "ATAI: Failed to read file for attachment {$attachment_id}" );
          return false;
        }

        $encoded_content = @base64_encode( $file_contents );
        if ( $encoded_content === false ) {
          // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log, QITStandard.PHP.DebugCode.DebugFunctionFound -- Production error logging
          error_log( "ATAI: Failed to encode file for attachment {$attachment_id}" );
          return false;
        }

        $body = array(
          'image' => array(
            'raw' => $encoded_content
          )
        );

        // Clean up memory immediately after encoding
        unset( $file_contents, $encoded_content );
      }
    }

    $body = array_merge( $body, $api_options );
    $timeout_secs = intval(ATAI_Utility::get_setting( 'atai_timeout', 20 ));
    $response = wp_remote_post(
      $this->base_url . '/images',
      array(
        'headers'       => array(
          'Content-Type'  => 'application/json',
          'X-Api-Key'     => $this->api_key,
          'X-Client'      => 'wordpress/' . ATAI_VERSION
        ),
        'timeout' => $timeout_secs,
        'body'          => wp_json_encode( $body )
      )
    );
    $response_code = wp_remote_retrieve_response_code( $response );

    $attachment_edit_url = empty($attachment_id) ? $attachment_url : get_edit_post_link( $attachment_id );

    if ( ! is_array( $response ) || is_wp_error( $response ) ) {
      if ( defined( 'ATAI_DEBUG' ) && ATAI_DEBUG ) {
        error_log( print_r( $response, true ) ); // phpcs:ignore QITStandard.PHP.DebugCode.DebugFunctionFound -- Conditional debug logging
      }

      ATAI_Utility::log_error(
        sprintf(
          '<a href="%s" target="_blank">Image #%d</a>: %s',
          esc_url( $attachment_edit_url ),
          (int) $attachment_id,
          esc_html__( 'Unknown error.', 'alttext-ai' )
        )
      );

      return false;
    }

    $response_body = json_decode( wp_remote_retrieve_body( $response ), true );

    if ( $response_code == '422' ) {
      $error_message = '';

      foreach( $response_body['errors'] as $key => $error ) {
        $error_message = $error[0];
        break;
      }

      if ( $error_message === 'account has insufficient credits' ) {
        if ( defined( 'ATAI_DEBUG' ) && ATAI_DEBUG ) {
          error_log( print_r( $response, true ) ); // phpcs:ignore QITStandard.PHP.DebugCode.DebugFunctionFound -- Conditional debug logging
        }

        ATAI_Utility::log_error(
          sprintf(
            '[%d] <a href="%s" target="_blank">Image #%d</a>: %s',
            (int) $response_code,
            esc_url( $attachment_edit_url ),
            (int) $attachment_id,
            esc_html( $error_message )
          )
        );

        if ( ATAI_Utility::get_setting( 'atai_no_credit_warning' ) != 'yes' ) {
          set_transient( 'atai_insufficient_credits', TRUE, MONTH_IN_SECONDS );
        }

        return 'insufficient_credits';
      }

      // Check if error indicates URL access issues (when site is marked as public but URLs aren't accessible)
      if ( ATAI_Utility::get_setting( 'atai_public' ) === 'yes' && 
           ( strpos( strtolower( $error_message ), 'unable to access' ) !== false || 
             strpos( strtolower( $error_message ), 'url not accessible' ) !== false ||
             strpos( strtolower( $error_message ), 'cannot fetch' ) !== false ||
             strpos( strtolower( $error_message ), 'failed to fetch' ) !== false ||
             strpos( strtolower( $error_message ), 'failed to open' ) !== false ||
             strpos( strtolower( $error_message ), 'tcp connection' ) !== false ||
             strpos( strtolower( $error_message ), 'getaddrinfo' ) !== false ||
             strpos( strtolower( $error_message ), 'name or service not known' ) !== false ||
             strpos( strtolower( $error_message ), 'image url' ) !== false ) ) {
        
        // Set a transient to show the suggestion
        set_transient( 'atai_url_access_suggestion_' . get_current_user_id(), array(
          'error' => $error_message,
          'attachment_id' => $attachment_id
        ), 3600 );
        
        return 'url_access_error';
      }

      error_log( print_r( $response, true ) ); // phpcs:ignore QITStandard.PHP.DebugCode.DebugFunctionFound -- Production error logging

      ATAI_Utility::log_error(
        sprintf(
          '[%d] <a href="%s" target="_blank">Image #%d</a>: %s',
          (int) $response_code,
          esc_url( $attachment_edit_url ),
          (int) $attachment_id,
          esc_html( $error_message )
        )
      );

      return false;
    } elseif ( substr( $response_code, 0, 1 ) == '4' && ATAI_Utility::get_setting( 'atai_public' ) === 'yes' ) {
      // 4xx errors when site is marked as public likely indicate URL access issues
      error_log( print_r( $response, true ) ); // phpcs:ignore QITStandard.PHP.DebugCode.DebugFunctionFound -- Production error logging
      
      // Extract error message if available
      $error_message = '';
      if ( isset( $response_body['message'] ) ) {
        $error_message = $response_body['message'];
      } elseif ( isset( $response_body['error'] ) ) {
        $error_message = $response_body['error'];
      } else {
        $error_message = sprintf( 'HTTP %d error', (int) $response_code );
      }

      ATAI_Utility::log_error(
        sprintf(
          '[%d] <a href="%s" target="_blank">Image #%d</a>: %s',
          (int) $response_code,
          esc_url( $attachment_edit_url ),
          (int) $attachment_id,
          esc_html( $error_message )
        )
      );

      // Set a transient to show the suggestion
      set_transient( 'atai_url_access_suggestion_' . get_current_user_id(), array(
        'error' => $error_message,
        'attachment_id' => $attachment_id
      ), 3600 );
      
      return 'url_access_error';
    } elseif ( substr( $response_code, 0, 1 ) != '2' ) {
      error_log( print_r( $response, true ) ); // phpcs:ignore QITStandard.PHP.DebugCode.DebugFunctionFound -- Production error logging

      ATAI_Utility::log_error(
        sprintf(
          '[%d] <a href="%s" target="_blank">Image #%d</a>: %s',
          (int) $response_code,
          esc_url( $attachment_edit_url ),
          (int) $attachment_id,
          esc_html__( 'API error.', 'alttext-ai' )
        )
      );

      return false;
    }

    return $response_body;
  }
}
} // End if class_exists check
