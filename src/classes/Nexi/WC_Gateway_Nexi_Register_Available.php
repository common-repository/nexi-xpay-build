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

class WC_Gateway_Nexi_Register_Available
{

    private static $xpayAllowedMethodsByCurrency = array(
        'EUR' => array(
            'PAYPAL',
            'SOFORT',
            'AMAZONPAY',
            'GOOGLEPAY',
            'APPLEPAY',
            'ALIPAY',
            'WECHATPAY',
            'GIROPAY',
            'IDEAL',
            'BCMC',
            'EPS',
            'P24',
            'BANCOMATPAY',
            'SCT',
            // 'MASTERPASS',
            'SKRILL',
            'SKRILL1TAP',
            'MULTIBANCO',
            'MY_BANK',
            'PAGODIL',
            'KLARNA',
            'PAGOLIGHT',
            'PAYPAL_BNPL',
        ),
        'CZK' => array(
            'PAYU'
        ),
        'PLN' => array(
            'PAYU',
            'BLIK',
        ),
        'NZD' => array(
            'POLI',
        ),
        'AUD' => array(
            'POLI',
        ),
        'GBP' => array(
            'KLARNA',
        ),
        'DKK' => array(
            'KLARNA',
        ),
    );
    private static $xpayMinAmounts = array(
        'SOFORT' => 10,
        'GIROPAY' => 10,
        'IDEAL' => 10,
        'EPS' => 100,
        'PAYU' => 300,
        'BLIK' => 100,
        'POLI' => 100,
        'KLARNA' => 3500,
        'PAGOLIGHT' => 6000,
        'PAYPAL_BNPL' => 3000,
    );
    private static $xpayMaxAmounts = array(
        'KLARNA' => 150000,
        'PAGOLIGHT' => 500000,
        'PAYPAL_BNPL' => 200000,
    );

    public static function register($paymentGateways)
    {
        $nexiGatewaysHelper = new static();
        return array_merge($paymentGateways, $nexiGatewaysHelper->get_all_nexi_gateways());
    }

    private $paymentGateways;
    private $currency;

    private function get_all_nexi_gateways()
    {
        if (\Nexi\WC_Admin_Page::migrate_data()) {
            return $this->paymentGateways;
        }
        return array();
    }

    private function __construct()
    {
        $this->evaluate_all();
    }

    private function evaluate_all()
    {
        global $pagenow;

        $this->paymentGateways = array();

        if (is_admin() && $pagenow == 'admin.php' && $_GET['page'] == 'wc-settings' && $_GET['tab'] == 'checkout') {
            $this->paymentGateways[] = new \Nexi\WC_Gateway_Admin();
        } else {
            $currentConfig = WC_Nexi_Helper::get_nexi_settings();

            switch (WC_GATEWAY_NEXI_PLUGIN_VARIANT) {
                case 'xpay':
                    if (WC_Nexi_Helper::nexi_array_key_exists_and_equals($currentConfig, 'nexi_gateway', GATEWAY_NPG)) {
                        $mainGateway = new \Nexi\WC_Gateway_NPG_Cards();
                    } else {
                        $mainGateway = new \Nexi\WC_Gateway_XPay_Cards();
                    }
                    break;

                case 'xpay_build':
                    if (WC_Nexi_Helper::nexi_array_key_exists_and_equals($currentConfig, 'nexi_gateway', GATEWAY_NPG)) {
                        $mainGateway = new \Nexi\WC_Gateway_NPG_Cards_Build();
                    } else {
                        $mainGateway = new \Nexi\WC_Gateway_XPay_Cards_Build();
                    }
                    break;

                default:
                    Log::actionWarning(__('Invalid plugin variant value', 'woocommerce-gateway-nexi-xpay'));
                    throw new \Exception(__('Invalid plugin variant value', 'woocommerce-gateway-nexi-xpay'));
            }

            $this->currency = get_woocommerce_currency();

            if (WC_Nexi_Helper::nexi_array_key_exists_and_equals($currentConfig, 'enabled', 'yes')) {
                if (WC_Nexi_Helper::nexi_array_key_exists_and_equals($currentConfig, 'nexi_gateway', GATEWAY_NPG)) {
                    if (is_admin() || static::is_currency_valid_for_apm($this->currency, 'CARDS')) {
                        $this->paymentGateways[] = $mainGateway;
                    }

                    $jsonAvailableMethodsNpg = \WC_Admin_Settings::get_option('xpay_npg_available_methods');

                    $availableMethodsNpg = json_decode($jsonAvailableMethodsNpg, true);

                    if (!is_array($availableMethodsNpg)) {
                        $availableMethodsNpg = [];
                    }

                    foreach ($availableMethodsNpg as $am) {
                        $this->evaluate_one_apm_npg((array) $am);
                    }
                } else {
                    if (is_admin() || $this->currency == 'EUR') {
                        $this->paymentGateways[] = $mainGateway;
                    }

                    $jsonAvailableMethodsXpay = \WC_Admin_Settings::get_option('xpay_available_methods');

                    $availableMethodsXpay = json_decode($jsonAvailableMethodsXpay, true);

                    if (!is_array($availableMethodsXpay)) {
                        $availableMethodsXpay = [];
                    }

                    foreach ($availableMethodsXpay as $am) {
                        $this->evaluate_one_apm_xpay($am);
                    }
                }
            }
        }
    }

