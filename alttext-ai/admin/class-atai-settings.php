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
    $capability = ATAI_Utility::get_setting( 'atai_admin_capability', 'manage_options' );
    // Main page
		add_menu_page(
			__( 'AltText.ai WordPress Settings', 'alttext-ai' ),
			__( 'AltText.ai', 'alttext-ai' ),
			$capability,
      'atai',
      array( $this, 'render_settings_page' ),
      'dashicons-format-image'
    );

    // Settings submenu item
    $hook_suffix = add_submenu_page(
      'atai',
      __( 'Settings', 'alttext-ai' ),
      __( 'Settings', 'alttext-ai' ),
      $capability,
      'atai',
      array( $this, 'render_settings_page' ),
      10
    );
    add_action( "admin_head-{$hook_suffix}", array( $this, 'enqueue_styles' ) );

    // Bulk Generate submenu item
    if ( ATAI_Utility::get_api_key() ) {
      $hook_suffix = add_submenu_page(
        'atai',
        __( 'Bulk Generate', 'alttext-ai' ),
        __( 'Bulk Generate', 'alttext-ai' ),
        $capability,
        'atai-bulk-generate',
        array( $this, 'render_bulk_generate_page' ),
        20
      );
      add_action( "admin_head-{$hook_suffix}", array( $this, 'enqueue_styles' ) );
    }

    // History submenu item
    if ( ATAI_Utility::get_api_key() ) {
      $hook_suffix = add_submenu_page(
        'atai',
        __( 'History', 'alttext-ai' ),
        __( 'History', 'alttext-ai' ),
        $capability,
        'atai-history',
        array( $this, 'render_history_page' ),
        30
      );
      add_action( "admin_head-{$hook_suffix}", array( $this, 'enqueue_styles' ) );
    }

    // Sync Library page
    $hook_suffix = add_submenu_page(
      'atai',
      __( 'Sync Library', 'alttext-ai' ),
      __( 'Sync Library', 'alttext-ai' ),
      $capability,
      'atai-csv-import',
      array( $this, 'render_csv_import_page' ),
      40
    );
    add_action( "admin_head-{$hook_suffix}", array( $this, 'enqueue_styles' ) );
	}

  /**
   * Register the network settings page.
   *
   * @since    1.10.16
   * @access   public
   */
  public function register_network_settings_page() {
    if ( ! is_multisite() ) {
      return;
    }

    $hook_suffix = add_submenu_page(
      'settings.php', // Parent slug (network admin settings)
      __( 'AltText.ai Network Settings', 'alttext-ai' ),
      __( 'AltText.ai', 'alttext-ai' ),
      'manage_network_options',
      'atai-network',
      array( $this, 'render_network_settings_page' )
    );

    // Enqueue styles for the network settings page
    add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_network_styles' ) );
  }

  /**
   * Enqueue styles for the network settings page.
   *
   * @since    1.10.16
   */
  public function enqueue_network_styles( $hook ) {
    // Debug the current hook to see what it is
    if ( strpos( $hook, 'atai-network' ) !== false ) {
      wp_enqueue_style( 'atai-admin', plugin_dir_url( __FILE__ ) . 'css/admin.css', array(), $this->version, 'all' );
    }
  }

  /**
   * Register the network bulk generate page.
   *
   * @since    1.10.20
   * @access   public
   */
  public function register_network_bulk_generate_page() {
    if ( ! is_multisite() ) {
      return;
    }

    add_submenu_page(
      'settings.php',
      __( 'AltText.ai Network Bulk Generate', 'alttext-ai' ),
      __( 'AltText.ai Bulk Generate', 'alttext-ai' ),
      'manage_network_options',
      'atai-network-bulk-generate',
      array( $this, 'render_network_bulk_generate_page' )
    );

    add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_network_bulk_generate_scripts' ) );
  }

  /**
   * Render the network bulk generate page.
   *
   * @since    1.10.20
   * @access   public
   */
  public function render_network_bulk_generate_page() {
    $this->load_account();
    require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/network-bulk-generate.php';
  }

  /**
   * Enqueue scripts for the network bulk generate page.
   *
   * @since    1.10.20
   */
  public function enqueue_network_bulk_generate_scripts( $hook ) {
    if ( strpos( $hook, 'atai-network-bulk-generate' ) === false ) {
      return;
    }
    wp_enqueue_style( 'atai-admin', plugin_dir_url( __FILE__ ) . 'css/admin.css', array(), $this->version, 'all' );
    wp_enqueue_script( 'atai-network-admin', plugin_dir_url( __FILE__ ) . 'js/network-admin.js', array( 'jquery' ), $this->version, true );
    wp_localize_script( 'atai-network-admin', 'wp_atai_network', array(
      'ajax_url' => admin_url( 'admin-ajax.php' ),
      'security' => wp_create_nonce( 'atai_network_bulk_generate' ),
    ) );
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
   * Render the network settings page.
   *
   * @since    1.10.16
   * @access   public
   */
  public function render_network_settings_page() {
    require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/partials/network-settings.php';
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
   * Filter the capability required to save settings.
   *
   * @since    1.10.13
   * @access   public
   * @param    string    $capability    The default capability (manage_options).
   * @return   string    The configured capability.
   */
  public function filter_settings_capability( $capability ) {
    return ATAI_Utility::get_setting( 'atai_admin_capability', 'manage_options' );
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

    // Network API key option (multisite only)
    if ( is_multisite() && is_super_admin() ) {
      register_setting(
        'atai-settings',
        'atai_network_api_key',
        array(
          'sanitize_callback' => array( $this, 'sanitize_yes_no_checkbox' ),
          'default'           => 'no',
        )
      );
      
      register_setting(
        'atai-settings',
        'atai_network_all_settings',
        array(
          'sanitize_callback' => array( $this, 'sanitize_yes_no_checkbox' ),
          'default'           => 'no',
        )
      );
    }

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
      'atai_force_lang',
      array(
        'sanitize_callback' => array( $this, 'sanitize_yes_no_checkbox' ),
        'default'           => 'no',
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
      'atai_admin_capability',
      array(
        'sanitize_callback' => 'sanitize_key',
        'default'           => 'manage_options',
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
      'atai_excluded_post_types',
      array(
        'sanitize_callback' => array( $this, 'sanitize_post_type_list' ),
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
      'atai_refresh_src_attr',
      array(
        'sanitize_callback' => array( $this, 'sanitize_refresh_src_field' ),
        'default'           => 'src',
      )
    );

    register_setting(
			'atai-settings',
      'atai_wp_generate_metadata',
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

    register_setting(
      'atai-settings',
      'atai_admin_capability',
      array(
        'default'           => 'manage_options',
        'sanitize_callback' => array( $this, 'sanitize_admin_capability' )
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
   * Sanitizes a string with an ensured default if blank.
   *
   * @since 1.9.2
   * @access public
   *
   * @param string $input The string to sanitize
   *
   * @return string Returns sanitized string with default applied.
   */
  public function sanitize_refresh_src_field( $input ) {
    if ( empty($input) ) {
      $input = 'src';
    }

    return sanitize_text_field($input);
  }

  /**
   * Sanitize the admin capability setting.
   *
   * @since 1.10.0
   * @access public
   *
   * @param string $capability The capability to sanitize.
   * @return string Sanitized capability.
   */
  public function sanitize_admin_capability( $capability ) {
    $valid_capabilities = array(
      'manage_options',    // Administrators only
      'edit_others_posts', // Editors and Administrators
      'publish_posts',     // Authors, Editors and Administrators (not Contributors)
      'read'               // All logged-in users
    );

    // If the submitted capability is not in our valid list, default to 'manage_options'
    if ( ! in_array( $capability, $valid_capabilities, true ) ) {
      return 'manage_options';
    }

    return $capability;
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
   * Sanitizes a post type list to ensure it contains valid post type names.
   *
   * @since 1.10.2
   * @access public
   *
   * @param string $input The post type list string. Example: "proof, submission"
   *
   * @return string Returns the sanitized post type list.
   */
  public function sanitize_post_type_list( $input ) {
    if ( empty( $input ) ) {
      return '';
    }
    
    $post_types = array_map( 'trim', explode( ',', $input ) );
    $sanitized_post_types = array_map( 'sanitize_key', $post_types );
    $filtered_post_types = array_filter( $sanitized_post_types );
    
    return implode( ',', $filtered_post_types );
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
      
      // If this is a multisite and we're a network admin, also update the network setting
      if ( is_multisite() && is_super_admin() ) {
        update_site_option( 'atai_network_api_key', 'no' );
      }
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

    // Check if the network API key option is set and save it
    if ( is_multisite() && is_super_admin() ) {
      if ( isset( $_POST['atai_network_api_key'] ) ) {
        $network_api_key = $_POST['atai_network_api_key'] === 'yes' ? 'yes' : 'no';
        update_site_option( 'atai_network_api_key', $network_api_key );
      }
      
      if ( isset( $_POST['atai_network_all_settings'] ) ) {
        $network_all_settings = $_POST['atai_network_all_settings'] === 'yes' ? 'yes' : 'no';
        update_site_option( 'atai_network_all_settings', $network_all_settings );
        
        // If enabled, sync all settings to network option for later use by subsites
        if ( $network_all_settings === 'yes' ) {
          $this->sync_settings_to_network();
        }
      }
    }

    // Add custom success message
    $message = __( 'API Key saved. Pro tip: Add alt text to all your existing images with our <a href="%s" class="font-medium text-primary-600 hover:text-primary-500">Bulk Generate</a> feature!', 'alttext-ai' );
    $message = sprintf( $message, admin_url( 'admin.php?page=atai-bulk-generate' ) );
    add_settings_error( 'atai_api_key_updated', '', $message, 'updated' );

    return $api_key;
  }

  /**
   * Sync settings from the main site to the network.
   *
   * Uses explicit defaults to avoid propagating unset options as false,
   * which could lock users out or change behavior unexpectedly on subsites.
   *
   * @since    1.10.16
   * @access   private
   */
  private function sync_settings_to_network() {
    if ( ! is_multisite() || ! is_main_site() ) {
      return;
    }

    // Settings with their defaults - prevents false from being stored for unset options
    $settings_with_defaults = array(
      'atai_api_key'              => '',
      'atai_lang'                 => 'en',
      'atai_model_name'           => '',
      'atai_force_lang'           => 'no',
      'atai_update_title'         => 'no',
      'atai_update_caption'       => 'no',
      'atai_update_description'   => 'no',
      'atai_enabled'              => 'yes',
      'atai_skip_filenotfound'    => 'no',
      'atai_keywords'             => 'yes',
      'atai_keywords_title'       => 'no',
      'atai_ecomm'                => 'yes',
      'atai_ecomm_title'          => 'no',
      'atai_alt_prefix'           => '',
      'atai_alt_suffix'           => '',
      'atai_gpt_prompt'           => '',
      'atai_type_extensions'      => '',
      'atai_excluded_post_types'  => '',
      'atai_bulk_refresh_overwrite' => 'no',
      'atai_bulk_refresh_external'  => 'no',
      'atai_refresh_src_attr'     => 'src',
      'atai_wp_generate_metadata' => 'no',
      'atai_timeout'              => '20',
      'atai_public'               => 'no',
      'atai_no_credit_warning'    => 'no',
      'atai_admin_capability'     => 'manage_options',
    );

    // Create a network_settings array with values from the main site (with defaults)
    $network_settings = array();
    foreach ( $settings_with_defaults as $option_name => $default ) {
      $network_settings[ $option_name ] = get_option( $option_name, $default );
    }

    // Save all settings to the network options
    update_site_option( 'atai_network_settings', $network_settings );
  }

  /**
   * Refresh network settings cache when a setting is updated.
   *
   * This ensures subsites get fresh values when the main site changes settings.
   *
   * @since    1.10.16
   * @access   public
   * @param    string    $option    The option name that was updated.
   */
  public function maybe_refresh_network_settings( $option ) {
    // Only process our plugin's options (all start with 'atai_')
    if ( strpos( $option, 'atai_' ) !== 0 ) {
      return;
    }

    // Only refresh if we're on the main site, multisite is enabled, and network settings are active
    if ( ! is_multisite() || ! is_main_site() ) {
      return;
    }

    $network_all_settings = get_site_option( 'atai_network_all_settings' );
    if ( $network_all_settings === 'yes' ) {
      $this->sync_settings_to_network();
    }
  }

  /**
   * Handle network settings update.
   *
   * @since    1.10.16
   * @access   public
   */
  public function handle_network_settings_update() {
    if ( ! is_multisite() || ! is_network_admin() ) {
      return;
    }

    // Verify user has permission to manage network options
    if ( ! current_user_can( 'manage_network_options' ) ) {
      wp_die( esc_html__( 'You do not have permission to manage network settings.', 'alttext-ai' ) );
    }

    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonces should not be sanitized before verification
    if ( ! isset( $_POST['atai_network_settings_nonce'] ) || ! wp_verify_nonce( $_POST['atai_network_settings_nonce'], 'atai_network_settings_nonce' ) ) {
      wp_die( esc_html__( 'Security check failed.', 'alttext-ai' ) );
    }

    // Update network API key setting
    $network_api_key = isset( $_POST['atai_network_api_key'] ) ? 'yes' : 'no';
    update_site_option( 'atai_network_api_key', $network_api_key );

    // Update network all settings option
    $network_all_settings = isset( $_POST['atai_network_all_settings'] ) ? 'yes' : 'no';
    update_site_option( 'atai_network_all_settings', $network_all_settings );
    
    // Update network hide credits option
    $network_hide_credits = isset( $_POST['atai_network_hide_credits'] ) ? 'yes' : 'no';
    update_site_option( 'atai_network_hide_credits', $network_hide_credits );

    // Sync settings from main site to network options if enabled
    if ( $network_all_settings === 'yes' || $network_api_key === 'yes' ) {
      $this->sync_settings_to_network();
    }

    // Redirect back to the network settings page with a success message
    wp_safe_redirect( add_query_arg( 'updated', 'true', network_admin_url( 'settings.php?page=atai-network' ) ) );
    exit;
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

    // Check user has permission
    $required_capability = ATAI_Utility::get_setting( 'atai_admin_capability', 'manage_options' );
    if ( ! current_user_can( $required_capability ) ) {
      wp_die( esc_html__( 'You do not have permission to perform this action.', 'alttext-ai' ) );
    }

    // Verify CSRF nonce
    if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'atai_clear_error_logs' ) ) {
      wp_die(
        esc_html__( 'Security verification failed. Please refresh the page and try again.', 'alttext-ai' ),
        esc_html__( 'AltText.ai', 'alttext-ai' ),
        array( 'back_link' => true )
      );
    }

    delete_option( 'atai_error_logs' );
    wp_safe_redirect( add_query_arg( 'atai_action', false ) );
    exit;
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
    // On subsites where the network admin has hidden credit information, suppress this notice
    if ( is_multisite() && ! is_main_site() && get_site_option( 'atai_network_hide_credits' ) === 'yes' ) {
      return;
    }

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

    $current_url = ( is_ssl() ? 'https://' : 'http://' ) . wp_parse_url(home_url(), PHP_URL_HOST) . sanitize_url($_SERVER['REQUEST_URI']);
    $updated_url = remove_query_arg( 'api_key_missing', $current_url );

    if ( $current_url !== $updated_url ) {
      wp_safe_redirect( $updated_url );
      exit;
    }
  }

  public function ajax_update_public_setting() {
    // Verify nonce
    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['security'] ) ), 'atai_update_public_setting' ) ) {
      wp_send_json_error( __( 'Security check failed.', 'alttext-ai' ) );
    }

    // Check user capabilities using configured capability
    $required_capability = ATAI_Utility::get_setting( 'atai_admin_capability', 'manage_options' );
    if ( ! current_user_can( $required_capability ) ) {
      wp_send_json_error( __( 'Insufficient permissions.', 'alttext-ai' ) );
    }

    // Sanitize and update the setting
    $atai_public = sanitize_text_field( $_POST['atai_public'] ?? 'no' );
    $atai_public = in_array( $atai_public, array( 'yes', 'no' ) ) ? $atai_public : 'no';
    
    update_option( 'atai_public', $atai_public );
    
    wp_send_json_success( array(
      'message' => __( 'Setting updated successfully.', 'alttext-ai' ),
      'new_value' => $atai_public
    ) );
  }
}
