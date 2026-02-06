<?php
/**
 * WP-CLI commands for AltText.ai plugin.
 *
 * @link       https://alttext.ai
 * @since      1.11.0
 *
 * @package    ATAI
 * @subpackage ATAI/includes
 */

// Bail if WP-CLI is not available.
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	return;
}

/**
 * Generate alt text for images using AltText.ai.
 *
 * ## EXAMPLES
 *
 *     # Generate alt text for all images missing alt text
 *     wp alttext generate
 *
 *     # Generate alt text for first 50 images
 *     wp alttext generate --limit=50
 *
 *     # Regenerate alt text for ALL images (overwrites existing)
 *     wp alttext generate --force
 *
 *     # Check plugin status and configuration
 *     wp alttext status
 *
 * @since 1.11.0
 */
class ATAI_CLI_Command {

	/**
	 * Generate alt text for images.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<number>]
	 * : Maximum number of images to process. Default: all eligible images.
	 *
	 * [--batch-size=<number>]
	 * : Number of images to process per batch. Default: 10. Max: 50.
	 *
	 * [--force]
	 * : Regenerate alt text even for images that already have it.
	 *
	 * [--dry-run]
	 * : Show what would be processed without making changes.
	 *
	 * [--porcelain]
	 * : Output only the count of processed images (for scripting).
	 *
	 * ## EXAMPLES
	 *
	 *     # Generate alt text for images missing it
	 *     wp alttext generate
	 *
	 *     # Process first 100 images in batches of 5
	 *     wp alttext generate --limit=100 --batch-size=5
	 *
	 *     # Preview what would be processed
	 *     wp alttext generate --dry-run
	 *
	 *     # Regenerate all images (overwrite existing)
	 *     wp alttext generate --force
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function generate( $args, $assoc_args ) {
		// Parse arguments.
		$limit      = isset( $assoc_args['limit'] ) ? absint( $assoc_args['limit'] ) : -1;
		$batch_size = isset( $assoc_args['batch-size'] ) ? min( absint( $assoc_args['batch-size'] ), 50 ) : 10;
		$force      = isset( $assoc_args['force'] );
		$dry_run    = isset( $assoc_args['dry-run'] );
		$porcelain  = isset( $assoc_args['porcelain'] );

		// Ensure batch size is at least 1.
		$batch_size = max( 1, $batch_size );

		// Verify API key is configured.
		$api_key = ATAI_Utility::get_api_key();
		if ( empty( $api_key ) ) {
			WP_CLI::error( 'No API key configured. Set it in WordPress Admin → AltText.ai → Settings, or define ATAI_API_KEY constant.' );
		}

		// Get eligible images.
		if ( ! $porcelain ) {
			WP_CLI::log( 'Scanning for eligible images...' );
		}
		$images = $this->get_eligible_images( $limit, $force, $dry_run );

		if ( empty( $images ) ) {
			if ( $porcelain ) {
				WP_CLI::line( '0' );
			} else {
				WP_CLI::success( 'No eligible images found.' );
			}
			return;
		}

		$total = count( $images );

		if ( $dry_run ) {
			if ( $porcelain ) {
				WP_CLI::line( (string) $total );
			} else {
				WP_CLI::log( sprintf( 'Dry run: Would process %d images.', $total ) );
				foreach ( array_slice( $images, 0, 10 ) as $id ) {
					$url = wp_get_attachment_url( $id );
					WP_CLI::log( sprintf( '  - Attachment #%d: %s', $id, $url ? $url : '(no URL)' ) );
				}
				if ( $total > 10 ) {
					WP_CLI::log( sprintf( '  ... and %d more', $total - 10 ) );
				}
			}
			return;
		}

		if ( ! $porcelain ) {
			WP_CLI::log( sprintf( 'Found %d eligible images. Processing in batches of %d...', $total, $batch_size ) );
		}

		$progress = $porcelain ? null : \WP_CLI\Utils\make_progress_bar( 'Generating alt text', $total );

		$success      = 0;
		$failed       = 0;
		$skipped      = 0;
		$attachment   = new ATAI_Attachment();
		$batches      = array_chunk( $images, $batch_size );
		$batch_count  = count( $batches );

		foreach ( $batches as $batch_index => $batch ) {
			foreach ( $batch as $attachment_id ) {
				// Double-check eligibility (in case state changed).
				if ( ! $attachment->is_attachment_eligible( $attachment_id, 'cli' ) ) {
					$skipped++;
					if ( $progress ) {
						$progress->tick();
					}
					continue;
				}

				$result = $attachment->generate_alt( $attachment_id );

				if ( $this->is_success( $result ) ) {
					$success++;
				} elseif ( 'insufficient_credits' === $result ) {
					if ( $progress ) {
						$progress->finish();
					}
					WP_CLI::warning( sprintf( 'Ran out of credits after processing %d images.', $success ) );
					if ( $porcelain ) {
						WP_CLI::line( (string) $success );
					}
					return;
				} else {
					$failed++;
					if ( ! $porcelain ) {
						WP_CLI::debug( sprintf( 'Failed to process attachment #%d', $attachment_id ) );
					}
				}

				if ( $progress ) {
					$progress->tick();
				}
			}

			// Pause between batches to avoid rate limiting (skip after last batch).
			if ( $batch_index < $batch_count - 1 ) {
				sleep( 1 );
			}
		}

		if ( $progress ) {
			$progress->finish();
		}

		if ( $porcelain ) {
			WP_CLI::line( (string) $success );
		} else {
			WP_CLI::success(
				sprintf(
					'Complete: %d successful, %d failed, %d skipped.',
					$success,
					$failed,
					$skipped
				)
			);
		}
	}

	/**
	 * Show plugin status and configuration.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format. Options: table, json, yaml. Default: table.
	 *
	 * ## EXAMPLES
	 *
	 *     wp alttext status
	 *     wp alttext status --format=json
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function status( $args, $assoc_args ) {
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		$api_key     = ATAI_Utility::get_api_key();
		$has_key     = ! empty( $api_key );
		$auto_gen    = ATAI_Utility::get_setting( 'atai_enabled', 'yes' );

		// Count images.
		$missing_alt  = $this->count_images_missing_alt();
		$total_images = $this->count_total_images();

		$status_data = array(
			array(
				'Setting' => 'API Key',
				'Value'   => $has_key ? 'Configured' : 'Not configured',
				'Status'  => $has_key ? '✓' : '✗',
			),
			array(
				'Setting' => 'Auto-generate on upload',
				'Value'   => $auto_gen,
				'Status'  => 'yes' === $auto_gen ? '✓' : '○',
			),
			array(
				'Setting' => 'Default language',
				'Value'   => ATAI_Utility::get_setting( 'atai_lang', ATAI_Utility::get_default_language() ),
				'Status'  => '○',
			),
			array(
				'Setting' => 'Total images',
				'Value'   => (string) $total_images,
				'Status'  => '○',
			),
			array(
				'Setting' => 'Images missing alt text',
				'Value'   => (string) $missing_alt,
				'Status'  => $missing_alt > 0 ? '!' : '✓',
			),
		);

		\WP_CLI\Utils\format_items( $format, $status_data, array( 'Setting', 'Value', 'Status' ) );
	}

	/**
	 * Get eligible images for processing.
	 *
	 * Fetches images in chunks, filters for eligibility, and returns up to the limit.
	 * This ensures --limit returns N eligible images, not N database rows that may be ineligible.
	 *
	 * @param int  $limit    Maximum number of images to return. -1 for all.
	 * @param bool $force    Include images that already have alt text.
	 * @param bool $dry_run  If true, skip side effects like metadata generation.
	 *
	 * @return array Array of attachment IDs.
	 */
	private function get_eligible_images( $limit, $force, $dry_run = false ) {
		global $wpdb;

		$attachment = new ATAI_Attachment();
		$eligible   = array();
		$offset     = 0;
		$chunk_size = 1000; // Fetch in chunks to avoid memory issues.

		// Build base SQL query.
		if ( $force ) {
			// All images.
			$sql = "
				SELECT p.ID
				FROM {$wpdb->posts} p
				WHERE p.post_mime_type LIKE 'image/%'
				  AND p.post_type = 'attachment'
				  AND p.post_status = 'inherit'
				ORDER BY p.ID ASC
			";
		} else {
			// Only images missing alt text.
			$sql = "
				SELECT p.ID
				FROM {$wpdb->posts} p
				LEFT JOIN {$wpdb->postmeta} pm
					ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
				WHERE p.post_mime_type LIKE 'image/%'
				  AND p.post_type = 'attachment'
				  AND p.post_status = 'inherit'
				  AND (pm.post_id IS NULL OR TRIM(COALESCE(pm.meta_value, '')) = '')
				ORDER BY p.ID ASC
			";
		}

		// Fetch chunks until we have enough eligible images or run out.
		while ( $limit < 0 || count( $eligible ) < $limit ) {
			$chunk_sql = $sql . $wpdb->prepare( ' LIMIT %d OFFSET %d', $chunk_size, $offset );

			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
			$chunk = $wpdb->get_col( $chunk_sql );

			// No more results.
			if ( empty( $chunk ) ) {
				break;
			}

			// Filter chunk for eligibility.
			foreach ( $chunk as $attachment_id ) {
				$attachment_id = absint( $attachment_id );

				if ( $attachment->is_attachment_eligible( $attachment_id, 'cli', $dry_run ) ) {
					$eligible[] = $attachment_id;

					// Stop if we've reached the limit.
					if ( $limit > 0 && count( $eligible ) >= $limit ) {
						break 2;
					}
				}
			}

			$offset += $chunk_size;
		}

		return $eligible;
	}

