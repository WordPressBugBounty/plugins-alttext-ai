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
    if ( ATAI_Utility::has_polylang() ) {
      $language = ATAI_Utility::polylang_lang_for_attachment($attachment_id);
    }
    elseif ( ATAI_Utility::has_wpml() ) {
      $language = ATAI_Utility::wpml_lang_for_attachment($attachment_id);
    }

    // Ensure we can translate this language
    if ( isset($language) && ! array_key_exists( $language, ATAI_Utility::supported_languages() ) ) {
      $language = NULL;
    }

    if ( ! isset( $language ) ) {
      $language = get_option( 'atai_lang' );
    }

    return $language;
	}

  /**
	 * Fetch API key stored by the plugin.
	 *
	 * @since    1.0.0
   * @access public
	 */
  public static function get_api_key() {
    // Support for file-based API Key
    if ( defined( 'ATAI_API_KEY' ) ) {
      $api_key = ATAI_API_KEY;
    } else {
      $api_key = get_option( 'atai_api_key' );
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
   * Return array of supported languages [lang_code => Display name]
   *
   * @since    1.0.29
   * @access public
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
      "en" => "English",
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
      "pt" => "Portuguese",
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

  public static function print( $message, $die = false ) {
    echo '<pre>';

    if ( is_array( $message ) || is_object( $message ) ) {
      print_r( $message );
    } else {
      var_dump( $message );
    }

    echo '</pre>';

    if ( $die ) die();
  }
}
