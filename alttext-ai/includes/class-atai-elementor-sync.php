<?php
/**
 * Syncs WordPress Media Library alt text into Elementor's cached page data.
 *
 * Elementor stores its own copy of image metadata in the _elementor_data post
 * meta. This class keeps that copy in sync whenever alt text changes in the
 * Media Library, so the Elementor editor modal always shows the current value.
 *
 * Two paths:
 *  - Hook path: fires on added_post_meta / updated_post_meta for a single image.
 *  - Bulk path: called after bulk alt-text generation to sync the entire page.
 *
 * @since      1.10.23
 * @package    ATAI
 * @subpackage ATAI/includes
 */
class ATAI_Elementor_Sync {

  /**
   * Maximum recursion depth for walking Elementor widget trees.
   * Real-world Elementor nesting is 3-4 levels; 20 is generous.
   */
  const MAX_DEPTH = 20;

  /**
   * Default maximum posts to sync per image alt-text save.
   * Caps the cost of the synchronous meta hook on large sites.
   * Operators can raise this via the atai_elementor_max_posts_per_sync filter.
   */
  const MAX_POSTS_PER_SYNC = 100;

  /**
   * When true, sync_alt_to_elementor() is a no-op.
   *
   * Set to true during bulk image processing (e.g. enrich_post_content) so that
   * per-image meta-hook fires don't race each other on the same _elementor_data
   * row. The caller is responsible for doing a single sync_post() after the bulk
   * operation completes.
   *
   * @var bool
   */
  public static $paused = false;

  // -------------------------------------------------------------------------
  // Public API
  // -------------------------------------------------------------------------

  /**
   * Sync alt text changes into Elementor cached data.
   *
   * Fires on added_post_meta / updated_post_meta. Finds all Elementor pages
   * using the changed image and updates their cached alt text.
   *
   * @since 1.10.23
   * @param int    $meta_id       The meta ID (unused).
   * @param int    $attachment_id The attachment whose alt text changed.
   * @param string $meta_key      The meta key being set.
   * @param string $alt_text      The new alt text value.
   */
  public function sync_alt_to_elementor( $meta_id, $attachment_id, $meta_key, $alt_text ) {
    if ( self::$paused ) {
      return;
    }

    if ( $meta_key !== '_wp_attachment_image_alt' ) {
      return;
    }

    $attachment_id = absint( $attachment_id );
    if ( ! $attachment_id ) {
      return;
    }

    // Skip entirely on sites without Elementor.
    if ( ! get_option( 'elementor_version' ) ) {
      return;
    }

    $alt_text = sanitize_text_field( $alt_text );

    $image_url = wp_get_attachment_url( $attachment_id );
    if ( ! $image_url ) {
      return;
    }

    // Find Elementor pages using this image.
    // Prefilter by exact URL OR attachment-id/class tokens to catch URL variants
    // (resized files, relative URLs, scheme changes) before inner authoritative checks.
    // LIMIT caps the per-image update cost; large sites can tune via the filter.
    global $wpdb;
    $max_posts      = max( 1, min( (int) apply_filters( 'atai_elementor_max_posts_per_sync', self::MAX_POSTS_PER_SYNC ), 5000 ) );
    $url_like       = '%' . $wpdb->esc_like( $image_url ) . '%';
    $id_regex       = '"id"[[:space:]]*:[[:space:]]*' . $attachment_id . '([^0-9]|$)';
    $class_id_regex = 'wp-image-' . $attachment_id . '([^0-9]|$)';
    $elementor_posts = $wpdb->get_col( $wpdb->prepare(
      "SELECT pm.post_id FROM {$wpdb->postmeta} pm
       INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
       WHERE pm.meta_key = '_elementor_data'
       AND (
         pm.meta_value LIKE %s
         OR pm.meta_value REGEXP %s
         OR pm.meta_value REGEXP %s
       )
       AND p.post_status IN ('publish', 'draft', 'pending', 'future', 'private')
       AND p.post_type NOT IN ('revision', 'attachment')
       LIMIT %d",
      $url_like,
      $id_regex,
      $class_id_regex,
      $max_posts
    ) );

    if ( $elementor_posts === null ) {
      error_log( 'ATAI: wpdb query failed in sync_alt_to_elementor for attachment ' . $attachment_id . ': ' . $wpdb->last_error ); // phpcs:ignore QITStandard.PHP.DebugCode.DebugFunctionFound -- Production error logging
      return;
    }

    if ( empty( $elementor_posts ) ) {
      return;
    }

    // In interactive contexts, verify the user can edit each post.
    // In programmatic contexts (cron, REST callback, CLI), skip the check —
    // the alt text update already succeeded, we're just propagating to Elementor's cache.
    $check_caps = ( get_current_user_id() > 0 );

    foreach ( $elementor_posts as $post_id ) {
      if ( $check_caps && ! current_user_can( 'edit_post', $post_id ) ) {
        continue;
      }
      $this->update_image_alt( $post_id, $attachment_id, $image_url, $alt_text );
    }
  }

