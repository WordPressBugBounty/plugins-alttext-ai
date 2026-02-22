(function($) {
  'use strict';

  var networkBulk = {
    sites: [],
    currentSiteIndex: 0,
    isProcessing: false,
    isCancelled: false,
    totalMissing: 0,
    totalProcessed: 0,
    totalSuccessful: 0,
    stopReason: null,
    currentXhr: null,

    init: function() {
      this.loadStats();
      this.bindEvents();
    },

    bindEvents: function() {
      $('#atai-network-generate-btn').on('click', $.proxy(this.startProcessing, this));
      $('#atai-network-cancel-btn').on('click', $.proxy(this.cancelProcessing, this));
      $('#atai-network-refresh-btn').on('click', $.proxy(this.loadStats, this));
      $('#atai-select-all').on('change', function() {
        $('.atai-site-checkbox').prop('checked', $(this).is(':checked'));
        networkBulk.updateGenerateButton();
      });
      $(document).on('change', '.atai-site-checkbox', $.proxy(this.updateGenerateButton, this));
    },

    updateGenerateButton: function() {
      var hasChecked = $('.atai-site-checkbox:checked').length > 0;
      $('#atai-network-generate-btn').prop('disabled', !hasChecked || this.isProcessing);
    },

    loadStats: function() {
      if (this.isProcessing) {
        return;
      }

      var self = this;
      $('#atai-network-stats-loading').show();
      $('#atai-network-stats-table').hide();
      $('#atai-network-stats-error').hide();
      $('#atai-network-generate-btn').prop('disabled', true);

      $.ajax({
        type: 'post',
        dataType: 'json',
        url: wp_atai_network.ajax_url,
        data: {
          action: 'atai_network_get_stats',
          security: wp_atai_network.security
        },
        success: function(response) {
          $('#atai-network-stats-loading').hide();

          if (response.success && response.data && response.data.sites) {
            self.sites = response.data.sites;
            self.renderStats(response.data.sites);
            $('#atai-network-stats-table').show();
          } else {
            $('#atai-network-stats-error').show();
          }
        },
        error: function(jqXHR, textStatus) {
          $('#atai-network-stats-loading').hide();
          if (textStatus !== 'abort') {
            $('#atai-network-stats-error').show();
          }
        }
      });
    },

    renderStats: function(sites) {
      var tbody = $('#atai-network-stats-body');
      tbody.empty();

      var totalImages = 0;
      var totalMissing = 0;

      for (var i = 0; i < sites.length; i++) {
        var site = sites[i];
        if (!site.error) {
          totalImages += site.total_images;
          totalMissing += site.missing_alt;
        }

        var row;
        if (site.error) {
          row = '<tr data-blog-id="' + site.blog_id + '">' +
            '<td class="check-column"></td>' +
            '<td><a href="' + this.escapeHtml(site.url) + '" target="_blank" rel="noopener noreferrer">' + this.escapeHtml(site.name) + '</a><br><small>' + this.escapeHtml(site.url) + '</small></td>' +
            '<td>—</td>' +
            '<td>—</td>' +
            '<td class="atai-site-status"><span class="atai-status-error">Stats unavailable</span></td>' +
          '</tr>';
        } else {
          row = '<tr data-blog-id="' + site.blog_id + '">' +
            '<td class="check-column">' +
              (site.missing_alt > 0 ? '<input type="checkbox" class="atai-site-checkbox" value="' + site.blog_id + '" aria-label="' + this.escapeHtml('Select ' + site.name) + '" checked />' : '') +
            '</td>' +
            '<td><a href="' + this.escapeHtml(site.url) + '" target="_blank" rel="noopener noreferrer">' + this.escapeHtml(site.name) + '</a><br><small>' + this.escapeHtml(site.url) + '</small></td>' +
            '<td>' + site.total_images.toLocaleString() + '</td>' +
            '<td>' + (site.missing_alt > 0 ? '<strong class="atai-status-error">' + site.missing_alt.toLocaleString() + '</strong>' : '<span class="atai-status-success">0</span>') + '</td>' +
            '<td class="atai-site-status">' + (site.missing_alt > 0 ? 'Pending' : '<span class="atai-status-success">Complete</span>') + '</td>' +
          '</tr>';
        }

        tbody.append(row);
      }

      $('#atai-total-images').text(totalImages.toLocaleString());
      $('#atai-total-missing').text(totalMissing.toLocaleString());

      this.totalMissing = totalMissing;
      this.updateGenerateButton();
    },

    startProcessing: function() {
      if (this.isProcessing) {
        return;
      }

      var self = this;
      var selectedSites = [];

      $('.atai-site-checkbox:checked').each(function() {
        var blogId = parseInt($(this).val(), 10);
        for (var i = 0; i < self.sites.length; i++) {
          if (self.sites[i].blog_id === blogId && self.sites[i].missing_alt > 0) {
            selectedSites.push(self.sites[i]);
            break;
          }
        }
      });

      if (selectedSites.length === 0) {
        return;
      }

      this.isProcessing = true;
      this.isCancelled = false;
      this.currentSiteIndex = 0;
      this.totalProcessed = 0;
      this.totalSuccessful = 0;
      this.stopReason = null;
      this.sitesToProcess = selectedSites;

      // Calculate total to process
      this.totalToProcess = 0;
      for (var i = 0; i < selectedSites.length; i++) {
        this.totalToProcess += selectedSites[i].missing_alt;
      }

      $('#atai-network-generate-btn').prop('disabled', true);
      $('#atai-network-cancel-btn').show();
      $('#atai-network-refresh-btn').prop('disabled', true);
      $('#atai-network-progress-card').show();
      $('#atai-network-progress-card').attr('tabindex', '-1').focus();

      this.processSite();
    },

    processSite: function() {
      if (this.isCancelled || this.currentSiteIndex >= this.sitesToProcess.length) {
        this.onComplete();
        return;
      }

      var site = this.sitesToProcess[this.currentSiteIndex];
      var $row = $('tr[data-blog-id="' + site.blog_id + '"]');
      $row.find('.atai-site-status').html('<strong class="atai-status-processing">Processing...</strong>');
      $('#atai-network-current-site').text('Processing: ' + site.name + ' (' + (this.currentSiteIndex + 1) + ' of ' + this.sitesToProcess.length + ' sites)');

      this.currentLastPostId = 0;
      this.currentSiteProcessed = 0;
      this.processBatch(site);
    },

    processBatch: function(site) {
      if (this.isCancelled) {
        this.onComplete();
        return;
      }

      var self = this;

      this.currentXhr = $.ajax({
        type: 'post',
        dataType: 'json',
        url: wp_atai_network.ajax_url,
        data: {
          action: 'atai_network_bulk_generate',
          security: wp_atai_network.security,
          blog_id: site.blog_id,
          posts_per_page: 5,
          last_post_id: this.currentLastPostId
        },
        success: function(response) {
          self.currentXhr = null;
          var data = response.data || {};
          var $row = $('tr[data-blog-id="' + site.blog_id + '"]');
          if (response.success) {
            self.totalProcessed += data.process_count;
            self.totalSuccessful += data.success_count;
            self.currentSiteProcessed += data.process_count;
            self.currentLastPostId = data.last_post_id;

            self.updateProgress();

            if (data.recursive && !self.isCancelled) {
              self.processBatch(site);
            } else {
              // Site complete or out of credits
              if (data.stop_reason === 'no_credits') {
                self.stopReason = 'no_credits';
                $row.find('.atai-site-status').html('<span class="atai-status-error">No credits</span>');
                self.onComplete();
                return;
              }

              $row.find('.atai-site-status').html('<span class="atai-status-success">Done (' + self.currentSiteProcessed + ' generated)</span>');
              self.currentSiteIndex++;
              self.processSite();
            }
          } else {
            $row.find('.atai-site-status').html('<span class="atai-status-error">Error</span>');
            self.currentSiteIndex++;
            self.processSite();
          }
        },
        error: function(jqXHR, textStatus) {
          self.currentXhr = null;
          if (textStatus === 'abort') {
            self.onComplete();
            return;
          }
          var $row = $('tr[data-blog-id="' + site.blog_id + '"]');
          $row.find('.atai-site-status').html('<span class="atai-status-error">Error (' + jqXHR.status + ')</span>');
          self.currentSiteIndex++;
          self.processSite();
        }
      });
    },

    updateProgress: function() {
      var pct = this.totalToProcess > 0 ? Math.round((this.totalProcessed / this.totalToProcess) * 100) : 0;
      $('#atai-network-progress-bar').css('width', pct + '%').attr('aria-valuenow', pct);
      $('#atai-network-progress-text').text(
        this.totalSuccessful + ' generated, ' + this.totalProcessed + ' of ' + this.totalToProcess + ' processed (' + pct + '%)'
      );
    },

    cancelProcessing: function() {
      this.isCancelled = true;
      $('#atai-network-cancel-btn').prop('disabled', true).text('Cancelling...');
      if (this.currentXhr) {
        this.currentXhr.abort();
        this.currentXhr = null;
      }
    },

    onComplete: function() {
      this.isProcessing = false;
      this.currentXhr = null;
      $('#atai-network-cancel-btn').hide().prop('disabled', false).text('Cancel');
      $('#atai-network-refresh-btn').prop('disabled', false).focus();
      this.updateGenerateButton();

      var msg;
      var pct = this.totalToProcess > 0 ? Math.round((this.totalProcessed / this.totalToProcess) * 100) : 0;
      pct = Math.max(0, Math.min(100, pct));
      var siteWord = this.sitesToProcess.length === 1 ? 'site' : 'sites';
      if (this.isCancelled) {
        msg = 'Cancelled. ' + this.totalSuccessful + ' images generated.';
      } else if (this.stopReason === 'no_credits') {
        msg = 'Stopped: out of credits. ' + this.totalSuccessful + ' images generated.';
      } else {
        msg = 'Complete! ' + this.totalSuccessful + ' images generated across ' + this.sitesToProcess.length + ' ' + siteWord + '.';
        pct = 100;
      }
      $('#atai-network-progress-text').text(msg);
      $('#atai-network-progress-bar').css('width', pct + '%').attr('aria-valuenow', pct);
    },

    escapeHtml: function(str) {
      var div = document.createElement('div');
      div.appendChild(document.createTextNode(str));
      return div.innerHTML;
    }
  };

  $(document).ready(function() {
    if ($('#atai-network-stats-table').length) {
      networkBulk.init();
    }
  });

})(jQuery);
