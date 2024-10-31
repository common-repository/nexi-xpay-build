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

abstract class WC_Gateway_XPay_Generic_Method extends \WC_Payment_Gateway
{

    protected $selectedCard;
    protected $recurring;

    protected function __construct($id, $recurring)
    {
        $this->id = $id;
        $this->recurring = $recurring;

        $this->supports = array(
            'products',
            'refunds',
        );

        $this->init_form_fields();
        $this->init_settings();

        if (function_exists("wcs_is_subscription") && array_key_exists("nexi_xpay_recurring_enabled", $this->settings)) {
            if (($this->settings["nexi_xpay_recurring_enabled"] == "yes") && $this->recurring) {
                array_push(
                    $this->supports,
                    'subscriptions',
                    'subscription_cancellation',
                    'subscription_suspension',
                    'subscription_reactivation',
                    'subscription_amount_changes',
                    'subscription_date_changes',
                    'subscription_payment_method_change'
                );
            }
        }
    }

    public function process_payment($order_id)
    {
        $order = new \WC_Order($order_id);

        if (!empty($_REQUEST['installments'])) {
            update_post_meta($order_id, "installments", sanitize_text_field($_REQUEST['installments']));
        }

        return array(
            'result' => 'success',
            'redirect' => $order->get_checkout_payment_url(true),
        );
    }

    public function payment_fields()
    {
        global $wp;

        if (is_add_payment_method_page() && isset($wp->query_vars['add-payment-method'])) {
            echo '<b>' . __('New payment methods can only be added during checkout.', 'woocommerce-gateway-nexi-xpay') . '</b>';
            return;
        }

        if (WC_Nexi_Helper::cart_contains_subscription()) {
            echo __('Attention, the order for which you are making payment contains recurring payments, payment data will be stored securely by Nexi.', 'woocommerce-gateway-nexi-xpay');
        } else {
            echo $this->description;
        }
    }

    public function exec_payment($order_id)
    {
        $order = new \WC_Order($order_id);

        $recurringPaymentRequired = WC_Nexi_Helper::order_or_cart_contains_subscription($order);

        $order_form = \Nexi\WC_Gateway_XPay_API::getInstance()->get_payment_form($order, $this->selectedCard, $recurringPaymentRequired);

        echo "<form ";
        echo " action=\"" . htmlentities($order_form["target_url"]) . "\" ";
        echo " id=\"nexi_xpay_receipt_form\" ";
        echo " method=\"post\" ";
        echo " enctype=\"application/www-x-form-urlencoded\" ";
        echo " >";
        foreach ($order_form["fields"] as $name => $value) {
            // echo "<label>" . htmlentities($name) . "</label>";
            echo "<input type=\"hidden\" name=\"" . htmlentities($name) . "\" value=\"" . htmlentities($value) . "\" />";
            // echo "<br>";
        }
        echo "<input type=\"submit\" />";
        echo "</form>";
    }

    /**
     * on subscription renewal a new order is crated and post meta from the original one are copied and saved with new order's id as post_id
     * Con una nuova versione del plugin sembra che non vengano copiati i dati quindi prendiamo il campo _subscription_renewal per recuperare le info sul contratto
     * 
     * @param type $amount_to_charge
     * @param type $order
     */
    public function scheduled_subscription_payment($amount_to_charge, $order)
    {
        $subscriptionId = $order->get_meta('_subscription_renewal');

        if ($subscriptionId) {
            $idToUse = $subscriptionId;
        } else {
            $idToUse = $order->get_id();
        }

        Log::actionInfo(__METHOD__ . "::" . __LINE__ . ' $idToUse ' . json_encode($idToUse));

        $num_contratto = WC_Nexi_Helper::get_xpay_post_meta($idToUse, 'num_contratto');
        $scadenza_pan = WC_Nexi_Helper::get_xpay_post_meta($idToUse, 'scadenza_pan');
        $currency = $order->get_currency();

        list($alias, $newCodTrans) = \Nexi\WC_Gateway_XPay_API::getInstance()->recurring_payment($num_contratto, $scadenza_pan, $amount_to_charge, $currency, $order);

        //must be uptaed otherwise refferrs to the the first payment
        WC_Save_Order_Meta::saveSuccessXPay($order->get_id(), $alias, $num_contratto, $newCodTrans, $scadenza_pan);

        $order->payment_complete($newCodTrans);
    }

    /**
     * order state is changed to "Refunded" automatically when total amount is refunded
     * 
     * @param type $order_id
     * @param type $amount
     * @param type $reaseon
     * @return type
     */
    public function process_refund($order_id, $amount = null, $reaseon = "")
    {
        if ($amount > 0) {
            $order = new \WC_Order($order_id);
            $cod_trans = WC_Nexi_Helper::get_xpay_post_meta($order->get_id(), 'codTrans');
            $currency = $order->get_currency();

            $res = \Nexi\WC_Gateway_XPay_API::getInstance()->refund($cod_trans, $amount, $currency);

            if ($res) {
                $orderDetails = \Nexi\WC_Gateway_XPay_API::getInstance()->order_detail($cod_trans);

                if ($orderDetails["stato"] == 'Annullato') {
                    $order->update_status('cancelled');
                }
            }

            return $res;
        }

        return new \WP_Error("invalid_refund_amount", __('Invalid refund amount.', 'woocommerce-gateway-nexi-xpay'));
    }

    public function init_form_fields()
    {
        
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
                    <label for="<?php echo esc_attr($field); ?>"><?php echo wp_kses_post($data['label']); ?></label>

                    <?php echo $this->get_description_html($data); ?>
                </fieldset>
            </td>
        </tr>
        <?php
        return ob_get_clean();
    }

    /**
     * Add payment method via account screen.
     * doesn't return a response so that nothing is displayed (error/success message)
     *
     */
    public function add_payment_method()
    {
        
    }

    /**
     * Function to retrieve the language of checkout page, based off the module settings and user navigation language
     *
     * @return string
     */
    public static function get_language_id()
    {
        // Default language, it will be overridden by user one
        $language_id = 'ENG';

        $locale = get_locale();

        switch ($locale) {

            case 'it_IT':
                $language_id = 'ITA';
                break;

            case 'ar':
                $language_id = 'ARA';
                break;

            case 'zh_CN':
                $language_id = 'CHI';
                break;

            case 'ru_RU':
                $language_id = 'RUS';
                break;

            case 'es_ES':
                $language_id = 'SPA';
                break;

            case 'fr_FR':
                $language_id = 'FRA';
                break;

            case 'de_DE':
                $language_id = 'GER';
                break;

            case 'ja':
                $language_id = 'GER';
                break;

            case 'pt_PT':
                $language_id = 'POR';
                break;

            case 'el':
                $currentConfig = WC_Nexi_Helper::get_nexi_settings();

                if (WC_Nexi_Helper::nexi_array_key_exists_and_equals($currentConfig, 'nexi_gateway', GATEWAY_NPG)) {
                    $language_id = 'ELL';
                }
                break;

            case 'en_GB':
            case 'en_US':
            default:
                $language_id = 'ENG';
                break;
        }

        return $language_id;
    }

}
