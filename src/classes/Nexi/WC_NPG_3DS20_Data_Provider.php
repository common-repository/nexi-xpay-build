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

use Exception;

class WC_NPG_3DS20_Data_Provider
{

    public static function calculate_params($order)
    {
        $params = [];

        try {
            $params['cardHolderName'] = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
            $params['cardHolderEmail'] = $order->get_billing_email();

            $phone = $order->get_billing_phone();

            if (preg_match('/^(\+)([0-9]{10,15})$/', $phone)) {
                $params['homePhone'] = $phone;
            } else {
                $params['mobilePhone'] = $phone;
            }

            $params['billingAddress'] = [
                "name" => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                "street" => $order->get_billing_address_1(),
                "additionalInfo" => $order->get_billing_address_2(),
                "city" => $order->get_billing_city(),
                "postCode" => $order->get_billing_postcode(),
                "province" => CapToStateCode::getStateCode($order->get_billing_postcode()),
                "country" => Iso3166::getAlpha3($order->get_billing_country())
            ];

            $params['shippingAddress'] = [
                "name" => $order->get_shipping_first_name() . ' ' . $order->get_shipping_last_name(),
                "street" => $order->get_shipping_address_1(),
                "additionalInfo" => $order->get_shipping_address_2(),
                "city" => $order->get_shipping_city(),
                "postCode" => $order->get_shipping_postcode(),
                "province" => CapToStateCode::getStateCode($order->get_shipping_postcode()),
                "country" => Iso3166::getAlpha3($order->get_shipping_country())
            ];

            $user_id = $order->get_user_id();

            if ($user_id != 0) {
                $user = new \WC_Customer($user_id);

                $params['cardHolderAcctInfo'] = [
                    "chAccDate" => $user->get_date_created() ? $user->get_date_created()->format("Y-m-d") : null,
                    "chAccAgeIndicator" =>  static::get3ds20AccountDateIndicator($user->get_date_created() ? $user->get_date_created() : false),
                    "nbPurchaseAccount" => static::get3ds20OrderInLastSixMonth(),
                    "destinationAddressUsageDate" => static::get3ds20LastUsagedestinationAddress(
                        $order->get_id(),
                        $order->get_shipping_city(),
                        $order->get_shipping_country(),
                        $order->get_shipping_address_1(),
                        $order->get_shipping_address_2(),
                        $order->get_shipping_postcode(),
                        $order->get_shipping_state()
                    ),
                    "destinationAddressUsageIndicator" => static::get3ds20FirstUsagedestinationAddress(
                        $order->get_id(),
                        $order->get_shipping_city(),
                        $order->get_shipping_country(),
                        $order->get_shipping_address_1(),
                        $order->get_shipping_address_2(),
                        $order->get_shipping_postcode(),
                        $order->get_shipping_state()
                    ),
                    "destinationNameIndicator" => static::get3ds20CheckName($user, $order->get_shipping_first_name(), $order->get_shipping_last_name()),
                ];
            }
        } catch (Exception $exc) {
            Log::actionWarning($exc->getMessage());
        }


        $checkParams = [
            'cardHolderName',
            'cardHolderEmail',
            'mobilePhone',
            'billingAddress',
            'shippingAddress',
            'cardHolderAcctInfo'
        ];

        foreach ($checkParams as $param) {
            if (!array_key_exists($param, $params)) {
                continue;
            }

            if (!is_array($params[$param])) {
                if ($params[$param] == null || trim($params[$param]) == '') {
                    unset($params[$param]);
                }

                continue;
            }

            foreach ($params[$param] as $key => $value) {
                if ($value == null || trim($value) == '') {
                    unset($params[$param][$key]);
                }
            }

            if (count($params[$param]) == 0) {
                unset($params[$param]);
            }
        }

        return $params;
    }

    public static function get3ds20CheckName($user, $first_name, $last_name)
    {
        if ($first_name == $user->get_first_name() && $last_name == $user->get_last_name()) {
            return '01';
        }
        return '02';
    }

