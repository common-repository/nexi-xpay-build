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

class WC_Pagodil_Data_Provider
{

    public static function calculate_params($order)
    {
        $params = array();

        try {
            $params['nome'] = $order->get_billing_first_name();
            $params['cognome'] = $order->get_billing_last_name();

            $params['itemsAmount'] = WC_Nexi_Helper::mul_bcmul($order->get_total(), 100, 0);

            $allItems = $order->get_items();

            $itemsNumber = 0;

            foreach ($allItems as $item) {
                $itemsNumber++;

                $params['Item_code_' . $itemsNumber] = $item->get_product_id();
                $params['Item_quantity_' . $itemsNumber] = $item->get_quantity();
                $params['Item_amount_' . $itemsNumber] = WC_Nexi_Helper::mul_bcmul($item->get_total(), 100, 0);
                $params['Item_description_' . $itemsNumber] = $item->get_name();

                $categoryIds = wc_get_product_term_ids($item->get_product_id(), 'product_cat');
                $params['Item_category_' . $itemsNumber] = implode(", ", self::getCategories($categoryIds));
            }

            $params['itemsNumber'] = $itemsNumber;

            $params['shipIndicator'] = self::getShipIndicator($order);

            $params['numberOfInstalment'] = get_post_meta($order->get_id(), "installments", true);

            $xpaySettings = \Nexi\WC_Pagodil_Widget::getXPaySettings();

            if ($xpaySettings['pd_product_code'] !== null && $xpaySettings['pd_product_code'] !== "") {
                $params['pagodilOfferID'] = $xpaySettings['pd_product_code'];
            }

            $phone = $order->get_billing_phone();

            if (isset($phone)) {
                $phone = trim($phone);

                if (strpos($phone, "+") === false) {
                    $phone = '+39' . $phone;
                }

                if ($phone[3] == '3') {
                    $params['Buyer_msisdn'] = $phone;
                }
            }

            if ($order->has_shipping_address()) {
                $params['Dest_city'] = $order->get_shipping_city();
                $params['Dest_country'] = Iso3166::getAlpha3($order->get_shipping_country());
                $params['Dest_street'] = $order->get_shipping_address_1();
                $params['Dest_street2'] = $order->get_shipping_address_2();
                $params['Dest_cap'] = $order->get_shipping_postcode();
                $params['Dest_state'] = CapToStateCode::getStateCode($order->get_shipping_postcode());
            }

            $params['Bill_city'] = $order->get_billing_city();
            $params['Bill_country'] = Iso3166::getAlpha3($order->get_billing_country());
            $params['Bill_street'] = $order->get_billing_address_1();
            $params['Bill_street2'] = $order->get_billing_address_2();
            $params['Bill_cap'] = $order->get_billing_postcode();
            $params['Bill_state'] = CapToStateCode::getStateCode($order->get_billing_postcode());

            if ($xpaySettings['pd_field_name_cf']) {
                $fiscalCode = get_post_meta($order->get_id(), $xpaySettings['pd_field_name_cf'], true);

                if ($fiscalCode != "") {
                    $params['OPTION_CF'] = $fiscalCode;
                }
            }

            $user_id = $order->get_user_id();

            if ($user_id != 0) {
                $user = new \WC_Customer($user_id);

                if ($user->get_date_created()) {
                    $params['chAccDate'] = $user->get_date_created()->format("Y-m-d");
                }

                $params['nbPurchaseAccount'] = WC_3DS20_Data_Provider::get3ds20OrderInLastSixMonth();
                $params['destinationNameIndicator'] = WC_3DS20_Data_Provider::get3ds20CheckName($user, $order->get_shipping_first_name(), $order->get_shipping_last_name());
            }
        } catch (Exception $exc) {
            Log::actionWarning($exc->getMessage());
        }

        return $params;
    }

    private static function getCategories($categoryIds)
    {
        $categories = array();

        foreach ($categoryIds as $id) {
            $term = get_term_by('id', $id, 'product_cat');

            $categories[] = $term->name;
        }

        return $categories;
    }

    private static function getShipIndicator($order)
    {
        if (!$order->has_shipping_address()) {
            return "05";
        }

        if ($order->get_shipping_city() == $order->get_billing_city() && Iso3166::getAlpha3($order->get_shipping_country()) == Iso3166::getAlpha3($order->get_billing_country()) && $order->get_shipping_address_1() == $order->get_billing_address_1() && $order->get_shipping_postcode() == $order->get_billing_postcode()) {

            if ($order->get_shipping_address_2() !== null) {
                if ($order->get_shipping_address_2() == $order->get_billing_address_2()) {
                    return "01";
                } else {
                    return "03";
                }
            }

            return "01";
        } else {
            return "03";
        }
    }
}
