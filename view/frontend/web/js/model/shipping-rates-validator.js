/**
 * Easyship.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Easyship.com license that is
 * available through the world-wide-web at this URL:
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade this extension to newer
 * version in the future.
 *
 * @category    Goeasyship
 * @package     Goeasyship_Shipping
 * @copyright   Copyright (c) 2022 Easyship (https://www.easyship.com/)
 * @license     https://www.apache.org/licenses/LICENSE-2.0
 */

define([
    'jquery',
    'mageUtils',
    './shipping-rates-validation-rules',
    'mage/translate'
], function ($, utils, validationRules, $t) {
    'use strict';

    var checkoutConfig = window.checkoutConfig;

    return {
        validationErrors: [],

        /**
         * @param {Object} address
         * @return {Boolean}
         */
        validate: function (address) {
            var rules = validationRules.getRules(),
                self = this;

            $.each(rules, function (field, rule) {
                var message;

                if (rule.required && utils.isEmpty(address[field])) {
                    message = $t('Field ') + field + $t(' is required.');
                    self.validationErrors.push(message);
                }
            });

            if (!this.validationErrors.length) {
                if (address['country_id'] == checkoutConfig.originCountryCode) {
                    return !utils.isEmpty(address.postcode);
                }

                return true;
            }

            return false;
        }
    };
});
