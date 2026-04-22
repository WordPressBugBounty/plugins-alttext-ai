<?php
/**
 * YOOtheme Pro page builder handler.
 *
 * Handles alt text sync for images in YOOtheme Pro's JSON-based page builder.
 * YOOtheme stores its layout as a JSON tree directly in post_content.
 *
 * @since      1.10.33
 * @package    ATAI
 * @subpackage ATAI/includes/builders
 */
class ATAI_Builder_YooTheme {

	/**
	 * Maximum recursion depth for walking the node tree.
	 *
	 * @var int
	 */
	private const MAX_DEPTH = 20;

	/**
	 * Node types that use background images (no alt text field in YOOtheme).
	 *
	 * @var array
	 */
	private const BACKGROUND_TYPES = array( 'section', 'column' );

	/**
	 * Cached decoded layout per post_id.
	 *
	 * @var array
	 */
	private $layouts = array();

	/**
	 * Cached storage format per post_id: 'pure_json' or 'html_comment'.
	 *
	 * @var array
	 */
	private $formats = array();

	/**
	 * Pending alt-text updates per post_id, recorded by update_image_alt().
	 *
	 * For html_comment posts, save() replays these onto the layout decoded
	 * fresh from the DB at save time, not $this->layouts — encoding the
	 * in-memory layout would discard structural edits the customer made
	 * during the refresh.
	 *
	 * @var array
	 */
	private $pending_updates = array();

	/**
	 * Check if YOOtheme Pro is the active theme.
	 *
	 * @since  1.10.33
	 * @return bool
	 */
	public static function is_active() {
		$theme = wp_get_theme();
		$template = $theme->get_template();
		return strpos( strtolower( $template ), 'yootheme' ) !== false;
	}

	/**
	 * Check if the post contains YOOtheme builder content.
	 *
	 * @since  1.10.33
	 * @param  int $post_id
	 * @return bool
	 */
	public function has_builder_content( $post_id ) {
		$layout = $this->get_layout( $post_id );
		return $layout !== null;
	}

	/**
	 * YOOtheme stores its layout in post_content.
	 *
	 * @since  1.10.33
	 * @return bool
	 */
	public function uses_post_content() {
		return true;
	}

	/**
	 * Extract all images from the YOOtheme layout.
	 *
	 * @since  1.10.33
	 * @param  int $post_id
	 * @return array
	 */
	public function extract_images( $post_id ) {
		$layout = $this->get_layout( $post_id );
		if ( $layout === null ) {
			return array();
		}

		$images = array();
		$this->walk_nodes( $layout, array(), $images, $post_id );
		return $images;
	}

	/**
	 * Update alt text for a specific image in the layout.
	 *
	 * @since  1.10.33
	 * @param  int    $post_id
	 * @param  mixed  $ref
	 * @param  string $alt_text
	 * @return bool
	 */
	public function update_image_alt( $post_id, $ref, $alt_text ) {
		if ( ! isset( $this->layouts[ $post_id ] ) ) {
			return false;
		}

		if ( ! isset( $ref['path'], $ref['prop'] ) ) {
			return false;
		}

		$layout = &$this->layouts[ $post_id ];
		$node = &$layout;
		$path = $ref['path'];
		$prop = $ref['prop'];

		// Walk to the target node
		foreach ( $path as $index ) {
			if ( ! isset( $node['children'][ $index ] ) ) {
				return false;
			}
			$node = &$node['children'][ $index ];
		}

		// Set the alt text prop on the in-memory layout (used for pure_json save
		// and so repeated extract_images calls within the same process see the
		// update).
		$alt_prop = $prop . '_alt';
		if ( ! isset( $node['props'] ) ) {
			$node['props'] = array();
		}
		$sanitized = sanitize_text_field( $alt_text );
		$node['props'][ $alt_prop ] = $sanitized;

		// Capture normalized URL + attachment ID so save() can verify the path
		// still points at the same image before stamping the alt. Normalization
		// absorbs CDN/host rewrites that would otherwise false-negative.
		$raw_expected_url      = isset( $node['props'][ $prop ] ) ? $node['props'][ $prop ] : null;
		$normalized_expected   = $this->normalize_identity_url( $raw_expected_url );
		$expected_attachment_id = null;
		if ( $normalized_expected !== null ) {
			$expected_attachment_id = ATAI_Utility::lookup_attachment_id( $normalized_expected, $post_id );
			if ( ! $expected_attachment_id ) {
				$expected_attachment_id = ATAI_Utility::lookup_attachment_id( $normalized_expected );
			}
		}

		$this->pending_updates[ $post_id ][] = array(
			'path'                   => $path,
			'prop'                   => $prop,
			'alt'                    => $sanitized,
			'expected_url'           => $normalized_expected,
			'expected_attachment_id' => $expected_attachment_id ?: null,
		);

		return true;
	}

