<?php
/**
 * PrestaShop GoPay API
 * Connect to the GoPay API using the GoPay's PHP SDK
 *
 * @author    GoPay
 * @copyright 2022 GoPay
 * @license   https://www.gnu.org/licenses/gpl-2.0.html  GPLv2 or later
 *
 * @see       https://www.gopay.com/
 * @since     1.0.0
 */

use GoPay\Http\Response;
use GoPay\Payments;

class PrestashopGopayApi
{
    /**
     * Constructor for the plugin GoPay api
     *
     * @since 1.0.0
     */
    public function __construct()
    {
    }

    /**
     * GoPay authentication
     *
     * @return Payments object
     *
     * @since  1.0.0
     */
    public static function auth_gopay(): Payments
    {
        static $urls = [
            true => 'https://gate.gopay.cz/',
            false => 'https://gw.sandbox.gopay.com/'
        ];

        return GoPay\payments([
            'goid' => Configuration::get('PRESTASHOPGOPAY_GOID'),
            'clientId' => Configuration::get('PRESTASHOPGOPAY_CLIENT_ID'),
            'clientSecret' => Configuration::get('PRESTASHOPGOPAY_CLIENT_SECRET'),
            'isProductionMode' => !Configuration::get('PRESTASHOPGOPAY_TEST'),
            'scope' => GoPay\Definition\TokenScope::ALL,
            'language' => Configuration::get('PRESTASHOPGOPAY_DEFAULT_LANGUAGE'),
            'timeout' => 30,
            'gatewayUrl' => $urls[!Configuration::get('PRESTASHOPGOPAY_TEST')],
        ]);
    }

    /**
     * Get items info
     *
     * @param object $order order detail
     *
     * @return array
     *
     * @since 1.0.0
     */
    private static function get_items($cartProducts): array
    {
        $items = [];
        foreach ($cartProducts as $item) {
            $items[] = [
                'type' => 'ITEM',
                'name' => $item['product_name'],
                'product_url' => Context::getContext()->link->getProductLink($item['id_product']),
                'amount' => $item['total_wt'] * 100,
                'count' => $item['product_quantity'],
                'vat_rate' => $item['tax_rate'],
            ];
        }

        return $items;
    }

    /**
     * GoPay create payment
     *
     * @param Order $order order
     * @param string $gopay_payment_method order detail
     * @param string $moduleId module id
     * @param string $return_url URL the customer returns to from the gateway
     * @param string $notification_url URL GoPay calls server-to-server on payment state change
     *
     * @return Response
     *
     * @since 1.0.0
     */
    public static function create_payment(
        Order $order,
        string $gopay_payment_method,
        string $moduleId,
        string $return_url,
        string $notification_url
    ): Response {
        $prestashopGopayOptions = new PrestashopGopayOptions();

        $gopay = self::auth_gopay();
        $products = $order->getProducts();
        $customer = new Customer($order->id_customer);
        $address = new Address($order->id_address_invoice);
        $country = new Country($address->id_country);
        $currency = new Currency($order->id_currency);

        $default_swift = '';
        if (array_key_exists($gopay_payment_method, $prestashopGopayOptions->supported_banks())) {
            $default_swift = $gopay_payment_method;
            $gopay_payment_method = 'BANK_ACCOUNT';
        }

        $default_payment_instrument = '';
        if (!empty($gopay_payment_method)) {
            $default_payment_instrument = $gopay_payment_method;
        }

        $items = self::get_items($products);

        $callback = [
            'return_url' => $return_url,
            'notification_url' => $notification_url,
        ];

        $contact = [
            'first_name' => $customer->firstname,
            'last_name' => $customer->lastname,
            'email' => $customer->email,
            'phone_number' => $address->phone,
            'city' => $address->city,
            'street' => $address->address1,
            'postal_code' => $address->postcode,
            'country_code' => PrestashopGopayOptions::iso2_to_iso3()[$country->iso_code],
        ];

        if (!empty($default_payment_instrument)) {
            $payer = [
                'default_payment_instrument' => $default_payment_instrument,
                'allowed_payment_instruments' => json_decode(Configuration::get('PRESTASHOPGOPAY_PAYMENT_METHODS')
                ) ?? [],
//				'allowed_swifts'              => json_decode( Configuration::get( 'PRESTASHOPGOPAY_BANKS' )
//					) ?? array(),
                'contact' => $contact,
            ];
            if (!empty($default_swift)) {
                unset($payer['allowed_swifts']);
                $payer['default_swift'] = $default_swift;
            }
        } else {
            $payer = [
                'contact' => $contact,
            ];
        }

        $module = Module::getInstanceByName('prestashopgopay');
        $version = $module->version ?? '';

        $additional_params = [
            [
                'name' => 'id_cart',
                'value' => $order->id_cart,
            ],
            [   
                'name' => 'gopay_plugin',
                'value' => 'gopay-prestashop-' . $version,
            ]
        ];

        $language = PrestashopGopayOptions::country_to_language()[$country->iso_code];
        if (!array_key_exists($language, $prestashopGopayOptions->supported_languages())) {
            $language = Configuration::get('PRESTASHOPGOPAY_DEFAULT_LANGUAGE');
        }

        $totalProducts = $order->getTotalProductsWithTaxes();
        $totalShipping = $order->total_shipping_tax_incl;
        $discountAmount = $order->total_discounts_tax_incl;

        $total = round(($totalProducts + $totalShipping - $discountAmount), 2) * 100;

        $data = [
            'payer' => $payer,
            'amount' => $total,
            'currency' => $currency->iso_code,
            'order_number' => $order->id,
            'order_description' => 'order',
            'items' => $items,
            'additional_params' => $additional_params,
            'callback' => $callback,
            'lang' => $language,
        ];

        //		if ( !empty( $end_date ) ) {
        //			$data['recurrence'] = array(
        //				'recurrence_cycle'      => 'ON_DEMAND',
        //				'recurrence_date_to'    => $end_date != 0 ? $end_date : date( 'Y-m-d', strtotime( '+5 years' ) ) );
        //		}

        $response = $gopay->createPayment($data);

        return $response;
    }

