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
WHERE (p.post_mime_type LIKE %s)
  AND p.post_type = %s
  AND p.post_status = %s
SQL;

    if ($only_attached === '1') {
      $all_images_query = $all_images_query . " AND (p.post_parent > 0)";
    }

    if ($only_new === '1') {
      $all_images_query = $all_images_query . " AND (NOT EXISTS(SELECT 1 FROM {$atai_asset_table} WHERE wp_post_id = p.ID))"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    $like_image = $wpdb->esc_like('image/') . '%';
    $prepare_args = array( $like_image, 'attachment', 'inherit' ); // Base values for the placeholders

    if ($wc_products === '1') {
      $all_images_query = $all_images_query . " AND (EXISTS(SELECT 1 FROM {$wpdb->posts} p2 WHERE p2.ID = p.post_parent and p2.post_type = %s))";
      $prepare_args[] = 'product';
    }

    if ($wc_only_featured === '1') {
      $all_images_query = $all_images_query . " AND (EXISTS(SELECT 1 FROM {$wpdb->postmeta} pm2 WHERE pm2.post_id = p.post_parent and pm2.meta_key = %s and CAST(pm2.meta_value as UNSIGNED) = p.ID))";
      $prepare_args[] = '_thumbnail_id';
    }

    // Exclude images attached to specific post types
    $excluded_post_types = get_option( 'atai_excluded_post_types' );
    if ( ! empty( $excluded_post_types ) ) {
      $post_types = array_map( 'trim', explode( ',', $excluded_post_types ) );
      $post_types_placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
      $all_images_query = $all_images_query . " AND (p.post_parent = 0 OR NOT EXISTS(SELECT 1 FROM {$wpdb->posts} p3 WHERE p3.ID = p.post_parent AND p3.post_type IN ($post_types_placeholders)))";
      $prepare_args = array_merge( $prepare_args, $post_types );
    }

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is properly prepared, table names are from core
    $all_images_count = $images_count = (int) $wpdb->get_results( $wpdb->prepare( $all_images_query, $prepare_args ) )[0]->total_images;
    $images_missing_alt_text_count = 0;

    // Images without alt text
    $images_without_alt_text_sql = <<<SQL
SELECT COUNT(DISTINCT p.ID) as total_images
FROM {$wpdb->posts} p
  LEFT JOIN {$wpdb->postmeta} pm
    ON (p.ID = pm.post_id AND pm.meta_key = %s)
WHERE (p.post_mime_type LIKE %s)
  AND p.post_type = %s
  AND p.post_status = %s
  AND (pm.post_id IS NULL OR TRIM(COALESCE(pm.meta_value, '')) = '')
SQL;

    if ($only_attached === '1') {
      $images_without_alt_text_sql = $images_without_alt_text_sql . " AND (p.post_parent > 0)";
    }

    if ($only_new === '1') {
      $images_without_alt_text_sql = $images_without_alt_text_sql . " AND (NOT EXISTS(SELECT 1 FROM {$atai_asset_table} WHERE wp_post_id = p.ID))"; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
    }

    // Apply the same exclusions to the missing alt text query
    $like_image = $wpdb->esc_like('image/') . '%';
    $alt_prepare_args = array( '_wp_attachment_image_alt', $like_image, 'attachment', 'inherit' ); // Base values for the placeholders

    if ($wc_products === '1') {
      $images_without_alt_text_sql = $images_without_alt_text_sql . " AND (EXISTS(SELECT 1 FROM {$wpdb->posts} p2 WHERE p2.ID = p.post_parent and p2.post_type = %s))";
      $alt_prepare_args[] = 'product';
    }

    if ($wc_only_featured === '1') {
      $images_without_alt_text_sql = $images_without_alt_text_sql . " AND (EXISTS(SELECT 1 FROM {$wpdb->postmeta} pm2 WHERE pm2.post_id = p.post_parent and pm2.meta_key = %s and CAST(pm2.meta_value as UNSIGNED) = p.ID))";
      $alt_prepare_args[] = '_thumbnail_id';
    }
    if ( ! empty( $excluded_post_types ) ) {
      $post_types = array_map( 'trim', explode( ',', $excluded_post_types ) );
      $post_types_placeholders = implode( ',', array_fill( 0, count( $post_types ), '%s' ) );
      $images_without_alt_text_sql = $images_without_alt_text_sql . " AND (p.post_parent = 0 OR NOT EXISTS(SELECT 1 FROM {$wpdb->posts} p3 WHERE p3.ID = p.post_parent AND p3.post_type IN ($post_types_placeholders)))";
      $alt_prepare_args = array_merge( $alt_prepare_args, $post_types );
    }

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query is properly prepared, table names are from core
    $images_missing_alt_text_count = (int) $wpdb->get_results( $wpdb->prepare( $images_without_alt_text_sql, $alt_prepare_args ) )[0]->total_images;

    if ( $mode === 'missing' ) {
      $images_count = $images_missing_alt_text_count;
    }
  } elseif ( $action === 'bulk-select-generate' ) {
    $all_images_count = $images_count = count( $selected_images );
  }
