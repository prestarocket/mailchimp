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
    /** @var int $id_cart */
    public $id_cart;
    /** @var string $last_synced */
    public $last_synced;
    // @codingStandardsIgnoreEnd

    /**
     * Count Carts
     *
     * @param int|null $idShop    Shop ID
     * @param bool     $remaining Remaining carts only
     *
     * @return int
     * @since 1.1.0
     */
    public static function countCarts($idShop = null, $remaining = false)
    {
        if (!$idShop) {
            $idShop = \Context::getContext()->shop->id;
        }

        $fromDateTime = new \DateTime();
        $fromDateTime->modify('-1 day');

        $selectOrdersSql = new \DbQuery();
        $selectOrdersSql->select('`id_cart`');
        $selectOrdersSql->from('orders');

        $sql = new \DbQuery();
        $sql->select('COUNT(c.`id_cart`)');
        $sql->from('cart', 'c');
        $sql->where('c.`id_shop` = '.(int) $idShop);
        $sql->innerJoin('customer', 'cu', 'cu.`id_customer` = c.`id_customer`');
        $sql->leftJoin(bqSQL(self::$definition['table']), 'mc', 'mc.`id_cart` = c.`id_cart`');
        $sql->where('c.`date_upd` > \''.$fromDateTime->format('Y-m-d H:i:s').'\'');
        $sql->where('c.`id_cart` NOT IN ('.$selectOrdersSql->build().')');
        if ($remaining) {
            $cartsLastSynced = \Configuration::get(\MailChimp::CARTS_LAST_SYNC, null, null, $idShop);
            if ($cartsLastSynced) {
                $sql->where('mc.`last_synced` IS NULL OR mc.`last_synced` < c.`date_upd`');
                $sql->where('STR_TO_DATE(c.`date_upd`, \'%Y-%m-%d %H:%i:%s\') IS NOT NULL');
            }
        }

        return (int) \Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
    }

    /**
     * Get products
     *
     * @param int|null $idShop
     * @param int      $offset
     * @param int      $limit
     *
     * @param bool     $remaining
     *
     * @return array|false|\mysqli_result|null|\PDOStatement|resource
     * @since 1.1.0
     */
    public static function getCarts($idShop = null, $offset = 0, $limit = 0, $remaining = false)
    {
        if (!$idShop) {
            $idShop = \Context::getContext()->shop->id;
        }

        $fromDateTime = new \DateTime();
        $fromDateTime->modify('-1 day');

        $selectOrdersSql = new \DbQuery();
        $selectOrdersSql->select('`id_cart`');
        $selectOrdersSql->from('orders');

        $sql = new \DbQuery();
        $sql->select('c.*, cu.`email`, cu.`firstname`, cu.`lastname`, cu.`birthday`, cu.`newsletter`, cu.`id_lang`, mc.`last_synced`');
        $sql->from('cart', 'c');
        $sql->innerJoin('customer', 'cu', 'cu.`id_customer` = c.`id_customer`');
        $sql->innerJoin('lang', 'l', 'l.`id_lang` = cu.`id_lang`');
        $sql->leftJoin(bqSQL(self::$definition['table']), 'mc', 'mc.`id_cart` = c.`id_cart`');
        $sql->where('c.`id_shop` = '.(int) $idShop);
        $sql->where('c.`date_upd` > \''.$fromDateTime->format('Y-m-d H:i:s').'\'');
        $sql->where('c.`id_cart` NOT IN ('.$selectOrdersSql->build().')');
        if ($remaining) {
            $cartsLastSynced = \Configuration::get(\MailChimp::CARTS_LAST_SYNC, null, null, $idShop);
            if ($cartsLastSynced) {
                $sql->where('mc.`last_synced` IS NULL OR mc.`last_synced` < c.`date_upd`');
                $sql->where('STR_TO_DATE(c.`date_upd`, \'%Y-%m-%d %H:%i:%s\') IS NOT NULL');
            }
        }
        if ($limit) {
            $sql->limit($limit, $offset);
        }

        $results = \Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

        $defaultCurrency = \Currency::getDefaultCurrency();
        $defaultCurrencyCode = $defaultCurrency->iso_code;
        $mailChimpShop = MailChimpShop::getByShopId($idShop);
        if (!\Validate::isLoadedObject($mailChimpShop)) {
            return false;
        }
        $rate = 1;
        $tax = new \Tax($mailChimpShop->id_tax);
        if (\Validate::isLoadedObject($tax) && $tax->active) {
            $rate = 1 + ($tax->rate / 100);
        }
        foreach ($results as &$cart) {
            $cartObject = new \Cart($cart['id_cart']);

            $cart['currency_code'] = $defaultCurrencyCode;
            $cart['order_total'] = (float) ($cartObject->getOrderTotal(false) * $rate);
            $cart['checkout_url'] = \Context::getContext()->link->getPageLink(
                'order',
                false,
                (int) $cart['id_lang'],
                'step=3&recover_cart='.$cart['id_cart'].'&token_cart='.md5(_COOKIE_KEY_.'recover_cart_'.$cart['id_cart'])
            );

            $orderProducts = $cartObject->getProducts();

            $cart['lines'] = [];
            foreach ($orderProducts as &$cartProduct) {
                $cart['lines'][] = [
                    'id'                 => (string) $cartProduct['id_product'],
                    'product_id'         => (string) $cartProduct['id_product'],
                    'product_variant_id' => (string) $cartProduct['id_product_attribute'] ? $cartProduct['id_product'].'-'.$cartProduct['id_product_attribute'] : $cartProduct['id_product'],
                    'quantity'           => (int) $cartProduct['cart_quantity'],
                    'price'              => (float) ($cartProduct['price'] * $rate),
                ];
            }
        }

        return $results;
    }

    /**
     * Set synced
     *
     * @param array $range
     *
     * @return bool
     * @since 1.1.0
     */
    public static function setSynced($range)
    {
        if (empty($range)) {
            return false;
        }

        $insert = [];
        $now = date('Y-m-d H:i:s');
        foreach ($range as &$item) {
            $insert[] = [
                'id_cart'     => $item,
                'last_synced' => $now,
            ];
        }

        \Db::getInstance()->delete(
            bqSQL(self::$definition['table']),
            '`id_cart` IN ('.implode(',', $range).')',
            0,
            false
        );

        return \Db::getInstance()->insert(
            bqSQL(self::$definition['table']),
            $insert,
            false,
            false
        );
    }
}