    /**
     * Empty the customer's cart after a successful payment.
     * Before redirecting to GoPay the module duplicates the cart (so the
     * customer keeps the products if the payment fails); this removes
     * that copy once the order is paid.
     *
     * @param Order $order
     */
    private static function empty_customer_cart(Order $order)
    {
        $context = Context::getContext();

        if (isset($context->cart) && Validate::isLoadedObject($context->cart)
            && (int) $context->cart->id !== (int) $order->id_cart
            && (int) $context->cart->id_customer === (int) $order->id_customer
            && !$context->cart->orderExists()) {
            $context->cart->delete();
        }

        if (isset($context->cookie) && isset($context->cookie->id_cart)) {
            unset($context->cookie->id_cart);
            $context->cookie->write();
        }
    }

    /**
     * Check payment status
     *
     * Called from the return URL (customer's browser) and from the notification URL (GoPay server).
     * The order state is changed only if the payment belongs to the order. The order confirmation
     * (with the customer's secure key) is shown only to the order's owner.
     *
     * @param int $order_id order id from the callback URL
     * @param string $transaction_id GoPay payment id from the callback URL
     * @param bool $is_notification true for the GoPay notification
     *
     * @since  1.0.0
     */
    public static function check_payment_status(int $order_id, string $transaction_id, bool $is_notification = false)
    {
        $order = new Order($order_id);
        if (!Validate::isLoadedObject($order) || $order->module !== 'prestashopgopay' || !ctype_digit($transaction_id)) {
            self::end_callback($is_notification);
        }

        $gopay = self::auth_gopay();
        $response = $gopay->getStatus($transaction_id);
        $currency = new Currency($order->id_currency);
        $valid = 200 == $response->statusCode &&
            (int) $response->json['order_number'] === (int) $order->id &&
            $response->json['currency'] === $currency->iso_code;

        // Save log.
        $log = [
            'order_id' => $order->id,
            'transaction_id' => (int) $transaction_id,
            'message' => $valid ? 'Checking payment status' : 'Error checking payment status',
            'log_level' => $valid ? 'INFO' : 'ERROR',
            'log' => $response,
        ];
        PrestashopGopayLog::insert_log($log);

        if (!$valid) {
            self::end_callback($is_notification);
        }

        $payment_error = false;
        switch ($response->json['state']) {
            case 'PAID':
                $order->setCurrentState((int) Configuration::get('PS_OS_WS_PAYMENT'));

                $order_payments = OrderPayment::getByOrderReference($order->reference);
                foreach ($order_payments as $order_payment) {
                    $order_payment->transaction_id = $transaction_id;
                    $order_payment->save();
                }

                self::empty_customer_cart($order);

                break;
            case 'PAYMENT_METHOD_CHOSEN':
            case 'AUTHORIZED':
                $order->setCurrentState((int) Configuration::get('GOPAY_OS_WAITING'));

                break;
            case 'CREATED':
            case 'TIMEOUTED':
            case 'CANCELED':
                $order->setCurrentState((int) Configuration::get('PS_OS_ERROR'));
                $payment_error = true;

                break;
            case 'REFUNDED':
                $order->setCurrentState((int) Configuration::get('PS_OS_REFUND'));

                break;
        }

        // GoPay expects only HTTP 200 for the notification.
        if ($is_notification) {
            exit;
        }

        // Show the order confirmation only to the order's owner.
        if ((int) Context::getContext()->customer->id !== (int) $order->id_customer) {
            Tools::redirect('index.php?controller=guest-tracking');
        }

        if ($payment_error) {
            Tools::redirect('order?payment-error=yes');
        }

        Tools::redirect('index.php?controller=order-confirmation&id_cart=' . (int) $order->id_cart . '&id_module=' .
            (int) PrestaShopGoPay::getInstanceByName('prestashopgopay')->id . '&id_order=' .
            $order->id . '&key=' . $order->getCustomer()->secure_key);
    }

