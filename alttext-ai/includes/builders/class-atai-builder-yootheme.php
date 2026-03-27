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

		// Set the alt text prop
		$alt_prop = $prop . '_alt';
		if ( ! isset( $node['props'] ) ) {
			$node['props'] = array();
		}
		$node['props'][ $alt_prop ] = sanitize_text_field( $alt_text );

		return true;
	}

	/**
	 * Persist changes back to post_content.
	 *
	 * @since  1.10.33
	 * @param  int $post_id
	 * @return bool
	 */
	public function save( $post_id ) {
		if ( ! isset( $this->layouts[ $post_id ] ) ) {
			return false;
		}

		$json = wp_json_encode( $this->layouts[ $post_id ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( $json === false ) {
			error_log( 'ATAI YOOtheme: Failed to encode layout for post ' . $post_id . ': ' . json_last_error_msg() );
			return false;
		}

		$result = wp_update_post( array(
			'ID'           => $post_id,
			'post_content' => wp_slash( $json ),
		), true );

		if ( is_wp_error( $result ) ) {
			error_log( 'ATAI YOOtheme: wp_update_post failed for post ' . $post_id . ': ' . $result->get_error_message() );
			return false;
		}

		return true;
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

		$data = json_decode( $post->post_content, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			error_log( 'ATAI YOOtheme: Failed to decode layout for post ' . $post_id . ': ' . json_last_error_msg() );
			return null;
		}

		// YOOtheme layouts have a children array with typed child nodes.
		// Require children array AND at least one child with a 'type' key
		// to avoid false-positive activation on non-YOOtheme JSON post_content.
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

				// Skip data URIs and SVGs
				$ext = strtolower( pathinfo( strtok( $raw_url, '?' ), PATHINFO_EXTENSION ) );
				if ( strpos( $raw_url, 'data:' ) !== 0 && $ext !== 'svg' ) {
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