    private static function get3ds20FirstUsagedestinationAddress($order_id, $city, $country, $street_1, $street_2, $postcode, $state)
    {
        $customer_orders = wc_get_orders(array(
            'numberposts' => 1,
            'orderby' => 'date',
            'order' => 'ASC',
            'exclude' => array($order_id),
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => 'customer_user',
                    'value' => get_current_user_id(),
                    'compare' => '=',
                ),
                array(
                    'key' => 'shipping_country',
                    'value' => $country,
                    'compare' => '=',
                ),
                array(
                    'key' => 'shipping_city',
                    'value' => $city,
                    'compare' => '=',
                ),
                array(
                    'key' => 'shipping_address_1',
                    'value' => $street_1,
                    'compare' => '=',
                ),
                array(
                    'key' => 'shipping_address_2',
                    'value' => $street_2,
                    'compare' => '=',
                ),
                array(
                    'key' => 'shipping_postcode',
                    'value' => $postcode,
                    'compare' => '=',
                ),
                array(
                    'key' => 'shipping_state',
                    'value' => $state,
                    'compare' => '=',
                ),
            ),
        ));

        if (count($customer_orders) == 0) {
            //Account Created in this transaction
            return "01";
        }

        $date = $customer_orders[0]->get_date_created()->date("Y-m-d");

        if ($date >= (new \DateTime('now - 30 day'))->format("Y-m-d")) {
            // Account created in last 30 days
            return '02';
        } else if ($date >= (new \DateTime('now - 60 day'))->format("Y-m-d")) {
            // Account created from 30 to 60 days ago
            return '03';
        } else {
            // Account created more then 60 days ago
            return '04';
        }
        return "";
    }

    private static function get3ds20LastUsagedestinationAddress($order_id, $city, $country, $street_1, $street_2, $postcode, $state)
    {
        $customer_orders = wc_get_orders(array(
            'numberposts' => 1,
            'orderby' => 'date',
            'order' => 'DESC',
            'exclude' => array($order_id),
            'meta_query' => array(
                'relation' => 'AND',
                array(
                    'key' => 'customer_user',
                    'value' => get_current_user_id(),
                    'compare' => '=',
                ),
                array(
                    'key' => 'shipping_country',
                    'value' => $country,
                    'compare' => '=',
                ),
                array(
                    'key' => 'shipping_city',
                    'value' => $city,
                    'compare' => '=',
                ),
                array(
                    'key' => 'shipping_address_1',
                    'value' => $street_1,
                    'compare' => '=',
                ),
                array(
                    'key' => 'shipping_address_2',
                    'value' => $street_2,
                    'compare' => '=',
                ),
                array(
                    'key' => 'shipping_postcode',
                    'value' => $postcode,
                    'compare' => '=',
                ),
                array(
                    'key' => 'shipping_state',
                    'value' => $state,
                    'compare' => '=',
                ),
            ),
        ));

        if (count($customer_orders) == 0) {
            //Account Created in this transaction
            return null;
        }

        return $customer_orders[0]->get_date_created()->date("Y-m-d");
    }

    public static function get3ds20OrderInLastSixMonth()
    {
        $args = array(
            'numberposts' => -1,
            'meta_key' => '_customer_user',
            'orderby' => 'date',
            'order' => 'DESC',
            'meta_value' => get_current_user_id(),
            'post_type' => wc_get_order_types(),
            'post_status' => array_keys(wc_get_order_statuses()),
            'date_query' => array(
                'after' => date('Y-m-d', strtotime('- 6 month'))
            )
        );
        $orders = get_posts($args);
        return count($orders);
    }

    private static function get3ds20AccountDateIndicator($date)
    {
        $today = date("Y-m-d");

        if ($date == false) {
            // Account not registred
            return '01';
        }

        if ($date->format("Y-m-d") == $today) {
            // Account Created in this transaction
            return '02';
        }

        $newDate = new \DateTime($today . ' - 30 day');

        if ($date->format("Y-m-d") >= $newDate->format("Y-m-d")) {
            // Account created in last 30 days
            return '03';
        }

        $newDate = new \DateTime($today . ' - 60 day');

        if ($date->format("Y-m-d") >= $newDate->format("Y-m-d")) {
            // Account created from 30 to 60 days ago
            return '04';
        }

        if ($date->format("Y-m-d") < $newDate->format("Y-m-d")) {
            // Account created more then 60 days ago
            return '05';
        }
    }

    public static function getParamsFromWC($wc)
    {
        $params = [];

        static::setFieldifNotEmpty($params, 'cardHolderName', $wc->customer->get_first_name(), $wc->customer->get_last_name());
        static::setFieldifNotEmpty($params, 'cardHolderEmail', $wc->customer->get_email());

        static::setFieldifNotEmpty($params["billingAddress"], 'name', $wc->customer->get_billing_first_name(), $wc->customer->get_billing_last_name());
        static::setFieldifNotEmpty($params["billingAddress"], 'street', $wc->customer->get_billing_address_1());
        static::setFieldifNotEmpty($params["billingAddress"], 'additionalInfo', $wc->customer->get_billing_address_2());
        static::setFieldifNotEmpty($params["billingAddress"], 'city', $wc->customer->get_billing_city());
        static::setFieldifNotEmpty($params["billingAddress"], 'postCode', $wc->customer->get_billing_postcode());
        if (!empty($wc->customer->get_billing_postcode())) {
            static::setFieldifNotEmpty($params["billingAddress"], 'province', \Nexi\CapToStateCode::getStateCode($wc->customer->get_billing_postcode()));
        }
        if (!empty($wc->customer->get_billing_country())) {
            static::setFieldifNotEmpty($params["billingAddress"], 'country', \Nexi\Iso3166::getAlpha3($wc->customer->get_billing_country()));
        }

        static::setFieldifNotEmpty($params["shippingAddress"], 'name', $wc->customer->get_shipping_first_name(), $wc->customer->get_shipping_last_name());
        static::setFieldifNotEmpty($params["shippingAddress"], 'street', $wc->customer->get_shipping_address_1());
        static::setFieldifNotEmpty($params["shippingAddress"], 'additionalInfo', $wc->customer->get_shipping_address_2());
        static::setFieldifNotEmpty($params["shippingAddress"], 'city', $wc->customer->get_shipping_city());
        static::setFieldifNotEmpty($params["shippingAddress"], 'postCode', $wc->customer->get_shipping_postcode());
        if (!empty($wc->customer->get_shipping_postcode())) {
            static::setFieldifNotEmpty($params["shippingAddress"], 'province', \Nexi\CapToStateCode::getStateCode($wc->customer->get_shipping_postcode()));
        }
        if (!empty($wc->customer->get_shipping_country())) {
            static::setFieldifNotEmpty($params["shippingAddress"], 'country', \Nexi\Iso3166::getAlpha3($wc->customer->get_shipping_country()));
        }

        if (!empty($wc->customer->get_date_created())) {
            $params["cardHolderAcctInfo"]["chAccDate"] =  $wc->customer->get_date_created()->format("Y-m-d");

            $params["cardHolderAcctInfo"]["chAccAgeIndicator"] =  static::get3ds20AccountDateIndicator($wc->customer->get_date_created());
        }

        $params["cardHolderAcctInfo"]["nbPurchaseAccount"] =  static::get3ds20OrderInLastSixMonth();

        if (
            !empty($wc->customer->get_shipping_city()) &&
            !empty($wc->customer->get_shipping_country()) &&
            !empty($wc->customer->get_shipping_address_1()) && 
            !empty($wc->customer->get_shipping_address_2()) && 
            !empty($wc->customer->get_shipping_postcode()) && 
            !empty($wc->customer->get_shipping_state())
        ) {
            $params["cardHolderAcctInfo"]["destinationAddressUsageDate"] =  static::get3ds20LastUsagedestinationAddress(
                null,
                $wc->customer->get_shipping_city(),
                $wc->customer->get_shipping_country(),
                $wc->customer->get_shipping_address_1(),
                $wc->customer->get_shipping_address_2(),
                $wc->customer->get_shipping_postcode(),
                $wc->customer->get_shipping_state()
            );

            $params["cardHolderAcctInfo"]["destinationAddressUsageIndicator"] =  static::get3ds20FirstUsagedestinationAddress(
                null,
                $wc->customer->get_shipping_city(),
                $wc->customer->get_shipping_country(),
                $wc->customer->get_shipping_address_1(),
                $wc->customer->get_shipping_address_2(),
                $wc->customer->get_shipping_postcode(),
                $wc->customer->get_shipping_state()
            );
        }

        if (!empty($wc->customer->get_shipping_first_name()) && !empty($wc->customer->get_shipping_last_name())) {
            $params["cardHolderAcctInfo"]['destinationNameIndicator'] = static::get3ds20CheckName(
                $wc->customer,
                $wc->customer->get_shipping_first_name(),
                $wc->customer->get_shipping_last_name()
            );
        }

        return $params;
    }

    private static function setFieldifNotEmpty(&$params, $field, ...$values)
    {
        $finalValues = [];

        foreach ($values as $value) {
            if (!empty($value)) {
                $finalValues[] = $value;
            }
        }

        if (!empty($finalValues)) {
            $params[$field] = join(" ", $finalValues);
        }
    }
}
