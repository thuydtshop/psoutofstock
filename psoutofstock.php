<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

if (!defined('_PS_VERSION_')) {
    exit;
}

include_once dirname(__FILE__).'/PsOutstock.php';

class PsOutOfStock extends Module implements WidgetInterface
{

    public function __construct()
    {
        $this->name = 'psoutofstock';
        $this->version = '1.0.0';
        $this->author = 'alexbaysu07@gmail.com';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->tab = 'front_office_features';
        $this->controllers = array('outstock');

        parent::__construct();
        $this->displayName = $this->l('OutOfStock');
        $this->description = $this->l('Show products list back-in stock');

        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->defaults = array(
            'productsNb' => 0,
        );

    }

    public function install()
    {
        return parent::install() && $this->installDB() && $this->registerHook('actionUpdateQuantity');
    }

    public function installDB()
    {
        $return = true;
        $return &= Db::getInstance()->execute('
            CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'ps_out_stock` (
                `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `id_shop` int(10) unsigned NOT NULL ,
                `id_product` int NOT NULL,
                `id_product_attribute` int,
                `date_update` TIMESTAMP on update CURRENT_TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ,
                PRIMARY KEY (`id`)
            ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8 ;');


        return $return;
    }

    public function uninstall()
    {
        return $this->uninstallDB() && parent::uninstall();
    }

    public function uninstallDB()
    {
        return Db::getInstance()->execute('DROP TABLE IF EXISTS `'._DB_PREFIX_.'ps_out_stock`');
    }

    public function renderWidget($hookName = null, array $configuration = [])
    {
    }

    public function hookActionUpdateQuantity($params)
    {
        $id_product = (int) $params['id_product'];
        $id_product_attribute = (int) $params['id_product_attribute'];

        $quantity = (int) $params['quantity'];
        $context = Context::getContext();
        $id_shop = (int) $context->shop->id;

        if($quantity<1){
            $newStock=new PsOutstock();
            $newStock->id_product=$id_product;
            $newStock->id_product_attribute=$id_product_attribute;
            $newStock->id_shop=$id_shop;
            $newStock->date_update=date('Y-m-d');
            $newStock->add();
        }
    }

    public function getWidgetVariables($hookName = null, array $configuration = [])
    {
    }

}
