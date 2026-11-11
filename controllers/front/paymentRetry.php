<?php
/**
 * Controller responsible for payment retry
 *
 * @author    GoPay
 * @copyright 2022 GoPay
 * @license   https://www.gnu.org/licenses/gpl-2.0.html  GPLv2 or later
 *
 * @see       https://www.gopay.com/
 * @since     1.0.0
 */
class PrestaShopGoPayPaymentRetryModuleFrontController extends ModuleFrontController
{
    /**
     * Validate data, create payment
     * and redirect to GoPay
     *
     * @since  1.0.0
     */
    public function initContent()
    {
        $order = new Order((int) Tools::getValue('order_id'));

        // Only the order's owner may retry: the form carries the order's secure key (see hookDisplayOrderDetail).
        if (!Validate::isLoadedObject($order) || $order->module !== 'prestashopgopay' || $order->hasBeenPaid() ||
            !hash_equals((string) $order->secure_key, (string) Tools::getValue('key'))) {
            Tools::redirect('index.php?controller=history');
        }

        $customer = new Customer($order->id_customer);

        if ($order->id_customer == 0 || $order->id_address_delivery == 0 ||
            $order->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        if (!($this->module instanceof PrestaShopGoPay || !Validate::isLoadedObject($customer))) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'prestashopgopay') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            exit($this->module->l('PrestaShop GoPay gateway is not available.', 'validation'));
        }

        $default_payment_instrument = '';
        if (Configuration::get('PRESTASHOPGOPAY_PAYMENT_RETRY')) {
            $transaction_id = Db::getInstance()->executeS(sprintf(
                'SELECT transaction_id FROM %s%s WHERE order_id = %s ORDER BY id DESC LIMIT 1',
                _DB_PREFIX_,
                'gopay_log',
                $order->id
            ))[0]['transaction_id'];

            $status = PrestashopGopayApi::get_status($transaction_id);
            if ($status->statusCode == 200 && array_key_exists('default_payment_instrument', $status->json['payer'])) {
                $default_payment_instrument = $status->json['payer']['default_payment_instrument'];
            }
        }

        $params = ['payment-method' => 'GoPay_gateway', 'order-id' => $order->id];
        $return_url = $this->context->link->getModuleLink('prestashopgopay', 'paymentRetry', $params);
        $notification_url = $this->context->link->getModuleLink('prestashopgopay', 'paymentRetry',
            $params + ['notification' => 1]);
        $response = PrestashopGopayApi::create_payment($order, array_key_exists('gopay_payment_method',
            $_REQUEST) ? $_REQUEST['gopay_payment_method'] : $default_payment_instrument, $this->module->id,
            $return_url, $notification_url);

        // Save log.
        $log = [
            'order_id' => $order->id,
            'transaction_id' => 200 == $response->statusCode ? $response->json['id'] : '0',
            'message' => 200 == $response->statusCode ? 'Checking payment status' :
                'Error checking payment status',
            'log_level' => 200 == $response->statusCode ? 'INFO' : 'ERROR',
            'log' => $response,
        ];
        PrestashopGopayLog::insert_log($log);

        if ($response->statusCode != 200) {
            Tools::redirect('index.php?controller=order-detail&id_order=' . $order->id);
        } else {
            if (Configuration::get('PRESTASHOPGOPAY_TEST') == 'yes') {
                $embed = 'https://gw.sandbox.gopay.com/gp-gw/js/embed.js';
            } else {
                $embed = 'https://gate.gopay.cz/gp-gw/js/embed.js';
            }
            $this->context->smarty->assign([
                'gopay_url' => $response->json['gw_url'],
                'embed' => $embed,
            ]);

            if (version_compare(_PS_VERSION_, '1.7', '>')) {
                $this->setTemplate('module:prestashopgopay/views/templates/front/payment_form.tpl');
            } else {
                $this->setTemplate('payment_form.tpl');
            }
        }
    }

    /**
     * Validate data, create payment
     * and redirect to GoPay
     *
     * @since  1.0.0
     */
    public function postProcess()
    {
        if (Tools::getValue('payment-method') === 'GoPay_gateway' && Tools::getIsset('id')) {
            PrestashopGopayApi::check_payment_status(
                (int) Tools::getValue('order-id'),
                (string) Tools::getValue('id'),
                (bool) Tools::getValue('notification')
            );
        }
    }
}
