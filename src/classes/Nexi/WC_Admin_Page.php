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

class WC_Admin_Page
{

    public static function init()
    {
        $instance = new static();

        add_action('add_meta_boxes', array($instance, 'add_meta_box_details_payment_nexixpay'), 10, 2);

        wp_enqueue_script('xpay-admin-checkout', plugins_url('assets/js/xpay-admin.js', WC_ECOMMERCE_GATEWAY_NEXI_MAIN_FILE), array('jquery'), WC_GATEWAY_XPAY_VERSION);

        wp_localize_script('xpay-admin-checkout', 'wpApiSettings', [
            'nonce' => wp_create_nonce('wp_rest'),
        ]);

        wp_enqueue_style('xpay-admin-checkout', plugins_url('assets/css/xpay-admin.css', WC_ECOMMERCE_GATEWAY_NEXI_MAIN_FILE), [], WC_GATEWAY_XPAY_VERSION);

        self::migrate_data();
    }

    public static function migrate_data()
    {
        $CURRENT_VERSION = "2";

        $nexi_xpay_data_version = get_option("nexi_xpay_data_version");

        switch ($nexi_xpay_data_version) {
            case "":
                self::migrate_to_v1();
                return true;
                break;

            case "1":
                self::migrate_to_v2();
                return true;
                break;

            default:
                if ($nexi_xpay_data_version == $CURRENT_VERSION || intval($nexi_xpay_data_version) > intval($CURRENT_VERSION)) {
                    return true;
                }

                return false;
                break;
        }
    }

    private static function migrate_to_v1()
    {
        Log::actionInfo("updating settings to v1");

        $currentConfig = WC_Nexi_Helper::get_nexi_settings();

        $currentConfig["nexi_xpay_alias"] = $currentConfig["cartasi_alias"];
        $currentConfig["nexi_xpay_mac"] = $currentConfig["cartasi_mac"];

        $currentConfig["nexi_xpay_test_mode"] = $currentConfig["cartasi_modalita_test"];

        $currentConfig["nexi_xpay_accounting"] = $currentConfig["contabilizzazione"];

        $currentConfig["nexi_xpay_oneclick_enabled"] = $currentConfig["abilita_modulo_oneclick"];

        $currentConfig["nexi_xpay_recurring_enabled"] = $currentConfig["abilita_modulo_ricorrenze"];
        $currentConfig["nexi_xpay_recurring_alias"] = $currentConfig["cartasi_alias_rico"];
        $currentConfig["nexi_xpay_recurring_mac"] = $currentConfig["cartasi_mac_rico"];
        $currentConfig["nexi_xpay_group"] = $currentConfig["gruppo_rico"];

        $currentConfig["nexi_xpay_3ds20_enabled"] = $currentConfig['enabled3ds'];

        update_option(WC_SETTINGS_KEY, $currentConfig);
        Log::actionInfo("updated settings to v1");

        update_option('nexi_xpay_data_version', "1");
    }

    private static function migrate_to_v2()
    {
        Log::actionInfo("updating settings to v2");

        $currentConfig = WC_Nexi_Helper::get_nexi_settings();

        if (array_key_exists('nexi_gateway', $currentConfig) && $currentConfig['nexi_gateway'] == GATEWAY_NPG) {
            WC_Gateway_NPG_API::enable_apms();
        }

        Log::actionInfo("updated settings to v2");

        update_option('nexi_xpay_data_version', "2");
    }

    public function add_meta_box_details_payment_nexixpay($post_type, $post)
    {
        $order_id = $post->ID;

        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        $npgOrderId = get_post_meta($order_id, "_npg_" . "orderId", true);

        if ($npgOrderId != "") {
            if ($order->get_payment_method() === 'xpay' || substr($order->get_payment_method(), 0, 9) == 'xpay_npg_' || substr($order->get_payment_method(), 0, 10) == 'xpay_build') {
                foreach (array('woocommerce_page_wc-orders', 'shop_order') as $type) {
                    add_meta_box('xpay-subscription-box', __('Nexi payment details', 'woocommerce-gateway-nexi-xpay'), array($this, 'details_payment_npg'), $type, 'normal', 'high');
                }
            }
        } else {
            $transactionCodTrans = get_post_meta($order_id, '_xpay_' . 'codTrans', true);
            if ($transactionCodTrans == "") {
                return;
            }

            if ($order->get_payment_method() === 'xpay' || substr($order->get_payment_method(), 0, 5) == 'xpay_') {
                foreach (array('woocommerce_page_wc-orders', 'shop_order') as $type) {
                    add_meta_box('xpay-subscription-box', __('XPay payment details', 'woocommerce-gateway-nexi-xpay'), array($this, 'details_payment_xpay'), $type, 'normal', 'high');
                }
            }
        }
    }

    private function get_currency_sign($currency)
    {
        $currencySign = array(
            'EUR' => '&euro;',
            'CZK' => '&#75;&#269;',
            'PLN' => '&#122;&#322;',
            'NZD' => '&dollar;',
            'AUD' => '&dollar;'
        );

        return array_key_exists($currency, $currencySign) ? $currencySign[$currency] : null;
    }

    private function get_currency_label($currency)
    {
        $currencySign = array(
            'EUR' => __('Euros', 'woocommerce-gateway-nexi-xpay'),
            'CZK' => __('Czech Kurun', 'woocommerce-gateway-nexi-xpay'),
            'PLN' => __('Zloty', 'woocommerce-gateway-nexi-xpay'),
            'NZD' => __('Dollars', 'woocommerce-gateway-nexi-xpay'),
            'AUD' => __('Dollars', 'woocommerce-gateway-nexi-xpay')
        );

        return array_key_exists($currency, $currencySign) ? $currencySign[$currency] : null;
    }

