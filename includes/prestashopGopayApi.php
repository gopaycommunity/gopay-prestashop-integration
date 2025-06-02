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
     * Decode GoPay response and add raw body if
     * different from json property
     *
     * @param Response $response
     *
     * @since  1.0.0
     */
    private static function decode_response(Response $response): Response
    {
        $not_identical = (json_decode($response->__toString(), true) != $response->json) ||
            (empty($response->__toString()) != empty($response->json));

        if ($not_identical) {
            $response->{'raw_body'} = filter_var(str_replace(
                '\n',
                ' ',
                $response->__toString()
            ), FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        return $response;
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
     * @param string $url URL of the payment page
     *
     * @return Response
     *
     * @since 1.0.0
     */
    public static function create_payment(Order $order, string $gopay_payment_method, string $moduleId, string $url): Response
    {
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

        $notification_url = $url;
        $return_url = $url;

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

        $additional_params = [
            [
                'name' => 'id_cart',
                'value' => $order->id_cart,
            ],
            [   
                'name' => 'gopay_plugin',
                'value' => 'gopay-prestashop',
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

        return self::decode_response($response);
    }

    /**
     * Check payment status
     *
     * @param string $order_id
     * @param string $GoPay_Transaction_id
     *
     * @since  1.0.0
     */
    public static function check_payment_status(string $order_id, string $GoPay_Transaction_id)
    {
        $order = new Order((int) $order_id);
        $gopay = self::auth_gopay();
        $response = $gopay->getStatus($GoPay_Transaction_id);

        // Save log.
        $log = [
            'order_id' => $order->id,
            'transaction_id' => 200 == $response->statusCode ? $response->json['id'] : $GoPay_Transaction_id,
            'message' => 200 == $response->statusCode ? 'Checking payment status' :
                'Error checking payment status',
            'log_level' => 200 == $response->statusCode ? 'INFO' : 'ERROR',
            'log' => $response,
        ];
        PrestashopGopayLog::insert_log($log);

        $state = '';
        if ($response->statusCode == 200) {
            $state = $response->json['state'];
        }

        switch ($state) {
            case 'PAID':
                $order->setCurrentState((int) Configuration::get('PS_OS_WS_PAYMENT'));

                $order_payments = OrderPayment::getByOrderReference($order->reference);
                foreach ($order_payments as $order_payment) {
                    $order_payment->transaction_id = $GoPay_Transaction_id;
                    $order_payment->save();
                }

                break;
            case 'PAYMENT_METHOD_CHOSEN':
            case 'AUTHORIZED':
                $order->setCurrentState((int) Configuration::get('GOPAY_OS_WAITING'));

                break;
            case 'CREATED':
            case 'TIMEOUTED':
            case 'CANCELED':
                $order->setCurrentState((int) Configuration::get('PS_OS_ERROR'));

                Tools::redirect('order?payment-error=yes');

                break;
            case 'REFUNDED':
                $order->setCurrentState((int) Configuration::get('PS_OS_REFUND'));

                break;
        }

        $cart = new Cart($order->id_cart);
        $cart->delete();

        Tools::redirect('index.php?controller=order-confirmation&id_cart=' . (int) $order->id_cart . '&id_module=' .
            (int) PrestaShopGoPay::getInstanceByName('prestashopgopay')->id . '&id_order=' .
            $order->id . '&key=' . $order->getCustomer()->secure_key);
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

        return self::decode_response($response);
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

        return self::decode_response($response);
    }
}
