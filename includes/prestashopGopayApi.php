<?php

use GoPay\Http\Response;
use GoPay\Payments;

/**
 * PrestaShop GoPay API
 * Connect to the GoPay API using the GoPay's PHP SDK
 *
 * @package   PrestaShop GoPay gateway
 * @author    argo22
 * @link      https://www.argo22.com
 * @copyright 2022 argo22
 * @since     1.0.0
 */

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
	private static function decode_response( Response $response ): Response
	{
		$not_identical = ( json_decode( $response->__toString(), true ) != $response->json ) ||
			( empty( $response->__toString() ) != empty( $response->json ) );

		if ( $not_identical ) {
			$response->{'raw_body'} = filter_var( str_replace(
				'\n',
				' ',
				$response->__toString()
			), FILTER_SANITIZE_FULL_SPECIAL_CHARS );
		}

		return $response;
	}

	/**
	 * GoPay authentication
	 *
	 * @return Payments object
	 * @since  1.0.0
	 */
	private static function auth_GoPay(): Payments
	{
		return GoPay\payments( array(
			'goid'             => Configuration::get( 'PRESTASHOPGOPAY_GOID' ),
			'clientId'         => Configuration::get( 'PRESTASHOPGOPAY_CLIENT_ID' ),
			'clientSecret'     => Configuration::get( 'PRESTASHOPGOPAY_CLIENT_SECRET' ),
			'isProductionMode' => !Configuration::get( 'PRESTASHOPGOPAY_TEST' ),
			'scope'            => GoPay\Definition\TokenScope::ALL,
			'language'         => Configuration::get( 'PRESTASHOPGOPAY_DEFAULT_LANGUAGE' ),
			'timeout'          => 30,
		) );
	}

	/**
	 * Get items info
	 *
	 * @param object $order order detail.
	 *
	 * @return array
	 * @since 1.0.0
	 */
	private static function get_items( $cartProducts ): array
	{
		$items = array();
		foreach ( $cartProducts as $item ) {
			$items[] = array(
				'type'        => 'ITEM',
				'name'        => $item['name'],
				'product_url' => Context::getContext()->link->getProductLink( $item['id_product'] ),
				'amount'      => $item['total_wt'] * 100,
				'count'       => $item['quantity'],
				'vat_rate'    => $item['rate'],
			);
		}

		return $items;
	}

	/**
	 * GoPay create payment
	 *
	 * @param Context $context          payment method.
	 * @param string  $gopay_payment_method   order detail.
	 * @param string  $moduleId module id
	 * @param string  $url URL of the payment page
	 *
	 * @return Response
	 * @since 1.0.0
	 */
	public static function create_payment( Context $context, string $gopay_payment_method, string $moduleId, string $url ):
	Response
	{
		$gopay        = self::auth_GoPay();
		$cartProducts = $context->cart->getProducts();
		$customer     = new Customer( $context->cart->id_customer );
		$address      = new Address( $context->cart->id_address_invoice );
		$country      = new Country( $address->id_country );
		$currency     = new Currency( $context->cart->id_currency );
		$order        = Order::getByCartId( $context->cart->id);
		$simplified   = Configuration::get( 'PRESTASHOPGOPAY_SIMPLIFIED' );

		$allowed_swifts = array();
		foreach ( PrestashopGopayOptions::supported_banks() as $key => $value ) {
			if ( $gopay_payment_method == $value['key'] ) {
				$allowed_swifts       = [ $gopay_payment_method ];
				$gopay_payment_method = 'BANK_ACCOUNT';
			}
		}

		$default_payment_instrument = $gopay_payment_method;
		if ( empty( $gopay_payment_method ) || !Configuration::get( 'PRESTASHOPGOPAY_PAYMENT_RETRY' ) ) {
			if ( !$simplified && !empty( $gopay_payment_method ) ) {
				$default_payment_instrument = $gopay_payment_method;
			}
		}

		$items = self::get_items( $cartProducts );

		$notification_url = $url;
		$return_url       = $url;

		$callback = array(
			'return_url'       => $return_url,
			'notification_url' => $notification_url,
		);

		$contact = array(
			'first_name'   => $customer->firstname,
			'last_name'    => $customer->lastname,
			'email'        => $customer->email,
			'phone_number' => $address->phone,
			'city'         => $address->city,
			'street'       => $address->address1,
			'postal_code'  => $address->postcode,
			'country_code' => PrestashopGopayOptions::iso2_to_iso3()[ $country->iso_code ],
		);

		if ( !empty( $default_payment_instrument ) ) {
			$payer = array(
				'default_payment_instrument'  => $default_payment_instrument,
				'allowed_payment_instruments' => array( $default_payment_instrument ),
				'allowed_swifts'              => $allowed_swifts,
				'contact'                     => $contact,
			);
		} else {
			$payer = array(
				'contact' => $contact,
			);
		}

		$additional_params = array(
			array(
				'name'  => 'invoicenumber',
				'value' => $order->id,
			) );

		$language = PrestashopGopayOptions::country_to_language()[ $country->iso_code ];
		if ( !array_key_exists( $language, PrestashopGopayOptions::supported_languages() ) ) {
			$language = Configuration::get( 'PRESTASHOPGOPAY_DEFAULT_LANGUAGE' );
		}

		$data = array(
			'payer'             => $payer,
			'amount'            => round( $order->getTotalProductsWithTaxes(), 2 ) * 100,
			'currency'          => $currency->iso_code,
			'order_number'      => $order->id,
			'order_description' => 'order',
			'items'             => $items,
			'additional_params' => $additional_params,
			'callback'          => $callback,
			'lang'              => $language,
		);

//		if ( !empty( $end_date ) ) {
//			$data['recurrence'] = array(
//				'recurrence_cycle'      => 'ON_DEMAND',
//				'recurrence_date_to'    => $end_date != 0 ? $end_date : date( 'Y-m-d', strtotime( '+5 years' ) ) );
//		}

		$response = $gopay->createPayment( $data );

		return self::decode_response( $response );
	}

	/**
	 * Check payment status
	 *
	 * @param Order $order
	 * @param string $GoPay_Transaction_id
	 *
	 * @since  1.0.0
	 */
	public static function check_payment_status( Order $order, string $GoPay_Transaction_id )
	{
		$gopay    = self::auth_GoPay();
		$response = $gopay->getStatus( $GoPay_Transaction_id );

		if ( empty( $order ) ) {
			return;
		}

		if ( $response->statusCode != 200 ) {
			return;
		}

		switch ( $response->json['state'] ) {
			case 'PAID':
				$order->setCurrentState( 11 );

				break;
			case 'PAYMENT_METHOD_CHOSEN':
			case 'AUTHORIZED':
			case 'CREATED':

				break;
			case 'TIMEOUTED':
			case 'CANCELED':
				$order->setCurrentState( 8 );

				break;
			case 'REFUNDED':
				$order->setCurrentState( 7 );

				break;
		}
	}

	/**
	 * Check payment methods and banks that
	 * are enabled on GoPay account.
	 *
	 * @param string
	 * @return array
	 * @since  1.0.0
	 */
	public static function check_enabled_on_GoPay( $currency ): array
	{
		$gopay = self::auth_GoPay();

		$payment_methods = array();
		$banks           = array();
		$enabledPayments = $gopay->getPaymentInstruments( Configuration::get( 'PRESTASHOPGOPAY_GOID' ), $currency );

		if ( $enabledPayments->statusCode == 200 ) {
			foreach ( $enabledPayments->json['enabledPaymentInstruments'] as $key => $paymentMethod ) {
				$payment_methods[ $paymentMethod['paymentInstrument']
				] = array(
					'label' => PrestaShopGoPay::getInstanceByName(
						'prestashopgopay' )->l( $paymentMethod['label']['cs'] ),
					'image' => $paymentMethod['image']['normal'],
				);

				if ( $paymentMethod['paymentInstrument'] == 'BANK_ACCOUNT' ) {
					foreach ( $paymentMethod['enabledSwifts'] as $_ => $bank ) {
						$banks[ $bank['swift'] ] = array(
							'label'     => PrestaShopGoPay::getInstanceByName(
								'prestashopgopay' )->l( $bank['label']['cs'] ),
							'country'   => $bank['swift'] != 'OTHERS' ? substr($bank['swift'], 4, 2) : '',
							'image'     => $bank['image']['normal'] );
					}
				}
			}
		}

		return array( $payment_methods, $banks );
	}

}