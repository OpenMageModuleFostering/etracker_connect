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
class Etracker_Etracker_Block_Track extends Mage_Core_Block_Template
{
    /* Local cache */
    private $_cacheOrders = null;


    /**
     * Get a specific page name (may be customized via layout)
     *
     * @return string|null
     */
    public function getPageName() {
        $value = '';
		$route = $this->getRequest()->getRouteName();
		/* Try CMS */
        if ($route == 'cms') {
            $value = Mage::getSingleton('cms/page')->getTitle();
            if (empty($value)) {
                $value = Mage::getSingleton('cms/page')->getIdentifier();
            }
        }
        /* Try get from product */
        /** @var $product Mage_Catalog_Model_Product */
        if ($product = Mage::registry('current_product')) {
            $value = $product->getName().'-'.$product->getSku();
        }
        /* Try get from category */
        /** @var $cat Mage_Catalog_Model_Category */
        if (Mage::registry('current_category') && !$product) {
            $value = Mage::registry('current_category')->getName();
        }
        /* Try get from head title */
        if (empty($value)) {
            $headBlock = $this->getLayout()->getBlock('head');
            if ($headBlock) $value = $headBlock->getTitle();
        }
        if (empty($value)) {
            Etracker_Etracker_Helper_Data::log("Unable getting page name for req {$this->getRequest()->getRequestUri()}", Zend_Log::WARN);
        }
		/* Trim 'Searchresult' page title to the first word only */
		if (strpos($route, 'search') !== false) {
			$parts = explode(' ', $value);
			if (count($parts)) $value = $parts[0];
		}

        if ($prefix = Mage::getStoreConfig('design/head/title_prefix')) {
            if (strncmp($prefix, $value, strlen($prefix))===0) $value = substr($value, strlen($prefix)+1);
        }
        if ($suffix = Mage::getStoreConfig('design/head/title_suffix')) {
            if (preg_match('/.*'.$suffix.'/', $value)) $value = substr($value, 0, strlen($value) - (strlen($suffix)+1));
        }

        if ($this->isHomePage()) $value = '__INDEX__'.$value;
        return $value;
    }

    /**
     * @param $eventName string The name of the event
	 * @param $putToRegistryAfter In case the flag should still be available even after it has been removed from session,
	 * 			pass true.
     * @return bool|mixed The registered value, false if nothing found
     */
    public function isEventRegistered($eventName, $putToRegistryAfter=false) {
        if ($value = Mage::registry($eventName)) {
            return $value;
        }
        $session = Mage::getSingleton('customer/session');
        if ($value = $session->getData($eventName)) {
            $session->unsetData($eventName);
			if ($putToRegistryAfter) Mage::register($eventName, $value);
            return $value;
        }
        return false;
    }

    /**
     * @return string
     */
    public function getAreas() {
        $hlp = Mage::helper('etracker');
        $value = Mage::getStoreConfig('etracker/standard/storeviewcode') ? ucfirst($hlp->__(Mage::app()->getStore()->getCode())) : '';
        $value .= strlen($value) ? '/' : '';
        /* In case of product navigation... */
        $product = Mage::registry('current_product');
        if ($product) {
            $value .= $hlp->__('Products').'/';
        }
        /** @var $cat Mage_Catalog_Model_Category */
        /** @var $category Mage_Catalog_Model_Category */
        if ($cat = Mage::registry('current_category')) {
            if (!$product) {
                $value .= $hlp->__('Categories').'/';
            }
			$catPath = $this->getCategoryPath($cat);
            $value .= $catPath;
            Etracker_Etracker_Helper_Data::setToSession(
                Etracker_Etracker_Helper_Data::SESS_KEY_LAST_CAT_VIEW, 'path', $catPath);
            if ($product) {
                Etracker_Etracker_Helper_Data::setToSession(
                    Etracker_Etracker_Helper_Data::SESS_KEY_LAST_CAT_PROD_VIEW, $product->getId(), $catPath);
            } else
                Etracker_Etracker_Helper_Data::removeFromSession(Etracker_Etracker_Helper_Data::SESS_KEY_LAST_CAT_PROD_VIEW);
            return $value;
        }
        /* In all other cases... */
        $module = $this->getRequest()->getModuleName();
        if ($module != 'catalog') {
            $value .= $hlp->__(ucfirst($module));
            $controllerName = $this->getRequest()->getControllerName();
            if ($controllerName && $controllerName != 'index' && $controllerName != 'page') {
                $value .= '/'.ucfirst($hlp->__($controllerName));
            }
        }
        return $value;
    }

