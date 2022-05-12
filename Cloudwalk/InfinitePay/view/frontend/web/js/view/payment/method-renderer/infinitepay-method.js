// define(
//     [
//         'Magento_Checkout/js/view/payment/cc'
//     ],
//     function (Component) {
//         'use strict';
//         return Component.extend({
//             defaults: {
//                 template: 'Cloudwalk_InfinitePay/payment/infinitepay'
//             },
//             getMailingAddress: function () {
//                 return window.checkoutConfig.payment.checkmo.mailingAddress;
//             },
//             getInstructions: function () {
//                 return window.checkoutConfig.payment.instructions[this.item.method];
//             },
//         });
//     }
// );

define(
    [
        'Magento_Payment/js/view/payment/cc-form',
        'jquery',
        'Magento_Payment/js/model/credit-card-validation/validator'
    ],
    function (Component, $) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'Cloudwalk_InfinitePay/payment/infinitepay'
            },

            getCode: function() {
                return 'infinitepay';
            },

            isActive: function() {
                return true;
            },

            validate: function() {
                var $form = $('#' + this.getCode() + '-form');
                return $form.validation() && $form.validation('isValid');
            }
        });
    }
);