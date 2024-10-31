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

class WC_Gateway_XPay_APM extends WC_Gateway_XPay_Generic_Method
{

    protected $selectedCard;

    public function __construct($code, $description, $selectedCard, $img)
    {
        parent::__construct('xpay_' . strtolower($code), false);

        $this->selectedCard = $selectedCard;

        if ($this->selectedCard === "PAGODIL") {
            $this->method_title = __("Pay in installments without interest", "woocommerce-gateway-nexi-xpay");
        } else {
            $this->method_title = $description;
        }

        $this->method_description = $description . __(" via Nexi XPay", "woocommerce-gateway-nexi-xpay");
        $this->title = $this->method_title;
        $this->icon = $img;
        $this->description = $this->method_description;

        add_action('woocommerce_receipt_' . $this->id, array($this, 'exec_payment'));

        // Admin page
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_save'));

        add_action('woocommerce_scheduled_subscription_payment_' . $this->id, array($this, 'scheduled_subscription_payment'), 10, 2);
    }

    function init_form_fields()
    {
        parent::init_form_fields();
        $title = __("APMs do not have a custom configuration. ", 'woocommerce-gateway-nexi-xpay');
        $title .= " ";
        $title .= __("Please use ", 'woocommerce-gateway-nexi-xpay');
        $title .= __('Nexi XPay', 'woocommerce-gateway-nexi-xpay');
        $title .= __(" configurations", 'woocommerce-gateway-nexi-xpay');

        $this->form_fields = array(
            'title_section_1' => array(
                'title' => $title,
                'type' => 'title',
            ),
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'checkbox',
                'label' => __("Enable Nexi XPay payment plugin.", 'woocommerce-gateway-nexi-xpay'),
                'default' => 'yes'
            ),
        );
    }

    function payment_fields()
    {
        if ($this->selectedCard === "PAGODIL") {
            $installmentsNumber = \Nexi\WC_Pagodil_Widget::getAvailableInstallmentsNumber();

            if (count($installmentsNumber) === 1) {
                $installmentsAmount = \Nexi\WC_Pagodil_Widget::calcInstallmentsAmount(WC_Nexi_Helper::mul_bcmul(WC()->cart->total, 100, 1), end($installmentsNumber));

                $oneInstallmentInfo = sprintf(__('Amount: %s installments of %s€', 'woocommerce-gateway-nexi-xpay'), end($installmentsNumber), $installmentsAmount);
            }

            $path = plugin_dir_path(WC_ECOMMERCE_GATEWAY_NEXI_MAIN_FILE);

            include_once $path . 'templates/pagodil_checkout.php';
        } else {
            echo $this->description;
        }
    }

}