	/**
	 * Returns the path to the given category (incl.).
	 * @param $category Mage_Catalog_Model_Category
	 * @return string
	 */
	private function getCategoryPath($category) {
		$cats = $category->getParentCategories();
		$catPath = '';
		$i=1; foreach($cats as $category) {
			$catPath .= $category->getName();
			$catPath .= ($i<count($cats)) ? '/' :'';
			$i++;
		}
		return $catPath;
	}

    /**
	 * Checks whether current request returns home page. <br/>
	 * <b>Note:</b> As this cannot be identified safely, we simply check for 'home' CMS-Page additionally.
     * @return bool
     */
    public function isHomePage() {
        $isHome = $this->getUrl('') == $this->getUrl('*/*/*', array('_current'=>true, '_use_rewrite'=>true));
		if (!$isHome) {
			$routeName = Mage::app()->getRequest()->getRouteName();
			$identifier = Mage::getSingleton('cms/page')->getIdentifier();
			$isHome = ($routeName == 'cms' && $identifier == 'home');
		}
        return $isHome;
    }

    /**
     * @return string the configured Security-Code
     */
    public function getSecurityCode() {
        $code = Mage::helper('etracker')->getSecurityCode();
        if (empty($code)) {
            Etracker_Etracker_Helper_Data::log('No security code configured!', Zend_Log::ERR);
        }
        return $code;
    }

    /**
     * @return string The configured CC-Key (may be null/empty)
     */
    public function getCcKey() {
        $code = Mage::helper('etracker')->getCcKey();
        return $code;
    }

    /**
     * @return bool true if tracking code is to be included, false otherwise
     */
    public function isTrackingCodeToBeIncluded() {
        $isStandardActive = (bool) Mage::getStoreConfig('etracker/standard/active');
        return $isStandardActive && $this->isEnabled();
    }

	/**
	 * Returns true in case the tracking code can be delivered, false otherwise.
	 * @return bool
	 */
	public function isEnabled() {
		$code = $this->getSecurityCode();
		return !empty($code);
	}

    /**
     * @return bool true if async (and standard) is activated, false otherwise
     */
    public function isAsyncActive() {
        $isAsyncActive = false; //(bool) Mage::getStoreConfig('etracker/standard/async');
        return $isAsyncActive && $this->isEnabled();
    }

    /**
     * @return bool True if ecommerce (PPR) part is active
     */
    public function isPprActive() {
        $isPprActive = true; //(bool) Mage::getStoreConfig('etracker/ecommerce/active');
        return $isPprActive && $this->isEnabled();
    }

    /**
     * @return bool True if debug is active
     */
    public function isPprDebugActive() {
        $isPprActive = (bool) Mage::getStoreConfig('etracker/developer/debug');
        return $isPprActive && $this->isPprActive();
    }

