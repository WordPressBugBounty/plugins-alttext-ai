<?php
/**
 * Divi 5 page builder handler.
 *
 * Divi 5 replaced Divi 4's shortcodes with WordPress block markup stored in
 * post_content (`<!-- wp:divi/image {ATTRS} /-->`, `<!-- wp:divi/gallery ... -->`,
 * etc.). Because the images are referenced inside block JSON rather than as plain
 * <img> tags, the post-content HTML scan in class-atai-post.php never sees them, and
 * Divi-inserted images are frequently left unattached (post_parent = 0) so the focus
 * keyphrase can't be resolved from the attachment. This handler discovers Divi 5
 * image and gallery modules so process_builder_images() can generate alt text for
 * them (with explicit_post_id, so the post's focus keyphrase resolves) and write the
 * result back where Divi reads it.
 *
 * Two module shapes are handled:
 *
 *   - divi/image   — a single image. The URL and alt live in the block attributes
 *                    under image.innerContent.{breakpoint}.value.{src,alt} (the image
 *                    module's image component, typed ImageLink.Attributes). Divi renders
 *                    the image module's own alt attribute, so the alt must be written
 *                    back into the block (save() rewrites post_content).
 *   - divi/gallery — attachment IDs at image.advanced.galleryIds.{breakpoint}.value
 *                    (comma-separated string). Galleries render alt from each
 *                    attachment's _wp_attachment_image_alt, which generate_alt()
 *                    already sets, so gallery items need NO block rewrite
 *                    (update_image_alt is a no-op for them).
 *
 * NOTE: attribute shapes are confirmed against Divi's published TypeScript types
 * (@divi/types v1.0.5): ImageAttrs.image = ImageLink.Attributes (innerContent →
 * breakpoint → value → {src, alt}); GalleryAttrs.image.advanced.galleryIds =
 * FormatBreakpointStateAttr<string>. To stay resilient to the in-progress Divi 5
 * format, locate_image_attr() scans top-level components for the image shape and
 * find_gallery_ids() probes the authoritative path plus fallbacks — a format shift
 * degrades to "no image found" rather than a fatal error. A real Divi 5 install
 * round-trip is still recommended before release.
 *
 * @since      1.10.36
 * @package    ATAI
 * @subpackage ATAI/includes/builders
 */
class ATAI_Builder_Divi {

	/**
	 * Maximum recursion depth for walking the block tree.
	 *
	 * @var int
	 */
	private const MAX_DEPTH = 30;

	/**
	 * Breakpoints to probe when reading/writing a module attribute, in priority
	 * order. desktop is canonical; tablet/phone are fallbacks for the rare case
	 * where a value is only set on a smaller breakpoint.
	 *
	 * @var array
	 */
	private const BREAKPOINTS = array( 'desktop', 'tablet', 'phone' );

	/**
	 * Parsed block tree cached per post_id (from get_blocks()).
	 *
	 * @var array
	 */
	private $blocks = array();

	/**
	 * Pending divi/image alt updates per post_id, recorded by update_image_alt().
	 *
	 * save() replays these onto a block tree re-parsed fresh from the DB at save
	 * time (not $this->blocks) so concurrent builder edits during a long refresh
	 * survive — mirrors the YOOtheme handler's merge strategy.
	 *
	 * @var array
	 */
	private $pending_updates = array();

	/**
	 * Check if Divi (theme or builder plugin) is active.
	 *
	 * Coarse gate only — has_builder_content() is the authoritative per-post check.
	 *
	 * @since  1.10.36
	 * @return bool
	 */
	public static function is_active() {
		$theme = wp_get_theme();
		$template = strtolower( (string) $theme->get_template() );
		$name     = strtolower( (string) $theme->get( 'Name' ) );
		if ( strpos( $template, 'divi' ) !== false || strpos( $name, 'divi' ) !== false ) {
			return true;
		}

		// Divi shipped as the standalone "Divi Builder" plugin.
		return function_exists( 'et_setup_theme' )
			|| defined( 'ET_BUILDER_PLUGIN_VERSION' )
			|| defined( 'ET_CORE_VERSION' );
	}

