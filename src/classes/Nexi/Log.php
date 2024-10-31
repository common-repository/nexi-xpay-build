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

class Log
{

    private static function getContext()
    {
        return array(
            'source' => 'woocommerce-gateway-nexi-xpay'
        );
    }

    public static function actionInfo($message)
    {
        $logger = wc_get_logger();
        $logger->log("info", $message, self::getContext());
    }

    public static function actionWarning($message)
    {
        $logger = wc_get_logger();
        $logger->log("warning", $message, self::getContext());
    }

}