	/**
	 * Persist changes back to post_content.
	 *
	 * pure_json encodes $this->layouts directly. html_comment re-reads
	 * post_content from the DB and replays pending updates onto the fresh
	 * layout so concurrent builder edits survive. Handler state for $post_id
	 * is always cleared on exit (try/finally) so reused handler instances
	 * can't replay stale updates onto a later save.
	 *
	 * @since  1.10.33
	 * @param  int $post_id
	 * @return bool
	 */
	public function save( $post_id ) {
		try {
			return $this->save_internal( $post_id );
		} finally {
			unset(
				$this->layouts[ $post_id ],
				$this->formats[ $post_id ],
				$this->pending_updates[ $post_id ]
			);
		}
	}

	/**
	 * Internal save implementation — kept separate so save() can always
	 * run cleanup in a finally block.
	 *
	 * @param  int $post_id
	 * @return bool
	 */
	private function save_internal( $post_id ) {
		if ( ! isset( $this->layouts[ $post_id ] ) ) {
			return false;
		}

		$format = $this->formats[ $post_id ] ?? 'pure_json';

		if ( $format === 'html_comment' ) {
			// Read post_content directly from the DB (bypassing the object
			// cache) so the merge target is the CURRENT stored state, not the
			// stale load-time copy. Refresh can take seconds; concurrent
			// customer edits to the HTML body or layout must survive.
			global $wpdb;
			$current_content = $wpdb->get_var( $wpdb->prepare(
				"SELECT post_content FROM {$wpdb->posts} WHERE ID = %d",
				$post_id
			) );
			if ( $current_content === null || $current_content === '' ) {
				ATAI_Utility::log_error( 'YOOtheme: Post content missing or empty when saving alt text on post ' . $post_id . '. The post may have been deleted during refresh.' );
				return false;
			}

			$current_layout_match = $this->extract_layout_comment( $current_content );
			if ( $current_layout_match === null ) {
				ATAI_Utility::log_error( 'YOOtheme: Could not locate the YOOtheme layout comment when saving alt text on post ' . $post_id . '. The post may have been edited or its YOOtheme layout removed during refresh.' );
				return false;
			}

			$current_layout = $current_layout_match['layout'];

			$updates = $this->pending_updates[ $post_id ] ?? array();
			if ( empty( $updates ) ) {
				return true; // Nothing to do; leave concurrent edits untouched.
			}

			$applied = 0;
			$skipped_identity = 0;
			$skipped_missing  = 0;
			foreach ( $updates as $update ) {
				$outcome = $this->apply_alt_update( $current_layout, $update );
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
					'YOOtheme: No alt text updates could be applied to post %d (attempted=%d, skipped because images were moved or swapped=%d, skipped because image nodes were deleted=%d). This usually means the page was edited in the YOOtheme builder while alt text was generating.',
					$post_id,
					count( $updates ),
					$skipped_identity,
					$skipped_missing
				) );
				return false;
			}