	/**
	 * Check if the post contains Divi 5 builder blocks.
	 *
	 * @since  1.10.36
	 * @param  int $post_id
	 * @return bool
	 */
	public function has_builder_content( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || empty( $post->post_content ) ) {
			return false;
		}

		// Cheap substring gate before the full parse. Divi 5 blocks are all
		// namespaced wp:divi/*. Divi 4 shortcode posts ([et_pb_*]) have no such
		// marker and fall through to the existing shortcode/HTML scan untouched.
		return strpos( $post->post_content, '<!-- wp:divi/' ) !== false;
	}

	/**
	 * Divi 5 image-module alt lives in post_content; gallery alt lives on the
	 * attachment. We rewrite post_content for image modules, so report true.
	 *
	 * @since  1.10.36
	 * @return bool
	 */
	public function uses_post_content() {
		return true;
	}

	/**
	 * Extract all image and gallery images from the Divi 5 block tree.
	 *
	 * @since  1.10.36
	 * @param  int $post_id
	 * @return array
	 */
	public function extract_images( $post_id ) {
		$blocks = $this->get_blocks( $post_id );
		if ( $blocks === null ) {
			return array();
		}

		$images = array();
		$this->walk_blocks( $blocks, array(), $images, $post_id );
		return $images;
	}

	/**
	 * Record an alt-text update for an image module. Gallery items are a no-op
	 * here — their alt is stored on the attachment (set by generate_alt) and Divi
	 * renders galleries from attachment data, so no block rewrite is needed.
	 *
	 * @since  1.10.36
	 * @param  int    $post_id
	 * @param  mixed  $ref
	 * @param  string $alt_text
	 * @return bool   True if a block rewrite was recorded; false otherwise.
	 */
	public function update_image_alt( $post_id, $ref, $alt_text ) {
		if ( ! is_array( $ref ) || ( $ref['type'] ?? '' ) !== 'image' ) {
			return false;
		}

		if ( ! isset( $ref['path'] ) || ! is_array( $ref['path'] ) ) {
			return false;
		}

		if ( ! isset( $this->blocks[ $post_id ] ) ) {
			return false;
		}

		$sanitized = sanitize_text_field( $alt_text );

		// Apply to the in-memory tree too, so repeated extract_images() calls in
		// the same process observe the update.
		$this->set_module_alt( $this->blocks[ $post_id ], $ref['path'], $sanitized, $ref['expected_src'] ?? null );

		$this->pending_updates[ $post_id ][] = array(
			'path'         => $ref['path'],
			'alt'          => $sanitized,
			'expected_src' => $ref['expected_src'] ?? null,
		);

		return true;
	}

	/**
	 * Persist image-module alt changes back to post_content.
	 *
	 * Re-parses post_content fresh from the DB and replays pending updates so
	 * concurrent builder edits during the refresh survive. Handler state for
	 * $post_id is always cleared (try/finally) so a reused instance can't replay
	 * stale updates onto a later save.
	 *
	 * @since  1.10.36
	 * @param  int $post_id
	 * @return bool
	 */
	public function save( $post_id ) {
		try {
			return $this->save_internal( $post_id );
		} finally {
			unset( $this->blocks[ $post_id ], $this->pending_updates[ $post_id ] );
		}
	}

	// --- Private helpers ---

	/**
	 * Internal save implementation — separated so save() can always clean up.
	 *
	 * @param  int $post_id
	 * @return bool
	 */
	private function save_internal( $post_id ) {
		$updates = $this->pending_updates[ $post_id ] ?? array();
		if ( empty( $updates ) ) {
			// Gallery-only refresh: alt was written to attachments, nothing to
			// rewrite in post_content. Not a failure.
			return true;
		}

		// Read post_content directly from the DB (bypassing the object cache) so
		// the merge target is the CURRENT stored state, not the load-time copy.
		global $wpdb;
		$current_content = $wpdb->get_var( $wpdb->prepare(
			"SELECT post_content FROM {$wpdb->posts} WHERE ID = %d",
			$post_id
		) );

		if ( $current_content === null || $current_content === '' ) {
			ATAI_Utility::log_error( 'Divi: Post content missing or empty when saving alt text on post ' . $post_id . '. The post may have been deleted during refresh.' );
			return false;
		}

		$blocks = parse_blocks( $current_content );
		if ( ! is_array( $blocks ) ) {
			ATAI_Utility::log_error( 'Divi: Failed to parse blocks when saving alt text on post ' . $post_id . '.' );
			return false;
		}

		$applied          = 0;
		$skipped_identity = 0;
		$skipped_missing  = 0;
		foreach ( $updates as $update ) {
			$outcome = $this->set_module_alt( $blocks, $update['path'], $update['alt'], $update['expected_src'] );
			if ( $outcome === 'applied' ) {
				$applied++;
			} elseif ( $outcome === 'identity_mismatch' ) {
				$skipped_identity++;
			} else {
				$skipped_missing++;
			}
		}

		if ( $applied === 0 ) {
			ATAI_Utility::log_error( sprintf(
				'Divi: No alt text updates could be applied to post %d (attempted=%d, skipped because images were moved or swapped=%d, skipped because image blocks were deleted=%d). This usually means the page was edited in the Divi builder while alt text was generating.',
				$post_id,
				count( $updates ),
				$skipped_identity,
				$skipped_missing
			) );
			return false;
		}

		$new_content = serialize_blocks( $blocks );
		if ( ! is_string( $new_content ) || $new_content === '' || $new_content === $current_content ) {
			ATAI_Utility::log_error( 'Divi: Could not re-serialize blocks when saving alt text on post ' . $post_id . '. No changes were written.' );
			return false;
		}

		// We bypassed the object cache with a direct $wpdb read, so any cached
		// post object is stale relative to $current_content. Clear it before
		// wp_update_post to avoid a spurious revision that drifts post_modified.
		clean_post_cache( $post_id );

		$result = wp_update_post( array(
			'ID'           => $post_id,
			'post_content' => wp_slash( $new_content ),
		), true );

		if ( is_wp_error( $result ) ) {
			ATAI_Utility::log_error( 'Divi: wp_update_post failed for post ' . $post_id . ': ' . $result->get_error_message() );
			return false;
		}

		return true;
	}

	/**
	 * Parse and cache the Divi 5 block tree for a post.
	 *
	 * @param  int $post_id
	 * @return array|null
	 */
	private function get_blocks( $post_id ) {
		if ( isset( $this->blocks[ $post_id ] ) ) {
			return $this->blocks[ $post_id ];
		}

		$post = get_post( $post_id );
		if ( ! $post || empty( $post->post_content ) ) {
			return null;
		}

		if ( strpos( $post->post_content, '<!-- wp:divi/' ) === false ) {
			return null;
		}

		$blocks = parse_blocks( $post->post_content );
		if ( ! is_array( $blocks ) ) {
			return null;
		}

		$this->blocks[ $post_id ] = $blocks;
		return $this->blocks[ $post_id ];
	}

	/**
	 * Recursively walk the block tree, collecting image and gallery references.
	 *
	 * @param  array $blocks   Sibling blocks at this level
	 * @param  array $path     Indices leading here: [topIndex, innerIndex, ...]
	 * @param  array &$images  Collected references
	 * @param  int   $post_id
	 * @param  int   $depth
	 */
	private function walk_blocks( $blocks, $path, &$images, $post_id, $depth = 0 ) {
		if ( $depth >= self::MAX_DEPTH || ! is_array( $blocks ) ) {
			return;
		}

		foreach ( $blocks as $index => $block ) {
			if ( ! is_array( $block ) ) {
				continue;
			}

			$name     = $block['blockName'] ?? '';
			$cur_path = array_merge( $path, array( $index ) );

			if ( $name === 'divi/image' ) {
				$this->collect_image_module( $block, $cur_path, $images, $post_id );
			} elseif ( $name === 'divi/gallery' ) {
				$this->collect_gallery_module( $block, $images );
			}

			if ( ! empty( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$this->walk_blocks( $block['innerBlocks'], $cur_path, $images, $post_id, $depth + 1 );
			}
		}
	}

	/**
	 * Collect a single divi/image module.
	 *
	 * @param  array $block
	 * @param  array $path
	 * @param  array &$images
	 * @param  int   $post_id
	 */
	private function collect_image_module( $block, $path, &$images, $post_id ) {
		$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
		$loc   = $this->locate_image_attr( $attrs );
		if ( $loc === null ) {
			return;
		}

		$raw_url = $this->read_image_field( $attrs, $loc, 'src' );
		if ( ! is_string( $raw_url ) || $raw_url === '' || strpos( $raw_url, 'data:' ) === 0 ) {
			return;
		}

		$normalized_url = ATAI_Utility::normalize_image_url( $raw_url, home_url() );
		if ( ! $normalized_url ) {
			return;
		}

		$current_alt   = $this->read_image_field( $attrs, $loc, 'alt' );
		$attachment_id = ATAI_Utility::lookup_attachment_id( $normalized_url, $post_id );
		if ( ! $attachment_id ) {
			$attachment_id = ATAI_Utility::lookup_attachment_id( $normalized_url );
		}

		$images[] = array(
			'url'           => $normalized_url,
			'attachment_id' => $attachment_id ?: null,
			'current_alt'   => is_string( $current_alt ) ? $current_alt : '',
			'ref'           => array(
				'type'         => 'image',
				'path'         => $path,
				'expected_src' => $normalized_url,
			),
		);
	}

	/**
	 * Collect images referenced by a divi/gallery module.
	 *
	 * Galleries store attachment IDs (attrs.gallery_ids, comma-separated) and
	 * render alt from each attachment's _wp_attachment_image_alt, so these emit
	 * attachment-only refs with no block path — generate_alt() writes the alt to
	 * the attachment and update_image_alt() is a no-op for them.
	 *
	 * @param  array $block
	 * @param  array &$images
	 */
	private function collect_gallery_module( $block, &$images ) {
		$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
		$ids   = $this->read_gallery_ids( $attrs );

		foreach ( $ids as $attachment_id ) {
			$url = wp_get_attachment_url( $attachment_id );

			$images[] = array(
				'url'           => $url ?: '',
				'attachment_id' => $attachment_id,
				'current_alt'   => (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
				'ref'           => array( 'type' => 'gallery' ),
			);
		}
	}

	/**
	 * Locate the image component inside a Divi 5 module's attributes.
	 *
	 * Divi 5 stores a module's image under a component key at
	 * {component}.innerContent.{breakpoint}.value.{src,alt}. Confirmed against
	 * Divi's own module source — the blurb module's image lives at
	 * imageIcon.innerContent.desktop.value.src (placeholder-content.ts) and its
	 * Divi 4->5 conversion-outline maps src/alt to imageIcon.innerContent.*.{src,alt}.
	 * The component key differs per module (blurb: "imageIcon"; the standalone image
	 * module's key is not in the public examples), so we scan top-level components
	 * rather than hard-coding it. The .value "state" wrapper is also probed with a
	 * flat fallback, since the conversion-outline notation omits it.
	 *
	 * @param  array $attrs
	 * @return array|null ['component'=>string,'breakpoint'=>string,'state'=>'value'|null]
	 */
	private function locate_image_attr( $attrs ) {
		if ( ! is_array( $attrs ) ) {
			return null;
		}

		foreach ( $attrs as $component => $cfg ) {
			if ( ! is_array( $cfg ) || ! isset( $cfg['innerContent'] ) || ! is_array( $cfg['innerContent'] ) ) {
				continue;
			}

			foreach ( self::BREAKPOINTS as $bp ) {
				$node = $cfg['innerContent'][ $bp ] ?? null;
				if ( ! is_array( $node ) ) {
					continue;
				}

				// Shape A: {component}.innerContent.{bp}.value.src (runtime default).
				$value_src = $node['value']['src'] ?? null;
				if ( is_string( $value_src ) && $value_src !== '' ) {
					return array( 'component' => $component, 'breakpoint' => $bp, 'state' => 'value' );
				}

				// Shape B: {component}.innerContent.{bp}.src (state omitted).
				$flat_src = $node['src'] ?? null;
				if ( is_string( $flat_src ) && $flat_src !== '' ) {
					return array( 'component' => $component, 'breakpoint' => $bp, 'state' => null );
				}
			}
		}

		return null;
	}

	/**
	 * Read a string field (src/alt) from the located image component.
	 *
	 * @param  array  $attrs
	 * @param  array  $loc   Output of locate_image_attr()
	 * @param  string $field 'src' or 'alt'
	 * @return string|null
	 */
	private function read_image_field( $attrs, $loc, $field ) {
		$node = $attrs[ $loc['component'] ]['innerContent'][ $loc['breakpoint'] ] ?? null;
		if ( ! is_array( $node ) ) {
			return null;
		}

		$value = ( $loc['state'] === 'value' ) ? ( $node['value'][ $field ] ?? null ) : ( $node[ $field ] ?? null );

		return is_string( $value ) ? $value : null;
	}

	/**
	 * Parse a Divi 5 gallery module's attachment IDs into a de-duplicated int list.
	 *
	 * @param  array $attrs
	 * @return int[]
	 */
	private function read_gallery_ids( $attrs ) {
		$raw = $this->find_gallery_ids( $attrs );

		if ( is_array( $raw ) ) {
			$candidates = $raw;
		} elseif ( is_string( $raw ) && $raw !== '' ) {
			$candidates = explode( ',', $raw );
		} else {
			return array();
		}

		$ids = array();
		foreach ( $candidates as $candidate ) {
			$id = absint( trim( (string) $candidate ) );
			if ( $id > 0 ) {
				$ids[ $id ] = $id;
			}
		}

		return array_values( $ids );
	}

	/**
	 * Find a gallery module's raw gallery-IDs value (comma-separated string or
	 * array), or null.
	 *
	 * Authoritative location per Divi's published types (@divi/types GalleryAttrs):
	 * {component}.advanced.galleryIds.{breakpoint}.value — a FormatBreakpointStateAttr
	 * <string> under "advanced" (the gallery module uses component key "image").
	 * Top-level flat keys and an innerContent scan are kept as defensive fallbacks.
	 *
	 * @param  array $attrs
	 * @return string|array|null
	 */
	private function find_gallery_ids( $attrs ) {
		if ( ! is_array( $attrs ) ) {
			return null;
		}

		// 1. Authoritative: {component}.advanced.galleryIds.{bp}.value
		foreach ( $attrs as $cfg ) {
			if ( ! is_array( $cfg ) ) {
				continue;
			}
			$node = $cfg['advanced']['galleryIds'] ?? null;
			if ( is_array( $node ) ) {
				foreach ( self::BREAKPOINTS as $bp ) {
					$value = $node[ $bp ]['value'] ?? null;
					if ( ( is_string( $value ) && $value !== '' ) || ( is_array( $value ) && ! empty( $value ) ) ) {
						return $value;
					}
				}
			}
		}

		// 2. Top-level flat keys.
		foreach ( array( 'gallery_ids', 'galleryIds' ) as $k ) {
			if ( isset( $attrs[ $k ] ) && ( is_string( $attrs[ $k ] ) || is_array( $attrs[ $k ] ) ) ) {
				return $attrs[ $k ];
			}
		}

		// 3. Defensive innerContent scan (older/variant shapes).
		$keys = array( 'galleryIds', 'gallery_ids', 'ids' );
		foreach ( $attrs as $cfg ) {
			if ( ! is_array( $cfg ) || ! isset( $cfg['innerContent'] ) || ! is_array( $cfg['innerContent'] ) ) {
				continue;
			}
			foreach ( self::BREAKPOINTS as $bp ) {
				$node = $cfg['innerContent'][ $bp ] ?? null;
				if ( ! is_array( $node ) ) {
					continue;
				}
				$value = ( isset( $node['value'] ) && is_array( $node['value'] ) ) ? $node['value'] : $node;
				foreach ( $keys as $k ) {
					if ( isset( $value[ $k ] ) && ( is_string( $value[ $k ] ) || is_array( $value[ $k ] ) ) ) {
						return $value[ $k ];
					}
				}
			}
		}

		return null;
	}

	/**
	 * Set a divi/image module's alt text in a block tree, in place.
	 *
	 * When called for the in-memory tree (update_image_alt) the return value is
	 * ignored. When called from save_internal() it returns a string outcome:
	 *   - 'applied'           — alt written
	 *   - 'path_missing'      — block at path no longer exists (moved/deleted)
	 *   - 'identity_mismatch' — block exists but points at a different image
	 *
	 * @param  array  &$blocks
	 * @param  array  $path
	 * @param  string $alt
	 * @param  string|null $expected_src Normalized URL captured at extract time
	 * @return string
	 */
	private function set_module_alt( &$blocks, $path, $alt, $expected_src ) {
		$block = &$this->locate_block( $blocks, $path );
		if ( $block === null ) {
			return 'path_missing';
		}

		$attrs = is_array( $block['attrs'] ?? null ) ? $block['attrs'] : array();
		$loc   = $this->locate_image_attr( $attrs );
		if ( $loc === null ) {
			// The image attribute is gone (block emptied/replaced).
			return 'path_missing';
		}

		// Verify identity: a path can still resolve after a reorder but point at a
		// different image. Normalize both sides so CDN/host rewrites don't false-
		// negative.
		$raw_current = $this->read_image_field( $attrs, $loc, 'src' );
		$current_src = ( is_string( $raw_current ) && $raw_current !== '' )
			? ATAI_Utility::normalize_image_url( $raw_current, home_url() )
			: null;

		if ( $expected_src !== null && ( $current_src === null || $expected_src !== $current_src ) ) {
			return 'identity_mismatch';
		}

		// Write alt at the SAME component/breakpoint/state where src was found, so
		// it sits beside src exactly as Divi stores it (syncImageData: {src, alt}).
		if ( ! isset( $block['attrs'] ) || ! is_array( $block['attrs'] ) ) {
			$block['attrs'] = array();
		}
		$component = $loc['component'];
		$bp        = $loc['breakpoint'];

		if ( ! isset( $block['attrs'][ $component ]['innerContent'][ $bp ] ) || ! is_array( $block['attrs'][ $component ]['innerContent'][ $bp ] ) ) {
			// locate_image_attr found it, so this should exist; guard anyway.
			return 'path_missing';
		}

		if ( $loc['state'] === 'value' ) {
			if ( ! isset( $block['attrs'][ $component ]['innerContent'][ $bp ]['value'] ) || ! is_array( $block['attrs'][ $component ]['innerContent'][ $bp ]['value'] ) ) {
				$block['attrs'][ $component ]['innerContent'][ $bp ]['value'] = array();
			}
			$block['attrs'][ $component ]['innerContent'][ $bp ]['value']['alt'] = $alt;
		} else {
			$block['attrs'][ $component ]['innerContent'][ $bp ]['alt'] = $alt;
		}

		return 'applied';
	}

	/**
	 * Resolve a block path ([topIndex, innerIndex, ...]) to a reference into the
	 * tree. Returns a reference to a null sentinel if the path doesn't resolve.
	 *
	 * @param  array &$blocks
	 * @param  array $path
	 * @return array|null  Reference to the block array, or null.
	 */
	private function &locate_block( &$blocks, $path ) {
		$null = null;

		if ( empty( $path ) || ! isset( $blocks[ $path[0] ] ) ) {
			return $null;
		}

		$node = &$blocks[ $path[0] ];
		$count = count( $path );
		for ( $i = 1; $i < $count; $i++ ) {
			if ( ! isset( $node['innerBlocks'][ $path[ $i ] ] ) ) {
				return $null;
			}
			$node = &$node['innerBlocks'][ $path[ $i ] ];
		}

		return $node;
	}
}
