<?php

/**
 * Plugin Name: Nexi XPay
 * Plugin URI:
 * Description: Payment plugin for payment cards and alternative methods. Powered by Nexi.
 * Version: 7.3.4
 * Author: Nexi SpA
 * Author URI: https://www.nexi.it
 * Text Domain: woocommerce-gateway-nexi-xpay
 * Domain Path: /lang
 * Copyright: © 2017-2021, Nexi SpA
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */
if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('is_plugin_active_for_network')) {
    require_once(ABSPATH . '/wp-admin/includes/plugin.php');
}

add_action('plugins_loaded', 'nexi_xpay_plugins_loaded');

function nexi_xpay_plugins_loaded()
{
    if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins'))) || is_plugin_active_for_network('woocommerce/woocommerce.php')) {
        define("WC_GATEWAY_XPAY_VERSION", "7.3.4");

        define("GATEWAY_XPAY", "xpay");
        define("GATEWAY_NPG", "npg");

        // The script build-plugin-variants.sh replaces xpay_build with 'xpay' or 'xpay_build'
        define('WC_GATEWAY_NEXI_PLUGIN_VARIANT', 'xpay_build');
        define('WC_GATEWAY_XPAY_PLUGIN_COLL', false);

        define('WC_SETTINGS_KEY', 'woocommerce_' . WC_GATEWAY_NEXI_PLUGIN_VARIANT . '_settings');

        define('NPG_OR_AUTHORIZED', 'AUTHORIZED');
        define('NPG_OR_EXECUTED', 'EXECUTED');
        define('NPG_OR_DECLINED', 'DECLINED');
        define('NPG_OR_DENIED_BY_RISK', 'DENIED_BY_RISK');
        define('NPG_OR_THREEDS_VALIDATED', 'THREEDS_VALIDATED');
        define('NPG_OR_THREEDS_FAILED', 'THREEDS_FAILED');
        define('NPG_OR_3DS_FAILED', '3DS_FAILED');
        define('NPG_OR_PENDING', 'PENDING');
        define('NPG_OR_CANCELED', 'CANCELED');
        define('NPG_OR_CANCELLED', 'CANCELLED');
        define('NPG_OR_VOIDED', 'VOIDED');
        define('NPG_OR_REFUNDED', 'REFUNDED');
        define('NPG_OR_FAILED', 'FAILED');
        define('NPG_OR_EXPIRED', 'EXPIRED');

        define('NPG_PAYMENT_SUCCESSFUL', [
            NPG_OR_AUTHORIZED,
            NPG_OR_EXECUTED,
        ]);

        define('NPG_PAYMENT_FAILURE', [
            NPG_OR_DECLINED,
            NPG_OR_DENIED_BY_RISK,
            NPG_OR_FAILED,
            NPG_OR_THREEDS_FAILED,
            NPG_OR_3DS_FAILED,
        ]);

        define('NPG_CONTRACT_CIT', 'CIT');

        define('NPG_OT_AUTHORIZATION', 'AUTHORIZATION');
        define('NPG_OT_CAPTURE', 'CAPTURE');
        define('NPG_OT_VOID', 'VOID');
        define('NPG_OT_REFUND', 'REFUND');
        define('NPG_OT_CANCEL', 'CANCEL');

        define('NPG_NO_RECURRING', 'NO_RECURRING');
        define('NPG_SUBSEQUENT_PAYMENT', 'SUBSEQUENT_PAYMENT');
        define('NPG_CONTRACT_CREATION', 'CONTRACT_CREATION');
        define('NPG_CARD_SUBSTITUTION', 'CARD_SUBSTITUTION');

        define('NPG_RT_MIT_SCHEDULED', 'MIT_SCHEDULED');

        load_plugin_textdomain('woocommerce-gateway-nexi-xpay', false, dirname(plugin_basename(__FILE__)) . '/lang');

        include_once realpath(plugin_dir_path(__FILE__)) . DIRECTORY_SEPARATOR . 'src' . DIRECTORY_SEPARATOR . "autoload.php";

        add_filter('woocommerce_payment_gateways', "\Nexi\WC_Gateway_Nexi_Register_Available::register");

        // Register endpoint in the rest api for s2s notification API, for post payment redirect url and for cancel url
        add_action('rest_api_init', '\Nexi\WC_Gateway_XPay_Process_Completion::rest_api_init');
        add_action('rest_api_init', '\Nexi\WC_Gateway_NPG_Process_Completion::rest_api_init');

        \Nexi\WC_Gateway_XPay_Process_Completion::register();
        \Nexi\WC_Gateway_NPG_Process_Completion::register();

        \Nexi\WC_Pagodil_Widget::register();

        add_action('wp_ajax_get_build_fields', '\Nexi\WC_Gateway_NPG_Cards_Build::get_build_fields');
        add_action('wp_ajax_nopriv_get_build_fields', '\Nexi\WC_Gateway_NPG_Cards_Build::get_build_fields');

        define('WC_ECOMMERCE_GATEWAY_NEXI_MAIN_FILE', __FILE__);

        function xpay_gw_wp_enqueue_scripts()
        {
            wp_enqueue_script('xpay-checkout', plugins_url('assets/js/xpay.js', __FILE__), array('jquery'), WC_GATEWAY_XPAY_VERSION);
            wp_enqueue_style('xpay-checkout', plugins_url('assets/css/xpay.css', __FILE__), [], WC_GATEWAY_XPAY_VERSION);

            if (WC_GATEWAY_NEXI_PLUGIN_VARIANT == 'xpay_build') {
                if (\Nexi\WC_Nexi_Helper::nexi_is_gateway_NPG()) {
                    wp_enqueue_script('xpay-build-npg-checkout', plugins_url('assets/js/xpay-build-npg.js', __FILE__), array('jquery'), WC_GATEWAY_XPAY_VERSION);
                } else {
                    wp_enqueue_script('xpay-build-checkout', plugins_url('assets/js/xpay-build.js', __FILE__), array('jquery'), WC_GATEWAY_XPAY_VERSION);
                }
            }
        }

        add_action('admin_init', '\Nexi\WC_Admin_Page::init');

        add_action('wp_enqueue_scripts', 'xpay_gw_wp_enqueue_scripts');

        if (!defined("WC_WOOCOMMERCE_GATEWAY_NEXI_XPAY_WOOCOMMERCE_VERSION")) {
            if (!function_exists('get_plugins')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }

            $plugins = get_plugins();

            define("WC_WOOCOMMERCE_GATEWAY_NEXI_XPAY_WOOCOMMERCE_VERSION", $plugins["woocommerce/woocommerce.php"]["Version"]);
        }

        if (!defined("WC_WOOCOMMERCE_GATEWAY_NEXI_XPAY_DEFAULT_LOGO_URL")) {
            $default_logo_url = plugins_url('assets/images/logo.jpg', plugin_basename(__FILE__));

            define("WC_WOOCOMMERCE_GATEWAY_NEXI_XPAY_DEFAULT_LOGO_URL", $default_logo_url);
        }

        // custom hook called by the scheduled cron
        add_action('wp_nexi_polling', 'wp_nexi_polling_executor');

        function wp_nexi_polling_executor()
        {
            $args = array(
                'payment_method' => WC_GATEWAY_NEXI_PLUGIN_VARIANT,
                'status' => ['wc-pending'],
                'orderby' => 'date',
                'order' => 'ASC',
            );

            $orders = wc_get_orders($args);

            foreach ($orders as $order) {
                $authorizationRecord = \Nexi\WC_Gateway_NPG_API::getInstance()->get_order_status($order->get_id());

                if ($authorizationRecord === null) {
                    \Nexi\Log::actionWarning(__FUNCTION__ . ': authorization operation not found for order: ' . $order->get_id());
                    continue;
                }

                $orderObj = new \WC_Order($order->get_id());

                switch ($authorizationRecord['operationResult']) {
                    case NPG_OR_AUTHORIZED:
                    case NPG_OR_EXECUTED:
                        $completed = $orderObj->payment_complete(get_post_meta($order->get_id(), "_npg_" . "orderId", true));

                        if ($completed) {
                            \Nexi\WC_Save_Order_Meta::saveSuccessNpg(
                                $order->get_id(),
                                $authorizationRecord
                            );
                        } else {
                            \Nexi\Log::actionWarning(__FUNCTION__ . ': unable to change order status: ' . $orderObj->get_status());
                        }
                        break;

                    case NPG_OR_PENDING:
                        \Nexi\Log::actionWarning(__FUNCTION__ . ': operation not in a final status yet');
                        break;

                    case NPG_OR_CANCELED:
                    case NPG_OR_CANCELLED:
                        \Nexi\Log::actionWarning(__FUNCTION__ . ': payment canceled');

                        if ($order->get_status() != 'cancelled') {
                            $order->update_status('cancelled');
                        }
                        break;

                    case NPG_OR_DECLINED:
                    case NPG_OR_DENIED_BY_RISK:
                    case NPG_OR_THREEDS_FAILED:
                    case NPG_OR_3DS_FAILED:
                    case NPG_OR_FAILED:
                        \Nexi\Log::actionWarning(__FUNCTION__ . ': payment error - operation: ' . json_encode($authorizationRecord));

                        if ($order->get_status() != 'cancelled') {
                            $orderObj->update_status('failed');
                        }

                        $orderObj->add_order_note(__('Payment error', 'woocommerce-gateway-nexi-xpay'));
                        break;

                    default:
                        \Nexi\Log::actionWarning(__FUNCTION__ . ': payment error - not managed operation status: ' . json_encode($authorizationRecord));
                        break;
                }
            }
        }

        add_action('wp_nexi_update_npg_payment_methods', 'wp_nexi_update_npg_payment_methods_executor');

        function wp_nexi_update_npg_payment_methods_executor()
        {
            try {
                \Nexi\WC_Gateway_NPG_API::getInstance()->get_profile_info();
            } catch (\Exception $exc) {
                \Nexi\Log::actionWarning(__FUNCTION__ . $exc->getMessage());
            }
        }

        // to add a new custom interval for cron execution
        function my_add_nexi_schedules_for_polling($schedules)
        {
            // add a 'nexi_polling_schedule' schedule to the existing set
            $schedules['nexi_polling_schedule'] = array(
                'interval' => 300,
                'display' => __('5 minutes')
            );

            $schedules['nexi_polling_schedule_2h'] = array(
                'interval' => 7200,
                'display' => __('2 hours'),
            );

            return $schedules;
        }

        add_filter('cron_schedules', 'my_add_nexi_schedules_for_polling');

        //chcks if the task is not already scheduled
        if (!wp_next_scheduled('wp_nexi_polling') && WC_GATEWAY_NEXI_PLUGIN_VARIANT == 'xpay' && \Nexi\WC_Nexi_Helper::nexi_is_gateway_NPG()) {
            //schedules the task by giving the first execution time, the interval and the hook to call
            wp_schedule_event(time(), 'nexi_polling_schedule', 'wp_nexi_polling');
        }

        if (!wp_next_scheduled('wp_nexi_update_npg_payment_methods') && WC_GATEWAY_NEXI_PLUGIN_VARIANT == 'xpay' && \Nexi\WC_Nexi_Helper::nexi_is_gateway_NPG()) {
            //schedules the task by giving the first execution time, the interval and the hook to call
            wp_schedule_event(time(), 'nexi_polling_schedule_2h', 'wp_nexi_update_npg_payment_methods');
        }

        function xpay_plugin_activation()
        {
            $nexi_unique = get_option("nexi_unique");

            if ($nexi_unique == "") {
                update_option('nexi_unique', uniqid());
            }
        }

        register_activation_hook(__FILE__, 'xpay_plugin_activation');

        function xpay_plugin_deactivation()
        {
            $timestamp = wp_next_scheduled('wp_nexi_polling');

            if ($timestamp) {
                wp_unschedule_event($timestamp, 'wp_nexi_polling');
            }

            $timestamp = wp_next_scheduled('wp_nexi_update_npg_payment_methods');

            if ($timestamp) {
                wp_unschedule_event($timestamp, 'wp_nexi_update_npg_payment_methods');
            }
        }

        register_deactivation_hook(__FILE__, 'xpay_plugin_deactivation');

        function xpay_plugin_action_links($links)
        {
            $plugin_links = array(
                '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=checkout&section=' . WC_GATEWAY_NEXI_PLUGIN_VARIANT)) . '">' . __('Settings') . '</a>',
            );

            return array_merge($plugin_links, $links);
        }

        add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'xpay_plugin_action_links');

        function nexi_xpay_plugin_init()
        {
            \Nexi\WC_Pending_Status::addNexiPendingPaymentOrderStatus();

            \Nexi\WC_Nexi_Db::run_updates();
        }

        add_action('init', 'nexi_xpay_plugin_init');

        add_filter('wc_order_statuses', '\Nexi\WC_Pending_Status::wcOrderStatusesFilter');

        add_filter('woocommerce_valid_order_statuses_for_payment_complete', '\Nexi\WC_Pending_Status::validOrderStatusesForPaymentCompleteFilter');

        add_action('woocommerce_payment_token_deleted', '\Nexi\WC_Gateway_NPG_Cards::woocommerce_payment_token_deleted', 10, 2);

        function nexixpay_admin_warning()
        {
            if (!extension_loaded('bcmath')) {
                $notice = '
                <div class="notice notice-warning">
                    <p><b>Nexi XPay</b>: ' . __('Warning, the PHP extension bcmath is not enabled. The amounts calculated by the plugin may be incorrect; please enable it to ensure correct calculations.', 'woocommerce-gateway-nexi-xpay') . '</p>
                </div>
            ';

                echo $notice;
            }
        }

        add_action('admin_notices', 'nexixpay_admin_warning');
    }
}