    /**
     * @return string Contains a ';'-separated string of the
     */
    protected function _getOrdersBasketTrackingCode()
    {
        $isTaxInclRequired = Mage::getStoreConfig('etracker/general/tax');
        $orders = $this->getOrders();
		if (is_null($orders)) $orders = array();
        $result = "";
        foreach ($orders as $order) {
            /* @var $item Mage_Sales_Model_Order_Item */
            foreach ($order->getAllVisibleItems() as $item) {
            	if (strlen($result)>0) $result .= ";";
            	$cat = "";
                $product = Mage::getModel('catalog/product')->load($item->getProductId());
                if ($product && $product->getId()) {
                    if ($catIds = $product->getCategoryIds()) {
                        $category = Mage::getModel("catalog/category")->load(end($catIds));
                        if ($category && $category->getId()) {
                            $cat = str_replace(array(',', ';'), ' ', $category->getName());
                        }
                    }
                    $name = str_replace(array(',', ';'), ' ', $item->getName());
                    $price = $isTaxInclRequired ? $item->getPriceInclTax() : $item->getPrice();
                    $result .= sprintf("%s,%s,%s,%s,%s",
                        $this->jsQuoteEscape($item->getSku()),
                        $this->jsQuoteEscape($name),
                        $this->jsQuoteEscape($cat),
                        number_format($item->getQtyOrdered(), 4, '.', ''),
                        Etracker_Etracker_Helper_Data::priceFormat($price)
                    );
                }
            }
			/* Shipment */
			$shipmentData = $this->getShipmentData($order);
			if (!empty($shipmentData)) {
				$result .= ';';
				$result .= sprintf("%s,%s,%s,%s,%s",
					$this->jsQuoteEscape($shipmentData['id']),
					$this->jsQuoteEscape($shipmentData['name']),
					$this->jsQuoteEscape(reset($shipmentData['category'])),
					number_format(1, 4, '.', ''),
					Etracker_Etracker_Helper_Data::priceFormat($shipmentData['price'])
				);
			}
			/* Discounts */
			$discountData = $this->getDiscountData($order);
			if (!empty($discountData)) {
				$result .= ';';
				$result .= sprintf("%s,%s,%s,%s,%s",
					$this->jsQuoteEscape($discountData['id']),
					$this->jsQuoteEscape($discountData['name']),
					$this->jsQuoteEscape(reset($discountData['category'])),
					number_format(1, 4, '.', ''),
					Etracker_Etracker_Helper_Data::priceFormat($discountData['price'])
				);
			}
        }
        return $result;
    }

    /**
     * 0=>Lead, 1=>Sale, 2=Vollstorno
     * @return string the etracker sale type
     */
    protected function _getOrderTsale() {
        if ($order = $this->getOrder()) {
            return '1';
        }
        return '0';
    }

    /**
     * @return bool|Mage_Sales_Model_Order
     */
    private function getOrder() {
        $orders = $this->getOrders();
        if (!empty($orders)) {
            return reset($orders);
        }
        return false;
    }

    /**
     * @return null|array(Mage_Sales_Model_Order)
     */
    private function getOrders() {
        if (!is_null($this->_cacheOrders)) {
            return $this->_cacheOrders;
        }
        $orderIds = Mage::registry('current_order_ids');
        if ($orderIds) {
            $collection = Mage::getResourceModel('sales/order_collection')
                ->addFieldToFilter('entity_id', array('in' => $orderIds));
            if (!$collection->count()) {
                Etracker_Etracker_Helper_Data::log('No valid order found in registry!', Zend_Log::ERR);
            }
            foreach($collection as $order) {
                $this->_cacheOrders[] = $order;
            }
        }
        return $this->_cacheOrders;
    }

    /**
     * @return bool|Mage_Sales_Model_Order Returns the increment ID of the first found order. Returns false if no order available.
     */
    protected function _getOrdersIdTrackingCode()
    {
    	$orders = $this->getOrders();
        if (!empty($orders)) {
            $order = reset($orders);
            return ($order) ? $order->getIncrementId() : false;
        }
    	return false;
    }    

    /**
     * @return string
     */
    protected function _getOrdersValueTrackingCode()
    {
        $isTaxInclRequired = Mage::getStoreConfig('etracker/general/tax');
    	$orders = $this->getOrders();
		if (is_null($orders)) $orders = array();
    	$result = 0;
    	foreach ($orders as $order) {
    	    /* @var $order Mage_Sales_Model_Order */
            $result += $isTaxInclRequired ? $order->getBaseGrandTotal() : $order->getBaseGrandTotal() - $order->getBaseTaxAmount();
    	}
    	return $result;
    }

	/**
	 * Returns true in case we're in checkout's basket view. Additionally, the view is stored into session for later
	 * usage in checkout process.
	 * @return bool
	 */
	public function isCheckoutCartView() {
		$isCheckout = $this->isCheckout('cart');
		if ($isCheckout) {
			$session = Mage::getSingleton('core/session');
			$session->setData(Etracker_Etracker_Helper_Data::SESS_KEY_LAST_CART_VIEW, 1);
		}
		return $isCheckout;
	}

    /**
     * @param string The checkout type: 'onepage' or 'multishipping'
     * @return bool True in case the checkout (module) is used
     */
    public function isCheckout($type = null) {
        $moduleName = $this->getRequest()->getModuleName();
        $controllerName = $this->getRequest()->getControllerName();
		if (!is_null($type)) {
        	return $moduleName == 'checkout' && $controllerName == $type;
		} else {
			return $moduleName == 'checkout';
		}
    }

