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
            var data = {
                'method': this.getCode(),
                'additional_data': {
                    'installments': $('#' + this.getCode() + '_installments').val(),
                    'cc_holdername': $('#' + this.getCode() + '_cc_holdername').val(),
                    'cc_cid': this.creditCardVerificationNumber(),
                    'cc_ss_start_month': this.creditCardSsStartMonth(),
                    'cc_ss_start_year': this.creditCardSsStartYear(),
                    'cc_type': this.creditCardType(),
                    'cc_exp_year': this.creditCardExpYear(),
                    'cc_exp_month': this.creditCardExpMonth(),
                    'cc_number': this.creditCardNumber()
                }
            };
            return data;
        },
        getInstallments: function () {
            let infinite_pay_tax = [1, 1.3390, 1.5041, 1.5992, 1.6630, 1.7057, 2.3454, 2.3053, 2.2755, 2.2490, 2.2306, 2.2111];
            let max_installments = 12;
            let max_installments_free = 8;

            let amount = window.checkoutConfig.totalsData.grand_total;
            let installments_value = [];
            for (let i = 1; i <= max_installments; i++) {
                let tax = !(max_installments_free >= i) && i > 1;
                var interest = 1;
                if (tax) {
                    interest = infinite_pay_tax[i - 1] / 100;
                }
                let value = !tax ? amount / i : amount * (interest / (1 - Math.pow(1 + interest, - i)));
                installments_value.push({
                    value: value,
                    interest: tax,
                    text: 'R$ ' + value
                });
            }

            let installments = [];
            for (let i = 1; i <= max_installments; i++) {
                if (i === 1) {
                    installments.push({ value: 1, text: amount + ' à vista' });
                } else {
                    let new_value = Math.round(installments_value[i - 1]['value'], 2);
                    let has_interest = (installments_value[i - 1]['interest'] ? 'com' : 'sem') + ' juros';
                    let msg = i + 'x de R$ ' + new_value + ' ' + has_interest;
                    installments.push({ value: i, text: msg });
                }
            }
            return installments;
        },
        getCode: function () {
            return 'infinitepay';
        },
        isActive: function () {
            return true;
        },
        validate: function () {
            var $form = $('#' + this.getCode() + '-form');
            return $form.validation() && $form.validation('isValid');
        }
    });
});