?>

<div class="wrap max-w-6xl">
  <div>
    <!-- Hero Section -->
    <h1 class="!text-2xl !font-bold !text-gray-900"><?php esc_html_e( 'Bulk Generate Alt Text', 'alttext-ai' ); ?></h1>
    <p class="!text-gray-700 !text-base !font-medium ">
      <?php esc_html_e( 'Automatically generate alt text for multiple images at once. Improve accessibility and SEO across your entire media library.', 'alttext-ai' ); ?>
    </p>

  <!-- Stats Cards -->
  <div class="mb-6">
    <?php if ( $action === 'bulk-select-generate' ) : ?>
      <dl class="mx-auto grid grid-cols-1 gap-px bg-gray-900/5 border p-px rounded-lg sm:grid-cols-2 lg:grid-cols-4">
        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-2 rounded-lg bg-white px-4 py-6 sm:px-6 xl:px-8">
          <dt class="text-sm/6 font-medium text-gray-500"><?php esc_html_e( 'Selected Images', 'alttext-ai' ); ?></dt>
          <dd class="text-xs font-medium text-gray-700">Ready</dd>
          <dd class="w-full flex-none text-3xl/10 font-medium tracking-tight text-gray-900 ml-0"><?php echo esc_html(number_format($all_images_count)); ?></dd>
        </div>
      </dl>
    <?php else : ?>
      <dl class="mx-auto grid grid-cols-1 gap-px bg-gray-900/5 border p-px rounded-lg sm:grid-cols-2 lg:grid-cols-4">
        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-2 rounded-l-lg bg-white px-4 py-6 sm:px-6 xl:px-8">
          <dt class="text-sm/6 font-medium text-gray-500"><?php esc_html_e( 'Total Images', 'alttext-ai' ); ?></dt>
          <dd class="text-xs font-medium text-gray-700">Library</dd>
          <dd class="w-full flex-none text-3xl/10 font-medium tracking-tight text-gray-900 ml-0"><?php echo esc_html(number_format($all_images_count)); ?></dd>
        </div>
        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-2 bg-white px-4 py-6 sm:px-6 xl:px-8">
          <dt class="text-sm/6 font-medium text-gray-500"><?php esc_html_e( 'Missing Alt Text', 'alttext-ai' ); ?></dt>
          <dd class="text-xs font-medium text-rose-600"><?php echo esc_html(number_format(($images_missing_alt_text_count / max($all_images_count, 1)) * 100, 1)); ?>%</dd>
          <dd class="w-full flex-none text-3xl/10 font-medium tracking-tight text-gray-900 ml-0"><?php echo esc_html(number_format($images_missing_alt_text_count)); ?></dd>
        </div>
        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-2 bg-white px-4 py-6 sm:px-6 xl:px-8">
          <dt class="text-sm/6 font-medium text-gray-500"><?php esc_html_e( 'With Alt Text', 'alttext-ai' ); ?></dt>
          <dd class="text-xs font-medium text-gray-700"><?php echo esc_html(number_format((($all_images_count - $images_missing_alt_text_count) / max($all_images_count, 1)) * 100, 1)); ?>%</dd>
          <dd class="w-full flex-none text-3xl/10 font-medium tracking-tight text-gray-900 ml-0"><?php echo esc_html(number_format($all_images_count - $images_missing_alt_text_count)); ?></dd>
        </div>
        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-2 bg-white rounded-r-lg px-4 py-6 sm:px-6 xl:px-8">
          <dt class="text-sm/6 font-medium text-gray-500"><?php esc_html_e( 'Available Credits', 'alttext-ai' ); ?></dt>
          <dd class="text-xs font-medium text-<?php echo ($this->account && $this->account['available'] > 100) ? 'emerald-600' : 'rose-600'; ?>">
            <?php echo ($this->account && $this->account['available'] > 100) ? '' : 'Low'; ?>
          </dd>
          <dd class="w-full flex-none text-3xl/10 font-medium tracking-tight text-gray-900 ml-0">
            <?php echo $this->account ? esc_html(number_format((int) $this->account['available'])) : '0'; ?>
          </dd>
        </div>
      </dl>
    <?php endif; ?>
  </div>
  </div> <!-- Close the hero/stats container -->

  <?php if ( $cannot_bulk_update ) : ?>
    <div class=" border bg-amber-900/5 p-px rounded-lg  mb-8 ">
      <div class=" bg-amber-50 flex rounded-lg items-center justify-between px-4 py-6  sm:px-6 xl:px-8">
        <div class="flex items-center">
          <div class="flex-shrink-0">
            <svg class="w-5 h-5 text-amber-400" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
              <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
            </svg>
          </div>
          <div class="ml-3">
            <p class="text-sm text-amber-700">
              <?php esc_html_e( 'You have no more credits left. To bulk update your library, you need to purchase more credits.', 'alttext-ai' ); ?>
            </p>
          </div>
        </div>
        <?php if ( $this->account && !$this->account['whitelabel'] ) : ?>
        <div class="ml-4">
          <a 
            href="<?php echo esc_url($subscriptions_url); ?>" 
            target="_blank" 
            class="atai-button blue no-underline"
          >
            <?php esc_html_e( 'Purchase Credits', 'alttext-ai' ); ?>
          </a>
        </div>
        <?php endif; ?>
      </div>
    </div>
    <?php return; ?>
  <?php endif; ?>

  <div id="bulk-generate-form" class="space-y-6">
    <div class="border bg-gray-900/5 p-px rounded-lg mb-6">
      <div class="overflow-hidden rounded-lg bg-white">
        <div class="border-b border-gray-200 bg-white px-4 pt-5 pb-3 sm:px-6">
          <h3 class="text-base font-semibold text-gray-900 my-0">Keywords</h3>
        </div>
        <div class="px-4 py-4 sm:px-6">
          <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
        <div>
          <label for="bulk-generate-keywords" class="block text-sm/6 font-medium text-gray-900">SEO Keywords <span class="text-gray-500 font-normal">(optional)</span></label>
          <div class="mt-2">
            <input 
              data-bulk-generate-keywords 
              type="text" 
              size="60" 
              maxlength="512" 
              name="keywords" 
              id="bulk-generate-keywords" 
              placeholder="Enter keywords separated by commas"
              aria-describedby="keywords-description"
              class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-primary-600 sm:text-sm/6" 
            />
          </div>
          <p id="keywords-description" class="mt-2 text-sm text-gray-500">Try to include these in the generated alt text. Maximum of 6 keywords or phrases</p>
        </div>
        <div>
          <label for="bulk-generate-negative-keywords" class="block text-sm/6 font-medium text-gray-900">Negative Keywords <span class="text-gray-500 font-normal">(optional)</span></label>
          <div class="mt-2">
            <input 
              data-bulk-generate-negative-keywords 
              type="text" 
              size="60" 
              maxlength="512" 
              name="negative-keywords" 
              id="bulk-generate-negative-keywords" 
              placeholder="Enter negative keywords separated by commas"
              aria-describedby="negative-keywords-description"
              class="block w-full rounded-md bg-white px-3 py-1.5 text-base text-gray-900 outline outline-1 -outline-offset-1 outline-gray-300 placeholder:text-gray-400 focus:outline focus:outline-2 focus:-outline-offset-2 focus:outline-primary-600 sm:text-sm/6" 
            />
          </div>
          <p id="negative-keywords-description" class="mt-2 text-sm text-gray-500">Do not include these in the generated alt text. Maximum of 6 keywords or phrases</p>
        </div>
          </div>
        </div>
      </div>
    </div>

    <?php if ( $action === 'normal' ) : ?>
      <div class="border bg-gray-900/5 p-px rounded-lg mb-6">
        <div class="overflow-hidden rounded-lg bg-white">
          <div class="border-b border-gray-200 bg-white px-4 pt-5 pb-3 sm:px-6">
            <h3 class="text-base font-semibold text-gray-900 my-0">Processing Options</h3>
          </div>
          <div class="px-4 py-4 sm:px-6">
            <div class="space-y-5">
          <div class="flex gap-3">
            <div class="flex h-6 shrink-0 items-center">
              <input
                type="checkbox"
                id="atai_bulk_generate_all"
                data-bulk-generate-mode-all
                data-url="<?php echo esc_url($mode_url); ?>"
                <?php if ( isset( $_GET['atai_mode'] ) && $_GET['atai_mode'] === 'all' ) echo esc_html('checked'); ?>
              />
            </div>
            <div class="text-sm/6 -mt-0.5">
              <label for="atai_bulk_generate_all" class="font-medium text-gray-900 align-top"><?php esc_html_e( 'Overwrite existing alt text', 'alttext-ai' ); ?></label>
              <p id="atai_bulk_generate_all-description" class="text-gray-500 my-0"><?php esc_html_e( 'Include images that already have alt text and replace their existing alt text.', 'alttext-ai' ); ?></p>
            </div>
          </div>
          <div class="flex gap-3">
            <div class="flex h-6 shrink-0 items-center">
              <input
                type="checkbox"
                id="atai_bulk_generate_only_attached"
                data-bulk-generate-only-attached
                data-url="<?php echo esc_url($only_attached_url); ?>"
                <?php if ( $only_attached === '1' ) echo 'checked'; ?>
              />
            </div>
            <div class="text-sm/6 -mt-0.5">
              <label for="atai_bulk_generate_only_attached" class="font-medium text-gray-900 align-top"><?php esc_html_e( 'Only attached images', 'alttext-ai' ); ?></label>
              <p class="text-gray-500 my-0"><?php esc_html_e( 'Only process images that are attached to posts or pages.', 'alttext-ai' ); ?></p>
            </div>
          </div>
          <div class="flex gap-3">
            <div class="flex h-6 shrink-0 items-center">
              <input
                type="checkbox"
                id="atai_bulk_generate_only_new"
                data-bulk-generate-only-new
                data-url="<?php echo esc_url($only_new_url); ?>"
                <?php if ( $only_new === '1' ) echo 'checked'; ?>
              />
            </div>
            <div class="text-sm/6 -mt-0.5">
              <label for="atai_bulk_generate_only_new" class="font-medium text-gray-900 align-top"><?php esc_html_e( 'Skip previously processed', 'alttext-ai' ); ?></label>
              <p class="text-gray-500 my-0"><?php esc_html_e( 'Skip images that have already been processed by AltText.ai', 'alttext-ai' ); ?></p>
            </div>
          </div>
          <?php if ( ATAI_Utility::has_woocommerce() ) : ?>
          <div class="">
            <div class="border-b border-gray-200 bg-white px-0 pb-3">
              <h3 class="text-base font-semibold text-gray-900 my-0">WooCommerce Options</h3>
            </div>
            <div class="px-0 py-4 space-y-5">
              <div class="flex gap-3">
                <div class="flex h-6 shrink-0 items-center">
                  <input
                    type="checkbox"
                    id="atai_bulk_generate_wc_products"
                    data-bulk-generate-wc-products
                    data-url="<?php echo esc_url($wc_products_url); ?>"
                    <?php if ( $wc_products === '1' ) echo 'checked'; ?>
                  />
                </div>
                <div class="text-sm/6 -mt-0.5">
                  <label for="atai_bulk_generate_wc_products" class="font-medium text-gray-900 align-top"><?php esc_html_e( 'Only process WooCommerce product images.', 'alttext-ai' ); ?></label>
                </div>
              </div>
              <div class="flex gap-3">
                <div class="flex h-6 shrink-0 items-center">
                  <input
                    type="checkbox"
                    id="atai_bulk_generate_wc_only_featured"
                    data-bulk-generate-wc-only-featured
                    data-url="<?php echo esc_url($wc_only_featured_url); ?>"
                    <?php if ( $wc_only_featured === '1' ) echo 'checked'; ?>
                  />
                </div>
                <div class="text-sm/6 -mt-0.5">
                  <label for="atai_bulk_generate_wc_only_featured" class="font-medium text-gray-900 align-top"><?php esc_html_e( 'For each product, only process the main image, and skip gallery images.', 'alttext-ai' ); ?></label>
                </div>
              </div>
            </div>
          </div>
          <?php endif; ?>
            </div>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <div class="flex justify-start mt-6 gap-3">
      <button
        data-bulk-generate-start
        type="button"
        class="atai-button <?php echo $images_count === 0 ? '' : 'blue'; ?>"
        <?php echo $images_count === 0 ? 'disabled' : ''; ?>
        style="<?php echo $images_count === 0 ? 'background-color: rgb(156 163 175); color: white; border-color: transparent;' : ''; ?>"
      >
        <?php
          if ($images_count === 0) {
            echo esc_html__('Generate Alt Text', 'alttext-ai');
          } else {
            echo esc_html( sprintf( _n( 'Generate Alt Text for %d Image', 'Generate Alt Text for %d Images', $images_count, 'alttext-ai' ), $images_count ) );
          }
        ?>
      </button>
      <button
        id="atai-static-start-over-button"
        type="button"
        class="atai-button black"
        style="display: none;"
      >
        <?php esc_html_e('Start Over', 'alttext-ai'); ?>
      </button>
    </div>



  <div data-bulk-generate-progress-wrapper style="display: none;" class="border bg-gray-900/5 p-px rounded-lg mb-6">
    <div class="overflow-hidden rounded-lg bg-white">
      <div class="border-b border-gray-200 bg-white px-4 pt-5 pb-0 sm:px-6">
        <h3 data-bulk-generate-progress-heading aria-live="polite" role="status" class="text-base font-semibold text-gray-900 mt-0 mb-4">
          <?php esc_html_e( 'Processing Images', 'alttext-ai' ); ?>
        </h3>
        <p data-bulk-generate-progress-subtitle class="text-sm text-gray-700 my-0 group data-[skipped]:bg-amber-900/15 data-[skipped]:p-px data-[skipped]:rounded-lg"><span class="group-data-[skipped]:bg-amber-50 group-data-[skipped]:rounded-lg group-data-[skipped]:py-2 group-data-[skipped]:px-3 group-data-[skipped]:block">Please keep this page open until the update completes</span></p>
      </div>
      <div class="px-4 py-4 sm:px-6 grid gap-y-4">
        <div data-bulk-generate-progress-bar-wrapper class="space-y-4">
          <div class="flex justify-between items-center">
            <span class="text-base font-semibold text-gray-700">Progress</span>
            <span data-bulk-generate-progress-percent class="text-base font-semibold text-gray-700">0%</span>
          </div>
          <div class="w-full h-4 bg-gray-200 overflow-hidden rounded-full shadow-inner">
            <div
              data-bulk-generate-progress-bar
              data-max="<?php echo esc_html($images_count); ?>"
              data-current="0"
              data-successful="0"
              class="h-4 rounded-full bg-primary-600 shadow-sm transition-all ease-in-out duration-700" style="width: 0.5%"
            ></div>
          </div>
        </div>

        <div class=" rounded-lg bg-gray-900/15 p-px space-y-2">
          <div class="p-4 bg-gray-50 rounded-lg">
            <p class="text-base  mt-0 font-medium text-gray-900 mb-0">
              <span data-bulk-generate-progress-current>0</span> / <span data-bulk-generate-progress-max><?php echo esc_html($images_count); ?></span> images processed 
              (<span data-bulk-generate-progress-successful class="text-emerald-600 font-medium">0</span> successful, 
              <span data-bulk-generate-progress-skipped class="text-gray-600 font-medium">0</span> skipped)
            </p>
            <p class="text-sm text-gray-500 mb-0">
              Last image ID: <span data-bulk-generate-last-post-id class="font-mono bg-white px-1.5 py-0.5 rounded text-xs border"></span>
            </p>
          </div>
        </div>

        <div class="flex justify-start gap-3">
          <button
            data-bulk-generate-cancel
            class="atai-button black"
            onclick="window.location = '<?php echo esc_url(admin_url( 'admin.php?page=atai-bulk-generate' )); ?>';"
          >
            <?php esc_html_e( 'Cancel', 'alttext-ai' ); ?>
          </button>
          <button
            data-bulk-generate-finished
            style="display: none;"
            class="atai-button blue"
            onclick="window.location = '<?php echo esc_url(admin_url( 'admin.php?page=atai-bulk-generate' )); ?>';"
          >
            <?php esc_html_e( 'View Summary', 'alttext-ai' ); ?>
          </button>
        </div>
      </div>
    </div>
  </div>
  <div class="clear"></div>
</div>
