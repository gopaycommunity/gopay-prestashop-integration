<?php
/**
 * Controller responsible for order validation,
 * payment creation and cheking
 * payment from GoPay
 *
 * @author    GoPay
 * @copyright 2022 GoPay
 * @license   https://www.gnu.org/licenses/gpl-2.0.html  GPLv2 or later
 *
 * @see       https://www.gopay.com/
 * @since     1.0.0
 */
class PrestaShopGoPayPaymentModuleFrontController extends ModuleFrontController
{
    /**
     * Validate data, create payment
     * and redirect to GoPay
     *
     * @since  1.0.0
     */
    public function initContent()
    {
        parent::initContent();

        $cart = $this->context->cart;
        $customer = new Customer($cart->id_customer);
        $currency = new Currency($cart->id_currency);

        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 ||
            $cart->id_address_invoice == 0 || !$this->module->active) {
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

        $this->module->validateOrder(
            (int) $cart->id,
            (int) Configuration::get('GOPAY_OS_WAITING'),
            (float) $cart->getOrderTotal(true, Cart::BOTH),
            $this->module->getOrderPaymentName((int) $this->context->language->id),
            null,
            null,
            (int) $currency->id,
            false,
            $customer->secure_key
        );

        if (in_array('getByCartId', get_class_methods('Order'))) {
            $order = Order::getByCartId($cart->id);
        } elseif (in_array('getOrderByCartId', get_class_methods('Order'))) {
            $order_id = Order::getOrderByCartId($cart->id);
            $order = new Order($order_id);
        }

        $dup = $this->context->cart->duplicate();
        $this->context->cart->delete();
        $this->context->cookie->id_cart = $dup['cart']->id;

        $url = $this->context->link->getModuleLink(
            'prestashopgopay',
            'payment',
            ['payment-method' => 'GoPay_gateway', 'order-id' => $order->id]
        );
        $response = PrestashopGopayApi::create_payment($order, array_key_exists(
            'gopay_payment_method',
            $_REQUEST
        ) ? $_REQUEST['gopay_payment_method'] : '', $this->module->id, $url);

        // Save log.
        $log = [
            'order_id' => $order->id,
            'transaction_id' => 200 == $response->statusCode ? $response->json['id'] : '0',
            'message' => 200 == $response->statusCode ? 'Payment created' :
                'Process payment error',
            'log_level' => 200 == $response->statusCode ? 'INFO' : 'ERROR',
            'log' => $response,
        ];
        PrestashopGopayLog::insert_log($log);

        if ($response->statusCode != 200) {
            Tools::redirect('index.php?controller=order-confirmation&id_cart=' . (int) $cart->id . '&id_module=' . (int)
                $this->module->id . '&id_order=' . $this->module->currentOrder . '&key=' . $customer->secure_key);
        } else {
            if (!Configuration::get('PRESTASHOPGOPAY_INLINE')) {
                Tools::redirect($response->json['gw_url']);
            }

            if (Configuration::get('PRESTASHOPGOPAY_TEST')) {
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
        if (array_key_exists('id', $_REQUEST) &&
            array_key_exists('payment-method', $_REQUEST) &&
            $_REQUEST['payment-method'] == 'GoPay_gateway') {
            PrestashopGopayApi::check_payment_status($_REQUEST['order-id'], $_REQUEST['id']);
        }
    }
}
