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

class WC_Pagodil_Widget
{

    public static function register()
    {
        add_action('wp_ajax_calc_installments', '\Nexi\WC_Pagodil_Widget::calc_installments');
        add_action('wp_ajax_nopriv_calc_installments', '\Nexi\WC_Pagodil_Widget::calc_installments');

        // Load widget script
        add_action('wp_head', '\Nexi\WC_Pagodil_Widget::wp_head');

        // Add widget in product details
        add_action('woocommerce_before_add_to_cart_button', '\Nexi\WC_Pagodil_Widget::woocommerce_before_add_to_cart_button');

        // Add widget in the list of products
        add_action('woocommerce_after_shop_loop_item_title', '\Nexi\WC_Pagodil_Widget::woocommerce_after_shop_loop_item_title', 100, 0);

        // Add widget in cart page
        add_action('woocommerce_proceed_to_checkout', '\Nexi\WC_Pagodil_Widget::woocommerce_proceed_to_checkout');

        // Add widget in checkout page
        add_action('woocommerce_review_order_before_payment', '\Nexi\WC_Pagodil_Widget::woocommerce_review_order_before_payment');

        // Save fiscal code in checkout page
        add_action('woocommerce_checkout_update_order_meta', '\Nexi\WC_Pagodil_Widget::save_custom_checkout_field');
    }

    /**
     * Calculate the installments amount
     * 
     * @param int $installments
     */
    public static function calc_installments($installments = null)
    {
        if ($installments == null && !empty($_REQUEST['installments'])) {
            $installments = sanitize_text_field($_REQUEST['installments']);
        }

        $total = self::getCartTotal(WC()->cart);

        $installments_amount = self::calcInstallmentsAmount($total, $installments);

        wp_send_json(array(
            'installmentsLabel' => sprintf(__('Amount: %s installments of %s€', 'woocommerce-gateway-nexi-xpay'), $installments, $installments_amount),
            'installments' => $installments,
            'installmentsAmount' => $installments_amount,
        ));

        wp_die();
    }

    public static function save_custom_checkout_field($order_id)
    {
        if (!self::isPagodilEnabled()) {
            return;
        }

        $xpaySettings = self::getXPaySettings();

        if ($xpaySettings['pd_field_name_cf']) {
            update_post_meta($order_id, $xpaySettings['pd_field_name_cf'], esc_attr($_POST[$xpaySettings['pd_field_name_cf']]));
        }
    }

    public static function wp_head()
    {
        echo '<script src="' . plugins_url('assets/js/pagodil-sticker.min.js', WC_ECOMMERCE_GATEWAY_NEXI_MAIN_FILE) . '?v=' . WC_GATEWAY_XPAY_VERSION . '"></script>'
            . '<style>.pagodil-sticker-container { display: inline-block; margin-bottom: 60px; } </style>';
    }

    /**
     * Add widget in product details
     *
     * @return void
     */
    public static function woocommerce_before_add_to_cart_button()
    {
        if (!self::isPagodilEnabled()) {
            return;
        }

        global $post;

        $product = wc_get_product($post->ID);

        if ($product !== false) {
            $xpaySettings = self::getXPaySettings();

            $pagodilConfig = self::getPagodilConfig();

            $installableCategories = self::getEnabledCategories($xpaySettings);

            if (self::isProductInstallable($installableCategories, $xpaySettings, $pagodilConfig, $product) && $xpaySettings['pd_show_widget'] == "yes") {
                $extraAttributes = array(
                    'data-amount-selector' => '.woocommerce-variation-price .woocommerce-Price-amount.amount bdi',
                    'data-amount-change-listener-selector' => '.woocommerce-variation.single_variation',
                    'data-amount-multiplier' => '1',
                    'data-language-mode' => 'B',
                );

                echo '<div style="display: block; margin-bottom: 20px;">' . self::getPagodilSticker(WC_Nexi_Helper::mul_bcmul($product->get_price(), 100, 0), $xpaySettings, $pagodilConfig, $extraAttributes) . '</div>';
            }
        }
    }

