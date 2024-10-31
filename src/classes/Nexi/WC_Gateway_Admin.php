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

class WC_Gateway_Admin extends \WC_Payment_Gateway
{

    public function __construct()
    {
        $this->id = WC_GATEWAY_NEXI_PLUGIN_VARIANT;

        $this->method_title = __('Nexi XPay', 'woocommerce-gateway-nexi-xpay');
        $this->method_description = __('Payment plugin for payment cards and alternative methods. Powered by Nexi.', 'woocommerce-gateway-nexi-xpay');
        $this->title = $this->method_title;
        $this->description = __("Pay securely by credit, debit and prepaid card. Powered by Nexi.", 'woocommerce-gateway-nexi-xpay');

        if (\WC_Admin_Settings::get_option('xpay_logo_small') == "") {
            $this->icon = WC_WOOCOMMERCE_GATEWAY_NEXI_XPAY_DEFAULT_LOGO_URL;
        } else {
            $this->icon = \WC_Admin_Settings::get_option('xpay_logo_small');
        }

        $this->init_form_fields();
        $this->init_settings();

        if ($this->id == "xpay_build") {
            wp_enqueue_script('xpay_build_lib', \Nexi\WC_Gateway_XPay_API::getInstance()->getUrlXpayBuildJS());
        } else {
            $this->set_nexi_default_gateway();
        }

        // Admin page
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_save'));
    }

    private function set_nexi_default_gateway()
    {
        if (array_key_exists('nexi_gateway', $this->settings) && $this->settings['nexi_gateway'] != null) {
            return;
        }

        $this->settings['nexi_gateway'] = GATEWAY_XPAY;

        if (
            array_key_exists('nexi_npg_api_key', $this->settings) &&
            $this->settings['nexi_npg_api_key'] != null &&
            array_key_exists('xpay_npg_available_methods', $this->settings)
        ) {
            $this->settings['nexi_gateway'] = GATEWAY_NPG;
        }

        update_option(WC_SETTINGS_KEY, $this->settings);
    }

    public static function my_error_notice_xpay()
    {
        ?>
        <div class="error notice">
            <p><?php echo __('Invalid credentials. Check and try again.', 'woocommerce-gateway-nexi-xpay'); ?></p>
        </div>
        <?php
    }

    public static function my_error_notice_npg()
    {
        ?>
        <div class="error notice">
            <p><?php echo __('Invalid API Key. Check and try again.', 'woocommerce-gateway-nexi-xpay'); ?></p>
        </div>
        <?php
    }

    function process_admin_save()
    {
        $this->process_admin_options();

        $this->update_profile_info();
    }

    private function update_profile_info()
    {
        if (array_key_exists('nexi_gateway', $this->settings) && $this->settings['nexi_gateway'] == GATEWAY_NPG) {
            try {
                $npg_ok = !is_null(WC_Gateway_NPG_API::getInstance()->get_profile_info());
            } catch (\Exception $exc) {
                $npg_ok = false;

                add_action('admin_notices', WC_Gateway_Admin::class . "::my_error_notice_npg");

                Log::actionWarning($exc->getMessage());
            }

            if (!$npg_ok) {
                delete_option('xpay_npg_available_methods');
            } else {
                delete_option('xpay_available_methods');
            }
        } else {
            try {
                $xpay_ok = !is_null(WC_Gateway_XPay_API::getInstance()->get_profile_info());
            } catch (\Exception $exc) {
                $xpay_ok = false;

                add_action('admin_notices', WC_Gateway_Admin::class . "::my_error_notice_xpay");

                Log::actionWarning($exc->getMessage());
            }

            if (!$xpay_ok) {
                delete_option('xpay_available_methods');
                delete_option('xpay_logo_small');
                delete_option('xpay_logo_large');
            } else {
                delete_option('xpay_npg_available_methods');
            }
        }

        if (!extension_loaded('bcmath') || !function_exists("bcmul") || !function_exists("bcdiv")) {
            Log::actionWarning("Library bcmath not loaded or function bcdiv|bcmul not defined!");
        }
    }

