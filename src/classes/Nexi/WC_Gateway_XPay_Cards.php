<?php

/**
 * Copyright (c) 2019 Nexi Payments S.p.A.
 *
 * @author      iPlusService S.r.l.
 * @category    Payment Module
 * @package     Nexi XPay
 * @version     6.0.0
 * @copyright   Copyright (c) 2019 Nexi Payments S.p.A. (https://ecommerce.nexi.it)
 * @license     GNU General Public License v3.0
 */

namespace Nexi;

class WC_Gateway_XPay_Cards extends WC_Gateway_XPay_Generic_Method
{

    public function __construct()
    {
        parent::__construct('xpay', true);

        $this->supports = array_merge($this->supports, ['tokenization']);

        $this->method_title = __('Payment cards', 'woocommerce-gateway-nexi-xpay');
        $this->method_description = __('Payment gateway.', 'woocommerce-gateway-nexi-xpay');
        $this->title = $this->method_title;

        $avaiable_methods_xpay = json_decode(\WC_Admin_Settings::get_option('xpay_available_methods'), true);
        $img_list = "";

        if (is_array($avaiable_methods_xpay)) {
            foreach ($avaiable_methods_xpay as $am) {
                if ($am['type'] != "CC") {
                    continue;
                }

                $img_list .= '  <div class="img-container"><img src="' . $am['image'] . '"></div>';
            }
        }

        if ($img_list != "") {
            $img_list = '<div class="nexixpay-loghi-container">' . $img_list . '</div>';
        }

        $this->description = $img_list . __("Pay securely by credit, debit and prepaid card. Powered by Nexi.", 'woocommerce-gateway-nexi-xpay');

        if (\WC_Admin_Settings::get_option('xpay_logo_small') == "") {
            $this->icon = WC_WOOCOMMERCE_GATEWAY_NEXI_XPAY_DEFAULT_LOGO_URL;
        } else {
            $this->icon = \WC_Admin_Settings::get_option('xpay_logo_small');
        }

        add_action('woocommerce_receipt_' . $this->id, array($this, 'exec_payment'));

        // Admin page
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_save'));

        add_action('woocommerce_scheduled_subscription_payment_' . $this->id, array($this, 'scheduled_subscription_payment'), 10, 2);

        $this->selectedCard = "CC";
    }

    function init_form_fields()
    {
        parent::init_form_fields();
    }

}