    /**
     * Add widget in the list of products
     *
     * @return void
     */
    public static function woocommerce_after_shop_loop_item_title()
    {
        if (!self::isPagodilEnabled()) {
            return;
        }

        global $post;

        $product = wc_get_product($post->ID);

        if ($product !== false) {
            $xpaySettings = self::getXPaySettings();

            $pagodilConfig = self::getPagodilConfig();

            $installableCategories = self::getEnabledCategories($xpaySettings);

            if (self::isProductInstallable($installableCategories, $xpaySettings, $pagodilConfig, $product) && $xpaySettings['pd_show_widget'] == "yes") {
                echo self::getPagodilSticker(WC_Nexi_Helper::mul_bcmul($product->get_price(), 100, 0), $xpaySettings, $pagodilConfig, array('data-show-logo' => 'false'));
            }
        }
    }

    /**
     * Add widget in cart page
     *
     * @return void
     */
    public static function woocommerce_proceed_to_checkout()
    {
        if (!self::isPagodilEnabled()) {
            return;
        }

        global $woocommerce;

        $xpaySettings = self::getXPaySettings();

        $pagodilConfig = self::getPagodilConfig();

        if (self::isQuoteInstallable($xpaySettings, $pagodilConfig, $woocommerce->cart) && $xpaySettings['pd_show_widget'] == "yes") {
            $extraAttributes = array(
                'data-language-mode' => 'B',
            );

            echo '<div style="margin-bottom: 20px;">' . self::getPagodilSticker(self::getCartTotal($woocommerce->cart), $xpaySettings, $pagodilConfig, $extraAttributes) . '</div>';
        }
    }

    /**
     * Add widget in checkout page
     *
     * @return void
     */
    public static function woocommerce_review_order_before_payment()
    {
        if (!self::isPagodilEnabled()) {
            return;
        }

        global $woocommerce;

        $xpaySettings = self::getXPaySettings();

        $pagodilConfig = self::getPagodilConfig();

        $amount = self::getCartTotal($woocommerce->cart);

        if (
            !self::totalNotBigEnough($pagodilConfig, $amount) &&
            self::totalTooBig($pagodilConfig, $amount) &&
            self::checkNumberOfProducts($xpaySettings, $woocommerce->cart) &&
            self::checkCategories($xpaySettings, $pagodilConfig, $woocommerce->cart)
        ) {
            wc_add_notice(
                sprintf(
                    __('Do you want to pay convenient installments without interest with PagoDIL by Cofidis? Reach the minimum amount of %s€ in the cart', 'woocommerce-gateway-nexi-xpay'),
                    WC_Nexi_Helper::div_bcdiv(self::getPagodilMinAmount($pagodilConfig), 100, 2)
                ),
                'notice'
            );
        }
    }

    private static function getPagodilSticker($amount, $xpaySettings, $pagodilConfig, $extraAttributes)
    {
        $attributes = array(
            'data-amount' => $amount,
            'data-installments-number' => $xpaySettings['pd_installments_number_widget'],
            'data-min-amount' => self::getPagodilMinAmount($pagodilConfig),
            'data-max-amount' => self::getPagodilMaxAmount($pagodilConfig),
            'data-logo-kind' => $xpaySettings['pd_logo_type_widget'],
            'data-info-link' => $xpaySettings['pd_link_find_out_more'],
            'data-language' => self::getPagodilLanguage(),
            'data-amount-bold' => 'true'
        );

        $attributesToUse = array_merge($attributes, $extraAttributes);

        $pagodilSticker = '<pagodil-sticker';

        foreach ($attributesToUse as $name => $value) {
            $pagodilSticker .= ' ' . $name . '="' . htmlentities($value) . '" ';
        }

        $pagodilSticker .= '></pagodil-sticker>';

        return $pagodilSticker;
    }

