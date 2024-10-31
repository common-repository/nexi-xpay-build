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

class WC_Gateway_NPG_Cards extends WC_Gateway_NPG_Generic_Method
{

    public function __construct()
    {
        parent::__construct('xpay', true);

        $this->supports = array_merge($this->supports, ['tokenization']);

        $this->method_title = __('Payment cards', 'woocommerce-gateway-nexi-xpay');
        $this->method_description = __('Payment gateway.', 'woocommerce-gateway-nexi-xpay');
        $this->title = $this->method_title;

        $this->description = $this->get_sorted_cards_images() . __("Pay securely by credit, debit and prepaid card. Powered by Nexi.", 'woocommerce-gateway-nexi-xpay');
    }

    public function process_payment($order_id)
    {
        $order = new \WC_Order($order_id);
        $result = 'failure';

        try {
            $recurringPayment = WC_Nexi_Helper::order_or_cart_contains_subscription($order);

            $selectedToken = 'new';

            if (isset($_REQUEST["wc-" . $this->id . "-payment-token"])) {
                $selectedToken = $_REQUEST["wc-" . $this->id . "-payment-token"];
            }

            $saveCard = false;
            if (isset($_REQUEST["save-card-npg"])) {
                $saveCard = $_REQUEST["save-card-npg"] == "1";
            }

            $installmentsNumber = 0;

            if (isset($_REQUEST["nexi-xpay-installments-number"])) {
                $installmentsNumber = $_REQUEST["nexi-xpay-installments-number"];
            }

            $redirectLink = WC_Gateway_NPG_API::getInstance()->new_payment_link($order, $recurringPayment, WC()->cart, $selectedToken, $saveCard, 'CARDS', $installmentsNumber);

            $result = 'success';
        } catch (\Throwable $th) {
            wc_add_notice($th->getMessage(), "error");

            $redirectLink = $this->get_return_url($order);
        }

        return array(
            'result' => $result,
            'redirect' => $redirectLink,
        );
    }

    function init_form_fields()
    {
        parent::init_form_fields();

        $title = __("APMs do not have a custom configuration. ", 'woocommerce-gateway-nexi-xpay');
        $title .= " ";
        $title .= __("Please use ", 'woocommerce-gateway-nexi-xpay');
        $title .= __('Nexi XPay', 'woocommerce-gateway-nexi-xpay');
        $title .= __(" configurations", 'woocommerce-gateway-nexi-xpay');

        $this->form_fields = array(
            'title_section_1' => array(
                'title' => $title,
                'type' => 'title',
            ),
        );
    }

    function payment_fields()
    {
        global $wp;

        if (is_add_payment_method_page() && isset($wp->query_vars['add-payment-method'])) {
            echo '<b>' . __('New payment methods can only be added during checkout.', 'woocommerce-gateway-nexi-xpay') . '</b>';
            return;
        }

        $this->tokenization_script();

        echo $this->description . '<br />';

        $installmentsInfo = $this->get_installments_info();

        if ($installmentsInfo["installments_enabled"]) {
            ?>
            <fieldset>
                <label for="nexi-xpay-installments-number" style="display: block;">
                    <?php echo __('Installments', 'woocommerce-gateway-nexi-xpay'); ?>
                </label>
                <select id="nexi-xpay-installments-number" name="nexi-xpay-installments-number">
                    <option value=""><?php echo __('One time solution', 'woocommerce-gateway-nexi-xpay'); ?></option>
                    <?php foreach ($installmentsInfo['max_installments'] as $installmentsNumber) { ?>
                        <option value="<?php echo $installmentsNumber; ?>"><?php echo $installmentsNumber; ?></option>
                    <?php } ?>
                </select>
            </fieldset>
            <?php
        }

        $isRecurring = WC_Nexi_Helper::cart_contains_subscription();

        if (!$isRecurring) {
            $this->saved_payment_methods();
        }

        if ($isRecurring) {
            ?>
            <fieldset id="wc-<?php echo esc_attr($this->id) ?>-cc-form">
                <?php
                echo __('Attention, the order for which you are making payment contains recurring payments, payment data will be stored securely by Nexi.', 'woocommerce-gateway-nexi-xpay');
                ?>
            </fieldset>
            <?php
        } else if ($this->settings["nexi_xpay_oneclick_enabled"] == "yes") {
            ?>
            <fieldset id="wc-<?php echo esc_attr($this->id) ?>-cc-form">
                <p class="form-row woocommerce-SavedPaymentMethods-saveNew">
                    <input id="save-card-npg" name="save-card-npg" type="checkbox" value="1" style="width:auto;" />
                    <label for="save-card-npg" style="display:inline;"><?php echo __('Remember the payment option.', 'woocommerce-gateway-nexi-xpay'); ?></label>
                </p>
            </fieldset>
            <?php
        }
    }

    public static function woocommerce_payment_token_deleted($token_id, $token)
    {
        $config = WC_Nexi_Helper::get_nexi_settings();

        if ($config['nexi_gateway'] == GATEWAY_NPG) {
            if ($token->get_gateway_id() === WC_GATEWAY_NEXI_PLUGIN_VARIANT) {
                \Nexi\WC_Gateway_NPG_API::getInstance()->deactivate_contract($token->get_token());
            }
        }
    }

    private function get_installments_info()
    {
        $installmentsEnabled = $this->settings["nexi_xpay_installments_enabled"] === "yes";

        $maxInstallments = array();

        if ($installmentsEnabled) {
            $tot = min($this->settings["nexi_xpay_max_installments"] ?? 99, $this->get_max_installments_number_by_cart());

            for ($i = 2; $i <= $tot; $i++) {
                $maxInstallments[] = $i;
            }
        }

        return array(
            'installments_enabled' => $installmentsEnabled && count($maxInstallments) > 0,
            'max_installments' => $maxInstallments,
        );
    }

    private function get_max_installments_number_by_cart()
    {
        $nInstallments = null;

        $ranges = json_decode($this->settings["nexi_xpay_installments_ranges"], true);

        if (is_array($ranges) && count($ranges)) {
            $baseGrandTotal = floatval(WC()->cart->total);

            $rangesValues = array_values($ranges);

            $toAmount = array_column($rangesValues, 'to_amount');

            array_multisort($toAmount, SORT_ASC, $rangesValues);

            foreach ($rangesValues as $value) {
                if ($baseGrandTotal <= $value['to_amount']) {
                    $nInstallments = (int) $value['n_installments'];
                    break;
                }
            }
        }

        return $nInstallments ?? 99;
    }

}
