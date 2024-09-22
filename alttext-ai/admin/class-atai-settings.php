<?php
/**
 * The options handling functionality of the plugin.
 *
 * @link       https://alttext.ai
 * @since      1.0.0
 *
 * @package    ATAI
 * @subpackage ATAI/admin
 */

/**
 * Options page functionality of the plugin.
 *
 * Renders the Options page, sanitizes, stores and fetches the options.
 *
 * @package    ATAI
 * @subpackage ATAI/admin
 * @author     AltText.ai <info@alttext.ai>
 */
class ATAI_Settings {
  /**
	 * The account information returned by the API.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      array/boolean    $account    The account information.
	 */
	private $account;

  private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.8.3
	 * @param      string    $version       The current version of the plugin.
	 */
	public function __construct( $version ) {
		$this->version = $version;
	}


  /**
   * Load account information from API.
   *
   * @since 1.0.0
   * @access private
   */
  private function load_account() {
    $api_key = ATAI_Utility::get_api_key();
    $this->account = array(
      'plan'          => '',
      'expires_at'    => '',
      'usage'         => '',
      'quota'         => '',
      'available'     => '',
      'whitelabel'    => false,
    );

    if ( empty( $api_key ) ) {
      return;
    }

    $api = new ATAI_API( $api_key );
    $this->account = $api->get_account();
  }

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.8.3
	 */
	public function enqueue_styles() {
		wp_enqueue_style( 'atai-admin', plugin_dir_url( __FILE__ ) . 'css/admin.css', array(), $this->version, 'all' );
	}

  /**
   * Register the settings pages for the plugin.
   *
   * @since    1.0.0
	 * @access   public
   */
	public function register_settings_pages() {
    // Main page
		add_menu_page(
			__( 'AltText.ai WordPress Settings', 'alttext-ai' ),
			__( 'AltText.ai', 'alttext-ai' ),
			'manage_options',
      'atai',
			array( $this, 'render_settings_page' ),
      'dashicons-format-image'
		);

    $hook_suffix = add_submenu_page(
      'atai',
      __( 'AltText.ai WordPress Settings', 'alttext-ai' ),
      __( 'Settings', 'alttext-ai' ),
      'manage_options',
      'atai'
    );
    add_action("admin_head-{$hook_suffix}", array($this, 'enqueue_styles') );

    // Bulk Generate Page
    if ( ATAI_Utility::get_api_key() ) {
      $hook_suffix = add_submenu_page(
        'atai',
        __( 'Bulk Generate', 'alttext-ai' ),
        __( 'Bulk Generate', 'alttext-ai' ),
        'manage_options',
        'atai-bulk-generate',
        array( $this, 'render_bulk_generate_page' )
      );

      add_action("admin_head-{$hook_suffix}", array($this, 'enqueue_styles') );
    }

    // History Page
    if ( ATAI_Utility::get_api_key() ) {
      $hook_suffix = add_submenu_page(
        'atai',
        __( 'History', 'alttext-ai' ),
        __( 'History', 'alttext-ai' ),
        'manage_options',
        'atai-history',
        array( $this, 'render_history_page' )
      );

      add_action("admin_head-{$hook_suffix}", array($this, 'enqueue_styles') );
    }

    // CSV Import Page
    $hook_suffix = add_submenu_page(
      'atai',
      __( 'Sync Library', 'alttext-ai' ),
      __( 'Sync Library', 'alttext-ai' ),
      'manage_options',
      'atai-csv-import',
      array( $this, 'render_csv_import_page' )
    );

    add_action("admin_head-{$hook_suffix}", array($this, 'enqueue_styles') );
	}

  /**
   * Render the settings page.
   *
   * @since    1.0.0
	 * @access   public
   */
  public function render_settings_page() {
    // Check if installed via Woo Marketplace:
    $woo_filepath = plugin_dir_path( __FILE__ ) . "../woo.txt";
    add_option( 'atai_woo_marketplace', file_exists($woo_filepath) ? 'yes' : 'no' );

    $this->load_account();
    require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/settings.php';
  }

  /**
   * Render the bulk generate page.
   *
   * @since    1.0.0
	 * @access   public
   */
  public function render_bulk_generate_page() {
    $this->load_account();
    require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/bulk-generate.php';
  }

  /**
   * Render the history page.
   *
   * @since    1.4.1
	 * @access   public
   */
  public function render_history_page() {
    $this->load_account();
    require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/history.php';
  }

  /**
   * Render the CSV import page.
   *
   * @since    1.1.0
	 * @access   public
   */
  public function render_csv_import_page() {
    $this->load_account();
    require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/csv-import.php';
  }

