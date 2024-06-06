<?php
/**
 * PrestaShop GoPay gateway log
 * Insert log into database
 *
 * @author    GoPay
 * @copyright 2022 GoPay
 * @license   https://www.gnu.org/licenses/gpl-2.0.html  GPLv2 or later
 *
 * @see       https://www.gopay.com/
 * @since     1.0.0
 */
class PrestashopGopayLog
{
    /**
     * Insert log into the database
     *
     * @param array $log log text
     *
     * @since  1.0.0
     */
    public static function insert_log(array $log)
    {
        $encodedLog = $log['log'];

        if (is_string($log['log']) && json_decode($log['log']) === null && json_last_error() !== JSON_ERROR_NONE) {
            $encodedLog = json_encode($log['log']);
        }

        $table_name = 'gopay_log';
        $data = [
            'order_id' => $log['order_id'],
            'transaction_id' => $log['transaction_id'],
            'message' => $log['message'],
            'created_at' => gmdate('Y-m-d H:i:s'),
            'log_level' => $log['log_level'],
            'log' => $encodedLog,
        ];
        $where = "`order_id` = '" . $log['order_id'] .
            "' AND `transaction_id` = '" . $log['transaction_id'] .
            "' AND `message` = '" . $log['message'] . "'";

        $response = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow(
            'SELECT * FROM `' . _DB_PREFIX_ . $table_name . '` WHERE ' . $where);
        if (false === $response) {
            Db::getInstance()->insert($table_name, $data);
        } else {
            Db::getInstance()->update($table_name, $data, $where);
        }
    }
}
