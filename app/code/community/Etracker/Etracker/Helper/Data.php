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

class Etracker_Etracker_Helper_Data extends Mage_Core_Helper_Abstract {

    const SESS_KEY_LAST_CAT_VIEW = 'last_category_view';
    const SESS_KEY_LAST_CAT_PROD_VIEW = 'last_category_product_view';
    const SESS_KEY_CART_PROD_CAT_MAP = 'cart_category_product';
	const SESS_KEY_LAST_CART_VIEW = 'last_cart_view';

    /**
     * Returns the configured security code.
     *
     * @return string May be empty/null
     */
    public function getSecurityCode() {
        $accountId = Mage::getStoreConfig('etracker/general/account');
        return $accountId;
    }

    /**
     * Returns the configured cc code.
     *
     * @return string May be empty/null
     */
    public function getCcKey() {
        $id = Mage::getStoreConfig('etracker/general/cckey');
        return $id;
    }

    /**
     * Writes the given log message (to a separate log file).
     * @param $msg
     * @param int $level
     */
    public static function log($msg, $level=Zend_Log::DEBUG) {
        $force = (bool) Mage::getStoreConfig('etracker/developer/log');
        if ($level == Zend_Log::ERR || $level == Zend_Log::ALERT || $level == Zend_Log::CRIT) $force = true;
        Mage::log($msg, $level, 'etracker.log', $force);
    }

    /**
     * @param $value int|float the number to be formatted
     * @return string
     */
    public static function priceFormat($value) {
        return number_format($value, 2, '.', '');
    }

    /**
     * Calculates the price on base of the configurable tax (incl./excl.).
     * @param Mage_Catalog_Model_Product $product
     */
    public static function getProductPrice($product) {
        /* @var $taxHelper Mage_Tax_Helper_Data */
        $taxHelper = Mage::helper('tax');
        $isTaxInclRequired = (bool) Mage::getStoreConfig('etracker/general/tax');
        $productsInclTax = $taxHelper->priceIncludesTax($product->getStore());
        $finalPrice = $product->getFinalPrice();
        if ($productsInclTax) { // shop
            if (!$isTaxInclRequired) {
                //$price = $taxHelper->getPrice($product, $finalPrice, false);
                if ($product->getTaxClassId()) {
                    $taxAmount = self::calculateTax($product, $finalPrice, true);
                    return $finalPrice - $taxAmount;
                }
            }
        } else {
            if ($isTaxInclRequired) {
                if ($product->getTaxClassId()) {
                    $taxAmount = self::calculateTax($product, $finalPrice, false);
                    return $taxAmount + $finalPrice;
                }
            }
        }
        return $finalPrice;
    }


    /**
     * Calculates the tax on the give price.
     * @param $product
     * @param $price
     * @param $productInclTax If product price includes tax already
     * @return mixed
     */
    protected static function calculateTax($product, $price, $productInclTax) {
        $request = Mage::getSingleton('tax/calculation')->getRateRequest(null, null, null, $product->getStore());
        $percent = Mage::getSingleton('tax/calculation')->getRate($request->setProductClassId($product->getTaxClassId()));
        $calculator = Mage::getSingleton('tax/calculation');
        $taxAmount = $calculator->calcTaxAmount($price, $percent, $productInclTax, false);
        return $taxAmount;
    }

    /**
     * Sets the given key/value pair to the core/session using the given group as data holder.
     * @param $group The group-key the values is set to session
     * @param $key
     * @param $value
     * @return bool True on success, false otherwise
     */
    public static function setToSession($group, $key, $value) {
        if (empty($group) || empty($key)) return false;
        $session = Mage::getSingleton('core/session');
        $groupValues = $session->getData($group);
        if ($groupValues && is_array($groupValues)) {
            $groupValues[$key] = $value;
        } else {
            $groupValues = array();
            $groupValues[$key] = $value;
        }
        $session->setData($group, $groupValues);
        Etracker_Etracker_Helper_Data::log("Set to core/session [group] {$group}, [key] {$key}, [value] {$value}");
        return true;
    }

    /**
     * Returns the group values (array) for the give group key.
     * @param $group
     * @param null $key if set, the value for the given key is returned
     * @return bool|mixed
     */
    public static function getFromSession($group, $key=null) {
        if (empty($group)) return false;
        $session = Mage::getSingleton('core/session');
        $groupValues = $session->getData($group);
        if (is_null($key)) {
            return $groupValues;
        } else {
            Etracker_Etracker_Helper_Data::log("Fetch from core/session [group] {$group}, [key] {$key}, [value] {$groupValues[$key]}");
            return (is_array($groupValues) && isset($groupValues[$key])) ? $groupValues[$key] : false;
        }
    }

    /**
     * Removes the key from the given group in session. If key is null, the group will be removed.
     * @param $group
     * @param null $key
     * @return bool
     */
    public static function removeFromSession($group, $key=null) {
        if (empty($group)) return false;
        $session = Mage::getSingleton('core/session');
        if (is_null($key) && $session->hasData($group)) {
            $session->unsetData($group);
        }
        if (!is_null($key)) {
            $groupValues = $session->getData($group);
            if (isset($groupValues[$key])) {
                Etracker_Etracker_Helper_Data::log("Remove from core/session [group] {$group}, [key] {$key}");
                unset($groupValues[$key]);
            }
        }
    }

    /**
     * Replaces the characters ',",; for JavaScript escaping (by a space-character) using str_ireplace.
     * @param $value
     */
    public static function iCharRepl($value) {
        $value = Mage::helper('core')->jsQuoteEscape($value);
        return str_ireplace(array('"', ';'), ' ', $value);
    }

	/**
	 * To support version < 1.4.2 we need to disable use of "dynamic block" feature (see Observer) and use overwridden
	 * blocks.
	 * @return bool
	 */
	public static function isDynamicBlockSupported() {
		$version = Mage::getVersionInfo();
		if ($version['minor'] == 4 && $version['revision'] < 2 ) {
			return false;
		} else if ($version['minor'] < 4) {
			return false;
		} else
			return true;
	}

	/**
	 * To support version < 1.4.2 we add child block html on this way as we do not need to overwrite any template file.
	 * (This is a better solution and causes less problems together with other modules).
	 *
	 * @param Mage_Core_Block_Abstract $block
	 * @param $html
	 * @param $childBlockName
	 * @return string
	 */
	public static function getDynamicChildBlockHtml($block, $html, $childBlockName) {
		if (!self::isDynamicBlockSupported()) {
			if ($block->getChild($childBlockName)) {
				$html .= "\n".$block->getChildHtml($childBlockName);
			}
		}
		return $html;
	}
}
