<?php
/**
 * Upgrade to 1.0.10
 * Register the hooks that were registered in the module constructor before
 * and remove the configuration keys saved by mistake from the settings form.
 *
 * @author    GoPay
 * @copyright 2022 GoPay
 * @license   https://www.gnu.org/licenses/gpl-2.0.html  GPLv2 or later
 *
 * @see       https://www.gopay.com/
 * @since     1.0.10
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

function upgrade_module_1_0_10($module)
{
    foreach (['token', '_token', 'submitprestashopgopay'] as $key) {
        Configuration::deleteByName($key);
    }

    return $module->registerHook('actionProductCancel') &&
        $module->registerHook('actionOrderSlipAdd') &&
        $module->registerHook('displayOrderDetail') &&
        $module->registerHook('displayAdminAfterHeader') &&
        $module->registerHook('displayCheckoutSummaryTop');
}
