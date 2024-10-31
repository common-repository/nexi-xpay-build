<?php

/**
 * Copyright (c) 2019 Nexi Payments S.p.A.
 *
 * @author      iPlusService S.r.l.
 * @category    Payment Module
 * @package     Nexi XPay
 * @version     7.0.2
 * @copyright   Copyright (c) 2019 Nexi Payments S.p.A. (https://ecommerce.nexi.it)
 * @license     GNU General Public License v3.0
 */

namespace Nexi;

class WC_NPG_Token extends \WC_Payment_Token_CC
{

    private static $gateway_id = 'xpay';

    public static function save_token(string $brand, string $pan, string $scadenza_pan, string $num_contratto, int $user_id)
    {
        $last4 = substr($pan, -4);
        $exp_month = substr($scadenza_pan, -2);
        $exp_year = substr($scadenza_pan, 0, 4);

        // Check if token exists
        if (!static::is_token_set($brand, $last4, $exp_month, $exp_year, $user_id)) {
            $newTokenObject = new WC_NPG_Token();
            $newTokenObject->set_token($num_contratto);
            $newTokenObject->set_gateway_id(static::$gateway_id);
            $newTokenObject->set_card_type($brand);
            $newTokenObject->set_last4($last4);
            $newTokenObject->set_expiry_month($exp_month);
            $newTokenObject->set_expiry_year($exp_year);
            $newTokenObject->set_user_id($user_id);
            $newTokenObject->save();
        }
    }

    private static function is_token_set($brand, $last4, $exp_month, $exp_year, $user_id)
    {
        $tokens = \WC_Payment_Tokens::get_customer_tokens($user_id, static::$gateway_id);

        foreach ($tokens as $token) {
            if (
                $token->get_card_type() == $brand && $token->get_last4() == $last4 &&
                $token->get_expiry_month() == $exp_month && $token->get_expiry_year() == $exp_year
            ) {
                return true;
            }
        }

        return false;
    }

    /**
     *
     * @param int $token_id
     * @param int $user_id
     * @return Object|boolean
     */
    public static function get_token_nexi($token_id, $user_id = null)
    {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        $token = \WC_Payment_Tokens::get($token_id);

        if ($token != false) {
            // Token user ID does not match the current user... bail out of payment processing.
            if ($token->get_user_id() === $user_id) {
                return $token;
            }
        }

        return false;
    }
}
