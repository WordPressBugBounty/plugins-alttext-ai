<?php
/**
 * This file is used to markup the bulk generate page of the plugin.
 *
 * @link       https://alttext.ai
 * @since      1.0.0
 *
 * @package    ATAI
 * @subpackage ATAI/admin/partials
 */
?>

<?php  if ( ! defined( 'WPINC' ) ) die; ?>

<?php
  $cannot_bulk_update = ( ! $this->account || ! $this->account['available'] );
  $subscriptions_url = esc_url( ATAI_Utility::get_credits_url() );
  $action = sanitize_text_field( $_REQUEST['atai_action'] ?? 'normal' );

  /* Variables used only for bulk-action selected images */
  $batch_id = sanitize_text_field( $_REQUEST['atai_batch_id'] ?? null );
  $selected_images = ( $action === 'bulk-select-generate' ) ? get_transient( 'alttext_bulk_select_generate_' . $batch_id ) : null;

  if ( $action === 'bulk-select-generate' && $selected_images === false ) {
    $action = 'normal';
  }

  if ( $action === 'normal' ) {
    global $wpdb;
    $atai_asset_table = $wpdb->prefix . ATAI_DB_ASSET_TABLE;
    $mode = isset( $_GET['atai_mode'] ) && $_GET['atai_mode'] === 'all' ? 'all' : 'missing';
    $mode_url = admin_url( sprintf( 'admin.php?%s', http_build_query( $_GET ) ) );
    $wc_products_url = $wc_only_featured_url = $only_attached_url = $only_new_url = $mode_url;

    if ( $mode !== 'all' ) {
      $mode_url = add_query_arg( 'atai_mode', 'all', $mode_url );
    } else {
      $mode_url = remove_query_arg( 'atai_mode', $mode_url );
    }

    $only_attached = isset( $_GET['atai_attached'] ) && $_GET['atai_attached'] === '1' ? '1' : '0';
    if ( $only_attached !== '1' ) {
      $only_attached_url = add_query_arg( 'atai_attached', '1', $only_attached_url );
    } else {
      $only_attached_url = remove_query_arg( 'atai_attached', $only_attached_url );
    }

    $only_new = isset( $_GET['atai_only_new'] ) && $_GET['atai_only_new'] === '1' ? '1' : '0';
    if ( $only_new !== '1' ) {
      $only_new_url = add_query_arg( 'atai_only_new', '1', $only_new_url );
    } else {
      $only_new_url = remove_query_arg( 'atai_only_new', $only_new_url );
    }

    $wc_products = isset( $_GET['atai_wc_products'] ) && $_GET['atai_wc_products'] === '1' ? '1' : '0';
    if ( $wc_products !== '1' ) {
      $wc_products_url = add_query_arg( 'atai_wc_products', '1', $wc_products_url );
    } else {
      $wc_products_url = remove_query_arg( array('atai_wc_products', 'atai_wc_only_featured'), $wc_products_url );
    }

    $wc_only_featured = isset( $_GET['atai_wc_only_featured'] ) && $_GET['atai_wc_only_featured'] === '1' ? '1' : '0';
    if ( $wc_only_featured !== '1' ) {
      $wc_only_featured_url = add_query_arg( array('atai_wc_products' => 1, 'atai_wc_only_featured' => 1), $wc_only_featured_url );
    } else {
      $wc_only_featured_url = remove_query_arg( 'atai_wc_only_featured', $wc_only_featured_url );
    }

    // Count of all images in the media gallery
    $all_images_query = <<<SQL
SELECT COUNT(*) as total_images
FROM {$wpdb->posts} p
WHERE (p.post_mime_type LIKE 'image/%')
  AND p.post_type = 'attachment'
  AND p.post_status = 'inherit'
SQL;

    if ($only_attached === '1') {
      $all_images_query = $all_images_query . " AND (p.post_parent > 0)";
    }

    if ($only_new === '1') {
      $all_images_query = $all_images_query . " AND (NOT EXISTS(SELECT 1 FROM {$atai_asset_table} WHERE wp_post_id = p.ID))";
    }

    if ($wc_products === '1') {
      $all_images_query = $all_images_query . " AND (EXISTS(SELECT 1 FROM {$wpdb->posts} p2 WHERE p2.ID = p.post_parent and p2.post_type = 'product'))";
    }

    if ($wc_only_featured === '1') {
      $all_images_query = $all_images_query . " AND (EXISTS(SELECT 1 FROM {$wpdb->postmeta} pm2 WHERE pm2.post_id = p.post_parent and pm2.meta_key = '_thumbnail_id' and CAST(pm2.meta_value as UNSIGNED) = p.ID))";
    }

    // Exclude images attached to specific post types
    $excluded_post_types = get_option( 'atai_excluded_post_types' );
    $prepare_args = array();
    if ( ! empty( $excluded_post_types ) ) {
      $post_types = array_map( 'trim', explode( ',', $excluded_post_types ) );
      $post_types_placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
      $all_images_query = $all_images_query . " AND (p.post_parent = 0 OR NOT EXISTS(SELECT 1 FROM {$wpdb->posts} p3 WHERE p3.ID = p.post_parent AND p3.post_type IN ($post_types_placeholders)))";
      $prepare_args = $post_types;
    }

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    if ( ! empty( $prepare_args ) ) {
      $all_images_count = $images_count = (int) $wpdb->get_results( $wpdb->prepare( $all_images_query, $prepare_args ) )[0]->total_images;
    } else {
      $all_images_count = $images_count = (int) $wpdb->get_results( $all_images_query )[0]->total_images;
    }
    $images_missing_alt_text_count = 0;

    // Images without alt text
    $images_without_alt_text_sql = <<<SQL
SELECT COUNT(DISTINCT p.ID) as total_images
FROM {$wpdb->posts} p
  LEFT JOIN {$wpdb->postmeta} pm
    ON (p.ID = pm.post_id AND pm.meta_key = '_wp_attachment_image_alt')
  LEFT JOIN {$wpdb->postmeta} AS mt1 ON (p.ID = mt1.post_id)
WHERE (p.post_mime_type LIKE 'image/%')
  AND (pm.post_id IS NULL OR (mt1.meta_key = '_wp_attachment_image_alt' AND mt1.meta_value = ''))
  AND p.post_type = 'attachment'
  AND p.post_status = 'inherit'
SQL;

    if ($only_attached === '1') {
      $images_without_alt_text_sql = $images_without_alt_text_sql . " AND (p.post_parent > 0)";
    }

    if ($only_new === '1') {
      $images_without_alt_text_sql = $images_without_alt_text_sql . " AND (NOT EXISTS(SELECT 1 FROM {$atai_asset_table} WHERE wp_post_id = p.ID))";
    }

    if ($wc_products === '1') {
      $images_without_alt_text_sql = $images_without_alt_text_sql . " AND (EXISTS(SELECT 1 FROM {$wpdb->posts} p2 WHERE p2.ID = p.post_parent and p2.post_type = 'product'))";
    }

    if ($wc_only_featured === '1') {
      $images_without_alt_text_sql = $images_without_alt_text_sql . " AND (EXISTS(SELECT 1 FROM {$wpdb->postmeta} pm2 WHERE pm2.post_id = p.post_parent and pm2.meta_key = '_thumbnail_id' and CAST(pm2.meta_value as UNSIGNED) = p.ID))";
    }

    // Exclude images attached to specific post types (for missing alt text query)
    if ( ! empty( $excluded_post_types ) ) {
      $images_without_alt_text_sql = $images_without_alt_text_sql . " AND (p.post_parent = 0 OR NOT EXISTS(SELECT 1 FROM {$wpdb->posts} p3 WHERE p3.ID = p.post_parent AND p3.post_type IN ($post_types_placeholders)))";
    }

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
    if ( ! empty( $prepare_args ) ) {
      $images_missing_alt_text_count = (int) $wpdb->get_results( $wpdb->prepare( $images_without_alt_text_sql, $prepare_args ) )[0]->total_images;
    } else {
      $images_missing_alt_text_count = (int) $wpdb->get_results( $images_without_alt_text_sql )[0]->total_images;
    }

    if ( $mode === 'missing' ) {
      $images_count = $images_missing_alt_text_count;
    }
  } elseif ( $action === 'bulk-select-generate' ) {
    $all_images_count = $images_count = count( $selected_images );
  }
