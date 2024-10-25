<?php
/**
 * The file that handles the post-related functionality of the plugin.
 *
 *
 * @link       https://alttext.ai
 * @since      1.0.46
 *
 * @package    ATAI
 * @subpackage ATAI/includes
 */

/**
 * The post handling class.
 *
 * This is used to handle operations related to post pages.
 *
 *
 * @since      1.0.46
 * @package    ATAI
 * @subpackage ATAI/includes
 * @author     AltText.ai <info@alttext.ai>
 */
class ATAI_Post {
  /**
   * Handle WP post deletion.
   *
   * This method will remove plugin-specific post data from the database, etc.
   *
   * @since 1.1.0
   * @access public
   *
   * @return void
   */
  public function on_post_deleted($post_id) {
    ATAI_Utility::remove_atai_asset($post_id);
  }

  /**
   * Adds a meta box for bulk generation of ALT text.
   *
   * This method adds a meta box to the sidebar of each posts and pages' edit screen.
   * The meta box is intended for triggering the bulk generation of ALT text for images.
   *
   * @since 1.0.46
   * @access public
   *
   * @uses add_meta_box() To add the meta box to each post type.
   *
   * @return void
   */
  public function add_bulk_generate_meta_box() {
    add_meta_box(
      'atai-generate-meta-box',
      __( 'AltText.ai', 'alttext-ai' ),
      [ $this, 'bulk_generate_meta_box_callback' ],
      'post',
      'side'
    );

    add_meta_box(
      'atai-generate-meta-box',
      __( 'AltText.ai', 'alttext-ai' ),
      [ $this, 'bulk_generate_meta_box_callback' ],
      'page',
      'side'
    );

    if ( ATAI_Utility::has_woocommerce() ) {
      add_meta_box(
        'atai-generate-meta-box',
        __( 'AltText.ai', 'alttext-ai' ),
        [ $this, 'bulk_generate_meta_box_callback' ],
        'product',
        'side'
      );
    }
  }

  /**
   * Callback function for rendering the content of the bulk generate ALT text meta box.
   *
   * This method outputs the HTML for a meta box that allows users to bulk generate ALT text
   * for all attachments associated with a particular post. The meta box includes a button
   * that triggers the bulk generation process via JavaScript.
   *
   * @since 1.0.46
   * @access public
   *
   * @param WP_Post $post The post object for which the meta box is being displayed.
   *
   * @return void
   */
  public function bulk_generate_meta_box_callback( $post ) {
    $button_href = '#atai-bulk-generate';

    if ( ! ATAI_Utility::get_api_key() ) {
      $button_href = admin_url( 'admin.php?page=atai&api_key_missing=1' );
    }
  ?>
    <p>
      <?php if ( $post->post_type === 'product' ) : ?>
        <?php esc_html_e('Populate alt text using values from your media library images. If missing, alt text will be generated for the product images and product description.', 'alttext-ai' ); ?>
      <?php else : ?>
        <?php esc_html_e('Populate alt text using values from your media library images. If missing, alt text will be generated for each image and added to the post.', 'alttext-ai' ); ?>
      <?php endif; ?>
    </p>

    <div>
      <input
        type="checkbox"
        id="atai-generate-button-overwrite-checkbox"
        data-post-bulk-generate-overwrite
      >
      <label for="atai-generate-button-overwrite-checkbox"><?php esc_html_e( 'Overwrite existing alt text', 'alttext-ai' ); ?></label>
    </div>

    <div>
      <input
        type="checkbox"
        id="atai-generate-button-process-external-checkbox"
        data-post-bulk-generate-process-external
      >
      <label for="atai-generate-button-process-external-checkbox"><?php esc_html_e( 'Include images not in library', 'alttext-ai' ); ?></label>
    </div>

    <div class="mt-1">
      <input
        type="checkbox"
        id="atai-generate-button-keywords-checkbox"
        data-post-bulk-generate-keywords-checkbox
      >
      <label for="atai-generate-button-keywords-checkbox"><?php esc_html_e( 'Add SEO keywords', 'alttext-ai' ); ?></label>

      <input
        type="text"
        class="hidden mt-1 w-full placeholder:text-gray-400"
        data-post-bulk-generate-keywords
        placeholder="keyword1, keyword2"
        maxlength="512"
      >
    </div>

    <div id="atai-post-generate-button">
      <a
        href="<?php echo esc_url($button_href); ?>"
        class="button-secondary button-large"
        title="<?php esc_html_e( 'Refreshing may take a while if many images are missing alt text. Please be patient during the refresh process.', 'alttext-ai' ); ?>"
        data-post-bulk-generate
      >
          <img
            src="<?php echo esc_url(plugin_dir_url( ATAI_PLUGIN_FILE ) . 'admin/img/icon-button-generate.png'); ?>"
            alt="<?php esc_html_e( 'Refresh alt text with AltText.ai', 'alttext-ai' ); ?>">
          <span>Refresh Alt Text</span>
      </a>
      <span class="atai-update-notice"></span>
    </div>
  <?php
  }