  /**
   * Register setting group.
   *
   * @since    1.0.0
	 * @access   public
   */
  public function register_settings() {
    register_setting(
			'atai-settings',
			'atai_api_key',
      array(
        'default'           => '',
      )
		);

    register_setting(
			'atai-settings',
      'atai_lang',
      array(
        'default'           => 'en',
      )
    );

    register_setting(
			'atai-settings',
      'atai_model_name',
      array(
        'default'           => null,
      )
    );

    register_setting(
			'atai-settings',
      'atai_update_title',
      array(
        'sanitize_callback' => array( $this, 'sanitize_yes_no_checkbox' ),
        'default'           => 'no',
      )
    );

    register_setting(
			'atai-settings',
      'atai_update_caption',
      array(
        'sanitize_callback' => array( $this, 'sanitize_yes_no_checkbox' ),
        'default'           => 'no',
      )
    );

    register_setting(
			'atai-settings',
      'atai_update_description',
      array(
        'sanitize_callback' => array( $this, 'sanitize_yes_no_checkbox' ),
        'default'           => 'no',
      )
    );

    register_setting(
			'atai-settings',
      'atai_enabled',
      array(
        'sanitize_callback' => array( $this, 'sanitize_yes_no_checkbox' ),
        'default'           => 'yes',
      )
    );

    register_setting(
			'atai-settings',
      'atai_skip_filenotfound',
      array(
        'sanitize_callback' => array( $this, 'sanitize_yes_no_checkbox' ),
        'default'           => 'no',
      )
    );

    register_setting(
			'atai-settings',
      'atai_keywords',
      array(
        'sanitize_callback' => array( $this, 'sanitize_yes_no_checkbox' ),
        'default'           => 'yes',
      )
    );

    register_setting(
			'atai-settings',
      'atai_keywords_title',
      array(
        'sanitize_callback' => array( $this, 'sanitize_yes_no_checkbox' ),
        'default'           => 'no',
      )
    );

    register_setting(
			'atai-settings',
      'atai_ecomm',
      array(
        'sanitize_callback' => array( $this, 'sanitize_yes_no_checkbox' ),
        'default'           => 'yes',
      )
    );

    register_setting(
			'atai-settings',
      'atai_ecomm_title',
      array(
        'sanitize_callback' => array( $this, 'sanitize_yes_no_checkbox' ),
        'default'           => 'no',
      )
    );

    register_setting(
			'atai-settings',
      'atai_public',
      array(
        'sanitize_callback' => array( $this, 'sanitize_yes_no_checkbox' ),
        'default'           => 'no',
      )
    );

    register_setting(
			'atai-settings',
      'atai_alt_prefix',
      array(
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '',
      )
    );

    register_setting(
			'atai-settings',
      'atai_alt_suffix',
      array(
        'sanitize_callback' => 'sanitize_text_field',
        'default'           => '',
      )
    );

    register_setting(
			'atai-settings',
      'atai_gpt_prompt',
      array(
        'sanitize_callback' => array( $this, 'sanitize_gpt_prompt' ),
        'default'           => '',
      )
    );

    register_setting(
			'atai-settings',
      'atai_type_extensions',
      array(
        'sanitize_callback' => array( $this, 'sanitize_file_extension_list' ),
        'default'           => '',
      )
    );

    register_setting(
			'atai-settings',
      'atai_no_credit_warning',
      array(
        'sanitize_callback' => array( $this, 'sanitize_yes_no_checkbox' ),
        'default'           => 'no',
      )
    );

    register_setting(
			'atai-settings',
      'atai_bulk_refresh_overwrite',
      array(
        'sanitize_callback' => array( $this, 'sanitize_yes_no_checkbox' ),
        'default'           => 'no',
      )
    );

    register_setting(
			'atai-settings',
      'atai_bulk_refresh_external',
      array(
        'sanitize_callback' => array( $this, 'sanitize_yes_no_checkbox' ),
        'default'           => 'no',
      )
    );

    register_setting(
			'atai-settings',
      'atai_timeout',
      array(
        'default'           => '20',
      )
    );
  }

  /**
   * Sanitizes a checkbox input to ensure it is either 'yes' or 'no'.
   *
   * This function is designed to handle checkbox inputs where the value
   * represents a binary choice like 'yes' or 'no'. If the input is 'yes',
   * it returns 'yes', otherwise it defaults to 'no'.
   *
   * @since 1.0.41
   * @access public
   *
   * @param string $input The checkbox input value.
   *
   * @return string Returns 'yes' if input is 'yes', otherwise returns 'no'.
   */
  public function sanitize_yes_no_checkbox( $input ) {
    return $input === 'yes' ? 'yes' : 'no';
  }

  /**
   * Sanitizes a file extension list to ensure it does not contain leading dots.
   *
   * @since 1.0.43
   * @access public
   *
   * @param string $input The file extension list string. Example: "jpg, .webp"
   *
   * @return string Returns the string with dots removed.
   */
  public function sanitize_file_extension_list( $input ) {
    return sanitize_text_field( str_replace( '.', '', strtolower( $input ) ) );
  }