?>

<div class="mt-4 mr-5">

  <div class="mb-4">
    <h2 class="mb-4 text-2xl font-bold"><?php esc_html_e( 'Bulk Generate Alt Text', 'alttext-ai' ); ?></h2>

    <?php if ( $action === 'bulk-select-generate' ) : ?>
      <dl class="grid grid-cols-1 gap-8 max-w-5xl sm:grid-cols-2">
        <div class="overflow-hidden py-2 px-4 bg-white rounded-lg shadow sm:p-4">
          <dt class="text-lg font-medium truncate text-primary-700"><?php esc_html_e( 'Selected Images to Update', 'alttext-ai' ); ?></dt>
          <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900"><?php echo esc_html($all_images_count); ?></dd>
        </div>
      </dl>
    <?php else : ?>
      <dl class="grid grid-cols-1 gap-4 max-w-5xl sm:grid-cols-2">
        <div class="overflow-hidden py-2 px-4 bg-white rounded-lg shadow sm:p-4">
          <dt class="text-lg font-medium text-gray-500 truncate"><?php esc_html_e( 'Total Images', 'alttext-ai' ); ?></dt>
          <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900"><?php echo esc_html($all_images_count); ?></dd>
        </div>
        <div class="overflow-hidden py-2 px-4 bg-white rounded-lg shadow sm:p-4">
          <dt class="text-lg font-medium truncate text-primary-700"><?php esc_html_e( 'Images Missing Alt Text', 'alttext-ai' ); ?></dt>
          <dd class="mt-1 text-3xl font-semibold tracking-tight text-gray-900"><?php echo esc_html($images_missing_alt_text_count); ?></dd>
        </div>
      </dl>
    <?php endif; ?>
  </div>

  <?php if ( $cannot_bulk_update ) : ?>
    <div class="py-2 px-4 mb-4 max-w-5xl bg-amber-100 rounded-lg border border-amber-300 sm:p-4">
      <div class="flex items-center">
        <div class="flex-shrink-0">
          <svg class="w-5 h-5 text-amber-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
            <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
          </svg>
        </div>
        <div class="ml-3">
          <p class="text-sm text-amber-700">
            You have no more credits left.
            <?php if ( $this->account && !$this->account['whitelabel'] ) : ?>
              To bulk update your library, please
              <a href="<?php echo esc_url($subscriptions_url); ?>" target="_blank" class="font-medium text-amber-700 underline hover:text-amber-600">
                <?php esc_html_e( 'purchase more credits.', 'alttext-ai' ); ?>
              </a>
              <?php endif; ?>
          </p>
        </div>
      </div>
    </div>
    <?php return; ?>
  <?php else : ?>
    <div class="py-2 px-4 mb-4 max-w-5xl rounded-lg border border-solid sm:p-4 bg-primary-100 border-primary-200 box-border">
      <div class="flex">
        <div class="flex-1 md:flex md:justify-between">
          <p class="text-sm font-medium text-primary-700">
            <?php printf( esc_html__( 'Available credits: %d', 'alttext-ai' ), (int) $this->account['available'] ); ?>
            <?php if ( !$this->account['whitelabel'] ) : ?>
              (
              <a href="<?php echo esc_url($subscriptions_url); ?>" target="_blank" class="text-xs underline whitespace-nowrap text-primary-700 hover:text-primary-600">
                <?php esc_html_e( 'Get more credits', 'alttext-ai' ); ?>
              </a>
              )
            <?php endif; ?>
          </p>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <div id="bulk-generate-form">
    <div class="p-4 mt-2 max-w-5xl rounded-lg border-2 border-gray-400 border-dashed box-border">
      <h3 class="text-lg font-semibold text-gray-700">Keywords</h3>
      <div class="grid grid-cols-1 gap-4 pt-4 sm:grid-cols-2">
        <div>
          <label for="keywords">
            <span class="text-sm leading-6 text-gray-900">[optional] SEO Keywords</span>
            <span class="text-xs text-gray-500">(try to include these in the generated alt text)</span>
          </label>

          <div class="mt-1 sm:max-w-md">
            <input data-bulk-generate-keywords type="text" size="60" maxlength="512" name="keywords" id="bulk-generate-keywords" class="block py-1.5 w-full text-gray-900 rounded-md border-0 ring-1 ring-inset ring-gray-300 shadow-sm sm:text-sm sm:leading-6 focus:ring-2 focus:ring-inset placeholder:text-gray-400 focus:ring-primary-600">
          </div>
          <p class="mt-1 text-xs text-gray-500">Separate with commas. Maximum of 6 keywords or phrases.</p>
        </div>
        <div>
          <label for="negative-keywords">
            <span class="text-sm leading-6 text-gray-900">[optional] Negative keywords</span>
            <span class="text-xs text-gray-500">(do not include these in the generated alt text)</span>
          </label>
          <div class="mt-1 sm:max-w-md">
            <input data-bulk-generate-negative-keywords type="text" size="60" maxlength="512" name="negative-keywords" id="bulk-generate-negative-keywords" class="block py-1.5 w-full text-gray-900 rounded-md border-0 ring-1 ring-inset ring-gray-300 shadow-sm sm:text-sm sm:leading-6 focus:ring-2 focus:ring-inset placeholder:text-gray-400 focus:ring-primary-600">
          </div>
          <p class="mt-1 text-xs text-gray-500">Separate with commas. Maximum of 6 keywords or phrases.</p>
        </div>
      </div>
    </div>

    <div class="mt-4">
      <?php if ($images_count === 0) : ?>
      <button
        type="button"
        disabled
        class="py-2.5 px-3.5 text-sm font-semibold text-white rounded-md border-none shadow-sm appearance-none pointer-events-none bg-primary-400 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-600 disabled"
      >
        Generate Alt Text
      </button>

      <?php else : ?>
      <button
        data-bulk-generate-start
        type="button"
        class="bg-primary-600 hover:bg-primary-700 focus:outline-primary-400 mt-4 box-border inline-flex cursor-pointer appearance-none items-center gap-2 rounded-md border border-solid border-transparent px-5 py-2.5 text-sm font-semibold text-white no-underline shadow-sm transition-colors duration-75 ease-in-out hover:text-white focus:!text-white focus:outline-offset-2 active:border-gray-700 active:!text-white active:!outline-none disabled:focus:outline-transparent disabled:active:border-transparent"
      >
        <?php
          echo esc_html( sprintf( _n( 'Generate Alt Text: %d image', 'Generate Alt Text: %d images', $images_count, 'alttext-ai' ), $images_count ) );
        ?>
      </button>
      <?php endif; ?>
    </div>

    <?php if ( $action === 'normal' ) : ?>
      <fieldset class="mt-4">
        <legend class="sr-only">Bulk Generation Modes</legend>
        <div class="space-y-2">
          <div class="flex relative items-start">
            <div class="flex items-center h-6">
              <input
                type="checkbox"
                id="atai_bulk_generate_all"
                data-bulk-generate-mode-all
                data-url="<?php echo esc_url($mode_url); ?>"
                class="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-600"
                <?php if ( isset( $_GET['atai_mode'] ) && $_GET['atai_mode'] === 'all' ) echo esc_html('checked'); ?>
              >
            </div>
            <div class="ml-2 -mt-1 text-xs leading-6">
              <label for="atai_bulk_generate_all" class="text-gray-900"><?php esc_html_e( 'Include images that already have alt text (overwrite existing alt text).', 'alttext-ai' ); ?></label>
            </div>
          </div>
          <div class="flex relative items-start">
            <div class="flex items-center h-6">
              <input
                type="checkbox"
                id="atai_bulk_generate_only_attached"
                data-bulk-generate-only-attached
                data-url="<?php echo esc_url($only_attached_url); ?>"
                class="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-600"
                <?php if ( $only_attached === '1' ) echo 'checked'; ?>
              >
            </div>
            <div class="ml-2 -mt-1 text-xs leading-6">
              <label for="atai_bulk_generate_only_attached" class="text-gray-900"><?php esc_html_e( 'Only process images that are attached to posts.', 'alttext-ai' ); ?></label>
            </div>
          </div>
          <div class="flex relative items-start">
            <div class="flex items-center h-6">
              <input
                type="checkbox"
                id="atai_bulk_generate_only_new"
                data-bulk-generate-only-new
                data-url="<?php echo esc_url($only_new_url); ?>"
                class="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-600"
                <?php if ( $only_new === '1' ) echo 'checked'; ?>
              >
            </div>
            <div class="ml-2 -mt-1 text-xs leading-6">
              <label for="atai_bulk_generate_only_new" class="text-gray-900"><?php esc_html_e( 'Skip images already processed by AltText.ai', 'alttext-ai' ); ?></label>
            </div>
          </div>
        </div>
      </fieldset>

      <?php if ( ATAI_Utility::has_woocommerce() ) : ?>
      <fieldset>
        <h4 class="mt-4 mb-2 font-semibold">WooCommerce</h4>
        <div class="space-y-2">
          <div class="flex relative items-start">
            <div class="flex items-center h-6">
              <input
                type="checkbox"
                id="atai_bulk_generate_wc_products"
                data-bulk-generate-wc-products
                data-url="<?php echo esc_url($wc_products_url); ?>"
                class="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-600"
                <?php if ( $wc_products === '1' ) echo 'checked'; ?>
              >
            </div>
            <div class="ml-2 -mt-1 text-xs leading-6">
              <label for="atai_bulk_generate_wc_products" class="text-gray-900"><?php esc_html_e( 'Only process WooCommerce product images.', 'alttext-ai' ); ?></label>
            </div>
          </div>
          <div class="flex relative items-start">
            <div class="flex items-center h-6">
              <input
                type="checkbox"
                id="atai_bulk_generate_wc_only_featured"
                data-bulk-generate-wc-only-featured
                data-url="<?php echo esc_url($wc_only_featured_url); ?>"
                class="w-4 h-4 rounded border-gray-300 text-primary-600 focus:ring-primary-600"
                <?php if ( $wc_only_featured === '1' ) echo 'checked'; ?>
              >
            </div>
            <div class="ml-2 -mt-1 text-xs leading-6">
              <label for="atai_bulk_generate_wc_only_featured" class="text-gray-900"><?php esc_html_e( 'For each product, only process the main image, and skip gallery images.', 'alttext-ai' ); ?></label>
            </div>
          </div>
        </div>
      </fieldset>
      <?php endif; ?>
    <?php endif; ?>
  </div> <!-- bulk generate form -->

  <div data-bulk-generate-progress-wrapper class="hidden p-6 mt-4 space-y-4 border-4 border">
    <h3 data-bulk-generate-progress-heading class="text-xl font-semibold">
      <?php esc_html_e( 'Update in progress (please keep this page open until the update completes)', 'alttext-ai' ); ?>
    </h3>

    <div data-bulk-generate-progress-bar-wrapper>
      <div class="flex justify-between mb-1">
        <span class="text-base font-medium text-primary-700">Progress</span>
        <span data-bulk-generate-progress-percent class="text-base font-medium text-primary-700">0%</span>
      </div>
      <div class="w-full h-4 bg-gray-200 rounded-full">
        <div
          data-bulk-generate-progress-bar
          data-max="<?php echo esc_html($images_count); ?>"
          data-current="0"
          data-successful="0"
          class="h-4 rounded-full bg-primary-600 transition-width ease-in-out duration-700" style="width: 0.5%"
        ></div>
      </div>
    </div>

    <p class="text-lg">
      <span data-bulk-generate-progress-current>0</span> / <?php echo esc_html($images_count); ?> images processed (<span data-bulk-generate-progress-successful>0</span> successful)
    </p>
    <p class="text-sm">
      Last image ID: <span data-bulk-generate-last-post-id class="ml-2"></span>
    </p>

    <p>
      <button
        data-bulk-generate-cancel
        class="bg-gray-600 hover:bg-gray-700 focus:outline-primary-400 mt-4 box-border inline-flex cursor-pointer appearance-none items-center gap-2 rounded-md border border-solid border-transparent px-5 py-2.5 text-sm font-semibold text-white no-underline shadow-sm transition-colors duration-75 ease-in-out hover:text-white focus:!text-white focus:outline-offset-2 active:border-gray-700 active:!text-white active:!outline-none disabled:focus:outline-transparent disabled:active:border-transparent"
        onclick="window.location = '<?php echo esc_url(admin_url( 'admin.php?page=atai-bulk-generate' )); ?>';"
      >
        <?php esc_html_e( 'Cancel', 'alttext-ai' ); ?>
      </button>
    </p>

    <p>
      <button
        data-bulk-generate-finished
        class="hidden bg-primary-600 hover:bg-primary-700 focus:outline-primary-400 mt-4 box-border inline-flex cursor-pointer appearance-none items-center gap-2 rounded-md border border-solid border-transparent px-5 py-2.5 text-sm font-semibold text-white no-underline shadow-sm transition-colors duration-75 ease-in-out hover:text-white focus:!text-white focus:outline-offset-2 active:border-gray-700 active:!text-white active:!outline-none disabled:focus:outline-transparent disabled:active:border-transparent"
        onclick="window.location = window.atai.redirectUrl;"
      >
        <?php esc_html_e( $action === 'bulk-select-generate' ? 'Back to Media Library' : 'Done', 'alttext-ai' ); ?>
      </button>
    </p>
  </div>
</div>