  /**
   * Updates alt text for product images
   *
   * @since 1.8.5
   * @access public
   *
   * @return void
   */
  public function update_product_images( $post_id, $overwrite, $keywords, $is_ajax ) {
    $metadata = get_post_meta( $post_id );
    if ( empty($metadata) ) {
      return false;
    }

    // Create list of image IDs to process (featured image + gallery images):
    $image_attachment_ids = [];
    if ( array_key_exists('_thumbnail_id', $metadata) && is_array($metadata['_thumbnail_id']) ) {
      array_push( $image_attachment_ids, $metadata['_thumbnail_id'][0] );
    }

    if ( array_key_exists('_product_image_gallery', $metadata) && is_array($metadata['_product_image_gallery']) ) {
      $gallery_images = $metadata['_product_image_gallery'][0];
      $image_attachment_ids = array_merge( $image_attachment_ids, explode(",", $gallery_images) );
    }

    // Process each image attachment:
    // Check if there are any images
    if ( empty($image_attachment_ids) ) {
      if ( $is_ajax ) {
        // Set a transient to show a success notice after page reload
        set_transient( 'atai_enrich_post_content_success', __( '[AltText.ai] No images were found to update.', 'alttext-ai' ), 60 );
        wp_send_json_success();
      }

      return true;
    }

    $total_images_found = count($image_attachment_ids);
    $num_alttext_generated = 0;
    $no_credits = false;
    $atai_attachment = new ATAI_Attachment();

    foreach ( $image_attachment_ids as $attachment_id ) {
      $alt_text = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
      $should_generate = false;

      // If the alt text is empty or need to overwrite, generate it:
      if ( $overwrite || empty( $alt_text ) ) {
        $should_generate = true;
        $ecomm_data = $atai_attachment->get_ecomm_data( $attachment_id, $post_id );
        $alt_text = $atai_attachment->generate_alt( $attachment_id, null, array( 'keywords' => $keywords, 'ecomm' => $ecomm_data ) );
      }

      // Check if generate_alt returned false or an error
      if ( empty($alt_text) || ! is_string( $alt_text ) ) {
        continue;
      }
      else if ( $alt_text === 'insufficient_credits' ) {
        $no_credits = true;
        break;
      }
      else if ( $should_generate ) {
        $num_alttext_generated = $num_alttext_generated + 1;
      }
    }

    if ( $is_ajax ) {
      // Set a transient to show a success notice after page reload
      if ( $no_credits ) {
        $success_msg = sprintf( __('[AltText.ai] You have no more credits available. Go to your account on AltText.ai to get more credits.', 'alttext-ai') );
      }
      else {
        $success_msg = sprintf( __('[AltText.ai] Refreshed alt text for %d images (%d generated).', 'alttext-ai'), $total_images_found, $num_alttext_generated );
      }

      set_transient( 'atai_enrich_post_content_success', $success_msg, 60 );

      // Return success
      wp_send_json_success();
    }

    return array(
      'status' => 'success',
      'total_images_found' => $total_images_found,
      'num_alttext_generated' => $num_alttext_generated
    );
  }

