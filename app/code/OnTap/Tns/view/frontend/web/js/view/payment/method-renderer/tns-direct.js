/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
/*global define*/
define(
    [
        'jquery',
        'Magento_Payment/js/view/payment/cc-form',
        'Magento_Checkout/js/model/payment/additional-validators'
    ],
    function ($, ccFormComponent, additionalValidators) {
        'use strict';

        return ccFormComponent.extend({
            defaults: {
                template: 'OnTap_Tns/payment/tns-direct',
                active: false,
                scriptLoaded: false,
                imports: {
                    onActiveChange: 'active'
                }
            },
            placeOrderHandler: null,
            validateHandler: null,

            hasSsCardType: function () {
                return false;
            },

            initObservable: function () {
                this._super()
                    .observe('active scriptLoaded');

                return this;
            },


            setPlaceOrderHandler: function (handler) {
                this.placeOrderHandler = handler;
            },


            setValidateHandler: function (handler) {
                this.validateHandler = handler;
            },


            context: function () {
                return this;
            },


            isShowLegend: function () {
                return true;
            },


            getCode: function () {
                return 'tns_direct';
            },


            isActive: function () {
                var active = this.getCode() === this.isChecked();

                this.active(active);

                return active;
            },


            onActiveChange: function (isActive) {
                if (isActive && !this.scriptLoaded()) {
                    this.loadScript();
                }
            },


            loadScript: function () {
                var state = this.scriptLoaded;

                $('body').trigger('processStart');
                //require([this.getUrl()], function () {
                    state(true);
                    $('body').trigger('processStop');
                //});
            },

            getData: function () {
                return {
                    'method': this.item.method,
                    'additionalData': {
                        'cc_type': this.creditCardType(),
                        'cc_exp_year': this.creditCardExpYear(),
                        'cc_exp_month': this.creditCardExpMonth(),

                        'cc_number': this.creditCardNumber(),
                        'cc_cid': this.creditCardVerificationNumber(),
                        'cc_ss_start_month': this.creditCardSsStartMonth(),
                        'cc_ss_start_year': this.creditCardSsStartYear()
                    }
                };
            },


            placeOrder: function () {
                if (this.validateHandler() && additionalValidators.validate()) {
                    this.isPlaceOrderActionAllowed(false);
                    this._super();
                }
            }
        });
    }
);