  /**
   * Sync all images in an Elementor page with current Media Library alt text.
   *
   * Called from the bulk-refresh path after alt text has been generated for a
   * page's images, to propagate those values into Elementor's JSON cache.
   *
   * @since 1.10.23
   * @param int $post_id The post ID to sync.
   */
  public function sync_post( $post_id ) {
    $data = $this->load_data( $post_id );
    if ( ! $data ) {
      return;
    }

    // Bulk path: look up the current alt text from the Media Library for any
    // image found in the widget tree.
    $home_url    = home_url();
    $resolve_alt = function ( $attachment_id, $url ) use ( $home_url ) {
      if ( ! $attachment_id && ! empty( $url ) ) {
        $normalized    = ATAI_Utility::normalize_image_url( $url, $home_url );
        if ( $normalized ) {
          $attachment_id = ATAI_Utility::lookup_attachment_id( $normalized );
        }
      }
      if ( ! $attachment_id ) {
        return null;
      }
      $alt = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
      return ! empty( $alt ) ? sanitize_text_field( $alt ) : null;
    };

    $updated = $this->walk_widgets( $data, $resolve_alt );

    if ( $updated ) {
      $this->save_data( $post_id, $data );
    }
  }

  // -------------------------------------------------------------------------
  // Private helpers
  // -------------------------------------------------------------------------

  /**
   * Update alt text for a specific attachment in an Elementor page.
   *
   * @since 1.10.23
   * @param int    $post_id       The post/page ID.
   * @param int    $attachment_id The attachment ID.
   * @param string $image_url     The image URL to match.
   * @param string $alt_text      The new alt text.
   */
  private function update_image_alt( $post_id, $attachment_id, $image_url, $alt_text ) {
    $data = $this->load_data( $post_id );
    if ( ! $data ) {
      return;
    }

    // Hook path: match a specific attachment by ID or URL, return the known alt text.
    $home_url         = home_url();
    $normalized_target = ATAI_Utility::normalize_image_url( $image_url, $home_url );
    $resolve_alt      = function ( $att_id, $url ) use ( $attachment_id, $image_url, $normalized_target, $alt_text, $home_url ) {
      // Match by attachment ID (from wp-image-{id} class).
      if ( $att_id && $att_id === $attachment_id ) {
        return $alt_text;
      }
      if ( ! empty( $url ) ) {
        // Match by exact URL.
        if ( $url === $image_url ) {
          return $alt_text;
        }
        // Match by normalized URL (strips dimension suffixes like -300x200).
        if ( $normalized_target ) {
          $normalized_src = ATAI_Utility::normalize_image_url( $url, $home_url );
          if ( $normalized_src && $normalized_src === $normalized_target ) {
            return $alt_text;
          }
        }
      }
      return null;
    };

    $updated = $this->walk_widgets( $data, $resolve_alt );

    if ( $updated ) {
      $this->save_data( $post_id, $data );
    }
  }