    function init_form_fields()
    {
        parent::init_form_fields();

        $descriptionEnable = __('For a correct behavior of the module, check in the configuration section of the Nexi back-office that the transaction cancellation in the event of a failed notification is set.', 'woocommerce-gateway-nexi-xpay') . '<br/><br/>'
            . __('A POST notification by the Nexi servers is sent to the following address, containing information on the outcome of the payment.', 'woocommerce-gateway-nexi-xpay') . '<br/>'
            . '<b>' . get_rest_url(null, "woocommerce-gateway-nexi-xpay/s2s/") . "(xpay|npg)/(order id)" . '</b><br/>'
            . __('The notification is essential for the functioning of the plugin, it is therefore necessary that it is not blocked or filtered by the site infrastructure.', 'woocommerce-gateway-nexi-xpay');

        $description3ds20 = "";
        $description3ds20 .= __('The 3D Secure 2 protocol adopted by the main international circuits (Visa, MasterCard, American Express), introduces new authentication methods, able to improve and speed up the cardholder\'s purchase experience.', 'woocommerce-gateway-nexi-xpay');
        $description3ds20 .= '<br><br>';
        $description3ds20 .= __('By activating this option it is established that the terms and conditions that you submit to your customers, with particular reference to the privacy policy, are foreseen to include the acquisition and processing of additional data provided by the', 'woocommerce-gateway-nexi-xpay');
        $description3ds20 .= ' <a class="xpay-only-text" href=\"https://ecommerce.nexi.it/specifiche-tecniche/3dsecure2/introduzione.html\" target="_blank">' . __('3D Secure 2 Service', 'woocommerce-gateway-nexi-xpay') . '</a> ';
        $description3ds20 .= ' <span class="npg-only-text">' . __('3D Secure 2 Service', 'woocommerce-gateway-nexi-xpay') . '</span> ';
        $description3ds20 .= __('(for example, shipping and / or invoicing address, payment details). Nexi and the International Circuits use the additional data collected separately for the purpose of fraud prevention.', 'woocommerce-gateway-nexi-xpay');

        $this->form_fields = array(
            'enabled' => array(
                'title' => __('Enable/Disable', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'checkbox',
                'label' => __("Enable Nexi XPay payment plugin.", 'woocommerce-gateway-nexi-xpay'),
                'description' => $descriptionEnable,
                'default' => 'no',
            ),
        );

        $this->form_fields = array_merge($this->form_fields, array(
            'nexi_gateway' => array(
                'title' => __('Choose the type of credentials you have available for XPay', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'select',
                'options' => array(
                    GATEWAY_XPAY => __('Alias and MAC Key', 'woocommerce-gateway-nexi-xpay'),
                    GATEWAY_NPG => __('APIKey', 'woocommerce-gateway-nexi-xpay')
                ),
                'description' => '- ' . __('Select "Alias and MAC Key" option if you received the credentials of the production environment in the Welcome Mail received from Nexi during the activation of the service', 'woocommerce-gateway-nexi-xpay') . '<br />'
                . '- ' . __('Select "APIKey" option if you use the API Key as the credential of the production environment generated from the Back Office XPay. Follow the directions in the developer portal for the correct generation process.', 'woocommerce-gateway-nexi-xpay'),
                'class' => 'gateway-input'
            )
        ));

        $this->form_fields = array_merge($this->form_fields, array(
            'nexi_xpay_alias' => array(
                'title' => __('Alias', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'text',
                'description' => __('Given to Merchant by Nexi.', 'woocommerce-gateway-nexi-xpay'),
                'class' => 'xpay-only',
            ),
            'nexi_xpay_mac' => array(
                'title' => __('Key MAC', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'text',
                'description' => __('Given to Merchant by Nexi.', 'woocommerce-gateway-nexi-xpay'),
                'class' => 'xpay-only',
            ),
        ));

        $this->form_fields = array_merge($this->form_fields, array(
            'nexi_npg_api_key' => array(
                'title' => __('API Key', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'text',
                'description' => __('Generated from the Back Office XPay. Follow the directions in the developer portal for the correct generation process.', 'woocommerce-gateway-nexi-xpay'),
                'class' => 'npg-only'
            ),
        ));

        $this->form_fields = array_merge($this->form_fields, array(
            'nexi_xpay_test_mode' => array(
                'title' => __('Enable/Disable TEST Mode', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'checkbox',
                'label' => __('Enable Nexi XPay plugin in testing mode.', 'woocommerce-gateway-nexi-xpay'),
                'default' => 'no',
                'description' => '<span class="xpay-only-text" >' . __('Please register at', 'woocommerce-gateway-nexi-xpay') . ' <a href="https://ecommerce.nexi.it/area-test" target="_blank">ecommerce.nexi.it/area-test</a> ' . __('to get the test credentials.', 'woocommerce-gateway-nexi-xpay') . '</span><span class="npg-only-text">' . __('Please refer to Dev Portal to get access to the Sandbox', 'woocommerce-gateway-nexi-xpay') . '</span>',
            ),
            'nexi_xpay_accounting' => array(
                'title' => __('Accounting', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'select',
                'options' => array(
                    "C" => __('Immediate', 'woocommerce-gateway-nexi-xpay'),
                    "D" => __('Deferred', 'woocommerce-gateway-nexi-xpay')
                ),
                'description' => __('The field identifies the collection method that the merchant wants to apply to the single transaction, if valued with:<br>- I (immediate) the transaction if authorized is also collected without further intervention by the operator and without considering the default profile set on the terminal.<br>- D (deferred) or the field is not inserted the transaction if authorized is managed according to what is defined by the terminal profile', 'woocommerce-gateway-nexi-xpay'),
                'class' => 'xpay-only',
            ),
            'nexi_xpay_3ds20_enabled' => array(
                'title' => __('Enable 3D Secure 2 service', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'checkbox',
                'label' => __('Enable the sending of the fields for the 3D Secure 2 service', 'woocommerce-gateway-nexi-xpay'),
                'default' => 'no',
                'description' => $description3ds20,
            ),
            'nexi_xpay_oneclick_enabled' => array(
                'title' => __('Enable/Disable OneClick', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'checkbox',
                'label' => __('Enable Nexi XPay for OneClick payment', 'woocommerce-gateway-nexi-xpay'),
                'default' => 'no',
                'description' => __('Enable Nexi XPay for OneClick payment. Make sure that this option is also enabled on your terminal configuration.', 'woocommerce-gateway-nexi-xpay'),
                'class' => $this->id == "xpay_build" ? 'xpay-only' : '',
            ),
        ));

        $this->form_fields = array_merge($this->form_fields, array(
            'nexi_xpay_multicurrency_enabled' => array(
                'title' => __('Enable/Disable Multicurrency', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'checkbox',
                'label' => __('Enable Nexi XPay for Multicurrency payments', 'woocommerce-gateway-nexi-xpay'),
                'default' => 'no',
                'description' => __('Enable this option to make the payment methods available for different currencies. To have the complete list of the supported currencies, please visit the developer Portal. Make sure that this option is also enabled on your terminal configuration.', 'woocommerce-gateway-nexi-xpay'),
                'class' => 'npg-only',
            ),
        ));

        $this->form_fields = array_merge($this->form_fields, array(
            'nexi_xpay_installments_enabled' => array(
                'title' => __('Enable/Disable Installment Payments', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'checkbox',
                'label' => __('Enable/Disable Installment Payments', 'woocommerce-gateway-nexi-xpay'),
                'default' => 'no',
                'description' => __('Enable this option to use installment payments via XPay. This functionality is only available to merchants with Greek VAT Number. Before enabling this functionality, make sure it is available on your terminal with your payment provider.', 'woocommerce-gateway-nexi-xpay'),
                'class' => 'npg-only installments-enabled',
            ),
        ));

        $maxInstallmentsOptions = array();

        for ($i = 2; $i < 100; $i++) {
            $maxInstallmentsOptions[$i] = $i;
        }

        $this->form_fields = array_merge($this->form_fields, array(
            'nexi_xpay_max_installments' => array(
                'title' => __('Maximum number of installments regardless of the total order amount', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'select',
                'options' => $maxInstallmentsOptions,
                'label' => __('Maximum number of installments regardless of the total order amount', 'woocommerce-gateway-nexi-xpay'),
                'default' => 'no',
                'description' => __('1 to 99 installments, 1 for one shot payment. Before set up a configuration, make sure to check with your payment provider what is the maximum number accepted for your terminal.', 'woocommerce-gateway-nexi-xpay'),
                'class' => 'npg-only installments-only',
            ),
        ));

        $this->form_fields = array_merge($this->form_fields, array(
            'nexi_xpay_installments_ranges' => array(
                'title' => __('Maximum number of installments depending on the total order amount', 'woocommerce-gateway-nexi-xpay'),
                'type' => 'field_group',
                'label' => __('Maximum number of installments depending on the total order amount', 'woocommerce-gateway-nexi-xpay'),
                'default' => '[]',
                'description' => __('Add amount and installments for each row. The installments limit is 99', 'woocommerce-gateway-nexi-xpay'),
                'class' => 'npg-only installments-only',
            ),
        ));

        if ($this->id == "xpay_build") {
            $this->form_fields = array_merge($this->form_fields, array(
                'style_title' => array(
                    'title' => __('Style configuration', 'woocommerce-gateway-nexi-xpay'),
                    'type' => 'title',
                    'description' => __('By using this configurator you can change the look and feel of your module', 'woocommerce-gateway-nexi-xpay'),
                    'class' => 'style_title xpay-only-text',
                ),
                'preview' => array(
                    'title' => __('Preview', 'woocommerce-gateway-nexi-xpay'),
                    'type' => 'label',
                    'label' => '',
                    'class' => 'xpay-only'
                ),
                'font_family' => array(
                    'title' => __('Font family', 'woocommerce-gateway-nexi-xpay'),
                    'type' => 'text',
                    'description' => __('The font family in the CC Form', 'woocommerce-gateway-nexi-xpay'),
                    'default' => 'Arial',
                    'desc_tip' => true,
                    'class' => 'build_style font-family xpay-only',
                ),
                'font_size' => array(
                    'title' => __('Font size', 'woocommerce-gateway-nexi-xpay'),
                    'type' => 'text',
                    'description' => __('The size of the font in the CC Form in pixel', 'woocommerce-gateway-nexi-xpay'),
                    'default' => '15px',
                    'desc_tip' => true,
                    'class' => 'build_style font-size xpay-only',
                ),
                'font_style' => array(
                    'title' => __('Font style', 'woocommerce-gateway-nexi-xpay'),
                    'type' => 'select',
                    'description' => __('Font style in the CC Form', 'woocommerce-gateway-nexi-xpay'),
                    'default' => 'Normal',
                    'desc_tip' => true,
                    'options' => $this->getOptionsConfigFontStyle(),
                    'class' => 'build_style font-style xpay-only',
                ),
                'font_variant' => array(
                    'title' => __('Font variant', 'woocommerce-gateway-nexi-xpay'),
                    'type' => 'select',
                    'description' => __('Font variant in the CC Form', 'woocommerce-gateway-nexi-xpay'),
                    'default' => 'Normal',
                    'desc_tip' => true,
                    'options' => $this->getOptionsConfigFontVariant(),
                    'class' => 'build_style font-variant xpay-only',
                ),
                'letter_spacing' => array(
                    'title' => __('Letter spacing', 'woocommerce-gateway-nexi-xpay'),
                    'type' => 'text',
                    'description' => __('The space between letters in pixel', 'woocommerce-gateway-nexi-xpay'),
                    'default' => '1px',
                    'desc_tip' => true,
                    'class' => 'build_style letter-spacing xpay-only',
                ),
                'border_color_ok' => array(
                    'title' => __('Border Color', 'woocommerce-gateway-nexi-xpay'),
                    'type' => 'color',
                    'description' => __('When form is empty or correct', 'woocommerce-gateway-nexi-xpay'),
                    'default' => '#CDCDCD',
                    'desc_tip' => true,
                    'css' => 'width:362px;',
                    'class' => 'build_style border-color xpay-only',
                ),
                'border_color_ko' => array(
                    'title' => __('Error Border Color', 'woocommerce-gateway-nexi-xpay'),
                    'type' => 'color',
                    'description' => __('When form has error', 'woocommerce-gateway-nexi-xpay'),
                    'default' => '#C80000',
                    'desc_tip' => true,
                    'css' => 'width:362px;',
                    'class' => 'xpay-only',
                ),
                'placeholder_color' => array(
                    'title' => __('Placeholder Color', 'woocommerce-gateway-nexi-xpay'),
                    'type' => 'color',
                    'description' => __('Text color of placeholder', 'woocommerce-gateway-nexi-xpay'),
                    'default' => '#CDCDCD',
                    'desc_tip' => true,
                    'css' => 'width:362px;',
                    'class' => 'build_style placeholder-color xpay-only',
                ),
                'text_color' => array(
                    'title' => __('Text Color', 'woocommerce-gateway-nexi-xpay'),
                    'type' => 'color',
                    'description' => __('Text color in input field', 'woocommerce-gateway-nexi-xpay'),
                    'default' => '#5C5C5C',
                    'desc_tip' => true,
                    'css' => 'width:362px;',
                    'class' => 'build_style color xpay-only',
                ),
            ));
        }

        $this->form_fields = array_merge($this->form_fields, \Nexi\WC_Pagodil_Configuration::getSettingsForm());

        if (function_exists("wcs_is_subscription")) {
            $this->form_fields = array_merge($this->form_fields, array(
                'title_section_6' => array(
                    'title' => __("Subscription configuration", 'woocommerce-gateway-nexi-xpay'),
                    'type' => 'title',
                ),
                'nexi_xpay_recurring_enabled' => array(
                    'title' => __('Enable/Disable Recurring', 'woocommerce-gateway-nexi-xpay'),
                    'type' => 'checkbox',
                    'label' => __("Enable Nexi XPay for subscription's payment", 'woocommerce-gateway-nexi-xpay'),
                    'default' => 'no',
                    'description' => '',
                ),
                'nexi_xpay_recurring_alias' => array(
                    'title' => __('Recurring Alias', 'woocommerce-gateway-nexi-xpay'),
                    'type' => 'text',
                    'description' => __('Given to Merchant by Nexi.', 'woocommerce-gateway-nexi-xpay'),
                    'class' => 'xpay-only',
                ),
                'nexi_xpay_recurring_mac' => array(
                    'title' => __('Recurring key MAC', 'woocommerce-gateway-nexi-xpay'),
                    'type' => 'text',
                    'description' => __('Given to Merchant by Nexi.', 'woocommerce-gateway-nexi-xpay'),
                    'class' => 'xpay-only',
                ),
                'nexi_xpay_group' => array(
                    'title' => __('Group', 'woocommerce-gateway-nexi-xpay'),
                    'type' => 'text',
                    'description' => __('Given to Merchant by Nexi.', 'woocommerce-gateway-nexi-xpay'),
                    'class' => 'xpay-only',
                ),
            ));
        }
    }

    /**
     * Generate Field group HTML.
     *
     * @param mixed $key
     * @param mixed $data
     * @return string
     */
    public function generate_field_group_html($key, $data)
    {
        $field = $this->plugin_id . $this->id . '_' . $key;

        $value = $this->get_option($key);

        if ($value === false || $value === null || $value === "") {
            $value = $data['default'];
        }

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field); ?>"><?php echo wp_kses_post($data['title']); ?></label>
            </th>
            <td class="forminp <?php echo esc_attr($data['class']); ?>">
                <fieldset>
                    <div id="installments-ranges-variations-container">
                        <table>
                            <thead>
                                <tr>
                                    <th><?php echo __('Up to an amount of', 'woocommerce-gateway-nexi-xpay'); ?></th>
                                    <th><?php echo __('Maximum installments', 'woocommerce-gateway-nexi-xpay'); ?></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>

                    <div>
                        <button class="button" id="add-ranges-variation"><?php echo __('Add rule', 'woocommerce-gateway-nexi-xpay'); ?></button>
                    </div>
                    
                    <input type="hidden" id="ranges-delete-label" value="<?php echo __('Delete', 'woocommerce-gateway-nexi-xpay'); ?>" />

                    <input type="hidden" id="<?php echo esc_attr($key); ?>" name="<?php echo esc_attr($field); ?>" value="<?php echo esc_attr($value); ?>" />
                </fieldset>

                <style>
                    #installments-ranges-variations-container table thead th,
                    #installments-ranges-variations-container table tbody td {
                        padding: 5px 10px;
                        padding-left: 0;
                        width: 200px;
                    }

                    #installments-ranges-variations-container table tbody td input {
                        width: 190px;
                    }

                    #installments-ranges-variations-container {
                        margin-bottom: 20px;
                    }
                </style>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate Label HTML.
     *
     * @param mixed $key
     * @param mixed $data
     * @return string
     */
    public function generate_label_html($key, $data)
    {
        $field = $this->plugin_id . $this->id . '_' . $key;

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field); ?>"><?php echo wp_kses_post($data['title']); ?></label>
            </th>
            <td class="forminp <?php echo esc_attr($data['class']); ?>">
                <fieldset>
                    <?php
                    if ($this->id == "xpay") {
                        ?>

                        <label for="<?php echo esc_attr($field); ?>"><?php echo wp_kses_post($data['label']); ?></label>

                        <?php echo $this->get_description_html($data); ?>

                        <?php
                    } else {
                        $path = plugin_dir_path(WC_ECOMMERCE_GATEWAY_NEXI_MAIN_FILE);

                        include_once $path . 'templates/build_preview.php';
                    }
                    ?>
                </fieldset>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    /**
     * Generate Label HTML.
     *
     * @param mixed $key
     * @param mixed $data
     * @return string
     */
    public function generate_simple_label_html($key, $data)
    {
        $field = $this->plugin_id . $this->id . '_' . $key;

        ob_start();
        ?>
        <tr valign="top">
            <th scope="row" class="titledesc">
                <label for="<?php echo esc_attr($field); ?>"><?php echo wp_kses_post($data['title']); ?></label>
            </th>
            <td class="forminp">
                <fieldset>
                    <label for="<?php echo esc_attr($field); ?>" class="<?php echo wp_kses_post($data['class']); ?>"><?php echo wp_kses_post($data['label']); ?></label>

                    <?php echo $this->get_description_html($data); ?>
                </fieldset>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    private function getOptionsConfigFontStyle()
    {
        return array(
            "Normal" => "Normal",
            "Italic" => "Italic",
            "Oblique" => "Oblique",
            "Initial" => "Initial",
            "Inherit" => "Inherit"
        );
    }

    private function getOptionsConfigFontVariant()
    {
        return array(
            "Normal" => "Normal",
            "Small-caps" => "Small-caps",
            "Initial" => "Initial",
            "Inherit" => "Inherit"
        );
    }

}
