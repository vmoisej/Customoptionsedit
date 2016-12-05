<?php
class MaxVision_Customoptionsedit_Helper_Data extends Mage_Core_Helper_Abstract
{
    const XML_PATH_IS_ENABLED      = 'maxvision_customoptionsedit/customoptions_setting/enabled';
	const XML_PATH_IS_ORDERHISTORY = 'maxvision_customoptionsedit/customoptions_setting/orderhistory';
	const NAME_CONTROLLER = 'sales_order';

    public function isCustomOptionsEditEnabled()
    {
        return Mage::getStoreConfig(self::XML_PATH_IS_ENABLED, Mage::app()->getStore()->getId());
    }

	public function isAddNoticeToOrderHistory()
	{
		return Mage::getStoreConfig(self::XML_PATH_IS_ORDERHISTORY, Mage::app()->getStore()->getId());
	}

	/**
	 * Compare custom options of buyRequest with existing buyRequest
	 *
	 * @param array $originalRequest original buyRequest in array form
	 * @param array $newRequest  buyRequest with updated custom options in array form
	 * @return boolean
	 */
	public function compareBuyRequests(array $originalRequest, array $newRequest)
	{
        foreach($newRequest['options'] as $key=>$option) {
            if ($option != $originalRequest['options'][$key])
                return true;
        }

		return false;
	}

	/**
	 * Merges custom options from buyRequest into existing buyRequest
	 *
	 * @param array $originalRequest original buyRequest in array form
	 * @param array $updateRequest   buyRequest with updated custom options in array form
	 * @return Varien_Object
	 */
	public function mergeBuyRequest(array $originalRequest, array $updateRequest)
	{
		// merge custom options
		// + operator instead of array_merge to handle duplicate numeric keys
		$originalRequest['options'] = $updateRequest['options'] + $originalRequest['options'];

		// merge additional request parameters (necessary for changed files)
		unset($updateRequest['options']);
		unset($updateRequest['qty']);

		return new Varien_Object($updateRequest + $originalRequest);
	}

	/**
	 * Returns true if final price of product depends on given option
	 *
	 * @param Mage_Catalog_Model_Product_Option $option
	 * @return boolean
	 */
	public function isOptionAffectingPrice(Mage_Catalog_Model_Product_Option $option)
	{
		foreach ($option->getValuesCollection() as $value) {
			/* @var $value Mage_Catalog_Model_Product_Option_Value */
			if ($value->getPrice() * 1 > 0) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Returns true if it is Sale_Order_controller
	 *
	 * @return boolean
	 */
    public function isSaleOrderView() {
	    if(Mage::app()->getRequest()->getControllerName() == self::NAME_CONTROLLER)
		    return true;

        return false;
    }

	/**
	 * Return custom options from item
	 *
	 * @see Mage_Sales_Block_Order_Item_Renderer_Default::getItemOptions()
	 * @return array
	 */
	protected function _getItemOptions(Mage_Sales_Model_Order_Item $item)
	{
		$result = array();
		if ($options = $item->getProductOptions()) {
			if (isset($options['options'])) {
				$result = array_merge($result, $options['options']);
			}
			if (isset($options['additional_options'])) {
				$result = array_merge($result, $options['additional_options']);
			}
			if (isset($options['attributes_info'])) {
				$result = array_merge($result, $options['attributes_info']);
			}
		}
		return $result;
	}
}
