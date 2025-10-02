(function () {
  'use strict';
  const { __ } = wp.i18n;
  window.atai = window.atai || { 
    postsPerPage: 1, 
    lastPostId: 0, 
    intervals: {}, 
    redirectUrl: '', 
    isProcessing: false, 
    retryCount: 0, 
    maxRetries: 2,
    progressCurrent: 0,
    progressSuccessful: 0,
    progressSkipped: 0,
    progressMax: 0
  };
  
  // Utility function to ensure progress state consistency
  window.atai.validateProgressState = function() {
    this.progressCurrent = isNaN(this.progressCurrent) ? 0 : Math.max(0, parseInt(this.progressCurrent, 10));
    this.progressSuccessful = isNaN(this.progressSuccessful) ? 0 : Math.max(0, parseInt(this.progressSuccessful, 10));
    this.progressSkipped = isNaN(this.progressSkipped) ? 0 : Math.max(0, parseInt(this.progressSkipped, 10));
    this.progressMax = isNaN(this.progressMax) ? 100 : Math.max(1, parseInt(this.progressMax, 10));
    this.lastPostId = isNaN(this.lastPostId) ? 0 : Math.max(0, parseInt(this.lastPostId, 10));
    this.retryCount = isNaN(this.retryCount) ? 0 : Math.max(0, parseInt(this.retryCount, 10));
  };
  
  // Single function to manage Start Over button visibility
  window.atai.updateStartOverButtonVisibility = function() {
    const staticStartOverButton = jQuery('#atai-static-start-over-button');
    const hasSession = localStorage.getItem('atai_bulk_progress') || this.isContinuation;
    const isProcessing = this.isProcessing;
    
    // During bulk processing, hide the button even if processing state fluctuates between batches
    const isBulkRunning = hasSession && this.progressCurrent > 0 && isProcessing;
    
    // Only show static Start Over button if there's a session AND not actively processing
    if (isBulkRunning || !hasSession) {
      staticStartOverButton.hide();
    } else {
      staticStartOverButton.show();
    }
  };

  // UI state management for processing
  window.atai.setProcessingState = function(isProcessing) {
    this.isProcessing = isProcessing;
  };

  // Memory cleanup function
  window.atai.cleanup = function() {
    // Clear intervals to prevent memory leaks
    if (this.intervals && typeof this.intervals === 'object') {
      Object.values(this.intervals).forEach(intervalId => {
        if (intervalId) clearInterval(intervalId);
      });
      this.intervals = {};
    }
    
    // Clear large objects
    if (this.errorHistory && this.errorHistory.length > 3) {
      this.errorHistory = this.errorHistory.slice(-3);
    }
    
    // Reset processing state and UI
    this.setProcessingState(false);
  };
  
  // Utility functions for button visibility management
  window.atai.hideButtons = function() {
    jQuery('[data-bulk-generate-start]').addClass('atai-hidden');
  };
  
  window.atai.showButtons = function() {
    jQuery('[data-bulk-generate-start]').removeClass('atai-hidden');
  };
  
  // Check if current URL parameters conflict with saved recovery session
  function hasUrlParameterConflicts(progress) {
    const urlParams = new URLSearchParams(window.location.search);
    
    // Check for bulk-select mode conflicts
    const currentAction = urlParams.get('atai_action');
    const currentBatchId = urlParams.get('atai_batch_id');
    const isBulkSelectUrl = currentAction === 'bulk-select-generate';
    const isBulkSelectSession = progress.mode === 'bulk-select';
    
    
    // If URL is bulk-select but session is not, or vice versa, it's a conflict
    if (isBulkSelectUrl !== isBulkSelectSession) {
      return true;
    }
    
    // If both are bulk-select but batch IDs don't match, it's a conflict
    if (isBulkSelectUrl && isBulkSelectSession) {
      if (currentBatchId && progress.batchId && currentBatchId !== progress.batchId) {
        return true;
      }
    }
    
    // Check each setting that could be changed via URL parameters (for normal mode)
    if (!isBulkSelectUrl) {
      if (urlParams.get('atai_mode') === 'all' && progress.mode !== 'all') return true;
      if (urlParams.get('atai_attached') === '1' && progress.onlyAttached !== '1') return true;
      if (urlParams.get('atai_attached') === '0' && progress.onlyAttached === '1') return true;
      if (urlParams.get('atai_only_new') === '1' && progress.onlyNew !== '1') return true;
      if (urlParams.get('atai_only_new') === '0' && progress.onlyNew === '1') return true;
      if (urlParams.get('atai_wc_products') === '1' && progress.wcProducts !== '1') return true;
      if (urlParams.get('atai_wc_products') === '0' && progress.wcProducts === '1') return true;
      if (urlParams.get('atai_wc_only_featured') === '1' && progress.wcOnlyFeatured !== '1') return true;
      if (urlParams.get('atai_wc_only_featured') === '0' && progress.wcOnlyFeatured === '1') return true;
    }
    
    return false;
  }


  // Consolidated session recovery function - runs on DOM ready
  function handleSessionRecovery() {
    try {
      const savedProgress = localStorage.getItem('atai_bulk_progress');
      
      
      if (!savedProgress) {
        window.atai.updateStartOverButtonVisibility();
        return;
      }
      
      const progress = JSON.parse(savedProgress);
      
      // Check if URL parameters conflict with saved session
      if (hasUrlParameterConflicts(progress)) {
        // Special handling for bulk-select sessions on wrong page
        if (progress.mode === 'bulk-select' && progress.batchId) {
          // Show helpful message instead of just clearing
          const bulkSelectUrl = 'admin.php?page=atai-bulk-generate&atai_action=bulk-select-generate&atai_batch_id=' + progress.batchId;
          
          const banner = jQuery(`
            <div class="border bg-gray-900/5 p-px rounded-lg mb-6 atai-bulk-select-notice">
              <div class="overflow-hidden rounded-lg bg-white">
                <div class="border-b border-gray-200 bg-white px-4 pt-5 pb-2 sm:px-6">
                  <h3 class="text-base font-semibold text-gray-900 my-0">Unfinished Bulk Selection</h3>
                </div>
                <div class="px-4 pb-4 sm:px-6">
                  <p class="text-sm text-gray-700 mb-0">
                    You have an unfinished bulk generation session from the Media Library with <strong>${progress.progressCurrent || 0} of ${progress.progressMax || 0} images processed</strong>.
                  </p>
                  <div class="mt-4 flex gap-3">
                    <a href="${bulkSelectUrl}" class="atai-button blue no-underline">
                      Continue Processing
                    </a>
                    <button type="button" class="atai-button black" onclick="localStorage.removeItem('atai_bulk_progress'); localStorage.removeItem('atai_error_history'); jQuery('.atai-bulk-select-notice').remove();">
                      Discard Session
                    </button>
                  </div>
                </div>
              </div>
            </div>
          `);
          
          jQuery('#bulk-generate-form').prepend(banner);
          
          return;
        }
        
        localStorage.removeItem('atai_bulk_progress');
        localStorage.removeItem('atai_error_history');
        window.atai.updateStartOverButtonVisibility();
        return;
      }
      
      // Set session state
      window.atai.lastPostId = progress.lastPostId || 0;
      window.atai.hasRecoveredSession = true;
      window.atai.isContinuation = true;
      
      // Restore processing settings
      if (progress.mode) window.atai.bulkGenerateMode = progress.mode;
      if (progress.batchId) window.atai.bulkGenerateBatchId = progress.batchId;
      if (progress.onlyAttached) window.atai.bulkGenerateOnlyAttached = progress.onlyAttached;
      if (progress.onlyNew) window.atai.bulkGenerateOnlyNew = progress.onlyNew;
      if (progress.wcProducts) window.atai.bulkGenerateWCProducts = progress.wcProducts;
      if (progress.wcOnlyFeatured) window.atai.bulkGenerateWCOnlyFeatured = progress.wcOnlyFeatured;
      if (progress.keywords) window.atai.bulkGenerateKeywords = progress.keywords;
      if (progress.negativeKeywords) window.atai.bulkGenerateNegativeKeywords = progress.negativeKeywords;
      
      // Restore progress state
      if (typeof progress.progressCurrent !== 'undefined') window.atai.progressCurrent = progress.progressCurrent;
      if (typeof progress.progressSuccessful !== 'undefined') window.atai.progressSuccessful = progress.progressSuccessful;
      if (typeof progress.progressMax !== 'undefined') window.atai.progressMax = progress.progressMax;
      if (typeof progress.progressSkipped !== 'undefined') window.atai.progressSkipped = progress.progressSkipped;
      
      // If progressMax is missing or 0, try to get it from the DOM
      if (!progress.progressMax || progress.progressMax === 0) {
        const maxFromDOM = jQuery('[data-bulk-generate-progress-bar]').data('max');
        if (maxFromDOM && maxFromDOM > 0) {
          window.atai.progressMax = maxFromDOM;
        }
      }
      
      // Restore form settings
      if (progress.mode === 'all') {
        jQuery('[data-bulk-generate-mode-all]').prop('checked', true);
      }
      if (progress.onlyAttached === '1') {
        jQuery('[data-bulk-generate-only-attached]').prop('checked', true);
      }
      if (progress.onlyNew === '1') {
        jQuery('[data-bulk-generate-only-new]').prop('checked', true);
      }
      if (progress.wcProducts === '1') {
        jQuery('[data-bulk-generate-wc-products]').prop('checked', true);
      }
      if (progress.wcOnlyFeatured === '1') {
        jQuery('[data-bulk-generate-wc-only-featured]').prop('checked', true);
      }
      if (progress.keywords && progress.keywords.length > 0) {
        jQuery('[data-bulk-generate-keywords]').val(progress.keywords.join(', '));
      }
      if (progress.negativeKeywords && progress.negativeKeywords.length > 0) {
        jQuery('[data-bulk-generate-negative-keywords]').val(progress.negativeKeywords.join(', '));
      }
      
      // Update button text and enable it
      const buttonEl = jQuery('[data-bulk-generate-start]');
      if (buttonEl.length) {
        const processed = progress.progressCurrent || 0;
        const total = progress.progressMax || 0;
        const remaining = Math.max(0, total - processed);
        
        if (remaining > 0) {
          const newText = __('Continue: %d remaining images', 'alttext-ai').replace('%d', remaining);
          buttonEl.text(newText);
          
          // Enable the button
          buttonEl
            .prop('disabled', false)
            .removeAttr('disabled')
            .removeClass('disabled')
            .addClass('blue')
            .removeAttr('style');
        }
      }
      
      // Show recovery notification banner
      jQuery('.atai-recovery-banner').remove();
      showRecoveryNotification(progress);
      
      // Update progress display elements
      if (window.atai.progressMaxEl && window.atai.progressMaxEl.length) {
        window.atai.progressMaxEl.text(window.atai.progressMax);
      }
      if (window.atai.progressCurrentEl && window.atai.progressCurrentEl.length) {
        window.atai.progressCurrentEl.text(window.atai.progressCurrent);
      }
      if (window.atai.progressSuccessfulEl && window.atai.progressSuccessfulEl.length) {
        window.atai.progressSuccessfulEl.text(window.atai.progressSuccessful);
      }
      
      // Update Start Over button visibility
      window.atai.updateStartOverButtonVisibility();
      
    } catch (e) {
      // If localStorage is corrupted, clear it
      localStorage.removeItem('atai_bulk_progress');
      localStorage.removeItem('atai_error_history');
      window.atai.updateStartOverButtonVisibility();
    }
  }

  // Initialize session recovery when DOM is ready
  jQuery(document).ready(function() {
    // Initialize DOM element references first so they're available during session recovery
    window.atai.progressBarEl = jQuery('[data-bulk-generate-progress-bar]');
    window.atai.progressMaxEl = jQuery('[data-bulk-generate-progress-max]');
    window.atai.progressCurrentEl = jQuery('[data-bulk-generate-progress-current]');
    window.atai.progressSuccessfulEl = jQuery('[data-bulk-generate-progress-successful]');
    
    // Then handle session recovery
    handleSessionRecovery();
  });
  
  function showRecoveryNotification(progress) {
    // Prevent multiple banners
    if (window.atai.recoveryBannerShown) {
      return;
    }
    window.atai.recoveryBannerShown = true;
    
    const timeSince = Math.round((Date.now() - progress.timestamp) / 1000 / 60); // minutes
    const baseMessage = timeSince < 5 
      ? __('Previous bulk processing session found. The form has been restored to continue where you left off.', 'alttext-ai')
      : __('Previous bulk processing session found from %d minutes ago. The form has been restored to continue where you left off.', 'alttext-ai').replace('%d', timeSince);
    
    const resumeMessage = progress.lastPostId > 0 
      ? __(' Processing will resume after image ID %d.', 'alttext-ai').replace('%d', progress.lastPostId)
      : '';
    
    const message = baseMessage + resumeMessage;
    
    // Create a clean notification banner with Start Over button
    const banner = jQuery(`
      <div class="border bg-gray-900/5 p-px rounded-lg mb-6 atai-recovery-banner">
        <div class="overflow-hidden rounded-lg bg-white">
          <div class="border-b border-gray-200 bg-white px-4 pt-5 pb-2 sm:px-6">
            <h3 class="text-base font-semibold text-gray-900 my-0">Previous Bulk Processing Session Found</h3>
          </div>
          <div class="px-4 pb-4 sm:px-6">
            <p class="text-sm text-gray-700 mb-0">
              ${message}
            </p>
            <div class="mt-4 flex gap-3">
              <button type="button" class="atai-button blue" data-bulk-generate-start>
                Continue Processing
              </button>
              <button type="button" class="atai-button black" id="atai-banner-start-over-button">
                ${__('Start Over', 'alttext-ai')}
              </button>
            </div>
          </div>
        </div>
      </div>
    `);
    
    // Insert banner at the top of the bulk generate form
    jQuery('#bulk-generate-form').prepend(banner);
    
    // Handle Start Over button click using document delegation for dynamic content
    jQuery(document).on('click', '.atai-recovery-banner #atai-banner-start-over-button', function() {
      try {
        localStorage.removeItem('atai_bulk_progress');
        localStorage.removeItem('atai_error_history');
        
        // Complete memory cleanup
        window.atai.cleanup();
        
        // Reset all window.atai state
        window.atai.lastPostId = 0;
        window.atai.hasRecoveredSession = false;
        window.atai.isContinuation = false;
        window.atai.progressCurrent = 0;
        window.atai.progressSuccessful = 0;
        window.atai.progressSkipped = 0;
        window.atai.retryCount = 0;
        
        // Reset processing UI state
        window.atai.setProcessingState(false);
        
        // Remove the recovery banner
        jQuery('.atai-recovery-banner').remove();
        
        // Restore original button text
        const buttonEl = jQuery('[data-bulk-generate-start]');
        if (buttonEl.length) {
          const defaultText = buttonEl.data('default-text') || __('Generate Alt Text', 'alttext-ai');
          buttonEl.text(defaultText);
          buttonEl.removeClass('disabled').prop('disabled', false);
          
          // Ensure button styling is also reset
          buttonEl.css({
            'background-color': '',
            'color': '',
            'border-color': ''
          });
        }
      } catch (e) {
        console.error('AltText.ai: Error clearing recovery session:', e);
      }
    });
    
    // Handle dismiss button (WordPress standard) - clear localStorage when dismissed
    banner.on('click', '.notice-dismiss', function() {
      try {
        localStorage.removeItem('atai_bulk_progress');
        
        // Reset continuation flag so main button works normally
        window.atai.lastPostId = 0;
        window.atai.hasRecoveredSession = false;
        window.atai.isContinuation = false;
        window.atai.progressCurrent = 0;
        window.atai.progressSuccessful = 0;
        window.atai.retryCount = 0;
        
        // Restore original button text
        const buttonEl = jQuery('[data-bulk-generate-start]');
        if (buttonEl.length) {
          // Restore original button text based on image count
          const imageCount = buttonEl.closest('.wrap').find('[data-bulk-generate-progress-bar]').data('max') || 0;
          if (imageCount > 0) {
            const originalText = imageCount === 1 
              ? __('Generate Alt Text for %d Image', 'alttext-ai').replace('%d', imageCount)
              : __('Generate Alt Text for %d Images', 'alttext-ai').replace('%d', imageCount);
            buttonEl.text(originalText);
          }
        }
      } catch (e) {
        // Ignore localStorage errors
      }
      banner.remove();
    });
  }
  
  function isPostDirty() {
    try {
      // Check for Gutenberg
      if (window.wp && wp.data && wp.blocks) {
        return wp.data.select('core/editor').isEditedPostDirty();
      }
      
      // Check for Classic Editor (TinyMCE)
      if (window.tinymce && tinymce.editors) {
        for (let editorId in tinymce.editors) {
          const editor = tinymce.editors[editorId];
          if (editor && editor.isDirty && editor.isDirty()) {
            return true;
          }
        }
      }
      
      // Check for any forms with unsaved changes
      const forms = document.querySelectorAll('form');
      for (let form of forms) {
        if (form.classList.contains('dirty') || form.dataset.dirty === 'true') {
          return true;
        }
      }
    } catch (error) {
      console.error("Error checking if post is dirty:", error);
      return true;
    }

    // Assume clean if no editor detected
    return false;
  }

  function editHistoryAJAX(attachmentId, altText = '') {
    if (!attachmentId) {
      const error = new Error(__('Attachment ID is missing', 'alttext-ai'));
      console.error("editHistoryAJAX error:", error);
      return Promise.reject(error);
    }

    return new Promise((resolve, reject) => {
      jQuery.ajax({
        type: 'post',
        dataType: 'json',
        data: {
          action: 'atai_edit_history',
          security: wp_atai.security_edit_history,
          attachment_id: attachmentId,
          alt_text: altText
        },
        url: wp_atai.ajax_url,
        success: function (response) {
          resolve(response);
        },
        error: function (response) {
          const error = new Error('AJAX request failed');
          console.error("editHistoryAJAX failed:", error);
          reject(error);
        }
      });
    });
  }

  function singleGenerateAJAX(attachmentId, keywords = []) {
    if (!attachmentId) {
      const error = new Error(__('Attachment ID is missing', 'alttext-ai'));
      console.error("singleGenerateAJAX error:", error);
      return Promise.reject(error);
    }

    return new Promise((resolve, reject) => {
      jQuery.ajax({
        type: 'post',
        dataType: 'json',
        data: {
          action: 'atai_single_generate',
          security: wp_atai.security_single_generate,
          attachment_id: attachmentId,
          keywords: keywords
        },
        url: wp_atai.ajax_url,
        success: function (response) {
          resolve(response);
        },
        error: function (response) {
          const error = new Error('AJAX request failed');
          console.error("singleGenerateAJAX failed:", error);
          reject(error);
        }
      });
    });
  }

  function bulkGenerateAJAX() {
    if (window.atai.isProcessing) {
      return;
    }
    window.atai.setProcessingState(true);
    
    // Hide Start Over button for entire bulk operation
    jQuery('#atai-static-start-over-button').hide();
    
    
    jQuery.ajax({
      type: 'post',
      dataType: 'json',
      data: {
        action: 'atai_bulk_generate',
        security: wp_atai.security_bulk_generate,
        posts_per_page: window.atai.postsPerPage,
        last_post_id: window.atai.lastPostId,
        keywords: window.atai.bulkGenerateKeywords,
        negativeKeywords: window.atai.bulkGenerateNegativeKeywords,
        mode: window.atai.bulkGenerateMode,
        onlyAttached: window.atai.bulkGenerateOnlyAttached,
        onlyNew: window.atai.bulkGenerateOnlyNew,
        wcProducts: window.atai.bulkGenerateWCProducts,
        wcOnlyFeatured: window.atai.bulkGenerateWCOnlyFeatured,
        batchId: window.atai.bulkGenerateBatchId,
      },
      url: wp_atai.ajax_url,
      success: function (response) {
        try {
          // Check for URL access error - stop and show clear error message
          if (response.action_required === 'url_access_fix') {
            showUrlAccessErrorNotification(response.message);
            return;
          }

          // Reset retry count on successful response (after server comes back up)
          window.atai.retryCount = 0;
          
          // Validate state before processing
          window.atai.validateProgressState();
          
          // Update progress heading if it was showing retry message
          if (window.atai.progressHeading.length) {
            const currentHeading = window.atai.progressHeading.text();
            if (currentHeading.includes('Retrying') || currentHeading.includes('Server error')) {
              window.atai.progressHeading.text(__('Processing images...', 'alttext-ai'));
            }
          }
          
          // Ensure progress values are initialized before adding
          window.atai.progressCurrent = (window.atai.progressCurrent || 0) + (response.process_count || 0);
          window.atai.progressSuccessful = (window.atai.progressSuccessful || 0) + (response.success_count || 0);
          
          
          // Handle skipped images count if present
          if (typeof response.skipped_count !== 'undefined') {
            window.atai.progressSkipped = (window.atai.progressSkipped || 0) + response.skipped_count;
            if (window.atai.progressSkippedEl) {
              window.atai.progressSkippedEl.text(window.atai.progressSkipped);
            }
          }
          
          window.atai.lastPostId = response.last_post_id;
  
          if (window.atai.progressBarEl.length) {
            window.atai.progressBarEl.data('current', window.atai.progressCurrent);
          }
          if (window.atai.progressLastPostId.length) {
            window.atai.progressLastPostId.text(window.atai.lastPostId);
          }
          
          
          // Save progress to localStorage with all processing settings
          try {
            const progress = {
              lastPostId: window.atai.lastPostId,
              timestamp: Date.now(),
              // Save all processing settings to ensure continuation uses same parameters
              mode: window.atai.bulkGenerateMode,
              batchId: window.atai.bulkGenerateBatchId,
              onlyAttached: window.atai.bulkGenerateOnlyAttached,
              onlyNew: window.atai.bulkGenerateOnlyNew,
              wcProducts: window.atai.bulkGenerateWCProducts,
              wcOnlyFeatured: window.atai.bulkGenerateWCOnlyFeatured,
              keywords: window.atai.bulkGenerateKeywords,
              negativeKeywords: window.atai.bulkGenerateNegativeKeywords,
              // Save complete progress bar state
              progressCurrent: window.atai.progressCurrent,
              progressSuccessful: window.atai.progressSuccessful,
              progressMax: window.atai.progressMax,
              progressSkipped: window.atai.progressSkipped || 0
            };
            localStorage.setItem('atai_bulk_progress', JSON.stringify(progress));
          } catch (e) {
            // Ignore localStorage errors
          }
          if (window.atai.progressCurrentEl.length) {
            window.atai.progressCurrentEl.text(window.atai.progressCurrent);
          }
          if (window.atai.progressSuccessfulEl.length) {
            window.atai.progressSuccessfulEl.text(window.atai.progressSuccessful);
          }
  
          const percentage = (window.atai.progressCurrent * 100) / window.atai.progressMax;
          if (window.atai.progressBarEl.length) {
            window.atai.progressBarEl.css('width', percentage + '%');
          }
          if (window.atai.progressPercent.length) {
            window.atai.progressPercent.text(Math.round(percentage) + '%');
          }
  
          if (response.recursive) {
            // Reset retry count on successful batch
            window.atai.retryCount = 0;
            // Reset flag before recursive call to allow next batch
            window.atai.setProcessingState(false);
            setTimeout(() => {
              bulkGenerateAJAX();
            }, 100);
          } else {
            // Reset retry count on completion
            window.atai.retryCount = 0;
            window.atai.setProcessingState(false);
            
            // Show Start Over button only if there's still a session to clear
            if (localStorage.getItem('atai_bulk_progress')) {
              jQuery('#atai-static-start-over-button').show();
            }
            
            if (window.atai.progressButtonCancel.length) {
              window.atai.progressButtonCancel.hide();
            }
            if (window.atai.progressBarWrapper.length) {
              window.atai.progressBarWrapper.hide();
            }
            if (window.atai.progressButtonFinished.length) {
              window.atai.progressButtonFinished.show();
            }
            if (window.atai.progressHeading.length) {
              window.atai.progressHeading.text(response.message || __('Update complete!', 'alttext-ai'));
            }
            
            // Clean up processing animations when complete
            jQuery('[data-bulk-generate-progress-bar]').removeClass('atai-progress-pulse');
            // Show subtitle with skip reasons if available
            const progressSubtitle = jQuery('[data-bulk-generate-progress-subtitle]');
            if (progressSubtitle.length) {
              let subtitleText = response.subtitle && response.subtitle.trim() ? response.subtitle : '';
              
              // Add URL access errors to skip reasons if any occurred
              if (window.atai.urlAccessErrorCount > 0) {
                const urlErrorText = window.atai.urlAccessErrorCount === 1 
                  ? '1 URL access failure' 
                  : `${window.atai.urlAccessErrorCount} URL access failures`;
                
                const settingsUrl = `${wp_atai.settings_page_url}#atai_error_logs_container`;
                const urlErrorWithLink = `${urlErrorText} (<a href="${settingsUrl}" target="_blank" style="color: inherit; text-decoration: underline;">see error logs for details</a>)`;
                
                if (subtitleText) {
                  subtitleText += `, ${urlErrorWithLink}`;
                } else {
                  subtitleText = `Skip reasons: ${urlErrorWithLink}`;
                }
              }
              
              if (subtitleText) {
                progressSubtitle.attr('data-skipped', '').find('span').html(subtitleText);
                progressSubtitle.show();
              } else {
                progressSubtitle.hide();
              }
            }
            window.atai.redirectUrl = response?.redirect_url;
            
            // Clear progress from localStorage when complete
            try {
              localStorage.removeItem('atai_bulk_progress');
            } catch (e) {
              // Ignore localStorage errors
            }
          }
        } catch (error) {
          console.error("bulkGenerateAJAX error:", error);
          handleBulkGenerationError(error);
        }
      },
      error: function (response) {
        try {
          const error = new Error('AJAX request failed during bulk generation');
          console.error("bulkGenerateAJAX AJAX failed:", error.message);
          handleBulkGenerationError(error, response);
        } catch (e) {
          // Fallback if console.error fails
          const error = new Error('AJAX request failed during bulk generation');
          handleBulkGenerationError(error, response);
        }
      }
    });
  }

  function handleBulkGenerationError(error, response) {
    // Check if this is a retryable server error - be more aggressive about retrying
    const isServerError = response && (response.status >= 500 || response.status === 0 || response.status === 408 || response.status === 405 || response.status === 502 || response.status === 503 || response.status === 504);
    const hasTimeoutError = error.message.includes('timeout') || error.message.includes('network') || error.message.includes('failed');
    const isAjaxFailure = error.message.includes('AJAX request failed');
    const isRetryable = isServerError || hasTimeoutError || isAjaxFailure;
    
    const errorDetails = {
      errorMessage: error.message,
      errorType: error.name || 'Unknown',
      responseStatus: response?.status,
      responseStatusText: response?.statusText,
      responseText: response?.responseText?.substring(0, 500),
      ajaxSettings: {
        url: response?.responseURL || 'unknown',
        method: 'POST',
        timeout: response?.timeout || 'default'
      },
      imagesProcessed: window.atai.progressCurrent || 0,
      batchSize: window.atai.postsPerPage || 5,
      memoryUsage: performance.memory ? Math.round(performance.memory.usedJSHeapSize / 1048576) + 'MB' : 'unknown',
      errorClassification: {
        isServerError,
        hasTimeoutError,
        isAjaxFailure,
        isRetryable
      },
      retryCount: window.atai.retryCount,
      maxRetries: window.atai.maxRetries,
      timestamp: Date.now()
    };
    
    console.error('Bulk generation error details:', errorDetails);
    
    // Store error for debugging - keep last 5 errors
    if (!window.atai.errorHistory) {
      window.atai.errorHistory = [];
    }
    window.atai.errorHistory.push(errorDetails);
    if (window.atai.errorHistory.length > 5) {
      window.atai.errorHistory.shift();
    }
    
    // Save to localStorage for persistence across page reloads
    try {
      localStorage.setItem('atai_error_history', JSON.stringify(window.atai.errorHistory));
    } catch (e) {
      // If localStorage fails, limit in-memory storage to prevent memory leaks
      if (window.atai.errorHistory.length > 10) {
        window.atai.errorHistory = window.atai.errorHistory.slice(-3); // Keep only last 3
      }
    }

    
    if (isRetryable && window.atai.retryCount < window.atai.maxRetries) {
      window.atai.retryCount++;
      
      console.error(`Retrying bulk generation (attempt ${window.atai.retryCount}/${window.atai.maxRetries})`);
      
      // Update UI to show retry status
      if (window.atai.progressHeading.length) {
        const retryText = __('Server error - retrying in 2 seconds...', 'alttext-ai');
        window.atai.progressHeading.text(retryText);
      }
      
      // Retry after simple 2-second delay
      setTimeout(() => {
        console.error('Executing retry attempt', window.atai.retryCount);
        if (window.atai.progressHeading.length) {
          const retryingText = __('Retrying bulk generation...', 'alttext-ai');
          window.atai.progressHeading.text(retryingText);
        }
        // Reset processing flag before retry to allow the new request
        window.atai.setProcessingState(false);
        bulkGenerateAJAX();
      }, 2000);
      
    } else {
      // Max retries reached or non-retryable error - stop processing
      window.atai.setProcessingState(false);
      window.atai.retryCount = 0; // Reset for next bulk operation
      
      // Show Start Over button if there's a session to clear
      if (localStorage.getItem('atai_bulk_progress')) {
        jQuery('#atai-static-start-over-button').show();
      }
      
      // Clean up memory
      window.atai.cleanup();
      
      if (window.atai.progressButtonCancel.length) {
        window.atai.progressButtonCancel.hide();
      }
      if (window.atai.progressBarWrapper.length) {
        window.atai.progressBarWrapper.hide();
      }
      if (window.atai.progressButtonFinished.length) {
        window.atai.progressButtonFinished.show();
      }
      if (window.atai.progressHeading.length) {
        const message = window.atai.retryCount >= window.atai.maxRetries 
          ? __('Update stopped after multiple server errors. Your progress has been saved - you can restart to continue.', 'alttext-ai')
          : __('Update stopped due to an error. Your progress has been saved - you can restart to continue.', 'alttext-ai');
        window.atai.progressHeading.text(message);
      }
      
      alert(__('Bulk generation encountered an error. Your progress has been saved.', 'alttext-ai'));
    }
  }

  function enrichPostContentAJAX(postId, overwrite = false, processExternal = false, keywords = []) {
    if (!postId) {
      const error = new Error(__('Post ID is missing', 'alttext-ai'));
      console.error("enrichPostContentAJAX error:", error);
      return Promise.reject(error);
    }

    return new Promise((resolve, reject) => {
      jQuery.ajax({
        type: 'post',
        dataType: 'json',
        data: {
          action: 'atai_enrich_post_content',
          security: wp_atai.security_enrich_post_content,
          post_id: postId,
          overwrite: overwrite,
          process_external: processExternal,
          keywords: keywords
        },
        url: wp_atai.ajax_url,
        success: function (response) {
          resolve(response);
        },
        error: function (response) {
          const error = new Error('AJAX request failed');
          console.error("enrichPostContentAJAX failed:", error);
          reject(error);
        }
      });
    });
  }

  function extractKeywords(content) {
    return content.split(',').map(function (item) {
      return item.trim();
    }).filter(function (item) {
      return item.length > 0;
    }).slice(0, 6);
  }

  jQuery('[data-edit-history-trigger]').on('click', async function () {
    const triggerEl = this;
    const attachmentId = triggerEl.dataset.attachmentId;
    const inputEl = document.getElementById('edit-history-input-' + attachmentId);
    const altText = inputEl.value.replace(/\n/g, '');

    triggerEl.disabled = true;

    try {
      const response = await editHistoryAJAX(attachmentId, altText);
      if (response.status !== 'success') {
        alert(__('Unable to update alt text for this image.', 'alttext-ai'));
      }

      const successEl = document.getElementById('edit-history-success-' + attachmentId);
      successEl.classList.remove('hidden');
      setTimeout(() => {
        successEl.classList.add('hidden');
      }, 2000);
    } catch (error) {
      alert(__('An error occurred while updating the alt text.', 'alttext-ai'));
    } finally {
      triggerEl.disabled = false;
    }
  });

  // Handle static Start Over button click
  jQuery('#atai-static-start-over-button').on('click', function() {
    try {
      localStorage.removeItem('atai_bulk_progress');
      localStorage.removeItem('atai_error_history');
      
      // Complete memory cleanup
      window.atai.cleanup();
      
      // Reset all window.atai state
      window.atai.lastPostId = 0;
      window.atai.hasRecoveredSession = false;
      window.atai.isContinuation = false;
      window.atai.progressCurrent = 0;
      window.atai.progressSuccessful = 0;
      window.atai.progressSkipped = 0;
      window.atai.progressMax = 0;
      window.atai.recoveryBannerShown = false;
      
      // Remove any recovery banner
      jQuery('.atai-recovery-banner').remove();
      
      // Update the UI to normal state
      location.reload();
      
    } catch (error) {
      console.error('Error in Start Over button handler:', error);
      // Even if there's an error, reload to reset the state
      location.reload();
    }
  });

  jQuery('[data-bulk-generate-start]').on('click', function () {
    const action = getQueryParam('atai_action') || 'normal';
    const batchId = getQueryParam('atai_batch_id') || 0;

    if (action === 'bulk-select-generate' && !batchId) {
      alert(__('Invalid batch ID', 'alttext-ai'));
    }

    window.atai['bulkGenerateKeywords'] = extractKeywords(jQuery('[data-bulk-generate-keywords]').val() ?? '');
    window.atai['bulkGenerateNegativeKeywords'] = extractKeywords(jQuery('[data-bulk-generate-negative-keywords]').val() ?? '');
    window.atai['progressWrapperEl'] = jQuery('[data-bulk-generate-progress-wrapper]');
    window.atai['progressHeading'] = jQuery('[data-bulk-generate-progress-heading]');
    window.atai['progressBarWrapper'] = jQuery('[data-bulk-generate-progress-bar-wrapper]');
    window.atai['progressBarEl'] = jQuery('[data-bulk-generate-progress-bar]');
    window.atai['progressPercent'] = jQuery('[data-bulk-generate-progress-percent]');
    window.atai['progressLastPostId'] = jQuery('[data-bulk-generate-last-post-id]');
    window.atai['progressCurrentEl'] = jQuery('[data-bulk-generate-progress-current]');
    // Only initialize from HTML if not already set by recovery
    if (typeof window.atai['progressCurrent'] === 'undefined') {
      window.atai['progressCurrent'] = window.atai.progressBarEl.length ? window.atai.progressBarEl.data('current') : 0;
    }
    window.atai['progressSuccessfulEl'] = jQuery('[data-bulk-generate-progress-successful]');
    if (typeof window.atai['progressSuccessful'] === 'undefined') {
      window.atai['progressSuccessful'] = window.atai.progressBarEl.length ? window.atai.progressBarEl.data('successful') : 0;
    }
    window.atai['progressSkippedEl'] = jQuery('[data-bulk-generate-progress-skipped]');
    if (typeof window.atai['progressSkipped'] === 'undefined') {
      window.atai['progressSkipped'] = 0;
    }
    // Set progressMax from DOM if not already set by recovery session
    if (!window.atai.hasRecoveredSession || window.atai['progressMax'] === 0) {
      window.atai['progressMax'] = window.atai.progressBarEl.length ? window.atai.progressBarEl.data('max') : 100;
    }
    window.atai['progressButtonCancel'] = jQuery('[data-bulk-generate-cancel]');
    window.atai['progressButtonFinished'] = jQuery('[data-bulk-generate-finished]');

    if (action === 'bulk-select-generate') {
      window.atai['bulkGenerateMode'] = 'bulk-select';
      window.atai['bulkGenerateBatchId'] = batchId;
    } else {
      window.atai['bulkGenerateMode'] = jQuery('[data-bulk-generate-mode-all]').is(':checked') ? 'all' : 'missing';
      window.atai['bulkGenerateOnlyAttached'] = jQuery('[data-bulk-generate-only-attached]').is(':checked') ? '1' : '0';
      window.atai['bulkGenerateOnlyNew'] = jQuery('[data-bulk-generate-only-new]').is(':checked') ? '1' : '0';
      window.atai['bulkGenerateWCProducts'] = jQuery('[data-bulk-generate-wc-products]').is(':checked') ? '1' : '0';
      window.atai['bulkGenerateWCOnlyFeatured'] = jQuery('[data-bulk-generate-wc-only-featured]').is(':checked') ? '1' : '0';
    }

    jQuery('#bulk-generate-form').hide();
    // Explicitly hide the recovery buttons when form is hidden using CSS class
    window.atai.hideButtons();
    if (window.atai.progressWrapperEl.length) {
      window.atai.progressWrapperEl.show();
      
      // Add processing animations to show the page is alive
      const progressHeading = jQuery('[data-bulk-generate-progress-heading]');
      if (progressHeading.length) {
        progressHeading.html(__('Processing Images', 'alttext-ai') + '<span class="atai-processing-dots"></span>');
      }
      
      // Add pulse animation to the progress bar
      const progressBar = jQuery('[data-bulk-generate-progress-bar]');
      if (progressBar.length) {
        progressBar.addClass('atai-progress-pulse');
      }
    }

    // If continuing from localStorage, restore the exact progress state
    if (window.atai.isContinuation) {
      const lastId = window.atai.lastPostId || 0;
      
      // Restore the exact progress bar state from localStorage
      if (window.atai.progressBarEl.length) {
        window.atai.progressBarEl.data('current', window.atai.progressCurrent);
        window.atai.progressBarEl.data('successful', window.atai.progressSuccessful);
        window.atai.progressBarEl.data('max', window.atai.progressMax);
        
        // Update progress display elements to show current state
        if (window.atai.progressCurrentEl.length) {
          window.atai.progressCurrentEl.text(window.atai.progressCurrent);
        }
        if (window.atai.progressSuccessfulEl.length) {
          window.atai.progressSuccessfulEl.text(window.atai.progressSuccessful);
        }
        if (window.atai.progressSkippedEl.length) {
          window.atai.progressSkippedEl.text(window.atai.progressSkipped || 0);
        }
        
        // Update progress bar visual
        const percentage = (window.atai.progressCurrent * 100) / window.atai.progressMax;
        window.atai.progressBarEl.css('width', percentage + '%');
        if (window.atai.progressPercent.length) {
          window.atai.progressPercent.text(Math.round(percentage) + '%');
        }
      }
      
      // Add a clean continuation banner above the form (inside max-w-6xl wrapper)
      const continuationBanner = jQuery('<div class="notice notice-success" style="margin: 15px 0; padding: 10px 15px; border-left: 4px solid #00a32a;"><p style="margin: 0; font-weight: 500;"><span class="dashicons dashicons-update" style="margin-right: 5px;"></span>' + 
        __('Resuming from where you left off - starting after image ID %d', 'alttext-ai').replace('%d', lastId) + '</p></div>');
      
      jQuery('.wrap.max-w-6xl').find('#bulk-generate-form').before(continuationBanner);
      
      // Update progress heading when processing starts
      if (window.atai.progressHeading.length) {
        window.atai.progressHeading.text(__('Continuing bulk generation from image ID %d...', 'alttext-ai').replace('%d', lastId));
      }
    }

    bulkGenerateAJAX();
  });

  jQuery('[data-bulk-generate-mode-all]').on('change', function () {
    window.location.href = this.dataset.url;
  });

  jQuery('[data-bulk-generate-only-attached]').on('change', function () {
    window.location.href = this.dataset.url;
  });

  jQuery('[data-bulk-generate-only-new]').on('change', function () {
    window.location.href = this.dataset.url;
  });

  jQuery('[data-bulk-generate-wc-products]').on('change', function () {
    window.location.href = this.dataset.url;
  });

  jQuery('[data-bulk-generate-wc-only-featured]').on('change', function () {
    window.location.href = this.dataset.url;
  });

  // Handle permanent Start Over button click
  jQuery(document).on('click', '#atai-start-over-button', function() {
    try {
      // Clear all localStorage progress data
      localStorage.removeItem('atai_bulk_progress');
      localStorage.removeItem('atai_error_history');
      
      // Complete memory cleanup
      window.atai.cleanup();
      
      // Reset all window.atai state
      window.atai.lastPostId = 0;
      window.atai.hasRecoveredSession = false;
      window.atai.isContinuation = false;
      window.atai.remainingImages = null;
      window.atai.progressCurrent = 0;
      window.atai.progressSuccessful = 0;
      window.atai.progressSkipped = 0;
      window.atai.retryCount = 0;
      
      // Update button visibility after clearing session
      window.atai.updateStartOverButtonVisibility();
      
      // Reload page to reset UI
      window.location.reload();
    } catch (e) {
      // Still reload page even if localStorage operations fail
      window.location.reload();
    }
  });


  jQuery('[data-post-bulk-generate]').on('click', async function (event) {
    if (this.getAttribute('href') !== '#atai-bulk-generate') {
      return;
    }

    event.preventDefault();

    if (isPostDirty()) {
      // Ask for consent
      const consent = confirm(__('[AltText.ai] Make sure to save any changes before proceeding -- any unsaved changes will be lost. Are you sure you want to continue?', 'alttext-ai'));

      // If user doesn't consent, return
      if (!consent) {
        return;
      }
    }

    const postId = document.getElementById('post_ID')?.value;
    const buttonLabel = this.querySelector('span');
    const updateNotice = this.nextElementSibling;
    const buttonLabelText = buttonLabel.innerText;
    const overwrite = document.querySelector('[data-post-bulk-generate-overwrite]')?.checked || false;
    const processExternal = document.querySelector('[data-post-bulk-generate-process-external]')?.checked || false;
    const keywordsCheckbox = document.querySelector('[data-post-bulk-generate-keywords-checkbox]');
    const keywordsTextField = document.querySelector('[data-post-bulk-generate-keywords]');
    const keywords = keywordsCheckbox?.checked ? extractKeywords(keywordsTextField?.value) : [];

    if (!postId) {
      updateNotice.innerText = __('This is not a valid post.', 'alttext-ai');
      updateNotice.classList.add('atai-update-notice--error');
      return;
    }

    try {
      this.classList.add('disabled');
      buttonLabel.innerText = __('Processing...', 'alttext-ai');
      
      // Generate alt text for all images in the post
      const response = await enrichPostContentAJAX(postId, overwrite, processExternal, keywords);

      if (response.success) {
        window.location.reload();
      } else {
        throw new Error(__('Unable to generate alt text. Check error logs for details.', 'alttext-ai'));
      }
    } catch (error) {
      updateNotice.innerText = error.message || __('An error occurred.', 'alttext-ai');
      updateNotice.classList.add('atai-update-notice--error');
    } finally {
      this.classList.remove('disabled');
      buttonLabel.innerText = buttonLabelText;
    }
  });  

  document.addEventListener('DOMContentLoaded', () => {
    // If not using Gutenberg, return
    if (!wp?.blocks) {
      return;
    }

    // Fetch the transient message via AJAX
    jQuery.ajax({
      url: wp_atai.ajax_url,
      type: 'GET',
      data: {
        action: 'atai_check_enrich_post_content_transient',
        security: wp_atai.security_enrich_post_content_transient,
      },
      success: function (response) {
        if (!response?.success) {
          return;
        }

        wp.data.dispatch('core/notices').createNotice(
          'success',
          response.data.message,
          { isDismissible: true }
        );
      }
    });
  });

  /**
   * Empty API key input when clicked "Clear API Key" button
   */
  jQuery('[name="handle_api_key"]').on('click', function () {
    if (this.value === 'Clear API Key') {
      jQuery('[name="atai_api_key"]').val('');
    }
  });

  jQuery('.notice--atai.is-dismissible').on('click', '.notice-dismiss', function () {
    jQuery.ajax(wp_atai.ajax_url, {
      type: 'POST',
      data: {
        action: 'atai_expire_insufficient_credits_notice',
        security: wp_atai.security_insufficient_credits_notice,
      }
    });
  });

  function getQueryParam(name) {
    name = name.replace(/[[]/, '\\[').replace(/[\]]/, '\\]');
    let regex = new RegExp('[\\?&]' + name + '=([^&#]*)');
    let paramSearch = regex.exec(window.location.search);

    return paramSearch === null ? '' : decodeURIComponent(paramSearch[1].replace(/\+/g, ' '));
  }

  function addGenerateButtonToModal(replacementId, generateButtonId, attachmentId) {
    let replacementNode = document.getElementById(replacementId);

    if (!replacementNode) {
      return false;
    }

    // Remove existing button, if any
    let oldGenerateButton = document.getElementById(generateButtonId + '-' + attachmentId);

    if (oldGenerateButton) {
      oldGenerateButton.remove();
    }

    if (!window.location.href.includes('upload.php')) {
      return false;
    }

    let generateButton = createGenerateButton(generateButtonId, attachmentId, 'modal');
    let parentNode = replacementNode.parentNode;
    if (parentNode) {
      parentNode.replaceChild(generateButton, replacementNode);
    }

    return true;
  }

  function createGenerateButton(generateButtonId, attachmentId, context) {
    const generateUrl = new URL(window.location.href);
    generateUrl.searchParams.set('atai_action', 'generate');

    // Button wrapper
    const buttonId = generateButtonId + '-' + attachmentId;
    const button = document.createElement('div');
    button.setAttribute('id', buttonId);

    button.classList.add('description');
    button.classList.add('atai-generate-button');

    // Clickable anchor inside the wrapper for initiating the action
    const anchor = document.createElement('a');
    anchor.setAttribute('id', buttonId + '-anchor');
    anchor.setAttribute('href', generateUrl);
    anchor.className = 'button-secondary button-large atai-generate-button__anchor';

    // Create checkbox wrapper
    const keywordsCheckboxWrapper = document.createElement('div');
    keywordsCheckboxWrapper.setAttribute('id', buttonId + '-checkbox-wrapper');
    keywordsCheckboxWrapper.classList.add('atai-generate-button__keywords-checkbox-wrapper');

    // Create checkbox
    const keywordsCheckbox = document.createElement('input');
    keywordsCheckbox.setAttribute('type', 'checkbox');
    keywordsCheckbox.setAttribute('id', buttonId + '-keywords-checkbox');
    keywordsCheckbox.setAttribute('name', 'atai-generate-button-keywords-checkbox');
    keywordsCheckbox.className = 'atai-generate-button__keywords-checkbox'

    // Create label for checkbox
    const keywordsCheckboxLabel = document.createElement('label');
    keywordsCheckboxLabel.htmlFor = 'atai-generate-button-keywords-checkbox';
    keywordsCheckboxLabel.innerText = 'Add SEO keywords';

    // Create text field wrapper
    const keywordsTextFieldWrapper = document.createElement('div');
    keywordsTextFieldWrapper.setAttribute('id', buttonId + '-textfield-wrapper');
    keywordsTextFieldWrapper.className = 'atai-generate-button__keywords-textfield-wrapper';
    keywordsTextFieldWrapper.style.display = 'none';

    // Create text field
    const keywordsTextField = document.createElement('input');
    keywordsTextField.setAttribute('type', 'text');
    keywordsTextField.setAttribute('id', buttonId + '-textfield');
    keywordsTextField.className = 'atai-generate-button__keywords-textfield';
    keywordsTextField.setAttribute('name', 'atai-generate-button-keywords');
    keywordsTextField.size = 40;

    // Append checkbox and label to its wrapper
    keywordsCheckboxWrapper.appendChild(keywordsCheckbox);
    keywordsCheckboxWrapper.appendChild(keywordsCheckboxLabel);

    // Append text field to its wrapper
    keywordsTextFieldWrapper.appendChild(keywordsTextField);

    // Event listener to show/hide text field on checkbox change
    keywordsCheckbox.addEventListener('change', function () {
      if (this.checked) {
        keywordsTextFieldWrapper.style.display = 'block';
        keywordsTextField.setSelectionRange(0, 0);
        keywordsTextField.focus();
      } else {
        keywordsTextFieldWrapper.style.display = 'none';
      }
    });

    // Check if the attachment is eligible for generation
    const isAttachmentEligible = (attachmentId) => {
      jQuery.ajax({
        type: 'post',
        dataType: 'json',
        data: {
          'action': 'atai_check_image_eligibility',
          'security': wp_atai.security_check_attachment_eligibility,
          'attachment_id': attachmentId,
        },
        url: wp_atai.ajax_url,
        success: function (response) {
          if (response.status !== 'success') {
            const tempAnchor = document.querySelector(`#${buttonId}-anchor`);
            const tempCheckbox = document.querySelector(`#${buttonId}-keywords-checkbox`);

            if (tempAnchor) {
              tempAnchor.classList.add('disabled');
            } else {
              anchor.classList.add('disabled');
            }

            if (tempCheckbox) {
              tempCheckbox.classList.add('disabled');
            } else {
              keywordsCheckbox.classList.add('disabled');
            }
          }
        }
      });
    };

    // If attachment is eligible, we enable the button
    if (wp_atai.can_user_upload_files) {
      isAttachmentEligible(attachmentId);
    }
    else {
      anchor.classList.add('disabled');
      keywordsCheckbox.disabled = true;
    }

    anchor.title = __('AltText.ai: Update alt text for this single image', 'alttext-ai');
    anchor.onclick = function () {
      this.classList.add('disabled');
      let span = this.querySelector('span');

      if (span) {
        // Create animated dots for processing state
        span.innerHTML = __('Processing', 'alttext-ai') + '<span class="atai-processing-dots"></span>';
        
        // Add processing state class for better visibility
        this.classList.add('atai-processing');
      }
    };

    // Button icon
    const img = document.createElement('img');
    img.src = wp_atai.icon_button_generate;
    img.alt = __('Update Alt Text with AltText.ai', 'alttext-ai');
    anchor.appendChild(img);

    // Button label/text
    const span = document.createElement('span');
    span.innerText = __('Update Alt Text', 'alttext-ai');
    anchor.appendChild(span);

    // Append anchor to the button
    button.appendChild(anchor);

    // Append checkbox and text field wrappers to the button
    button.appendChild(keywordsCheckboxWrapper);
    button.appendChild(keywordsTextFieldWrapper);

    // Notice element below the button,
    // to display "Updated" message when action is successful
    const updateNotice = document.createElement('span');
    updateNotice.classList.add('atai-update-notice');
    button.appendChild(updateNotice);

    // Event listener to initiate generation
    anchor.addEventListener('click', async function (event) {
      event.preventDefault();

      // If API key is not set, redirect to settings page
      if (!wp_atai.has_api_key) {
        window.location.href = wp_atai.settings_page_url + '&api_key_missing=1';
      }

      const titleEl = (context == 'single') ? document.getElementById('title') : document.querySelector('[data-setting="title"] input');
      const captionEl = (context == 'single') ? document.getElementById('attachment_caption') : document.querySelector('[data-setting="caption"] textarea');
      const descriptionEl = (context == 'single') ? document.getElementById('attachment_content') : document.querySelector('[data-setting="description"] textarea');
      const altTextEl = (context == 'single') ? document.getElementById('attachment_alt') : document.querySelector('[data-setting="alt"] textarea');
      const keywords = keywordsCheckbox.checked ? extractKeywords(keywordsTextField.value) : [];

      // Hide notice
      if (updateNotice) {
        updateNotice.innerText = '';
        updateNotice.classList.remove('atai-update-notice--success', 'atai-update-notice--error');
      }

      // Generate alt text
      const response = await singleGenerateAJAX(attachmentId, keywords);

      // Update alt text in DOM
      if (response.status === 'success') {
        altTextEl.value = response.alt_text;

        if (wp_atai.should_update_title === 'yes') {
          titleEl.value = response.alt_text;

          if (context == 'single') {
            // Add class to label to hide it; initially it behaves as placeholder
            titleEl.previousElementSibling.classList.add('screen-reader-text');
          }
        }

        if (wp_atai.should_update_caption === 'yes') {
          captionEl.value = response.alt_text;
        }

        if (wp_atai.should_update_description === 'yes') {
          descriptionEl.value = response.alt_text;
        }

        updateNotice.innerText = __('Updated', 'alttext-ai');
        updateNotice.classList.add('atai-update-notice--success');

        setTimeout(() => {
          updateNotice.classList.remove('atai-update-notice--success');
        }, 3000);
      } else {
        let errorMessage = __('Unable to generate alt text. Check error logs for details.', 'alttext-ai');

        if (response?.message) {
          errorMessage = response.message;
        }

        updateNotice.innerText = errorMessage;
        updateNotice.classList.add('atai-update-notice--error');
      }

      // Reset button
      anchor.classList.remove('disabled', 'atai-processing');
      anchor.querySelector('span').innerHTML = __('Update Alt Text', 'alttext-ai');
    });

    return button;
  }

  // Utility function to DRY up button injection logic
  function injectGenerateButton(container, attachmentId, context) {
    try {
      // First check if a button already exists to prevent duplicates
      // Use a more specific selector that includes the ID to be absolutely sure
      const existingButton = container.querySelector('#atai-generate-button-' + attachmentId + ', .atai-generate-button');
      if (existingButton) {
        return true; // Button already exists, no need to inject another
      }

      let injected = false;
      let button;

      // 1. Try p#alt-text-description
      const altDescP = container.querySelector('p#alt-text-description');
      if (altDescP && altDescP.parentNode) {
        button = createGenerateButton('atai-generate-button', attachmentId, context);
        altDescP.parentNode.replaceChild(button, altDescP);
        injected = true;
      }

      // 2. Try after alt text input/textarea
      if (!injected) {
        const altInput = container.querySelector('[data-setting="alt"] input, [data-setting="alt"] textarea');
        if (altInput && altInput.parentNode) {
          button = createGenerateButton('atai-generate-button', attachmentId, context);
          altInput.parentNode.insertBefore(button, altInput.nextSibling);
          injected = true;
        }
      }

      // 3. Try appending to .attachment-details or .media-attachment-details
      if (!injected) {
        const detailsContainer = container.querySelector('.attachment-details, .media-attachment-details');
        if (detailsContainer) {
          button = createGenerateButton('atai-generate-button', attachmentId, context);
          detailsContainer.appendChild(button);
          injected = true;
        }
      }

      // 4. As a last resort, append to the root
      if (!injected) {
        button = createGenerateButton('atai-generate-button', attachmentId, context);
        container.appendChild(button);
        injected = true;
      }

      return injected;
    } catch (error) {
      console.error('[AltText.ai] Error injecting button:', error);
      return false;
    }
  }

  function insertGenerationButton(hostWrapper, generationButton) {
    // If the wrapping class already has a BUTTON element, replace it with ours.
    // Otherwise insert at end.
    if (!hostWrapper.hasChildNodes()) {
      hostWrapper.appendChild(generationButton);
      return;
    }

    for (const childNode of hostWrapper.childNodes) {
      if (childNode.nodeName == 'BUTTON') {
        hostWrapper.replaceChild(generationButton, childNode);
        return;
      }
    }

    // If we get here, there was no textarea elelment, so just append to the end again.
    hostWrapper.appendChild(generationButton);
  }

  /**
   * Manage Generation for Single Image
   */
  document.addEventListener('DOMContentLoaded', async () => {
    const isAttachmentPage = window.location.href.includes('post.php') && jQuery('body').hasClass('post-type-attachment');
    const isEditPost = window.location.href.includes('post-new.php') || (window.location.href.includes('post.php') && !jQuery('body').hasClass('post-type-attachment'));
    const isAttachmentModal = window.location.href.includes('upload.php');
    let attachmentId = null;
    let generateButtonId = 'atai-generate-button';

    if (isAttachmentPage) {
      // Editing media library image from the list view
      attachmentId = getQueryParam('post');

      // Bail early if no post ID.
      if (!attachmentId) {
        return false;
      }

      attachmentId = parseInt(attachmentId, 10);

      // Bail early if post ID is not a number.
      if (!attachmentId) {
        return;
      }

      let hostWrapper = document.getElementsByClassName('attachment-alt-text')[0];

      if (hostWrapper) {
        let generateButton = createGenerateButton(generateButtonId, attachmentId, 'single');
        setTimeout(() => {
          insertGenerationButton(hostWrapper, generateButton);
        }, 200);
      }
    } else if (isAttachmentModal || isEditPost) {
      // Media library grid view modal window
      attachmentId = getQueryParam('item');

      // Initial click to open the media library grid view attachment modal:
      jQuery(document).on('click', 'ul.attachments li.attachment', function () {
        let element = jQuery(this);

        // Bail early if no data-id attribute.
        if (!element.attr('data-id')) {
          return;
        }

        attachmentId = parseInt(element.attr('data-id'), 10);

        // Bail early if post ID is not a number.
        if (!attachmentId) {
          return;
        }

        addGenerateButtonToModal('alt-text-description', generateButtonId, attachmentId);
      });

      // Click on the next/previous image arrows from the media library modal window:
      document.addEventListener('click', function (event) {
        attachmentModalChangeHandler(event, 'button-click', generateButtonId);
      });

      // Keyboard navigation for the media library modal window:
      document.addEventListener('keydown', function (event) {
        if (event.key === 'ArrowRight' || event.key === 'ArrowLeft') {
          attachmentModalChangeHandler(event, 'keyboard', generateButtonId);
        }
      });

      // Bail early if no post ID.
      if (!attachmentId) {
        return false;
      }
    } else {
      return false;
    }
  });

  /**
   * Make bulk action parent option disabled
   */
  document.addEventListener('DOMContentLoaded', () => {
    jQuery('.tablenav .bulkactions select option[value="alttext_options"]').attr('disabled', 'disabled');
  });

  /**
   * Handle button injection on modal navigation
   *
   * @param {Event} event - The DOM event triggered by user interaction, such as a click or keydown.
   * @param {string} eventType - A string specifying the type of event that initiated the modal navigation.
   * @param {string} generateButtonId - A string containing the button ID that will be injected into the modal.
   */
  function attachmentModalChangeHandler(event, eventType, generateButtonId) {
    // Bail early if not clicking on the modal navigation.
    if (eventType === 'button-click' && !event.target.matches('.media-modal .right, .media-modal .left')) {
      return;
    }

    // Get attachment ID from URL.
    const urlParams = new URLSearchParams(window.location.search);
    const attachmentId = urlParams.get('item');

    // Bail early if post ID is not a number.
    if (!attachmentId) {
      return;
    }

    addGenerateButtonToModal('alt-text-description', generateButtonId, attachmentId);
  }

  /**
   * Native override to play nice with other plugins that may also be modifying this modal.
   * Adds the generate button to the media modal when the attachment details are rendered.
   *
   */
  const attachGenerateButtonToModal = () => {
    if (wp?.media?.view?.Attachment?.Details?.prototype?.render) {
      const origRender = wp.media.view.Attachment.Details.prototype.render;
      wp.media.view.Attachment.Details.prototype.render = function () {
        const result = origRender.apply(this, arguments);
        const container = this.$el ? this.$el[0] : null;
        if (container) {
          // Clean up any existing observer to prevent memory leaks
          if (this._ataiObserver) {
            this._ataiObserver.disconnect();
            delete this._ataiObserver;
          }
          
          // Use a more efficient observer with a debounce mechanism
          let debounceTimer = null;
          const tryInject = () => {
            // Clear any pending injection to avoid multiple rapid calls
            if (debounceTimer) {
              clearTimeout(debounceTimer);
            }
            
            // Debounce the injection to avoid excessive processing
            debounceTimer = setTimeout(() => {
              // Check if button already exists before doing any work
              if (!container.querySelector('.atai-generate-button')) {
                injectGenerateButton(container, this.model.get("id"), "modal");
              }
              
              // Disconnect observer after successful injection to prevent further processing
              if (this._ataiObserver) {
                this._ataiObserver.disconnect();
                delete this._ataiObserver;
              }
            }, 50); // Small delay to batch DOM changes
          };
          
          // Create a new observer with limited scope
          this._ataiObserver = new MutationObserver(tryInject);
          
          // Only observe specific changes to reduce overhead
          this._ataiObserver.observe(container, { 
            childList: true,  // Watch for child additions/removals
            subtree: true,    // Watch the entire subtree
            attributes: false, // Don't watch attributes (reduces overhead)
            characterData: false // Don't watch text content (reduces overhead)
          });
          
          // Try immediate injection but with a slight delay to let other scripts finish
          setTimeout(() => {
            if (!container.querySelector('.atai-generate-button')) {
              injectGenerateButton(container, this.model.get("id"), "modal");
            }
          }, 10);
        }
        return result;
      };
    }
  };

  attachGenerateButtonToModal();
   
  document.addEventListener("DOMContentLoaded", () => {
    const form = document.querySelector("form#alttextai-csv-import");    
    if (form) { 
      const input = form.querySelector('input[type="file"]');
      if (input) { 
        input.addEventListener("change", (event) => {
          form.dataset.fileLoaded = event.target.files?.length > 0 ? "true" : "false";
        });
      }
    }
  });

  function extendMediaTemplate() {
    const previousAttachmentDetails = wp.media.view.Attachment.Details;
    wp.media.view.Attachment.Details = previousAttachmentDetails.extend({
      ATAICheckboxToggle: function (event) {
        const target = event.currentTarget;
        const keywordsTextFieldWrapper = target.parentNode.nextElementSibling;
        const keywordsTextField = keywordsTextFieldWrapper.querySelector('.atai-generate-button__keywords-textfield');

        if (target.checked) {
          keywordsTextFieldWrapper.style.display = 'block';
          keywordsTextField.setSelectionRange(0, 0);
          keywordsTextField.focus();
        } else {
          keywordsTextFieldWrapper.style.display = 'none';
        }
      },
      ATAIAnchorClick: async function (event) {
        event.preventDefault();
        const attachmentId = this.model.id;
        const anchor = event.currentTarget;
        const attachmentDetails = anchor.closest('.attachment-details');
        const generateButton = anchor.closest('.atai-generate-button');
        const keywordsCheckbox = generateButton.querySelector('.atai-generate-button__keywords-checkbox');
        const keywordsTextField = generateButton.querySelector('.atai-generate-button__keywords-textfield');
        const updateNotice = generateButton.querySelector('.atai-update-notice');

        // Loading state
        anchor.classList.add('disabled');
        const anchorLabel = anchor.querySelector('span');

        if (anchorLabel) {
          // Create animated dots for processing state
          anchorLabel.innerHTML = __('Processing', 'alttext-ai') + '<span class="atai-processing-dots"></span>';
          
          // Add processing state class for better visibility
          anchor.classList.add('atai-processing');
        }

        // If API key is not set, redirect to settings page
        if (!wp_atai.has_api_key) {
          window.location.href = wp_atai.settings_page_url + '&api_key_missing=1';
        }

        const titleEl = attachmentDetails.querySelector('[data-setting="title"] input');
        const captionEl = attachmentDetails.querySelector('[data-setting="caption"] textarea');
        const descriptionEl = attachmentDetails.querySelector('[data-setting="description"] textarea');
        const altTextEl = attachmentDetails.querySelector('[data-setting="alt"] textarea');
        const keywords = keywordsCheckbox.checked ? extractKeywords(keywordsTextField.value) : [];

        // Hide notice
        if (updateNotice) {
          updateNotice.innerText = '';
          updateNotice.classList.remove('atai-update-notice--success', 'atai-update-notice--error');
        }

        // Generate alt text
        const response = await singleGenerateAJAX(attachmentId, keywords);

        // Update alt text in DOM
        if (response.status === 'success') {
          altTextEl.value = response.alt_text;
          altTextEl.dispatchEvent(new Event('change', { bubbles: true }));

          if (wp_atai.should_update_title === 'yes') {
            titleEl.value = response.alt_text;
            titleEl.dispatchEvent(new Event('change', { bubbles: true }));
          }

          if (wp_atai.should_update_caption === 'yes') {
            captionEl.value = response.alt_text;
            captionEl.dispatchEvent(new Event('change', { bubbles: true }));
          }

          if (wp_atai.should_update_description === 'yes') {
            descriptionEl.value = response.alt_text;
            descriptionEl.dispatchEvent(new Event('change', { bubbles: true }));
          }

          updateNotice.innerText = __('Updated', 'alttext-ai');
          updateNotice.classList.add('atai-update-notice--success');

          setTimeout(() => {
            updateNotice.classList.remove('atai-update-notice--success');
          }, 3000);
        } else {
          let errorMessage = __('Unable to generate alt text. Check error logs for details.', 'alttext-ai');

          if (response?.message) {
            errorMessage = response.message;
          }

          updateNotice.innerText = errorMessage;
          updateNotice.classList.add('atai-update-notice--error');
        }

        // Reset button
        anchor.classList.remove('disabled', 'atai-processing');
        anchorLabel.innerHTML = __('Update Alt Text', 'alttext-ai');
      },
      events: {
        ...previousAttachmentDetails.prototype.events,
        'change .atai-generate-button__keywords-checkbox': 'ATAICheckboxToggle',
        'click .atai-generate-button__anchor': 'ATAIAnchorClick'
      },
      template: function (view) {
        // tmpl-attachment-details
        const html = previousAttachmentDetails.prototype.template.apply(this, arguments);
        const dom = document.createElement('div');
        dom.innerHTML = html;

        // Use the robust injection function
        injectGenerateButton(dom, view.model.id, 'modal');
        return dom.innerHTML;
      }
    });
  }

  function showUrlAccessErrorNotification(message) {
    // Stop bulk processing
    window.atai.setProcessingState(false);
    
    // Show Start Over button if there's a session to clear
    if (localStorage.getItem('atai_bulk_progress')) {
      jQuery('#atai-static-start-over-button').show();
    }
    
    // Update progress heading to show error
    if (window.atai.progressHeading.length) {
      window.atai.progressHeading.text(__('URL Access Error', 'alttext-ai'));
    }
    
    // Create notification HTML with action button
    const notificationHtml = `
      <div class="atai-url-access-notification bg-amber-900/5 p-px rounded-lg mb-6">
        <div class="bg-amber-50 rounded-lg p-4">
          <div class="flex items-start">
            <div class="flex-shrink-0">
              <svg class="size-5 mt-5 text-amber-500" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
              </svg>
            </div>
            <div class="ml-3 flex-1">
              <h3 class="text-base font-semibold text-amber-800 mb-2">${__('Image Access Problem', 'alttext-ai')}</h3>
              <p class="text-sm text-amber-700 mb-3">${__('Some of your image URLs are not accessible to our servers. This can happen due to:', 'alttext-ai')}</p>
              <ul class="text-sm text-amber-700 mb-3 ml-4 list-disc space-y-1">
                <li>${__('Server firewalls or security restrictions', 'alttext-ai')}</li>
                <li>${__('Local development environments (localhost)', 'alttext-ai')}</li>
                <li>${__('Password-protected or staging sites', 'alttext-ai')}</li>
                <li>${__('VPN or private network configurations', 'alttext-ai')}</li>
              </ul>
              <p class="text-sm text-amber-800">${__('Switching to direct upload mode will send your images securely to our servers instead of using URLs, which resolves this issue.', 'alttext-ai')}</p>
            </div>
          </div>
          <div class="mt-4 flex gap-3">
            <button type="button" id="atai-fix-url-access" class="atai-button blue">
              ${__('Update Setting Now', 'alttext-ai')}
            </button>
            <button type="button" id="atai-dismiss-url-notification" class="atai-button white">
              ${__('Dismiss', 'alttext-ai')}
            </button>
          </div>
        </div>
      </div>
    `;
    
    // Insert notification after the progress wrapper
    const progressWrapper = jQuery('[data-bulk-generate-progress-wrapper]');
    if (progressWrapper.length) {
      progressWrapper.after(notificationHtml);
      
      // Add event handlers
      jQuery('#atai-fix-url-access').on('click', function() {
        // Update the setting via AJAX
        jQuery.post(wp_atai.ajax_url, {
          action: 'atai_update_public_setting',
          security: wp_atai.security_update_public_setting,
          atai_public: 'no'
        }, function(response) {
          if (response.success) {
            // Reload page to reset the bulk generation with new setting
            window.location.reload();
          }
        }).fail(function(xhr, status, error) {
          console.error('AJAX request failed:', error);
          // Fallback - just reload the page
          window.location.reload();
        });
      });
      
      jQuery('#atai-dismiss-url-notification').on('click', function() {
        jQuery('.atai-url-access-notification').remove();
      });
    }
  }

  document.addEventListener('DOMContentLoaded', () => {
    if (!wp?.media?.view?.Attachment?.Details) {
      return;
    }

    // Use a small delay to ensure WordPress media is fully initialized
    setTimeout(extendMediaTemplate, 500);
  });
})();
