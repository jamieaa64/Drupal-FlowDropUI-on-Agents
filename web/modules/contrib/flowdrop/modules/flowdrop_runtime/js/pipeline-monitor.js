/**
 * @file
 * Pipeline monitoring JavaScript for auto-refresh functionality.
 */

(function (Drupal, drupalSettings, $) {
  'use strict';

  /**
   * Pipeline Monitor behavior.
   */
  Drupal.behaviors.flowdropRuntimePipelineMonitor = {
    attach: function (context, settings) {
      var $autoRefreshCheckbox = $(context).find('input[name="auto_refresh"]').once('pipeline-monitor');
      var refreshInterval;

      if ($autoRefreshCheckbox.length) {
        $autoRefreshCheckbox.on('change', function () {
          if (this.checked) {
            startAutoRefresh();
          } else {
            stopAutoRefresh();
          }
        });

        // Start auto-refresh if checkbox is already checked.
        if ($autoRefreshCheckbox.is(':checked')) {
          startAutoRefresh();
        }
      }

      /**
       * Start auto-refresh timer.
       */
      function startAutoRefresh() {
        refreshInterval = setInterval(function () {
          // Find and trigger the refresh button.
          var $refreshButton = $('input[value="Refresh"]');
          if ($refreshButton.length) {
            $refreshButton.trigger('click');
          }
        }, 30000); // 30 seconds

        // Show status message.
        if (typeof Drupal.announce !== 'undefined') {
          Drupal.announce(Drupal.t('Auto-refresh enabled. Page will refresh every 30 seconds.'));
        }
      }

      /**
       * Stop auto-refresh timer.
       */
      function stopAutoRefresh() {
        if (refreshInterval) {
          clearInterval(refreshInterval);
          refreshInterval = null;
        }

        // Show status message.
        if (typeof Drupal.announce !== 'undefined') {
          Drupal.announce(Drupal.t('Auto-refresh disabled.'));
        }
      }

      // Clean up on page unload.
      $(window).on('beforeunload', function () {
        stopAutoRefresh();
      });
    }
  };

})(Drupal, drupalSettings, jQuery);
