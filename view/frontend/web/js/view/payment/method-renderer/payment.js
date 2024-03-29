define([
    'Magento_Payment/js/view/payment/cc-form',
    'jquery',
    'Magento_Payment/js/model/credit-card-validation/validator'
], function (Component, $) {
    'use strict';
    return Component.extend({
        defaults: {
            template: 'Cloudwalk_InfinitePay/payment/payment'
        },
        getData: function () {
            if($('#infinitepay_payment_method').val() != 'pix') {
                var data = {
                    'method': this.getCode(),
                    'additional_data': {
                        'installments': $('#' + this.getCode() + '_installments').val(),
                        'cc_holdername': $('#' + this.getCode() + '_cc_holdername').val(),
                        'cc_cid': this.creditCardVerificationNumber(),
                        'cc_ss_start_month': this.creditCardSsStartMonth(),
                        'cc_ss_start_year': this.creditCardSsStartYear(),
                        'cc_type': this.creditCardType(),
                        'cc_token': $('#' + this.getCode() + '_cc_token').val(),
                        'document_id': $('#' + this.getCode() + '_document_id').val(),
                        'payment_method': $('#' + this.getCode() + '_payment_method').val()
                    }
                };
            } else {
                var data = {
                    'method': this.getCode(),
                    'additional_data': {
                        'document_id': $('#' + this.getCode() + '_document_id').val(),
                        'payment_method': $('#' + this.getCode() + '_payment_method').val()
                    }
                };
            }
            return data;
        },
        getInstallments: function () {
            let max_installments = window.checkoutConfig.payment.infinitepay.max_installments;
            let arr_installments = window.checkoutConfig.payment.infinitepay.installments;
            let installments = [];
            for (let i = 1; i <= max_installments; i++) {
                if (i === 1) {
                    var value = window.checkoutConfig.payment.infinitepay.price.replace(".", ",");
                    installments.push({ value: 1, text: 'R$ ' + value + ' à vista' });
                } else {
                    let new_value = arr_installments[i - 1]['value'];
                    let has_interest = (arr_installments[i - 1]['interest'] ? 'com' : 'sem') + ' juros';
                    let msg = i + 'x de R$ ' + new_value + ' ' + has_interest;
                    installments.push({ value: i, text: msg });
                }
            }
            return installments;
        },
        getCode: function () {
            return 'infinitepay';
        },
        getInstructions: function () {
            return window.checkoutConfig.payment.infinitepay.instructions;
        },
        getDescription: function () {
            return window.checkoutConfig.payment.infinitepay.description;
        },
        isActive: function () {
            return true;
        },
        changeMethod: function (selector, root) {
            $('#infinitepay_payment_method').val(selector);
            if(selector == 'pix') {
                $('.ipcc-form').hide();
                $('.ippix-form').show();
            } else {
                $('.ipcc-form').show();
                $('.ippix-form').hide();
            }
            return true;
        },
        activeRadioCC: function() {
            return window.checkoutConfig.payment.infinitepay.cc_enabled;
        },
        activeRadioPix: function() {
            return !window.checkoutConfig.payment.infinitepay.cc_enabled;
        },
        enablePixForm: function () {
            return !window.checkoutConfig.payment.infinitepay.cc_enabled;
        },
        enablePix: function () {
            return window.checkoutConfig.payment.infinitepay.pix_enabled;
        },
        enableCC: function () {
            return window.checkoutConfig.payment.infinitepay.cc_enabled;
        },
        validate: function () {
            var $form = $('#' + this.getCode() + '-form');
            if (!($form.validation() && $form.validation("isValid"))) {
                return false;
            }

            var cardToken = this.getCardToken(this.creditCardNumber(), this.creditCardExpMonth(), this.creditCardExpYear());
            $('#' + this.getCode() + '_cc_token').val(cardToken)

            return true;
        },
        getCardToken: function (creditCardNumber, creditCardExpMonth, creditCardExpYear) {
            let cardToken = "";
            if($('#infinitepay_payment_method').val() != 'pix') {
                $.ajax({
                    url: window.checkoutConfig.payment.infinitepay.url_tokenize,
                    contentType: "application/json",
                    type: "POST",
                    beforeSend: function (xhr) {
                        xhr.setRequestHeader('Authorization', "Bearer " + window.checkoutConfig.payment.infinitepay.jwt);
                    },
                    data: JSON.stringify({
                        number: creditCardNumber, 
                        expiration_month: creditCardExpMonth.padStart(2, '0'),
                        expiration_year: creditCardExpYear.substring(2, creditCardExpYear.length)
                    }),
                    async: false,
                    success: function (data) { 
                        cardToken = data.token;
                    }});
            }
            return cardToken;
        }
    });
});