  /**
   * Enriches the post content by updating the alt text of images.
   *
   * This function fetches the post content based on the provided Post ID in params or AJAX,
   * scans for embedded images, and updates their alt text based on the
   * attachment metadata. The updated content is then saved back to the post.
   *
   * @since 1.0.46
   * @access public
   *
   * @return void
   */
  public function enrich_post_content( $post_id = null, $overwrite = false, $process_external = false, $keywords = [] ) {
    $is_ajax = defined( 'DOING_AJAX' ) && DOING_AJAX;

    // Check if this is an AJAX call
    if ( $is_ajax ) {
      check_ajax_referer( 'atai_enrich_post_content', 'security' );
      $post_id = intval( $_POST['post_id'] ?? 0 );
      $overwrite = filter_var($_REQUEST['overwrite'], FILTER_VALIDATE_BOOLEAN);
      $process_external = filter_var($_REQUEST['process_external'], FILTER_VALIDATE_BOOLEAN);
      $keywords = ( isset( $_REQUEST['keywords'] ) && is_array( $_REQUEST['keywords'] ) ) ? array_map( 'sanitize_text_field', $_REQUEST['keywords'] ) : [];
    }

    $post = get_post( $post_id );

    // Check if post exists
    if ( $post === null ) {
      if ( $is_ajax ) {
        wp_send_json_error( array(
          'status' => 'error',
          'message' => __( 'Post not found.', 'alttext-ai' )
        ) );
      }

      return false;
    } elseif ( $post->post_type === 'product' && ATAI_Utility::has_woocommerce() ) {
      return $this->update_product_images( $post_id, $overwrite, $keywords, $is_ajax );
    }

    $content = $post->post_content;

    // Check if content is empty
    if ( empty( $content ) ) {
      if ( $is_ajax ) {
        // Set a transient to show a success notice after page reload
        set_transient( 'atai_enrich_post_content_success', __( '[AltText.ai] Content is empty, no update needed.', 'alttext-ai' ), 60 );
        wp_send_json_success();
      }

      return true;
    }

    // Check if there are any images
    if ( strpos($content, '<img') === false ) {
      if ( $is_ajax ) {
        // Set a transient to show a success notice after page reload
        set_transient( 'atai_enrich_post_content_success', __( '[AltText.ai] No images were found to update.', 'alttext-ai' ), 60 );
        wp_send_json_success();
      }

      return true;
    }

    $atai_attachment = new ATAI_Attachment();
    $total_images_found = 0;
    $num_alttext_generated = 0;
    $no_credits = false;
    $updated_content = '';

    if ( version_compare( get_bloginfo( 'version' ), '6.2') >= 0 ) {
      $tags = new WP_HTML_Tag_Processor( $content );

      while ( $tags->next_tag( 'img' ) ) {
        $img_url = $img_url_original = $tags->get_attribute( 'src' );

        $should_generate = false;
        $total_images_found = $total_images_found + 1;

        // If relative path, convert to full URL:
        if ( isset($img_url) && substr($img_url, 0, 1) == "/" ) {
          $img_url = $img_url_original = home_url() . $img_url;
        }

        // Remove the dimensions from the URL to get the URL of the original image,
        // only if the image is hosted on the same site
        if ( strpos( $img_url, home_url() ) === 0 ) {
          $img_url_original = preg_replace( '/-\d+x\d+(?=\.[a-zA-Z]{3,4}$)/', '', $img_url );
        }

        // Prepend protocol if missing:
        if ( substr($img_url_original, 0, 2) == "//" ) {
          $img_url_original = "https:" . $img_url_original;
        }

        // Get the attachment ID from the image URL
        $attachment_id = ATAI_Utility::lookup_attachment_id($img_url_original, $post_id);
        $attachment_id = $attachment_id ?? ATAI_Utility::lookup_attachment_id($img_url_original);
        $alt_text = false;

        if ( $attachment_id ) {
          $alt_text = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

          // If the alt text is empty, generate it
          if ( $overwrite || empty( $alt_text ) ) {
            $should_generate = true;
            $alt_text = $atai_attachment->generate_alt( $attachment_id, null, array( 'keywords' => $keywords ) );
          }
        } elseif ( $process_external ) {
          // Extract alt text from the image tag
          $alt_text = trim( $tags->get_attribute( 'alt' ) ) ?? '';

          if ( $overwrite || empty( $alt_text ) ) {
            $should_generate = true;
            $alt_text = $atai_attachment->generate_alt( null, $img_url_original, array( 'keywords' => $keywords ) );
          }
        }

        // Check if generate_alt returned false or an error
        if ( empty($alt_text) || ! is_string( $alt_text ) ) {
          continue;
        }
        else if ( $alt_text === 'insufficient_credits' ) {
          $no_credits = true;
          break;
        }
        else if ( $should_generate ) {
          $num_alttext_generated = $num_alttext_generated + 1;
        }

        $tags->set_attribute( 'alt', $alt_text );
      }

      $updated_content = $tags->get_updated_html();
    } else {
      $updated_content = preg_replace_callback(
        '/<img .*?(src="([^"]*?)")[^>]*?>/i',
        function( $matches ) use ( $atai_attachment, $overwrite, $process_external, $keywords, &$total_images_found, &$num_alttext_generated, &$no_credits ) {
          $img_tag = $matches[0];
          $img_url = $img_url_original = $matches[2]; // The src URL is captured in the second group.

          $should_generate = false;
          $total_images_found = $total_images_found + 1;

          // If relative path, convert to full URL:
          if ( isset($img_url) && substr($img_url, 0, 1) == "/" ) {
            $img_url = $img_url_original = home_url() . $img_url;
          }

          // Remove the dimensions from the URL to get the URL of the original image,
          // only if the image is hosted on the same site
          if ( strpos( $img_url, home_url() ) === 0 ) {
            $img_url_original = preg_replace( '/-\d+x\d+(?=\.[a-zA-Z]{3,4}$)/', '', $img_url );
          }

          // Prepend protocol if missing:
          if ( substr($img_url_original, 0, 2) == "//" ) {
            $img_url_original = "https:" . $img_url_original;
          }

          // Get the attachment ID from the image URL
          $attachment_id = ATAI_Utility::lookup_attachment_id($img_url_original, $post_id);
          $attachment_id = $attachment_id ?? ATAI_Utility::lookup_attachment_id($img_url_original);
          $alt_text = false;

          if ( $attachment_id ) {
            $alt_text = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

            // If the alt text is empty, generate it
            if ( $overwrite || empty( $alt_text ) ) {
              $should_generate = true;
              $alt_text = $atai_attachment->generate_alt( $attachment_id, null, array( 'keywords' => $keywords ) );
            }
          } elseif ( $process_external ) {
            // Extract alt text from the image tag
            preg_match('/alt="([^"]*)"/i', $img_tag, $matches);
            $alt_text = trim( $matches[1] ) ?? '';

            if ( $overwrite || empty( $alt_text ) ) {
              $should_generate = true;
              $alt_text = $atai_attachment->generate_alt( null, $img_url_original, array( 'keywords' => $keywords ) );
            }
          }

          // Check if generate_alt returned false or an error
          if ( empty( $alt_text ) || ! is_string( $alt_text ) ) {
            return $img_tag;
          }
          else if ( $alt_text === 'insufficient_credits' ) {
            $no_credits = true;
            return $img_tag;
          }
          else if ( $should_generate ) {
            $num_alttext_generated = $num_alttext_generated + 1;
          }

          if ( false === strpos( $img_tag, ' alt=' ) ) {
            // If there's no alt attribute, add one
            return str_replace( '<img ', '<img alt="' . esc_attr( $alt_text ) . '" ', $img_tag );
          } else {
            // If there's an existing alt attribute, update it
            return preg_replace( '/alt="[^"]*"/i', 'alt="' . esc_attr( $alt_text ) . '"', $img_tag );
          }

          return $img_tag;
        },
        $content
      );
    }

    if ( !empty($updated_content) ) {
      wp_update_post( array(
        'ID' => $post_id,
        'post_content' => str_replace('\\', '\\\\', $updated_content),
        )
      );
    }

    if ( $is_ajax ) {
      // Set a transient to show a success notice after page reload
      if ( $no_credits ) {
        $success_msg = sprintf( __('[AltText.ai] You have no more credits available. Go to your account on AltText.ai to get more credits.', 'alttext-ai') );
      }
      else {
        $success_msg = sprintf( __('[AltText.ai] Refreshed alt text for %d images (%d generated).', 'alttext-ai'), $total_images_found, $num_alttext_generated );
      }

      set_transient( 'atai_enrich_post_content_success', $success_msg, 60 );

      // Return success
      wp_send_json_success();
    }

    return array(
      'status' => 'success',
      'total_images_found' => $total_images_found,
      'num_alttext_generated' => $num_alttext_generated
    );
  }

