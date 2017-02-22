<?php
/**
 * 2017 Thirty Bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    Thirty Bees <modules@thirtybees.com>
 * @copyright 2017 Thirty Bees
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

namespace MailChimpModule;

if (!defined('_TB_VERSION_')) {
    exit;
}

/**
 * Class MailChimpProduct
 *
 * @package MailChimpModule
 *
 * @since 1.1.0
 */
class MailChimpCart extends \ObjectModel
{
    /**
     * @see ObjectModel::$definition
     *
     * @since 1.1.0
     */
    public static $definition = [
        'table'   => 'mailchimp_cart',
        'primary' => 'id_mailchimp_cart',
        'fields'  => [
            'id_cart'     => ['type' => self::TYPE_INT,    'validate' => 'isInt',    'required' => true,                                     'db_type' => 'INT(11) UNSIGNED'],
            'last_synced' => ['type' => self::TYPE_DATE,   'validate' => 'isBool',   'required' => true, 'default' => '1970-01-01 00:00:00', 'db_type' => 'DATETIME'        ],
        ],
    ];
    // @codingStandardsIgnoreStart
    public $id_cart;
    public $last_synced;
    // @codingStandardsIgnoreEnd

    /**
     *
     *
     * @param int|null $idShop
     */
    public static function countCarts($idShop = null)
    {
        if (!$idShop) {
            $idShop = \Context::getContext()->shop->id;
        }

        $sql = new \DbQuery();
        $sql->select('COUNT(c.`id_cart`)');
        $sql->from('cart', 'c');
        $sql->where('c.`id_shop` = '.(int) $idShop);

        return (int) \Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
    }

    /**
     * Get products
     *
     * @param int|null $idShop
     * @param int      $offset
     * @param int      $limit
     *
     * @return array|false|\mysqli_result|null|\PDOStatement|resource
     * @since 1.1.0
     */
    public static function getCarts($idShop = null, $offset = 0, $limit = 0)
    {
        if (!$idShop) {
            $idShop = \Context::getContext()->shop->id;
        }

        $sql = new \DbQuery();
        $sql->select('c.*, cu.`email`, cu.`firstname`, cu.`lastname`, cu.`newsletter`');
        $sql->from('cart', 'c');
        $sql->innerJoin('customer', 'cu', 'cu.`id_customer` = c.`id_customer`');
        $sql->where('c.`id_shop` = '.(int) $idShop);
        if ($offset || $limit) {
            $sql->limit($limit, $offset);
        }

        $results = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

        $defaultCurrency = \Currency::getDefaultCurrency();
        $defaultCurrencyCode = $defaultCurrency->iso_code;
        foreach ($results as &$cart) {
            $cartObj = new \Cart($cart['id_cart']);

            $cart['currency_code'] = $defaultCurrencyCode;
            $cart['order_total'] = $cartObj->getOrderTotal(true);

            $cart['lines'] = [];
            $sql = new \DbQuery();
            $sql->select('cp.*, ps.`price`');
            $sql->from('cart_product', 'cp');
            $sql->innerJoin('product_shop', 'ps', 'ps.`id_product` = cp.`id_product` AND ps.`id_shop` = '.(int) $idShop);
            $sql->where('cp.`id_cart` = '.(int) $cart['id_cart']);

            $cartProducts = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

            foreach ($cartProducts as &$cartProduct) {
                $cart['lines'][] = [
                    'id'                 => $cartProduct['id_product'],
                    'product_id'         => $cartProduct['id_product'],
                    'product_variant_id' => $cartProduct['id_product'],
                    'quantity'           => (int) $cartProduct['id_product'],
                    'price'              => (float) $cartProduct['price'],
                ];
            }
        }

        return $results;
    }
}