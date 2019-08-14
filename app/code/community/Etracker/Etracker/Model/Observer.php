<?php
/**
 * justselling Germany Ltd. EULA
 * http://www.justselling.de/
 * Read the license at http://www.justselling.de/lizenz
 *
 * Do not edit or add to this file, please refer to http://www.justselling.de for more information.
 *
 * @category    justselling
 * @package     justselling_etracker
 * @copyright   Copyright (c) 2013 justselling Germany Ltd. (http://www.justselling.de)
 * @license     http://www.justselling.de/lizenz
 * @author      Bodo Schulte
 */
?>
<?php

class Etracker_Etracker_Model_Observer
{

    private $_cacheCartProductCount = array();

    /**
     * @param Varien_Event_Observer $observer
     */
    public function onOrderSuccess(Varien_Event_Observer $observer) {
		$orderIds = $observer->getEvent()->getOrderIds();
		if (empty($orderIds) || !is_array($orderIds)) {
			/* Backwards-Compatibility to < 1.4.2 */
			$checkoutSingleton = Mage::getSingleton('checkout/type_onepage');
			if ($checkoutSingleton) {
				$process = $checkoutSingleton->getCheckout();
				if ($process) {
					$lastOrderId = $process->getLastOrderId();
					if ($lastOrderId) $orderIds = array($lastOrderId);
				}
			}
			if (empty($orderIds)) return;
		}
		Mage::register('current_order_ids', $orderIds);
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function onCustomerAuthenticated(Varien_Event_Observer $observer) {
        $customer = $observer['model'];
        $session = Mage::getSingleton('customer/session');
        $session->setData('event_customer_login', $customer ? $customer->getId() : '');
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function onSalesQuoteAddItem(Varien_Event_Observer $observer) {
        /* @var $item Mage_Sales_Model_Quote_Item */
        $item = $observer['quote_item'];
        if ($item) {
            Etracker_Etracker_Helper_Data::log('onSalesQuoteAddItem: '.$item->getId());

            /* To be used in PPR: insertToBasket/removeFromBasket identify product */
			$qty = $item->getTotalQty(); // may be null!
			if (!$qty) {
				$qty = Mage::app()->getRequest()->getParam('qty', 1);
			}
            Mage::getSingleton('customer/session')->setData('event_sales_quote_add_item_product',
                    array($item->getProductId() => $qty));

            /* To be used in PPR: provide relevant category the product has been picked from */
            $categoryPath = Etracker_Etracker_Helper_Data::getFromSession(
                Etracker_Etracker_Helper_Data::SESS_KEY_LAST_CAT_PROD_VIEW, $item->getProductId());
            if (!$categoryPath) {
                $categoryPath = Etracker_Etracker_Helper_Data::getFromSession(Etracker_Etracker_Helper_Data::SESS_KEY_LAST_CAT_VIEW, 'path');
            }
            if ($categoryPath) {
                Etracker_Etracker_Helper_Data::setToSession(
                    Etracker_Etracker_Helper_Data::SESS_KEY_CART_PROD_CAT_MAP, $item->getProductId(), $categoryPath);
            }
        }
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function onSalesQuoteRemoveItem(Varien_Event_Observer $observer) {
        /* @var $item Mage_Sales_Model_Quote_Item */
        $item = $observer['quote_item'];
        if ($item) {
            Etracker_Etracker_Helper_Data::log('onSalesQuoteRemoveItem: '.$item->getId());
            Mage::getSingleton('customer/session')->setData('event_sales_quote_remove_item_product',
                array($item->getProductId() => $item->getQty()));
        }
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function onCartUpdateItemsBefore(Varien_Event_Observer $observer) {
        /** @var $cart Mage_Checkout_Model_Cart */
        $cart = $observer['cart'];
        /** @var $item Mage_Sales_Model_Quote_Item */
        foreach ($cart->getItems() as $item) {
            $qty = $item->getQty() ? $item->getQty() : 1;
            $this->_cacheCartProductCount[$item->getProductId()] = $qty;
        }
    }

    /**
     * @param Varien_Event_Observer $observer
     */
    public function onCartUpdateItemsAfter(Varien_Event_Observer $observer) {
        /** @var $cart Mage_Checkout_Model_Cart */
        $cart = $observer['cart'];
        $session = Mage::getSingleton('customer/session');
        $itemsRemove = array();
        $itemsAdd = array();
        /** @var $item Mage_Sales_Model_Quote_Item */
        foreach ($cart->getItems() as $item) {
            $qty = $item->getQty();
            if (isset($this->_cacheCartProductCount[$item->getProductId()])) {
                $oldQty = $this->_cacheCartProductCount[$item->getProductId()];
                if ($oldQty > $qty) {
                    $itemsRemove[$item->getProductId()] = $oldQty - $qty;
                }
                if ($oldQty < $qty) {
                    $itemsAdd[$item->getProductId()] = $qty - $oldQty;
                }
            }
        }
        if (!empty($itemsRemove)) $session->setData('event_sales_quote_remove_item_product', $itemsRemove);
        if (!empty($itemsAdd)) $session->setData('event_sales_quote_add_item_product', $itemsAdd);
    }


    /**
     * @param Varien_Event_Observer $observer
     */
    public function onWishlistAddProduct(Varien_Event_Observer $observer) {
        $product = $observer['product'];
        $session = Mage::getSingleton('customer/session');
        $session->setData('event_wishlist_add_product', $product);
    }


    /**
     * Appends a layout-configured block dynamically to an existing one.
     * @param $observer
     * @return $this
     */
    public function afterBlockOutput($observer) {
        $block = $observer->getEvent()->getBlock();
		$isDynamicBlockSupported = Etracker_Etracker_Helper_Data::isDynamicBlockSupported();
        $transport = $observer->getEvent()->getTransport();
        if(empty($transport) || !$isDynamicBlockSupported) {
            return $this;
        }
        if ($block->getNameInLayout() == 'product.info.media' && $block->getChild('etracker_img')) {
            $this->appendChildToBlock($block, $transport, 'etracker_img');
        } elseif ($block->getNameInLayout() == 'checkout.onepage' && $block->getChild('etracker_onepage')) {
            $this->appendChildToBlock($block, $transport, 'etracker_onepage');
        } elseif ($block->getNameInLayout() == 'checkout.cart' && $block->getChild('etracker_cart')) {
            $this->appendChildToBlock($block, $transport, 'etracker_cart');
        }
        return $this;
    }

    /**
     * Appends the block html from the given child (childName) to the given block.
     * @param $block Mage_Core_Block_Abstract
     * @param $transport
     * @return $this
     */
    public function appendChildToBlock($block, $transport, $childName) {
        Etracker_Etracker_Helper_Data::log("Appending block '{$childName}'");
        $originalHtml = $transport->getHtml();
        $appendHtml = $block->getChildHtml($childName);
        $newHtml = $originalHtml."\n".$appendHtml;
        $transport->setHtml($newHtml);
        return $this;
    }
}
?>