    /**
     * @return bool In case the checkout succeeded and is finished.
     */
    public function isCheckoutSuccess() {
        $orderId = $this->_getOrdersIdTrackingCode();
        return (bool) $orderId;
    }

    /**
     * @return string
     */
    protected function _getOrdersEtTarget() {
        $hlp = Mage::helper('etracker');
		$pre = $hlp->__('Cart');
        $pathPost = '';
        if ($this->isCheckout('multishipping')) {
            $pathPost .= $pre.'/'.$hlp->__('Multishipping');
            $steps = Mage::getSingleton('checkout/type_multishipping_state')->getSteps();
            $activeStep = $this->getCheckoutMultiShippingActiveStepKey($steps);
            foreach($steps as $step => $data) {
                $pathPost .= '/'. $data['label'];
                if ($step == $activeStep) break;
            }
        } else if ($this->isCheckout('onepage')) {
            $pathPost .= $pre.'/'.$hlp->__('Onepage');
            $steps = Mage::getBlockSingleton('checkout/onepage')->getSteps();
            foreach($steps as $step => $data) {
                $pathPost .= '/'. $data['label'];
            }
            if ($this->isCheckoutSuccess()) {
                $pathPost .= '/'.$hlp->__('Order Success');
            }
        } else if ($this->isCheckout(null)) {
			$pathPost = $pre;
		}
		$path = empty($pathPost) ? '' : $pathPost;
        return $path;
    }

    /**
     * Returns the key name of the current active step for the multishipping checkout
     * @param $steps
     * @return bool|string
     */
    private function getCheckoutMultiShippingActiveStepKey($steps) {
        foreach($steps as $stepKey => $stepData) {
            if (isset($stepData['is_active']) && $stepData['is_active'] == true) {
                return $stepKey;
            }
        }
        return false;
    }

	/**
	 * @return bool True in case the first step of multishipping checkout is active, false otherwise
	 */
	public function isCheckoutMultiShippingFirstStep() {
		$steps = Mage::getSingleton('checkout/type_multishipping_state')->getSteps();
		$stepIdx = 0;
		foreach($steps as $stepKey => $stepData) {
			if ($stepIdx == 0 && isset($stepData['is_active']) && $stepData['is_active'] == true) {
				return true;
			}
			break;
		}
		return false;
	}

    /**
     * @return string The current quote item ID from the session, maybe empty string
     */
    public function getQuoteId($withLabel=true) {
        $session = Mage::getSingleton('checkout/session');
        /* @var $cart Mage_Sales_Model_Quote */
        $cart = $session->getQuote();
        if ($cart && $cart->getId()) {
            return $withLabel ? Mage::helper('etracker')->__('Cart').'#'.$cart->getId() : $cart->getId();
        }
        if ($order = $this->getOrder()) {
            return $withLabel ? Mage::helper('etracker')->__('Cart').'#'.$order->getQuoteId() : $order->getQuoteId();
        }
        return '-';
    }

    /**
     * @return array|bool The relevant data as an array:
     * 'data' => array()<product data>
     * 'qty' => product quantity
     * Returns false if no product has been put to basket
     */
    public function getPprInsertToBasketProductData() {
        $session = Mage::getSingleton('customer/session');
        $products = $session->getData('event_sales_quote_add_item_product');
        $items = array();
        if (!empty($products)) {
            $session->unsetData('event_sales_quote_add_item_product');
            foreach($products as $productId => $qty) {
                $product = Mage::getModel('catalog/product')->load($productId);
                if ($product && $product->getId()) {
                    $data = array('data' => $this->getProductData($product), 'qty' => $qty);
                    $items[] = $data;
                }
            }
            if (!empty($items)) return $items;
        }
        return false;
    }

