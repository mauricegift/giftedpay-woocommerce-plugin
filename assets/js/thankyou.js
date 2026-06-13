/* GiftedPay — Thank-you page polling */
(function ($) {
  'use strict';

  $(document).ready(function () {
    var $wrap = $('#giftedpay-polling-wrap');
    if (!$wrap.length) return;

    var checkoutId = $wrap.data('checkout-id');
    var orderId    = $wrap.data('order-id');
    var interval   = parseInt($wrap.data('interval'), 10)  || 4000;
    var timeout    = parseInt($wrap.data('timeout'), 10)   || 150000;
    var ajaxUrl    = $wrap.data('ajax-url');
    var nonce      = $wrap.data('nonce');

    if (!checkoutId) return;

    var elapsed   = 0;
    var pollTimer = null;

    function show(cls) {
      $wrap.find('.giftedpay-status-box').hide();
      $wrap.find('.' + cls).show();
    }

    function stopPolling() {
      if (pollTimer) {
        clearInterval(pollTimer);
        pollTimer = null;
      }
    }

    function poll() {
      $.post(ajaxUrl, {
        action:              'giftedpay_poll_status',
        nonce:               nonce,
        checkout_request_id: checkoutId,
        order_id:            orderId,
      })
      .done(function (res) {
        if (!res || !res.success) return;

        var data   = res.data || {};
        var status = data.status || '';

        if (status === 'completed') {
          stopPolling();
          show('giftedpay-success');

          /* Show receipt link if available */
          var receipt = (data.data || {}).mpesa_receipt_number || '';
          if (receipt && typeof giftedpayReceiptBase !== 'undefined') {
            var cid = (data.data || {}).checkout_request_id || checkoutId;
            var receiptUrl = giftedpayReceiptBase + encodeURIComponent(cid);
            $wrap.find('.giftedpay-receipt-link').html(
              '<a href="' + receiptUrl + '" target="_blank" class="button" style="margin-top:10px;display:inline-block">View M-Pesa Receipt →</a>'
            );
          }

          /* Reload page after 3s so WooCommerce order status updates */
          setTimeout(function () { location.reload(); }, 3000);
          return;
        }

        var terminalStatuses = ['failed', 'cancelled', 'timeout', 'failed_insufficient_funds', 'failed_invalid_input'];
        if (terminalStatuses.indexOf(status) !== -1) {
          stopPolling();
          var reason = (data.data || {}).result_desc || 'The payment was not completed.';
          $wrap.find('.giftedpay-fail-reason').text(reason);
          show('giftedpay-failed');
          return;
        }
        /* Still pending — keep polling */
      })
      .fail(function () {
        /* Network error — keep retrying, don't stop */
      });

      elapsed += interval;
      if (elapsed >= timeout) {
        stopPolling();
        show('giftedpay-timeout');
      }
    }

    /* Start immediately, then repeat */
    poll();
    pollTimer = setInterval(poll, interval);
  });

})(jQuery);