    private static function getPagodilLanguage()
    {
        $language = 'it';

        $locale = get_locale();

        switch ($locale) {

            case 'es_ES':
                $language = 'es';
                break;

            case 'fr_FR':
                $language = 'fr';
                break;

            case 'de_DE':
            case 'ja':
                $language = 'de';

            case 'en_GB':
            case 'en_US':
                $language = 'en';
                break;
        }

        return $language;
    }

    /**
     * Calculate installments amount
     * 
     * @return float
     */
    public static function calcInstallmentsAmount($total, $installments)
    {
        $installment_amountNF = floor(WC_Nexi_Helper::div_bcdiv($total, $installments, 2));

        return number_format(WC_Nexi_Helper::div_bcdiv($installment_amountNF, 100, 3), 2, ',', ' ');
    }

    /**
     * Return PagoDIL configuration
     * 
     * @return array
     */
    public static function getPagodilConfig()
    {
        $availableMethods = json_decode(\WC_Admin_Settings::get_option('xpay_available_methods'), true);

        if (is_array($availableMethods)) {
            foreach ($availableMethods as $method) {
                if ($method['code'] === 'PAGODIL') {
                    return $method;
                }
            }
        }

        return null;
    }

    private static function canSkipCategoriesCheck($xpaySettings)
    {
        return $xpaySettings['pd_check_mode_categories'] === "all_categories";
    }

    /**
     * Check if single product is payable in installments
     * 
     * @return boolean
     */
    public static function isProductInstallable($installableCategories, $xpaySettings, $pagodilConfig, $product)
    {
        if ($xpaySettings['enabled'] != "yes" || !self::isPagodilEnabled() || $pagodilConfig === null) {
            return false;
        }

        if (self::canSkipCategoriesCheck($xpaySettings)) {
            return true;
        }

        $categories = wc_get_product_term_ids($product->get_id(), 'product_cat');

        return count(array_intersect($categories, $installableCategories)) > 0;
    }

    /**
     * Check if quote is payable in installments
     * 
     * @return boolean
     */
    public static function isQuoteInstallable($xpaySettings, $pagodilConfig, $cart)
    {
        $amount = self::getCartTotal($cart);

        return self::totalNotBigEnough($pagodilConfig, $amount) && self::totalTooBig($pagodilConfig, $amount) && self::checkNumberOfProducts($xpaySettings, $cart) && self::checkCategories($xpaySettings, $pagodilConfig, $cart);
    }

    /**
     * Check if total amount is not big enough
     * 
     * @return boolean
     */
    public static function totalNotBigEnough($pagodilConfig, $amount)
    {
        if ($amount < self::getPagodilMinAmount($pagodilConfig)) {
            return false;
        }

        return true;
    }

    /**
     * Check if total amount is too big
     * 
     * @return boolean
     */
    public static function totalTooBig($pagodilConfig, $amount)
    {
        if ($amount > self::getPagodilMaxAmount($pagodilConfig)) {
            return false;
        }

        return true;
    }

