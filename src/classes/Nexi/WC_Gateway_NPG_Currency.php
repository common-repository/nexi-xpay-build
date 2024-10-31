<?php

/**
 * Copyright (c) 2019 Nexi Payments S.p.A.
 *
 * @author      iPlusService S.r.l.
 * @category    Payment Module
 * @package     Nexi XPay
 * @version     7.1.1
 * @copyright   Copyright (c) 2019 Nexi Payments S.p.A. (https://ecommerce.nexi.it)
 * @license     GNU General Public License v3.0
 */


namespace Nexi;

class WC_Gateway_NPG_Currency
{
    /**
     * currency map to the number of digits after the decimal separator
     *
     * @var array
     */
    private static $npgCurrenciesList = [
        "AED" => 2,
        "AOA" => 2,
        "ARS" => 2,
        "AUD" => 2,
        "AZN" => 2,
        "BAM" => 2,
        "BGN" => 2,
        "BHD" => 3,
        "BRL" => 2,
        "BYN" => 2,
        "BYR" => 0,
        "CAD" => 2,
        "CHF" => 2,
        "CLP" => 0,
        "CNY" => 2,
        "COP" => 2,
        "CZK" => 2,
        "DKK" => 2,
        "EGP" => 2,
        "EUR" => 2,
        "GBP" => 2,
        "GIP" => 2,
        "HKD" => 2,
        "HRK" => 2,
        "HUF" => 2,
        "INR" => 2,
        "ISK" => 0,
        "JOD" => 3,
        "JPY" => 0,
        "KRW" => 0,
        "KWD" => 3,
        "KZT" => 2,
        "LTL" => 2,
        "LVL" => 2,
        "MKD" => 2,
        "MXN" => 2,
        "MYR" => 2,
        "NGN" => 2,
        "NOK" => 2,
        "PHP" => 2,
        "PLN" => 2,
        "QAR" => 2,
        "RON" => 2,
        "RSD" => 2,
        "RUB" => 2,
        "SAR" => 2,
        "SEK" => 2,
        "SGD" => 2,
        "THB" => 2,
        "TRY" => 2,
        "TWD" => 2,
        "UAH" => 2,
        "USD" => 2,
        "VEF" => 2,
        "VND" => 0,
        "ZAR" => 2
    ];

    /**
     * returns list of supported currencies
     *
     * @return array
     */
    public static function get_npg_supported_currency_list()
    {
        return array_keys(static::$npgCurrenciesList);
    }

    /**
     * returns the multiplier to convert the amount to currency min unit
     *
     * @param string $currency
     * @return int
     */
    public static function get_currency_min_unit_multiplier($currency)
    {
        if (!in_array($currency, static::get_npg_supported_currency_list())) {
            throw new \Exception("Currency not supported: " . $currency);
        }

        $decimals = static::$npgCurrenciesList[$currency];

        $mul = pow(10, $decimals);

        if ($mul === false) {
            throw new \Exception("Error calculating min unit multiplier, currency: " . $currency . " - decimals: " . $decimals);
        }

        return $mul;
    }

    /**
     * converts the amount to the minumum currency unit
     *
     * @param int|float|string $amount
     * @param string $currency
     * @return string
     */
    public static function calculate_amount_to_min_unit($amount, $currency)
    {
        return WC_Nexi_Helper::mul_bcmul((string) $amount, static::get_currency_min_unit_multiplier($currency), 0);
    }

    /**
     * formats amount to be displayed
     *
     * @param string $amount    to minimum unit
     * @param string $currency
     * @param string $decimalSep
     * @param string $thousandsSep
     * @return string
     */
    public static function format_npg_amount($amount, $currency, $decimalSep = ',', $thousandsSep = '.')
    {
        return number_format(
            WC_Nexi_Helper::div_bcdiv($amount, static::get_currency_min_unit_multiplier($currency), static::$npgCurrenciesList[$currency]),
            static::$npgCurrenciesList[$currency],
            $decimalSep,
            $thousandsSep
        );
    }
}