    /**
     * @return array|bool The relevant product data as key/value pairs, false if no product has been removed from basket
     */
    public function getPprRemoveFromBasketProductData() {
        $session = Mage::getSingleton('customer/session');
        $products = $session->getData('event_sales_quote_remove_item_product');
        $items = array();
        if (!empty($products)) {
            $session->unsetData('event_sales_quote_remove_item_product');
            foreach($products as $productId => $qty) {
                $product = Mage::getModel('catalog/product')->load($productId);
                $data = array('data' => $this->getProductData($product), 'qty' => $qty);
                $items[] = $data;
            }
            if (!empty($items)) return $items;
        }
        return false;
    }

    /**
     * @return array|bool The relevant product data as key/value pairs, false if no current product
     */
    public function getPprViewProductData() {
        /* @var $product Mage_Catalog_Model_Product */
        if ($product = Mage::registry('current_product')) {
            $data = $this->getProductData($product);
            return $data;
        }
        return false;
    }

    /**
     * Translates and escapes given text for usage in javascript context.
     * @param $text
     * @return mixed
     */
    public function jsTranslAndEscape($text) {
        $value = parent::__($text);
        return Etracker_Etracker_Helper_Data::iCharRepl($value);
    }

    /**
     * Returns the order in etracker required format (ready for json-encoding)
     * @return bool|array
     */
    public function getPprOrderData() {
        $isTaxInclRequired = Mage::getStoreConfig('etracker/general/tax');
        $order = $this->getOrder();
        if ($order && $order->getId()) {
            try {
                $group = Mage::getModel('customer/group')->load($order->getCustomerGroupId());
                $data = array();
                $data['orderNumber'] = $order->getIncrementId();
                $data['status'] = 'sale';
                $total = $isTaxInclRequired ? $order->getBaseGrandTotal() : $order->getBaseGrandTotal() - $order->getBaseTaxAmount();
                $data['orderPrice'] = Etracker_Etracker_Helper_Data::priceFormat($total);
                $data['currency'] = $order->getOrderCurrencyCode();
                $data['customerId'] = $order->getCustomerId() ? $order->getCustomerId() : '-';
                $customerGroup = ($group && $group->getId()) ? $group->getCustomerGroupCode() : '-';
                $data['customerGroup'] = Etracker_Etracker_Helper_Data::iCharRepl($customerGroup);
                $shipmentAddressData = $this->getAddressString($order->getShippingAddress());
                $data['customerAddress'] = $shipmentAddressData;
                $billingAddressData = $this->getAddressString($order->getBillingAddress());
                $data['invoiceAddress'] = $billingAddressData;
                $data['paymentMethod'] = $order->getPayment() ? $order->getPayment()->getMethod() : '-';
                $data['deliveryConditions'] = '-';
                $data['paymentConditions'] = '-';
                $data['shipType'] = $order->getShippingMethod() ? $order->getShippingMethod() : '';
                $costs = $isTaxInclRequired ? $order->getShippingInclTax() : $order->getShippingAmount();
                $data['shipCosts'] = Etracker_Etracker_Helper_Data::priceFormat($costs);
                $data['couponCodes'] = $order->getCouponCode() ? Etracker_Etracker_Helper_Data::iCharRepl($order->getCouponCode()) : '-';
                $data['giftPackage'] = array('-');
                $data['differenceData'] = 0;
                $products = array();
                /* @var $item Mage_Sales_Model_Order_Item */
                foreach($order->getAllVisibleItems() as $item) {
                    $dataItem = array();
                    $product = Mage::getModel('catalog/product')->load($item->getProductId());
                    $dataItem['product'] = $this->getProductData($product);
                    $dataItem['quantity'] = (int)$item->getQtyOrdered();
                    $products[] = $dataItem;
                }
                if ($order->getDiscountAmount() != 0) {
                    $dataItem['product'] = $this->getDiscountData($order);
                    $dataItem['quantity'] = (int)1;
                    $products[] = $dataItem;
                }
				$shipmentData = $this->getShipmentData($order);
				if (!empty($shipmentData)) {
					$dataItem['product'] = $shipmentData;
					$dataItem['quantity'] = (int)1;
					$products[] = $dataItem;
				}
                $basketId = Etracker_Etracker_Helper_Data::iCharRepl(Mage::helper('etracker')->__('Cart')).'#'.$order->getQuoteId();
                $data['basket'] = (object) array(
                    'id' => $basketId,
                    'products' => $products
                );
                return $data;
            } catch (Exception $e) {
                Etracker_Etracker_Helper_Data::log('Problem extracting data from order: '.$e->getMessage(), Zend_Log::ERR);
            }
        }
        return false;
    }