    /**
     * Check if number of products in the cart is smaller than configuration value
     * 
     * @return boolean
     */
    public static function checkNumberOfProducts($xpaySettings, $cart)
    {
        if ($xpaySettings['pd_product_limits'] && $xpaySettings['pd_product_limits'] !== "") {
            $allItems = $cart->get_cart();

            // Invalid number of products
            if (count($allItems) > $xpaySettings['pd_product_limits']) {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if products in the cart are payable in installments
     * 
     * @return boolean
     */
    public static function checkCategories($xpaySettings, $pagodilConfig, $cart)
    {
        $installableCategories = self::getEnabledCategories($xpaySettings);

        // Invalid installable categories
        if ((!is_array($installableCategories) || count($installableCategories) === 0) && !self::canSkipCategoriesCheck($xpaySettings)) {
            return false;
        }

        $allItems = $cart->get_cart();

        foreach ($allItems as $item) {
            // Check if product is installable
            if (!self::isProductInstallable($installableCategories, $xpaySettings, $pagodilConfig, $item['data'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Return total amount from cart
     * 
     * @return float
     */
    public static function getCartTotal($cart)
    {
        return WC_Nexi_Helper::mul_bcmul(floatval($cart->total), 100, 0);
    }

    /**
     * Categories saved as payable in installments
     * 
     * @param array $xpaySettings
     * @return array
     */
    private static function getEnabledCategories($xpaySettings)
    {
        $enabledCategories = $xpaySettings['pd_categories'];

        if ($enabledCategories) {
            if (!is_array($enabledCategories)) {
                $enabledCategories = json_decode($enabledCategories);
            }
        } else {
            $enabledCategories = array();
        }

        return $enabledCategories;
    }

    /**
     * Return minimum price limit based on XPay configuration
     * 
     * @param array $pagodilConfig
     * @return int
     */
    public static function getPagodilMinAmount($pagodilConfig)
    {
        if (!isset($pagodilConfig['importo']['min'])) {
            return 0;
        }

        return $pagodilConfig['importo']['min'];
    }

    /**
     * Return maximum price limit based on XPay configuration
     * 
     * @param array $pagodilConfig
     * @return int
     */
    public static function getPagodilMaxAmount($pagodilConfig)
    {
        if (!isset($pagodilConfig['importo']['max'])) {
            return 10000000000;
        }

        return $pagodilConfig['importo']['max'];
    }

    /**
     * 
     * @return array
     */
    public static function getXPaySettings()
    {
        return WC_Nexi_Helper::get_nexi_settings();
    }

    /**
     * Checks if PagoDIL is enabled from the settings page
     *
     * @return boolean
     */
    public static function isPagodilEnabled()
    {
        $xpaySettings = self::getXPaySettings();

        if (!array_key_exists('pd_enabled', $xpaySettings)) {
            return false;
        }

        return $xpaySettings['pd_enabled'] == "yes";
    }

    /**
     * List of available installments number based on XPay configuration, shown in configuration
     * 
     * @return array
     */
    public static function getAvailableInstallmentsNumber()
    {
        $numbers = self::getArrayOfInstallmentValues();

        $installmentsNumber = array();

        foreach ($numbers as $number) {
            $installmentsNumber[$number] = $number;
        }

        return $installmentsNumber;
    }

    /**
     * List of installment values based on XPay configuration
     * 
     * @return array
     */
    private static function getArrayOfInstallmentValues()
    {
        $pagodilConfig = self::getPagodilConfig();

        $values = array();

        if (is_array($pagodilConfig) && count($pagodilConfig)) {
            $typeInstallment = $pagodilConfig['tipoRata'];
            $installmentValues = $pagodilConfig['valoriRata'];

            if (self::isTypeSingleInstallment($typeInstallment)) {
                $values[] = $installmentValues;
            } else if (self::isTypeRangeInstallment($typeInstallment)) {
                $range = array(
                    (int) $installmentValues['min'],
                    (int) $installmentValues['max'],
                );

                sort($range);

                for ($i = $range[0]; $i <= $range[1]; $i++) {
                    $values[] = $i;
                }
            } else if (self::isTypeMultipleInstallments($typeInstallment)) {
                $values = $installmentValues;
            }
        }

        $array = array();

        foreach ($values as $value) {
            $array[] = (int) $value;
        }

        sort($array);

        return $array;
    }

    /**
     * 
     * @return bool
     */
    private static function isTypeSingleInstallment($type)
    {
        return strtoupper(trim($type)) === "VALORE";
    }

    /**
     * 
     * @return bool
     */
    private static function isTypeRangeInstallment($type)
    {
        return strtoupper(trim($type)) === "RANGE";
    }

    /**
     * 
     * @return bool
     */
    private static function isTypeMultipleInstallments($type)
    {
        return strtoupper(trim($type)) === "VALORI";
    }
}
