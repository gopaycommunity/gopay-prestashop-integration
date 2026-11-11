<?php
/**
 * @author    GoPay
 * @copyright 2022 GoPay
 * @license   https://www.gnu.org/licenses/gpl-2.0.html  GPLv2 or later
 *
 * @see       https://www.gopay.com/
 * @since     1.0.0
 */
class AdminPrestaShopGoPayLogController extends ModuleAdminController
{
    /** @var string */
    protected $db_log_table = 'gopay_log';

    public function __construct()
    {
        parent::__construct();
    }

    public function initContent()
    {
        $this->display = 'view';
        $this->meta_title = 'PrestaShop GoPay Log';
        $this->show_toolbar = true;

        $this->context->controller->addJS(_PS_MODULE_DIR_ . 'prestashopgopay/views/js/menu.js');
        $this->context->controller->addCSS(_PS_MODULE_DIR_ . 'prestashopgopay/views/css/menu.css');

        parent::initContent();
    }

    public function initToolBarTitle()
    {
        $this->toolbar_title = 'Log';
    }

    public function initToolBar()
    {
        return true;
    }

    public function renderView()
    {
        $pagenum = Tools::getValue('pagenum');
        $log_table_filter = Tools::getValue('log_table_filter');

        $rows = Db::getInstance()->executeS(sprintf(
            "SELECT COUNT(*) as num_rows FROM %s%s
			WHERE UPPER(CONCAT(order_id, transaction_id, message, created_at, log_level, log))
                REGEXP '[\w\W]*%s[\w\W]*'",
            _DB_PREFIX_,
            $this->db_log_table,
            pSQL(strtoupper($log_table_filter))
        ));

        $results_per_page = 20;
        $number_of_rows = $rows[0]['num_rows'];
        $number_of_pages = ceil($number_of_rows / $results_per_page);

        if (null === $pagenum || false === $pagenum || $pagenum > $number_of_pages) {
            $pagenum = 1;
        }

        $page_pagination = ($pagenum - 1) * $results_per_page;
        $log_data = $page_pagination >= 0 ? Db::getInstance()->executeS(
            sprintf(
                "SELECT * FROM %s%s WHERE UPPER(CONCAT(order_id, transaction_id, message, created_at, log_level, log))
                REGEXP '[\w\W]*%s[\w\W]*' ORDER BY created_at DESC LIMIT %d,%d",
                _DB_PREFIX_,
                $this->db_log_table,
                pSQL(strtoupper($log_table_filter)),
                $page_pagination,
                $results_per_page
            )
        ) : [];

        $orders_link = $this->context->link->getAdminLink('AdminOrders');
        $orders_link = str_replace('/?', '?', $orders_link);
        $this->context->smarty->assign([
            'log_data' => $log_data,
            'orders_link' => $orders_link,
            'pagenum' => $pagenum,
            'log_table_filter' => $log_table_filter,
            'number_of_pages' => $number_of_pages,
        ]);

        return $this->context->smarty->fetch(_PS_MODULE_DIR_ . 'prestashopgopay/views/templates/admin/log.tpl');
    }
}
