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

class WC_Gateway_XPay_API extends \WC_Settings_API
{

    private static $instance = null;

    /**
     *
     * @return \Nexi\WC_Gateway_XPay_API
     */
    public static function getInstance()
    {
        if (self::$instance == null) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    public $id = WC_GATEWAY_NEXI_PLUGIN_VARIANT;
    private $nexi_xpay_alias;
    private $nexi_xpay_mac;
    private $nexi_xpay_group = "";
    private $nexi_xpay_recurring_alias = "";
    private $nexi_xpay_recurring_mac = "";
    private $base_url = null;
    private $nexi_xpay_accounting;
    private $nexi_xpay_oneclick_enabled;
    private $nexi_xpay_recurring_enabled;
    private $nexi_xpay_3ds20_enabled;
    private $xpay_build_enviroment;

    private function __construct()
    {
        $this->init_settings();

        if ($this->settings["nexi_xpay_test_mode"] == "no") {
            $this->base_url = 'https://ecommerce.nexi.it/';
            $this->xpay_build_enviroment = "PROD";
        } else if (WC_GATEWAY_XPAY_PLUGIN_COLL) {
            $this->base_url = 'https://coll-ecommerce.nexi.it/';
            $this->xpay_build_enviroment = "INTEG";
        } else {
            $this->base_url = 'https://int-ecommerce.nexi.it/';
            $this->xpay_build_enviroment = "INTEG";
        }

        $this->load_nexi_settings();
    }

    private function load_nexi_settings()
    {
        $this->init_settings();

        $this->nexi_xpay_alias = $this->settings["nexi_xpay_alias"];
        $this->nexi_xpay_mac = $this->settings["nexi_xpay_mac"];

        $this->nexi_xpay_accounting = $this->settings["nexi_xpay_accounting"];

        $this->nexi_xpay_oneclick_enabled = ($this->settings["nexi_xpay_oneclick_enabled"] == "yes");

        $this->nexi_xpay_recurring_enabled = ($this->settings["nexi_xpay_recurring_enabled"] == "yes");
        $this->nexi_xpay_recurring_alias = $this->settings["nexi_xpay_recurring_alias"];
        $this->nexi_xpay_recurring_mac = $this->settings["nexi_xpay_recurring_mac"];
        $this->nexi_xpay_group = $this->settings["nexi_xpay_group"] ?? '';

        $this->nexi_xpay_3ds20_enabled = ($this->settings['nexi_xpay_3ds20_enabled'] == 'yes');
    }

    public function get_profile_info()
    {
        $this->load_nexi_settings();

        if (strlen($this->nexi_xpay_alias) == 0 || strlen($this->nexi_xpay_mac) == 0) {
            delete_option('xpay_available_methods');
            delete_option('xpay_logo_small');
            delete_option('xpay_logo_large');
            return null;
        }

        $timeStamp = (time()) * 1000;

        // MAC calculation
        $macStr = 'apiKey=' . $this->nexi_xpay_alias;
        $macStr .= 'timeStamp=' . $timeStamp;
        $macStr .= $this->nexi_xpay_mac;
        $mac = sha1($macStr);

        // Params
        $payload = array(
            'apiKey' => $this->nexi_xpay_alias,
            'timeStamp' => $timeStamp,
            'mac' => $mac,
            'platform' => 'woocommerce',
            'platformVers' => WC_WOOCOMMERCE_GATEWAY_NEXI_XPAY_WOOCOMMERCE_VERSION,
            'pluginVers' => WC_GATEWAY_XPAY_VERSION
        );

        $profile_info = $this->exec_curl_post_json("ecomm/api/profileInfo", $payload);

        // Check on the outcome
        if ($profile_info['esito'] != 'OK') {
            Log::actionWarning(__FUNCTION__ . ": remote error: " . $profile_info['errore']['messaggio']);
            throw new \Exception(__('Response KO', 'woocommerce-gateway-nexi-xpay'));
        }

        $macResponseStr = 'esito=' . $profile_info['esito'];
        $macResponseStr .= 'idOperazione=' . $profile_info['idOperazione'];
        $macResponseStr .= 'timeStamp=' . $profile_info['timeStamp'];
        $macResponseStr .= $this->nexi_xpay_mac;

        $MACrisposta = sha1($macResponseStr);

        // Check on repsonse MAC
        if ($profile_info['mac'] != $MACrisposta) {
            Log::actionWarning(__FUNCTION__ . ": error: " . $profile_info['mac'] . " != " . $MACrisposta);
            throw new \Exception(__('Mac verification failed', 'woocommerce-gateway-nexi-xpay'));
        }

        update_option('xpay_available_methods', json_encode($profile_info['availableMethods']));
        update_option('xpay_logo_small', $profile_info['urlLogoNexiSmall']);
        update_option('xpay_logo_large', $profile_info['urlLogoNexiLarge']);

        if (is_array($profile_info['availableMethods'])) {
            foreach ($profile_info['availableMethods'] as $method) {
                $config_name = "woocommerce_xpay_" . $method['code'] . "_settings";

                $config = get_option($config_name, array());

                $config['enabled'] = 'yes';

                update_option($config_name, $config);
            }
        }

        return $profile_info;
    }

    public function get_payment_form($order, $selectedcard, $recurringPaymentRequired)
    {
        $importo = WC_Nexi_Helper::mul_bcmul($order->get_total(), 100, 0);

        $chiaveSegreta = $this->nexi_xpay_mac;

        $params = array(
            'alias' => $this->nexi_xpay_alias,
            'importo' => $importo,
            'divisa' => $order->get_currency(),
            'mail' => $order->get_billing_email(),
            'url' => get_rest_url(null, "woocommerce-gateway-nexi-xpay/redirect/xpay/" . $order->get_id()), //returning URL
            'url_back' => get_rest_url(null, "woocommerce-gateway-nexi-xpay/cancel/xpay/" . $order->get_id()), //cancel URL
            'languageId' => WC_Gateway_XPay_Generic_Method::get_language_id(), //checkout page lang
            'descrizione' => "WC Order: " . $order->get_order_number(),
            'urlpost' => get_rest_url(null, "woocommerce-gateway-nexi-xpay/s2s/xpay/" . $order->get_id()), //S2S notification URL
            'selectedcard' => $selectedcard,
            'TCONTAB' => $this->nexi_xpay_accounting,
            'Note1' => 'woocommerce',
            'Note2' => WC_WOOCOMMERCE_GATEWAY_NEXI_XPAY_WOOCOMMERCE_VERSION,
            'Note3' => WC_GATEWAY_XPAY_VERSION
        );

        $costumer_id = $order->get_customer_id();

        if ($recurringPaymentRequired) {
            if (!$this->nexi_xpay_recurring_enabled) {
                Log::actionWarning(__FUNCTION__ . ": recurring payment for non recurring payment method");
                throw new \Exception("Recurring not enabled");
            }

            $params['alias'] = $this->nexi_xpay_recurring_alias;

            $chiaveSegreta = $this->nexi_xpay_recurring_mac;

            // Contract number
            $md5_hash = md5($costumer_id . "@" . $order->get_order_number() . "@" . time() . '@' . get_option('nexi_unique'));
            $params['num_contratto'] = substr("RP" . base_convert($md5_hash, 16, 36), 0, 30);

            $params['tipo_servizio'] = "paga_multi"; // static param for recurring payments
            $params['tipo_richiesta'] = "PP"; //PP = First Payment
            $params['gruppo'] = $this->nexi_xpay_group;

            $params['codTrans'] = $this->get_cod_trans($order->get_order_number(), "PR");

            $macString = 'codTrans=' . $params['codTrans'];
            $macString .= 'divisa=' . $params['divisa'];
            $macString .= 'importo=' . $params['importo'];
        } else {
            // Is using CC and is logged in enable the "one click" payment
            if ($selectedcard == "CC" && is_user_logged_in() && $this->nexi_xpay_oneclick_enabled) {
                $order_user = $order->get_user();

                // This will store, on nexi sistems, the card data to speed up the payment process
                $params['codTrans'] = $this->get_cod_trans($order->get_id(), "");

                $md5_hash = md5($costumer_id . "@" . $order_user->user_email . '@' . get_option('nexi_unique'));
                $params['num_contratto'] = substr("OC" . base_convert($md5_hash, 16, 36), 0, 30);

                $params['tipo_servizio'] = "paga_1click";

                if ($this->nexi_xpay_group != "") {
                    $params['gruppo'] = $this->nexi_xpay_group;
                }

                // Oneclick
                $macString = 'codTrans=' . $params['codTrans'];
                $macString .= 'divisa=' . $params['divisa'];
                $macString .= 'importo=' . $params['importo'];
                $macString .= 'gruppo=' . $this->nexi_xpay_group;
                $macString .= 'num_contratto=' . $params['num_contratto'];
            } else {
                $params['codTrans'] = $this->get_cod_trans($order->get_id(), "");
                $macString = 'codTrans=' . $params['codTrans'];
                $macString .= 'divisa=' . $params['divisa'];
                $macString .= 'importo=' . $params['importo'];
            }
        }

        $params['mac'] = sha1($macString . $chiaveSegreta);

        if ($this->nexi_xpay_3ds20_enabled && $selectedcard == "CC") {
            $params = array_merge($params, WC_3DS20_Data_Provider::calculate_params($order));
        }

        if ($selectedcard == "PAGODIL") {
            $params = array_merge($params, WC_Pagodil_Data_Provider::calculate_params($order));
        }

        update_post_meta($order->get_id(), "_xpay_" . "codTrans", $params['codTrans']);

        return array(
            "target_url" => $this->base_url . "ecomm/ecomm/DispatcherServlet",
            "fields" => $params,
        );
    }

    public function get_payment_build_payload($total)
    {
        $importo = WC_Nexi_Helper::mul_bcmul($total, 100, 0);

        $transactionId = substr("BP-" . date('ysdim') . "-" . time(), 0, 30);

        $divisa = array(
            'EUR' => '978',
            'CZK' => '203',
            'PLN' => '985',
            'AUD' => '036',
            'NZD' => '554'
            )[get_woocommerce_currency()];

        $buildData = array(
            "amount" => $importo,
            "enviroment" => $this->xpay_build_enviroment,
            "apiKey" => $this->nexi_xpay_alias,
            "transactionId" => $transactionId,
            "divisa" => $divisa,
            "timestamp" => time() * 1000,
            "mac" => sha1("codTrans=" . $transactionId . "divisa=" . $divisa . "importo=" . $importo . $this->nexi_xpay_mac),
            "language" => WC_Gateway_XPay_Generic_Method::get_language_id(),
            "buildStyle" => "OK",
            "build_border_color_default" => "white",
            "build_border_color_error" => "OK",
        );

        if ($this->nexi_xpay_3ds20_enabled) {
            $buildData = array_merge($buildData, WC_3DS20_Data_Provider::getParamsFromWC(WC()));
        }

        return $buildData;
    }

    /**
     * Return codTrans param for XPay gateway
     *
     * @param string $payment_type
     * @return string
     */
    protected function get_cod_trans($order_id, $payment_type)
    {
        $cod_trans = '';

        switch ($payment_type) {
            case "PR":
                $cod_trans .= "PR-";
                break;
            default:
        }

        $cod_trans .= $order_id;

        return substr($cod_trans . "-" . time(), 0, 30);
    }

    private static function is_recurring($cod_trans)
    {
        return substr($cod_trans, 0, 2) == "PR";
    }

    public function validate_return_mac($request_parameters)
    {
        $mac = sha1('codTrans=' . $request_parameters['codTrans'] .
            'esito=' . $request_parameters['esito'] .
            'importo=' . $request_parameters['importo'] .
            'divisa=' . $request_parameters['divisa'] .
            'data=' . $request_parameters['data'] .
            'orario=' . $request_parameters['orario'] .
            'codAut=' . $request_parameters['codAut'] .
            $this->nexi_xpay_mac);
        if ($mac == $request_parameters["mac"]) {
            return true;
        }

        Log::actionWarning(__FUNCTION__ . ": error: " . $request_parameters["mac"] . " != " . $mac);

        return false;
    }

    public function recurring_payment($num_contratto, $scadenza_pan, $amount_to_charge, $currency, $order)
    {
        Log::actionInfo(__FUNCTION__ . ": begin num_contratto = " . $num_contratto . " for " . $amount_to_charge . " " . $currency . " order " . $order->get_id());
        $newCodTrans = $this->get_cod_trans($order->get_id(), "PR");

        $importo = WC_Nexi_Helper::mul_bcmul($amount_to_charge, 100, 0); // 5000 = 50,00 EURO (NB: the amount HAVE to be in cents format, so 50,00 euro becomes 5000  )
        $divisa = array(
            'EUR' => '978',
            'CZK' => '203',
            'PLN' => '985',
            'AUD' => '036',
            'NZD' => '554'
            )[$currency];
        $timeStamp = (time()) * 1000;

        // MAC calculation
        $mac = sha1('apiKey=' . $this->nexi_xpay_recurring_alias . 'numeroContratto=' . $num_contratto . 'codiceTransazione=' . $newCodTrans . 'importo=' . $importo . "divisa=" . $divisa . "scadenza=" . $scadenza_pan . "timeStamp=" . $timeStamp . $this->nexi_xpay_recurring_mac);

        $payload = array(
            'apiKey' => $this->nexi_xpay_recurring_alias,
            'numeroContratto' => $num_contratto,
            'codiceTransazione' => $newCodTrans,
            'importo' => $importo,
            'divisa' => $divisa,
            'scadenza' => $scadenza_pan,
            'codiceGruppo' => $this->nexi_xpay_group,
            'timeStamp' => $timeStamp,
            'mac' => $mac,
            'parametriAggiuntivi' => array(
                'mail' => $order->get_billing_email(),
                'nome' => $order->get_billing_first_name(),
                'cognome' => $order->get_billing_last_name(),
                'Note1' => 'woocommerce',
                'Note2' => WC_WOOCOMMERCE_GATEWAY_NEXI_XPAY_WOOCOMMERCE_VERSION,
                'Note3' => WC_GATEWAY_XPAY_VERSION,
                'TCONTAB' => $this->nexi_xpay_accounting
            )
        );

        $payment_data = $this->exec_curl_post_json("ecomm/api/recurring/pagamentoRicorrente", $payload);

        if ($payment_data['esito'] != 'OK') {
            Log::actionWarning(__FUNCTION__ . ": remote error: " . $payment_data['errore']['messaggio']);
            throw new \Exception(__($payment_data['errore']['messaggio'], 'woocommerce-gateway-nexi-xpay'));
        }

        // MAC calculation with the repsonse params
        $macCalculated = sha1('esito=' . $payment_data['esito'] . 'idOperazione=' .
            $payment_data['idOperazione'] . 'timeStamp=' .
            $payment_data['timeStamp'] . $this->nexi_xpay_recurring_mac);

        if ($macCalculated != $payment_data['mac']) {
            Log::actionWarning(__FUNCTION__ . ": mac error: " . $payment_data["mac"] . " != " . $mac);
            throw new \Exception(__('Error in the calculation of the return MAC parameter', 'woocommerce-gateway-nexi-xpay'));
        }

        return array($this->nexi_xpay_recurring_alias, $newCodTrans);
    }

    public function refund($cod_trans, $amount, $currency)
    {
        $apiKey = $this->nexi_xpay_alias;
        $chiaveSegreta = $this->nexi_xpay_mac;
        $importo = WC_Nexi_Helper::mul_bcmul($amount, 100, 0);
        $divisa = array(
            'EUR' => '978',
            'CZK' => '203',
            'PLN' => '985',
            'AUD' => '036',
            'NZD' => '554'
            )[$currency];
        $timeStamp = (time()) * 1000;

        //  MAC calculation
        $mac = sha1('apiKey=' . $apiKey . 'codiceTransazione=' . $cod_trans . 'divisa=' . $divisa . 'importo=' . $importo . 'timeStamp=' . $timeStamp . $chiaveSegreta);

        // Params
        $payload = array(
            // Mandatory
            'apiKey' => $apiKey,
            'codiceTransazione' => $cod_trans,
            'importo' => $importo,
            'divisa' => $divisa,
            'timeStamp' => $timeStamp,
            'mac' => $mac,
        );

        $operation_info = $this->exec_curl_post_json("ecomm/api/bo/storna", $payload);

        $MACrisposta = sha1('esito=' . $operation_info['esito'] . 'idOperazione=' . $operation_info['idOperazione'] . 'timeStamp=' . $operation_info['timeStamp'] . $chiaveSegreta);

        // Check on the response MAC
        if ($operation_info['mac'] != $MACrisposta) {
            Log::actionWarning(__FUNCTION__ . ": mac error: " . $operation_info["mac"] . " != " . $MACrisposta);
            throw new \Exception(__('Error in the calculation of the return MAC parameter', 'woocommerce-gateway-nexi-xpay'));
        }

        // Check on the outcome of the operation
        if ($operation_info['esito'] != 'OK') {
            Log::actionWarning(__FUNCTION__ . ": remote error: " . $operation_info['errore']['messaggio']);
            throw new \Exception(__($operation_info['errore']['messaggio'], 'woocommerce-gateway-nexi-xpay'));
        }

        return true;
    }

    public function order_detail($cod_trans)
    {
        $apiKey = $this->nexi_xpay_alias;
        $chiaveSegreta = $this->nexi_xpay_mac;

        if (self::is_recurring($cod_trans)) {
            $apiKey = $this->nexi_xpay_recurring_alias;
            $chiaveSegreta = $this->nexi_xpay_recurring_mac;
        }

        $timeStamp = (time()) * 1000;

        //  MAC calculation
        $mac = sha1('apiKey=' . $apiKey . 'codiceTransazione=' . $cod_trans . 'timeStamp=' . $timeStamp . $chiaveSegreta);

        // Params
        $payload = array(
            // Mandatory
            'apiKey' => $apiKey,
            'codiceTransazione' => $cod_trans,
            'timeStamp' => $timeStamp,
            'mac' => $mac
        );

        $operation_info = $this->exec_curl_post_json("ecomm/api/bo/situazioneOrdine", $payload);

        $MACrisposta = sha1('esito=' . $operation_info['esito'] . 'idOperazione=' .
            $operation_info['idOperazione'] . 'timeStamp=' .
            $operation_info['timeStamp'] . $chiaveSegreta);

        // Check on the response MAC
        if ($operation_info['mac'] != $MACrisposta) {
            Log::actionWarning(__FUNCTION__ . ": mac error: " . $operation_info["mac"] . " != " . $MACrisposta);
            throw new \Exception(__('Error in the calculation of the return MAC parameter', 'woocommerce-gateway-nexi-xpay'));
        }

        // Check on the outcome of the operation
        if ($operation_info['esito'] != 'OK') {
            Log::actionWarning(__FUNCTION__ . ": remote error: " . $operation_info['errore']['messaggio']);
            throw new \Exception(__($operation_info['errore']['messaggio'], 'woocommerce-gateway-nexi-xpay'));
        }

        return $operation_info["report"][0];
    }

    public function account($cod_trans, $amount, $currency)
    {
        $apiKey = $this->nexi_xpay_alias;
        $chiaveSegreta = $this->nexi_xpay_mac;

        if (self::is_recurring($cod_trans)) {
            $apiKey = $this->nexi_xpay_recurring_alias;
            $chiaveSegreta = $this->nexi_xpay_recurring_mac;
        }

        $divisa = array(
            'EUR' => '978',
            'CZK' => '203',
            'PLN' => '985',
            'AUD' => '036',
            'NZD' => '554'
            )[$currency];

        $timeStamp = (time()) * 1000;

        //  MAC calculation
        $mac = sha1('apiKey=' . $apiKey . 'codiceTransazione=' . $cod_trans . 'divisa=' . $divisa . 'importo=' . $amount . 'timeStamp=' . $timeStamp . $chiaveSegreta);

        // Params
        $payload = array(
            // Mandatory
            'apiKey' => $apiKey,
            'codiceTransazione' => $cod_trans,
            'importo' => $amount,
            'divisa' => $divisa,
            'timeStamp' => $timeStamp,
            'mac' => $mac,
        );

        $operation_info = $this->exec_curl_post_json("ecomm/api/bo/contabilizza", $payload);

        $MACrisposta = sha1('esito=' . $operation_info['esito'] . 'idOperazione=' . $operation_info['idOperazione'] . 'timeStamp=' . $operation_info['timeStamp'] . $chiaveSegreta);

        // Check on the response MAC
        if ($operation_info['mac'] != $MACrisposta) {
            Log::actionWarning(__FUNCTION__ . ": mac error: " . $operation_info["mac"] . " != " . $MACrisposta);
            throw new \Exception(__('Error in the calculation of the return MAC parameter', 'woocommerce-gateway-nexi-xpay'));
        }

        // Check on the outcome of the operation
        if ($operation_info['esito'] != 'OK') {
            Log::actionWarning(__FUNCTION__ . ": remote error: " . $operation_info['errore']['messaggio']);
            throw new \Exception(__($operation_info['errore']['messaggio'], 'woocommerce-gateway-nexi-xpay'));
        }

        return true;
    }

    private function exec_curl_post_json($path, $payload)
    {
        $connection = curl_init();

        if (!$connection) {
            throw new \Exception(__('Can\'t connect!', 'woocommerce-gateway-nexi-xpay'));
        }

        curl_setopt_array($connection, array(
            CURLOPT_URL => $this->base_url . $path,
            CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => 1,
            CURLINFO_HEADER_OUT => true
        ));

        $response = curl_exec($connection);
        if ($response == false) {
            throw new \Exception(sprintf(__('CURL exec error: %s', 'woocommerce-gateway-nexi-xpay'), curl_error($connection)));
        }
        curl_close($connection);

        $payment_data = json_decode($response, true);

        if (!(is_array($payment_data) && json_last_error() === JSON_ERROR_NONE)) {
            throw new \Exception(__('JSON error', 'woocommerce-gateway-nexi-xpay'));
        }

        return $payment_data;
    }

    public function getUrlXpayBuildJS()
    {
        if (strlen($this->nexi_xpay_alias) > 0 && strlen($this->base_url) > 0) {
            return $this->base_url . 'ecomm/XPayBuild/js?alias=' . $this->nexi_xpay_alias;
        }

        return "javascript:alert('missing config')";
    }

    public function pagaNonceCreazioneContratto($codiceTransazione, $importo, $nonce, $divisa, $numeroContratto, $order)
    {
        $timeStamp = (time()) * 1000;

        // Calcolare il mac
        $macString = 'apiKey=' . $this->nexi_xpay_alias
            . 'codiceTransazione=' . $codiceTransazione
            . 'importo=' . $importo
            . 'divisa=' . $divisa
            . 'xpayNonce=' . $nonce
            . 'timeStamp=' . $timeStamp . $this->nexi_xpay_mac;
        $mac = sha1($macString);

        $pay_load = array(
            'apiKey' => $this->nexi_xpay_alias,
            'codiceTransazione' => $codiceTransazione,
            'importo' => $importo,
            'divisa' => $divisa,
            'xpayNonce' => $nonce,
            'timeStamp' => $timeStamp,
            'numeroContratto' => $numeroContratto,
            'codiceGruppo' => $this->nexi_xpay_group,
            'mac' => $mac,
            'parametriAggiuntivi' => array(
                'mail' => $order->get_billing_email(),
                'nome' => $order->get_billing_first_name(),
                'cognome' => $order->get_billing_last_name(),
                'TCONTAB' => $this->nexi_xpay_accounting,
                'Note1' => 'woocommerce',
                'Note2' => WC_WOOCOMMERCE_GATEWAY_NEXI_XPAY_WOOCOMMERCE_VERSION,
                'Note3' => WC_GATEWAY_XPAY_VERSION
            )
        );

        $operation_info = $this->exec_curl_post_json("ecomm/api/hostedPayments/pagaNonceCreazioneContratto", $pay_load);

        // Calcolo MAC di risposta
        $MACrisposta = sha1('esito=' . $operation_info['esito']
            . 'idOperazione=' . $operation_info['idOperazione']
            . 'timeStamp=' . $operation_info['timeStamp'] . $this->nexi_xpay_mac);

        // Check on the response MAC
        if ($operation_info['mac'] != $MACrisposta) {
            Log::actionWarning(__FUNCTION__ . ": mac error: " . $operation_info["mac"] . " != " . $MACrisposta);
            throw new \Exception(__('Error in the calculation of the return MAC parameter', 'woocommerce-gateway-nexi-xpay'), $operation_info['errore']['codice']);
        }

        // Check on the outcome of the operation
        if ($operation_info['esito'] != 'OK') {
            Log::actionWarning(__FUNCTION__ . ": remote error: " . $operation_info['errore']['messaggio']);
            throw new \Exception(__($operation_info['errore']['messaggio'], 'woocommerce-gateway-nexi-xpay'), $operation_info['errore']['codice']);
        }

        return true;
    }

    public function get_build_alias()
    {
        return $this->nexi_xpay_alias;
    }

    public function pagaNonce(bool $standalonePayment, $codiceTransazione, $importo, $nonce, $divisa, $order)
    {
        $timeStamp = (time()) * 1000;

        // Calcolo MAC
        $macString = 'apiKey=' . $this->nexi_xpay_alias
            . 'codiceTransazione=' . $codiceTransazione
            . 'importo=' . $importo
            . 'divisa=' . $divisa
            . 'xpayNonce=' . $nonce
            . 'timeStamp=' . $timeStamp . $this->nexi_xpay_mac;
        $mac = sha1($macString);

        $pay_load = array(
            'apiKey' => $this->nexi_xpay_alias,
            'codiceTransazione' => $codiceTransazione,
            'importo' => $importo,
            'divisa' => $divisa,
            'xpayNonce' => $nonce,
            'timeStamp' => $timeStamp,
            'mac' => $mac,
            'parametriAggiuntivi' => array(
                'mail' => $order->get_billing_email(),
                'nome' => $order->get_billing_first_name(),
                'cognome' => $order->get_billing_last_name(),
                'TCONTAB' => $this->nexi_xpay_accounting,
                'Note1' => 'woocommerce',
                'Note2' => WC_WOOCOMMERCE_GATEWAY_NEXI_XPAY_WOOCOMMERCE_VERSION,
                'Note3' => WC_GATEWAY_XPAY_VERSION
            )
        );

        $uri = null;
        if ($standalonePayment) {
            $uri = "ecomm/api/hostedPayments/pagaNonce";
        } else {
            $uri = "ecomm/api/recurring/pagamentoRicorrente3DS";
        }

        $operation_info = $this->exec_curl_post_json($uri, $pay_load);

        // Calcolo MAC di risposta
        $MACrisposta = sha1('esito=' . $operation_info['esito']
            . 'idOperazione=' . $operation_info['idOperazione']
            . 'timeStamp=' . $operation_info['timeStamp'] . $this->nexi_xpay_mac);

        // Check on the response MAC
        if ($operation_info['mac'] != $MACrisposta) {
            Log::actionWarning(__FUNCTION__ . ": mac error: " . $operation_info["mac"] . " != " . $MACrisposta);
            throw new \Exception(__('Error in the calculation of the return MAC parameter', 'woocommerce-gateway-nexi-xpay'), $operation_info['errore']['codice']);
        }

        // Check on the outcome of the operation
        if ($operation_info['esito'] != 'OK') {
            Log::actionWarning(__FUNCTION__ . ": remote error: " . $operation_info['errore']['messaggio']);
            throw new \Exception(__($operation_info['errore']['messaggio'], 'woocommerce-gateway-nexi-xpay'), $operation_info['errore']['codice']);
        }

        return true;
    }

    public function calculate_mac_for_build_oneclick($codTransCvv, $divisa, $importoCvv)
    {
        $divisa = array(
            'EUR' => '978',
            'CZK' => '203',
            'PLN' => '985',
            'AUD' => '036',
            'NZD' => '554'
            )[$divisa];

        return sha1('codTrans=' . $codTransCvv
            . 'divisa=' . $divisa
            . 'importo=' . $importoCvv
            . $this->nexi_xpay_mac);
    }

}