	/**
	 * Count total images in the media library.
	 *
	 * @return int
	 */
	private function count_total_images() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			"
			SELECT COUNT(*)
			FROM {$wpdb->posts}
			WHERE post_mime_type LIKE 'image/%'
			  AND post_type = 'attachment'
			  AND post_status = 'inherit'
		"
		);
	}

	/**
	 * Count images missing alt text.
	 *
	 * @return int
	 */
	private function count_images_missing_alt() {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (int) $wpdb->get_var(
			"
			SELECT COUNT(*)
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->postmeta} pm
				ON p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt'
			WHERE p.post_mime_type LIKE 'image/%'
			  AND p.post_type = 'attachment'
			  AND p.post_status = 'inherit'
			  AND (pm.post_id IS NULL OR TRIM(COALESCE(pm.meta_value, '')) = '')
		"
		);
	}

	/**
	 * Check if a generate_alt result indicates success.
	 *
	 * @param mixed $result Result from generate_alt().
	 *
	 * @return bool
	 */
	private function is_success( $result ) {
		if ( is_wp_error( $result ) ) {
			return false;
		}
		if ( ! is_string( $result ) || '' === $result ) {
			return false;
		}
		// Check for known error codes.
		$error_patterns = array( 'error_', 'invalid_', 'insufficient_credits', 'url_access_error' );
		foreach ( $error_patterns as $pattern ) {
			if ( 0 === strpos( $result, $pattern ) || $result === $pattern ) {
				return false;
			}
		}
		return true;
	}
}

WP_CLI::add_command( 'alttext', 'ATAI_CLI_Command' );
