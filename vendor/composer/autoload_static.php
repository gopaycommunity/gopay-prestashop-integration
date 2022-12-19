<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit90ed0ad7c81798fffb6311f7aa623020
{
    public static $files = array (
        'ad155f8f1cf0d418fe49e248db8c661b' => __DIR__ . '/..' . '/react/promise/src/functions_include.php',
        'a9b805bf529b5a997093b3cddca2af6f' => __DIR__ . '/..' . '/gopay/payments-sdk-php/factory.php',
    );

    public static $prefixLengthsPsr4 = array (
        'R' => 
        array (
            'React\\Promise\\' => 14,
        ),
        'G' => 
        array (
            'GuzzleHttp\\Stream\\' => 18,
            'GuzzleHttp\\Ring\\' => 16,
            'GuzzleHttp\\' => 11,
            'GoPay\\' => 6,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'React\\Promise\\' => 
        array (
            0 => __DIR__ . '/..' . '/react/promise/src',
        ),
        'GuzzleHttp\\Stream\\' => 
        array (
            0 => __DIR__ . '/..' . '/guzzlehttp/streams/src',
        ),
        'GuzzleHttp\\Ring\\' => 
        array (
            0 => __DIR__ . '/..' . '/guzzlehttp/ringphp/src',
        ),
        'GuzzleHttp\\' => 
        array (
            0 => __DIR__ . '/..' . '/guzzlehttp/guzzle/src',
        ),
        'GoPay\\' => 
        array (
            0 => __DIR__ . '/..' . '/gopay/payments-sdk-php/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'GoPay\\Auth' => __DIR__ . '/..' . '/gopay/payments-sdk-php/src/Auth.php',
        'GoPay\\Definition\\Account\\StatementGeneratingFormat' => __DIR__ . '/..' . '/gopay/payments-sdk-php/src/Definition/Account/StatementGeneratingFormat.php',
        'GoPay\\Definition\\Language' => __DIR__ . '/..' . '/gopay/payments-sdk-php/src/Definition/Language.php',
        'GoPay\\Definition\\Payment\\BankSwiftCode' => __DIR__ . '/..' . '/gopay/payments-sdk-php/src/Definition/Payment/BankSwiftCode.php',
        'GoPay\\Definition\\Payment\\Currency' => __DIR__ . '/..' . '/gopay/payments-sdk-php/src/Definition/Payment/Currency.php',
        'GoPay\\Definition\\Payment\\PaymentInstrument' => __DIR__ . '/..' . '/gopay/payments-sdk-php/src/Definition/Payment/PaymentInstrument.php',
        'GoPay\\Definition\\Payment\\PaymentItemType' => __DIR__ . '/..' . '/gopay/payments-sdk-php/src/Definition/Payment/PaymentItemType.php',
        'GoPay\\Definition\\Payment\\Recurrence' => __DIR__ . '/..' . '/gopay/payments-sdk-php/src/Definition/Payment/Recurrence.php',
        'GoPay\\Definition\\Payment\\VatRate' => __DIR__ . '/..' . '/gopay/payments-sdk-php/src/Definition/Payment/VatRate.php',
        'GoPay\\Definition\\RequestMethods' => __DIR__ . '/..' . '/gopay/payments-sdk-php/src/Definition/RequestMethods.php',
        'GoPay\\Definition\\Response\\PaymentStatus' => __DIR__ . '/..' . '/gopay/payments-sdk-php/src/Definition/Response/PaymentStatus.php',
        'GoPay\\Definition\\Response\\PaymentSubStatus' => __DIR__ . '/..' . '/gopay/payments-sdk-php/src/Definition/Response/PaymentSubStatus.php',
        'GoPay\\Definition\\Response\\PreAuthState' => __DIR__ . '/..' . '/gopay/payments-sdk-php/src/Definition/Response/PreAuthState.php',
        'GoPay\\Definition\\Response\\RecurrenceState' => __DIR__ . '/..' . '/gopay/payments-sdk-php/src/Definition/Response/RecurrenceState.php',
        'GoPay\\Definition\\Response\\Result' => __DIR__ . '/..' . '/gopay/payments-sdk-php/src/Definition/Response/Result.php',
        'GoPay\\Definition\\TokenScope' => __DIR__ . '/..' . '/gopay/payments-sdk-php/src/Definition/TokenScope.php',
        'GoPay\\GoPay' => __DIR__ . '/..' . '/gopay/payments-sdk-php/src/GoPay.php',
        'GoPay\\Http\\JsonBrowser' => __DIR__ . '/..' . '/gopay/payments-sdk-php/src/Http/JsonBrowser.php',
        'GoPay\\Http\\Log\\Logger' => __DIR__ . '/..' . '/gopay/payments-sdk-php/src/Http/Log/Logger.php',
        'GoPay\\Http\\Log\\NullLogger' => __DIR__ . '/..' . '/gopay/payments-sdk-php/src/Http/Log/NullLogger.php',
        'GoPay\\Http\\Log\\PrintHttpRequest' => __DIR__ . '/..' . '/gopay/payments-sdk-php/src/Http/Log/PrintHttpRequest.php',
        'GoPay\\Http\\Request' => __DIR__ . '/..' . '/gopay/payments-sdk-php/src/Http/Request.php',
        'GoPay\\Http\\Response' => __DIR__ . '/..' . '/gopay/payments-sdk-php/src/Http/Response.php',
        'GoPay\\OAuth2' => __DIR__ . '/..' . '/gopay/payments-sdk-php/src/OAuth2.php',
        'GoPay\\Payments' => __DIR__ . '/..' . '/gopay/payments-sdk-php/src/Payments.php',
        'GoPay\\PaymentsSupercash' => __DIR__ . '/..' . '/gopay/payments-sdk-php/src/PaymentsSupercash.php',
        'GoPay\\Token\\AccessToken' => __DIR__ . '/..' . '/gopay/payments-sdk-php/src/Token/AccessToken.php',
        'GoPay\\Token\\CachedOAuth' => __DIR__ . '/..' . '/gopay/payments-sdk-php/src/Token/CachedOAuth.php',
        'GoPay\\Token\\InMemoryTokenCache' => __DIR__ . '/..' . '/gopay/payments-sdk-php/src/Token/InMemoryTokenCache.php',
        'GoPay\\Token\\TokenCache' => __DIR__ . '/..' . '/gopay/payments-sdk-php/src/Token/TokenCache.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit90ed0ad7c81798fffb6311f7aa623020::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit90ed0ad7c81798fffb6311f7aa623020::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit90ed0ad7c81798fffb6311f7aa623020::$classMap;

        }, null, ClassLoader::class);
    }
}