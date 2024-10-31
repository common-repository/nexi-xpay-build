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

class WC_Gateway_NPG_APM extends WC_Gateway_NPG_Generic_Method
{

    protected $selectedCard;

    public function __construct($code, $title, $description, $selectedCard, $img)
    {
        parent::__construct('xpay_npg_' . strtolower($code), false);

        $this->selectedCard = $selectedCard;

        $this->method_title = $title;
        $this->method_description = $description;

        $this->title = $this->method_title;
        $this->icon = $img;
        $this->description = $this->method_description;
    }

    public function process_payment($order_id)
    {
        $order = new \WC_Order($order_id);
        $result = 'failure';

        try {
            $recurringPayment = WC_Nexi_Helper::order_or_cart_contains_subscription($order);

            update_post_meta($order_id, "_npg_" . "is_build", false);

            $redirectLink = WC_Gateway_NPG_API::getInstance()->new_payment_link($order, $recurringPayment, WC()->cart, false, false, $this->selectedCard, 0);

            $result = 'success';
        } catch (\Throwable $th) {
            wc_add_notice($th->getMessage(), "error");

            $redirectLink = $this->get_return_url($order);
        }

        return array(
            'result' => $result,
            'redirect' => $redirectLink,
        );
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

}
