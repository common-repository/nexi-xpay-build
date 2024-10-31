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

class WC_Gateway_NPG_Lock_Handler
{

    private static function get_table_name()
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'nexi_' . WC_GATEWAY_NEXI_PLUGIN_VARIANT . '_order_lock';

        return $table_name;
    }

    private static function take_lock($order_id, $function)
    {
        global $wpdb;

        return $wpdb->insert(self::get_table_name(), array('id' => $order_id, 'caller' => $function), array('%d', '%s'));
    }

    public static function create_lock_table()
    {
        global $wpdb;

        $table_name = self::get_table_name();

        $charset_collate = $wpdb->get_charset_collate();

        $sql = " CREATE TABLE $table_name (id bigint(20) UNSIGNED NOT NULL, caller VARCHAR(64) NOT NULL, PRIMARY KEY (id) ) $charset_collate; ";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        dbDelta($sql);
    }

    public static function check_and_take_lock($order_id, $function)
    {
        $i = 0;

        do {
            ++$i;

            if (static::take_lock($order_id, $function) !== false) {
                return true;
            }

            sleep(1);
        } while ($i <= 30);

        Log::actionWarning('Reached max timeout for lock release');

        return false;
    }

    public static function release_lock($order_id)
    {
        global $wpdb;

        $wpdb->delete(self::get_table_name(), array('id' => $order_id), array('%d'));
    }

}
