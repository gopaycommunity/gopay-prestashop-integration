<?php

/**
 * PrestaShop GoPay gateway integration
 *
 * @author    GoPay
 * @copyright 2022 GoPay
 * @license   https://www.gnu.org/licenses/gpl-2.0.html  GPLv2 or later
 *
 * @see       https://www.gopay.com/
 * @since     1.0.0
 */

// If this file is called directly, abort.
// Preventing direct access to your PrestaShop.
if (!defined('_PS_VERSION_')) {
    exit;
}

class PrestaShopGoPay extends PaymentModule
{
    /**
     * Load required
     *
     * @since  1.0.0
     */
    private function init()
    {
        require_once 'vendor/autoload.php';
        require_once 'includes/prestashopGopayOptions.php';
        require_once 'includes/prestashopGopayApi.php';
        require_once 'includes/prestashopGopayLog.php';
    }

    /**
     * Constructor for the gateway
     *
     * @since  1.0.0
     */
    public function __construct()
    {
        $this->name = 'prestashopgopay';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.7';
        $this->author = 'GoPay';
        $this->need_instance = 1;
        $this->ps_versions_compliancy = [
            'min' => '8.0.1',
            'max' => _PS_VERSION_,
        ];
        $this->module_key = '0886a44d421d7fdc2bb4e20cbb71c5c9';

        parent::__construct();

        $this->init();
        if (!$this->isRegisteredInHook('actionProductCancel')) {
            $this->registerHook('actionProductCancel');
        }
        if (!$this->isRegisteredInHook('actionOrderSlipAdd')) {
            $this->registerHook('actionOrderSlipAdd');
        }
        if (!$this->isRegisteredInHook('displayOrderDetail')) {
            $this->registerHook('displayOrderDetail');
        }
        if (version_compare(_PS_VERSION_, '8.0', '<') && !$this->isRegisteredInHook('payment')) {
            $this->registerHook('payment');
        }
        if (!$this->isRegisteredInHook('displayAdminAfterHeader')) {
            $this->registerHook('displayAdminAfterHeader');
        }
        if (!$this->isRegisteredInHook('displayCheckoutSummaryTop')) {
            $this->registerHook('displayCheckoutSummaryTop');
        }

        $this->displayName = $this->l('PrestaShop GoPay gateway');
        $this->description = $this->l('PrestaShop and GoPay payment gateway integration');

        $this->confirmUninstall = $this->l('Are you sure you want to uninstall PrestaShop GoPay gateway ?');

        //		$this->limited_countries  = array( 'CZ' );
        //		$this->limited_currencies = array( 'CZK' );
    }

    /**
     * Create PrestaShop GoPay tabs on admin menu
     */
    private function createTabs()
    {
        $class_name = 'AdminPrestaShopGoPay';
        $subtabs = [
            [
                'class' => 'AdminPrestaShopGoPayInfo',
                'name' => 'Info',
                'icon' => 'store',
            ],
            [
                'class' => 'AdminPrestaShopGoPayLog',
                'name' => 'Log',
                'icon' => 'assessment',
            ],
        ];

        $id_parent = $this->createMainTab($class_name);
        $this->createSubTabs($subtabs, $id_parent);
    }

    /**
     * Create main tab on PrestaShop GoPay admin menu
     *
     * @param string $class_name
     *
     * @return string
     */
    private function createMainTab(string $class_name)
    {
        $tab = new Tab();

        $tab->active = 1;
        $tab->class_name = $class_name;
        $tab->id_parent = 0;
        $tab->module = $this->name;
        $tab->name = [];
        foreach (Language::getLanguages() as $lang) {
            $tab->name[$lang['id_lang']] = $this->l('PrestaShop GoPay Gateway');
        }
        $tab->add();

        return $tab->id;
    }

    /**
     * Create subtabs on PrestaShop GoPay main tab
     *
     * @param array $subtabs
     * @param string $id_parent
     */
    private function createSubTabs(array $subtabs, string $id_parent)
    {
        foreach ($subtabs as $subtab) {
            $tab = new Tab();

            $tab->active = 1;
            $tab->class_name = $subtab['class'];
            $tab->id_parent = $id_parent;
            $tab->module = $this->name;
            $tab->icon = $subtab['icon'];
            $tab->name = [];
            foreach (Language::getLanguages() as $lang) {
                $tab->name[$lang['id_lang']] = $this->l($subtab['name']);
            }
            $tab->add();
        }
    }

    /**
     * Create a new order state
     * for the GoPay payment module
     *
     * @return bool
     */
    public function installOrderState()
    {
        if (
            !Configuration::get('GOPAY_OS_WAITING')
            || !Validate::isLoadedObject(new OrderState(Configuration::get('GOPAY_OS_WAITING')))
        ) {
            $order_state = new OrderState();
            $order_state->color = '#1e8dce';
            $order_state->module_name = $this->name;

            $order_state->name = [];
            foreach (Language::getLanguages() as $language) {
                $order_state->name[$language['id_lang']] = 'Awaiting for GoPay payment';
            }

            if ($order_state->add()) {
                $source = _PS_MODULE_DIR_ . 'prestashopgopay/logo.png';
                $destination = _PS_ROOT_DIR_ . '/img/os/' . (int) $order_state->id . '.gif';

                copy($source, $destination);
            }

            if (Shop::isFeatureActive()) {
                $shops = Shop::getShops();
                foreach ($shops as $shop) {
                    Configuration::updateValue(
                        'GOPAY_OS_WAITING',
                        (int) $order_state->id,
                        false,
                        null,
                        (int) $shop['id_shop']
                    );
                }
            } else {
                Configuration::updateValue('GOPAY_OS_WAITING', (int) $order_state->id);
            }
        }

        return true;
    }

    /**
     * Create log table if it does not exist
     *
     * @since 1.0.0
     */
    private static function create_log_table()
    {
        Db::getInstance()->execute('CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . "gopay_log` (
                `id` bigint(255) NOT NULL AUTO_INCREMENT,
                `order_id` bigint(255) NOT NULL,
                `transaction_id` bigint(255) NOT NULL,
                `message` varchar(50) NOT NULL,
                `created_at` datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
                `log_level` varchar(100) NOT NULL,
                `log` JSON NOT NULL,
                CONSTRAINT `order_transaction_state_unique` UNIQUE(`order_id`, `transaction_id`, `message`),
                PRIMARY KEY (`id`)
                ) ENGINE=" . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8;');
    }

