<?php
/**
 * This file is used to markup the CSV import page of the plugin.
 *
 * @link       https://alttext.ai
 * @since      1.1.0
 *
 * @package    ATAI
 * @subpackage ATAI/admin/partials
 */
?>

<?php  if ( ! defined( 'WPINC' ) ) die; ?>

<?php
  $message = '';

  if ( isset( $_POST['submit'] ) && isset( $_FILES['csv'] ) ) {
    $attachment = new ATAI_Attachment();
    $response = $attachment->process_csv();

    if ($response['status'] === 'success') {
      // Generate a success message
      $message = '<div class="mt-2 ml-0 p-4  notice notice-success is-dismissible">';
      $message .= '<p>' . esc_html($response['message']) . '</p>';
      $message .= '</div>';
    } elseif ($response['status'] === 'error') {
      // Generate an error message
      $message = '<div class="mt-2 ml-0 p-4  notice notice-error is-dismissible">';
      $message .= '<p>' . esc_html($response['message']) . '</p>';
      $message .= '</div>';
    }
  }
?>

<div class="mr-5 mt-4 max-w-2xl">
  <div class="mb-4">
    <h2 class="text-2xl font-bold"><?php esc_html_e( 'Sync Alt Text Library', 'alttext-ai' ); ?></h2>

    <p class="mt-2">
      Synchronize any changes or edits from your online AltText.ai image library to WordPress.
      Any matching images in WordPress will be updated with the corresponding alt text
      from your library.
    </p>

    <?php echo wp_kses( $message, array( 'div' => array( 'class' => array() ), 'p' => array() ) ); ?>

    <div class="mt-6">
      <p class="block mb-2 text-base font-medium text-gray-900">Step 1: Export your online library</p>
      <ul class="ml-4 list-inside list-disc">
        <li>Go to your <a href="https://alttext.ai/images" target="_blank" class="font-medium text-primary-600 hover:text-primary-500">AltText.ai Image Library</a></li>
        <li>Click the Export button.</li>
        <li>Start the export, then download the CSV file when it's done.</li>
      </ul>
    </div>

    <div class="mt-8">
      <p class="block mb-2 text-base font-medium text-gray-900">Step 2: Upload your CSV</p>
      <form method="post" enctype="multipart/form-data" id="alttextai-csv-import" class="group" data-file-loaded="false">
        <?php wp_nonce_field( 'atai_csv_import', 'atai_csv_import_nonce' ); ?>
        <div class=" relative flex flex-col items-center gap-2  w-full px-6 py-10 sm:flex mt-2 text-center rounded-lg border-gray-500 hover:bg-gray-200 group transition-colors duration-200 ease-in-out border border-dashed box-border">
            <label class="absolute -inset-px size-[calc(100%+2px)] cursor-pointer group-hover:border-gray-500 border border-transparent rounded-lg font-semibold transition-colors duration-200 ease-in-out">
              <input
                id="file_input"
                type="file"
                name="csv"
                accept=".csv"
                required
                class="sr-only"
              >
            </label>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="size-6">
              <path fill-rule="evenodd" d="M1.5 5.625c0-1.036.84-1.875 1.875-1.875h17.25c1.035 0 1.875.84 1.875 1.875v12.75c0 1.035-.84 1.875-1.875 1.875H3.375A1.875 1.875 0 0 1 1.5 18.375V5.625ZM21 9.375A.375.375 0 0 0 20.625 9h-7.5a.375.375 0 0 0-.375.375v1.5c0 .207.168.375.375.375h7.5a.375.375 0 0 0 .375-.375v-1.5Zm0 3.75a.375.375 0 0 0-.375-.375h-7.5a.375.375 0 0 0-.375.375v1.5c0 .207.168.375.375.375h7.5a.375.375 0 0 0 .375-.375v-1.5Zm0 3.75a.375.375 0 0 0-.375-.375h-7.5a.375.375 0 0 0-.375.375v1.5c0 .207.168.375.375.375h7.5a.375.375 0 0 0 .375-.375v-1.5ZM10.875 18.75a.375.375 0 0 0 .375-.375v-1.5a.375.375 0 0 0-.375-.375h-7.5a.375.375 0 0 0-.375.375v1.5c0 .207.168.375.375.375h7.5ZM3.375 15h7.5a.375.375 0 0 0 .375-.375v-1.5a.375.375 0 0 0-.375-.375h-7.5a.375.375 0 0 0-.375.375v1.5c0 .207.168.375.375.375Zm0-3.75h7.5a.375.375 0 0 0 .375-.375v-1.5A.375.375 0 0 0 10.875 9h-7.5A.375.375 0 0 0 3 9.375v1.5c0 .207.168.375.375.375Z" clip-rule="evenodd" />
            </svg>
            <p class="text-center mx-auto hidden items-center gap-1.5 group-data-[file-loaded=false]:inline-flex"><span class="text-primary-600 font-medium rounded border border-gray-200 bg-gray-50  px-1.5 py-0.5 ">Choose File</span> or drag and drop.</p>
            <p class="text-center mx-auto hidden items-center gap-1.5 group-data-[file-loaded=true]:inline-flex">File added, import to continue.</p>
        </div>

        <div id="atai-csv-language-selector" class="mt-6 hidden">
          <label for="atai-csv-language" class="block mb-2 text-base font-medium text-gray-900">
            <?php esc_html_e( 'Step 3: Select Language', 'alttext-ai' ); ?>
          </label>
          <p class="text-sm text-gray-600 mb-3">
            <?php esc_html_e( 'Your CSV contains alt text in multiple languages. Choose which language to import:', 'alttext-ai' ); ?>
          </p>

          <select id="atai-csv-language" name="csv_language" class="w-full max-w-xs px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-primary-500 focus:border-primary-500">
            <option value="">
              <?php esc_html_e( 'Default (alt_text column)', 'alttext-ai' ); ?>
            </option>
            <!-- Language options populated via JavaScript -->
          </select>

          <p class="mt-2 text-xs text-gray-500">
            <?php esc_html_e( 'Selecting "Default" uses the main alt_text column. This is backward compatible with older exports.', 'alttext-ai' ); ?>
          </p>
        </div>

        <div class="mt-4">
          <input type="submit" name="submit" value="Import" class="atai-button blue mt-4 cursor-pointer appearance-none no-underline shadow-sm">
        </div>
      </form>
    </div>

  <div class="mt-8">
    <div class="hidden md:block  group -mb-2">
      <a target="_blank" rel="noopenner noreferrer" class="group z-10  box-border no-underline hover:text-white active:text-white focus:!text-white relative px-4 py-3 bg-primary-800 hover:bg-primary-900 text-white rounded-lg font-medium text-sm grid overflow-hidden border border-white/10 shadow-lg" href="https://wordpress.org/support/plugin/alttext-ai/reviews/?filter=5">
        <div class="grid gap-1 z-20 text-white">
          <div class="text-lg">Do you like AltText.ai? Leave us a review!</div>
          <div>Help spread the word on WordPress.org. We'd really appreciate it!</div>
          <div class="text-[0.8125rem] text-gray-200">Leave your review →</div>
        </div>
        <div class="absolute h-full w-full flex right-0 top-0 overflow-hidden">
          <div class="absolute h-full w-full bg-gradient-to-r to-transparent from-primary-800 group-hover:from-primary-900 z-10"></div>
          <svg class="h-full ml-auto text-primary-600" aria-hidden="true" viewBox="0 0 2560 1339" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1846.44 -590.584C1870.38 -673.943 1833.5 -763.152 1757.65 -805.234C1681.79 -847.315 1587.63 -830.793 1529.86 -766.057L222.486 711.983C171.453 770.008 161.012 854.018 196.993 922.224C232.975 990.43 307.969 1030.27 384.333 1020.68L1024.53 940.244L713.141 2031.56C689.204 2114.92 726.074 2204.13 801.933 2246.21C877.791 2288.3 971.949 2271.77 1029.72 2207.04L2337.09 728.998C2388.13 670.973 2398.57 586.964 2362.59 518.757C2326.6 450.551 2252.26 411.212 2175.32 420.879L1535.12 501.314L1846.44 -590.584Z" fill="currentColor"></path></svg>
        </div>
      </a>
    </div>
  </div>
</div>
