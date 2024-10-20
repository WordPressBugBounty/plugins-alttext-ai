<?php

/**
 * This file is used to markup the history page of the plugin.
 *
 * @link       https://alttext.ai
 * @since      1.4.1
 *
 * @package    ATAI
 * @subpackage ATAI/admin/partials
 */
?>

<?php if (!defined('WPINC')) die; ?>

<?php
  // Fetch all images that the plugin has updated
  global $wpdb;
  $atai_asset_table = $wpdb->prefix . ATAI_DB_ASSET_TABLE;
  $paged = intval( $_GET['paged'] ?? 1 );
  $offset = ($paged - 1) * ATAI_HISTORY_ITEMS_PER_PAGE;
  $pagination_start = floor(($paged - 1)  / ATAI_HISTORY_PAGE_SELECTORS) * ATAI_HISTORY_PAGE_SELECTORS + 1;
  $pagination_end =  $pagination_start + ATAI_HISTORY_PAGE_SELECTORS - 1;

  // Get the total number of assets
  $total_assets = $wpdb->get_var("SELECT COUNT(DISTINCT wp_post_id) FROM {$atai_asset_table}"); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
  $total_pages = ceil($total_assets / ATAI_HISTORY_ITEMS_PER_PAGE);
  $pagination_end = min($pagination_end, $total_pages);

  // Get the assets for the current page
  // phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared
  $atai_assets = $wpdb->get_results( $wpdb->prepare( <<<SQL
    SELECT wp_post_id, MAX(updated_at) as updated_at
    FROM {$atai_asset_table}
    GROUP BY 1
    ORDER BY updated_at DESC
    LIMIT %d OFFSET %d
SQL
    , ATAI_HISTORY_ITEMS_PER_PAGE, $offset)
  );
  // phpcs:enable
?>

