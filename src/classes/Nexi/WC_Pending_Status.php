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

class WC_Pending_Status
{

    public static function addNexiPendingPaymentOrderStatus()
    {
        register_post_status('wc-pd-pending-status', array(
            'label' => __('Nexi: pending payment', 'woocommerce-gateway-nexi-xpay'),
            'public' => true,
            'exclude_from_search' => false,
            'show_in_admin_all_list' => true,
            'show_in_admin_status_list' => true,
            'label_count' => _n_noop('Nexi: pending payment (%s)', 'Nexi: pending payment (%s)', 'woocommerce-gateway-nexi-xpay')
        ));
    }

    public static function wcOrderStatusesFilter($orderStatuses)
    {
        $orderStatuses['wc-pd-pending-status'] = __('Nexi: pending payment', 'woocommerce-gateway-nexi-xpay');

        return $orderStatuses;
    }

    public static function validOrderStatusesForPaymentCompleteFilter($orderStatuses)
    {
        $orderStatuses[] = 'pd-pending-status';

        return $orderStatuses;
    }

}
