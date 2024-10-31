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

class WC_Gateway_XPay_Cards_Build extends WC_Gateway_XPay_Generic_Method
{

    public function __construct()
    {
        parent::__construct('xpay_build', true);

        $this->supports = array_merge($this->supports, ['tokenization']);

        $this->method_title = __('Payment cards', 'woocommerce-gateway-nexi-xpay');
        $this->method_description = __('Payment gateway.', 'woocommerce-gateway-nexi-xpay');
        $this->title = $this->method_title;

        $avaiable_methods_xpay = json_decode(\WC_Admin_Settings::get_option('xpay_available_methods'), true);
        $img_list = "";

        if (is_array($avaiable_methods_xpay)) {
            foreach ($avaiable_methods_xpay as $am) {
                if ($am['type'] != "CC") {
                    continue;
                }

                $img_list .= '  <div class="img-container"><img src="' . $am['image'] . '"></div>';
            }
        }

        if ($img_list != "") {
            $img_list = '<div class="nexixpay-loghi-container">' . $img_list . '</div>';
        }

        $this->description = $img_list . __("Pay securely by credit, debit and prepaid card. Powered by Nexi.", 'woocommerce-gateway-nexi-xpay');

        if (\WC_Admin_Settings::get_option('xpay_logo_small') == "") {
            $this->icon = WC_WOOCOMMERCE_GATEWAY_NEXI_XPAY_DEFAULT_LOGO_URL;
        } else {
            $this->icon = \WC_Admin_Settings::get_option('xpay_logo_small');
        }

        add_action('woocommerce_receipt_' . $this->id, array($this, 'exec_payment'));

        // Admin page
        add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_save'));

        add_action('woocommerce_scheduled_subscription_payment_' . $this->id, array($this, 'scheduled_subscription_payment'), 10, 2);

        $this->selectedCard = "CC";

        wp_enqueue_script('xpay_build_lib', \Nexi\WC_Gateway_XPay_API::getInstance()->getUrlXpayBuildJS());
    }

    function init_form_fields()
    {
        parent::init_form_fields();
    }

    private function getBuildStyle()
    {
        $style = array();

        $fontFamily = $this->settings['font_family'];
        $style['common']['fontFamily'] = $fontFamily;
        $style['correct']['fontFamily'] = $fontFamily;
        $style['error']['fontFamily'] = $fontFamily;

        $fontSize = $this->settings['font_size'];
        $style['common']['fontSize'] = $fontSize;
        $style['correct']['fontSize'] = $fontSize;
        $style['error']['fontSize'] = $fontSize;

        $fontStyle = $this->settings['font_style'];
        $style['common']['fontStyle'] = $fontStyle;
        $style['correct']['fontStyle'] = $fontStyle;
        $style['error']['fontStyle'] = $fontStyle;

        $fontVariant = $this->settings['font_variant'];
        $style['common']['fontVariant'] = $fontVariant;
        $style['correct']['fontVariant'] = $fontVariant;
        $style['error']['fontVariant'] = $fontVariant;

        $letterSpacing = $this->settings['letter_spacing'];
        $style['common']['letterSpacing'] = $letterSpacing;
        $style['correct']['letterSpacing'] = $letterSpacing;
        $style['error']['letterSpacing'] = $letterSpacing;

        $textColorPlaceholder = $this->settings['placeholder_color'];
        $style['common']['::placeholder']['color'] = $textColorPlaceholder;
        $style['correct']['::placeholder']['color'] = $textColorPlaceholder;
        $style['error']['::placeholder']['color'] = $textColorPlaceholder;

        $textColorInput = $this->settings['text_color'];
        $style['common']['color'] = $textColorInput;
        $style['correct']['color'] = $textColorInput;
        $style['error']['color'] = $textColorInput;

        return json_encode($style);
    }

