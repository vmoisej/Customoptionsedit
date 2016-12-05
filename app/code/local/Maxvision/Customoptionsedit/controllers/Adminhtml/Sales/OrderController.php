<?php
require_once 'Mage/Adminhtml/controllers/Sales/OrderController.php';
class MaxVision_Customoptionsedit_Adminhtml_Sales_OrderController extends Mage_Adminhtml_Sales_OrderController
{
	protected $_orderItem;

	/**
	 * Update custom options and get new content
	 *
	 */
	protected  function loadBlockAction()
	{
		$request = $this->getRequest();

		try {
			$this->_updateCustomOptions();
		}
		catch (Mage_Core_Exception $e){
			$this->_getSession()->addError($e->getMessage());
		}
		catch (Exception $e){
			$this->_getSession()->addException($e, $e->getMessage());
		}

		$block = $request->getParam('block');       // custom_options
		$itemId = $request->getParam('item_id');

		if( $block == 'custom_options' && $itemId) {
			$result = $this->_getNewContentCustomOptions($itemId);
		} else {
            return $this;
		}
		Mage::getSingleton('adminhtml/session')->setUpdateResult($result);
	}

	/**
	 * Show item update result from loadBlockAction
	 * to prevent popup alert with resend data question
	 *
	 */
	public function showUpdateResultAction()
	{
		$session = Mage::getSingleton('adminhtml/session');
		if ($session->hasUpdateResult() && is_scalar($session->getUpdateResult())){
			$this->getResponse()->setBody($session->getUpdateResult());
			$session->unsUpdateResult();
		} else {
			$session->unsUpdateResult();
			return false;
		}
	}

	protected function _getNewContentCustomOptions($itemId)
	{
		$orderitem = Mage::getModel('sales/order_item')->load($itemId);

		$itemId = $orderitem->getItemId();
		$quoteItemId = $orderitem->getQuoteItemId();

		$block = $this->getLayout()->createBlock('adminhtml/sales_order_view_items_renderer_default');
		$content = "";
		if ($block->canDisplayContainer()){
			$content .= '<div id="order_item_'. $itemId . '" class="item-container">';
		}
		$content .= '<div class="item-text">';
		$content .= $block->getColumnHtml($orderitem, 'name');
		$content .= '</div>';
		if ($block->canDisplayContainer()){
			$content .= '</div>';
		}

		$content .= '<div class="button-item-container">';
		$content .= '<button id="my_button" title="Configure" type="button" class="scalable" onclick="options.showQuoteItemConfiguration(' . $itemId . ',' . $quoteItemId  . ')">';
		$content .= '<span><span><span>Configure</span></span></span></button></div>';

		return $content;
	}

	/*
	 * Ajax handler to response configuration fieldset of composite product in quote items
	 *
	 * @return Mage_Adminhtml_Sales_Order_CreateController
	 */
	public function configureQuoteItemsAction()
	{
		// Prepare data
		$configureResult = new Varien_Object();
		try {
			$quoteItemId = (int) $this->getRequest()->getParam('id');
			if (!$quoteItemId) {
				Mage::throwException($this->__('Quote item id is not received.'));
			}

			$quoteItem = Mage::getModel('sales/quote_item')->load($quoteItemId);
			if (!$quoteItem->getId()) {
				Mage::throwException($this->__('Quote item is not loaded.'));
			}

			$configureResult->setOk(true);
			$optionCollection = Mage::getModel('sales/quote_item_option')->getCollection()
			                        ->addItemFilter(array($quoteItemId));
			$quoteItem->setOptions($optionCollection->getOptionsByItem($quoteItem));

			$configureResult->setBuyRequest($quoteItem->getBuyRequest());
			$configureResult->setCurrentStoreId($quoteItem->getStoreId());
			$configureResult->setProductId($quoteItem->getProductId());
			$sessionQuote = Mage::getSingleton('adminhtml/session_quote');
			$configureResult->setCurrentCustomerId($sessionQuote->getCustomerId());

		} catch (Exception $e) {
			$configureResult->setError(true);
			$configureResult->setMessage($e->getMessage());
		}
		// Render page
		/* @var $helper Mage_Adminhtml_Helper_Catalog_Product_Composite */
		$helper = Mage::helper('adminhtml/catalog_product_composite');
		$helper->renderConfigureResult($this, $configureResult);

		return $this;
	}

