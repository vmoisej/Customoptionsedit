<?php
class MaxVision_Customoptionsedit_Block_Adminhtml_Sales_Order_View_Items extends Mage_Adminhtml_Block_Sales_Order_View_Items
{

	public function getOrderDataJson()
	{
		$order = Mage::registry('current_order');
		if (!is_null($order->getCustomerId())) {
			$data['customer_id'] = $order->getCustomerId();
		}
		if (!is_null($order->getStoreId())) {
			$data['store_id'] = $order->getStoreId();
		}

		return Mage::helper('core')->jsonEncode($data);
	}

	/**
	 * Retrieve url for save data
	 * @return string
	 */
	public function getLoadBlockUrl()
	{
		return Mage::helper('adminhtml')->getUrl('*/sales_order/loadBlock');
	}

	/**
	 * Retrieve url for reloading custom options
	 * @return string
	 */
	public function getShowUpdateResultUrl()
	{
		return Mage::helper('adminhtml')->getUrl('*/sales_order/showUpdateResult');
	}

}
