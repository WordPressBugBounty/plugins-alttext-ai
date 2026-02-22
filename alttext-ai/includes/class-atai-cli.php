<?php
/**
 * WP-CLI commands for AltText.ai plugin.
 *
 * @link       https://alttext.ai
 * @since      1.10.23
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
 *     # Enrich post content with alt text for inline images
 *     wp alttext enrich
 *
 *     # Enrich only WooCommerce products
 *     wp alttext enrich --post-type=product
 *
 *     # Import alt text from CSV file
 *     wp alttext import /path/to/export.csv
 *
 * @since 1.10.23
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

		// Treat --limit=0 as explicit no-op.
		if ( 0 === $limit ) {
			if ( $porcelain ) {
				WP_CLI::line( '0' );
			} else {
				WP_CLI::success( 'Nothing to process (--limit=0).' );
			}
			return;
		}

		$this->require_api_key();

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
						WP_CLI::warning( sprintf( 'Failed to process attachment #%d', $attachment_id ) );
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
	 * Enrich post content with alt text for inline images.
	 *
	 * Scans published posts for <img> tags and generates alt text via the
	 * AltText.ai API. Updates alt text directly in post content HTML.
	 *
	 * ## OPTIONS
	 *
	 * [--post-type=<type>]
	 * : Comma-separated post types to process. Default: post,page (and product if WooCommerce active).
	 *
	 * [--limit=<number>]
	 * : Maximum number of posts to process. Default: all.
	 *
	 * [--force]
	 * : Regenerate alt text even for images that already have it.
	 *
	 * [--include-external]
	 * : Also process external (non-library) images.
	 *
	 * [--dry-run]
	 * : Show what would be processed without making changes.
	 *
	 * [--porcelain]
	 * : Output only the count of generated alt texts (for scripting).
	 *
	 * ## EXAMPLES
	 *
	 *     # Enrich all posts and pages
	 *     wp alttext enrich
	 *
	 *     # Enrich only WooCommerce products
	 *     wp alttext enrich --post-type=product
	 *
	 *     # Preview what would be enriched
	 *     wp alttext enrich --dry-run
	 *
	 *     # Overwrite existing alt text
	 *     wp alttext enrich --force
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function enrich( $args, $assoc_args ) {
		$force            = isset( $assoc_args['force'] );
		$include_external = isset( $assoc_args['include-external'] );
		$dry_run          = isset( $assoc_args['dry-run'] );
		$porcelain        = isset( $assoc_args['porcelain'] );
		$limit            = isset( $assoc_args['limit'] ) ? absint( $assoc_args['limit'] ) : -1;

		// Treat --limit=0 as explicit no-op.
		if ( 0 === $limit ) {
			if ( $porcelain ) {
				WP_CLI::line( '0' );
			} else {
				WP_CLI::success( 'Nothing to process (--limit=0).' );
			}
			return;
		}

		// Build post types list.
		if ( isset( $assoc_args['post-type'] ) ) {
			$post_types = array_map( 'sanitize_key', array_map( 'trim', explode( ',', $assoc_args['post-type'] ) ) );

			// Validate against registered post types.
			$registered = get_post_types();
			foreach ( $post_types as $pt ) {
				if ( ! isset( $registered[ $pt ] ) ) {
					WP_CLI::warning( sprintf( 'Post type "%s" is not registered and will be skipped.', $pt ) );
				}
			}
			$post_types = array_filter( $post_types, function ( $pt ) use ( $registered ) {
				return isset( $registered[ $pt ] );
			} );
			if ( empty( $post_types ) ) {
				WP_CLI::error( 'No valid post types provided.' );
			}
		} else {
			$post_types = array( 'post', 'page' );
			if ( ATAI_Utility::has_woocommerce() ) {
				$post_types[] = 'product';
			}
		}

		$this->require_api_key();

		// Query posts.
		if ( ! $porcelain ) {
			WP_CLI::log( sprintf( 'Scanning %s for posts to enrich...', implode( ', ', $post_types ) ) );
		}

		$post_ids = $this->get_posts_for_enrichment( $post_types, $limit );

		if ( empty( $post_ids ) ) {
			if ( $porcelain ) {
				WP_CLI::line( '0' );
			} else {
				WP_CLI::success( 'No posts found to enrich.' );
			}
			return;
		}

		$total = count( $post_ids );

		if ( $dry_run ) {
			if ( $porcelain ) {
				WP_CLI::line( (string) $total );
			} else {
				WP_CLI::log( sprintf( 'Dry run: Would enrich %d posts.', $total ) );
				foreach ( array_slice( $post_ids, 0, 10 ) as $id ) {
					$post = get_post( $id );
					if ( ! $post ) {
						WP_CLI::log( sprintf( '  - #%d: (post not found)', $id ) );
						continue;
					}
					WP_CLI::log( sprintf( '  - #%d: %s (%s)', $id, $post->post_title, $post->post_type ) );
				}
				if ( $total > 10 ) {
					WP_CLI::log( sprintf( '  ... and %d more', $total - 10 ) );
				}
			}
			return;
		}

		if ( ! $porcelain ) {
			WP_CLI::log( sprintf( 'Found %d posts. Enriching...', $total ) );
		}

		$progress        = $porcelain ? null : \WP_CLI\Utils\make_progress_bar( 'Enriching posts', $total );
		$total_images    = 0;
		$total_generated = 0;
		$post_handler    = new ATAI_Post();

		foreach ( $post_ids as $index => $post_id ) {
			$result = $post_handler->enrich_post_content( $post_id, $force, $include_external );

			if ( false === $result ) {
				if ( ! $porcelain ) {
					WP_CLI::warning( sprintf( 'Post #%d not found, skipping.', $post_id ) );
				}
			} elseif ( is_array( $result ) ) {
				// Check for credit exhaustion.
				if ( ! empty( $result['no_credits'] ) ) {
					if ( $progress ) {
						$progress->finish();
					}
					WP_CLI::warning( sprintf( 'Ran out of credits after processing %d posts.', $index + 1 ) );
					if ( $porcelain ) {
						WP_CLI::line( (string) $total_generated );
					}
					return;
				}

				$total_images    += $result['total_images_found'] ?? 0;
				$total_generated += $result['num_alttext_generated'] ?? 0;
			}

			if ( $progress ) {
				$progress->tick();
			}

			// Pause between posts to avoid rate limiting (skip after last).
			if ( $index < $total - 1 ) {
				usleep( 500000 ); // 0.5s
			}
		}

		if ( $progress ) {
			$progress->finish();
		}

		if ( $porcelain ) {
			WP_CLI::line( (string) $total_generated );
		} else {
			WP_CLI::success(
				sprintf(
					'Complete: %d posts enriched, %d images found, %d alt texts generated.',
					$total,
					$total_images,
					$total_generated
				)
			);
		}
	}

	/**
	 * Import alt text from a CSV file.
	 *
	 * CSV must contain 'asset_id' and 'alt_text' columns. Optionally include
	 * a 'url' column for fallback matching by image URL.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to the CSV file.
	 *
	 * [--lang=<language>]
	 * : Import from a language-specific column (e.g., alt_text_es). Falls back to alt_text if empty.
	 *
	 * [--dry-run]
	 * : Show what would be imported without making changes.
	 *
	 * [--porcelain]
	 * : Output only the count of imported images (for scripting).
	 *
	 * ## EXAMPLES
	 *
	 *     # Import alt text from CSV
	 *     wp alttext import /path/to/export.csv
	 *
	 *     # Import Spanish alt text
	 *     wp alttext import /path/to/export.csv --lang=es
	 *
	 *     # Preview what would be imported
	 *     wp alttext import /path/to/export.csv --dry-run
	 *
	 * @when after_wp_load
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Associative arguments.
	 */
	public function import( $args, $assoc_args ) {
		$file_path = $args[0];
		$lang      = isset( $assoc_args['lang'] ) ? sanitize_text_field( $assoc_args['lang'] ) : '';
		$dry_run   = isset( $assoc_args['dry-run'] );
		$porcelain = isset( $assoc_args['porcelain'] );

		// Validate file exists.
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			WP_CLI::error( sprintf( 'File not found or not readable: %s', $file_path ) );
		}

		// Open and validate CSV.
		$handle = fopen( $file_path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			WP_CLI::error( sprintf( 'Could not open file: %s', $file_path ) );
		}

		$header = fgetcsv( $handle, ATAI_CSV_LINE_LENGTH, ',', '"', '\\' );
		if ( ! $header ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			WP_CLI::error( 'Could not read CSV header.' );
		}

		// Find required columns.
		$asset_id_index  = array_search( 'asset_id', $header, true );
		$alt_text_index  = array_search( 'alt_text', $header, true );
		$image_url_index = array_search( 'url', $header, true );

		if ( false === $asset_id_index || false === $alt_text_index ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			WP_CLI::error( 'Invalid CSV: missing required columns (asset_id, alt_text).' );
		}

		// Find language-specific column if requested.
		$lang_column_index = $alt_text_index;
		if ( ! empty( $lang ) ) {
			$lang_column_name  = 'alt_text_' . $lang;
			$found_lang_index  = array_search( $lang_column_name, $header, true );
			if ( false !== $found_lang_index ) {
				$lang_column_index = $found_lang_index;
			} elseif ( ! $porcelain ) {
				WP_CLI::warning( sprintf( 'Language column "%s" not found, using default alt_text column.', $lang_column_name ) );
			}
		}

		// Count rows for progress bar without loading all into memory.
		$total = 0;
		while ( fgetcsv( $handle, ATAI_CSV_LINE_LENGTH, ',', '"', '\\' ) !== false ) {
			$total++;
		}

		if ( 0 === $total ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
			if ( $porcelain ) {
				WP_CLI::line( '0' );
			} else {
				WP_CLI::success( 'CSV file contains no data rows.' );
			}
			return;
		}

		// Rewind past header for streaming.
		rewind( $handle );
		fgetcsv( $handle, ATAI_CSV_LINE_LENGTH, ',', '"', '\\' ); // skip header

		if ( $dry_run ) {
			// Count how many rows can be matched to attachments.
			$matchable = 0;
			while ( ( $data = fgetcsv( $handle, ATAI_CSV_LINE_LENGTH, ',', '"', '\\' ) ) !== false ) {
				$asset_id      = $data[ $asset_id_index ] ?? '';
				$attachment_id = ATAI_Utility::find_atai_asset( $asset_id );

				if ( ! $attachment_id && false !== $image_url_index && isset( $data[ $image_url_index ] ) ) {
					$attachment_id = attachment_url_to_postid( $data[ $image_url_index ] );
				}

				if ( $attachment_id ) {
					$matchable++;
				}
			}
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

			if ( $porcelain ) {
				WP_CLI::line( (string) $matchable );
			} else {
				WP_CLI::log( sprintf( 'Dry run: %d of %d rows match existing attachments.', $matchable, $total ) );
			}
			return;
		}

		if ( ! $porcelain ) {
			WP_CLI::log( sprintf( 'Importing %d rows...', $total ) );
		}

		$progress = $porcelain ? null : \WP_CLI\Utils\make_progress_bar( 'Importing alt text', $total );
		$imported = 0;
		$skipped  = 0;

		while ( ( $data = fgetcsv( $handle, ATAI_CSV_LINE_LENGTH, ',', '"', '\\' ) ) !== false ) {
			$asset_id = $data[ $asset_id_index ] ?? '';

			// Get alt text from language column with fallback.
			$alt_text = isset( $data[ $lang_column_index ] ) ? $data[ $lang_column_index ] : '';
			if ( empty( $alt_text ) && $lang_column_index !== $alt_text_index ) {
				$alt_text = isset( $data[ $alt_text_index ] ) ? $data[ $alt_text_index ] : '';
			}

			// Sanitize alt text — strip HTML tags.
			$alt_text = wp_strip_all_tags( $alt_text );

			// Skip rows with empty alt text to avoid overwriting existing values.
			if ( empty( $alt_text ) ) {
				$skipped++;
				if ( $progress ) {
					$progress->tick();
				}
				continue;
			}

			// Find attachment by asset ID.
			$attachment_id = ATAI_Utility::find_atai_asset( $asset_id );

			// Fallback to URL lookup.
			if ( ! $attachment_id && false !== $image_url_index && isset( $data[ $image_url_index ] ) ) {
				$image_url     = esc_url_raw( $data[ $image_url_index ] );
				$attachment_id = $image_url ? attachment_url_to_postid( $image_url ) : 0;

				if ( ! empty( $attachment_id ) && ! empty( $asset_id ) ) {
					ATAI_Utility::record_atai_asset( $attachment_id, $asset_id );
				}
			}

			if ( ! $attachment_id ) {
				$skipped++;
				if ( $progress ) {
					$progress->tick();
				}
				continue;
			}

			// Update alt text.
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt_text );
			$imported++;

			// Update title/caption/description per plugin settings.
			$post_updates = array();

			if ( ATAI_Utility::get_setting( 'atai_update_title' ) === 'yes' ) {
				$post_updates['post_title'] = sanitize_text_field( $alt_text );
			}
			if ( ATAI_Utility::get_setting( 'atai_update_caption' ) === 'yes' ) {
				$post_updates['post_excerpt'] = sanitize_textarea_field( $alt_text );
			}
			if ( ATAI_Utility::get_setting( 'atai_update_description' ) === 'yes' ) {
				$post_updates['post_content'] = sanitize_text_field( $alt_text );
			}

			if ( ! empty( $post_updates ) ) {
				$post_updates['ID'] = $attachment_id;
				wp_update_post( $post_updates );
			}

			if ( $progress ) {
				$progress->tick();
			}
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		if ( $progress ) {
			$progress->finish();
		}

		if ( $porcelain ) {
			WP_CLI::line( (string) $imported );
		} else {
			WP_CLI::success(
				sprintf(
					'Complete: %d imported, %d skipped (no matching attachment or empty alt text).',
					$imported,
					$skipped
				)
			);
		}
	}

	/**
	 * Get published post IDs for enrichment.
	 *
	 * Only returns posts whose content contains an <img tag to avoid
	 * iterating posts that have nothing to enrich.
	 *
	 * @param array $post_types Post types to query.
	 * @param int   $limit      Maximum posts to return. -1 for all.
	 *
	 * @return array Array of post IDs.
	 */
	private function get_posts_for_enrichment( $post_types, $limit ) {
		global $wpdb;

		// Build placeholders for post types.
		$type_placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $type_placeholders contains only %s placeholders generated by array_fill, not user data
	$sql = $wpdb->prepare(
			"SELECT ID FROM {$wpdb->posts}
			WHERE post_type IN ($type_placeholders)
			AND post_status = 'publish'
			AND post_content LIKE %s
			ORDER BY ID ASC",
			array_merge( $post_types, array( '%<img %' ) )
		);
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $limit > 0 ) {
			$sql .= $wpdb->prepare( ' LIMIT %d', $limit );
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return array_map( 'intval', $wpdb->get_col( $sql ) );
	}

	/**
	 * Verify an API key is configured, or halt with an error.
	 */
	private function require_api_key() {
		if ( empty( ATAI_Utility::get_api_key() ) ) {
			WP_CLI::error( 'No API key configured. Set it in WordPress Admin → AltText.ai → Settings, or define ATAI_API_KEY constant.' );
		}
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
			if ( 0 === strpos( $result, $pattern ) ) {
				return false;
			}
		}
		return true;
	}
}

WP_CLI::add_command( 'alttext', 'ATAI_CLI_Command' );
