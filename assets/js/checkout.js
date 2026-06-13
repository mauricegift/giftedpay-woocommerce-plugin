/* GiftedPay — Checkout phone field helpers */
(function ($) {
  'use strict';

  function formatPhone(val) {
    val = val.replace(/\D/g, '');
    if (val.startsWith('254')) val = '0' + val.slice(3);
    if (val.length > 4 && val.length <= 7) val = val.slice(0, 4) + ' ' + val.slice(4);
    else if (val.length > 7) val = val.slice(0, 4) + ' ' + val.slice(4, 7) + ' ' + val.slice(7, 10);
    return val;
  }

  $(document).on('keyup change', '#giftedpay_phone', function () {
    var raw = $(this).val().replace(/\D/g, '');
    var hint = '';

    if (raw.length >= 9) {
      var norm = raw;
      if (raw.startsWith('07') || raw.startsWith('01')) norm = '254' + raw.slice(1);
      if (/^254(7|1)\d{8}$/.test(norm)) {
        hint = '<span class="giftedpay-valid">✓ Valid Safaricom number</span>';
        $('#giftedpay_phone').removeClass('giftedpay-input-invalid').addClass('giftedpay-input-valid');
      } else {
        hint = '<span class="giftedpay-invalid">✗ Must be a valid Safaricom number (07xx or 01xx)</span>';
        $('#giftedpay_phone').removeClass('giftedpay-input-valid').addClass('giftedpay-input-invalid');
      }
    } else {
      $('#giftedpay_phone').removeClass('giftedpay-input-valid giftedpay-input-invalid');
    }

    $('#giftedpay-phone-hint').html(hint);
  });

  $(document).ready(function () {
    var $field = $('#giftedpay_phone');
    if (!$field.length) return;
    $field.after('<div id="giftedpay-phone-hint"></div>');
  });

  /* Collapse phone field when another payment method is chosen */
  $(document.body).on('payment_method_selected', function () {
    var chosen = $('input[name="payment_method"]:checked').val();
    if (chosen === 'giftedpay') {
      $('#giftedpay_phone').attr('required', true);
    } else {
      $('#giftedpay_phone').removeAttr('required');
    }
  });

})(jQuery);
