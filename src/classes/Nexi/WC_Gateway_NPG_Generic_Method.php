<?php

/**
 * Copyright (c) 2019 Nexi Payments S.p.A.
 *
 * @author      iPlusService S.r.l.
 * @category    Payment Module
 * @package     Nexi XPay
 * @version     7.0.2
 * @copyright   Copyright (c) 2019 Nexi Payments S.p.A. (https://ecommerce.nexi.it)
 * @license     GNU General Public License v3.0
 */

namespace Nexi;

abstract class WC_Gateway_NPG_Generic_Method extends WC_Gateway_XPay_Generic_Method
{

    public function __construct($id, $recurring)
    {
        parent::__construct($id, $recurring);

        // Admin page
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_save'));

        add_action('woocommerce_scheduled_subscription_payment_' . $this->id, array($this, 'scheduled_subscription_payment'), 10, 2);
    }

    public function get_sorted_cards_images()
    {
        $available_methods_npg = json_decode(\WC_Admin_Settings::get_option('xpay_npg_available_methods'), true);
        $cards = [
            'MC',
            'MAE',
            'VISA',
            //V Pay
            'AMEX',
            'JCB',
            //UPI
        ];

        $image_list = array_fill(0, count($cards), null);

        if (is_array($available_methods_npg)) {
            foreach ($available_methods_npg as $apm) {
                if ($apm['paymentMethodType'] != 'CARDS') {
                    continue;
                }

                if (!in_array($apm['circuit'], $cards)) {
                    continue;
                }

                array_splice($image_list, array_search($apm['circuit'], $cards), 1, [$apm['imageLink']]);
            }
        }

        $image_list = array_filter($image_list);

        $img_list_html = "";

        foreach ($image_list as $img) {
            $img_list_html .= '  <div class="img-container"><img src="' . $img . '"></div>';
        }

        if ($img_list_html != "") {
            $img_list_html = '<div class="nexixpay-loghi-container">' . $img_list_html . '</div>';
        }

        return $img_list_html;
    }

    function process_admin_save()
    {
        $this->process_admin_options();
    }

    /**
     * on subscription renewal a new order is crated and post meta from the original one are copied and saved with new order's id as post_id
     * 
     * @param float $amount_to_charge
     * @param \WC_Order $order
     */
    public function scheduled_subscription_payment($amount_to_charge, $order)
    {
        try {
            $subscriptionId = $order->get_meta('_subscription_renewal');

            if ($subscriptionId) {
                $idToUse = $subscriptionId;
            } else {
                $idToUse = $order->get_id();
            }

            Log::actionInfo(__METHOD__ . "::" . __LINE__ . ' $idToUse ' . json_encode($idToUse));

            $contractId = get_post_meta($idToUse, '_npg_' . 'recurringContractId', true);

            if ($contractId === null || $contractId === '') {
                throw new \Exception('Invalid contract id');
            }

            $currency = $order->get_currency();

            $newOrderId = \Nexi\WC_Gateway_NPG_API::getInstance()->recurring_payment($order, $contractId, \Nexi\WC_Gateway_NPG_Currency::calculate_amount_to_min_unit($amount_to_charge, $currency));

            // must be updated otherwise refferrs to the the first payment
            update_post_meta($order->get_id(), '_npg_' . "orderId", $newOrderId);

            $order->payment_complete($newOrderId);
        } catch (\Exception $exc) {
            Log::actionWarning(__FUNCTION__ . ': ' . $exc->getMessage());

            $order->update_status('failed');
        }
    }

    /**
     * order state is changed to "Refunded" automatically when total amount is refunded
     * 
     * @param type $order_id
     * @param type $amount
     * @param type $reaseon
     * @return type
     */
    public function process_refund($order_id, $amount = null, $reaseon = "")
    {
        if ($amount <= 0) {
            return new \WP_Error("invalid_refund_amount", __('Invalid refund amount.', 'woocommerce-gateway-nexi-xpay'));
        }

        try {
            $res = \Nexi\WC_Gateway_NPG_API::getInstance()->refund($order_id, $amount);

            return $res;
        } catch (\Exception $exc) {
            return new \WP_Error("refund_operation_error", $exc->getMessage());
        }
    }

}