    /**
     * True if the module is correctly installed,
     * or false otherwise
     *
     * @return bool
     *
     * @since  1.0.0
     */
    public function install()
    {
        if (!$this->installOrderState()) {
            return false;
        }

        $id_parent = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS(
            'SELECT id_tab FROM `' . _DB_PREFIX_ . 'tab` WHERE class_name = "AdminPrestaShopGoPay"',
            true,
            false
        );
        if (!$id_parent) {
            $this->createTabs();
        }

        $this->create_log_table();

        $installed = parent::install() &&
            $this->registerHook('paymentOptions') &&
            $this->registerHook('displayOrderConfirmation') &&
            $this->registerHook('actionOrderStatusUpdate') &&
            $this->registerHook('actionProductCancel') &&
            $this->registerHook('actionOrderSlipAdd') &&
            $this->registerHook('displayAdminAfterHeader') &&
            $this->registerHook('displayCheckoutSummaryTop');

        if ($installed && version_compare(_PS_VERSION_, '8.0', '<')) {
            $installed = $this->registerHook('payment');
        }

        return $installed;
    }

    /**
     * True if the module is correctly uninstalled,
     * or false otherwise
     *
     * @return bool
     *
     * @since  1.0.0
     */
    public function uninstall()
    {
        return parent::uninstall();
    }

    /**
     * Module's configuration page
     *
     * @return string
     *
     * @since  1.0.0
     */
    public function getContent()
    {
        $this->update_payment_methods();
        $output = '';

        if (Tools::isSubmit('submit' . $this->name)) {
            $output = $this->postProcess();
        }

        return $output . $this->displayForm();
    }

    /**
     * Process and save config form data.
     *
     * @return string
     *
     * @since  1.0.0
     */
    protected function postProcess()
    {
        $form_values = Tools::getAllValues();

        foreach (array_keys($form_values) as $key) {
            if (is_array(Tools::getValue($key))) {
                Configuration::updateValue($key, json_encode(Tools::getValue($key)));

                continue;
            }
            Configuration::updateValue($key, Tools::getValue($key));
        }

        if (
            !array_key_exists('PRESTASHOPGOPAY_GOID', $form_values) ||
            empty($form_values['PRESTASHOPGOPAY_GOID']) ||
            !array_key_exists('PRESTASHOPGOPAY_CLIENT_ID', $form_values) ||
            empty($form_values['PRESTASHOPGOPAY_CLIENT_ID']) ||
            !array_key_exists('PRESTASHOPGOPAY_CLIENT_SECRET', $form_values) ||
            empty($form_values['PRESTASHOPGOPAY_CLIENT_SECRET'])
        ) {
            Configuration::updateValue('PRESTASHOPGOPAY_ENABLED', false);

            return $this->displayError($this->l(
                'Inform goid, client id and secret to enable GoPay payment gateway and load the other options'
            ));
        } else {
            if (array_key_exists('PRESTASHOPGOPAY_TEST', $form_values)) {
                $gopay = PrestashopGopayApi::auth_gopay();

                $response = $gopay->getPaymentInstruments(
                    Configuration::get('PRESTASHOPGOPAY_GOID'),
                    'CZK'
                );
                if (!$response->hasSucceed()) {
                    $response = $gopay->getPaymentInstruments(
                        Configuration::get('PRESTASHOPGOPAY_GOID'),
                        'CZK'
                    );
                    if (
                        array_key_exists('errors', $response->json) &&
                        $response->json['errors'][0]['error_name'] == 'INVALID'
                    ) {
                        Configuration::updateValue('PRESTASHOPGOPAY_GOID', '');
                    }

                    $response = $gopay->getAuth()->authorize()->response;
                    if (
                        array_key_exists('errors', $response->json) &&
                        $response->json['errors'][0]['error_name'] == 'AUTH_WRONG_CREDENTIALS'
                    ) {
                        Configuration::updateValue('PRESTASHOPGOPAY_CLIENT_ID', '');
                        Configuration::updateValue('PRESTASHOPGOPAY_CLIENT_SECRET', '');
                    }

                    return $this->displayError($this->l(
                        'Wrong GoID and/or credentials. Please provide valid GoID, Client ID and Client Secret.'
                    ));
                }
            }
        }

        return $this->displayConfirmation($this->l('Settings updated'));
    }

