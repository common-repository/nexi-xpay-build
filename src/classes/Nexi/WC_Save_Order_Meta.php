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

class WC_Save_Order_Meta
{

    public static function saveSuccessXPay($order_id, $alias, $num_contratto, $codTrans, $scadenza_pan)
    {
        $metaPrefix = "_xpay_";
        delete_post_meta($order_id, $metaPrefix . "last_error");

        if (
            function_exists("wcs_is_subscription") && wcs_is_subscription($order_id) ||
            (function_exists("wcs_order_contains_subscription") &&
                (wcs_order_contains_subscription($order_id) || wcs_order_contains_renewal($order_id))
            )
        ) {
            if (get_option("woocommerce_subscriptions_turn_off_automatic_payments") !== "yes") {

                $subscriptions = wcs_get_subscriptions_for_order($order_id);
                foreach ($subscriptions as $subscription) {
                    $subscription_id = $subscription->get_id();

                    update_post_meta($subscription_id, $metaPrefix . "alias", $alias);
                    update_post_meta($subscription_id, $metaPrefix . "num_contratto", $num_contratto);
                    update_post_meta($subscription_id, $metaPrefix . "codTrans", $codTrans);
                    update_post_meta($subscription_id, $metaPrefix . "scadenza_pan", $scadenza_pan);
                }
            }
        }

        update_post_meta($order_id, $metaPrefix . "alias", $alias);
        update_post_meta($order_id, $metaPrefix . "num_contratto", $num_contratto);
        update_post_meta($order_id, $metaPrefix . "codTrans", $codTrans);
        update_post_meta($order_id, $metaPrefix . "scadenza_pan", $scadenza_pan);
    }


    public static function saveSuccessNpg($order_id, $authorization)
    {
        $metaPrefix = "_npg_";
        delete_post_meta($order_id, $metaPrefix . "last_error");

        // if it is a subscription, in addition to the order a subscription order is created, in which we need to save some information about the original order
        // doing so, when a new recurring payment is made for the same order, this data is copied automatically to the new order and can be used in the payment process
        if (
            (function_exists("wcs_is_subscription") && wcs_is_subscription($order_id)) ||
            (function_exists("wcs_order_contains_subscription") && (wcs_order_contains_subscription($order_id) || wcs_order_contains_renewal($order_id)))
        ) {
            if (get_option("woocommerce_subscriptions_turn_off_automatic_payments") !== "yes") {

                $subscriptions = wcs_get_subscriptions_for_order($order_id);
                foreach ($subscriptions as $subscription) {
                    $subscription_id = $subscription->get_id();

                    foreach ([
                        "orderId",
                        "paymentMethod",
                        "paymentCircuit",
                        "operationCurrency",
                        "customerInfo"
                    ] as $var_name) {
                        if (\Nexi\WC_Nexi_Helper::nexi_array_key_exists($authorization, $var_name)) {
                            update_post_meta($subscription_id, $metaPrefix . $var_name, $authorization[$var_name]);
                        }
                    }

                    update_post_meta($subscription_id, $metaPrefix . "recurringContractId", get_post_meta($order_id,  $metaPrefix . 'recurringContractId', true));
                }
            }
        }

        foreach ([
            "orderId",
            "operationId",
            "operationType",
            "operationResult",
            "operationTime",
            "paymentMethod",
            "paymentCircuit",
            "paymentInstrumentInfo",
            "paymentEndToEndId",
            "cancelledOperationId",
            "operationAmount",
            "operationCurrency",
            "customerInfo"
        ] as $var_name) {
            update_post_meta($order_id, $metaPrefix . $var_name, $authorization[$var_name]);
        }
    }
}
