<?php
/**
 *
 * @category   Inovarti
 * @package    Inovarti_Pagarme
 * @author     Suporte <suporte@inovarti.com.br>
 */
class Inovarti_Pagarme_Transaction_BoletoController extends Mage_Core_Controller_Front_Action
{
	public function postbackAction()
	{
		$pagarme = Mage::getModel('pagarme/api');
		$request = $this->getRequest();

		$orderId = Mage::helper('pagarme')->getOrderIdByTransactionId($request->getPost('id'));
		$order = Mage::getModel('sales/order')->load($orderId);
				
		if ($request->isPost()
			&& $pagarme->validateFingerprint($request->getPost('id'), $request->getPost('fingerprint'))
		) 
		{
			if($request->getPost('current_status') == Inovarti_Pagarme_Model_Api::TRANSACTION_STATUS_PAID){

				if (!$order->canInvoice()) {
					Mage::log($this->__('The order does not allow creating an invoice.'), null, 'pagarme.log');
					Mage::throwException($this->__('The order does not allow creating an invoice.'));
				}

				$invoice = Mage::getModel('sales/service_order', $order)
					->prepareInvoice()
					->register()
					->pay();

				$sendEmail = Mage::getStoreConfig('payment/pagarme_boleto/email_status_change');

				$invoice->setEmailSent(true);
				$invoice->getOrder()->setIsInProcess(true);

				$transactionSave = Mage::getModel('core/resource_transaction')
					->addObject($invoice)
					->addObject($invoice->getOrder())
					->save();

				$invoice->sendEmail($sendEmail);
			}
			if($request->getPost('current_status') == Inovarti_Pagarme_Model_Api::TRANSACTION_STATUS_REFUNDED){
				foreach ($order->getInvoiceCollection() as $invoice) {
					if (!$invoice->canCancel()) {
						Mage::log($this->__('Invoice cannot be cancelled.'), null, 'pagarme.log');
					}
					$invoice->cancel();
				}
				$order->cancel()->save();
				$order->addStatusHistoryComment($this->__('Canceled by Pagarme via Boleto postback.'))->save();
			}
			$this->getResponse()->setBody('ok');
			return;
		}

		$this->_forward('404');
	}
}
