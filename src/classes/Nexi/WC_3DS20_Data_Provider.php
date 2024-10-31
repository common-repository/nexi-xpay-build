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

class WC_3DS20_Data_Provider
{

    public static function calculate_params($order)
    {
        $params = array();

        try {
            $params['Buyer_email'] = $order->get_billing_email();
            $params['Buyer_account'] = $order->get_billing_email();

            if (strpos($order->get_billing_phone(), "+") !== false) {
                $params['Buyer_homePhone'] = $order->get_billing_phone();
            } else if ($order->get_billing_phone()) {
                $params['Buyer_homePhone'] = "+39" . $order->get_billing_phone();
            }

            $params['Dest_city'] = $order->get_shipping_city();
            $params['Dest_country'] = Iso3166::getAlpha3($order->get_shipping_country());
            $params['Dest_street'] = $order->get_shipping_address_1();
            $params['Dest_street2'] = $order->get_shipping_address_2();
            $params['Dest_cap'] = $order->get_shipping_postcode();
            $params['Dest_state'] = CapToStateCode::getStateCode($order->get_shipping_postcode());
            $params['Bill_city'] = $order->get_billing_city();
            $params['Bill_country'] = Iso3166::getAlpha3($order->get_billing_country());
            $params['Bill_street'] = $order->get_billing_address_1();
            $params['Bill_street2'] = $order->get_billing_address_2();
            $params['Bill_cap'] = $order->get_billing_postcode();
            $params['Bill_state'] = CapToStateCode::getStateCode($order->get_billing_postcode());

            $user_id = $order->get_user_id();

            if ($user_id != 0) {
                $user = new \WC_Customer($user_id);

                if ($user->get_date_created()) {
                    $params['chAccDate'] = $user->get_date_created()->format("Y-m-d");
                }

                $params['chAccAgeIndicator'] = static::get3ds20AccountDateIndicator($user->get_date_created() ? $user->get_date_created() : false);
                $params['nbPurchaseAccount'] = static::get3ds20OrderInLastSixMonth();
                $params['destinationAddressUsageDate'] = static::get3ds20LastUsagedestinationAddress($order->get_id(), $order->get_shipping_city(), $order->get_shipping_country(), $order->get_shipping_address_1(), $order->get_shipping_address_2(), $order->get_shipping_postcode(), $order->get_shipping_state());
                $params['destinationAddressUsageIndicator'] = static::get3ds20FirstUsagedestinationAddress($order->get_id(), $order->get_shipping_city(), $order->get_shipping_country(), $order->get_shipping_address_1(), $order->get_shipping_address_2(), $order->get_shipping_postcode(), $order->get_shipping_state());
                $params['destinationNameIndicator'] = static::get3ds20CheckName($user, $order->get_shipping_first_name(), $order->get_shipping_last_name());
            }
        } catch (Exception $exc) {
            Log::actionWarning($exc->getMessage());
        }

        $fieldsGroups = array(
            array(
                "Buyer_email" => true,
                "Buyer_homePhone" => false,
                "Buyer_workPhone" => false,
                "Buyer_msisdn" => false,
                "Buyer_account" => false
            ),
            array(
                "Dest_city" => true,
                "Dest_country" => true,
                "Dest_street" => true,
                "Dest_street2" => false,
                "Dest_street3" => false,
                "Dest_cap" => true,
                "Dest_state" => true
            ),
            array(
                "Bill_city" => true,
                "Bill_country" => true,
                "Bill_street" => true,
                "Bill_street2" => false,
                "Bill_street3" => false,
                "Bill_cap" => true,
                "Bill_state" => true
            )
        );

        $returnedParams = array();
        foreach ($params as $k => $v) {
            if ($v != "") {
                $returnedParams[$k] = $v;
            }
        }


        foreach ($fieldsGroups as $fieldsGroup) {
            $inThisGroup = false;
            foreach ($returnedParams as $k => $v) {
                $inThisGroup = $inThisGroup || key_exists($k, $fieldsGroup);
            }
            if ($inThisGroup) {
                $presentAllRequired = true;
                foreach ($fieldsGroup as $param => $isRequired) {
                    if ($isRequired) {
                        if (!key_exists($param, $returnedParams)) {
                            $presentAllRequired = false;
                        }
                    }
                }

                if (!$presentAllRequired) {
                    foreach ($fieldsGroup as $param => $isRequired) {
                        unset($returnedParams[$param]);
                    }
                }
            }
        }

        return $returnedParams;
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
        $params = array(
            'Buyer_email' => $wc->customer->get_email(),
            'Buyer_account' => $wc->customer->get_email(),
            'Dest_city' => $wc->customer->get_shipping_city(),
            'Dest_country' => \Nexi\Iso3166::getAlpha3($wc->customer->get_shipping_country()),
            'Dest_street' => $wc->customer->get_shipping_address_1(),
            'Dest_street2' => $wc->customer->get_shipping_address_2(),
            'Dest_cap' => $wc->customer->get_shipping_postcode(),
            'Dest_stateCode' => \Nexi\CapToStateCode::getStateCode($wc->customer->get_shipping_postcode()),
            'Bill_city' => $wc->customer->get_billing_city(),
            'Bill_country' => \Nexi\Iso3166::getAlpha3($wc->customer->get_billing_country()),
            'Bill_street' => $wc->customer->get_billing_address_1(),
            'Bill_street2' => $wc->customer->get_billing_address_2(),
            'Bill_cap' => $wc->customer->get_billing_postcode(),
            'Bill_stateCode' => \Nexi\CapToStateCode::getStateCode($wc->customer->get_billing_postcode()),
        );

        if (strpos($wc->customer->get_billing_phone(), "+") !== false) {
            $params['Buyer_homePhone'] = $wc->customer->get_billing_phone();
        } else if ($wc->customer->get_billing_phone()) {
            $params['Buyer_homePhone'] = "+39" . $wc->customer->get_billing_phone();
        }

        $userParams = array();

        if ($wc->customer->get_date_created()) {
            $userParams['chAccDate'] = $wc->customer->get_date_created()->format("Y-m-d");
        }

        $userParams['chAccAgeIndicator'] = static::get3ds20AccountDateIndicator($wc->customer->get_date_created() ? $wc->customer->get_date_created() : false);

        $userParams['nbPurchaseAccount'] = static::get3ds20OrderInLastSixMonth();
        $userParams['destinationAddressUsageDate'] = static::get3ds20LastUsagedestinationAddress(null, $wc->customer->get_shipping_city(), $wc->customer->get_shipping_country(), $wc->customer->get_shipping_address_1(), $wc->customer->get_shipping_address_2(), $wc->customer->get_shipping_postcode(), $wc->customer->get_shipping_state());
        $userParams['destinationAddressUsageIndicator'] = static::get3ds20FirstUsagedestinationAddress(null, $wc->customer->get_shipping_city(), $wc->customer->get_shipping_country(), $wc->customer->get_shipping_address_1(), $wc->customer->get_shipping_address_2(), $wc->customer->get_shipping_postcode(), $wc->customer->get_shipping_state());
        $userParams['destinationNameIndicator'] = static::get3ds20CheckName($wc->customer, $wc->customer->get_shipping_first_name(), $wc->customer->get_shipping_last_name());

        $params = array_merge($params, $userParams);

        return $params;
    }

}