    private function evaluate_one_apm_xpay($am)
    {
        // The method is an APM with selectedcard support
        if ($am['type'] != 'APM' || $am['selectedcard'] == '') {
            return;
        }

        // If not an admin execute checks
        if (!is_admin()) {
            // The method supports the currency or is in the admin page
            if (!WC_Nexi_Helper::nexi_array_key_exists_and_in_array(self::$xpayAllowedMethodsByCurrency, $this->currency, $am['selectedcard'])) {
                return;
            }

            // Test for minimum amount. Each APM can have a minimum amount for payment processing
            if (WC_Nexi_Helper::nexi_array_key_exists(self::$xpayMinAmounts, $am['selectedcard']) && isset(WC()->cart)) {
                $currentCartAmount = WC_Nexi_Helper::mul_bcmul(WC()->cart->total, 100, 0);

                if ($currentCartAmount < self::$xpayMinAmounts[$am['selectedcard']]) {
                    return;
                }
            }

            // Test for maximum amount. Each APM can have a maximum amount for payment processing
            if (WC_Nexi_Helper::nexi_array_key_exists(self::$xpayMaxAmounts, $am['selectedcard']) && isset(WC()->cart)) {
                $currentCartAmount = WC_Nexi_Helper::mul_bcmul(WC()->cart->total, 100, 0);

                if ($currentCartAmount > self::$xpayMaxAmounts[$am['selectedcard']]) {
                    return;
                }
            }

            // Test for PagoDIL configuration. Cart must be payable in installable to pay with PagoDIL
            if ($am['selectedcard'] == 'PAGODIL' && isset(WC()->cart)) {
                $xpaySettings = \Nexi\WC_Pagodil_Widget::getXPaySettings();

                $pagodilConfig = \Nexi\WC_Pagodil_Widget::getPagodilConfig();

                if (!\Nexi\WC_Pagodil_Widget::isQuoteInstallable($xpaySettings, $pagodilConfig, WC()->cart)) {
                    return;
                }
            }
        }

        // If all tests are ok then add the APM to the array of gateways
        $this->paymentGateways[] = new \Nexi\WC_Gateway_XPay_APM($am['code'], $am['description'], $am['selectedcard'], $am['image']);
    }

    private function evaluate_one_apm_npg($am)
    {
        if ($am['paymentMethodType'] != 'APM') {
            return;
        }

        if (!in_array($am['circuit'], static::get_npg_allowed_apm())) {
            return;
        }

        if (!static::is_currency_valid_for_apm($this->currency, $am['circuit'])) {
            return;
        }

        $apmInfo = static::get_npg_apm_info($am['circuit']);

        // Test for minimum amount. Each APM can have a minimum amount for payment processing
        if (isset($apmInfo['min_amount'])) {
            if (isset(WC()->cart)) {
                $currentCartAmount = \Nexi\WC_Gateway_NPG_Currency::calculate_amount_to_min_unit(WC()->cart->total, $this->currency);

                if ($currentCartAmount < $apmInfo['min_amount']) {
                    return;
                }
            }
        }

        // Test for maximum amount. Each APM can have a maximum amount for payment processing
        if (isset($apmInfo['max_amount'])) {
            if (isset(WC()->cart)) {
                $currentCartAmount = \Nexi\WC_Gateway_NPG_Currency::calculate_amount_to_min_unit(WC()->cart->total, $this->currency);

                if ($currentCartAmount > $apmInfo['max_amount']) {
                    return;
                }
            }
        }

        $this->paymentGateways[] = new \Nexi\WC_Gateway_NPG_APM(
            $am['circuit'],
            $apmInfo['title'],
            $apmInfo['description'],
            $am['circuit'],
            $am['imageLink']
        );
    }

