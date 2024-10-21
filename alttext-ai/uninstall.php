<?php
/**
 * Fired when the plugin is uninstalled.
 *
 * For more information, see the following discussion:
 * https://github.com/tommcfarlin/WordPress-Plugin-Boilerplate/pull/123#issuecomment-28541913
 *
 * @link       https://alttext.ai
 * @since      1.0.0
 *
 * @package    ATAI
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'atai_api_key' );
delete_option( 'atai_error_logs' );
delete_option( 'atai_public' );
delete_option( 'atai_lang' );
delete_option( 'atai_model_name' );
delete_option( 'atai_enabled' );
delete_option( 'atai_ecomm' );
delete_option( 'atai_ecomm_title' );
delete_option( 'atai_update_title' );
delete_option( 'atai_update_caption' );
delete_option( 'atai_update_description' );
delete_option( 'atai_alt_prefix' );
delete_option( 'atai_alt_suffix' );
delete_option( 'atai_type_extensions' );
delete_option( 'atai_gpt_prompt' );
delete_option( 'atai_no_credit_warning' );
delete_option( 'atai_timeout' );
delete_option( 'atai_keywords_title' );
delete_option( 'atai_bulk_refresh_overwrite' );
delete_option( 'atai_bulk_refresh_external' );
delete_option( 'atai_skip_filenotfound' );
delete_option( 'atai_woo_marketplace' );

// Database cleanup
global $wpdb;
$table_name = $wpdb->prefix . 'atai_assets'; // Cannot use plugin constant here
$wpdb->query( "DROP TABLE IF EXISTS {$table_name};" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQL.NotPrepared

delete_option( 'atai_db_version' );
