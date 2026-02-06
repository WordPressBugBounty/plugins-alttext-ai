<?php
/**
 * House for utility methods
 *
 * @link       https://alttext.ai
 * @since      1.0.0
 *
 * @package    ATAI
 * @subpackage ATAI/includes
 */

/**
 * Class containing utility methods of the plugin.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    ATAI
 * @subpackage ATAI/includes
 * @author     AltText.ai <info@alttext.ai>
 */
if ( ! class_exists( 'ATAI_Utility' ) ) {
class ATAI_Utility {
  /**
	 * Record the AltText.ai asset_id of an image attachment.
	 *
	 * @since    1.1.0
   * @access public
	 */
  public static function record_atai_asset($attachment_id, $asset_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . ATAI_DB_ASSET_TABLE;

    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query(
      $wpdb->prepare(
        "INSERT INTO {$table_name}(asset_id, wp_post_id) VALUES (%s, %d) ON DUPLICATE KEY UPDATE wp_post_id = %d;",
        $asset_id, $attachment_id, $attachment_id
      )
    );
    // phpcs:enable
	}

  /**
	 * Find the WP post ID from an AltText.ai asset ID
	 *
	 * @since    1.1.0
   * @access public
	 */
  public static function find_atai_asset($asset_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . ATAI_DB_ASSET_TABLE;

    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    return $wpdb->get_var( $wpdb->prepare("SELECT wp_post_id FROM {$table_name} WHERE asset_id = %s", $asset_id) );
	}

  /**
	 * Remove AltText.ai data for a WP post
	 *
	 * @since    1.1.0
   * @access public
	 */
  public static function remove_atai_asset($post_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . ATAI_DB_ASSET_TABLE;

    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    $wpdb->query( $wpdb->prepare("DELETE FROM {$table_name} WHERE wp_post_id = %d", $post_id) );
	}

  /**
	 * Find attachment post ID based on URL
	 *
	 * @since    1.4.7
   * @access public
	 */
  public static function lookup_attachment_id($url, $parent_post_id = null) {
    global $wpdb;

    // This is an improved version of attachment_url_to_postid()
    // If given the parent post ID, we use that to make the query much faster.
    // We also handle WordPress images which have been auto-scaled, and have the "-scaled" suffix.
    // cf: https://make.wordpress.org/core/2019/10/09/introducing-handling-of-big-images-in-wordpress-5-3/
    // and https://developer.wordpress.org/reference/hooks/big_image_size_threshold/

    // Construct the $path variable which will contain the attached file path to look for:
    $dir  = wp_get_upload_dir();
    $path = $url;

    $site_url   = parse_url( $dir['url'] );
    $image_path = parse_url( $path );

    // Force the protocols to match if needed.
    if ( isset( $image_path['scheme'] ) && ( $image_path['scheme'] !== $site_url['scheme'] ) ) {
      $path = str_replace( $image_path['scheme'], $site_url['scheme'], $path );
    }

    if ( str_starts_with( $path, $dir['baseurl'] . '/' ) ) {
      $path = substr( $path, strlen( $dir['baseurl'] . '/' ) );
    }

    $scaled_path = $path;
    $extension = pathinfo($path, PATHINFO_EXTENSION);
    if ( !empty($extension) ) {
      $offset = -( strlen($extension) + 1 ); // +1 for the 'dot' before the extension
      $scaled_path = substr_replace($scaled_path, "-scaled", $offset, 0);
    }
    else {
      $scaled_path = $path . "-scaled";
    }

    // Search for the attachment ID based on the path and optional parent post ID:
    if ( !empty($parent_post_id) ) {
      $sql = <<<SQL
SELECT pm.post_id
FROM {$wpdb->postmeta} pm
INNER JOIN
    {$wpdb->posts} p ON pm.post_id = p.ID
WHERE
    p.post_parent = {$parent_post_id}
AND
    pm.meta_key = '_wp_attached_file'
AND
    ( (pm.meta_value = %s) OR (pm.meta_value = %s) )
LIMIT 1
SQL;
    }
    else {
      $sql = <<<SQL
SELECT pm.post_id
FROM {$wpdb->postmeta} pm
WHERE
    pm.meta_key = '_wp_attached_file'
AND
    ( (pm.meta_value = %s) OR (pm.meta_value = %s) )
LIMIT 1
SQL;
    }

    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
    $sql = $wpdb->prepare($sql, $path, $scaled_path);
    $attachment_id = $wpdb->get_var( $sql );
    // phpcs:enable

    return !empty($attachment_id) ? intval( $attachment_id ) : null;
  }

  /**
	 * Determine if WooCommerce is installed/active:
	 *
	 * @since    1.0.25
   * @access public
	 */
  public static function has_woocommerce() {
    return is_plugin_active('woocommerce/woocommerce.php');
	}

  /**
	 * Determine if Yoast is installed/active:
	 *
	 * @since    1.0.29
   * @access public
	 */
  public static function has_yoast() {
    return is_plugin_active('wordpress-seo/wp-seo.php') || is_plugin_active('wordpress-seo-premium/wp-seo-premium.php');
	}

  /**
	 * Determine if AllInOne SEO is installed/active:
	 *
	 * @since    1.0.29
   * @access public
	 */
  public static function has_aioseo() {
    return is_plugin_active('all-in-one-seo-pack/all_in_one_seo_pack.php');
	}

  /**
	 * Determine if RankMath is installed/active:
	 *
	 * @since    1.0.29
   * @access public
	 */
  public static function has_rankmath() {
    return is_plugin_active('seo-by-rank-math/rank-math.php');
	}

  /**
	 * Determine if SEOPress is installed/active:
	 *
	 * @since    1.0.31
   * @access public
	 */
  public static function has_seopress() {
    return is_plugin_active('wp-seopress/seopress.php');
	}

  /**
	 * Determine if SquirrlySEO is installed/active:
	 *
	 * @since    1.0.36
   * @access public
	 */
  public static function has_squirrly() {
    return is_plugin_active('squirrly-seo/squirrly.php');
	}

  /**
	 * Determine if The SEO Framework is installed/active:
	 *
	 * @since    1.6.0
   * @access public
	 */
  public static function has_theseoframework() {
    return defined('THE_SEO_FRAMEWORK_PRESENT') && THE_SEO_FRAMEWORK_PRESENT;
	}

  /**
	 * Determine if Polylang is installed/active:
	 *
	 * @since    1.0.29
   * @access public
	 */
  public static function has_polylang() {
    return function_exists("pll_current_language");
	}

  /**
	 * Determine if WPML is installed/active:
	 *
	 * @since    1.0.45
   * @access public
	 */
  public static function has_wpml() {
    return defined('ICL_LANGUAGE_CODE');
	}

  /**
   * Determine if SmartCrawl is installed/active.
   *
   * @since 1.9.91
   * @access public
   */
  public static function has_smartcrawl() {
    include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
    return in_array( 'smartcrawl-seo/wpmu-dev-seo.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) );
  }

  /**
	 * Get Polylang language for given attachment
	 *
	 * @since    1.0.45
   * @access public
   *
   * @param integer $attachment_id  ID of the attachment
	 */
  public static function polylang_lang_for_attachment( $attachment_id ) {
    global $wpdb;
    $language_sql = <<<SQL
select terms.slug
from {$wpdb->terms} terms
    inner join {$wpdb->term_taxonomy} tt on tt.term_id = terms.term_id
    inner join {$wpdb->term_relationships} tr on tr.term_taxonomy_id = tt.term_taxonomy_id
where tr.object_id = %d
    and tt.taxonomy = 'language';
SQL;

    // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared
    $lang_data = $wpdb->get_results( $wpdb->prepare($language_sql, $attachment_id) );
    $language = NULL;

    if ( count( $lang_data ) > 0 ) {
      $language = $lang_data[0]->slug;
    }

    return $language;
  }

  /**
   * Get WPML language for given attachment
   *
   * @since    1.0.45
   * @access public
   *
   * @param integer $attachment_id  ID of the attachment
   */
  public static function wpml_lang_for_attachment( $attachment_id ) {
    $language_details = apply_filters( 'wpml_post_language_details', NULL, $attachment_id );
    $language = $language_details["language_code"];
    return $language;
  }

  /**
	 * Determine language to use for a given attachment:
	 *
	 * @since    1.0.29
   * @access public
   *
   * @param integer $attachment_id  ID of the attachment
	 */
  public static function lang_for_attachment( $attachment_id ) {
    if ( ATAI_Utility::get_setting( 'atai_force_lang' ) === 'yes' ) {
      $language = ATAI_Utility::get_setting( 'atai_lang', self::get_default_language() );
    }
    else {
      if ( ATAI_Utility::has_polylang() ) {
        $language = ATAI_Utility::polylang_lang_for_attachment($attachment_id);
      }
      elseif ( ATAI_Utility::has_wpml() ) {
        $language = ATAI_Utility::wpml_lang_for_attachment($attachment_id);
      }
    }

    // Ensure we can translate this language
    if ( isset($language) && ! array_key_exists( $language, ATAI_Utility::supported_languages() ) ) {
      $language = NULL;
    }

    if ( ! isset( $language ) ) {
      $language = ATAI_Utility::get_setting( 'atai_lang', self::get_default_language() );
    }

    return $language;
	}

  /**
   * Get a setting with network fallback.
   *
   * When network-wide settings are enabled, this is authoritative - subsites
   * cannot override network settings even if local options exist.
   *
   * @since 1.10.16
   * @access public
   * @static
   *
   * @param string $option_name The option name.
   * @param mixed $default The default value if the option is not found.
   *
   * @return mixed The option value or default.
   */
  public static function get_setting( $option_name, $default = false ) {
    // If not multisite, just get the regular option
    if ( ! is_multisite() ) {
      return get_option( $option_name, $default );
    }

    // If we're on the main site, just get the regular option
    if ( is_main_site() ) {
      return get_option( $option_name, $default );
    }

    // Check if network all settings is enabled - this is authoritative
    if ( get_site_option( 'atai_network_all_settings' ) === 'yes' ) {
      $network_settings = get_site_option( 'atai_network_settings', array() );
      if ( array_key_exists( $option_name, $network_settings ) ) {
        return $network_settings[ $option_name ];
      }
      // Network controls all settings but key is missing - return default, not local option
      // This prevents subsites from accidentally using local values when network is authoritative
      return $default;
    }

    // Check if network API key is enabled (but not all settings)
    if ( get_site_option( 'atai_network_api_key' ) === 'yes' && $option_name === 'atai_api_key' ) {
      // Always fetch directly from main site to avoid stale cached values
      $main_site_id = get_main_site_id();
      switch_to_blog( $main_site_id );
      $value = get_option( $option_name, $default );
      restore_current_blog();
      return $value;
    }

    // Network settings not enabled - use local subsite option
    return get_option( $option_name, $default );
  }

  /**
   * Fetch API key stored by the plugin.
   *
   * Priority: ATAI_API_KEY constant > network settings > local option
   *
   * @since    1.0.0
   * @access public
   */
  public static function get_api_key() {
    // Support for file-based API Key (highest priority)
    if ( defined( 'ATAI_API_KEY' ) ) {
      $api_key = ATAI_API_KEY;
    } else {
      // Use get_setting which handles network/local fallback consistently
      $api_key = self::get_setting( 'atai_api_key', '' );
    }

    return apply_filters( 'atai_api_key', $api_key );
  }

  /**
   * Return array of supported AI models [model_name => Display name]
   *
   * @since    1.4.1
   * @access public
   */
  public static function supported_model_names() {
    $supported_models = array(
      null => "Use account default",
      "describe-detailed" => "Elaborated",
      "describe-regular" => "Standard",
      "describe-factual" => "Matter-of-fact",
      "succinct-describe-factual" => "Concise",
      "describe-terse" => "Terse"
    );

    return $supported_models;
  }

  /**
   * Get the default language based on WordPress site locale.
   *
   * Maps WordPress locale (e.g., 'en_US', 'de_DE') to our supported language codes.
   * Falls back to 'en' if the locale isn't supported.
   *
   * @since    1.10.18
   * @access   public
   * @static
   *
   * @return string The default language code.
   */
  public static function get_default_language() {
    $supported = self::supported_languages();
    $locale    = get_locale(); // e.g., 'en_US', 'de_DE', 'pt_BR', 'zh_CN'

    // Try exact match with regional variant (e.g., 'en_GB' -> 'en-gb')
    $locale_lower = strtolower( str_replace( '_', '-', $locale ) );
    if ( array_key_exists( $locale_lower, $supported ) ) {
      return $locale_lower;
    }

    // Try uppercase regional for Chinese (e.g., 'zh_CN' -> 'zh-CN')
    $parts = explode( '_', $locale );
    if ( count( $parts ) === 2 ) {
      $regional = strtolower( $parts[0] ) . '-' . strtoupper( $parts[1] );
      if ( array_key_exists( $regional, $supported ) ) {
        return $regional;
      }
    }

    // Try just the language code (e.g., 'en_US' -> 'en')
    $lang_code = strtolower( $parts[0] );
    if ( array_key_exists( $lang_code, $supported ) ) {
      return $lang_code;
    }

    // Fallback to English
    return 'en';
  }

  /**
   * Return array of supported languages [lang_code => Display name]
   *
   * @since    1.0.29
   * @access   public
   */
  public static function supported_languages() {
    $supported_languages = array(
      "af" => "Afrikaans",
      "sq" => "Albanian",
      "am" => "Amharic",
      "ar" => "Arabic",
      "hy" => "Armenian",
      "as" => "Assamese",
      "ay" => "Aymara",
      "az" => "Azerbaijani",
      "bm" => "Bambara",
      "eu" => "Basque",
      "be" => "Belarusian",
      "bn" => "Bengali",
      "bho" => "Bhojpuri",
      "bs" => "Bosnian",
      "bg" => "Bulgarian",
      "ca" => "Catalan",
      "ceb" => "Cebuano",
      "zh-CN" => "Chinese (Simplified)",
      "zh-TW" => "Chinese (Traditional)",
      "co" => "Corsican",
      "hr" => "Croatian",
      "cs" => "Czech",
      "da" => "Danish",
      "dv" => "Dhivehi",
      "doi" => "Dogri",
      "nl" => "Dutch",
      "en" => "English (American)",
      "en-gb" => "English (British)",
      "eo" => "Esperanto",
      "et" => "Estonian",
      "ee" => "Ewe",
      "fil" => "Filipino (Tagalog)",
      "fi" => "Finnish",
      "fr" => "French",
      "fy" => "Frisian",
      "gl" => "Galician",
      "ka" => "Georgian",
      "de" => "German",
      "el" => "Greek",
      "gn" => "Guarani",
      "gu" => "Gujarati",
      "ht" => "Haitian Creole",
      "ha" => "Hausa",
      "haw" => "Hawaiian",
      "he" => "Hebrew",
      "hi" => "Hindi",
      "hmn" => "Hmong",
      "hu" => "Hungarian",
      "is" => "Icelandic",
      "ig" => "Igbo",
      "ilo" => "Ilocano",
      "id" => "Indonesian",
      "ga" => "Irish",
      "it" => "Italian",
      "ja" => "Japanese",
      "jv" => "Javanese",
      "kn" => "Kannada",
      "kk" => "Kazakh",
      "km" => "Khmer",
      "rw" => "Kinyarwanda",
      "gom" => "Konkani",
      "ko" => "Korean",
      "kri" => "Krio",
      "ku" => "Kurdish",
      "ckb" => "Kurdish (Sorani)",
      "ky" => "Kyrgyz",
      "lo" => "Lao",
      "la" => "Latin",
      "lv" => "Latvian",
      "ln" => "Lingala",
      "lt" => "Lithuanian",
      "lg" => "Luganda",
      "lb" => "Luxembourgish",
      "mk" => "Macedonian",
      "mai" => "Maithili",
      "mg" => "Malagasy",
      "ms" => "Malay",
      "ml" => "Malayalam",
      "mt" => "Maltese",
      "mi" => "Maori",
      "mr" => "Marathi",
      "mni-Mtei" => "Meiteilon (Manipuri)",
      "lus" => "Mizo",
      "mn" => "Mongolian",
      "my" => "Myanmar (Burmese)",
      "ne" => "Nepali",
      "no" => "Norwegian",
      "ny" => "Nyanja (Chichewa)",
      "or" => "Odia (Oriya)",
      "om" => "Oromo",
      "ps" => "Pashto",
      "fa" => "Persian",
      "pl" => "Polish",
      "pt" => "Portuguese (Brazil)",
      "pt-pt" => "Portuguese (Portugal)",
      "pa" => "Punjabi",
      "qu" => "Quechua",
      "ro" => "Romanian",
      "ru" => "Russian",
      "sm" => "Samoan",
      "sa" => "Sanskrit",
      "gd" => "Scots Gaelic",
      "nso" => "Sepedi",
      "sr" => "Serbian",
      "st" => "Sesotho",
      "sn" => "Shona",
      "sd" => "Sindhi",
      "si" => "Sinhala (Sinhalese)",
      "sk" => "Slovak",
      "sl" => "Slovenian",
      "so" => "Somali",
      "es" => "Spanish",
      "su" => "Sundanese",
      "sw" => "Swahili",
      "sv" => "Swedish",
      "tl" => "Tagalog (Filipino)",
      "tg" => "Tajik",
      "ta" => "Tamil",
      "tt" => "Tatar",
      "te" => "Telugu",
      "th" => "Thai",
      "ti" => "Tigrinya",
      "ts" => "Tsonga",
      "tr" => "Turkish",
      "tk" => "Turkmen",
      "ak" => "Twi (Akan)",
      "uk" => "Ukrainian",
      "ur" => "Urdu",
      "ug" => "Uyghur",
      "uz" => "Uzbek",
      "vi" => "Vietnamese",
      "cy" => "Welsh",
      "xh" => "Xhosa",
      "yi" => "Yiddish",
      "yo" => "Yoruba",
      "zu" => "Zulu"
    );

    return $supported_languages;
  }

  /**
	 * Fetch error logs stored by the plugin.
	 *
	 * @since    1.0.0
   * @access public
	 */
  public static function get_error_logs() {
    return get_option( 'atai_error_logs', '' );
	}

	/**
	 * Log error in database.
	 *
	 * @since    1.0.0
   * @access public
   *
   * @param string  $error  The error to log.
	 */
  public static function log_error( $error ) {
    $error_logs = get_option( 'atai_error_logs', '' );
    $error_logs .= "- {$error}<br>";
    $error_logs = wp_kses(
      $error_logs,
      array(
        'a' => array(
            'href' => array(),
            'target' => array()
        ),
        'br' => array()
      )
    );

    update_option( 'atai_error_logs', $error_logs );
	}

  /**
   * Check if the site is publicly accessible.
   *
   * @since 1.6.3
   * @access public
   */
  public static function is_publicly_accessible() {
    $local_ips = array(
      '127.0.0.1',
      '::1'
    );

    return !in_array( $_SERVER['REMOTE_ADDR'], $local_ips );
  }

  /**
   * Get URL to buy more credits
   *
   * @since 1.7.3
   * @access public
   */
  public static function get_credits_url() {
    $base_url = "https://alttext.ai/subscriptions?utm_source=wp&utm_medium=dl";

    if ( get_option("atai_woo_marketplace", "no") === "yes" ) {
      $base_url .= "&woocommerce=1";
    }

    return $base_url;
  }

  /**
   * Get the correct file size for an attachment, supporting offloaded media.
   *
   * @since 1.9.9
   * @access public
   *
   * @param int $attachment_id The WordPress attachment ID.
   * @return int|null File size in bytes, or null if unavailable.
   */
  public static function get_attachment_size($attachment_id)
  {
    if (empty($attachment_id) || !is_numeric($attachment_id)) {
      ATAI_Utility::log_error("Invalid attachment ID provided for file size retrieval: " . print_r($attachment_id, true));
      return null;
    }

    // Check in `_wp_attachment_metadata`
    $metadata = wp_get_attachment_metadata($attachment_id);
    if (!empty($metadata) && isset($metadata['filesize'])) {
      return (int) $metadata['filesize'];
    }

    // Check if file size exists in WP Offload Media metadata
    $size = get_post_meta($attachment_id, 'as3cf_filesize_total', true);
    if (!empty($size)) {
      return (int) $size; // Already in bytes
    }

    // Fallback: Try local file check
    $file = get_attached_file($attachment_id);
    if (!empty($file) && file_exists($file)) {
      return filesize($file);
    }

    ATAI_Utility::log_error("File size unavailable for attachment ID: {$attachment_id}");
    return null;
  }

  /**
   * Check if settings are controlled by the network.
   *
   * @since 1.10.16
   * @access public
   * @static
   *
   * @return boolean True if settings are controlled by the network, false otherwise.
   */
  public static function is_network_controlled() {
    // If not multisite, settings are never network controlled
    if ( ! is_multisite() ) {
      return false;
    }
    
    // If we're on the main site, settings are never network controlled
    if ( is_main_site() ) {
      return false;
    }
    
    // Check if network all settings is enabled
    return get_site_option( 'atai_network_all_settings' ) === 'yes';
  }
}
} // End if class_exists check