    /**
     * End an invalid callback without changing the order
     *
     * @param bool $is_notification true for the GoPay notification
     *
     * @since  1.0.10
     */
    private static function end_callback(bool $is_notification)
    {
        if ($is_notification) {
            exit;
        }

        Tools::redirect('index.php?controller=order&step=1');
    }

    /**
     * Check payment methods and banks that
     * are enabled on GoPay account.
     *
     * @param string
     *
     * @return array
     *
     * @since  1.0.0
     */
    public static function check_enabled_on_GoPay($currency): array
    {
        $gopay = self::auth_gopay();

        $language_id = Configuration::get('PS_LANG_DEFAULT');
        $language = new Language($language_id);
        
        // Get the ISO code of language
        $language_code = $language->iso_code;

        $payment_methods = [];
        $banks = [];
        $enabledPayments = $gopay->getPaymentInstruments(Configuration::get('PRESTASHOPGOPAY_GOID'), $currency . '?lang=' . $language_code);

        if ($enabledPayments->statusCode == 200 && isset($enabledPayments->json['enabledPaymentInstruments'])) {
            // Determine if the specified language code exists in the response
            $paymentInstrument = reset($enabledPayments->json['enabledPaymentInstruments']);
            $language_code = isset($paymentInstrument['label'][$language_code]) ? $language_code : 'cs';

            foreach ($enabledPayments->json['enabledPaymentInstruments'] as $key => $paymentMethod) {
                $payment_methods[$paymentMethod['paymentInstrument']
                ] = [
                    'label' => PrestaShopGoPay::getInstanceByName(
                        'prestashopgopay')->l($paymentMethod['label'][$language_code]),
                    'image' => $paymentMethod['image']['normal'],
                ];

                if ($paymentMethod['paymentInstrument'] == 'BANK_ACCOUNT') {
                    foreach ($paymentMethod['enabledSwifts'] as $_ => $bank) {
                        $banks[$bank['swift']] = [
                            'label' => PrestaShopGoPay::getInstanceByName(
                                'prestashopgopay')->l($bank['label'][$language_code]),
                            'country' => $bank['swift'] != 'OTHERS' ? substr($bank['swift'], 4, 2) : '',
                            'image' => $bank['image']['normal'], ];
                    }
                }
            }
        }

        return [$payment_methods, $banks];
    }

    /**
     * Refund payment
     *
     * @param string $transaction_id
     * @param float $amount
     *
     * @return Response $response
     *
     * @since  1.0.0
     */
    public static function refund_payment(string $transaction_id, float $amount): Response
    {
        $gopay = self::auth_gopay();
        $response = $gopay->refundPayment($transaction_id, $amount);

        return $response;
    }

    /**
     * Get status of the transaction
     *
     * @param string $transaction_id
     *
     * @since  1.0.0
     */
    public static function get_status(string $transaction_id): Response
    {
        $gopay = self::auth_gopay();
        $response = $gopay->getStatus($transaction_id);

        return $response;
    }
}