<div class="mr-5">
  <h2 class="text-2xl font-bold"><?php esc_html_e('Alt Text Processing History', 'alttext-ai'); ?></h2>
  <p class="mt-2">
    Below is a list of all images from your Media Library which have been processed by AltText.ai
  </p>

  <?php if ( $total_assets == 0 ) : ?>
    <div class="mt-12">
      <svg class="mx-auto h-12 w-12 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
      </svg>
      <h3 class="mt-2 text-lg font-semibold text-gray-900">No media files have been processed yet.</h3>
      <p class="mt-1 text-sm text-gray-500">You can use our
        <a href="<?php echo esc_url(admin_url( 'admin.php?page=atai-bulk-generate' )) ?>" class="font-medium text-indigo-600 hover:text-indigo-500">bulk generate tool</a>
        to get started on your existing images.
      </p>
    </div>
  <?php else : ?>
    <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
      <table class="w-full text-sm text-left rtl:text-right text-gray-500">
        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
        <tr>
          <th scope="col" class="px-2 py-3">
            Media ID
          </th>
          <th scope="col" class="px-6 py-3">
            Image
          </th>
          <th scope="col" class="px-6 py-3">
            Alt Text
          </th>
          <th scope="col" class="px-6 py-3">
            Processed On
          </th>
        </tr>
        </thead>
        <tbody>
          <?php
            foreach( $atai_assets as $atai_asset ) :
              $attachment_id = $atai_asset->wp_post_id;
              $attachment_url = wp_get_attachment_url( $attachment_id );
              $alt_text = get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );
              $updated_at = $atai_asset->updated_at;
          ?>
            <tr class="bg-white border-b hover:bg-gray-50">
              <td class="px-4 py-2 font-semibold text-gray-900">
                <a href="<?php echo esc_url(get_edit_post_link( $attachment_id )); ?>">
                  <?php echo esc_html($attachment_id); ?>
                </a>
              </td>
              <td class="p-4">
                <a href="<?php echo esc_url(get_edit_post_link( $attachment_id )); ?>">
                  <img src="<?php echo esc_url($attachment_url); ?>" alt="<?php echo esc_html($alt_text); ?>" class="w-16 md:w-32 max-w-full max-h-full">
                </a>
              </td>
              <td class="px-6 py-4">
                <div class="sm:flex sm:items-center sm:gap-x-3">
                  <textarea id="edit-history-input-<?php echo esc_html($attachment_id); ?>" rows="4" maxlength="1024" class="block w-full rounded-md border-1 py-1.5 text-gray-700 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-indigo-600 sm:text-sm sm:leading-6 resize-none"><?php echo esc_html($alt_text); ?></textarea>
                  <div class="flex flex-col">
                    <button type="button" class="rounded bg-indigo-50 border-none cursor-pointer mt-1 sm:mt-0 px-2 py-1 text-sm font-semibold text-indigo-500 shadow-sm hover:bg-indigo-100 disabled:cursor-not-allowed disabled:opacity-30" data-attachment-id="<?php echo esc_html($attachment_id); ?>" data-edit-history-trigger>Update</button>
                    <span id="edit-history-success-<?php echo esc_html($attachment_id); ?>" class="hidden absolute mt-8 font-semibold text-lime-600">Updated!</span>
                  </div>
                </div>
              </td>
              <td class="px-4 py-4 whitespace-nowrap">
                <?php echo esc_html($updated_at); ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>

  <?php if ( $total_pages > 1 ) : ?>
    <nav aria-label="Page navigation">
      <ul class="flex items-center -space-x-px h-10 text-base">
      <?php if ($pagination_start > ATAI_HISTORY_PAGE_SELECTORS): ?>
        <li>
          <a href="<?php echo esc_url(add_query_arg('paged', $pagination_start - 1)); ?>"
             class="flex items-center justify-center px-4 h-10 ms-0 leading-tight text-gray-500 bg-white border border-e-0 border-gray-300 rounded-s-lg hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">
            <span class="sr-only">Previous</span>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
              <path fill-rule="evenodd" d="M4.72 9.47a.75.75 0 0 0 0 1.06l4.25 4.25a.75.75 0 1 0 1.06-1.06L6.31 10l3.72-3.72a.75.75 0 1 0-1.06-1.06L4.72 9.47Zm9.25-4.25L9.72 9.47a.75.75 0 0 0 0 1.06l4.25 4.25a.75.75 0 1 0 1.06-1.06L11.31 10l3.72-3.72a.75.75 0 0 0-1.06-1.06Z" clip-rule="evenodd" />
            </svg>
          </a>
        </li>
      <?php endif; ?>

      <?php for ($i = $pagination_start; $i <= $pagination_end; $i++): ?>
      <?php
        $page_class = ($i == $paged) ? "z-10 text-blue-600 border border-blue-300 bg-blue-50 hover:bg-blue-100 hover:text-blue-700" : "text-gray-500 bg-white border border-gray-300 hover:bg-gray-100 hover:text-gray-700";
      ?>
        <li>
          <a href="<?php echo esc_url(add_query_arg('paged', $i)); ?>"
            class="flex items-center justify-center px-4 h-10 leading-tight no-underline <?php echo esc_html($page_class); ?>">
            <?php echo esc_html($i); ?>
          </a>
        </li>
      <?php endfor; ?>

      <?php if ($pagination_end < $total_pages): ?>
        <li>
          <a href="<?php echo esc_url(add_query_arg('paged', $pagination_end + 1)); ?>"
             class="flex items-center justify-center px-4 h-10 leading-tight text-gray-500 bg-white border border-gray-300 rounded-e-lg hover:bg-gray-100 hover:text-gray-700 dark:bg-gray-800 dark:border-gray-700 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-white">
            <span class="sr-only">Next</span>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5">
              <path fill-rule="evenodd" d="M15.28 9.47a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 1 1-1.06-1.06L13.69 10 9.97 6.28a.75.75 0 0 1 1.06-1.06l4.25 4.25ZM6.03 5.22l4.25 4.25a.75.75 0 0 1 0 1.06l-4.25 4.25a.75.75 0 0 1-1.06-1.06L8.69 10 4.97 6.28a.75.75 0 0 1 1.06-1.06Z" clip-rule="evenodd" />
            </svg>
          </a>
        </li>
      <?php endif; ?>
      </ul>
    </nav>
  <?php endif; ?>
</div>