    public function payment_fields()
    {
        global $wp;

        if (is_add_payment_method_page() && isset($wp->query_vars['add-payment-method'])) {
            echo __('New payment methods can only be added during checkout', 'woocommerce-gateway-nexi-xpay');
            return;
        }

        $this->tokenization_script();   //hides 'Use new payment card' flag if there aren't saved cards and checks if user is logged in to display the save card option

        echo $this->description . "<br>";

        $isRecurring = WC_Nexi_Helper::cart_contains_subscription();

        $payment_payload = \Nexi\WC_Gateway_XPay_API::getInstance()->get_payment_build_payload(\WC_Payment_Gateway::get_order_total());

        if (!$isRecurring) {
            $this->saved_payment_methods();
        }
        ?>
        <fieldset id="wc-<?php echo esc_attr($this->id) ?>-cc-form" class="wc-credit-card-form wc-payment-form">

            <script>
                renderXpayBuild();
            </script>

            <input type="hidden" id="xpay_msg_err" value="<?php echo __("The transaction has been declined, please retry.", 'woocommerce-gateway-nexi-xpay') ?>">

            <input type="hidden" id="xpay_new_payment_info" value="<?php echo htmlentities(json_encode($payment_payload)) ?>">
            <input type="hidden" name="divisa" value="<?php echo htmlentities($payment_payload["divisa"]) ?>">
            <input type="hidden" name="transactionId" id="xpay_build_transactionId" data-new-card-value="<?php echo htmlentities($payment_payload["transactionId"]) ?>">
            <input type="hidden" id="xpay_build_style" value="<?php echo htmlentities($this->getBuildStyle()); ?>">
            <input type="hidden" id="xpay_build_border_color_default" value="<?php echo htmlentities($this->settings['border_color_ok']); ?>">
            <input type="hidden" id="xpay_build_border_color_error" value="<?php echo htmlentities($this->settings['border_color_ko']); ?>">
            <input type="hidden" id="xpay_build_3ds" value="<?php echo $this->settings['nexi_xpay_3ds20_enabled'] == 'yes' ? 1 : 0; ?>">

            <?php
            do_action('woocommerce_credit_card_form_start', $this->id);
            ?>

            <!-- Contiene il form dei dati carta -->
            <div id="xpay-card" style="border: 1px solid  <?php echo $this->settings['border_color_ok']; ?> ; padding: 3px; max-width: 420px;"></div>

            <!-- Contiene gli errori -->
            <div id="xpay-card-errors"></div>
            <br>

            <!-- input valorizzati dopo la chiamata "creaNonce" -->
            <input type="hidden" name="xpayNonce" id="xpayNonce">
            <input type="hidden" name="xpayIdOperazione" id="xpayIdOperazione">
            <input type="hidden" name="xpayTimeStamp" id="xpayTimeStamp">
            <input type="hidden" name="xpayEsito" id="xpayEsito">
            <input type="hidden" name="xpayMac" id="xpayMac">

            <?php
            do_action('woocommerce_credit_card_form_end', $this->id);
            ?>

            <?php
            if ($isRecurring) {
                echo __('Attention, the order for which you are making payment contains recurring payments, payment data will be stored securely by Nexi.', 'woocommerce-gateway-nexi-xpay');
            } else if ($this->settings["nexi_xpay_oneclick_enabled"] == "yes") {
                ?>
                <p class="form-row woocommerce-SavedPaymentMethods-saveNew">
                    <input id="save-card" name="save-card" type="checkbox" value="1" style="width:auto;" />
                    <label for="save-card" style="display:inline;"><?php echo __('Remember the payment option.', 'woocommerce-gateway-nexi-xpay'); ?></label>
                </p>
            <?php }
            ?>
            <div class="clear"></div>

        </fieldset>
        <?php
    }

