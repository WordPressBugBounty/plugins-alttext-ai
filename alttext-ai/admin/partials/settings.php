<?php
/**
 * Provide an admin area view for the plugin settings.
 *
 * This file is used to markup the admin-facing settings of the plugin.
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
  $has_file_based_api_key = defined( 'ATAI_API_KEY' );
  $wp_kses_args = array(
    'a' => array(
        'href' => array(),
        'target' => array()
    ),
    'br' => array()
  );
?>

<?php
  $lang = get_option( 'atai_lang' );
  $supported_languages = ATAI_Utility::supported_languages();
  $ai_model_name = get_option( 'atai_model_name' );
  $supported_models = ATAI_Utility::supported_model_names();
  $timeout_secs = intval(get_option( 'atai_timeout', 20 ));
  $timeout_values = [10, 15, 20, 25, 30];
?>

<div class="mt-8 mr-5">
  <?php if ( empty( ATAI_Utility::get_api_key() ) ) : ?>
    <script type="application/javascript">
      function onCloseAtaiWelcomePanel() {
        document.getElementById('atai-welcome-panel').style.display = 'none';
      }
    </script>
    <div id="atai-welcome-panel" class="overflow-hidden mb-4 rounded-2xl border border-gray-200 bg-primary-600">
      <a id="atai-welcome-panel-close" class="flex relative z-10 justify-end items-center pt-4 pr-4 w-full text-gray-50 hover:text-primary-200 no-underline" href="#" aria-label="Dismiss the welcome panel" onclick="onCloseAtaiWelcomePanel(); return false;">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
          <path stroke-linecap="round" stroke-linejoin="round" d="M9.75 9.75l4.5 4.5m0-4.5l-4.5 4.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
        </svg>
        <span class="ml-1 mr-4">Close</span>
      </a>
      <div class="relative px-6 pt-14 lg:px-8 isolate">
        <div class="py-8 mx-auto max-w-5xl">
          <div class="text-center">
          <svg class="mx-auto w-56" viewBox="0 0 501 144" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M128.727 13.0524L128.726 13.056L114.862 61.6833L114.021 64.6318L117.063 64.2496L145.574 60.6674C148.154 60.3432 150.656 61.6614 151.869 63.9613C153.073 66.244 152.73 69.0768 151.006 71.0382C151.006 71.0389 151.005 71.0397 151.004 71.0405L92.7907 136.853C92.7895 136.855 92.7884 136.856 92.7872 136.857C90.8368 139.039 87.6793 139.583 85.1412 138.175C82.5886 136.759 81.3458 133.754 82.1516 130.947L82.1525 130.944L96.0202 82.3426L96.8616 79.3936L93.8189 79.7759L65.3079 83.3581C62.7601 83.6782 60.2262 82.3445 59.0097 80.0385C57.8054 77.7556 58.149 74.9224 59.8729 72.961C59.8734 72.9604 59.8739 72.9599 59.8743 72.9593L118.088 7.14631C118.089 7.1453 118.09 7.14428 118.091 7.14327C120.041 4.96054 123.199 4.41617 125.737 5.82436C128.29 7.24038 129.533 10.2462 128.727 13.0524Z" stroke="#F9FAFB" stroke-width="4.0529"/>
            <path d="M149.918 22.1499H140.586L189.907 71.4713C190.2 71.7635 190.2 72.2373 189.907 72.5296L140.586 121.851H149.918C157.262 121.851 164.304 118.934 169.496 113.742L204.888 78.35C208.395 74.8432 208.395 69.1576 204.888 65.6508L169.496 30.2593C164.304 25.067 157.262 22.1499 149.918 22.1499Z" fill="#76A9FA"/>
            <path d="M61.3194 22.1499H70.6519L21.3305 71.4713C21.0382 71.7635 21.0382 72.2373 21.3305 72.5296L70.6519 121.851H61.3194C53.9763 121.851 46.9339 118.934 41.7415 113.742L6.34998 78.35C2.8432 74.8432 2.8432 69.1576 6.34999 65.6508L41.7415 30.2593C46.9339 25.067 53.9762 22.1499 61.3194 22.1499Z" fill="#76A9FA"/>
            <path d="M498.523 53.4136C499.635 53.4136 500.481 54.4116 500.299 55.5084L494.811 88.5629C494.667 89.4314 493.916 90.0681 493.035 90.0681H487.825C486.713 90.0681 485.867 89.0701 486.049 87.9733L491.537 54.9188C491.681 54.0503 492.432 53.4136 493.313 53.4136H498.523Z" fill="#A4CAFE"/>
            <path d="M458.261 89.0481C457.961 89.6719 457.33 90.0686 456.638 90.0686H451.17C449.83 90.0686 448.96 88.6563 449.563 87.4592L466.203 54.4047C466.509 53.7973 467.131 53.4141 467.811 53.4141H476.978C477.855 53.4141 478.604 54.0458 478.753 54.91L484.418 87.9645C484.606 89.0641 483.759 90.0686 482.644 90.0686H476.812C475.919 90.0686 475.161 89.4138 475.031 88.5302L471.254 62.8132C471.24 62.7191 471.159 62.6493 471.064 62.6493C470.99 62.6493 470.923 62.6916 470.891 62.7581L458.261 89.0481ZM458.661 77.141C458.808 76.2759 459.558 75.643 460.436 75.643H476.686C477.801 75.643 478.648 76.6461 478.461 77.7451L477.928 80.8746C477.781 81.7397 477.031 82.3726 476.153 82.3726H459.903C458.788 82.3726 457.941 81.3695 458.128 80.2705L458.661 77.141Z" fill="#A4CAFE"/>
            <path d="M442.701 90.5873C441.401 90.5873 440.339 90.1339 439.515 89.2271C438.692 88.3202 438.334 87.2225 438.441 85.9339C438.561 84.6691 439.122 83.5893 440.124 82.6944C441.126 81.7995 442.272 81.3521 443.56 81.3521C444.789 81.3521 445.821 81.7995 446.656 82.6944C447.504 83.5893 447.868 84.6691 447.748 85.9339C447.677 86.793 447.384 87.5745 446.871 88.2785C446.37 88.9824 445.75 89.5432 445.01 89.9609C444.27 90.3785 443.501 90.5873 442.701 90.5873Z" fill="#A4CAFE"/>
            <path d="M409.319 60.608C408.324 60.608 407.519 59.8021 407.519 58.808V55.2131C407.519 54.219 408.324 53.4131 409.319 53.4131H436.7C437.694 53.4131 438.5 54.219 438.5 55.2131V58.808C438.5 59.8021 437.694 60.608 436.7 60.608H429.185C428.191 60.608 427.385 61.4139 427.385 62.408V88.2676C427.385 89.2617 426.579 90.0676 425.585 90.0676H420.451C419.457 90.0676 418.651 89.2617 418.651 88.2676V62.408C418.651 61.4139 417.845 60.608 416.851 60.608H409.319Z" fill="#F9FAFB"/>
            <path d="M382.459 53.4136C383.102 53.4136 383.696 53.7563 384.018 54.3128L390.12 64.8691C390.164 64.946 390.246 64.9934 390.335 64.9934C390.424 64.9934 390.505 64.9465 390.55 64.8701L396.722 54.3056C397.045 53.7532 397.637 53.4136 398.276 53.4136H403.97C405.371 53.4136 406.235 54.944 405.511 56.1436L396.668 70.7958C396.318 71.3757 396.323 72.1029 396.682 72.6777L405.802 87.3163C406.549 88.5152 405.687 90.0681 404.274 90.0681H398.381C397.743 90.0681 397.152 89.73 396.829 89.1795L390.551 78.4865C390.506 78.41 390.424 78.363 390.335 78.363C390.246 78.363 390.164 78.41 390.119 78.4865L383.841 89.1795C383.517 89.73 382.927 90.0681 382.288 90.0681H376.459C375.048 90.0681 374.185 88.5186 374.929 87.3195L384.009 72.6775C384.365 72.1027 384.369 71.3765 384.02 70.7977L375.162 56.1447C374.437 54.9451 375.301 53.4136 376.702 53.4136H382.459Z" fill="#F9FAFB"/>
            <path d="M348.251 90.0676C347.257 90.0676 346.451 89.2617 346.451 88.2676V55.2131C346.451 54.219 347.257 53.4131 348.251 53.4131H370.209C371.203 53.4131 372.009 54.219 372.009 55.2131V58.808C372.009 59.8021 371.203 60.608 370.209 60.608H357.11C356.116 60.608 355.31 61.4139 355.31 62.408V66.325C355.31 67.3191 356.116 68.125 357.11 68.125H368.902C369.896 68.125 370.702 68.9309 370.702 69.925V73.5378C370.702 74.5319 369.896 75.3378 368.902 75.3378H357.11C356.116 75.3378 355.31 76.1437 355.31 77.1378V81.0727C355.31 82.0669 356.116 82.8727 357.11 82.8727H370.209C371.203 82.8727 372.009 83.6786 372.009 84.6727V88.2676C372.009 89.2617 371.203 90.0676 370.209 90.0676H348.251Z" fill="#F9FAFB"/>
            <path d="M315.966 60.6085C314.971 60.6085 314.166 59.8026 314.166 58.8085V55.2136C314.166 54.2195 314.971 53.4136 315.966 53.4136H343.346C344.341 53.4136 345.146 54.2195 345.146 55.2136V58.8085C345.146 59.8026 344.341 60.6085 343.346 60.6085H335.832C334.838 60.6085 334.032 61.4143 334.032 62.4085V88.2681C334.032 89.2622 333.226 90.0681 332.232 90.0681H327.098C326.104 90.0681 325.298 89.2622 325.298 88.2681V62.4085C325.298 61.4143 324.492 60.6085 323.498 60.6085H315.966Z" fill="#F9FAFB"/>
            <path d="M284.882 60.608C283.887 60.608 283.082 59.8021 283.082 58.808V55.2131C283.082 54.219 283.887 53.4131 284.882 53.4131H312.263C313.257 53.4131 314.063 54.219 314.063 55.2131V58.808C314.063 59.8021 313.257 60.608 312.263 60.608H304.748C303.754 60.608 302.948 61.4139 302.948 62.408V88.2676C302.948 89.2617 302.142 90.0676 301.148 90.0676H296.014C295.02 90.0676 294.214 89.2617 294.214 88.2676V62.408C294.214 61.4139 293.408 60.608 292.414 60.608H284.882Z" fill="#F9FAFB"/>
            <path d="M264.889 90.0681C263.895 90.0681 263.089 89.2622 263.089 88.2681V55.2136C263.089 54.2195 263.895 53.4136 264.889 53.4136H270.148C271.142 53.4136 271.948 54.2195 271.948 55.2136V81.0732C271.948 82.0673 272.754 82.8732 273.748 82.8732H285.397C286.391 82.8732 287.197 83.6791 287.197 84.6732V88.2681C287.197 89.2622 286.391 90.0681 285.397 90.0681H264.889Z" fill="#F9FAFB"/>
            <path d="M235.554 88.8058C235.319 89.5566 234.623 90.0676 233.836 90.0676H228.144C226.913 90.0676 226.045 88.8589 226.439 87.6922L237.591 54.6376C237.838 53.9058 238.525 53.4131 239.297 53.4131H248.507C249.279 53.4131 249.965 53.9058 250.212 54.6376L261.365 87.6922C261.759 88.8589 260.891 90.0676 259.659 90.0676H253.966C253.18 90.0676 252.485 89.5571 252.249 88.8068L244.079 62.7849C244.053 62.7036 243.978 62.6483 243.893 62.6483C243.808 62.6483 243.732 62.7037 243.707 62.785L235.554 88.8058ZM233.888 77.4421C233.888 76.448 234.694 75.6421 235.688 75.6421H251.99C252.984 75.6421 253.79 76.448 253.79 77.4421V80.5716C253.79 81.5657 252.984 82.3716 251.99 82.3716H235.688C234.694 82.3716 233.888 81.5657 233.888 80.5716V77.4421Z" fill="#F9FAFB"/>
          </svg>

            <h1 class="mt-4 text-4xl font-semibold tracking-tight text-gray-50 sm:text-5xl text-balance">Welcome to AltText.ai</h1>
            <p class="mt-4 text-lg font-medium text-gray-100 text-pretty sm:text-xl/8">Let's get set up...</p>
            <div class="flex gap-x-6 justify-center items-center mt-10">
              <div class="grid grid-cols-1 gap-4 text-white lg:grid-cols-3">
                <div class="flex flex-col gap-4 justify-center items-center p-4 text-base rounded-2xl border border-primary-700 bg-gray-900/10 backdrop-blur">
                  <span class="flex justify-center items-center p-2 leading-none text-center text-white bg-gray-900 rounded-full size-6"><span>1</span></span>
                  <p class="text-base">
                    If you don't have an AltText.ai account,
                    <a class="underline !text-white decoration-dotted hover:decoration-solid hover:text-primary-50" href="https://alttext.ai?utm_source=wp&utm_medium=dl" target="_blank" rel="noopener noreferrer">create a free account</a>
                    on our site.
                  </p>
                </div>
                <div class="flex flex-col gap-4 justify-center items-center p-4 rounded-2xl border border-primary-700 bg-gray-900/10 backdrop-blur">
                  <span class="flex justify-center items-center p-2 leading-none text-center text-white bg-gray-900 rounded-full size-6"><span>2</span></span>
                  <p class="text-base">
                  Copy or create
                  <a class="underline !text-white decoration-dotted hover:decoration-solid hover:text-primary-50" href="https://alttext.ai/account/api_keys" target="_blank" rel="noopener noreferrer">your API Key</a>
                  from your account, and enter it below.
                  </p>
                </div>
                <div class="flex flex-col gap-4 justify-center items-center p-4 rounded-2xl border border-primary-700 bg-gray-900/10 backdrop-blur">
                  <span class="flex justify-center items-center p-2 leading-none text-center text-white bg-gray-900 rounded-full size-6"><span>3</span></span>
                  <p class="text-base">
                See the plugin features in our short
                <a class="underline !text-white decoration-dotted hover:decoration-solid hover:text-primary-50" href="https://youtu.be/LpMXPbMds4U" target="_blank" rel="noopener noreferrer">
                  Tutorial video.
                </a>
                </p>
                  </div>
              </div>
            </div>
          </div>
        </div>

      </div>
    </div>

  <?php endif; ?>

  <h2 class="mb-0 text-2xl font-bold"><?php esc_html_e( 'AltText.ai WordPress Settings', 'alttext-ai' ); ?></h2>
  <?php settings_errors(); ?>

  <form method="post" class="" action="<?php echo esc_url( admin_url() . 'options.php' ); ?>">
    <?php settings_fields( 'atai-settings' ); ?>
    <?php do_settings_sections( 'atai-settings' ); ?>

    <input type="submit" name="submit" value="Save Changes" class="atai-button blue mt-4 cursor-pointer appearance-none no-underline shadow-sm">
    <div class="mt-4 space-y-4 border-b-0 border-t border-solid divide-x-0 divide-y divide-solid sm:space-y-6 border-x-0 border-gray-900/10 divide-gray-900/10">
      <div class="">
        <div class="pb-12 mt-4 space-y-8 sm:pb-0 sm:space-y-0 sm:border-t">

          <div class="sm:grid sm:grid-cols-3 sm:gap-4 sm:items-start sm:py-4">
            <label for="username" class="block text-sm font-medium leading-6 text-gray-900 sm:pt-1.5"><?php esc_html_e( 'API Key', 'alttext-ai' ); ?></label>
            <div class="mt-2 sm:col-span-2 sm:mt-0">
              <div class="flex gap-x-2">
                <input
                  type="text"
                  name="atai_api_key"
                  value="<?php echo ( ATAI_Utility::get_api_key() ) ? '*********' : null; ?>"
                  class="block py-1.5 w-full text-gray-900 rounded-md border-0 ring-1 ring-inset ring-gray-300 shadow-sm sm:max-w-xs sm:text-sm sm:leading-6 focus:ring-2 focus:ring-inset placeholder:text-gray-400 focus:ring-primary-600"
                  <?php echo ( $has_file_based_api_key || ATAI_Utility::get_api_key() ) ? 'readonly' : null; ?>
                >
                <input
                  type="submit"
                  name="handle_api_key"
                  class="<?php echo ( ATAI_Utility::get_api_key() ) ? 'atai-button black' : 'atai-button blue'; ?> relative no-underline cursor-pointer shadow-sm focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 whitespace-nowrap"
                  value="<?php echo ( ATAI_Utility::get_api_key() ) ? esc_attr__( 'Clear API Key', 'alttext-ai' ) : esc_attr__( 'Add API Key', 'alttext-ai' ); ?>"
                  <?php echo ( $has_file_based_api_key ) ? 'disabled' : null; ?>
                >
              </div>
              <div class="mt-4 max-w-lg">
                <?php if ( ! ATAI_Utility::get_api_key() ) : ?>
                  <div class="bg-gray-900/15 p-px rounded-lg">
                    <p class="py-2 px-4 leading-relaxed bg-gray-100 rounded-lg sm:p-4 m-0">
                    <?php
                      printf (
                        wp_kses(
                          __( 'Get your API Key at <a href="%s" target="_blank" class="font-medium text-primary-600 hover:text-primary-500">AltText.ai > Account > API Keys</a>.', 'alttext-ai' ),
                          array( 'a' => array( 'href' => array(), 'target' => array(), 'class' => array() ) )
                        ),
                        esc_url( 'https://alttext.ai/account/api_keys' )
                      );
                    ?>
                    </p>
                  </div>
                <?php elseif ( ATAI_Utility::get_api_key() && $this->account === false ) : ?>
                  <div class="bg-red-900/15 p-px rounded-lg">
                    <p class="py-2 px-4 font-semibold leading-relaxed text-red-600 bg-red-100 rounded-lg sm:p-4 m-0">
                    <?php
                      printf (
                        wp_kses(
                          __( 'Your API key is invalid. Please check your API key or <a href="%s" target="_blank" class="font-medium text-primary-600 hover:text-primary-500">create a new API key</a>.', 'alttext-ai' ),
                          array( 'a' => array( 'href' => array(), 'target' => array(), 'class' => array() ) )
                        ),
                        esc_url( 'https://alttext.ai/account/api_keys' )
                      );
                    ?>
                    </p>
                  </div>
                <?php else : ?>
                  <div class="bg-primary-900/15 p-px rounded-lg">
                    <p class="py-2 m-0 px-4 leading-relaxed bg-primary-100 rounded-lg sm:p-4">
                    <?php
                      if (! $this->account['whitelabel']) {
                        printf(
                          wp_kses(
                            __( 'You\'re on the <strong>%s</strong> plan.', 'alttext-ai' ),
                            array( 'strong' => array() )
                          ),
                          esc_html( $this->account['plan'] )
                        );

                        echo '<br>';
                      }
                      printf(
                        wp_kses(
                          __( 'You have <strong>%d</strong> credits available out of <strong>%d</strong>.', 'alttext-ai' ),
                          array( 'strong' => array() )
                        ),
                        (int) $this->account['available'],
                        (int) $this->account['quota']
                      );

                      if (! $this->account['whitelabel']) {
                        echo '<br>';

                        printf(
                          wp_kses(
                            __( 'You can <a href="%s" target="_blank" class="font-medium text-primary-600 hover:text-primary-500">upgrade your plan</a> to get more credits.', 'alttext-ai' ),
                            array( 'a' => array( 'href' => array(), 'target' => array(), 'class' => array() ) )
                          ),
                          esc_url( ATAI_Utility::get_credits_url() )
                        );
                      }
                    ?>
                    </p>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <div class="sm:grid sm:grid-cols-3 sm:gap-4 sm:items-start sm:py-4">
            <label for="atai_lang" class="block text-sm font-medium leading-6 text-gray-900 sm:pt-1.5"><?php esc_html_e( 'Alt Text Language', 'alttext-ai' ); ?></label>
            <div class="mt-2 sm:col-span-2 sm:mt-0">
              <select id="atai_lang" name="atai_lang" class="block py-1.5 w-full text-gray-900 rounded-md border-0 ring-1 ring-inset ring-gray-300 shadow-sm sm:max-w-xs sm:text-sm sm:leading-6 focus:ring-2 focus:ring-inset focus:ring-primary-600">
                <?php
                  foreach ( $supported_languages as $lang_code => $lang_name ) {
                    $option_str = "<option value=\"$lang_code\"";

                    if ( $lang === $lang_code ) {
                      $option_str = $option_str . " selected";
                    }

                    $option_str = $option_str . ">$lang_name</option>\n";
                    echo wp_kses( $option_str, array( 'option' => array( 'selected' => array(), 'value' => array() ) ) );
                  }
                ?>
              </select>
              <?php if ( ATAI_Utility::has_polylang() || ATAI_Utility::has_wpml() ) : ?>
                <div class="ml-2 mt-4 flex relative gap-x-3">
                  <div class="flex items-center h-6">
                    <input
                      id="atai_force_lang"
                      name="atai_force_lang"
                      type="checkbox"
                      value="yes"
                      class="w-4 h-4 rounded border-gray-300 checked:bg-white text-primary-600 focus:ring-primary-600"
                      <?php checked( 'yes', get_option( 'atai_force_lang' ) ); ?>
                    >
                  </div>
                  <div class="-mt-1 text-sm leading-6">
                    <label for="atai_force_lang" class="text-gray-600"><?php esc_html_e( 'Always use this language, even if translations exist.', 'alttext-ai' ); ?></label>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          </div>

          <div class="sm:grid sm:grid-cols-3 sm:gap-4 sm:items-start sm:py-4">
            <label for="atai_model_name" class="block text-sm font-medium leading-6 text-gray-900 sm:pt-1.5"><?php esc_html_e( 'AI Writing Style', 'alttext-ai' ); ?></label>
            <div class="mt-2 sm:col-span-2 sm:mt-0">
              <select id="atai_model_name" name="atai_model_name" class="block py-1.5 w-full text-gray-900 rounded-md border-0 ring-1 ring-inset ring-gray-300 shadow-sm sm:max-w-xs sm:text-sm sm:leading-6 focus:ring-2 focus:ring-inset focus:ring-primary-600">
                <?php
                  foreach ( $supported_models as $model_name => $display_name ) {
                    $option_str = "<option value=\"$model_name\"";

                    if ( $ai_model_name === $model_name ) {
                      $option_str = $option_str . " selected";
                    }

                    $option_str = $option_str . ">$display_name</option>\n";
                    echo wp_kses( $option_str, array( 'option' => array( 'selected' => array(), 'value' => array() ) ) );
                  }
                ?>
              </select>
              <p class="mt-1 text-gray-500">
                <a href="https://alttext.ai/docs/webui/account/#style-and-level-of-detail" target="_blank"><?php esc_html_e("Learn more about available AI writing styles.", "alttext-ai"); ?></a>
              </p>
            </div>
          </div>

          <div class="sm:grid sm:grid-cols-3 sm:gap-4 sm:py-4">
            <div class="text-sm font-semibold leading-6 text-gray-900" aria-hidden="true"><?php esc_html_e( 'When alt text is generated for an image:', 'alttext-ai' ); ?></div>
            <div class="mt-4 sm:col-span-2 sm:mt-0">
              <div class="space-y-2 max-w-lg">
                <div class="flex relative gap-x-3">
                  <div class="flex items-center h-6">
                    <input
                      id="atai_update_title"
                      name="atai_update_title"
                      type="checkbox"
                      value="yes"
                      class="w-4 h-4 rounded border-gray-300 checked:bg-white text-primary-600 focus:ring-primary-600"
                      <?php checked( 'yes', get_option( 'atai_update_title' ) ); ?>
                    >
                  </div>
                  <div class="-mt-1 text-sm leading-6">
                    <label for="atai_update_title" class="font-medium text-gray-900"><?php esc_html_e( 'Also set the image title with the generated alt text.', 'alttext-ai' ); ?></label>
                  </div>
                </div>
                <div class="flex relative gap-x-3">
                  <div class="flex items-center h-6">
                    <input
                      id="atai_update_caption"
                      name="atai_update_caption"
                      type="checkbox"
                      value="yes"
                      class="w-4 h-4 rounded border-gray-300 checked:bg-white text-primary-600 focus:ring-primary-600"
                      <?php checked( 'yes', get_option( 'atai_update_caption' ) ); ?>
                    >
                  </div>
                  <div class="-mt-1 text-sm leading-6">
                    <label for="atai_update_caption" class="font-medium text-gray-900"><?php esc_html_e( 'Also set the image caption with the generated alt text.', 'alttext-ai' ); ?></label>
                  </div>
                </div>
                <div class="flex relative gap-x-3">
                  <div class="flex items-center h-6">
                    <input
                      id="atai_update_description"
                      name="atai_update_description"
                      type="checkbox"
                      value="yes"
                      class="w-4 h-4 rounded border-gray-300 checked:bg-white text-primary-600 focus:ring-primary-600"
                      <?php checked( 'yes', get_option( 'atai_update_description' ) ); ?>
                    >
                  </div>
                  <div class="-mt-1 text-sm leading-6">
                    <label for="comments" class="font-medium text-gray-900"><?php esc_html_e( 'Also set the image description with the generated alt text.', 'alttext-ai' ); ?></label>
                  </div>
                </div>
                <div>
                  <label for="atai_alt_prefix" class="block text-sm leading-6 text-gray-600"><?php esc_html_e( 'Add a hardcoded string to the beginning:', 'alttext-ai' ); ?></label>
                  <div class="mt-2">
                    <input
                      type="text"
                      name="atai_alt_prefix"
                      id="atai_alt_prefix"
                      class="block py-1.5 w-full text-gray-900 rounded-md border-0 ring-1 ring-inset ring-gray-300 shadow-sm sm:text-sm sm:leading-6 focus:ring-2 focus:ring-inset placeholder:text-gray-400 focus:ring-primary-600"
                      value="<?php echo esc_html ( get_option( 'atai_alt_prefix' ) ); ?>"
                    >
                  </div>
                </div>
                <div>
                  <label for="atai_alt_suffix" class="block text-sm leading-6 text-gray-600"><?php esc_html_e( 'Add a hardcoded string to the end:', 'alttext-ai' ); ?></label>
                  <div class="mt-2">
                    <input
                      type="text"
                      name="atai_alt_suffix"
                      id="atai_alt_suffix"
                      class="block py-1.5 w-full text-gray-900 rounded-md border-0 ring-1 ring-inset ring-gray-300 shadow-sm sm:text-sm sm:leading-6 focus:ring-2 focus:ring-inset placeholder:text-gray-400 focus:ring-primary-600"
                      value="<?php echo esc_html ( get_option( 'atai_alt_suffix' ) ); ?>"
                    >
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="sm:grid sm:grid-cols-3 sm:gap-4 sm:py-4">
            <div class="text-sm font-semibold leading-6 text-gray-900" aria-hidden="true"><?php esc_html_e( 'When new images are added:', 'alttext-ai' ); ?></div>
            <div class="mt-4 sm:col-span-2 sm:mt-0">
              <div class="space-y-2 max-w-lg">
                <div class="flex relative gap-x-3">
                  <div class="flex items-center h-6">
                    <input
                      id="atai_enabled"
                      name="atai_enabled"
                      type="checkbox"
                      value="yes"
                      class="w-4 h-4 rounded border-gray-300 checked:bg-white text-primary-600 focus:ring-primary-600"
                      <?php checked( 'yes', get_option( 'atai_enabled' ) ); ?>
                    >
                  </div>
                  <div class="-mt-1 text-sm leading-6">
                    <label for="atai_enabled" class="font-medium text-gray-900"><?php esc_html_e( 'Automatically generate alt text with AltText.ai', 'alttext-ai' ); ?></label>
                    <p class="text-gray-500"><?php esc_html_e( 'Note: You can always generate alt text using the Bulk Generate page or Update Alt Text button on an individual image.', 'alttext-ai' ); ?></p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="sm:grid sm:grid-cols-3 sm:gap-4 sm:py-4">
            <div class="text-sm font-semibold leading-6 text-gray-900" aria-hidden="true"><?php esc_html_e( 'Image filtering:', 'alttext-ai' ); ?></div>
            <div class="mt-4 sm:col-span-2 sm:mt-0">
              <div class="space-y-6 max-w-lg">
                <div>
                  <label for="atai_type_extensions" class="block text-sm leading-6 text-gray-600"><?php esc_html_e( 'Only process images with these file extensions:', 'alttext-ai' ); ?></label>
                  <div class="mt-2">
                    <input
                      type="text"
                      name="atai_type_extensions"
                      id="atai_type_extensions"
                      class="block py-1.5 w-full text-gray-900 rounded-md border-0 ring-1 ring-inset ring-gray-300 shadow-sm sm:text-sm sm:leading-6 focus:ring-2 focus:ring-inset placeholder:text-gray-400 focus:ring-primary-600"
                      value="<?php echo esc_html ( get_option( 'atai_type_extensions' ) ); ?>"
                    >
                  </div>
                  <p class="mt-1 text-gray-500">
                    <?php esc_html_e( 'Separate multiple extensions with commas. Example: jpg,webp', 'alttext-ai' ); ?>
                    <br>
                    <?php esc_html_e( 'Leave blank to process all image types.', 'alttext-ai' ); ?>
                  </p>
                  <span class="mt-1 text-amber-900/80">  
                    <?php 
                      printf(
                        wp_kses(
                          __( 'Note: Advanced Image Formats (AVIF, SVG) cost 2 credits per image. You can disable this feature in your <a href="%s" target="_blank" class="font-medium underline">account settings</a>.', 'alttext-ai' ),
                          array( 'a' => array( 'href' => array(), 'target' => array(), 'class' => array() ) )
                        ),
                        esc_url( 'https://alttext.ai/account/edit' )
                      );
                    ?>
                  </span>
                </div>
                <div>
                  <label for="atai_excluded_post_types" class="block text-sm leading-6 text-gray-600"><?php esc_html_e( 'Exclude images attached to these post types:', 'alttext-ai' ); ?></label>
                  <div class="mt-2">
                    <input
                      type="text"
                      name="atai_excluded_post_types"
                      id="atai_excluded_post_types"
                      class="block py-1.5 w-full text-gray-900 rounded-md border-0 ring-1 ring-inset ring-gray-300 shadow-sm sm:text-sm sm:leading-6 focus:ring-2 focus:ring-inset placeholder:text-gray-400 focus:ring-primary-600"
                      value="<?php echo esc_html ( get_option( 'atai_excluded_post_types' ) ); ?>"
                    >
                  </div>
                  <p class="mt-1 text-gray-500">
                    <?php esc_html_e( 'Separate multiple post types with commas. Example: proof,submission', 'alttext-ai' ); ?>
                    <br>
                    <?php esc_html_e( 'Leave blank to process images from all post types.', 'alttext-ai' ); ?>
                  </p>
                </div>
                <div class="flex relative gap-x-3">
                  <div class="flex items-center h-6">
                    <input
                      id="atai_skip_filenotfound"
                      name="atai_skip_filenotfound"
                      type="checkbox"
                      value="yes"
                      class="w-4 h-4 rounded border-gray-300 checked:bg-white text-primary-600 focus:ring-primary-600"
                      <?php checked( 'yes', get_option( 'atai_skip_filenotfound' ) ); ?>
                    >
                  </div>
                  <div class="-mt-1 text-sm leading-6">
                    <label for="atai_skip_filenotfound" class="font-medium text-gray-900"><?php esc_html_e( 'Skip image files unable to be found on the server.', 'alttext-ai' ); ?></label>
                    <p class="mt-1 text-gray-500">
                      Useful if WordPress references old images which have been deleted.
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="sm:grid sm:grid-cols-3 sm:gap-4 sm:py-4">
            <div class="text-sm font-semibold leading-6 text-gray-900" aria-hidden="true"><?php esc_html_e( 'SEO Keywords', 'alttext-ai' ); ?></div>
            <div class="mt-4 sm:col-span-2 sm:mt-0">
              <div class="space-y-2 max-w-lg">
                <div class="flex relative gap-x-3">
                  <div class="flex items-center h-6">
                    <input
                      id="atai_keywords"
                      name="atai_keywords"
                      type="checkbox"
                      value="yes"
                      class="w-4 h-4 rounded border-gray-300 checked:bg-white text-primary-600 focus:ring-primary-600"
                      <?php checked( 'yes', get_option( 'atai_keywords' ) ); ?>
                    >
                  </div>
                  <div class="-mt-1 text-sm leading-6">
                    <label for="atai_keywords" class="font-medium text-gray-900"><?php esc_html_e( 'Generate alt text using focus keyphrases, if present.', 'alttext-ai' ); ?></label>
                    <p class="mt-1 text-gray-500">
                      AltText.ai will intelligently integrate the focus keyphrases from the associated post.
                      Compatible with Yoast SEO, AllInOne SEO, RankMath, SEOPress, Squirrly, and SmartCrawl Pro SEO plugins for WordPress.
                      <a href="https://alttext.ai/support#faq-wordpress" target="blank" rel="noopener" class="font-medium text-primary-600 hover:text-primary-500">Learn more</a>.
                    </p>
                  </div>
                </div>
                <div class="flex relative gap-x-3">
                  <div class="flex items-center h-6">
                    <input
                      id="atai_keywords_title"
                      name="atai_keywords_title"
                      type="checkbox"
                      value="yes"
                      class="w-4 h-4 rounded border-gray-300 checked:bg-white text-primary-600 focus:ring-primary-600"
                      <?php checked( 'yes', get_option( 'atai_keywords_title' ) ); ?>
                    >
                  </div>
                  <div class="-mt-1 text-sm leading-6">
                    <label for="atai_keywords_title" class="font-medium text-gray-900"><?php esc_html_e( 'Use post title as keywords if SEO keywords not found from plugins.', 'alttext-ai' ); ?></label>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="sm:grid sm:grid-cols-3 sm:gap-4 sm:py-4">
            <div class="text-sm font-semibold leading-6 text-gray-900" aria-hidden="true"><?php esc_html_e( 'Chat GPT:', 'alttext-ai' ); ?></div>
            <div class="mt-4 sm:col-span-2 sm:mt-0">
              <div class="space-y-6 max-w-lg">
                <div>
                  <label for="atai_gpt_prompt" class="block text-sm leading-6 text-gray-600">
                    <?php esc_html_e( 'Use a ChatGPT prompt to modify any generated alt text.', 'alttext-ai' ); ?>
                    <a href="https://alttext.ai/docs/webui/adding-images/#using-chatgpt-modification" target="blank" rel="noopener" class="font-medium text-primary-600 hover:text-primary-500">Learn more</a>.
                  </label>
                  <div class="mt-2">
                    <textarea
                      name="atai_gpt_prompt"
                      id="atai_gpt_prompt"
                      rows="3"
                      maxlength="512"
                      class="block py-1.5 w-full text-gray-900 rounded-md border-0 ring-1 ring-inset ring-gray-300 shadow-sm sm:text-sm sm:leading-6 focus:ring-2 focus:ring-inset placeholder:text-gray-400 focus:ring-primary-600"
                      placeholder="example: Rewrite the following text in the style of Shakespeare: {{AltText}}"
                    ><?php echo esc_html ( get_option( 'atai_gpt_prompt' ) ); ?></textarea>
                  </div>
                  <p class="mt-1 text-gray-500">
                    <?php esc_html_e( 'Your prompt MUST include the macro {{AltText}}, which will be substituted with the generated alt text, then sent to ChatGPT.', 'alttext-ai' ); ?>
                  </p>
                </div>
              </div>
            </div>
          </div>

          <div class="sm:grid sm:grid-cols-3 sm:gap-4 sm:py-4">
            <div class="text-sm font-semibold leading-6 text-gray-900" aria-hidden="true"><?php esc_html_e( 'Bulk Refreshing', 'alttext-ai' ); ?></div>
            <div class="mt-4 sm:col-span-2 sm:mt-0">
              <div class="space-y-2 max-w-lg">
                <div class="flex relative gap-x-3">
                  <div class="flex items-center h-6">
                    <input
                      id="atai_bulk_refresh_overwrite"
                      name="atai_bulk_refresh_overwrite"
                      type="checkbox"
                      value="yes"
                      class="w-4 h-4 rounded border-gray-300 checked:bg-white text-primary-600 focus:ring-primary-600"
                      <?php checked( 'yes', get_option( 'atai_bulk_refresh_overwrite' ) ); ?>
                    >
                  </div>
                  <div class="-mt-1 text-sm leading-6">
                    <label for="atai_bulk_refresh_overwrite" class="font-medium text-gray-900"><?php esc_html_e( 'Overwrite existing alt text when refreshing posts and pages using the Bulk Action menu.', 'alttext-ai' ); ?></label>
                  </div>
                </div>

                <div class="flex relative gap-x-3">
                  <div class="flex items-center h-6">
                    <input
                      id="atai_bulk_refresh_external"
                      name="atai_bulk_refresh_external"
                      type="checkbox"
                      value="yes"
                      class="w-4 h-4 rounded border-gray-300 checked:bg-white text-primary-600 focus:ring-primary-600"
                      <?php checked( 'yes', get_option( 'atai_bulk_refresh_external' ) ); ?>
                    >
                  </div>
                  <div class="-mt-1 text-sm leading-6">
                    <label for="atai_bulk_refresh_external" class="font-medium text-gray-900"><?php esc_html_e( 'Process external images when refreshing posts and pages using the Bulk Action menu.', 'alttext-ai' ); ?></label>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <?php if ( ! $this->account || ! $this->account['whitelabel'] ) : ?>
          <div class="sm:grid sm:grid-cols-3 sm:gap-4 sm:py-4">
            <div class="text-sm font-semibold leading-6 text-gray-900" aria-hidden="true"><?php esc_html_e( 'AltText.ai Account', 'alttext-ai' ); ?></div>
            <div class="mt-4 sm:col-span-2 sm:mt-0">
              <div class="space-y-6 max-w-lg">
                <div>
                  <?php
                    printf(
                      wp_kses(
                        __( '<a href="%s" target="_blank" class="font-medium text-primary-600 hover:text-primary-500">Manage your account</a> and additional settings.', 'alttext-ai' ),
                        array( 'a' => array( 'href' => array(), 'target' => array(), 'class' => array() ) )
                      ),
                      esc_url( 'https://alttext.ai/account/edit?utm_source=wp&utm_medium=dl' )
                    );
                  ?>
                </div>
              </div>
            </div>
          </div>
          <?php endif; ?>

        </div>
      </div>

      <?php if (ATAI_Utility::has_woocommerce()) : ?>
      <div>
        <h2 class="text-base font-semibold leading-7 text-gray-900"><?php esc_html_e( 'WooCommerce Integration', 'alttext-ai' ); ?></h2>
        <p class="mt-1 max-w-2xl text-sm leading-6 text-gray-600"><?php esc_html_e( 'Control how AltText.ai works with WooCommerce.', 'alttext-ai' ); ?></p>

        <div class="pb-12 mt-4 space-y-10 border-b sm:pb-0 sm:space-y-0 border-gray-900/10">
          <div class="sm:grid sm:grid-cols-3 sm:gap-4 sm:py-4">
            <div class="text-sm font-semibold leading-6 text-gray-900" aria-hidden="true"><?php esc_html_e( 'Ecommerce Vision™', 'alttext-ai' ); ?></div>
            <div class="mt-4 sm:col-span-2 sm:mt-0">
              <div class="space-y-6 max-w-lg">
                <div class="flex relative gap-x-3">
                  <div class="flex items-center h-6">
                    <input
                      id="atai_ecomm"
                      name="atai_ecomm"
                      type="checkbox"
                      value="yes"
                      class="w-4 h-4 rounded border-gray-300 checked:bg-white text-primary-600 focus:ring-primary-600"
                      <?php checked( 'yes', get_option( 'atai_ecomm' ) ); ?>
                    >
                  </div>
                  <div class="-mt-1 text-sm leading-6">
                    <label for="atai_ecomm" class="font-medium text-gray-900"><?php esc_html_e( 'Use product name in generated alt text for WooCommerce product images.', 'alttext-ai' ); ?></label>
                  </div>
                </div>

                <div class="flex relative gap-x-3">
                  <div class="flex items-center h-6">
                    <input
                      id="atai_ecomm_title"
                      name="atai_ecomm_title"
                      type="checkbox"
                      value="yes"
                      class="w-4 h-4 rounded border-gray-300 checked:bg-white text-primary-600 focus:ring-primary-600"
                      <?php checked( 'yes', get_option( 'atai_ecomm_title' ) ); ?>
                    >
                  </div>
                  <div class="-mt-1 text-sm leading-6">
                    <label for="atai_ecomm_title" class="font-medium text-gray-900"><?php esc_html_e( 'Get product name from image title, not WooCommerce product.', 'alttext-ai' ); ?></label>
                    <p class="mt-1 text-gray-500">
                      Use this if your image titles are more descriptive than your WooCommerce product names.
                    </p>
                  </div>
                </div>

              </div>
            </div>
          </div>
        </div>
      </div>
      <?php endif; ?>

      <div>
        <h2 class="text-base font-semibold leading-7 text-gray-900">Technical Settings</h2>
        <p class="mt-1 max-w-2xl text-sm leading-6 text-gray-600">These settings are for more advanced technical features. Only modify these if needed.</p>

        <div class="pb-12 mt-10 space-y-10 border-b sm:pb-0 sm:space-y-0 border-gray-900/10">
          <div class="sm:grid sm:grid-cols-3 sm:gap-4 sm:py-4">
            <div class="text-sm font-semibold leading-6 text-gray-900" aria-hidden="true"><?php esc_html_e( 'Miscellaneous', 'alttext-ai' ); ?></div>
            <div class="mt-4 sm:col-span-2 sm:mt-0">
              <div class="space-y-6 max-w-lg">
                <div class="flex relative gap-x-3">
                  <div class="flex items-center h-6">
                    <input
                      id="atai_public"
                      name="atai_public"
                      type="checkbox"
                      value="yes"
                      class="w-4 h-4 rounded border-gray-300 checked:bg-white text-primary-600 focus:ring-primary-600"
                      <?php checked( 'yes', get_option( 'atai_public' ) ); ?>
                    >
                  </div>
                  <div class="-mt-1 text-sm leading-6">
                    <label for="atai_public" class="font-medium text-gray-900"><?php esc_html_e( 'This site is reachable over the public internet.', 'alttext-ai' ); ?></label>
                    <p class="text-gray-500">
                      Check to allow AltText.ai to fetch your images via URLs. If this site is private
                      then uncheck this box, and images will be uploaded to AltText.ai.
                    </p>
                  </div>
                </div>
                <div class="flex flex-col gap-2">
                  <div class="flex items-center">
                    <label for="atai_admin_capability" class="block text-sm font-medium text-gray-900"><?php esc_html_e( 'Who can access AltText.ai menu?', 'alttext-ai' ); ?></label>
                  </div>
                  <div class="mt-1">
                    <select
                      name="atai_admin_capability"
                      id="atai_admin_capability"
                      class="block w-full rounded-md border-0 py-1.5 text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 focus:ring-2 focus:ring-inset focus:ring-primary-600 sm:text-sm sm:leading-6"
                    >
                      <option value="manage_options" <?php selected( 'manage_options', get_option( 'atai_admin_capability', 'manage_options' ) ); ?>>
                        <?php esc_html_e( 'Administrators only (recommended)', 'alttext-ai' ); ?>
                      </option>
                      <option value="edit_others_posts" <?php selected( 'edit_others_posts', get_option( 'atai_admin_capability', 'manage_options' ) ); ?>>
                        <?php esc_html_e( 'Editors and Administrators', 'alttext-ai' ); ?>
                      </option>
                      <option value="publish_posts" <?php selected( 'publish_posts', get_option( 'atai_admin_capability', 'manage_options' ) ); ?>>
                        <?php esc_html_e( 'Authors, Editors and Administrators', 'alttext-ai' ); ?>
                      </option>
                      <option value="read" <?php selected( 'read', get_option( 'atai_admin_capability', 'manage_options' ) ); ?>>
                        <?php esc_html_e( 'All logged-in users', 'alttext-ai' ); ?>
                      </option>
                    </select>
                    <p class="mt-1 text-sm text-gray-500">
                      <?php esc_html_e( 'Control which user roles can access the AltText.ai menu.', 'alttext-ai' ); ?>
                    </p>
                  </div>
                </div>
                <div class="flex relative gap-x-3">
                  <div class="flex items-center h-6">
                    <input
                      id="atai_no_credit_warning"
                      name="atai_no_credit_warning"
                      type="checkbox"
                      value="yes"
                      class="w-4 h-4 rounded border-gray-300 checked:bg-white text-primary-600 focus:ring-primary-600"
                      <?php checked( 'yes', get_option( 'atai_no_credit_warning' ) ); ?>
                    >
                  </div>
                  <div class="-mt-1 text-sm leading-6">
                    <label for="atai_no_credit_warning" class="font-medium text-gray-900"><?php esc_html_e( 'Do not show warning when out of credits.', 'alttext-ai' ); ?></label>
                  </div>
                </div>
                <div class="flex relative gap-x-3">
                  <div class="flex items-center h-6">
                    <input
                      id="atai_wp_generate_metadata"
                      name="atai_wp_generate_metadata"
                      type="checkbox"
                      value="yes"
                      class="w-4 h-4 rounded border-gray-300 checked:bg-white text-primary-600 focus:ring-primary-600"
                      <?php checked( 'yes', get_option( 'atai_wp_generate_metadata' ) ); ?>
                    >
                  </div>
                  <div class="-mt-1 text-sm leading-6">
                    <label for="atai_wp_generate_metadata" class="font-medium text-gray-900"><?php esc_html_e( 'Allow WordPress to generate missing metadata for processed images.', 'alttext-ai' ); ?></label>
                    <p class="text-amber-600 text-sm mt-1">
                      <strong><?php esc_html_e( 'Performance Warning:', 'alttext-ai' ); ?></strong> <?php esc_html_e( 'This setting can cause bulk processing failures and server timeouts. Only enable if images were uploaded via FTP or are missing thumbnails.', 'alttext-ai' ); ?>
                      <strong><?php esc_html_e( 'Most users should leave this unchecked.', 'alttext-ai' ); ?></strong>
                    </p>
                  </div>
                </div>
                <div class="flex relative gap-x-3">
                  <div class="text-sm leading-6">
                    <label for="atai_timeout" class="font-medium text-gray-900"><?php esc_html_e( 'Timeout alt text generation requests after:', 'alttext-ai' ); ?></label>
                  </div>
                  <div class="flex items-center h-6">
                    <select id="atai_timeout" name="atai_timeout" class="block py-1.5 w-full text-gray-900 rounded-md border-0 ring-1 ring-inset ring-gray-300 shadow-sm sm:max-w-xs sm:text-sm sm:leading-6 focus:ring-2 focus:ring-inset focus:ring-primary-600">
                      <?php
                        foreach ( $timeout_values as $timeout_val ) {
                          $option_str = "<option value=\"$timeout_val\"";

                          if ( $timeout_secs === $timeout_val ) {
                            $option_str = $option_str . " selected";
                          }

                          $option_str = $option_str . ">$timeout_val</option>\n";
                          echo wp_kses( $option_str, array( 'option' => array( 'selected' => array(), 'value' => array() ) ) );
                        }
                      ?>
                    </select>
                  </div>
                  <div class="text-sm leading-6">
                    <label for="atai_timeout" class="font-medium text-gray-900"><?php esc_html_e( 'seconds', 'alttext-ai' ); ?></label>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="sm:grid sm:grid-cols-3 sm:gap-4 sm:py-4">
            <div class="text-sm font-semibold leading-6 text-gray-900" aria-hidden="true"><?php esc_html_e( 'Post/Page Refresh', 'alttext-ai' ); ?></div>
            <div class="mt-4 sm:col-span-2 sm:mt-0">
              <div class="space-y-6 max-w-lg">
                <div>
                  <label for="atai_refresh_src_attr" class="block text-sm leading-6 text-gray-600"><?php esc_html_e( 'Image tag attribute for source URL', 'alttext-ai' ); ?></label>
                  <div class="mt-2">
                    <input
                      type="text"
                      name="atai_refresh_src_attr"
                      id="atai_refresh_src_attr"
                      maxlength="128"
                      class="block py-1.5 w-full text-gray-900 rounded-md border-0 ring-1 ring-inset ring-gray-300 shadow-sm sm:text-sm sm:leading-6 focus:ring-2 focus:ring-inset placeholder:text-gray-400 focus:ring-primary-600"
                      value="<?php echo esc_html ( get_option( 'atai_refresh_src_attr' ) ); ?>"
                    >
                  </div>
                  <p class="mt-1 text-gray-500">
                    <?php esc_html_e( 'Specify the attribute on <img> tags which contains the image source URL. Typically needed if your posts use Javascript to lazy load images.', 'alttext-ai' ); ?>
                  </p>
                </div>
              </div>
            </div>
          </div>

          <div class="sm:grid sm:grid-cols-3 sm:gap-4 sm:py-4" id="atai_error_logs_container">
            <div class="text-sm font-semibold leading-6 text-gray-900" aria-hidden="true"><?php esc_html_e( 'Error Logs', 'alttext-ai' ); ?></div>
            <div class="mt-4 sm:col-span-2 sm:mt-0">
              <div class="space-y-6 max-w-lg">
                <div class="relative gap-x-3">
                  <div
                    id="atai_error_logs"
                    class="overflow-auto h-24 bg-white"
                    disabled
                  >
                    <?php echo wp_kses( ATAI_Utility::get_error_logs(), $wp_kses_args ); ?>
                  </div>
                  <a
                    href="<?php echo esc_url( add_query_arg( 'atai_action', 'clear-error-logs' ) ); ?>"
                    class="atai-button blue mt-4 cursor-pointer appearance-none no-underline shadow-sm"
                  >
                    <?php esc_html_e( 'Clear Logs', 'alttext-ai' ); ?>
                  </a>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </div>

    <input type="submit" name="submit" value="Save Changes" class="atai-button blue mt-4 cursor-pointer appearance-none no-underline shadow-sm">
  </form>
</div>