  /**
   * Display a success notice to the user after successfully enriching post content.
   *
   * If the "atai_enrich_post_content_success" transient is set, display a success notice to the user
   * indicating that the ALT text has been updated successfully. The transient is then deleted to ensure
   * the message is only shown once.
   *
   * @since 1.0.46
   * @access public
   *
   * @return void
   */
  public function display_enrich_post_content_success_notice() {
    // Check if this is an AJAX call
    if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
      check_ajax_referer( 'atai_enrich_post_content_transient', 'security' );

      $message = get_transient( 'atai_enrich_post_content_success' );

      // If transient is set, return the message as JSON response
      if ( $message ) {
        delete_transient( 'atai_enrich_post_content_success' );
        wp_send_json_success( [ 'message' => $message ] );
      } else {
        wp_send_json_error( [ 'message' => 'No message found' ] );
      }
    } else if ( ! get_current_screen()->is_block_editor() ) {
      $message = get_transient( 'atai_enrich_post_content_success' );

      // Bail early if notice transient is not set
      if ( ! $message ) {
        return;
      }

      echo '<div class="notice notice--atai notice-success is-dismissible"><p>', esc_html( $message ), '</p></div>';

      // Delete the transient
      delete_transient( 'atai_enrich_post_content_success' );
    }
  }

  /**
   * Add Refresh Alt Text option to bulk actions
   *
   * @since 1.0.48
   * @access public
   *
   * @param Array $actions Array of bulk actions.
   */
  public function add_bulk_select_action( $actions ) {
    $actions[ 'alttext_options' ] = __( '&#8595; AltText.ai', 'alttext-ai' );
    $actions[ 'alttext_generate_alt' ] = __( 'Refresh Alt Text', 'alttext-ai' );
    return $actions;
  }

  /**
   * Process bulk select action
   *
   * @since 1.0.48
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

    // remove the query arg from URL because we do not need them any more
    $redirect = remove_query_arg(
      array( 'alttext_generate_alt' ),
      $redirect
    );

    $total_images_found = 0;
    $num_alttext_generated = 0;
    $overwrite = get_option( 'atai_bulk_refresh_overwrite' ) === 'yes';
    $include_external = get_option( 'atai_bulk_refresh_external' ) === 'yes';

    foreach ( $items as $post_id ) {
      $response = $this->enrich_post_content( $post_id, $overwrite, $include_external );

      if ( is_array( $response ) ) {
        $total_images_found += $response['total_images_found'] ?? 0;
        $num_alttext_generated += $response['num_alttext_generated'] ?? 0;
      }
    }

    // Set a transient to show a success notice after page reload
    $success_msg = sprintf(
      __('[AltText.ai] Refreshed alt text for %d images (%d generated).', 'alttext-ai'),
      $total_images_found,
      $num_alttext_generated
    );
    set_transient( 'atai_enrich_post_content_success', $success_msg, 60 );

    return $redirect;
  }

  /**
   * Register bulk action for post types
   *
   * @since 1.6.2
   * @access public
   */
  public function register_bulk_action() {
    $post_types = array( 'post', 'page' ); // Default to Post and Page types

    if ( ATAI_Utility::has_woocommerce() ) {
      array_push($post_types, 'product'); // Add WooCommerce products if available
    }

    $post_types = apply_filters( 'atai_bulk_action_post_types', $post_types ); // Allow user-defined custom types

    foreach ($post_types as $post_type) {
      add_filter( "bulk_actions-edit-{$post_type}", [$this, 'add_bulk_select_action'], 100, 1 );
      add_filter( "handle_bulk_actions-edit-{$post_type}", [$this, 'bulk_select_action_handler'], 100, 3 );
    }
  }
}
