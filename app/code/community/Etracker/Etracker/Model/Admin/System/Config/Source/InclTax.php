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
class Etracker_Etracker_Model_Admin_System_Config_Source_InclTax
{
    public function toOptionArray()
    {
        return array(
            array('value'=>0, 'label'=>Mage::helper('etracker')->__('Excluding Tax')),
            array('value'=>1, 'label'=>Mage::helper('etracker')->__('Including Tax')),
        );
    }

}