    public function details_payment_xpay($post)
    {
        $order_id = $post->ID;

        $transactionCodTrans = WC_Nexi_Helper::get_xpay_post_meta($order_id, 'codTrans');

        if ($transactionCodTrans == "") {
            echo __("Missing codTrans", 'woocommerce-gateway-nexi-xpay');
            return;
        }
        $transactionNumContratto = WC_Nexi_Helper::get_xpay_post_meta($order_id, 'num_contratto');
        $paymentCardBrandExpirationRaw = WC_Nexi_Helper::get_xpay_post_meta($order_id, 'scadenza_pan');

        $order = wc_get_order($order_id);

        $paymentCardBrandExpiration = "";
        if ($paymentCardBrandExpirationRaw != "") {
            $parsed = \DateTime::createFromFormat("Ym", $paymentCardBrandExpirationRaw);
            if ($parsed != null) {
                $paymentCardBrandExpiration = $parsed->format("m/Y");
            }
        }

        $canAccount = false;
        $currencySign = "";
        $currencyLabel = "";
        $operazioni = array();
        $transactionValue = "";
        $paymentCardBrandPan = "";
        $paymentCardBrandNazionalita = "";
        $paymentCardBrand = "";
        $custumerEmail = "";
        $transactionStatus = "";
        $transactionDate = "";
        $name = "";
        $cognome = "";

        try {
            $xpayOrderStatus = WC_Gateway_XPay_API::getInstance()->order_detail($transactionCodTrans);

            if (isset($xpayOrderStatus["stato"])) {
                $transactionStatus = $xpayOrderStatus["stato"];

                if ($xpayOrderStatus['stato'] == "Contabilizzato Parz.") {
                    $canAccount = true;
                } else if ($xpayOrderStatus['stato'] == "Autorizzato") {
                    $canAccount = true;
                }
            }

            if (isset($xpayOrderStatus["divisa"])) {
                $currencySign = $this->get_currency_sign($xpayOrderStatus['divisa']);
                $currencyLabel = $this->get_currency_label($xpayOrderStatus['divisa']);
            }

            $transactionValue = WC_Nexi_Helper::div_bcdiv($xpayOrderStatus['importo'], 100);
            $paymentCardBrandPan = $xpayOrderStatus['pan'];
            $paymentCardBrandNazionalita = $xpayOrderStatus["nazione"];
            $paymentCardBrand = $xpayOrderStatus["brand"];
            $custumerEmail = $xpayOrderStatus["dettaglio"][0]["mail"];

            $name = $xpayOrderStatus['dettaglio'][0]["nome"];
            $cognome = $xpayOrderStatus['dettaglio'][0]["cognome"];

            $parsed = \DateTime::createFromFormat("Y-m-d H:i:s", $xpayOrderStatus["dataTransazione"]);
            if ($parsed != null) {
                $transactionDate = $parsed->format("d/m/Y H:i");
            }

            $operazioni = $xpayOrderStatus['dettaglio'][0]['operazioni'];
        } catch (\Exception $exc) {
            Log::actionWarning($exc->getMessage());
        }

        $custumerDisplayName = trim($name . " " . $cognome);

        $canAccount = current_user_can('manage_woocommerce') && $canAccount;
        $accountUrl = get_rest_url(null, "woocommerce-gateway-nexi-xpay/process_account/xpay/" . $order->get_id());

        include_once WC_Nexi_Helper::get_nexi_template_path('xpay_payment_detail.php');
    }

    private function get_npg_currency_sign($currency)
    {
        return get_woocommerce_currency_symbol($currency);
    }

    private function get_npg_currency_label($currency)
    {
        return get_woocommerce_currency_symbol($currency);
    }

    public function details_payment_npg($post)
    {
        $order_id = $post->ID;

        try {
            $order = wc_get_order($order_id);

            $currency = $order->get_currency();

            $npgApi = WC_Gateway_NPG_API::getInstance();

            $orderInfo = $npgApi->get_order_info($order_id);

            $canAccount = false;
            $currencySign = "";
            $currencyLabel = "";

            $showOperations = is_array($orderInfo['operations']) && count($orderInfo['operations']) > 0;

            if ($showOperations) {
                $canAccount = (int) $orderInfo['orderStatus']['authorizedAmount'] !== (int) $orderInfo['orderStatus']['capturedAmount'] &&
                    $npgApi->get_account_operation_id($orderInfo['operations']) !== null;
            }

            if (isset($orderInfo['orderStatus']) && isset($orderInfo['orderStatus']['order']['currency'])) {
                $currencySign = $this->get_npg_currency_sign($orderInfo['orderStatus']['order']['currency']);
                $currencyLabel = $this->get_npg_currency_label($orderInfo['orderStatus']['order']['currency']);
            }

            $accountUrl = get_rest_url(null, "woocommerce-gateway-nexi-xpay/process_account/npg/" . $order->get_id());

            $installmentsNumber = get_post_meta($order_id, "_npg_" . "installmentsNumber", true);
        } catch (\Exception $exc) {
            Log::actionWarning(__FUNCTION__ . ': ' . $exc->getMessage());

            $orderError = $exc->getMessage();
        }

        include_once WC_Nexi_Helper::get_nexi_template_path('npg_payment_detail.php');
    }

}
