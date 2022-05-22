define([
    'uiComponent',
    'Magento_Checkout/js/model/payment/renderer-list'
], function (Component, rendererList) {
    'use strict';
    rendererList.push({ type: 'infinitepay', component: 'Cloudwalk_InfinitePay/js/view/payment/method-renderer/payment' });
    return Component.extend({});
});