    public function process_payment($order_id)
    {
        $order = new \WC_Order($order_id);
        $result = 'failure';

        try {
            $isRecurring = WC_Nexi_Helper::order_or_cart_contains_subscription($order);
            $isNewCard = true;

            if (isset($_REQUEST["wc-" . $this->id . "-payment-token"])) {
                $isNewCard = $_REQUEST["wc-" . $this->id . "-payment-token"] == "new";
            }

            $save_card = false;
            if (isset($_REQUEST["save-card"])) {
                $save_card = $_REQUEST["save-card"] == "1";
            }

            $nonce = $_REQUEST['xpayNonce'];
            $codTrans = $_REQUEST["transactionId"];
            $divisa = $_REQUEST["divisa"];
            $num_contratto = "";
            $amount = WC_Nexi_Helper::mul_bcmul($order->get_total(), 100, 0);

            if ($save_card || $isRecurring) {
                $user_id = get_current_user_id();

                if ($user_id !== null) {
                    $md5_hash_num_contratto = md5($codTrans . "@" . $user_id . "@" . get_option('nexi_unique'));
                    $num_contratto = "BP" . base_convert($md5_hash_num_contratto, 16, 36);
                }

                \Nexi\WC_Gateway_XPay_API::getInstance()->pagaNonceCreazioneContratto($codTrans, $amount, $nonce, $divisa, $num_contratto, $order);
                WC_Build_Token::save_token($_REQUEST['dettaglioCarta']['brand'], $_REQUEST['dettaglioCarta']['pan'], $_REQUEST['dettaglioCarta']['scadenza'], $num_contratto);

                $order->add_order_note(__("Nexi XPay payment and card tokenization successful", 'woocommerce-gateway-nexi-xpay'));
            } else if (!$isNewCard) {
                \Nexi\WC_Gateway_XPay_API::getInstance()->pagaNonce(false, $codTrans, $amount, $nonce, $divisa, $order);

                $order->add_order_note(__("Nexi XPay payment with tokenized card successful", 'woocommerce-gateway-nexi-xpay'));
            } else {
                \Nexi\WC_Gateway_XPay_API::getInstance()->pagaNonce(true, $codTrans, $amount, $nonce, $divisa, $order);

                $order->add_order_note(__("Nexi XPay payment successful", 'woocommerce-gateway-nexi-xpay'));
            }

            $order->payment_complete();

            WC()->cart->empty_cart();

            WC_Save_Order_Meta::saveSuccessXPay(
                $order_id,
                \Nexi\WC_Gateway_XPay_API::getInstance()->get_build_alias(),
                $num_contratto,
                $codTrans,
                $_REQUEST['dettaglioCarta']['scadenza']
            );

            $result = 'success';
        } catch (\Throwable $th) {
            Log::actionWarning(__FUNCTION__ . ": error: " . $th->getMessage());

            update_post_meta($order_id, '_xpay_' . 'last_error', $th->getMessage());

            $order->update_status('failed');

            $order->add_order_note(__('Payment error', 'woocommerce-gateway-nexi-xpay') . ": " . $th->getMessage());

            if ($th->getCode() == 96) {
                wc_add_notice(__("Payment denied, please retry the transaction using the same card.", 'woocommerce-gateway-nexi-xpay'), "error");
            } else {
                wc_add_notice(__("Thank you for shopping with us. However, the transaction has been declined.", 'woocommerce-gateway-nexi-xpay') . " - " . ($th->getMessage()), "error");
            }
        }

        return array(
            'result' => $result,
            'redirect' => $this->get_return_url($order),
        );
    }

    public function get_saved_payment_method_option_html($token)
    {
        $html = sprintf(
            '<li class="woocommerce-SavedPaymentMethods-token">
                <input id="wc-%1$s-payment-token-%2$s" type="radio" name="wc-%1$s-payment-token" value="%2$s" style="width:auto;" class="woocommerce-SavedPaymentMethods-tokenInput" %4$s />
                <label for="wc-%1$s-payment-token-%2$s">%3$s</label>
            </li>',
            esc_attr($this->id),
            esc_attr($token->get_id()),
            $this->get_token_display_name($token) /* esc_html( $token->get_display_name()) */,
            checked($token->is_default(), true, false)
        );

        return apply_filters('woocommerce_payment_gateway_get_saved_payment_method_option_html', $html, $token, $this);
    }

    private function get_token_display_name($token)
    {
        global $woocommerce;

        // Translators: 1: credit card type 2: last 4 digits 3: expiry month 4: expiry year
        $display = sprintf(
            __('%1$s ending in %2$s (expires %3$s/%4$s)', 'woocommerce-gateway-nexi-xpay'),
            wc_get_credit_card_type_label($token->get_card_type()),
            $token->get_last4(),
            $token->get_expiry_month(),
            substr($token->get_expiry_year(), 2)
        );

        $codTransCvv = substr("BR-" . date('ysdim') . "-" . time(), 0, 30);
        $timestampCvv = time() * 1000;
        $importoCvv = WC_Nexi_Helper::mul_bcmul(floatval(preg_replace('#[^\d.]#', '', $woocommerce->cart->total)), 100, 0);

        $macCvv = WC_Gateway_XPay_API::getInstance()->calculate_mac_for_build_oneclick($codTransCvv, get_woocommerce_currency(), $importoCvv);

        return $display . '
            <div
                class="xpay-card-cvv"
                id="xpay-card-cvv-' . $token->get_token() . '"
                data-wc-id="' . $token->get_id() . '"
                data-token="' . $token->get_token() . '"
                data-codTransCvv="' . $codTransCvv . '"
                data-timestampCvv="' . $timestampCvv . '"
                data-macCvv="' . $macCvv . '"
                style="border: 1px solid ' . $this->settings['border_color_ok'] . '; max-width: 50px;"
            ></div>
            ';
    }

}