			$merged_json = wp_json_encode( $current_layout, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			if ( $merged_json === false ) {
				ATAI_Utility::log_error( 'YOOtheme: Failed to encode merged layout when saving alt text on post ' . $post_id . ': ' . json_last_error_msg() );
				return false;
			}

			// substr_replace on the matched offset — no regex rewrite hazards.
			$new_content = substr_replace(
				$current_content,
				'<!-- ' . $merged_json . ' -->',
				$current_layout_match['offset'],
				$current_layout_match['length']
			);

			if ( $new_content === '' || $new_content === $current_content ) {
				ATAI_Utility::log_error( 'YOOtheme: Could not rewrite the layout comment when saving alt text on post ' . $post_id . '. No changes were written.' );
				return false;
			}

			$post_content = $new_content;
		} else {
			$json = wp_json_encode( $this->layouts[ $post_id ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
			if ( $json === false ) {
				ATAI_Utility::log_error( 'YOOtheme: Failed to encode layout when saving alt text on post ' . $post_id . ': ' . json_last_error_msg() );
				return false;
			}
			$post_content = $json;
		}

		// Clear the object cache for this post before wp_update_post. We just
		// bypassed the cache with a direct $wpdb read, so any cached post
		// object is stale relative to $current_content. Without this, WP can
		// merge cached (stale) post fields into the update and produce a
		// spurious revision that drifts post_modified/post_modified_gmt.
		clean_post_cache( $post_id );

		$result = wp_update_post( array(
			'ID'           => $post_id,
			'post_content' => wp_slash( $post_content ),
		), true );

		if ( is_wp_error( $result ) ) {
			ATAI_Utility::log_error( 'YOOtheme: wp_update_post failed for post ' . $post_id . ': ' . $result->get_error_message() );
			return false;
		}

		return true;
	}

	/**
	 * Apply a single alt-text update to a decoded layout array in place.
	 *
	 * Walks $layout by $update['path'] and sets $update['prop'].'_alt' to
	 * $update['alt']. Returns a string outcome:
	 *   - 'applied'            — alt stamped successfully
	 *   - 'path_missing'       — path no longer resolves (node moved/deleted)
	 *   - 'identity_mismatch'  — path resolves but points at a different image
	 *                            (customer swapped/reordered), or either side
	 *                            of the URL compare is null
	 *
	 * @param  array &$layout Decoded layout (modified by reference)
	 * @param  array $update  [ 'path' => [...], 'prop' => 'image', 'alt' => '...', 'expected_url' => '...' ]
	 * @return string         One of 'applied', 'path_missing', 'identity_mismatch'
	 */
	private function apply_alt_update( &$layout, $update ) {
		$node = &$layout;
		foreach ( $update['path'] as $index ) {
			if ( ! isset( $node['children'][ $index ] ) ) {
				return 'path_missing';
			}
			$node = &$node['children'][ $index ];
		}

		// Verify node identity: path might still resolve after a reorder but
		// point at a different image. Normalize both sides so CDN/host
		// rewrites don't false-negative.
		$expected_url = $update['expected_url'] ?? null;
		$raw_current  = $node['props'][ $update['prop'] ] ?? null;
		$current_url  = $this->normalize_identity_url( $raw_current );
		if ( $expected_url === null || $current_url === null || $expected_url !== $current_url ) {
			return 'identity_mismatch';
		}

		if ( ! isset( $node['props'] ) ) {
			$node['props'] = array();
		}
		$node['props'][ $update['prop'] . '_alt' ] = $update['alt'];
		return 'applied';
	}

	/**
	 * Normalize an image URL for identity comparison.
	 *
	 * Wraps ATAI_Utility::normalize_image_url( $url, home_url() ) with
	 * null/empty safety so callers can pass raw prop values without
	 * pre-checking. Returns null if the input is not a non-empty string
	 * or normalization returns a falsy value.
	 *
	 * @param  mixed $url
	 * @return string|null
	 */
	private function normalize_identity_url( $url ) {
		if ( ! is_string( $url ) || $url === '' ) {
			return null;
		}
		$normalized = ATAI_Utility::normalize_image_url( $url, home_url() );
		return $normalized ?: null;
	}

	/**
	 * Locate the trailing YOOtheme layout comment in post_content.
	 *
	 * Scans every `<!-- ... -->` comment and keeps the last one whose body
	 * decodes to JSON with type === 'layout' + children array. Post-decode
	 * validation (not regex) so named layouts match and `-->` inside alt
	 * text can't truncate the match.
	 *
	 * @param  string $content
	 * @return array|null ['layout' => array, 'offset' => int, 'length' => int]
	 */
	private function extract_layout_comment( $content ) {
		if ( ! is_string( $content ) || $content === '' ) {
			return null;
		}

		$last = null;
		$offset = 0;
		$len = strlen( $content );

		while ( $offset < $len ) {
			$start = strpos( $content, '<!--', $offset );
			if ( $start === false ) {
				break;
			}

			// Scan successive `-->` candidates. YOOtheme's JSON-encoded alt text
			// can contain literal `-->` inside string values (wp_json_encode does
			// not escape '>'), so the FIRST `-->` isn't always the real comment
			// terminator. Accept the closest terminator that produces a valid
			// layout JSON body.
			$search_from = $start + 4;
			$matched     = false;
			$next_offset = $start + 4;

			while ( true ) {
				$end = strpos( $content, '-->', $search_from );
				if ( $end === false ) {
					$next_offset = $len;
					break;
				}

				$body_start = $start + 4;
				$body       = substr( $content, $body_start, $end - $body_start );
				$trimmed    = trim( $body );

				if ( $trimmed !== '' && $trimmed[0] === '{' ) {
					$data = json_decode( $trimmed, true );
					if (
						is_array( $data )
						&& isset( $data['type'] )
						&& $data['type'] === 'layout'
						&& isset( $data['children'] )
						&& is_array( $data['children'] )
					) {
						// YOOtheme always appends its layout comment at the very
						// end of post_content. Require nothing but whitespace to
						// follow, so a `{"type":"layout"...}` snippet embedded
						// in the HTML body (code sample, imported content) is
						// not mistaken for the builder's storage comment.
						$tail = substr( $content, $end + 3 );
						if ( trim( $tail ) === '' ) {
							$last = array(
								'layout' => $data,
								'offset' => $start,
								'length' => ( $end + 3 ) - $start,
							);
							$next_offset = $end + 3;
							$matched     = true;
							break;
						}
					}
				}

				// Try the next `-->` in case this one was embedded in JSON.
				$search_from = $end + 3;
			}

			if ( ! $matched ) {
				// No valid layout body found starting at this `<!--`; advance
				// past it and keep scanning for the next comment open.
				$next_offset = $start + 4;
			}

			$offset = $next_offset;
		}

		return $last;
	}

	// --- Private helpers ---

	/**
	 * Decode and cache the YOOtheme layout JSON for a post.
	 *
	 * @param  int $post_id
	 * @return array|null
	 */
	private function get_layout( $post_id ) {
		if ( isset( $this->layouts[ $post_id ] ) ) {
			return $this->layouts[ $post_id ];
		}

		$post = get_post( $post_id );
		if ( ! $post || empty( $post->post_content ) ) {
			return null;
		}

		// Two storage shapes: pure_json (post_content IS the JSON) or
		// html_comment (HTML body + trailing <!-- {"type":"layout",...} --> comment).
		// Track which so save() can restore it without destroying the HTML body.
		$trimmed_content = ltrim( $post->post_content );
		if ( $trimmed_content === '' ) {
			return null;
		}

		$format = null;
		$data   = null;

		if ( $trimmed_content[0] === '{' ) {
			$format = 'pure_json';
			$data = json_decode( $trimmed_content, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				ATAI_Utility::log_error( 'YOOtheme: Failed to decode layout for post ' . $post_id . ': ' . json_last_error_msg() );
				return null;
			}
		} else {
			$match = $this->extract_layout_comment( $post->post_content );
			if ( $match === null ) {
				return null;
			}
			$format = 'html_comment';
			$data   = $match['layout'];
		}

		// Require a children array with at least one typed child so non-YOOtheme
		// JSON post_content doesn't false-positive into builder mode.
		if ( ! isset( $data['children'] ) || ! is_array( $data['children'] ) ) {
			return null;
		}
		$has_typed_child = false;
		foreach ( $data['children'] as $child ) {
			if ( is_array( $child ) && isset( $child['type'] ) ) {
				$has_typed_child = true;
				break;
			}
		}
		if ( ! $has_typed_child ) {
			return null;
		}

		$this->layouts[ $post_id ] = $data;
		$this->formats[ $post_id ] = $format;

		return $this->layouts[ $post_id ];
	}

	/**
	 * Recursively walk the node tree and collect image references.
	 *
	 * Only processes the primary 'image' prop where 'image_alt' exists.
	 * Skips hover images (image2 — no image2_alt in YOOtheme) and
	 * section/column backgrounds (decorative, no alt field).
	 *
	 * @param  array $node
	 * @param  array $path     Array of child indices leading to this node
	 * @param  array &$images  Collected image references
	 * @param  int   $post_id
	 * @param  int   $depth    Current recursion depth
	 */
	private function walk_nodes( $node, $path, &$images, $post_id, $depth = 0 ) {
		if ( $depth >= self::MAX_DEPTH ) {
			return;
		}

		$node_type = $node['type'] ?? '';

		// Skip section/column backgrounds — decorative images with no alt field
		$is_background = in_array( $node_type, self::BACKGROUND_TYPES, true );

		if ( ! $is_background && isset( $node['props'] ) && is_array( $node['props'] ) ) {
			// Only process 'image' prop — YOOtheme has no 'image2_alt' for hover images
			if ( ! empty( $node['props']['image'] ) ) {
				$raw_url = $node['props']['image'];

				// Skip data URIs (no attachment to look up). SVG is allowed — it's a
				// valid attachment type throughout the plugin; eligibility is decided
				// in class-atai-attachment.php, not here.
				if ( strpos( $raw_url, 'data:' ) !== 0 ) {
					$normalized_url = ATAI_Utility::normalize_image_url( $raw_url, home_url() );
					if ( $normalized_url ) {
						$current_alt = $node['props']['image_alt'] ?? '';

						$attachment_id = ATAI_Utility::lookup_attachment_id( $normalized_url, $post_id );
						if ( ! $attachment_id ) {
							$attachment_id = ATAI_Utility::lookup_attachment_id( $normalized_url );
						}

						$images[] = array(
							'url'           => $normalized_url,
							'attachment_id' => $attachment_id,
							'current_alt'   => $current_alt,
							'ref'           => array(
								'path' => $path,
								'prop' => 'image',
							),
						);
					}
				}
			}
		}

		// Recurse into children
		if ( isset( $node['children'] ) && is_array( $node['children'] ) ) {
			foreach ( $node['children'] as $index => $child ) {
				$child_path = array_merge( $path, array( $index ) );
				$this->walk_nodes( $child, $child_path, $images, $post_id, $depth + 1 );
			}
		}
	}
}
