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

class WC_Gateway_XPay_Process_Completion
{

    public static function rest_api_init()
    {
        register_rest_route(
            'woocommerce-gateway-nexi-xpay',
            '/s2s/xpay/(?P<id>\d+)',
            array(
                'methods' => 'POST',
                'callback' => '\Nexi\WC_Gateway_XPay_Process_Completion::s2s',
                'args' => [
                    'id' => array(),
                ],
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            'woocommerce-gateway-nexi-xpay',
            '/redirect/xpay/(?P<id>\d+)',
            array(
                'methods' => array('GET', 'POST'),
                'callback' => '\Nexi\WC_Gateway_XPay_Process_Completion::redirect',
                'args' => [
                    'id' => array(),
                ],
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            'woocommerce-gateway-nexi-xpay',
            '/cancel/xpay/(?P<id>\d+)',
            array(
                'methods' => 'GET',
                'callback' => '\Nexi\WC_Gateway_XPay_Process_Completion::cancel',
                'args' => [
                    'id' => array(),
                ],
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            'woocommerce-gateway-nexi-xpay',
            '/process_account/xpay' . '/(?P<id>\d+)',
            array(
                'methods' => array('POST'),
                'callback' => '\Nexi\WC_Gateway_XPay_Process_Completion::process_account',
                'args' => [
                    'id' => array(),
                ],
                'permission_callback' => function () {
                    return current_user_can('manage_woocommerce');
                }
            )
        );
    }

    public static function register()
    {
        add_action('woocommerce_before_cart', '\Nexi\WC_Gateway_XPay_Process_Completion::add_message_to_cart');
    }

    public static function add_message_to_cart()
    {
        if (key_exists("order_id", $_GET)) {
            $order_id = $_GET["order_id"];

            $error_message = get_post_meta($order_id, '_xpay_' . 'last_error', true);

            if ($error_message != "") {
                wc_add_notice(__('Payment error, please try again', 'woocommerce-gateway-nexi-xpay') . " (" . htmlentities($error_message) . ")", 'error');
            }

            $payment_error = get_post_meta($order_id, '_xpay_' . 'payment_error', true);

            if ($payment_error != "") {
                wc_add_notice(htmlentities($payment_error), 'error');
            }
        }
    }

    public static function s2s($data)
    {
        $params = $data->get_params();
        $order_id = $params["id"];

        Log::actionInfo(__FUNCTION__ . ": S2S notification for order id " . $order_id);

        $status = "500";
        $payload = array(
            "outcome" => "KO",
            "order_id" => $order_id,
        );

        try {
            if (\Nexi\WC_Gateway_XPay_API::getInstance()->validate_return_mac($_POST)) {
                $order = new \WC_Order($order_id);

                if ($_POST['esito'] == "OK") {
                    if (!in_array($order->get_status(), ['completed', 'processing'])) {
                        WC_Save_Order_Meta::saveSuccessXPay(
                            $order_id,
                            $_POST['alias'],
                            WC_Nexi_Helper::nexi_array_key_exists($_POST, 'num_contratto') ? $_POST['num_contratto'] : '',
                            $_POST['codTrans'],
                            WC_Nexi_Helper::nexi_array_key_exists($_POST, 'scadenza_pan') ? $_POST['scadenza_pan'] : ''
                        );

                        $completed = $order->payment_complete($_POST["codTrans"]);
                    }

                    if (!isset($completed) || $completed) {
                        $status = "200";
                        $payload = array(
                            "outcome" => "OK",
                            "order_id" => $order_id,
                        );
                    }
                } else if ($_POST['esito'] == "PEN") {
                    if ($order->get_status() != 'pd-pending-status') {
                        $order->update_status('pd-pending-status');
                    }

                    $status = "200";
                    $payload = array(
                        "outcome" => "OK",
                        "order_id" => $order_id,
                    );
                } else {
                    if (!in_array($order->get_status(), ['failed', 'cancelled'])) {
                        $order->update_status('failed');
                    }

                    update_post_meta($order_id, '_xpay_' . 'last_error', $_POST["messaggio"]);

                    $order->add_order_note(__('Payment error', 'woocommerce-gateway-nexi-xpay') . ": " . $_POST["messaggio"]);

                    $status = "200";
                    $payload = array(
                        "outcome" => "OK",
                        "order_id" => $order_id,
                    );
                }
            }

            update_post_meta($order_id, '_xpay_' . 'post_notification_timestamp', time());
        } catch (\Exception $exc) {
            Log::actionInfo(__FUNCTION__ . ": " . $exc->getTraceAsString());
        }

        return new \WP_REST_Response($payload, $status, array());
    }

    public static function redirect($data)
    {
        $params = $data->get_params();

        $order_id = $params["id"];

        $order = new \WC_Order($order_id);

        $post_notification_timestamp = get_post_meta($order_id, '_xpay_' . 'post_notification_timestamp', true);

        //s2s not recived, so we need to update the order based the data recived in params 
        if ($post_notification_timestamp == "") {
            Log::actionInfo(__FUNCTION__ . ": s2s notification for order id " . $order_id . " not recived, changing oreder status from request params");

            if ($params['esito'] == "OK") {
                if (!in_array($order->get_status(), ['completed', 'processing'])) {
                    WC_Save_Order_Meta::saveSuccessXPay(
                        $order_id,
                        $params['alias'],
                        WC_Nexi_Helper::nexi_array_key_exists($params, 'num_contratto') ? $params['num_contratto'] : '',
                        $params['codTrans'],
                        $params['scadenza_pan']
                    );

                    $order->payment_complete($params["codTrans"]);
                }
            } else if ($params['esito'] == "PEN") {
                if ($order->get_status() != 'pd-pending-status') {
                    // if order in this status, it is considerated as completed/payed
                    $order->update_status('pd-pending-status');
                }
            } else {
                if (!in_array($order->get_status(), ['failed', 'cancelled'])) {
                    $order->update_status('failed');
                }

                update_post_meta($order_id, '_xpay_' . 'last_error', $params["messaggio"]);

                $order->add_order_note(__('Payment error', 'woocommerce-gateway-nexi-xpay') . ": " . $params["messaggio"]);
            }
        }

        Log::actionInfo(__FUNCTION__ . ": user redirect for order id " . $order_id . ' - ' . (array_key_exists('esito', $params) ? $params['esito'] : ''));
        
        if ($order->needs_payment() || $order->get_status() == 'cancelled') {
            return new \WP_REST_Response(
                "redirecting failed...",
                "303",
                array("Location" => $order->get_cancel_order_url_raw())
            );
        }

        return new \WP_REST_Response(
            "redirecting success...",
            "303",
            array("Location" => $order->get_checkout_order_received_url())
        );
    }

    public static function cancel($data)
    {
        $params = $data->get_params();

        $order_id = $params["id"];

        if (($params['esito'] ?? '') === "ERRORE" && $params['warning']) {

            if (stripos($params['warning'], 'deliveryMethod') !== false) {
                update_post_meta($order_id, '_xpay_' . 'payment_error', __('It was not possible to process the payment, check that the shipping address set is correct.', 'woocommerce-gateway-nexi-xpay'));
            } else {
                update_post_meta($order_id, '_xpay_' . 'payment_error', __('Payment canceled: ', 'woocommerce-gateway-nexi-xpay') . $params['warning']);
            }
        } else {
            update_post_meta($order_id, '_xpay_' . 'payment_error', __('Payment has been cancelled.', 'woocommerce-gateway-nexi-xpay'));
        }

        $order = new \WC_Order($order_id);

        return new \WP_REST_Response(
            "failed...",
            "303",
            array("Location" => $order->get_cancel_order_url_raw())
        );
    }

    public static function process_account($data)
    {
        try {
            $params = $data->get_params();
            $order_id = $params["id"];

            $amount = WC_Nexi_Helper::mul_bcmul($_POST['amount'], 100, 0);

            if (!is_numeric($amount)) {
                throw new \Exception(__('Invalid amount.', 'woocommerce-gateway-nexi-xpay'));
            }

            $order = new \WC_Order($order_id);

            $codTrans = WC_Nexi_Helper::get_xpay_post_meta($order_id, 'codTrans');

            if (empty($codTrans)) {
                throw new \Exception(sprintf(__('Unable to account order %s. Order does not have XPay capture reference.', 'woocommerce-gateway-nexi-xpay'), $order_id));
            }

            return WC_Gateway_XPay_API::getInstance()->account($codTrans, $amount, $order->get_currency());
        } catch (\Exception $exc) {
            return new \WP_Error("broke", $exc->getMessage());
        }
    }

}