    /**
     * @param Mage_Sales_Model_Order_Address $address
     * @return string
     */
    private function getAddressString($address) {
        $value = '';
        if ($address && $address->getId()) {
            $value = $address->getCountryId();
            if ($address->getRegion()) $value .= ','.$address->getRegion();
            if ($address->getCity()) $value .= ','.$address->getCity();
        }
        return $value;
    }

    /**
     * @param Mage_Catalog_Model_Product $product
     * @return array
     */
    private function getProductData($product) {
        /* @var $categoryCurrent Mage_Catalog_Model_Category */
        $categoryCurrent = Mage::registry('current_category');
        $categoryFromCart = Etracker_Etracker_Helper_Data::getFromSession(
            Etracker_Etracker_Helper_Data::SESS_KEY_CART_PROD_CAT_MAP, $product->getId());

        $data = array();
        $data['id'] = $product->getSku();
        $data['name'] = Etracker_Etracker_Helper_Data::iCharRepl($product->getName());
		$cat = $categoryCurrent ? $this->pathToArray($this->getCategoryPath($categoryCurrent)) : array();
		if (empty($cat) && $categoryFromCart) {
			$cat = $this->pathToArray($categoryFromCart);
		}
        $data['category'] = $cat;
        $price = Etracker_Etracker_Helper_Data::getProductPrice($product);
        $data['price'] = Etracker_Etracker_Helper_Data::priceFormat($price);
        $data['currency'] = Mage::app()->getStore()->getCurrentCurrencyCode();
        $data['variants'] = (object) array(); //$product->getOptions();
        return $data;
    }

    /**
     * Generates an array from a given path (separated by '/') and performs a js-escape on each term.
     * @param $path
     * @return array|mixed
     */
    private function pathToArray($path) {
        $path = Etracker_Etracker_Helper_Data::iCharRepl($path);
        if (strpos($path, '/') !== false) {
            return explode('/', $path);
        }
        return array($path);
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @return array
     */
    private function getDiscountData($order) {
		$hlp = Mage::helper('etracker');
		$isTaxInclRequired = Mage::getStoreConfig('etracker/general/tax');
		$price = $isTaxInclRequired ? $order->getDiscountAmount() : $order->getDiscountAmount() - $order->getHiddenTaxAmount();
		$data = array();
		if ($price) {
			$id = $order->getIncrementId().($order->getCouponCode()? '-'.$order->getCouponCode() : '');
			$data['id'] = $id;
			$name = $order->getDiscountDescription() ? $order->getDiscountDescription().'('.$order->getCouponCode().')' : $hlp->__('Discount');
			$data['name'] = Etracker_Etracker_Helper_Data::iCharRepl($name);
			$cat = $order->getCouponCode() ? $hlp->__('Coupons') : $hlp->__('Discounts');
			$data['category'] = array(Etracker_Etracker_Helper_Data::iCharRepl($cat));
			$data['price'] = Etracker_Etracker_Helper_Data::priceFormat($price);
			$data['currency'] = $order->getOrderCurrencyCode(); // Mage::app()->getStore()->getCurrentCurrencyCode();
			$data['variants'] = (object) array();
		}
		return $data;
    }

	/**
	 * Returns the shipment data for the given order.
	 * @param Mage_Sales_Model_Order $order $order
	 * @return array May be empty
	 */
	private function getShipmentData($order) {
		$data = array();
		$isTaxInclRequired = Mage::getStoreConfig('etracker/general/tax');
		$amount = $isTaxInclRequired ? $order->getShippingInclTax() : $order->getShippingAmount();
		if ($amount) {
			$id = $order->getIncrementId().'-'.Mage::helper('etracker')->__('Shipment');
			$name = $order->getShippingMethod();
			$cat = Mage::helper('etracker')->__('Shipment');
			$data['id'] = $id;
			$data['name'] = $name;
			$data['category'] = array(Etracker_Etracker_Helper_Data::iCharRepl($cat));
			$data['price'] = $amount;
			$data['currency'] = $order->getOrderCurrencyCode();
			$data['variants'] = (object)array();
		}
		return $data;
	}
}
?>