	/**
	 * Post action to update order item
	 *
	 * similar to Mage_Checkout_CartController::updateItemOptionsAction(), but saves the quote
	 * directy without using the cart and then copies the updated options to the order
	 */
	protected function _updateCustomOptions() {

		if($updatedqi = $this->_updateQuoteItem()) {                 // updates custom options in quote item

			$this->_updateOrderItem($updatedqi);                     // update byRequest in order_item

			if ($this->_helper()->isAddNoticeToOrderHistory())
			    $this->_addStatusHistoryComment();                   // add history comment if you need
		}
	}

	/**
	 * Updates custom options in quote item based on request
	 *
	 * @return Mage_Sales_Model_Quote_Item
	 */
	protected function _updateQuoteItem()
	{
		$_order = $this->_getOrderItem()->getOrder();
		$quoteId = $_order->getQuoteId();
		$storeId = $this->_getOrderItem()->getStoreId();

		/* @var $quote Mage_Sales_Model_Quote */
		$quote = Mage::getModel('sales/quote')->setStoreId($storeId)->load($quoteId);

		if($this->_getUpdatedBuyRequest()) {
			$quoteItem = $quote->updateItem( $this->_getOrderItem()->getQuoteItemId(),	$this->_getUpdatedBuyRequest());
			try {
				$quoteItem->save();
			} catch (Exception $e) {
				$this->_getSession()->addException($e, $e->getMessage());
			}

			return $quoteItem;
		}

        return false;
	}

	/**
	 * Returns the buyRequest object with updated custom options.
	 *
	 * @return Varien_Object
	 */
	protected function _getUpdatedBuyRequest()
	{
		$options = $this->_getOrderItem()->getProductOptions();

		$originalRequest = $options['info_buyRequest'];
		$item = $this->getRequest()->getParam('item');

		$quoteItemId = $this->_getOrderItem()->getQuoteItemId();
		if(!$item[$quoteItemId]['configured'] || $item[$quoteItemId]['configured'] != 1 )
            return false;

		$newRequest = $item[$quoteItemId];

		foreach ($options['options'] as $_option) {
			// unset options that affect price in request
			if ($this->_helper()->isOptionAffectingPrice($this->_getOrderItem()->getProduct()->getOptionById($_option['option_id']))) {
				unset($newRequest['options'][$_option['option_id']]);
			}
			// unset generated file data in original buyRequest
			if ($_option['option_type'] === 'file') {
				unset($originalRequest['options'][$_option['option_id']]);
			}
		}

        if( $this->_helper()->compareBuyRequests($originalRequest, $newRequest) ) {
	        $merge_br = $this->_helper()->mergeBuyRequest($originalRequest, $newRequest);

	        return $merge_br;
        }

        return false;
	}

	/**
	 * Updates custom options in order item based on updated quote item
	 *
	 * @return MaxVision_Customoptionsedit_OrderController
	 */
	protected function _updateOrderItem(Mage_Sales_Model_Quote_Item $quoteItem)
	{
		$tmpOrderItem = Mage::getModel('sales/convert_quote')->itemToOrderItem($quoteItem);

        $this->_getOrderItem()
             ->setProductOptions($tmpOrderItem->getProductOptions())
             ->setSku($tmpOrderItem->getSku())
             ->setQuoteItemId($quoteItem->getId())
             ->save();

		return $this;
	}

	/**
	 * Add comment in order history
	 *
	 * @return MaxVision_Customoptionsedit_OrderController
	 */
	protected function _addStatusHistoryComment()
	{
		$this->_getOrderItem()->getOrder()
		     ->addStatusHistoryComment($this->__('Updated Custom Options'))
		     ->save();

		return $this;
	}

	/**
	 * Returns the order item
	 *
	 * @return Mage_Sales_Model_Order_Item
	 */
	protected function _getOrderItem()
	{
		if ($this->_orderItem === null) {
			$itemId = (int) $this->getRequest()->getParam('item_id');
			if ($itemId === 0) {
				Mage::throwException('No order item ID specified');
			}

     		$orderitem = Mage::getModel('sales/order_item')->load($itemId);
			if ($orderitem->getItemId() == $itemId) {
				$this->_orderItem = $orderitem;
			} else {
				Mage::throwException("Order item ID {$itemId} not found");
			}
		}
		return $this->_orderItem;
	}

	/**
	 * Return module helper
	 *
	 * @return MaxVision_Customoptionsedit_Helper_Data
	 */
	protected function _helper()
	{
		return Mage::helper('customoptionsedit');
	}

	/**
	 * Return session instance
	 *
	 * @return Mage_Core_Model_Session
	 */
	protected function _getSession()
	{
		return Mage::getSingleton('adminhtml/session');
	}
}
