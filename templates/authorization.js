/**
 * @license Copyright 2018 AtomicPay, MIT License
 * see https://github.com/atomicpay/woocommerce-plugin/blob/master/LICENSE
 */

'use strict';

(function ( $ ) {

  $(function () {

    /**
     * Try to pair with AtomicPay using an entered pairing code
    */
    $('#atomicpay_api_auth_form').on('click', '.atomicpay-authorization__auth', function (e) {

      // Don't submit any forms or follow any links
      e.preventDefault();

      // Hide the pairing code form
      $('.atomicpay-authorization').hide();
      $('.atomicpay-authorization').after('<div class="atomicpay-authorization__loading" style="width: 20em; text-align: center"><img src="'+ajax_loader_url+'"></div>');

      // Attempt the pair with AtomicPay
      $.post(AtomicPayAjax.ajaxurl, {
        'action':       'atomicpay_authorize_nonce',
        'accountID': $('.atomicpay-authorization-accountID').val(),
        'privateKey':      $('.atomicpay-authorization-privateKey').val(),
        'publicKey':      $('.atomicpay-authorization-publicKey').val(),
        'authNonce':    AtomicPayAjax.authNonce
      })
      .done(function (data) {

        $('.atomicpay-authorization__loading').remove();

        // Make sure the data is valid
        if (data && data.message) {

          $('.atomicpay-authorization').show();
          $('.atomicpay-authorization-accountID').val(data.accountID);
          $('.atomicpay-authorization-privateKey').val(data.privateKey);
          $('.atomicpay-authorization-publicKey').val(data.publicKey);
          alert(data.message);
        }
        // Pairing failed
        else if (data && data.success === false) {
          $('.atomicpay-authorization').show();
          alert(data.data);
        }

      });
    });

    // Revoking Token
    $('#atomicpay_api_auth_form').on('click', '.atomicpay-authorization__revoke', function (e) {

      // Don't submit any forms or follow any links
      e.preventDefault();

      $('.atomicpay-authorization').hide();
      $('.atomicpay-authorization').after('<div class="atomicpay-authorization__loading" style="width: 20em; text-align: center"><img src="'+ajax_loader_url+'"></div>');

      if (confirm('Are you sure you want to revoke the token?')) {
        $.post(AtomicPayAjax.ajaxurl, {
          'action': 'atomicpay_revoke_nonce',
          'revokeNonce':    AtomicPayAjax.revokeNonce
        })
        .always(function (data) {
          $('.atomicpay-authorization__loading').remove();
          $('.atomicpay-authorization').show();
          $('.atomicpay-authorization-accountID').val(null);
          $('.atomicpay-authorization-privateKey').val(null);
          $('.atomicpay-authorization-publicKey').val(null);
          alert(data.message);
        });
      }

    });

  });

}( jQuery ));