  /**
   * Sanitizes a custom ChatGPT prompt to ensure it contains the {{AltText} macro and isn't too long.
   *
   * @since 1.2.4
   * @access public
   *
   * @param string $input The text of the GPT prompt.
   *
   * @return string Returns the prompt string if valid, otherwise an empty string.
   */
  public function sanitize_gpt_prompt( $input ) {
    if ( strlen($input) > 512 || strpos($input, "{{AltText}}") === false ) {
      return '';
    }
    else {
      return sanitize_textarea_field($input);
    }
  }

  /**
   * Add or delete API key.
   *
   * @since 1.0.0
   * @access public
   */
  public function save_api_key( $api_key, $old_api_key ) {
    $delete = is_null( $api_key );

    if ( $delete ) {
      delete_option( 'atai_api_key' );
    }

    if ( empty( $api_key ) ) {
      return $api_key;
    }

    if ( $api_key === '*********' ) {
      return $old_api_key;
    }

    $api = new ATAI_API( $api_key );

    if ( ! $api->get_account() ) {
      add_settings_error( 'invalid-api-key', '', esc_html__( 'Your API key is not valid.', 'alttext-ai' ) );
      return false;
    }

    // Add custom success message
    $message = __( 'API Key saved. Pro tip: Add alt text to all your existing images with our <a href="%s" class="font-medium text-indigo-600 hover:text-indigo-500">Bulk Generate</a> feature!', 'alttext-ai' );
    $message = sprintf( $message, admin_url( 'admin.php?page=atai-bulk-generate' ) );
    add_settings_error( 'atai_api_key_updated', '', $message, 'updated' );

    return $api_key;
  }

  /**
   * Clear error logs on load
   *
   * @since 1.0.0
   * @access public
   */
  public function clear_error_logs() {
    if ( ! isset( $_GET['atai_action'] ) ) {
      return;
    }

    if ( $_GET['atai_action'] !== 'clear-error-logs' ) {
      return;
    }

    delete_option( 'atai_error_logs' );
    wp_safe_redirect( add_query_arg( 'atai_action', false ) );
  }

  /**
   * Display a notice to the user if they have insufficient credits.
   *
   * If the "atai_insufficient_credits" transient is set, display a notice to the user that
   * they are out of credits and provide a link to upgrade their plan.
   *
   * @since 1.0.20
   */
  public function display_insufficient_credits_notice() {
    // Bail early if notice transient is not set
    if ( ! get_transient( 'atai_insufficient_credits' ) ) {
      return;
    }

    echo '<div class="notice notice--atai notice-error is-dismissible"><p>';

    printf(
      wp_kses(
        __( '[AltText.ai] You have no more credits available. <a href="%s" target="_blank">Manage your account</a> to get more credits.', 'alttext-ai' ),
        array( 'a' => array( 'href' => array(), 'target' => array() ) )
      ),
      esc_url( ATAI_Utility::get_credits_url() )
    );

    echo '</p></div>';
  }

  /**
   * Delete the "atai_insufficient_credits" transient to expire the notice.
   *
   * @since 1.0.20
   */
  public function expire_insufficient_credits_notice() {
    check_ajax_referer( 'atai_insufficient_credits_notice', 'security' );
    delete_transient( 'atai_insufficient_credits' );

    wp_send_json( array(
      'status'    => 'success',
      'message'   => __( 'Notice expired.', 'alttext-ai' ),
    ) );
  }

  /**
   * Display a notice if no API key is added.
   *
   * @since 1.2.1
   */
  public function display_api_key_missing_notice() {
    if ( ! isset( $_GET['api_key_missing'] ) ) {
      return;
    }

    $api_key = ATAI_Utility::get_api_key();

    if ( ! empty( $api_key ) ) {
      return;
    }

    echo '<div class="notice notice--atai notice-warning"><p>';
    echo wp_kses(
      __('[AltText.ai] Please <strong>add your API key</strong> to generate alt text.', 'alttext-ai' ),
      array( 'strong' => array() )
    );
    echo '</p></div>';
  }

  /**
   * Remove the "api_key_missing" query arg from the URL.
   *
   * @since 1.2.1
   */
  public function remove_api_key_missing_param() {
    if ( ! isset( $_GET['api_key_missing'] ) ) {
      return;
    }

    $api_key = ATAI_Utility::get_api_key();

    if ( empty( $api_key ) ) {
      return;
    }

    $current_url = ( is_ssl() ? 'https://' : 'http://' ) . sanitize_text_field($_SERVER['HTTP_HOST']) . sanitize_url($_SERVER['REQUEST_URI']);
    $updated_url = remove_query_arg( 'api_key_missing', $current_url );

    if ( $current_url !== $updated_url ) {
      wp_safe_redirect( $updated_url );
      exit;
    }
  }
}
