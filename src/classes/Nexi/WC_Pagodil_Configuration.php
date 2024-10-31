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

class WC_Pagodil_Configuration
{
    /**
     * return Pagodil setting form fields
     *
     * @return array
     */
    public static function getSettingsForm()
    {
        $form_fields = array();

        $pagodilConfig = \Nexi\WC_Pagodil_Widget::getPagodilConfig();

        if ($pagodilConfig === null) {
            return $form_fields;
        }

        $installmentsNumber = \Nexi\WC_Pagodil_Widget::getAvailableInstallmentsNumber();

        $form_fields = array(
            'title_section_4' => array(
                'title' => __("PagoDIL by Cofidis", 'woocommerce-gateway-nexi-xpay'),
                'type' => 'title',
                'class' => 'xpay-only-text',
            ),
            'pd_enabled' => array(
                'title' => __('Enable PagoDIL', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'checkbox',
                'label' => __('Activate the PagoDIL payment method within the store.', 'woocommerce-gateway-nexi-xpay'),
                'default' => 'no',
                'class' => 'xpay-only',
            ),
            'pd_min_amount' => array(
                'title' => __('Minimum cart value', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'simple_label',
                'label' => WC_Nexi_Helper::div_bcdiv(\Nexi\WC_Pagodil_Widget::getPagodilMinAmount($pagodilConfig), 100, 2) . " €",
                'description' => __('Minimum cart value (in Euro) for which it will be possible to proceed through installment payments with PagoDIL. This value corresponds to the amount set in the XPay back office.', 'woocommerce-gateway-nexi-xpay'),
                'class' => 'xpay-only',
            ),
            'pd_max_amount' => array(
                'title' => __('Maximum cart value', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'simple_label',
                'label' => WC_Nexi_Helper::div_bcdiv(\Nexi\WC_Pagodil_Widget::getPagodilMaxAmount($pagodilConfig), 100, 2) . " €",
                'description' => __('Maximum cart value (in Euro) for which it will be possible to proceed through installment payments with PagoDIL. This value corresponds to the amount set in the XPay back office.', 'woocommerce-gateway-nexi-xpay'),
                'class' => 'xpay-only',
            ),
            'pd_installments_number' => array(
                'title' => __('Number of installments', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'simple_label',
                'label' => implode(", ", $installmentsNumber),
                'description' => __('Number of installments made available for payment via PagoDIL. The rates displayed correspond to the values set in the XPay back office.', 'woocommerce-gateway-nexi-xpay'),
                'class' => 'xpay-only',
            ),
            'pd_check_mode_categories' => array(
                'title' => __('Installment products', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'select',
                'options' => array(
                    "selected_categories" => __('For selected categories', 'woocommerce-gateway-nexi-xpay'),
                    "all_categories" => __('All categories', 'woocommerce-gateway-nexi-xpay')
                ),
                'desc_tip' => __('Setting up installment products:<br>- For selected categories: select the categories of products of the store that you want to set as payable in installments from the drop-down list below.<br>- All categories: all the products in the store will be configured as payable in installments.', 'woocommerce-gateway-nexi-xpay'),
                'class' => 'check-mode-categories xpay-only',
                'default' => "all_categories",
            ),
            'pd_categories' => array(
                'title' => __('Categories', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'multiselect',
                'options' => self::getOptionsConfigCategoriesTree(),
                'desc_tip' => __('Select the categories on which you want to enable payment via PagoDIL.', 'woocommerce-gateway-nexi-xpay'),
                'class' => 'categories-select2 xpay-only',
            ),
            'pd_field_name_cf' => array(
                'title' => __('Tax code', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'select',
                'options' => self::getCheckoutFields(),
                'desc_tip' => __('Select the field of your shop that corresponds to the tax code, it will be used by the plugin during the payment phase with PagoDIl: in this way the customer will not have to enter this data manually on the PagoDIL payment page.', 'woocommerce-gateway-nexi-xpay'),
                'default' => "",
                'class' => 'xpay-only',
            ),
            'pd_product_limits' => array(
                'title' => __('Number of products in the cart (Optional)', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'text',
                'desc_tip' => __('Maximum number of products in the cart for which it will be possible to proceed through installment payments with PagoDIL.', 'woocommerce-gateway-nexi-xpay'),
                'class' => 'xpay-only',
            ),
            'pd_product_code' => array(
                'title' => __('Cofidis product code (Optional)', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'text',
                'desc_tip' => __('If you have more than one Cofidis product code, enter the code that will be used by the plugin for payment via PagoDIL.', 'woocommerce-gateway-nexi-xpay'),
                'class' => 'xpay-only',
            ),
            'title_section_5' => array(
                'title' => __("Widget PagoDIL", 'woocommerce-gateway-nexi-xpay'),
                'type' => 'title',
                'class' => 'xpay-only-text',
            ),
            'pd_show_widget' => array(
                'title' => __('Show widget', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'checkbox',
                'label' => __('Enable the PagoDIL widget on products that can be paid in installments. The widget shows information relating to the installment payments made available on the product.', 'woocommerce-gateway-nexi-xpay'),
                'default' => 'yes',
                'class' => 'xpay-only',
            ),
            'pd_installments_number_widget' => array(
                'title' => __('Number of installments', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'select',
                'options' => $installmentsNumber,
                'desc_tip' => __('Number of installments displayed within the widget. It is possible to select only one value (e.g. or 5 interest-free installments).', 'woocommerce-gateway-nexi-xpay'),
                'default' => end($installmentsNumber),
                'class' => 'xpay-only',
            ),
            'pd_logo_type_widget' => array(
                'title' => __('Logo type', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'select',
                'options' => self::getLogoType(),
                'desc_tip' => __('Select the PagoDIL logo displayed in the widget.', 'woocommerce-gateway-nexi-xpay'),
                'default' => 'logo_1',
                'class' => 'xpay-only',
            ),
            'pd_link_find_out_more' => array(
                'title' => __('"Find out more" link', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'text',
                'desc_tip' => __('The PagoDIL widget has an information icon pointing to a predefined web address. It is possible to change the information section by entering a new address in the form.', 'woocommerce-gateway-nexi-xpay'),
                'default' => 'https://www.pagodil.it/e-commerce/come-funziona/',
                'class' => 'xpay-only',
            ),
        );

        return $form_fields;
    }

    /**
     * List of checkout fields to choose fiscal code field, shown in configuration
     * 
     * @return array
     */
    private static function getCheckoutFields()
    {
        //proceeds to get fields only if the current request is for an administrative interface page
        //required because otherwise crashes on s2s callback
        if (is_admin()) {
            if (WC()->checkout === null) {
                return array();
            }

            $fields = WC()->checkout->get_checkout_fields();

            $content = array(
                "" => __("select Tax Code Field", 'woocommerce-gateway-nexi-xpay')
            );

            foreach ($fields['billing'] as $key => $value) {
                if (!array_key_exists('custom_field', $value) || $value['custom_field'] != 1) {
                    continue;
                }

                if ($value['label']) {
                    $content[$key] = $value['label'];
                } else {
                    $content[$key] = $key;
                }
            }

            return $content;
        }

        return array();
    }

    /**
     * List of categories to be made payable in installments, shown in configuration
     * 
     * @return array
     */
    private static function getOptionsConfigCategoriesTree()
    {
        $categories = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);

        $parentCategories = array();
        $childCategories = array();

        foreach ($categories as $category) {
            if ($category->parent == 0) {
                $parentCategories[] = $category;
            } else {
                if (!array_key_exists($category->parent, $childCategories)) {
                    $childCategories[$category->parent] = array();
                }

                $childCategories[$category->parent][] = $category;
            }
        }

        $options = array();

        foreach ($parentCategories as $parentCategory) {
            $options[$parentCategory->term_id] = $parentCategory->name;

            $childOptions = self::getChildOptions($childCategories, $parentCategory->term_id);

            foreach ($childOptions as $key => $childOption) {
                $options[$key] = $parentCategory->name . ' -> ' . $childOption;
            }
        }

        return $options;
    }

    /**
     *
     * @return array
     */
    private static function getChildOptions($childCategories, $id)
    {
        $options = array();

        if (array_key_exists($id, $childCategories)) {
            foreach ($childCategories[$id] as $childCategory) {
                $options[$childCategory->term_id] = $childCategory->name;

                $childOptions = self::getChildOptions($childCategories, $childCategory->term_id);

                foreach ($childOptions as $childKey => $childOption) {
                    $options[$childKey] = $childCategory->name . ' -> ' . $childOption;
                }
            }
        }

        return $options;
    }

    /**
     * List of logo types available in the widget, shown in configuration
     * 
     * @return array
     */
    private static function getLogoType()
    {
        return array(
            "logo_1" => __('PagoDIL institutional logo', 'woocommerce-gateway-nexi-xpay'),
            "logo_5" => __('White', 'woocommerce-gateway-nexi-xpay'),
        );
    }
}