    private static function get_all_npg_available_apm_info()
    {
        return [
            'PAGOINCONTO' => [
                'title' => 'PagoinConto',
                'description' => __('Simply pay by bank transfer directly from your home banking with PagoinConto', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => null,
                'max_amount' => null,
            ],
            'GOOGLEPAY' => [
                'title' => 'Google Pay',
                'description' => __('Easily pay with your Google Pay wallet', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => null,
                'max_amount' => null,
            ],
            'APPLEPAY' => [
                'title' => 'Apple Pay',
                'description' => __('Easily pay with your Apple Pay wallet', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => null,
                'max_amount' => null,
            ],
            'BANCOMATPAY' => [
                'title' => 'Bancomat Pay',
                'description' => __('Pay via BANCOMAT Pay just by entering your phone number', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => null,
                'max_amount' => null,
            ],
            'MYBANK' => [
                'title' => 'MyBank',
                'description' => __('Pay securely by bank transfer with MyBank', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => null,
                'max_amount' => null,
            ],
            'ALIPAY' => [
                'title' => 'Alipay',
                'description' => __('Pay quickly and easily with your AliPay wallet', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => null,
                'max_amount' => null,
            ],
            'WECHATPAY' => [
                'title' => 'WeChat Pay',
                'description' => __('Pay quickly and easily with your WeChat Pay wallet', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => null,
                'max_amount' => null,
            ],
            'GIROPAY' => [
                'title' => 'Giropay',
                'description' => __('Pay directly from your bank account with Giropay', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => 10,
                'max_amount' => null,
            ],
            'IDEAL' => [
                'title' => 'iDEAL',
                'description' => __('Pay directly from your bank account with iDEAL', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => 10,
                'max_amount' => null,
            ],
            'BANCONTACT' => [
                'title' => 'Bancontact',
                'description' => __('Pay easily with Bancontact', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => null,
                'max_amount' => null,
            ],
            'EPS' => [
                'title' => 'EPS',
                'description' => __('Real time payment directly from your bank account with EPS', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => 100,
                'max_amount' => null,
            ],
            'PRZELEWY24' => [
                'title' => 'Przelewy24',
                'description' => __('Secure payment directly from your bank account with Przelewy24', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => null,
                'max_amount' => null,
            ],
            'SKRILL' => [
                'title' => 'Skrill',
                'description' => __('Pay quickly and easily with your Skrill wallet', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => null,
                'max_amount' => null,
            ],
            'SKRILL1TAP' => [
                'title' => 'Skrill 1tap',
                'description' => __('Pay in one tap with your Skrill wallet', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => null,
                'max_amount' => null,
            ],
            'PAYU' => [
                'title' => 'PayU',
                'description' => __('Secure payment directly from your bank account with PayU', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => 300,
                'max_amount' => null,
            ],
            'BLIK' => [
                'title' => 'Blik',
                'description' => __('Secure payment directly from your home banking with Blik', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => 100,
                'max_amount' => null,
            ],
            'MULTIBANCO' => [
                'title' => 'Multibanco',
                'description' => __('Secure payment directly from your home banking with Multibanco', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => null,
                'max_amount' => null,
            ],
            'SATISPAY' => [
                'title' => 'Satispay',
                'description' => __('Pay easily with your Satispay account', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => null,
                'max_amount' => null,
            ],
            'AMAZONPAY' => [
                'title' => 'Amazon Pay',
                'description' => __('Pay easily with your Amazon account', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => null,
                'max_amount' => null,
            ],
            'PAYPAL' => [
                'title' => 'PayPal',
                'description' => __('Pay securely with your PayPal account', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => null,
                'max_amount' => null,
            ],
            'ONEY' => [
                'title' => 'Oney',
                'description' => __('Pay in 3 or 4 installments by credit, debit or Postepay card with Oney', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => null,
                'max_amount' => null,
            ],
            'KLARNA' => [
                'title' => 'Klarna',
                'description' => __('Pay in 3 installments with Klarna interest-free', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => 3500,
                'max_amount' => 150000,
            ],
            'PAGODIL' => [
                'title' => 'PagoDil',
                'description' => __('Buy now and pay a little by little with PagoDIL', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => null,
                'max_amount' => null,
            ],
            'PAGOLIGHT' => [
                'title' => 'PagoLight',
                'description' => __('Pay in installments with PagoLight', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => 6000,
                'max_amount' => 300000,
            ],
            'PAYPAL_BNPL' => [
                'title' => 'PayPal BNPL',
                'description' => __('Pay in 3 installments with PayPal', 'woocommerce-gateway-nexi-xpay'),
                'min_amount' => 3000,
                'max_amount' => 200000,
            ],
        ];
    }

    private static function get_npg_allowed_apm()
    {
        return array_keys(static::get_all_npg_available_apm_info());
    }

    private static function get_npg_apm_info($circuit)
    {
        return static::get_all_npg_available_apm_info()[$circuit];
    }

    private static function is_currency_valid_for_apm($currency, $apmCode)
    {
        $validApmCodes = array(
            "CARDS",
            "GOOGLEPAY",
            "APPLEPAY",
        );

        if (WC_Nexi_Helper::nexi_array_key_exists_and_equals(WC_Nexi_Helper::get_nexi_settings(), 'nexi_xpay_multicurrency_enabled', 'yes') && in_array($apmCode, $validApmCodes)) {
            return in_array($currency, \Nexi\WC_Gateway_NPG_Currency::get_npg_supported_currency_list());
        }

        return $currency == 'EUR';
    }

}
