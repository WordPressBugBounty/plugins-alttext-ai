<?php
  // For handling audio attachments, need access to wp_read_audio_metadata:
  // cf: https://developer.wordpress.org/reference/functions/wp_generate_attachment_metadata/
  if ( ! function_exists( 'wp_read_audio_metadata' ) ) {
  	require_once ABSPATH . 'wp-admin/includes/media.php';
  }

  // Ensure wp_get_attachment_metadata is defined:
  if ( ! function_exists( 'wp_get_attachment_metadata' ) || ! function_exists( 'wp_generate_attachment_metadata' ) ) {
  	require_once ABSPATH . 'wp-admin/includes/image.php';
  }

/**
 * The file that handles attachment/image logic.
 *
 *
 * @link       https://alttext.ai
 * @since      1.0.0
 *
 * @package    ATAI
 * @subpackage ATAI/includes
 */

/**
 * The attachment handling class.
 *
 * This is used to handle operations related to attachments.
 *
 *
 * @since      1.0.0
 * @package    ATAI
 * @subpackage ATAI/includes
 * @author     AltText.ai <info@alttext.ai>
 */
class ATAI_Attachment {
  /**
   * Normalize and validate a language code.
   *
   * Supports 3-tier fallback:
   * 1. Perfect match (zh-cn → zh-cn)
   * 2. Base language fallback (pt-ao → pt, zh-Hant-HK → zh)
   * 3. Auto-detection (xyz → auto-detect)
   *
   * @since 1.0.0
   * @access private
   *
   * @param string $lang               The language code to normalize.
   * @param int    $attachment_id      Attachment ID for auto-detection fallback.
   * @param array  $supported_languages Map of supported language codes.
   *
   * @return string Normalized language code.
   */
  private function normalize_lang( $lang, $attachment_id, $supported_languages ) {
    // Invalid input - fall back to auto-detection
    if ( ! is_string( $lang ) || '' === trim( $lang ) ) {
      return ATAI_Utility::lang_for_attachment( $attachment_id );
    }

    // Normalize: lowercase and trim (preserves region codes)
    $lang = strtolower( trim( $lang ) );

    // Perfect match - use as-is
    if ( isset( $supported_languages[ $lang ] ) ) {
      return $lang;
    }

    // Try base language fallback whenever there's a hyphen (handles multi-subtag codes like zh-Hant-HK)
    if ( false !== strpos( $lang, '-' ) ) {
      $base = explode( '-', $lang, 2 )[0];
      if ( isset( $supported_languages[ $base ] ) ) {
        return $base;
      }
    }

    // Unsupported language - fall back to auto-detection
    return ATAI_Utility::lang_for_attachment( $attachment_id );
  }

  /**
   * Generate alt text for an image/attachment.
   *
   * @since 1.0.0
   * @access public
   *
   * @param integer $attachment_id  ID of the attachment.
   * @param string  $attachment_url URL of the attachment. $attachment_id has priority if both are provided.
   * @param array   $options        API Options to customize the API call. Supported keys:
   *                                 - 'overwrite' (bool): Whether to overwrite existing alt text. Default true.
   *                                 - 'ecomm' (array): E-commerce product data (product name, brand).
   *                                 - 'keywords' (array): SEO keywords to incorporate.
   *                                 - 'negative_keywords' (array): Keywords to avoid.
   *                                 - 'lang' (string): Language code (BCP-47-like, lowercase). Auto-detected if not provided.
   *                                 - 'explicit_post_id' (int): Force SEO keyword lookup from specific post.
   *
   *                                 Note: Global 'atai_force_lang' setting will override 'lang' if enabled.
   *
   * @return string|false|WP_Error  Generated alt text string on success; false, WP_Error, or error code string on failure.
   *                                 Known error codes: 'insufficient_credits', 'url_access_error'.
   */
  public function generate_alt( $attachment_id, $attachment_url = null, $options = [] ) {
    $api_key = ATAI_Utility::get_api_key();

    // Bail early if no API key
    if ( empty( $api_key ) ) {
      return false;
    }

    // Bail early if attachment is not eligible
    if ( $attachment_id && $this->is_attachment_eligible( $attachment_id ) === false ) {
      return false;
    }

    // Merge options with defaults (wp_parse_args gives priority to first arg)
    $api_options = wp_parse_args(
      $options,
      array(
        'overwrite'         => true,
        'ecomm'             => array(),
        'keywords'          => array(),
        'negative_keywords' => array(),
        'lang'              => ATAI_Utility::lang_for_attachment( $attachment_id ),
      )
    );

    // Normalize booleans that might arrive as strings/ints via filters
    $api_options['overwrite'] = ! empty( $api_options['overwrite'] ) ? true : false;

    $gpt_prompt = get_option('atai_gpt_prompt');
    if ( !empty($gpt_prompt) ) {
      $api_options['gpt_prompt'] = $gpt_prompt;
    }

    $model_name = get_option('atai_model_name');
    if ( !empty($model_name) ) {
      $api_options['model_name'] = $model_name;
    }

    if ( $attachment_id ) {
      $attachment_url = wp_get_attachment_image_url( $attachment_id, 'full' );
      $attachment_url = apply_filters( 'atai_attachment_url', $attachment_url, $attachment_id );
      if ( empty($api_options['ecomm']) ) {
        $api_options['ecomm'] = $this->get_ecomm_data( $attachment_id );
      }
      $api_options['ecomm'] = $this->filtered_ecomm_data( $attachment_id, $api_options['ecomm'] );

      if ( ! count( $api_options['keywords'] ) ) {
        if ( isset( $api_options['explicit_post_id'] ) && ! empty( $api_options['explicit_post_id'] ) ) {
            $api_options['keywords'] = $this->get_seo_keywords( $attachment_id, $api_options['explicit_post_id'] );
        } else {
            $api_options['keywords'] = $this->get_seo_keywords( $attachment_id );
        }
        if ( ! count( $api_options['keywords'] ) && ( get_option( 'atai_keywords_title' ) === 'yes' ) ) {
          $api_options['keyword_source'] = $this->post_title_seo_keywords( $attachment_id );
        }
      }
    }

    /**
     * Filter API options before sending to the AltText.ai API.
     *
     * Allows integrators to modify options per-site or per-attachment (e.g., custom keywords, throttling).
     *
     * @param array  $api_options     The final API options array.
     * @param int    $attachment_id   The attachment ID being processed.
     * @param string $attachment_url  The attachment URL.
     */
    $api_options = apply_filters( 'atai_before_create_image_options', $api_options, $attachment_id, $attachment_url );

    // Normalize keyword arrays (handles scalars from filters/integrations)
    $api_options['keywords']          = array_map( 'sanitize_text_field', (array) ( $api_options['keywords'] ?? array() ) );
    $api_options['negative_keywords'] = array_map( 'sanitize_text_field', (array) ( $api_options['negative_keywords'] ?? array() ) );

    // If present, ensure explicit_post_id is a safe integer
    if ( isset( $api_options['explicit_post_id'] ) ) {
      $api_options['explicit_post_id'] = absint( $api_options['explicit_post_id'] );
    }

    // Cache supported languages once (used in both normalization paths)
    $supported_languages = ATAI_Utility::supported_languages();

    // Normalize language (ensure a default even if a filter removed it)
    $api_options['lang'] = $this->normalize_lang(
      $api_options['lang'] ?? ATAI_Utility::lang_for_attachment( $attachment_id ),
      $attachment_id,
      $supported_languages
    );

    // Enforce force_lang setting if enabled (overrides filter and caller language)
    if ( 'yes' === get_option( 'atai_force_lang' ) ) {
      $forced_lang = get_option( 'atai_lang' );
      if ( is_string( $forced_lang ) && '' !== trim( $forced_lang ) ) {
        $api_options['lang'] = $this->normalize_lang(
          $forced_lang,
          $attachment_id,
          $supported_languages
        );
      }
    }

    $api            = new ATAI_API( $api_key );
    $response_code = null;
    $max_retries = apply_filters( 'atai_max_retries', 3 ); // Default 3 retries for admin-AJAX responsiveness
    $delay = 1; // 1 second
    $start_time = microtime( true );
    $time_budget = apply_filters( 'atai_retry_time_budget', 12 ); // Maximum seconds for all retries

    for ($attempt = 0; $attempt < $max_retries; $attempt++) {
      $response = $api->create_image( $attachment_id, $attachment_url, $api_options, $response_code );

      // Hard-fail on unrecoverable client/auth errors (no retry)
      $hard_fail_codes = apply_filters( 'atai_hard_fail_http_codes', array( 400, 401, 403, 404, 422 ) );
      if ( ! is_array( $hard_fail_codes ) ) {
          $hard_fail_codes = array( 400, 401, 403, 404, 422 ); // Reset to safe default if filter returns non-array
      }
      if ( $response_code !== null && in_array( (int) $response_code, $hard_fail_codes, true ) ) {
          break; // Exit immediately on unrecoverable errors
      }

      // Retry on rate limiting (429) and server errors (503, 504, 408)
      $retryable_codes = apply_filters( 'atai_retryable_http_codes', array( 429, 503, 504, 408 ) );
      if ( ! is_array( $retryable_codes ) ) {
          $retryable_codes = array( 429, 503, 504, 408 ); // Reset to safe default if filter returns non-array
      }
      $retryable = in_array( (int) $response_code, $retryable_codes, true );

      if ( ! $retryable ) {
          break; // Exit if not a retryable error
      }

      // Check time budget before sleeping (prevents long final retry)
      if ( microtime( true ) - $start_time > $time_budget ) {
          break; // Exceeded time budget, bail out
      }

      if ($attempt < $max_retries - 1) {
          // Add jitter (up to 250ms) to prevent thundering herd
          // Fallback to wp_rand for older PHP or low-entropy environments
          $jitter_microseconds = function_exists( 'random_int' ) ? random_int( 0, 250000 ) : wp_rand( 0, 250000 );
          $delay_microseconds = ( $delay * 1000000 ) + $jitter_microseconds;
          usleep( $delay_microseconds );

          // Exponential backoff with cap at 8 seconds
          $delay = min( $delay * 2, 8 );
      }
    }

    if ( ! is_array( $response ) ) {
      return $response;
    }

    $alt_text = $response['alt_text'];
    $alt_prefix = get_option('atai_alt_prefix');
    $alt_suffix = get_option('atai_alt_suffix');

    if ( ! empty( $alt_prefix ) ) {
      $alt_text = trim( $alt_prefix ) . ' ' . $alt_text;
    }

    if ( ! empty( $alt_suffix ) ) {
      $alt_text = $alt_text . ' ' . trim( $alt_suffix );
    }

    ATAI_Utility::record_atai_asset($attachment_id, $response['asset_id']);
    update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );

    $post_value_updates = array();
    if ( get_option( 'atai_update_title' ) === 'yes' ) {
      $post_value_updates['post_title'] = $alt_text;
    };

    if ( get_option( 'atai_update_caption' ) === 'yes' ) {
      $post_value_updates['post_excerpt'] = $alt_text;
    };

    if ( get_option( 'atai_update_description' ) === 'yes' ) {
      $post_value_updates['post_content'] = $alt_text;
    };

    if ( ! empty( $post_value_updates ) ) {
      $post_value_updates['ID'] = $attachment_id;
      wp_update_post( $post_value_updates );
    };

    do_action( 'atai_alttext_generated', $attachment_id, $alt_text );

    return $alt_text;
  }

  /**
   * Check if an attachment should be excluded based on its parent post type.
   *
   * @since 1.10.2
   * @access private
   *
   * @param integer $attachment_id  ID of the attachment.
   *
   * @return boolean  True if should be excluded, false otherwise.
   */
  private function is_attachment_excluded_by_post_type( $attachment_id ) {
    $excluded_post_types = get_option( 'atai_excluded_post_types' );
    
    if ( empty( $excluded_post_types ) ) {
      return false;
    }
    
    $post_types = array_map( 'trim', explode( ',', $excluded_post_types ) );
    $parent_id = wp_get_post_parent_id( $attachment_id );
    
    if ( ! $parent_id ) {
      return false;
    }
    
    $parent_post_type = get_post_type( $parent_id );
    
    return in_array( $parent_post_type, $post_types );
  }

  /**
   * Check if an attachment is eligible for alt text generation.
   *
   * @since 1.0.10
   * @access public
   *
   * @param integer $attachment_id  ID of the attachment.
   *
   * @return boolean  True if eligible, false otherwise.
   */
  public function is_attachment_eligible( $attachment_id, $context = 'generate' ) {
    // Bypass eligibility checks in test mode
    if ( defined( 'ATAI_TESTING' ) && ATAI_TESTING ) {
      return true;
    }

    // Log errors for actual processing (single or bulk), but not for eligibility checks
    $should_log = ($context !== 'check');

    /** Check user-defined filter for eligibility. Bail early if this attachment is not eligible. **/
    $custom_skip = apply_filters( 'atai_skip_attachment', false, $attachment_id );
    if ( $custom_skip ) {
      return false;
    }

    /** Check if attachment should be excluded based on parent post type. **/
    if ( $this->is_attachment_excluded_by_post_type( $attachment_id ) ) {
      if ( $should_log ) {
        $parent_id = wp_get_post_parent_id( $attachment_id );
        $parent_post_type = get_post_type( $parent_id );
        $attachment_edit_url = get_edit_post_link($attachment_id);
        ATAI_Utility::log_error(
          sprintf(
            '<a href="%s" target="_blank">Image #%d</a>: %s (%s)',
            esc_url($attachment_edit_url),
            (int) $attachment_id,
            esc_html__('Excluded post type in user settings.', 'alttext-ai'),
            esc_html($parent_post_type)
          )
        );
      }
      return false;
    }

    $meta = wp_get_attachment_metadata( $attachment_id );
    $upload_info = wp_get_upload_dir();

    $file = ( is_array($meta) && array_key_exists('file', $meta) ) ? ($upload_info['basedir'] . '/' . $meta['file']) : get_attached_file( $attachment_id );
    if ( empty( $meta ) && file_exists( $file ) ) {
      if ( ( get_option( 'atai_wp_generate_metadata' ) === 'no' ) ) {
        $meta = array('width' => 100, 'height' => 100); // Default values assuming this is a valid image
      }
      else {
        $meta = wp_generate_attachment_metadata( $attachment_id, $file );
        // Defensive fix: ensure metadata is always an array to prevent conflicts with other plugins
        if ( $meta === false || ! is_array( $meta ) ) {
          $meta = array('width' => 100, 'height' => 100); // Default values assuming this is a valid image
        }
      }
    }

    $size = null;

    // Local File check
    if (file_exists($file)) {
      $size = filesize($file);
    }

    // Check metadata first
    if (!$size && isset($meta['filesize'])) {
      $size = $meta['filesize'];
    }

    // Check offloaded plugin metadata
    if (!$size) {
      $offload_meta = get_post_meta($attachment_id, 'amazonS3_info', true) ?: get_post_meta($attachment_id, 'cloudinary_info', true);
      if (isset($offload_meta['key'])) {
        $external_url = wp_get_attachment_url($attachment_id);
        $size = ATAI_Utility::get_attachment_size($external_url);
      }
    }

    $width    = $meta['width'] ?? 0;
    $height   = $meta['height'] ?? 0;
    $size     = $size ? ($size / pow(1024, 2)) : null; // in MBs
    $type     = wp_check_filetype($file) ?: [];
    $extension = $type['ext'] ?? pathinfo($file, PATHINFO_EXTENSION);

    // If unable to get extension from WP, try parsing filename directly:
    if ( empty($extension) ) {
      $extension = pathinfo($file, PATHINFO_EXTENSION);
    }
    
    // Extract commonly used values for cleaner conditionals
    $extension_lower = strtolower($extension);
    $is_svg = ($extension_lower === 'svg');
    $size_unavailable = ($size === null || $size === false);

    $file_type_extensions = get_option( 'atai_type_extensions' );
    $attachment_edit_url = get_edit_post_link($attachment_id);

    // Logging reasons for ineligibility
    if (! empty($file_type_extensions)) {
      $valid_extensions = array_map('trim', explode(',', $file_type_extensions));
      if (! in_array($extension_lower, $valid_extensions)) {
        if ( $should_log ) {
          ATAI_Utility::log_error(
            sprintf(
              '<a href="%s" target="_blank">Image #%d</a>: %s (%s)',
              esc_url($attachment_edit_url),
              (int) $attachment_id,
              esc_html__('User setting image filtering: Filetype not allowed.', 'alttext-ai'),
              esc_html($extension)
            )
          );
        }
        return false; // This image extension is not in their whitelist of allowed extensions
      }
    }

    if (!in_array($extension_lower, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'avif', 'svg'])) {
      if ( $should_log ) {
        ATAI_Utility::log_error(
          sprintf(
            '<a href="%s" target="_blank">Image #%d</a>: %s (%s)',
            esc_url($attachment_edit_url),
            (int) $attachment_id,
            esc_html__('Unsupported extension.', 'alttext-ai'),
            esc_html($extension)
          )
        );
      }
      return false;
    }

    // SVGs often have metadata issues that prevent size detection, skip this check for them
    if (!$is_svg && $size_unavailable) {
      if ($should_log && get_option('atai_skip_filenotfound') === 'yes') {
        ATAI_Utility::log_error(
          sprintf(
            '<a href="%s" target="_blank">Image #%d</a>: %s %s',
            esc_url($attachment_edit_url),
            (int) $attachment_id,
            esc_html__('File not found.', 'alttext-ai'),
            esc_html__('(System Issue)', 'alttext-ai')
          )
        );
      }
      return false;
    }

    if ($size > 16) {
      if ( $should_log ) {
        ATAI_Utility::log_error(
          sprintf(
            '<a href="%s" target="_blank">Image #%d</a>: %s (%.2f MB)',
            esc_url($attachment_edit_url),
            (int) $attachment_id,
            esc_html__('File size exceeds 16MB limit.', 'alttext-ai'),
            $size
          )
        );
      }
      return false;
    }

    if (!$is_svg && ($width < 50 || $height < 50)) {
      
      if ( $should_log ) {
        ATAI_Utility::log_error(
          sprintf(
            '<a href="%s" target="_blank">Image #%d</a>: %s (%dx%d)',
            esc_url($attachment_edit_url),
            (int) $attachment_id,
            esc_html__('Image dimensions too small.', 'alttext-ai'),
            $width,
            $height
          )
        );
      }
      return false;
    }

    return true;
  }

  /**
   * Return ecomm-specific data for alt text generation.
   *
   * @since 1.0.25
   * @access public
   *
   * @param integer $attachment_id ID of the attachment.
   *
   * @return Array ["ecomm" => ["product" => <title>]] or empty array if not found.
   */
  public function get_ecomm_data( $attachment_id, $product_id = null ) {
    if ( ( get_option( 'atai_ecomm' ) === 'no' ) || ! ATAI_Utility::has_woocommerce() ) {
      return array();
    }

    if ( get_option( 'atai_ecomm_title' ) === 'yes' ) {
      $post = get_post( $attachment_id );
      if ( !empty( $post->post_title ) ) {
        return array( 'product' => $post->post_title );
      }
    }

    if ( isset($product_id) ) {
      $product_post = get_post( $product_id );
      return array( 'product' => $product_post->post_title );
    }

    global $wpdb;

    $find_product_title_sql = <<<SQL
SELECT parent_posts.post_title as product_title
FROM {$wpdb->posts} parent_posts
INNER JOIN {$wpdb->posts} image_posts
    ON image_posts.post_parent = parent_posts.id
WHERE
    image_posts.id = %d
AND
    parent_posts.post_type = 'product'
AND
    parent_posts.post_status <> 'auto-draft'
SQL;

    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
    $product_title_data = $wpdb->get_results( $wpdb->prepare($find_product_title_sql, $attachment_id) );

    if ( count( $product_title_data ) == 0 || strlen( $product_title_data[0]->product_title ) == 0 ) {
      return array();
    }

    $product_title = $product_title_data[0]->product_title;

    return array( 'product' => $product_title );
  }

  /**
   * Retrieve filtered ecomm data, if implemented.
   *
   * @since 1.0.34
   * @access public
   *
   * @param integer $attachment_id  ID of the attachment.
   * @param Array $ecomm_data Current array of ecomm data.
   *
   * @return Array New filtered array of ecomm data.
   */
  public function filtered_ecomm_data($attachment_id, $ecomm_data) {
    /**
     * Filter the ecomm data to use for alt text generation.
     *
     * This filter allows you to modify the ecommerce product/brand data before it is used for alt text generation.
     * You might want to use this filter if you have specific product and/or brand data outside of the natively
     * supported WooCommerce system.
     *
     * @param Array $ecomm_data The current array of ecomm_data. This array will have keys "product" and [optionally] "brand"
     * @param int $attachment_id The ID of the attachment for which the alt text is being generated.
     *
     * @return array The ecommerce product and optional brand data to use. The array keys MUST match the following:
     * 'product' => The name of the product.
     * 'brand' => The brand name of the product. This is OPTIONAL.
     *
     * Example return values:
     * Example 1 (both product + brand name): { "product" => "Air Jordan", "brand" => "Nike" }
     * Example 2 (only product name): { "product" => "Air Jordan" }
     */
    $ecomm_data = apply_filters( 'atai_ecomm_data', $ecomm_data, $attachment_id );
    return $ecomm_data;
  }

    /**
     * Return array of keywords to use for alt text generation.
     *
     * @since 1.0.26
     * @access public
     *
     * @param integer $attachment_id ID of the attachment.
     *
     * @return Array of keywords, or empty array if none.
     */
    public function get_seo_keywords( $attachment_id, $explicit_post_id = null ) {
      if ( ( get_option( 'atai_keywords' ) === 'no' ) ) {
        return array();
      }

      global $wpdb;
      $post_id = NULL;

      // Attempt to get the related post ID directly from WordPress based on the attachment:
      $fetch_post_sql = "select post_parent from {$wpdb->posts} where ID = %d";
      $post_results = $wpdb->get_results( $wpdb->prepare($fetch_post_sql, $attachment_id) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,,WordPress.DB.PreparedSQL.NotPrepared

      if ( count( $post_results ) > 0 ) {
        $post_id = $post_results[0]->post_parent;
      }

      if ( empty($post_id) && ! empty($explicit_post_id) ) {
        $post_id = $explicit_post_id;
      }
      
      // Fetch keywords from Yoast SEO.
      $keywords = $this->yoast_seo_keywords( $attachment_id, $post_id );

      //  Fetch keywords from All in One SEO.
      if ( ! count( $keywords ) ) {
        $keywords = $this->aio_seo_keywords( $attachment_id, $post_id );
      }

      // Fetch keywords from RankMath.
      if ( ! count( $keywords ) ) {
        $keywords = $this->rankmath_seo_keywords( $attachment_id, $post_id );
      }

      // Fetch keywords from SEOPress.
      if ( ! count( $keywords ) ) {
        $keywords = $this->seopress_seo_keywords( $attachment_id, $post_id );
      }

      // Fetch keywords from Squirrly SEO.
      if ( ! count( $keywords ) ) {
        $keywords = $this->squirrly_seo_keywords( $attachment_id, $post_id );
      }

      // Fetch keywords from The SEO Framework.
      if ( ! count( $keywords ) ) {
        $keywords = $this->theseoframework_seo_keywords( $attachment_id, $post_id );
      }

      // Fetch keywords from SmartCrawl Pro.
      if ( ! count( $keywords ) ) {
        $keywords = $this->smartcrawl_seo_keywords( $attachment_id, $post_id );
      }

      /**
       * Filter the keywords to use for alt text generation.
       *
       * This filter allows you to modify the list of SEO keywords before they are used for alt text generation.
       * You might want to use this filter if you have specific SEO needs that are not met by the built-in
       * methods of fetching keywords from the supported SEO plugins.
       *
       * @param array $keywords The current list of SEO keywords.
       *                         This may be a list fetched from one of the SEO plugins mentioned above,
       *                         or it may be an empty array if no keywords were found.
       * @param int $attachment_id The ID of the attachment for which the alt text is being generated.
       * @param int $post_id The ID of the related post for the attachment.
       *                     This is the post that the attachment is associated with.
       *                     It may be null if no related post was found.
       *
       * @return array The modified list of SEO keywords.
       *
       * EXAMPLE USAGE:
         function custom_atai_seo_keywords($keywords, $attachment_id, $post_id) {
             $additional_keywords = array("cats climbing", "adorable cats", "orange cats", "cats and dogs");
             $modified_keywords = array_merge($keywords, $additional_keywords);
             return $modified_keywords;
         }
         add_filter('atai_seo_keywords', 'custom_atai_seo_keywords', 10, 3);
       *
       */
      $keywords = apply_filters( 'atai_seo_keywords', $keywords, $attachment_id, $post_id );

      return $keywords;
    }

    /**
     * Return array of keywords from Yoast SEO.
     *
     * @since 1.0.28
     * @access public
     *
     * @param integer $attachment_id ID of the attachment.
     * @param integer $post_id ID of the post that has keywords. Can be NULL.
     *
     * @return Array of keywords, or empty array if none.
     */
    public function yoast_seo_keywords( $attachment_id, $post_id ) {
      // Bail early if Yoast SEO is not installed.
      if ( ! ATAI_Utility::has_yoast() ) {
        return array();
      }

      global $wpdb;

      // If post ID is null, we may still be able to get it directly from the Yoast data for this attachment:
      if ( ! $post_id ) {
        $yoast_post_sql = "select post_id from " . $wpdb->prefix . "yoast_seo_links where target_post_id = %d";
        $results = $wpdb->get_results( $wpdb->prepare($yoast_post_sql, $attachment_id) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,,WordPress.DB.PreparedSQL.NotPrepared

        if ( count( $results ) > 0 ) {
          $post_id = $results[0]->post_id;
        }
      }

       // If we don't have the post, we have to stop here.
      if ( ! $post_id ) {
        return array();
      }

      $keyword_sql = <<<SQL
select meta_value as focus_keywords
from {$wpdb->postmeta}
where meta_key = '_yoast_wpseo_focuskw'
  and post_id = %d
SQL;

      // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,,WordPress.DB.PreparedSQL.NotPrepared
      $keywords = $wpdb->get_results( $wpdb->prepare($keyword_sql, $post_id) );

      if ( count( $keywords ) == 0 || strlen( $keywords[0]->focus_keywords ) == 0 ) {
        return array();
      }

      $final_keywords = explode( ',', $keywords[0]->focus_keywords );

      // Retrieve related keyphrases, if any
      $keyword_sql = <<<SQL
select meta_value as related_keywords
from {$wpdb->postmeta}
where meta_key = '_yoast_wpseo_focuskeywords'
  and post_id = %d
SQL;

      // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,,WordPress.DB.PreparedSQL.NotPrepared
      $keywords = $wpdb->get_results( $wpdb->prepare($keyword_sql, $post_id) );

      if ( count( $keywords ) > 0 ) {
        $related_keywords = json_decode( $keywords[0]->related_keywords );
        foreach ( $related_keywords as $keyword_data ) {
          array_push( $final_keywords, $keyword_data->keyword );
        }
      }

      return $final_keywords;
    }

    /**
     * Return array of keywords from AllInOne SEO.
     *
     * @since 1.0.28
     * @access public
     *
     * @param integer $attachment_id ID of the attachment.
     * @param integer $post_id ID of the post that has keywords. Can be NULL.
     *
     * @return Array of keywords, or empty array if none.
     */
    public function aio_seo_keywords( $attachment_id, $post_id ) {
      // Bail early if All in One SEO is not active.
      if ( ! ATAI_Utility::has_aioseo() ) {
        return array();
      }

      // Bail early if $post_id is null.
      if ( ! $post_id ) {
        return array();
      }

      global $wpdb;

      $keyword_sql = <<<SQL
select keyphrases
from {$wpdb->prefix}aioseo_posts
where post_id = %d;
SQL;

      // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,,WordPress.DB.PreparedSQL.NotPrepared
      $keywords = $wpdb->get_results( $wpdb->prepare($keyword_sql, $post_id) );

      if ( count( $keywords ) == 0 || strlen( $keywords[0]->keyphrases ) == 0 ) {
        return array();
      }

      $keyphrase_data = json_decode( $keywords[0]->keyphrases );
      $final_keywords = array( $keyphrase_data->focus->keyphrase );

      if ( isset( $keyphrase_data->additional ) ) {
        foreach ( $keyphrase_data->additional as $additional_data ) {
          array_push( $final_keywords, $additional_data->keyphrase );
        }
      }

      return $final_keywords;
    }

    /**
     * Return array of keywords from RankMath.
     *
     * @since 1.0.28
     * @access public
     *
     * @param integer $attachment_id ID of the attachment.
     * @param integer $post_id ID of the post that has keywords. Can be NULL.
     *
     * @return Array of keywords, or empty array if none.
     */
    public function rankmath_seo_keywords( $attachment_id, $post_id ) {
      // Bail early if RankMath is not active.
      if ( ! ATAI_Utility::has_rankmath() ) {
        return array();
      }

      // Bail early if $post_id is null.
      if ( ! $post_id ) {
        return array();
      }

      global $wpdb;

      $keyword_sql = <<<SQL
select meta_value as focus_keywords
from {$wpdb->postmeta}
where meta_key = 'rank_math_focus_keyword'
  and post_id = %d
SQL;

      // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,,WordPress.DB.PreparedSQL.NotPrepared
      $keywords = $wpdb->get_results( $wpdb->prepare($keyword_sql, $post_id) );

      if ( count( $keywords ) == 0 || strlen( $keywords[0]->focus_keywords ) == 0 ) {
        return array();
      }

      return explode( ',', $keywords[0]->focus_keywords );
    }

    /**
     * Return array of keywords from SEOPress.
     *
     * @since 1.0.31
     * @access public
     *
     * @param integer $attachment_id ID of the attachment.
     * @param integer $post_id ID of the post that has keywords. Can be NULL.
     *
     * @return Array of keywords, or empty array if none.
     */
    public function seopress_seo_keywords( $attachment_id, $post_id ) {
      // Bail early if SEOPress is not active.
      if ( ! ATAI_Utility::has_seopress() ) {
        return array();
      }

      // Bail early if $post_id is null.
      if ( ! $post_id ) {
        return array();
      }

      global $wpdb;

      $keyword_sql = <<<SQL
select meta_value as focus_keywords
from {$wpdb->postmeta}
where meta_key = '_seopress_analysis_target_kw'
  and post_id = %d
SQL;

      // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,,WordPress.DB.PreparedSQL.NotPrepared
      $keywords = $wpdb->get_results( $wpdb->prepare($keyword_sql, $post_id) );

      if ( count( $keywords ) == 0 || strlen( $keywords[0]->focus_keywords ) == 0 ) {
        return array();
      }

      return explode( ',', $keywords[0]->focus_keywords );
    }

    /**
     * Return array of keywords from Squirrly SEO.
     *
     * @since 1.0.36
     * @access public
     *
     * @param integer $attachment_id ID of the attachment.
     * @param integer $post_id ID of the post that has keywords. Can be NULL.
     *
     * @return Array of keywords, or empty array if none.
     */
    public function squirrly_seo_keywords( $attachment_id, $post_id ) {
      // Bail early if Squirrly is not active.
      if ( ! ATAI_Utility::has_squirrly() ) {
        return array();
      }

      // Bail early if $post_id is null.
      if ( ! $post_id ) {
        return array();
      }

      global $wpdb;
      $lookup_key = md5($post_id); // Squirrly uses a hash of the post ID as the key for their database table

      $keyword_sql = <<<SQL
select seo
from {$wpdb->prefix}qss
where url_hash = %s;
SQL;

      // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,,WordPress.DB.PreparedSQL.NotPrepared
      $seo_data = $wpdb->get_results( $wpdb->prepare($keyword_sql, $lookup_key) );
      if ( count( $seo_data ) == 0 || strlen( $seo_data[0]->seo ) == 0 ) {
        return array();
      }

      $seo_data = unserialize($seo_data[0]->seo);
      $keywords = $seo_data["keywords"];
      return explode( ',', $keywords );
    }

    /**
     * Return array of keywords from post title
     *
     * @since 1.0.36
     * @access public
     *
     * @param integer $attachment_id ID of the attachment.
     * @param integer $post_id ID of the post that has keywords. Can be NULL.
     *
     * @return Array of keywords, or empty array if none.
     */
    public function post_title_seo_keywords( $attachment_id ) {
      global $wpdb;
      $keyword_sql = <<<SQL
select COALESCE(post_title, '') as title
from {$wpdb->posts}
where ID = (select post_parent from {$wpdb->posts} where ID = %d);
SQL;

      // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,,WordPress.DB.PreparedSQL.NotPrepared
      $keyword_source = $wpdb->get_results( $wpdb->prepare($keyword_sql, $attachment_id) );
      if ( count( $keyword_source ) == 0 || strlen( $keyword_source[0]->title ) == 0 ) {
        return;
      }

      return $keyword_source[0]->title;
    }

    /**
     * Return array of keywords from The SEO Framework plugin.
     *
     * @since 1.6.0
     * @access public
     *
     * @param integer $attachment_id ID of the attachment.
     * @param integer $post_id ID of the post that has keywords. Can be NULL.
     *
     * @return Array of keywords, or empty array if none.
     */
    public function theseoframework_seo_keywords( $attachment_id, $post_id ) {
      // Bail early if plugin is not active.
      if ( ! ATAI_Utility::has_theseoframework() ) {
        return array();
      }

      // Bail early if $post_id is null.
      if ( ! $post_id ) {
        return array();
      }

      global $wpdb;

      $keyword_sql = <<<SQL
select meta_value as keyword_data
from {$wpdb->postmeta}
where meta_key = '_tsfem-extension-post-meta'
  and post_id = %d
SQL;

      // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,,WordPress.DB.PreparedSQL.NotPrepared
      $keyword_data = $wpdb->get_var( $wpdb->prepare($keyword_sql, $post_id) );
      if ( empty($keyword_data) ) {
        return array();
      }

      $keyword_data = unserialize(unserialize($keyword_data));

      if ( empty($keyword_data['focus']) || empty($keyword_data['focus']['kw']) ) {
        return array();
      }

      $keywords = array();
      foreach ( $keyword_data['focus']['kw'] as $kw ) {
        if ( !empty($kw['keyword']) ) {
          array_push( $keywords, $kw['keyword'] );
        }
      }

      return $keywords;
    }
  
  /**
   * Return array of keywords from SmartCrawl Pro.
   *
   * @since 1.9.91
   * @access public
   *
   * @param integer $attachment_id ID of the attachment.
   * @param integer $post_id ID of the post that has keywords. Can be NULL.
   *
   * @return Array of keywords, or empty array if none.
   */
  public function smartcrawl_seo_keywords($attachment_id, $post_id)
  {
    // Ensure SmartCrawl is active
    if (! ATAI_Utility::has_smartcrawl()) {
      return array();
    }

    // Bail if post ID is missing
    if (! $post_id) {
      return array();
    }

    // Fetch SmartCrawl focus keywords
    $raw_focus_keywords = get_post_meta($post_id, '_wds_focus-keywords', true);

    if (empty($raw_focus_keywords)) {
      return array();
    }

    // Convert serialized data if needed
    if (is_serialized($raw_focus_keywords)) {
      $focus_keywords = unserialize($raw_focus_keywords);
    } else {
      $focus_keywords = explode(',', $raw_focus_keywords);
    }

    return array_map('trim', (array) $focus_keywords);
  }

  /**
   * Generate alt text for newly added image/attachment.
   *
   * For WPML-enabled sites, also generates alt text for all translated versions
   * of the attachment in their respective languages.
   *
   * @since 1.0.0
   * @access public
   *
   * @param integer $attachment_id ID of the newly uploaded image/attachment.
   *
   * @changed 2025-10-02 Fixed WPML language detection by passing lang explicitly
   *                     to avoid race conditions with WPML metadata initialization.
   */
  public function add_attachment( $attachment_id ) {
    if ( get_option( 'atai_enabled' ) === 'no' ) {
      return;
    }

    // Check if attachment is eligible (including post type exclusions)
    if ( ! $this->is_attachment_eligible( $attachment_id ) ) {
      return;
    }

    $this->generate_alt( $attachment_id );

    // Generate alt text for WPML translated versions in their respective languages
    if ( ! ATAI_Utility::has_wpml() ) {
      return;
    }

    $active_languages = apply_filters( 'wpml_active_languages', NULL );

    // Guard against WPML returning null/false
    if ( empty( $active_languages ) || ! is_array( $active_languages ) ) {
      return;
    }

    $language_codes = array_keys( $active_languages );

    foreach ( $language_codes as $lang ) {
      $translated_attachment_id = apply_filters( 'wpml_object_id', $attachment_id, 'attachment', FALSE, $lang );

      // Ensure translated attachment exists, is different, is actually an attachment, and not trashed
      if ( ! $translated_attachment_id || $translated_attachment_id === $attachment_id ) {
        continue;
      }

      $translated_post_type = get_post_type( $translated_attachment_id );
      $translated_post_status = get_post_status( $translated_attachment_id );

      if ( 'attachment' !== $translated_post_type || 'trash' === $translated_post_status ) {
        continue;
      }

      // Pass language explicitly to avoid timing issues with WPML metadata
      // Note: force_lang setting is enforced inside generate_alt() if enabled
      $this->generate_alt( $translated_attachment_id, null, array( 'lang' => $lang ) );
    }
  }


  /**
   * Generate alt text in bulk
   *
   * @since 1.0.0
   * @access public
   */
  public function ajax_bulk_generate() {
    check_ajax_referer( 'atai_bulk_generate', 'security' );

    // Check permissions
    $this->check_attachment_permissions();
    

    // Conservative memory management - only increase if needed
    $current_memory = ini_get('memory_limit');
    if ($current_memory !== '-1' && $current_memory !== 'unlimited') {
      // Simple check: if current limit looks low, increase to 128M
      $current_numeric = (int) $current_memory;
      if ($current_numeric > 0 && $current_numeric < 128) {
        @ini_set('memory_limit', '128M');
      }
    }
    
    // Allow user abort to prevent runaway processes
    ignore_user_abort(false);
    
    // Reset execution time for this batch
    if (function_exists('set_time_limit')) {
      @set_time_limit(60);
    }

    global $wpdb;
    $post_id = absint( $_REQUEST['post_id'] ?? 0 );
    $last_post_id = absint( $_REQUEST['last_post_id'] ?? 0 );
    $query_limit = min( max( absint( $_REQUEST['posts_per_page'] ?? 0 ), 1 ), 5 ); // 5 images per batch max
    $keywords = is_array($_REQUEST['keywords'] ?? null) ? array_map('sanitize_text_field', $_REQUEST['keywords']) : [];
    $negative_keywords = is_array($_REQUEST['negativeKeywords'] ?? null) ? array_map('sanitize_text_field', $_REQUEST['negativeKeywords']) : [];
    $mode = sanitize_text_field( $_REQUEST['mode'] ?? 'missing' );
    $only_attached = sanitize_text_field( $_REQUEST['onlyAttached'] ?? '0' );
    $only_new = sanitize_text_field( $_REQUEST['onlyNew'] ?? '0' );
    $wc_products = sanitize_text_field( $_REQUEST['wcProducts'] ?? '0' );
    $wc_only_featured = sanitize_text_field( $_REQUEST['wcOnlyFeatured'] ?? '0' );
    $batch_id = sanitize_text_field( $_REQUEST['batchId'] ?? '0' );
    $images_successful = $images_skipped = $loop_count = 0;
    $processed_ids = array(); // Track processed IDs for bulk-select cleanup
    
    
    // Get accumulated skip reasons from previous batches
    $skip_reasons = get_transient('atai_bulk_skip_reasons_' . get_current_user_id()) ?: array();
    $redirect_url = admin_url( 'admin.php?page=atai-bulk-generate' );
    $recursive = true;

    if ( $mode === 'all' ) {
      $images_to_update_sql = <<<SQL
SELECT p.ID as post_id
FROM {$wpdb->posts} p
WHERE p.ID > %d
  AND (p.post_mime_type LIKE 'image/%')
  AND p.post_type = 'attachment'
  AND (p.post_status = 'inherit')
SQL;
    } else {
      // Default to 'missing' mode
      // Processes images that are missing alt text
      $images_to_update_sql = <<<SQL
SELECT p.ID as post_id
FROM {$wpdb->posts} p
    LEFT JOIN {$wpdb->postmeta} AS pm
       ON (p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt')
WHERE p.ID > %d
  AND (p.post_mime_type LIKE 'image/%')
  AND (pm.post_id IS NULL OR TRIM(COALESCE(pm.meta_value, '')) = '')
  AND p.post_type = 'attachment'
  AND (p.post_status = 'inherit')
SQL;
    }

    if ( $post_id ) {
      $images_to_update_sql = $images_to_update_sql . $wpdb->prepare(" AND (p.post_parent = %d)", $post_id);
    }
    else {
      if ( $only_attached === '1' ) {
        $images_to_update_sql = $images_to_update_sql . " AND (p.post_parent > 0)";
      }

      if ( $only_new === '1' ) {
        $atai_asset_table = $wpdb->prefix . ATAI_DB_ASSET_TABLE;
        $images_to_update_sql = $images_to_update_sql . " AND (NOT EXISTS(SELECT 1 FROM {$atai_asset_table} WHERE wp_post_id = p.ID))";
      }

      if ($wc_products === '1') {
        $images_to_update_sql = $images_to_update_sql . " AND (EXISTS(SELECT 1 FROM {$wpdb->posts} p2 WHERE p2.ID = p.post_parent and p2.post_type = 'product'))";
      }

      if ($wc_only_featured === '1') {
        $images_to_update_sql = $images_to_update_sql . " AND (EXISTS(SELECT 1 FROM {$wpdb->postmeta} pm2 WHERE pm2.post_id = p.post_parent and pm2.meta_key = '_thumbnail_id' and CAST(pm2.meta_value as UNSIGNED) = p.ID))";
      }

      // Exclude images attached to specific post types
      $excluded_post_types = get_option( 'atai_excluded_post_types' );
      if ( ! empty( $excluded_post_types ) ) {
        $post_types = array_map( 'trim', explode( ',', $excluded_post_types ) );
        $post_types_placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
        $images_to_update_sql = $images_to_update_sql . " AND (p.post_parent = 0 OR NOT EXISTS(SELECT 1 FROM {$wpdb->posts} p3 WHERE p3.ID = p.post_parent AND p3.post_type IN ($post_types_placeholders)))";
      }
    }

    if ( $mode === 'bulk-select' ) {
      $images_to_update = get_transient( 'alttext_bulk_select_generate_' . $batch_id );
      
      if ( ! is_array( $images_to_update ) ) {
        $images_to_update = [];
      }
      
      // Debug: Log what we found in the transient

      if ( $url = get_transient( 'alttext_bulk_select_generate_redirect_' . $batch_id ) ) {
        $redirect_url = $url;
      }
    } else {
      $images_to_update_sql = $images_to_update_sql . " GROUP BY p.ID ORDER BY p.ID LIMIT %d";
      
      // Handle prepared statement with excluded post types
      $prepare_params = array( $last_post_id ); // Add last_post_id parameter
      if ( ! empty( $excluded_post_types ) ) {
        $prepare_params = array_merge( array( $last_post_id ), $post_types, array( $query_limit ) );
      } else {
        $prepare_params = array( $last_post_id, $query_limit );
      }
      
      // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,,WordPress.DB.PreparedSQL.NotPrepared
      $images_to_update = $wpdb->get_results( $wpdb->prepare( $images_to_update_sql, $prepare_params ) );
    }


    
    if ( count( $images_to_update ) == 0 ) {
      // Get final accumulated skip reasons
      $final_skip_reasons = get_transient('atai_bulk_skip_reasons_' . get_current_user_id()) ?: array();
      
      // Clean up the transient
      delete_transient('atai_bulk_skip_reasons_' . get_current_user_id());
      
      
      // Determine appropriate completion message based on context
      $completion_message = $last_post_id > 0 
        ? __( 'Bulk generation complete! All remaining images have been processed.', 'alttext-ai' )
        : __( 'All images already have alt text or are not eligible for processing.', 'alttext-ai' );
      
      // Build skip reasons subtitle
      $subtitle = '';
      $total_skipped = array_sum($final_skip_reasons);
      if ( $total_skipped > 0 ) {
        $reason_messages = array();
        if ( isset( $final_skip_reasons['ineligible'] ) && $final_skip_reasons['ineligible'] > 0 ) {
          $reason_messages[] = sprintf(__('%d ineligible (size/format/settings)', 'alttext-ai'), $final_skip_reasons['ineligible']);
        }
        if ( isset( $final_skip_reasons['api_error'] ) && $final_skip_reasons['api_error'] > 0 ) {
          $reason_messages[] = sprintf(__('%d API errors', 'alttext-ai'), $final_skip_reasons['api_error']);
        }
        if ( isset( $final_skip_reasons['generation_failed'] ) && $final_skip_reasons['generation_failed'] > 0 ) {
          $reason_messages[] = sprintf(__('%d generation failures', 'alttext-ai'), $final_skip_reasons['generation_failed']);
        }
        
        if ( ! empty( $reason_messages ) ) {
          $subtitle = sprintf(__('Skip reasons: %s', 'alttext-ai'), implode(', ', $reason_messages));
        }
      }
        
      wp_send_json( array(
        'status' => 'success',
        'message' => $completion_message,
        'subtitle' => $subtitle,
        'process_count'   => 0,
        'success_count'   => 0,
        'last_post_id' => $last_post_id,
        'recursive' => false,
        'redirect_url' => $redirect_url,
      ) );
    }


    foreach ( $images_to_update as $image ) {
      $attachment_id = ( $mode === 'bulk-select' ) ? $image : $image->post_id;
      
      if ( defined( 'ATAI_BULK_DEBUG' ) ) {
        ATAI_Utility::log_error( sprintf("BulkGenerate: Attachment ID %d", $attachment_id) );
      }
      
      // Skip if attachment is not eligible
      if ( ! $this->is_attachment_eligible( $attachment_id, 'bulk' ) ) {
        $images_skipped++;
        $last_post_id = $attachment_id;  // IMPORTANT: Update last_post_id to prevent infinite loop
        
        // Track skip reason for user feedback
        $skip_reasons['ineligible'] = ($skip_reasons['ineligible'] ?? 0) + 1;
        
        // Log why it was skipped (with minimal detail for bulk operations)
        if ( defined( 'ATAI_BULK_DEBUG' ) ) {
          ATAI_Utility::log_error( sprintf("BulkGenerate: Skipped attachment ID %d (not eligible)", $attachment_id) );
        }
        
        if ( $mode === 'bulk-select' ) {
          // Mark for removal instead of immediate array manipulation
          $processed_ids[] = $attachment_id;
        }
        
        if ( ++$loop_count >= $query_limit ) {
          break;
        }
        continue;
      }
      
      $response = $this->generate_alt( $attachment_id, null, array( 'keywords' => $keywords, 'negative_keywords' => $negative_keywords ) );
      

      if ( $response === 'insufficient_credits' ) {
        
        wp_send_json( array(
          'status'      => 'success',
          'message'     => __( 'Images partially updated (no more credits).', 'alttext-ai' ),
          'process_count'   => $loop_count,
          'success_count'   => $images_successful,
          'last_post_id' => $last_post_id,
          'recursive'   => false,
          'redirect_url' => $redirect_url,
        ) );
      }

      if ( $response === 'url_access_error' ) {
        
        wp_send_json( array(
          'status'      => 'error',
          'message'     => __( 'Unable to access image URLs. Your images may be on a private server.', 'alttext-ai' ),
          'action_required' => 'url_access_fix',
          'last_post_id' => $attachment_id,  // Use current failing image as the last processed
        ) );
      }

      $last_post_id = $attachment_id;

      // generate_alt() returns: string (alt text or error code), WP_Error, or false
      // Success: non-empty string that isn't an error code
      // Failure: false, empty string, WP_Error, or error code string
      $is_error_code = false;
      if ( is_wp_error( $response ) ) {
        $is_error_code = true;
      } elseif ( is_string( $response ) ) {
        // Known error codes start with common error prefixes or are specific strings
        $is_error_code = (
          0 === strpos( $response, 'error_' ) ||
          0 === strpos( $response, 'invalid_' ) ||
          in_array( $response, array( 'insufficient_credits', 'url_access_error' ), true )
        );
      }

      if ( is_string( $response ) && $response !== '' && ! $is_error_code ) {
        $images_successful++;
      } else {
        // API call failed - track the reason
        $images_skipped++;
        $skip_reasons['generation_failed'] = ($skip_reasons['generation_failed'] ?? 0) + 1;
      }

      if ( $mode === 'bulk-select' ) {
        // Mark for removal instead of immediate array manipulation
        $processed_ids[] = $attachment_id;
      }

      if ( ++$loop_count >= $query_limit ) {
        break;
      }
    }

    // Efficient cleanup for bulk-select mode
    if ( $mode === 'bulk-select' ) {
      if ( ! empty( $processed_ids ) ) {
        // Remove processed IDs efficiently
        $images_to_update = array_diff( $images_to_update, $processed_ids );
        if ( empty( $images_to_update ) ) {
          delete_transient( 'alttext_bulk_select_generate_' . $batch_id );
          delete_transient( 'alttext_bulk_select_generate_redirect_' . $batch_id );
          $recursive = false;
        } else {
          set_transient( 'alttext_bulk_select_generate_' . $batch_id, $images_to_update, 2048 );
        }
      }
      
      // Clean up processed IDs array to free memory
      unset( $processed_ids );
    }

    // Save accumulated skip reasons for next batch (or final display)
    set_transient('atai_bulk_skip_reasons_' . get_current_user_id(), $skip_reasons, 3600);
    
    
    // Clear process lock when batch completes or we're about to finish
    
    // Simple batch message - no skip reasons during processing
    if ( $images_skipped > 0 ) {
      $message = sprintf(__('Batch processed: %d updated, %d skipped', 'alttext-ai'), $images_successful, $images_skipped);
    } else {
      $message = sprintf(__('Batch processed: %d updated', 'alttext-ai'), $images_successful);
    }

      
    
    wp_send_json( array(
      'status'          => 'success',
      'message'         => $message,
      'subtitle'        => '',
      'process_count'   => $loop_count,
      'success_count'   => $images_successful,
      'skipped_count'   => $images_skipped,
      'last_post_id'    => $last_post_id,
      'recursive'       => $recursive,
      'redirect_url' => $redirect_url,
    ) );
  }

  /**
   * Generate ALT text for a single image, based on URL-based parameters
   *
   * @since 1.0.10
   * @access public
   */
  public function action_single_generate() {
    // Bail early if action does not exist
    // or action is not relevant
    if ( ! isset( $_GET['atai_action'] ) || $_GET['atai_action'] !== 'generate' ) {
      return;
    }

    $attachment_id  = isset( $_GET['item'] ) ? absint( $_GET['item'] ) : 0;

    if ( ! $attachment_id ) {
      $attachment_id  = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0;
    }

    // Bail early if attachment ID is not valid
    if ( ! $attachment_id ) {
      return;
    }

    // Generate ALT text
    $this->generate_alt( $attachment_id );

    // Redirect back to edit page
    wp_safe_redirect( wp_get_referer() );
  }

  /**
   * Generate ALT text for a single image, via AJAX
   *
   * @since 1.0.11
   * @access public
   */
  public function ajax_single_generate() {
    check_ajax_referer( 'atai_single_generate', 'security' );
    
    // Check permissions
    $this->check_attachment_permissions();

    // Bail early if attachment ID does not exist, or ID is not numeric
    if ( ! isset( $_REQUEST['attachment_id'] ) || empty( $_REQUEST['attachment_id'] ) || ! is_numeric( $_REQUEST['attachment_id'] ) ) {
      return;
    }

    $attachment_id = absint( $_REQUEST['attachment_id'] );
    $keywords = is_array($_REQUEST['keywords']) ? array_map('sanitize_text_field', $_REQUEST['keywords']) : [];

    // Generate ALT text
    $response = $this->generate_alt( $attachment_id, null, array( 'keywords' => $keywords ) );

    if ( $response === 'insufficient_credits' ) {
      wp_send_json( array(
        'status' => 'error',
        'message' => 'You have no more credits available. Go to your account on AltText.ai to get more credits.',
      ) );
    }

    if ( $response === 'url_access_error' ) {
      wp_send_json( array(
        'status' => 'error',
        'message' => __( 'Unable to access image URL. Your site may be on a private server.', 'alttext-ai' ),
        'action_required' => 'url_access_fix',
      ) );
    }

    if ( ! is_array( $response ) && $response !== false ) {
      wp_send_json( array(
        'status' => 'success',
        'alt_text' => $response,
      ) );
    }

    wp_send_json( array(
      'status' => 'error',
    ) );
  }

  /**
   * Update ALT text via AJAX
   *
   * @since 1.4.4
   * @access public
   */
  public function ajax_edit_history() {
    check_ajax_referer( 'atai_edit_history', 'security' );
    
    // Check permissions
    $this->check_attachment_permissions();

    $attachment_id = absint( $_REQUEST['attachment_id'] ?? 0 );
    $alt_text = sanitize_text_field( $_REQUEST['alt_text'] ?? '' );

    if ( ! $attachment_id ) {
      wp_send_json( array(
        'status' => 'error',
        'message' => __( 'Invalid request.', 'alttext-ai' )
      ) );
    }

    update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );

    wp_send_json( array(
      'status' => 'success',
      'message' => __( 'Alt text updated.', 'alttext-ai' )
    ) );
  }


  /**
   * Check if the current user has permission to manage attachments
   *
   * @since 1.9.94
   * @access private
   * @return bool|void Returns true if user has permission, otherwise sends JSON error response and exits
   */
  private function check_attachment_permissions() {
    if ( ! current_user_can( 'upload_files' ) ) {
      wp_send_json( array(
        'status' => 'error',
        'message' => __( 'You do not have permission to manage attachments.', 'alttext-ai' )
      ) );
    }
    return true;
  }

  /**
   * Check if attachment is eligible for auto-generating ALT text via AJAX
   *
   * @since 1.0.10
   * @access public
   */
  public function ajax_check_attachment_eligibility() {
    check_ajax_referer( 'atai_check_attachment_eligibility', 'security' );

    // Check permissions
    $this->check_attachment_permissions();

    $attachment_id = absint( $_POST['attachment_id'] ?? 0 );

    // Bail early if post ID is not valid
    if ( ! $attachment_id ) {
      wp_send_json( array(
        'status' => 'error',
        'message' => __( 'Invalid post ID.', 'alttext-ai' )
      ) );
    }

    if ( ! $this->is_attachment_eligible( $attachment_id, 'check' ) ) {
      wp_send_json( array(
        'status' => 'error',
        'message' => __( 'Image is not eligible for auto-generating ALT text.', 'alttext-ai' )
      ) );
    }

    wp_send_json( array(
      'status' => 'success',
      'message' => __( 'Image is eligible for auto-generating ALT text.', 'alttext-ai' )
    ) );
  }


  /**
   * Add Generate ALT Text option to bulk actions
   *
   * @since 1.0.27
   * @access public
   *
   * @param Array $actions Array of bulk actions.
   */
  public function add_bulk_select_action( $actions ) {
    $actions[ 'alttext_options' ] = __( '&#8595; AltText.ai', 'alttext-ai' );
    $actions[ 'alttext_generate_alt' ] = __( 'Generate Alt Text', 'alttext-ai' );
    return $actions;
  }

  /**
   * Process bulk select action
   *
   * @since 1.0.27
   * @access public
   *
   * @param String $redirect URL to redirect to after processing.
   * @param String $do_action The action being taken.
   * @param Array $items Array of attachments/images multi-selected to take action on.
   *
   * @return String $redirect URL to redirect to.
   */
  public function bulk_select_action_handler( $redirect, $do_action, $items ) {
    // Bail early if action is not alttext_generate_alt
    if ( $do_action !== 'alttext_generate_alt' ) {
      return $redirect;
    }

    // Generate a random id to identify the bulk action request
    $batch_id = uniqid();

    // Store the attachment IDs in a transient
    set_transient( 'alttext_bulk_select_generate_' . $batch_id, $items, 2048 );

    // Store the redirect URL in a transient
    set_transient( 'alttext_bulk_select_generate_redirect_' . $batch_id, $redirect, 2048 );

    // Redirect to the bulk action handler
    $redirect_url = admin_url( 'admin.php?page=atai-bulk-generate&atai_action=bulk-select-generate&atai_batch_id=' . $batch_id );
    return $redirect_url;
  }

  /**
   * Render bulk select notice
   *
   * @since 1.0.27
   * @access public
   */
  public function render_bulk_select_notice() {
    // Get the count of images that were processed
    $count = get_transient( 'bulk_generate_alt' );

    // Bail early if no bulk generate alt action was done
    if ( $count === false ) {
      return;
    }

    // Construct the notice message
    $message = sprintf(
      "[AltText.ai] Finished generating alt text for %d %s.",
      $count,
      _n(
        'image',
        'images',
        $count
      )
    );

    // Display the notice
    echo "<div class=\"notice notice-success is-dismissible\"><p>", esc_html($message), "</p></div>";

    // Delete the transient
    delete_transient( 'bulk_generate_alt' );
  }

  /**
   * Process a new translation of an attachment from Polylang.
   *
   * @since 1.0.34
   * @access public
   *
   * @param Int $post_id The ID of the source post that was translated.
   * @param Int $tr_id The ID of the new translated post.
   * @param String $lang_slug Language code of the new translation.
   *
   * @changed 2025-10-02 Pass explicit language to avoid race conditions (similar to WPML fix).
   */
  public function on_translation_created( $post_id, $tr_id, $lang_slug ) {
    $post = get_post($post_id);
    if (!isset($post)) {
      return;
    }

    // Bail early unless we have an image
    if ($post->post_type != "attachment" || $post->post_status != "inherit" || (0 != substr_compare($post->post_mime_type, "image", 0, 5))) {
      return;
    }

    // Generate alt text for the translation with explicit language
    // Pass language explicitly to avoid timing issues with Polylang metadata
    if ( get_option( 'atai_enabled' ) === 'no' || ! $this->is_attachment_eligible( $tr_id, 'add' ) ) {
      return;
    }

    // Normalize language code (Polylang may pass uppercase or region variants)
    $lang_slug = strtolower( (string) $lang_slug );

    // Pass language explicitly (Polylang provides it in the hook)
    $this->generate_alt( $tr_id, null, array( 'lang' => $lang_slug ) );
  }

  /**
   * Processes the uploaded CSV file to import ALT text for attachments.
   *
   * This method handles the CSV file upload, validates the file structure,
   * and updates the ALT text of the corresponding attachments in the WordPress
   * database. The CSV file should contain columns 'asset_id' and 'alt_text'.
   *
   * @since 1.1.0
   * @access public
   *
   * @return array Associative array containing the status and message of the operation.
   *               Returns 'success' status and a success message on successful import.
   *               Returns 'error' status and an error message if any issue occurs.
   */
  public function process_csv() {
    $uploaded_file = $_FILES['csv'] ?? []; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
    $moved_file = wp_handle_upload( $uploaded_file, array( 'test_form' => false ) );

    // Bail early if file upload failed
    if ( ! $moved_file || isset( $moved_file['error'] ) ) {
      return array(
        'status' => 'error',
        'message' => $moved_file['error']
      );
    }

    $images_updated = 0;
    $filename = $moved_file['file'];
    $handle = fopen( $filename, "r" );

    // Read the first row as header
    $header = fgetcsv( $handle, ATAI_CSV_LINE_LENGTH, ',', '"' );

    // Check if the required columns exist and capture their indexes
    $asset_id_index = array_search( 'asset_id', $header );
    $image_url_index = array_search( 'url', $header );
    $alt_text_index = array_search( 'alt_text', $header );

    // Bail early if required columns do not exist
    if ( $asset_id_index === false || $alt_text_index === false ) {
      fclose( $handle );
      unlink( $filename );

      return array(
        'status' => 'error',
        'message' => __( 'Invalid CSV file. Please make sure the file has the required columns.', 'alttext-ai' )
      );
    }

    // Loop through the rest of the rows and use the captured indexes to get the values
    while ( ( $data = fgetcsv( $handle, 1000, ',', '"' ) ) !== FALSE ) {
      global $wpdb;

      $asset_id = $data[$asset_id_index];
      $alt_text = $data[$alt_text_index];

      // Get the attachment ID from the asset ID
      $attachment_id = ATAI_Utility::find_atai_asset($asset_id);

      if ( ! $attachment_id && $image_url_index !== false ) {
        // If we don't have the attachment ID, try to get it from the URL
        $image_url = $data[$image_url_index];
        $attachment_id = attachment_url_to_postid( $image_url );

        if ( !empty($attachment_id) ) {
          ATAI_Utility::record_atai_asset($attachment_id, $asset_id);
        }
      }

      if ( ! $attachment_id ) {
        // If we still don't have the attachment ID, skip this row
        continue;
      }

      // Update the ALT text
      update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );
      $images_updated++;

      if ( empty( $alt_text ) ) {
        // Do not clear other values if alt text was empty
        continue;
      }

      // Update the post title, caption, and description if the corresponding option is enabled
      $post_value_updates = array();

      if ( get_option( 'atai_update_title' ) === 'yes' ) {
        $post_value_updates['post_title'] = $alt_text;
      };

      if ( get_option( 'atai_update_caption' ) === 'yes' ) {
        $post_value_updates['post_excerpt'] = $alt_text;
      };

      if ( get_option( 'atai_update_description' ) === 'yes' ) {
        $post_value_updates['post_content'] = $alt_text;
      };

      if ( ! empty( $post_value_updates ) ) {
        $post_value_updates['ID'] = $attachment_id;
        wp_update_post( $post_value_updates );
      };
    }

    fclose( $handle );
    unlink( $filename );

    $message = __( '[AltText.ai] No images were matched.', 'alttext-ai' );

    if ( $images_updated ) {
      $message = sprintf(
        _n(
          '[AltText.ai] Successfully imported alt text for %d image.',
          '[AltText.ai] Successfully imported alt text for %d images.',
          $images_updated,
          'alttext-ai'
        ),
        $images_updated
      );
    }

    return array(
      'status' => 'success',
      'message' => $message
    );
  }

  /**
   * Add a filter to the media library to filter images by ALT text presence
   *
   * @since 1.3.5
   * @access public
   */
  public function add_media_alt_filter( $post_type ) {
    if ( $post_type !== 'attachment' ) {
      return;
    };

    $atai_filter = sanitize_text_field( $_GET['atai_filter'] ?? 'all' );

    echo '<select id="filter-by-alt" name="atai_filter">';
    echo '<option value="all" ' . selected( $atai_filter, 'all', false ) . '>' . esc_html__( 'Any alt text', 'alttext-ai' ) . '</option>';
    echo '<option value="missing" ' . selected( $atai_filter, 'missing', false ) . '>' . esc_html__( 'Without alt text', 'alttext-ai' ) . '</option>';
    echo '</select>';
  }

  /**
   * Filter the media library query to show only images missing ALT text
   *
   * @since 1.3.5
   * @access public
   */
  public function media_alt_filter_handler( $query ) {
    $is_media_screen = false;

    if ( function_exists( 'get_current_screen' ) && get_current_screen() ) {
      $is_media_screen = get_current_screen()->base === 'upload';
    }
    else {
      $is_media_screen = ( isset($query->query['post_type'] ) && ( $query->query['post_type'] === 'attachment' ) );
    }

    $atai_filter = sanitize_text_field( $_GET['atai_filter'] ?? 'all' );

    if ( ! is_admin() || ! $query->is_main_query() || ! $is_media_screen || $atai_filter === 'all' ) {
      return;
    }

    $meta_query = $query->get('meta_query') ? $query->get('meta_query') : array();

    $meta_query[] = array(
      'relation' => 'OR',
      array(
        'key' => '_wp_attachment_image_alt',
        'compare' => 'NOT EXISTS',
      ),
      array(
        'key' => '_wp_attachment_image_alt',
        'value' => '',
        'compare' => '=',
      ),
    );

    $query->set( 'meta_query', $meta_query );
  }
}
