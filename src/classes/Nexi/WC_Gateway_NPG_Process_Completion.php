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

class WC_Gateway_NPG_Process_Completion
{

    public static function rest_api_init()
    {
        register_rest_route(
            'woocommerce-gateway-nexi-xpay',
            '/s2s/npg/(?P<id>\d+)',
            array(
                'methods' => 'POST',
                'callback' => '\Nexi\WC_Gateway_NPG_Process_Completion::s2s',
                'args' => [
                    'id' => array(),
                ],
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            'woocommerce-gateway-nexi-xpay',
            '/redirect/npg/(?P<id>\d+)',
            array(
                'methods' => array('GET', 'POST'),
                'callback' => '\Nexi\WC_Gateway_NPG_Process_Completion::redirect',
                'args' => [
                    'id' => array(),
                ],
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            'woocommerce-gateway-nexi-xpay',
            '/cancel/npg/(?P<id>\d+)',
            array(
                'methods' => 'GET',
                'callback' => '\Nexi\WC_Gateway_NPG_Process_Completion::cancel',
                'args' => [
                    'id' => array(),
                ],
                'permission_callback' => '__return_true',
            )
        );

        register_rest_route(
            'woocommerce-gateway-nexi-xpay',
            '/process_account/npg' . '/(?P<id>\d+)',
            array(
                'methods' => array('POST'),
                'callback' => '\Nexi\WC_Gateway_NPG_Process_Completion::process_account',
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
        add_action('woocommerce_before_cart', '\Nexi\WC_Gateway_NPG_Process_Completion::add_message_to_cart');
    }

    public static function add_message_to_cart()
    {
        if (key_exists("order_id", $_GET)) {
            $order_id = $_GET["order_id"];

            $error_message = get_post_meta($order_id, '_npg_' . 'last_error', true);

            wc_add_notice(__('Payment error, please try again', 'woocommerce-gateway-nexi-xpay') . ($error_message != "" ? " (" . htmlentities($error_message ?? "") . ")" : ""), 'error');

            $payment_error = get_post_meta($order_id, '_npg_' . 'payment_error', true);

            if ($payment_error != "") {
                wc_add_notice(htmlentities($payment_error), 'error');
            }
        }
    }

    /**
     * checks if the id is a npg build orderId and return the correct wc order_id
     * if it's not a build orderId then just return the id because already a wc order_id
     *
     * @param string $order_id
     * @return string
     */
    private static function check_if_build_and_get_wc_order_id($order_id)
    {
        if ((bool) get_post_meta($order_id, "_npg_" . "is_build", true) && get_post_meta($order_id, "_npg_" . "wc_order_id", true)) {
            return get_post_meta($order_id, "_npg_" . "wc_order_id", true);
        }

        return $order_id;
    }

    public static function s2s($data)
    {
        $params = $data->get_params();
        $order_id = static::check_if_build_and_get_wc_order_id($params["id"]);

        Log::actionInfo(__FUNCTION__ . ": S2S notification for order id " . $order_id);

        $status = "500";
        $payload = [
            "outcome" => "KO",
            "order_id" => $order_id,
        ];

        if (!isset($params['securityToken']) || !array_key_exists('operation', $params) || !isset($params['operation']['orderId'])) {
            Log::actionWarning(__FUNCTION__ . ': Required info not set in request: ' . json_encode($params));

            return new \WP_REST_Response($payload, $status, []);
        }

        if (!WC_Gateway_NPG_Lock_Handler::check_and_take_lock($order_id, __FUNCTION__)) {
            Log::actionWarning(__FUNCTION__ . ': Couldn\'t get execution lock');

            return new \WP_REST_Response($payload, $status, []);
        }

        Log::actionInfo(__FUNCTION__ . ': got lock - ' . date('d-m-Y H:i:s'));

        $securityToken = get_post_meta($order_id, "_npg_" . "securityToken", true);

        if ($params['securityToken'] != $securityToken) {
            Log::actionWarning(__FUNCTION__ . ': Invalid securityToken for order: ' . $order_id . ' - Request: ' . json_encode($params));

            WC_Gateway_NPG_Lock_Handler::release_lock($order_id);

            return new \WP_REST_Response($payload, $status, []);
        }

        static::change_order_status_by_operation($order_id, $params['operation']);

        $status = "200";
        $payload = [
            "outcome" => "OK",
            "order_id" => $order_id,
        ];

        // $order_id, '_npg_' . 'post_notification_timestamp_' . get_post_meta($order_id, '_npg_' . 'orderId', true) -> to be sure to reffer to the last pyment initialization,
        // more then one payment for the same order can be made if the first ìs declined, the second can reffer to the same order but have a different orderId
        update_post_meta($order_id, '_npg_' . 'post_notification_timestamp_' . get_post_meta($order_id, '_npg_' . 'orderId', true), time());

        $order = new \WC_Order($order_id);

        if (in_array($order->get_status(), ['completed', 'processing'])) {
            self::save_card_token($order_id, $params['operation']);
        }

        WC_Gateway_NPG_Lock_Handler::release_lock($order_id);

        return new \WP_REST_Response($payload, $status, []);
    }

    public static function redirect($data)
    {
        sleep(2);

        $params = $data->get_params();

        $order_id = static::check_if_build_and_get_wc_order_id($params["id"]);

        Log::actionInfo(__FUNCTION__ . ": return to shop for order id " . $order_id);

        $npg_order_id = get_post_meta($order_id, '_npg_' . 'orderId', true);

        if (!WC_Gateway_NPG_Lock_Handler::check_and_take_lock($order_id, __FUNCTION__)) {
            Log::actionWarning(__FUNCTION__ . ': Couldn\'t get execution lock');

            $order = new \WC_Order($order_id);

            return new \WP_REST_Response(
                "redirecting failed...",
                "303",
                array("Location" => $order->get_cancel_order_url_raw())
            );
        }

        Log::actionInfo(__FUNCTION__ . ': got lock - ' . date('d-m-Y H:i:s'));

        $c = 0;

        do {
            sleep(1);

            $post_notification_timestamp = get_post_meta($order_id, '_npg_' . 'post_notification_timestamp_' . $npg_order_id, true);

            if ($post_notification_timestamp !== "") {
                break;
            }

            $c++;

            $authorizationRecord = WC_Gateway_NPG_API::getInstance()->get_order_status($order_id);

            if ($c == 20) {
                Log::actionWarning(__FUNCTION__ . ": reached max number of GET for order: " . $order_id);
                break;
            }

            if ($authorizationRecord === null) {
                Log::actionWarning(__FUNCTION__ . ": authorization operation not found for order: " . $order_id);
                continue;
            }

            Log::actionInfo(__FUNCTION__ . ": s2s not recived for order: " . $order_id . " changing status from order info [" . $c . "]: " . json_encode($authorizationRecord));

            static::change_order_status_by_operation($order_id, $authorizationRecord);
        } while ($authorizationRecord === null || $authorizationRecord['operationResult'] == 'PENDING');

        Log::actionInfo(__FUNCTION__ . ": user redirect for order id " . $order_id);

        $order = new \WC_Order($order_id);

        if ($order->needs_payment() || $order->get_status() == 'cancelled') {
            WC_Gateway_NPG_Lock_Handler::release_lock($order_id);

            return new \WP_REST_Response(
                "redirecting failed...",
                "303",
                array("Location" => $order->get_cancel_order_url_raw())
            );
        }

        if (isset($authorizationRecord) && $authorizationRecord !== null) {
            self::save_card_token($order_id, $authorizationRecord);
        }

        WC_Gateway_NPG_Lock_Handler::release_lock($order_id);

        return new \WP_REST_Response(
            "redirecting success...",
            "303",
            array("Location" => $order->get_checkout_order_received_url())
        );
    }

    public static function change_order_status_by_operation($order_id, $operation)
    {
        $order = new \WC_Order($order_id);

        if (!isset($operation['operationResult']) || empty($operation['operationResult'])) {
            \Nexi\Log::actionWarning(__FUNCTION__ . ': payment error - operation status not valid for authorization: ' . $order_id . ' - ' . json_encode($operation));
        }

        switch ($operation['operationResult']) {
            case NPG_OR_AUTHORIZED:
            case NPG_OR_EXECUTED:
                if (!in_array($order->get_status(), ['completed', 'processing'])) {
                    $completed = $order->payment_complete(get_post_meta($order_id, "_npg_" . "orderId", true));

                    if ($completed) {
                        Log::actionInfo(__FUNCTION__ . ": order completed: " . $order_id);

                        WC_Save_Order_Meta::saveSuccessNpg(
                            $order_id,
                            $operation
                        );
                    } else {
                        Log::actionWarning(__FUNCTION__ . ': unable to change order status: ' . $order->get_status());
                    }
                }
                break;

            case NPG_OR_PENDING: // if operationResult is pending, the final operation status isn't sure and the order is set in pending
                Log::actionWarning(__FUNCTION__ . ': operation not in a final status');

                if ($order->get_status() != 'pending') {
                    $order->update_status('pending');   // not using 'pd-pending-status' because in this status the order is considered as paid and completed
                }
                break;

            case NPG_OR_CANCELED:
            case NPG_OR_CANCELLED:
                Log::actionInfo(__FUNCTION__ . ': payment canceled');
                break;

            case NPG_OR_DECLINED:
            case NPG_OR_DENIED_BY_RISK:
            case NPG_OR_THREEDS_FAILED:
            case NPG_OR_3DS_FAILED:
            case NPG_OR_FAILED:
                if (!in_array($order->get_status(), ['failed', 'cancelled'])) {
                    $order->update_status('failed');
                }

                Log::actionWarning(__FUNCTION__ . ': payment error');

                $error = $operation['operationResult'];

                foreach ($operation['warnings'] as $warning) {
                    if ($warning['description'] != '') {
                        $error .= ' - ' . $warning['description'];
                    }
                }

                update_post_meta($order_id, '_npg_' . 'last_error', $error);

                $order->add_order_note(__('Payment error', 'woocommerce-gateway-nexi-xpay') . ": " . $error);
                break;

            default:
                \Nexi\Log::actionWarning(__FUNCTION__ . ': payment error - not managed operation status: ' . json_encode($operation));

                $order->add_order_note(__('Payment error', 'woocommerce-gateway-nexi-xpay'));
                break;
        }
    }

    private static function save_card_token($orderId, $operation)
    {
        $order = new \WC_Order($orderId);

        try {
            if ($operation && $operation['paymentMethod'] == 'CARD' && $operation['paymentInstrumentInfo'] != '') {
                $token = get_post_meta($orderId, '_npg_' . 'contractId', true);

                if ($token != null && $token != "") {
                    $contracts = \Nexi\WC_Gateway_NPG_API::getInstance()->get_customer_one_click_contracts($order->get_customer_id());

                    $foundC = false;

                    if ($contracts['has_contracts'] && count($contracts['contracts']) > 0) {
                        foreach ($contracts['contracts'] as $contract) {
                            if ($contract['contractId'] == $token) {
                                $foundC = true;
                                break;
                            }
                        }
                    }

                    if (!$foundC) {
                        throw new \Exception("Token not saved not npg, orderId: " . $orderId . " - customerId: " . $order->get_customer_id());
                    }

                    WC_NPG_Token::save_token(
                        $operation['paymentCircuit'],
                        $operation['paymentInstrumentInfo'],
                        $operation['additionalData']['cardExpiryDate'],
                        $token,
                        $order->get_customer_id()
                    );
                }
            }
        } catch (\Exception $exc) {
            Log::actionWarning(__FUNCTION__ . ": " . $exc->getMessage());
        }
    }

    public static function cancel($data)
    {
        $params = $data->get_params();

        $order_id = static::check_if_build_and_get_wc_order_id($params["id"]);

        update_post_meta($order_id, '_npg_' . 'payment_error', __('Payment has been cancelled.', 'woocommerce-gateway-nexi-xpay'));

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

            $amount = $_POST['amount'];

            if (!is_numeric($amount)) {
                throw new \Exception(__('Invalid amount.', 'woocommerce-gateway-nexi-xpay'));
            }

            return WC_Gateway_NPG_API::getInstance()->account($order_id, $amount);
        } catch (\Exception $exc) {
            return new \WP_Error("broke", $exc->getMessage());
        }
    }

}