    /**
     * Configuration form
     *
     * @return string
     *
     * @since  1.0.0
     */
    protected function displayForm()
    {
        $helper = new HelperForm();

        $helper->module = $this;
        $helper->table = $this->table;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&' . http_build_query(
                ['configure' => $this->name]
            );
        $helper->submit_action = 'submit' . $this->name;
        $helper->default_form_language = (int) Configuration::get('PS_LANG_DEFAULT');

        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFormValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        ];

        return $helper->generateForm([$this->getConfigForm()]);
    }

    /**
     * Structure of the configuration form
     *
     * @return array
     *
     * @since  1.0.0
     */
    protected function getConfigForm()
    {
        if (
            !empty(Configuration::get('PRESTASHOPGOPAY_GOID')) &&
            !empty(Configuration::get('PRESTASHOPGOPAY_CLIENT_ID')) &&
            !empty(Configuration::get('PRESTASHOPGOPAY_CLIENT_SECRET'))
        ) {
            $prestashopGopayOptions = new PrestashopGopayOptions();

            return [
                'form' => [
                    'legend' => [
                        'title' => $this->l('Settings'),
                        'icon' => 'icon-cogs',
                    ],
                    'input' => [
                        [
                            'type' => 'switch',
                            'label' => $this->l('Enable/Disable'),
                            'name' => 'PRESTASHOPGOPAY_ENABLED',
                            'is_bool' => true,
                            'desc' => $this->l('Enable GoPay payment gateway'),
                            'values' => [
                                [
                                    'id' => 'active_on',
                                    'value' => true,
                                    'label' => $this->l('Enabled'),
                                ],
                                [
                                    'id' => 'active_off',
                                    'value' => false,
                                    'label' => $this->l('Disabled'),
                                ],
                            ],
                        ],
                        [
                            'type' => 'switch',
                            'label' => $this->l('Inline payment gateway'),
                            'name' => 'PRESTASHOPGOPAY_INLINE',
                            'is_bool' => true,
                            'desc' => $this->l('Inline payment gateway is initiated directly above the point of sale.'),
                            'values' => [
                                [
                                    'id' => 'active_on',
                                    'value' => true,
                                    'label' => $this->l('Enabled'),
                                ],
                                [
                                    'id' => 'active_off',
                                    'value' => false,
                                    'label' => $this->l('Disabled'),
                                ],
                            ],
                        ],
                        [
                            'type' => 'text',
                            'label' => $this->l('Title'),
                            'name' => 'PRESTASHOPGOPAY_TITLE',
                            'size' => 50,
                            'required' => true,
                            //							'desc'        => $this->l(
                            //								'Name of the payment method that is displayed at the checkout' ),
                            'placeholder' => $this->l('Insert Payment Title...'),
                        ],
                        [
                            'type' => 'text',
                            'label' => $this->l('Description'),
                            'name' => 'PRESTASHOPGOPAY_DESCRIPTION',
                            'size' => 50,
                            'required' => true,
                            //							'desc'        => $this->l(
                            //								'Description of the payment method that is displayed at the checkout' ),
                            'placeholder' => $this->l('Insert Description...'),
                        ],
                        [
                            'type' => 'text',
                            'label' => $this->l('Client Id'),
                            'name' => 'PRESTASHOPGOPAY_CLIENT_ID',
                            'size' => 50,
                            'required' => true,
                            'placeholder' => $this->l('Insert Your GoPay Client ID...'),
                        ],
                        [
                            'type' => 'text',
                            'label' => $this->l('Client secret'),
                            'name' => 'PRESTASHOPGOPAY_CLIENT_SECRET',
                            'size' => 50,
                            'required' => true,
                            'placeholder' => $this->l('Insert Your GoPay Client Secret Token...'),
                        ],
                        [
                            'type' => 'text',
                            'label' => $this->l('GoId'),
                            'name' => 'PRESTASHOPGOPAY_GOID',
                            'size' => 50,
                            'required' => true,
                            'placeholder' => $this->l('Insert Your GoID...'),
                        ],
                        [
                            'type' => 'switch',
                            'label' => $this->l('Test mode'),
                            'name' => 'PRESTASHOPGOPAY_TEST',
                            'is_bool' => true,
                            'desc' => $this->l('Enable GoPay payment gateway test mode'),
                            'values' => [
                                [
                                    'id' => 'active_on',
                                    'value' => true,
                                    'label' => $this->l('Enabled'),
                                ],
                                [
                                    'id' => 'active_off',
                                    'value' => false,
                                    'label' => $this->l('Disabled'),
                                ],
                            ],
                        ],
                        [
                            'type' => 'select',
                            'label' => $this->l('Default language of the GoPay interface'),
                            'name' => 'PRESTASHOPGOPAY_DEFAULT_LANGUAGE',
                            'options' => [
                                'query' => $prestashopGopayOptions->supported_languages(),
                                'id' => 'key',
                                'name' => 'name',
                            ],
                            'placeholder' => $this->l('Select Default Language...'),
                        ],
                        [
                            'type' => 'select',
                            'label' => $this->l('Enable shipping methods'),
                            'name' => 'PRESTASHOPGOPAY_SHIPPING_METHODS[]',
                            'multiple' => true,
                            'options' => [
                                'query' => $prestashopGopayOptions->supported_shipping_methods(),
                                'id' => 'key',
                                'name' => 'name',
                            ],
                            'placeholder' => $this->l('Select Shipping Methods...'),
                        ],
                        [
                            'type' => 'select',
                            'label' => $this->l('Enable countries'),
                            'name' => 'PRESTASHOPGOPAY_COUNTRIES[]',
                            'multiple' => true,
                            'options' => [
                                'query' => $prestashopGopayOptions->supported_countries(),
                                'id' => 'key',
                                'name' => 'name',
                            ],
                            'placeholder' => $this->l('Select Available Countries...'),
                        ],
                        [
                            'type' => 'switch',
                            'label' => $this->l('Bank Selection'),
                            'name' => 'PRESTASHOPGOPAY_SIMPLIFIED',
                            'is_bool' => true,
                            'desc' => $this->l('If enabled, customers cannot choose any specific bank at
							 the checkout, they are grouped into one “Bank account” option,
							 but they have to select the bank once the GoPay payment gateway is invoked.'),
                            'values' => [
                                [
                                    'id' => 'active_on',
                                    'value' => true,
                                    'label' => $this->l('Enabled'),
                                ],
                                [
                                    'id' => 'active_off',
                                    'value' => false,
                                    'label' => $this->l('Disabled'),
                                ],
                            ],
                        ],
                        [
                            'type' => 'select',
                            'label' => $this->l('Enable GoPay payment methods'),
                            'name' => 'PRESTASHOPGOPAY_PAYMENT_METHODS[]',
                            'multiple' => true,
                            'options' => [
                                'query' => $prestashopGopayOptions->supported_payment_methods(),
                                'id' => 'key',
                                'name' => 'name',
                            ],
                            'placeholder' => $this->l('Select GoPay Payment Methods...'),
                        ],
                        [
                            'type' => 'select',
                            'label' => $this->l('Enable banks'),
                            'name' => 'PRESTASHOPGOPAY_BANKS[]',
                            'multiple' => true,
                            'options' => [
                                'query' => $prestashopGopayOptions->supported_banks(),
                                'id' => 'key',
                                'name' => 'name',
                            ],
                            'placeholder' => $this->l('Select Available Banks...'),
                        ],
                    ],
                    'submit' => [
                        'title' => $this->l('Save'),
                    ],
                ],
            ];
        }

        return [
            'form' => [
                'legend' => [
                    'title' => $this->l(
                        'Inform goid, client id and secret to enable GoPay payment gateway and load the other options'
                    ),
                    'icon' => 'icon-cogs',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->l('Client Id'),
                        'name' => 'PRESTASHOPGOPAY_CLIENT_ID',
                        'size' => 50,
                        'required' => true,
                        'placeholder' => $this->l('Insert Your GoPay Client ID...'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('Client secret'),
                        'name' => 'PRESTASHOPGOPAY_CLIENT_SECRET',
                        'size' => 50,
                        'required' => true,
                        'placeholder' => $this->l('Insert Your GoPay Client Secret Token...'),
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->l('GoId'),
                        'name' => 'PRESTASHOPGOPAY_GOID',
                        'size' => 50,
                        'required' => true,
                        'placeholder' => $this->l('Insert Your GoID...'),
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->l('Test mode'),
                        'name' => 'PRESTASHOPGOPAY_TEST',
                        'is_bool' => true,
                        'desc' => $this->l('Enable GoPay payment gateway test mode'),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled'),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled'),
                            ],
                        ],
                    ],
                ],
                'submit' => [
                    'title' => $this->l('Save'),
                ],
            ],
        ];
    }

    /**
     * Get values from the database
     * to populate the inputs
     *
     * @return array
     *
     * @since  1.0.0
     */
    protected function getConfigFormValues()
    {
        return [
            'PRESTASHOPGOPAY_ENABLED' => Configuration::get('PRESTASHOPGOPAY_ENABLED'),
            'PRESTASHOPGOPAY_INLINE' => Configuration::get('PRESTASHOPGOPAY_INLINE'),
            'PRESTASHOPGOPAY_TITLE' => Configuration::get('PRESTASHOPGOPAY_TITLE'),
            'PRESTASHOPGOPAY_DESCRIPTION' => Configuration::get('PRESTASHOPGOPAY_DESCRIPTION'),
            'PRESTASHOPGOPAY_GOID' => Configuration::get('PRESTASHOPGOPAY_GOID'),
            'PRESTASHOPGOPAY_CLIENT_ID' => Configuration::get('PRESTASHOPGOPAY_CLIENT_ID'),
            'PRESTASHOPGOPAY_CLIENT_SECRET' => Configuration::get('PRESTASHOPGOPAY_CLIENT_SECRET'),
            'PRESTASHOPGOPAY_TEST' => Configuration::get('PRESTASHOPGOPAY_TEST'),
            'PRESTASHOPGOPAY_DEFAULT_LANGUAGE' => Configuration::get('PRESTASHOPGOPAY_DEFAULT_LANGUAGE'),
            'PRESTASHOPGOPAY_SHIPPING_METHODS[]' => is_string(Configuration::get('PRESTASHOPGOPAY_SHIPPING_METHODS')) ?
                json_decode(Configuration::get('PRESTASHOPGOPAY_SHIPPING_METHODS')) : [],
            'PRESTASHOPGOPAY_COUNTRIES[]' => is_string(Configuration::get('PRESTASHOPGOPAY_COUNTRIES')) ?
                json_decode(Configuration::get('PRESTASHOPGOPAY_COUNTRIES')) : [],
            'PRESTASHOPGOPAY_SIMPLIFIED' => Configuration::get('PRESTASHOPGOPAY_SIMPLIFIED'),
            'PRESTASHOPGOPAY_PAYMENT_METHODS[]' => is_string(Configuration::get('PRESTASHOPGOPAY_PAYMENT_METHODS')) ?
                json_decode(Configuration::get('PRESTASHOPGOPAY_PAYMENT_METHODS')) : [],
            'PRESTASHOPGOPAY_BANKS[]' => is_string(Configuration::get('PRESTASHOPGOPAY_BANKS')) ?
                json_decode(Configuration::get('PRESTASHOPGOPAY_BANKS')) : [],
        ];
    }

    /**
     * Order confirmation message
     *
     * @param array $params parameters
     *
     * @return string|bool
     *
     * @since  1.0.0
     */
    public function hookDisplayOrderConfirmation($params)
    {
        $order = [];
        if (array_key_exists('order', $params)) {
            $order = $params['order'];
        } elseif (array_key_exists('objOrder', $params)) {
            $order = $params['objOrder'];
        }

        if (!Validate::isLoadedObject($order)) {
            return;
        }

        $transaction_id = Db::getInstance()->getValue(
            "SELECT transaction_id FROM `" . _DB_PREFIX_ . "order_payment` WHERE order_reference = '" .
            pSQL($order->reference) . "';"
        );

        $this->context->smarty->assign([
            'transaction_id' => $transaction_id,
            'method' => $this->name,
            'order_status' => $order->getCurrentState(),
        ]);

        return $this->context->smarty->fetch(_PS_MODULE_DIR_ . $this->name . '/views/templates/hook/order_confirmation.tpl');
    }

    /**
     * Check the PrestaShop GoPay restrictions
     *
     * @param int $cart_id cart id;
     *
     * @return bool
     *
     * @since  1.0.0
     */
    public function checkRestrictions(int $cart_id)
    {
        $cart = new Cart($cart_id);
        $address = new Address($cart->id_address_invoice);
        $invoice_country = new Country($address->id_country);
        $currency_order = new Currency($cart->id_currency);
        $cartProducts = $cart->getProducts();

        $enabled_countries = is_string(Configuration::get('PRESTASHOPGOPAY_COUNTRIES')) ?
            json_decode(Configuration::get('PRESTASHOPGOPAY_COUNTRIES')) : [];
        $enabled_shipping_methods = is_string(Configuration::get('PRESTASHOPGOPAY_SHIPPING_METHODS')) ?
            json_decode(Configuration::get('PRESTASHOPGOPAY_SHIPPING_METHODS')) : [];

        // Check countries
        if (
            empty($invoice_country) || empty($enabled_countries) ||
            !in_array($invoice_country->iso_code, (array) $enabled_countries)
        ) {
            return false;
        }
        // end check countries

        // Check currency matches one of the supported currencies
        $prestashopGopayOptions = new PrestashopGopayOptions();
        if (
            empty($currency_order) || !array_key_exists(
                $currency_order->iso_code,
                $prestashopGopayOptions->supported_currencies()
            )
        ) {
            return false;
        }
        // end check currency

        // Check if all products are virtual
        $all_virtual_downloadable = true;
        foreach ($cartProducts as $item) {
            if (!$item['is_virtual']) {
                $all_virtual_downloadable = false;

                break;
            }
        }

        if ($all_virtual_downloadable) {
            return true;
        }
        // end check virtual

        // Check shipping methods
        if (
            empty($cart) || empty($enabled_shipping_methods) ||
            !in_array($cart->id_carrier, (array) $enabled_shipping_methods)
        ) {
            return false;
        }
        // end check shipping methods

        return true;
    }

    /**
     * Check if PrestaShop GoPay payment
     * method should be displayed
     * and render the button
     *
     * @param array $params parameters
     *
     * @return array
     *
     * @since  1.0.0
     */
    public function hookPaymentOptions(array $params)
    {
        if (!$this->active || !Configuration::get('PRESTASHOPGOPAY_ENABLED')) {
            return [];
        }

        $option = new \PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $option->setCallToActionText($this->l(Configuration::get('PRESTASHOPGOPAY_TITLE')))
            ->setLogo(Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/gopay.png'));
        $option->setAction($this->context->link->getModuleLink($this->name, 'payment', [], true));

        $option->setForm($this->generateForm($params['cart']->id_currency));

        return $this->checkRestrictions($params['cart']->id) ? [$option] : [];
    }

    /**
     * Refund order when status is updated
     *
     * @param array parameters
     *
     * @return bool
     *
     * @since  1.0.0
     */
    public function hookActionOrderStatusUpdate(array $params)
    {
        $order = new Order($params['id_order']);
        $newOrderStatus = $params['newOrderStatus'];

        if (!Validate::isLoadedObject($order) || $order->module != 'prestashopgopay') {
            return false;
        }

        if ($order->getOrderPaymentCollection()->count() > 0 && (int) $newOrderStatus->id == Configuration::get('PS_OS_REFUND')) {
            $this->refund_payment($order);
        }

        return true;
    }

    /**
     * Display admin after header
     *
     * @param array parameters
     *
     * @return bool
     *
     * @since  1.0.0
     */
    public function hookDisplayAdminAfterHeader($params)
    {
        if (isset($_REQUEST['gopay_refund'])) {
            switch ($_REQUEST['gopay_refund']) {
                case 'partial_refund_error':
                    $message = $this->l('Only full refund can be issued before 24 hours has passed since the payment.');
                    $success = false;

                    break;
                case 'refund_error':
                    $message = $this->l('Refund error. Try again.');
                    $success = false;

                    break;
                case 'success':
                    $message = $this->l('GoPay refund was successfully created.');
                    $success = true;

                    break;
            }

            unset($_REQUEST['paypal_partial_refund_successful']);

            $this->context->smarty->assign(['success' => $success, 'message' => $message]);

            return $this->context->smarty->fetch(_PS_MODULE_DIR_ . $this->name . '/views/templates/admin/alert.tpl');
        }

        return '';
    }

    /**
     * Display Message above Checkout top
     *
     * @return bool
     *
     * @since  1.0.0
     */
    public function hookDisplayCheckoutSummaryTop()
    {
        $this->context->smarty->assign([
            'payment_error' => $_REQUEST['payment-error'] ?? 'no',
        ]);

        return $this->context->smarty->fetch(_PS_MODULE_DIR_ . $this->name . '/views/templates/hook/checkout_summary_top.tpl');
    }

    /**
     * Refund order when creating a credit slip
     *
     * @param array parameters
     *
     * @return bool
     *
     * @since  1.0.0
     */
    public function hookActionOrderSlipAdd($params)
    {
        if (
            array_key_exists('generateDiscount', Tools::getAllValues()) &&
            Tools::getAllValues()['generateDiscount'] == 'on'
        ) {
            return false;
        }

        $order = $params['order'];
        $productsDetail = $order->getProductsDetail();
        if (array_key_exists('cancel_product', Tools::getAllValues())) {
            $cancel = Tools::getAllValues()['cancel_product'];
        } else {
            $this->refundOrderOlderVersions($params);
        }

        if (array_key_exists('voucher', $cancel) && $cancel['voucher'] == 1) {
            return false;
        }

        if (!Validate::isLoadedObject($order) || $order->module != 'prestashopgopay') {
            return false;
        }

        if ($order->getOrderPaymentCollection()->count() > 0) {
            $payments = $order->getOrderPayments();
            $amount = 0;
            $amount_shipping = 0;

            if ($payments[0]->payment_method == 'PrestaShop GoPay gateway') {
                // Amount shipping
                if (array_key_exists('shipping', $cancel) && $cancel['shipping'] == 1) {
                    $amount_shipping += $order->total_shipping;
                } elseif (array_key_exists('shipping_amount', $cancel)) {
                    $amount_shipping += $cancel['shipping_amount'];
                }

                // Amount products
                $to_be_refunded = [];
                foreach ($productsDetail as $key => $productDetail) {
                    $quantity = $cancel['quantity_' . $productDetail['id_order_detail']];
                    $total_refunded = $cancel['amount_' . $productDetail['id_order_detail']];

                    if ($quantity > 0) {
                        if ($total_refunded == 0) {
                            $total_refunded += $productDetail['unit_price_tax_incl'] * $quantity;
                        }
                        $to_be_refunded[$productDetail['id_order_detail']] = [
                            'quantity' => $quantity,
                            'total_refunded' => $total_refunded,
                        ];
                        $amount += $total_refunded;
                    }
                }

                // Process refund
                $wasRefunded = true;
                if (round($amount + $amount_shipping, 2) > 0) {
                    // Check if refund can be made
                    $date = DateTime::createFromFormat('Y-m-d H:i:s', $order->date_upd);
                    if (
                        round($amount + $amount_shipping, 2) != $order->getTotalPaid() &&
                        !($date->getTimestamp() < time() - 86400)
                    ) {
                        $order_slips = $order->getOrderSlipsCollection();
                        $order_slip = $order_slips->getLast();
                        $order_slip->delete();

                        Tools::redirect($_SERVER['HTTP_REFERER'] . '&gopay_refund=partial_refund_error');
                    }

                    list($wasRefunded, $state) = $this->process_refund(
                        $order->id,
                        round($amount + $amount_shipping, 2) * 100,
                        $payments[0]->transaction_id
                    );
                }

                if ($wasRefunded) {
                    if ($amount > 0) {
                        foreach ($to_be_refunded as $id => $quantity_total) {
                            $order_detail_refund = new OrderDetail($id);

                            $order_detail_refund->product_quantity_refunded =
                                $order_detail_refund->product_quantity_refunded + $quantity_total['quantity'];
                            $order_detail_refund->total_refunded_tax_incl =
                                round($order_detail_refund->total_refunded_tax_incl + $quantity_total['total_refunded'], 2);
                            $order_detail_refund->save();
                        }
                    }
                } else {
                    $order_slips = $order->getOrderSlipsCollection();
                    $order_slip = $order_slips->getLast();
                    $order_slip->delete();

                    Tools::redirect($_SERVER['HTTP_REFERER'] . '&gopay_refund=refund_error');
                }

                if ($state == 'REFUNDED') {
                    $order->setCurrentState(Configuration::get('PS_OS_REFUND'));
                }
                // End process refund

                Tools::redirect($_SERVER['HTTP_REFERER'] . '&gopay_refund=success');
            }
        }
    }

    /**
     * Rollback changes when refund failed
     *
     * @param object $order order
     * @param array $quantities quantities
     * @param bool $restriction_full_refund restriction 24h full refund
     *
     * @return bool
     *
     * @since  1.0.0
     */
    public function rollBackRefundChanges($order, $quantities, $restriction_full_refund = false)
    {
        $order_slips = $order->getOrderSlipsCollection();
        $order_slip = $order_slips[count($order_slips) - 1];
        $order_slip_id = $order_slip->id;
        $order_slip->delete();

        Db::getInstance()->executeS(
            'DELETE FROM `' . _DB_PREFIX_ . "order_slip_detail` WHERE id_order_slip = '" .
            $order_slip_id . "';"
        );

        foreach ($quantities as $id => $quantity) {
            $order_detail = new OrderDetail((int) $id);
            $order_detail->product_quantity_refunded -= (int) $quantity;
            $order_detail->update();
        }

        if ($restriction_full_refund) {
            Tools::redirect($_SERVER['HTTP_REFERER'] . '&gopay_refund=partial_refund_error');
        }
        Tools::redirect($_SERVER['HTTP_REFERER'] . '&gopay_refund=refund_error');
    }

    /**
     * Refund order older versions
     *
     * @param array parameters
     *
     * @return bool
     *
     * @since  1.0.0
     */
    public function refundOrderOlderVersions($params)
    {
        if (
            array_key_exists('generateDiscount', Tools::getAllValues()) &&
            Tools::getAllValues()['generateDiscount'] == 'on'
        ) {
            Tools::redirect($_SERVER['HTTP_REFERER'] . '&gopay_refund=refund_error');
        }

        $order = $params['order'];
        if ($order->getOrderPaymentCollection()->count() > 0) {
            $payments = $order->getOrderPayments();

            if ($payments[0]->payment_method == 'PrestaShop GoPay gateway') {
                $amount = 0;
                $amount_shipping = 0;
                $refund_values = [];
                if (array_sum(Tools::getAllValues()['partialRefundProductQuantity']) > 0) {
                    $quantities = Tools::getAllValues()['partialRefundProductQuantity'];
                    $refund_values = Tools::getAllValues()['partialRefundProduct'];
                } else {
                    $quantities = Tools::getAllValues()['cancelQuantity'];
                }

                foreach ($quantities as $id => $quantity) {
                    if ($quantity) {
                        $orderDetail = new OrderDetail($id);
                        if ($refund_values) {
                            $amount += $refund_values[$id];
                        } else {
                            $amount += $orderDetail->unit_price_tax_incl * $quantity;
                        }
                    }
                }

                // Calculate and update shipping total
                $order_slips = (array) $order->getOrderSlipsCollection()->getResults();
                $order_slips_current = $order_slips[count($order_slips) - 1];
                unset($order_slips[count($order_slips) - 1]);
                $amount_shipping_excl = 0;
                foreach ($order_slips as $key => $order_slip) {
                    $amount_shipping -= $order_slip->total_shipping_tax_incl;
                    $amount_shipping_excl -= $order_slip->total_shipping_tax_excl;
                }
                $order_slips_current->total_shipping_tax_incl += $amount_shipping;
                $order_slips_current->total_shipping_tax_excl += $amount_shipping_excl;
                $order_slips_current->update();

                if (array_key_exists('shippingBack', Tools::getAllValues())) {
                    $amount_shipping += $order->total_shipping_tax_incl;
                } elseif (array_key_exists('partialRefundShippingCost', Tools::getAllValues())) {
                    $amount_shipping += Tools::getAllValues()['partialRefundShippingCost'];
                }

                // Process refund
                $wasRefunded = false;
                if (round($amount + $amount_shipping, 2) > 0) {
                    // Check if refund can be made
                    $date = DateTime::createFromFormat('Y-m-d H:i:s', $order->date_upd);
                    if (
                        round($amount + $amount_shipping, 2) != $order->getTotalPaid() &&
                        !($date->getTimestamp() < time() - 86400)
                    ) {
                        self::rollBackRefundChanges($order, $quantities, true);
                    }

                    list($wasRefunded, $state) = $this->process_refund(
                        $order->id,
                        round($amount + $amount_shipping, 2) * 100,
                        $payments[0]->transaction_id
                    );
                    if (!$wasRefunded) {
                        self::rollBackRefundChanges($order, $quantities);
                    }
                }

                if ($state == 'REFUNDED') {
                    $order->setCurrentState(Configuration::get('PS_OS_REFUND'));
                }
                // End process refund

                Tools::redirect($_SERVER['HTTP_REFERER'] . '&gopay_refund=success');
            }
        }
    }

    /**
     * Refund order when creating a credit slip
     *
     * @param array parameters
     *
     * @return bool
     *
     * @since  1.0.0
     */
    public function hookActionProductCancel($params)
    {
        if (version_compare(_PS_VERSION_, '1.7.6', '>')) {
            if (
                $params['action'] !== PrestaShop\PrestaShop\Core\Domain\Order\CancellationActionType::STANDARD_REFUND &&
                $params['action'] !== PrestaShop\PrestaShop\Core\Domain\Order\CancellationActionType::PARTIAL_REFUND
            ) {
                Tools::redirect($_SERVER['HTTP_REFERER']);
            }
        } else {
            if (!array_key_exists('generateCreditSlip', Tools::getAllValues())) {
                $order_detail = new OrderDetail((int) $params['id_order_detail']);
                $order_detail->product_quantity_refunded -= (int) Tools::getAllValues()['cancelQuantity'][$params['id_order_detail']];
                $order_detail->update();
            }

            if (!$params['order']->hasBeenPaid()) {
                Tools::redirect($_SERVER['HTTP_REFERER']);
            }
        }
    }

    /**
     * Process refund.
     *
     * @param Order $order
     */
    private function refund_payment(Order $order)
    {
        $payments = $order->getOrderPayments();
        $orders_detail = $order->getOrderDetailList();
        $order_slips = $order->getOrderSlipsCollection();

        $order_slips_detail = [];
        foreach ($order_slips as $key => $order_slip) {
            foreach ($order_slip->getOrdersSlipDetail(
                $order_slip->id
            ) as $key => $order_slip_detail) {
                if (array_key_exists($order_slip_detail['id_order_detail'], $order_slips_detail)) {
                    $order_slips_detail[$order_slip_detail['id_order_detail']] += $order_slip_detail['amount_tax_incl'];
                } else {
                    $order_slips_detail[$order_slip_detail['id_order_detail']] = $order_slip_detail['amount_tax_incl'];
                }
            }
        }

        if ($payments[0]->payment_method == 'PrestaShop GoPay gateway') {
            // Amount shipping
            $order_slips = $order->getOrderSlipsCollection();
            $amount_shipping = $order->total_shipping_tax_incl;
            foreach ($order_slips as $key => $order_slip) {
                $amount_shipping -= $order_slip->total_shipping_tax_incl;
            }

            // Amount products
            $amount = 0;
            foreach ($orders_detail as $key => $order_detail) {
                $order_detail_refund = new OrderDetail($order_detail['id_order_detail']);
                $amount += round($order_detail_refund->total_price_tax_incl -
                    $order_slips_detail[$order_detail['id_order_detail']], 2);
            }

            // Process refund
            if (round($amount + $amount_shipping, 2) > 0) {
                // Check if refund can be made
                $date = DateTime::createFromFormat('Y-m-d H:i:s', $order->date_upd);
                if (
                    round($amount + $amount_shipping, 2) != $order->getTotalPaid() &&
                    !($date->getTimestamp() < time() - 86400)
                ) {
                    Tools::redirect($_SERVER['HTTP_REFERER'] . '&gopay_refund=partial_refund_error');
                }

                list($wasRefunded, $state) = $this->process_refund(
                    $order->id,
                    round($amount + $amount_shipping, 2) * 100,
                    $payments[0]->transaction_id
                );
            } else {
                return;
            }

            if ($wasRefunded) {
                foreach ($order->getOrderSlipsCollection() as $order_slip) {
                    $order_slip->delete();

                    if (gettype($order_slip) == 'array') {
                        $id_order_slip = (int) $order_slip['id_order_slip'];
                    } else {
                        $id_order_slip = (int) $order_slip->id;
                    }
                    Db::getInstance()->delete('order_slip_detail', 'id_order_slip = ' . $id_order_slip);
                }

                $order_slip = OrderSlip::create(
                    $order,
                    [],
                    $order->total_shipping_tax_incl,
                    $order->getTotalProductsWithTaxes(),
                    true,
                    false
                );
                if (in_array('getLast', get_class_methods($order->getOrderSlipsCollection()))) {
                    $order_slip = $order->getOrderSlipsCollection()->getLast();
                    $id_order_slip = (int) $order_slip->id;
                } else {
                    $id_order_slip = (int) $order_slip['id_order_slip'];
                }

                if ($amount > 0) {
                    foreach ($orders_detail as $key => $order_detail) {
                        $order_detail_refund = new OrderDetail($order_detail['id_order_detail']);

                        $order_detail_refund->product_quantity_refunded = $order_detail_refund->product_quantity;
                        $order_detail_refund->total_refunded_tax_incl = $order_detail_refund->total_price_tax_incl;
                        $order_detail_refund->save();

                        Db::getInstance()->insert('order_slip_detail', [
                            'id_order_slip' => $id_order_slip,
                            'id_order_detail' => (int) $order_detail['id_order_detail'],
                            'product_quantity' => $order_detail_refund->product_quantity,
                            'amount_tax_excl' => round($order_detail_refund->unit_price_tax_excl * $order_detail_refund->product_quantity, 2),
                            'amount_tax_incl' => round($order_detail_refund->unit_price_tax_incl * $order_detail_refund->product_quantity, 2),
                        ]);
                    }
                }
            } else {
                Tools::redirect($_SERVER['HTTP_REFERER'] . '&gopay_refund=refund_error');
            }
            // End refund

            if ($state != 'REFUNDED') {
                Tools::redirect($_SERVER['HTTP_REFERER'] . '&gopay_refund=success');
            }
        }
    }

    /**
     * Process refund.
     *
     * @param int $order_id
     * @param float $amount
     * @param string $transaction_id
     *
     * @return array
     */
    private function process_refund(int $order_id, float $amount, string $transaction_id): array
    {
        $response = PrestashopGopayApi::refund_payment($transaction_id, $amount);
        $status = PrestashopGopayApi::get_status($transaction_id);

        $log = [
            'order_id' => $order_id,
            'transaction_id' => $transaction_id,
            'message' => 200 == $status->statusCode ? ('PARTIALLY_REFUNDED' === $status->json['state'] ?
                'Payment partially refunded' : 'Payment refunded') : 'Payment refund executed',
            'log_level' => 'INFO',
            'log' => $status,
        ];

        if ($status->json['state'] != 'REFUNDED' && $response->statusCode != 200) {
            $log['message'] = 'Process refund error';
            $log['log_level'] = 'ERROR';
            $log['log'] = $response;
            PrestashopGopayLog::insert_log($log);

            return [false, array_key_exists('state', $status->json) ? $status->json['state'] : 'ERROR'];
        }
        PrestashopGopayLog::insert_log($log);

        if ($status->json['state'] != 'REFUNDED' && $response->json['result'] != 'FINISHED') {
            return [false, $status->json['state']];
        } else {
            return [true, $status->json['state']];
        }
    }

    /**
     * Generate form with list of payment methods
     *
     * @param array $currency_id currency id
     *
     * @return false|string
     *
     * @since  1.0.0
     */
    protected function generateForm($currency_id)
    {
        $prestashopGopayOptions = new PrestashopGopayOptions();

        $currency = new Currency($currency_id);
        $payment_methods_currency = is_string(Configuration::get('GOPAY_PAYMENT_METHODS_' . $currency->iso_code))
            ? json_decode(Configuration::get('GOPAY_PAYMENT_METHODS_' . $currency->iso_code)) : [];
        $banks_currency = is_string(Configuration::get('GOPAY_BANKS_' . $currency->iso_code))
            ? json_decode(Configuration::get('GOPAY_BANKS_' . $currency->iso_code)) : [];

        $decription = Configuration::get('PRESTASHOPGOPAY_DESCRIPTION');
        $enabled_payment_methods = is_string(Configuration::get('PRESTASHOPGOPAY_PAYMENT_METHODS'))
            ? array_flip(json_decode(Configuration::get('PRESTASHOPGOPAY_PAYMENT_METHODS'))) : [];
        $enabled_banks = is_string(Configuration::get('PRESTASHOPGOPAY_BANKS'))
            ? array_flip(json_decode(Configuration::get('PRESTASHOPGOPAY_BANKS'))) : [];

        // Intersection of all selected and the supported
        $supported_payment_methods = [];
        foreach ($payment_methods_currency as $payment_method => $label_image) {
            if (array_key_exists($payment_method, $enabled_payment_methods)) {
                $supported_payment_methods[$payment_method] = [
                    'name' => $prestashopGopayOptions->supported_payment_methods()[$payment_method]['name'],
                    'image' => $label_image->image,
                ];
            }
        }
        if (
            array_key_exists('BANK_ACCOUNT', $supported_payment_methods) &&
            !Configuration::get('PRESTASHOPGOPAY_SIMPLIFIED')
        ) {
            unset($supported_payment_methods['BANK_ACCOUNT']);
            foreach ($banks_currency as $swift => $label_image) {
                if (array_key_exists($swift, $enabled_banks)) {
                    $supported_payment_methods[$swift] = [
                        'name' => $this->l($label_image->label),
                        'image' => $label_image->image,
                    ];
                }
            }
        }

        $this->context->smarty->assign([
            'action' => $this->context->link->getModuleLink($this->name, 'payment', [], true),
            'description' => $decription,
            'payment_methods' => $supported_payment_methods,
        ]);

        return $this->context->smarty->fetch(_PS_MODULE_DIR_ . $this->name . '/views/templates/front/payment_methods_form.tpl');
    }

    /**
     * Update payment methods and banks
     *
     * @since 1.0.0
     */
    public function update_payment_methods()
    {
        if (empty(Configuration::get('PRESTASHOPGOPAY_GOID'))) {
            return;
        }

        $this->check_enabled_on_GoPay();
    }

    /**
     * Check payment methods and banks that
     * are enabled on GoPay account.
     *
     * @since 1.0.0
     */
    public function check_enabled_on_GoPay()
    {
        $prestashopGopayOptions = new PrestashopGopayOptions();

        $payment_methods = [];
        $banks = [];
        foreach ($prestashopGopayOptions->supported_currencies() as $currency => $value) {
            $supported = PrestashopGopayApi::check_enabled_on_GoPay($currency);
            $payment_methods = $payment_methods + $supported[0];
            $banks = $banks + $supported[1];

            Configuration::updateValue('GOPAY_PAYMENT_METHODS_' . $currency, json_encode($supported[0]));
            Configuration::updateValue('GOPAY_BANKS_' . $currency, json_encode($supported[1]));
        }

        if (!empty($payment_methods)) {
            Configuration::updateValue('OPTION_GOPAY_PAYMENT_METHODS', json_encode($payment_methods));
        }
        if (!empty($banks)) {
            if (array_key_exists('OTHERS', $banks)) {
                // Send 'Others' to the end
                $other = $banks['OTHERS'];
                unset($banks['OTHERS']);
                $banks['OTHERS'] = $other;
            }

            Configuration::updateValue('OPTION_GOPAY_BANKS', json_encode($banks));
        }
    }

    /**
     * Order payment for older versions of PrestaShop
     *
     * @param array $params
     *
     * @since 1.0.0
     */
    public function hookPayment($params)
    {
        if (
            !$this->active || !Configuration::get('PRESTASHOPGOPAY_ENABLED') ||
            !$this->checkRestrictions($params['cart']->id)
        ) {
            return;
        }

        $this->context->smarty->assign([
            'this_path' => $this->_path,
            'this_path_ssl' => Tools::getShopDomainSsl(true, true) . __PS_BASE_URI__ . 'modules/' . $this->name . '/',
            'payment_methods_form' => $this->generateForm($params['cart']->id_currency),
            'payment_title' => 'Pay by ' . Configuration::get('PRESTASHOPGOPAY_TITLE'),
        ]);

        return $this->context->smarty->fetch(_PS_MODULE_DIR_ . $this->name . '/views/templates/hook/payment.tpl');
    }

    /**
     * Display order details
     *
     *  @param array $params
     *
     * @since 1.0.0
     */
    public function hookDisplayOrderDetail($params)
    {
        $order = $params['order'];

        if ($order->module == 'prestashopgopay' && !$order->hasBeenPaid()) {
            $is_retry = Configuration::get('PRESTASHOPGOPAY_PAYMENT_RETRY');

            if (!$is_retry) {
                $this->generateForm($order->id_currency);
            }

            $this->context->smarty->assign([
                'action' => $this->context->link->getModuleLink($this->name, 'paymentRetry', [], true),
                'order_id' => $order->id,
                'is_retry' => $is_retry,
                'payment_methods_form' => _PS_MODULE_DIR_ . $this->name . '/views/templates/front/payment_methods_form.tpl',
            ]);

            return $this->context->smarty->fetch(_PS_MODULE_DIR_ . $this->name . '/views/templates/front/payment_retry_form.tpl');
        } else {
            return false;
        }
    }
}
