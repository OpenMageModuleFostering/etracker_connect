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
class Etracker_Etracker_Block_Checkout_Cart extends Mage_Checkout_Block_Cart {

	/**
	 * To support version < 1.4.2 we add child block html on this way as we do not need to overwrite any template file.
	 * (This is a better solution and causes less problems together with other modules).
	 * @return string
	 */
	protected function _toHtml() {
		$html = parent::_toHtml();
		return Etracker_Etracker_Helper_Data::getDynamicChildBlockHtml($this, $html, 'etracker_cart');
	}
}