  /**
   * Recursively walk an Elementor widget tree and update image alt text.
   *
   * The $resolve_alt callable encapsulates the matching strategy, allowing the
   * same walker to serve both the bulk-refresh and meta-hook paths.
   *
   * @since 1.10.23
   * @param array    $data        Elementor data array (by reference).
   * @param callable $resolve_alt fn( ?int $attachment_id, string $url ) => ?string alt text.
   * @param int      $depth       Current recursion depth.
   * @return bool True if any updates were made.
   */
  private function walk_widgets( array &$data, callable $resolve_alt, $depth = 0 ) {
    if ( $depth > self::MAX_DEPTH ) {
      return false;
    }

    $updated = false;

    foreach ( $data as &$element ) {
      if ( ! is_array( $element ) ) {
        continue;
      }

      $widget_type = $element['widgetType'] ?? null;

      // Image widget.
      if ( $widget_type === 'image' && isset( $element['settings']['image'] ) ) {
        if ( $this->apply_alt_to_image_entry( $element['settings']['image'], $resolve_alt ) ) {
          $updated = true;
        }
      // Image gallery widget.
      } elseif ( $widget_type === 'image-gallery' && isset( $element['settings']['gallery'] ) && is_array( $element['settings']['gallery'] ) ) {
        foreach ( $element['settings']['gallery'] as &$img ) {
          if ( $this->apply_alt_to_image_entry( $img, $resolve_alt ) ) {
            $updated = true;
          }
        }
        unset( $img );
      // Text-editor widget: update embedded <img> alt attributes.
      } elseif ( $widget_type === 'text-editor' && isset( $element['settings']['editor'] ) ) {
        // WP_HTML_Tag_Processor was introduced in newer WordPress releases.
        // Skip text-editor updates on older installs to avoid fatals.
        if ( ! $this->can_process_html_tags() ) {
          continue;
        }
        $html = $element['settings']['editor'];
        $tags = new WP_HTML_Tag_Processor( $html );
        $html_updated = false;

        while ( $tags->next_tag( 'img' ) ) {
          $att_id  = null;
          $classes = $tags->get_attribute( 'class' );
          if ( $classes && preg_match( '/(?:^|\s)wp-image-(\d+)(?:\s|$)/', $classes, $m ) ) {
            $att_id = (int) $m[1];
          }

          $src = $tags->get_attribute( 'src' ) ?? '';
          $alt = $resolve_alt( $att_id, $src );

          if ( $alt !== null && $tags->get_attribute( 'alt' ) !== $alt ) {
            $tags->set_attribute( 'alt', $alt );
            $html_updated = true;
          }
        }

        if ( $html_updated ) {
          $element['settings']['editor'] = $tags->get_updated_html();
          $updated = true;
        }
      }

      // Recurse into nested elements.
      if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
        if ( $this->walk_widgets( $element['elements'], $resolve_alt, $depth + 1 ) ) {
          $updated = true;
        }
      }
    }
    unset( $element );

    return $updated;
  }

  /**
   * Check whether the current WordPress version supports HTML tag processing.
   *
   * @since 1.10.23
   * @return bool True if WP_HTML_Tag_Processor is available.
   */
  protected function can_process_html_tags() {
    return class_exists( 'WP_HTML_Tag_Processor' );
  }

  /**
   * Apply a resolved alt text value to an image entry array.
   *
   * @since 1.10.23
   * @param array    $img         Image entry array (by reference).
   * @param callable $resolve_alt Function to resolve alt text.
   * @return bool True if the entry was updated.
   */
  private function apply_alt_to_image_entry( array &$img, callable $resolve_alt ) {
    $att_id = ! empty( $img['id'] ) ? (int) $img['id'] : null;
    $url    = $img['url'] ?? '';
    $alt    = $resolve_alt( $att_id, $url );
    if ( $alt !== null && ( ! isset( $img['alt'] ) || $img['alt'] !== $alt ) ) {
      $img['alt'] = $alt;
      return true;
    }
    return false;
  }

  /**
   * Load and decode Elementor data for a post.
   *
   * @since 1.10.23
   * @param int $post_id The post ID.
   * @return array|null Decoded data, or null if missing/invalid.
   */
  private function load_data( $post_id ) {
    $raw = get_post_meta( $post_id, '_elementor_data', true );
    if ( empty( $raw ) || ! is_string( $raw ) ) {
      return null;
    }
    $data = json_decode( $raw, true );
    if ( ! is_array( $data ) ) {
      error_log( 'ATAI: Failed to decode _elementor_data for post ' . $post_id . ' (' . json_last_error_msg() . ')' ); // phpcs:ignore QITStandard.PHP.DebugCode.DebugFunctionFound -- Production error logging
      return null;
    }
    return $data;
  }

  /**
   * Encode and save Elementor data for a post, clearing the CSS cache.
   *
   * wp_slash() is required because update_post_meta() calls wp_unslash() internally.
   * Without it, escaped quotes in HTML (e.g. in text-editor widgets) get stripped,
   * corrupting Elementor's JSON.
   *
   * @since 1.10.23
   * @param int   $post_id The post ID.
   * @param array $data    The Elementor data array.
   */
  private function save_data( $post_id, array $data ) {
    $encoded = wp_json_encode( $data, JSON_UNESCAPED_SLASHES );
    if ( $encoded === false ) {
      error_log( 'ATAI: wp_json_encode failed for Elementor data on post ' . $post_id ); // phpcs:ignore QITStandard.PHP.DebugCode.DebugFunctionFound -- Production error logging
      return;
    }
    $result = update_post_meta( $post_id, '_elementor_data', wp_slash( $encoded ) );
    if ( $result !== false ) {
      delete_post_meta( $post_id, '_elementor_css' );
    }
  }

}
