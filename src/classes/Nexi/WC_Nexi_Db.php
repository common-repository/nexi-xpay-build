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

class WC_Nexi_Db
{

    public static function run_updates()
    {
        $oldVersion = get_option('nexi_xpay_' . WC_GATEWAY_NEXI_PLUGIN_VARIANT . '_db_version', '1.0');

        $newVersion = '1.1';

        if (!(version_compare($oldVersion, $newVersion) < 0)) {
            return;
        }

        \Nexi\WC_Gateway_NPG_Lock_Handler::create_lock_table();

        update_option('nexi_xpay_' . WC_GATEWAY_NEXI_PLUGIN_VARIANT . '_db_version', $newVersion);
    }

}
