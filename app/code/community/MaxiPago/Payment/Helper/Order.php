<?php

/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to contato@maxipago.com.br so we can send you a copy immediately.
 *
 * @category   maxiPago!
 * @package    MaxiPago_Payment
 * @author        Thiago Contardi <thiago@contardi.com.br>
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class MaxiPago_Payment_Helper_Order extends Mage_Core_Helper_Data
{
    protected $_helper;

    /**
     * @param Mage_Sales_Model_Order $order
     * @param Mage_Sales_Model_Order_Payment|Mage_Payment_Model_Info $payment
     * @return array
     */
    public function getOrderData($order, $payment)
    {
        $orderData = array();

        $items = $order->getAllVisibleItems();

        $ccInstallments = $payment->getAdditionalInformation('installments');
        $installments = ($ccInstallments) ? $ccInstallments : 1;

        $countItems = count($items);
        if ($countItems > 0) {

            $orderData['itemCount'] = $countItems;
            $i = 1;
            /** @var Mage_Sales_Model_Order_Item $item */
            foreach ($items as $item) {
                $qty = $item->getQtyOrdered();
                $price = number_format($item->getPrice(), 2, '.', '');
                $orderData['itemIndex' . $i] = $i;
                $orderData['itemProductCode' . $i] = $item->getSku();
                $orderData['itemDescription' . $i] = $item->getName();
                $orderData['itemQuantity' . $i] = $qty;
                $orderData['itemUnitCost' . $i] = $price;
                $orderData['itemTotalAmount' . $i] = number_format($qty * $price, 2, '.', '');

                if ($this->_getHelper()->getConfig('enabled_split')) {

                    $seller = $this->getSellerByProductId($item->getProduct()->getId());

                    if ($seller) {

                        $sellerInstallments = $installments;
                        if ($seller->getData('installments')) {
                            $sellerInstallments = $seller->getData('installments');
                        }

                        $orderData['sellerId' . $i] = $seller->getData('seller_id');
                        $orderData['sellerMDR' . $i] = $price * ((float)$seller->getData('seller_mdr') / 100);
                        $orderData['sellerDaysToPay' . $i] = $seller->getData('days_to_pay');
                        $orderData['sellerItemStatus' . $i] = $seller->getData('status') ? $seller->getData('status') : 'approved';
                        $orderData['sellerInstallments' . $i] = $sellerInstallments;

                    }

                }

                $i++;
            }
        }

        $orderData['userAgent'] = Mage::helper('core/http')->getHttpUserAgent();

        return $orderData;
    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @param null $transactionId
     */
    public function createInvoice(Mage_Sales_Model_Order $order, $transactionId = null, $code = 'maxipago_cc')
    {
        $helper = $this->_getHelper();
        if ($order->canInvoice()) {
            /** @var Mage_Sales_Model_Order_Payment $payment */
            $payment = $order->getPayment();
            if (!$transactionId) {
                $transactionId = $payment->getAdditionalInformation('transaction_id');
            }

            /** @var Mage_Sales_Model_Order_Invoice $invoice */
            $invoice = Mage::getModel('sales/service_order', $order)->prepareInvoice();

            $invoice->setRequestedCaptureCase(Mage_Sales_Model_Order_Invoice::CAPTURE_OFFLINE);
            $invoice->register();
            $invoice->sendEmail(true);
            $invoice->setEmailSent(true);
            $invoice->getOrder()->setCustomerNoteNotify(true);
            $invoice->getOrder()->setIsInProcess(true);
            $invoice->setTransactionId($transactionId);
            $invoice->setCanVoidFlag(true);

            $payment->setAdditionalInformation('captured', true);
            $payment->setAdditionalInformation('captured_date', date('Y-m-d'));

            $transactionSave = Mage::getModel('core/resource_transaction')
                ->addObject($invoice)
                ->addObject($payment)
                ->addObject($invoice->getOrder());
            $transactionSave->save();

            $helper->log('Order: ' . $order->getIncrementId() . " - invoice created");

            $status = $this->_getHelper()->getConfig('captured_order_status', $code);
            if ($status) {
                $message = Mage::helper('maxipago')->__('The payment was confirmed - Transaction ID: %s', (string)$transactionId);
                $order->addStatusHistoryComment($message, $status)->setIsCustomerNotified(true);
                $order->save();
            }

        }

    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @param null $transactionId
     */
    public function cancelOrder(Mage_Sales_Model_Order $order, $transactionId = null)
    {
        /** @var Mage_Sales_Model_Order_Payment $payment */
        $payment = $order->getPayment();
        /** @var Mage_Sales_Model_Order $order */
        $order = $payment->getOrder();

        $autoReturnOption = Mage::getStoreConfig("cataloginventory/item_options/auto_return");
        if (!$order->canCancel() && !$order->canCreditmemo()) {
            if ($order->getState() != Mage_Sales_Model_Order::STATE_CANCELED) {
                $payment->setIsTransactionDenied(true);
                $payment->setAdditionalInformation('cancelled', true);
                foreach ($order->getInvoiceCollection() as $invoice) {
                    /** @var $invoice Mage_Sales_Model_Order_Invoice */
                    $invoice = $invoice->load($invoice->getId()); // to make sure all data will properly load (maybe not required)
                    if ($invoice) {
                        $invoice->cancel();
                        $order->addRelatedObject($invoice);
                        $invoice->save();
                    }
                    $message = Mage::helper('sales')->__('Registered update about denied payment.');
                    $order->registerCancellation($message, false);
                }
                $order->save();
            }
        } else if ($order->canCancel()) {
            $order->cancel();
            $order->addStatusHistoryComment('Order cancelled at maxiPago!', false);
            $payment->setAdditionalInformation('cancelled', true);
            $payment->save;
            $order->save();
        } else if ($order->canCreditmemo()) {
            /** @var $service  Mage_Sales_Model_Service_Order */
            $service = Mage::getModel('sales/service_order', $order);

            /** @var Mage_Sales_Model_Order_Creditmemo $creditmemo */
            $creditmemo = $service->prepareCreditmemo();
            $creditmemo->setOfflineRequested(true);
            $creditmemo->register();

            foreach ($creditmemo->getAllItems() as $creditmemoItem) {
                if ($autoReturnOption) {
                    $creditmemoItem->setBackToStock(true);
                }
            }

            $payment->setAdditionalInformation('cancelled', true);

            /** @var Mage_Core_Model_Resource_Transaction $transaction */
            $transaction = Mage::getModel('core/resource_transaction')
                ->addObject($creditmemo)
                ->addObject($payment)
                ->addObject($creditmemo->getOrder());

            if ($creditmemo->getInvoice()) {
                $transaction->addObject($creditmemo->getInvoice());
            }

            $transaction->save();
        }


        $payment->save();

    }

    /**
     * @param Mage_Sales_Model_Order $order
     * @param array $record
     */
    public function updatePayment($order, $record)
    {
        $helper = $this->_getHelper();
        $state = isset($record['transactionState']) ? $record['transactionState'] : null;
        $transactionId = isset($record['transactionId']) ? $record['transactionId'] : null;
        $amount = isset($record['transactionAmount']) ? $record['transactionAmount'] : null;

        $message = $helper->__('No updates available');

        if ($state) {

            $lastTransactionState = $order->getPayment()->getAdditionalInformation('last_transaction_state');
            if ($lastTransactionState != $state) {
                if (
                    $state == $helper->getTransactionState('paid')
                    || $state == $helper->getTransactionState('captured')
                    || $state == $helper->getTransactionState('boleto_overpaid')
                ) {

                    $this->createInvoice($order, $transactionId);
                    if ($state == $helper->getTransactionState('boleto_overpaid')) {
                        $transactionStatus = $helper->getTransactionStateLabel($state);
                        $message = $helper->__('Order synchronized with status <strong>%s</strong>', $transactionStatus);
                    } else {
                        $message = $helper->__('Order approved, TID %s', $transactionId);
                    }

                } else if (
                    $state == $helper->getTransactionState('fraud_declined')
                    || $state == $helper->getTransactionState('declined')
                    || $state == $helper->getTransactionState('voided')
                ) {

                    $this->cancelOrder($order, $transactionId);
                    $message = $helper->__('Order cancelled, TID %s', $transactionId);

                } else {

                    $transactionStatus = $helper->getTransactionStateLabel($state);
                    $message = $helper->__('Order synchronized with status <strong>%s</strong>', $transactionStatus);

                }

                if ($message) {
                    $order->addStatusHistoryComment($message);
                    $order->save();
                }

                $order->getPayment()->setAdditionalInformation('last_transaction_state', $state);
                $order->getPayment()->save();
            }

        }

        Mage::getSingleton('adminhtml/session')->addNotice($message);

    }

    /**
     * @param Mage_Sales_Model_Recurring_Profile $profile
     * @param $record
     */
    public function updateRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile, $record)
    {
        $orderIds = $profile->getResource()->getChildOrderIds($profile);

        $maxCycles = $profile->getIniAmount() ? $profile->getPeriodMaxCycles() : $profile->getPeriodMaxCycles() + 1;
        if (count($orderIds) >= $maxCycles) {
            return;
        }

        $collection = Mage::getModel('sales/order')->getCollection()
            ->join(
                array('payment' => 'sales/order_payment'),
                'main_table.entity_id = payment.parent_id',
                array('payment.maxipago_transaction_id')
            )
            ->addAttributeToFilter('main_table.entity_id', array('in' => $orderIds));

        $collection->getSelect()
            ->reset(Zend_Db_Select::COLUMNS)
            ->columns('payment.maxipago_transaction_id');

        $result = $collection->toArray();

        $tranIds = array();
        if ($result && $result['totalRecords'] > 0) {
            foreach ($result['items'] as $item) {
                $tranIds[] = $item['maxipago_transaction_id'];
            }
        }

        if (!in_array($record['transactionId'], $tranIds)) {
            $this->activateRecurringProfile($profile, $record);
        }

        if (count($tranIds) >= $maxCycles) {
            $profile->setState(Mage_Sales_Model_Recurring_Profile::STATE_SUSPENDED);
            $profile->save();
        }
    }

    /**
     * @param Mage_Sales_Model_Recurring_Profile $profile
     * @param $response
     */
    public function activateRecurringProfile(Mage_Payment_Model_Recurring_Profile $profile, $record)
    {
        $tid = $record['transactionId'];

        $productItemInfo = new Varien_Object();
        $productItemInfo->setPaymentType(Mage_Sales_Model_Recurring_Profile::PAYMENT_TYPE_REGULAR);
        $productItemInfo->setPrice((string)$record['transactionAmount']);
        $productItemInfo->setShippingAmount(0);
        $productItemInfo->setTaxAmount(0);

        $productItemInfo = new Varien_Object();
        $productItemInfo->setData($profile->getOrderItemInfo());
        $productItemInfo->setPaymentType(Mage_Sales_Model_Recurring_Profile::PAYMENT_TYPE_REGULAR);
        $order = $profile->createOrder($productItemInfo);
        $order->save();

        if ($order->getId()) {
            $profile->addOrderRelation($order->getId());

            /** @var Mage_Sales_Model_Order_Payment $payment */
            $payment = $order->getPayment();

            $additionalInfo = $profile->getAdditionalInfo();
            if (is_string($additionalInfo)) {
                $additionalInfo = unserialize($additionalInfo);
            }

            $payment->setCcType($additionalInfo['cc_type']);
            $payment->setCcOwner($additionalInfo['cc_type']);
            $payment->setCcExpMonth($additionalInfo['cc_type']);
            $payment->setCcExpYear($additionalInfo['cc_type']);
            $payment->setCcNumberEnc($additionalInfo['cc_type']);
            $payment->setCcLast4($additionalInfo['cc_type']);

            $cpfCnpj = $additionalInfo['cpf_cnpj'];
            $payment->setAdditionalInformation('cpf_cnpj', $cpfCnpj);

            $payment->setAdditionalInformation('recurring_profile', true);
            $payment->setIsTransactionClosed(1);

            $this->createInvoice($order, $tid);
        }

    }

    /**
     * @param $profile
     * @param $paymentInfo
     * @param $order
     * @param $response
     */
    public function setOrderPaymentData($profile, $paymentInfo, $order, $response)
    {
        $tid = $response['transactionID'];

        $cpfCnpj = $paymentInfo->getAdditionalInformation('cpf_cnpj');
        $installments = $paymentInfo->getAdditionalInformation('cc_installments');

        $additionalInfo = array(
            'cc_type' => $paymentInfo->getCcType(),
            'cc_owner' => $paymentInfo->getCcOwner(),
            'cc_exp_month' => $paymentInfo->getCcExpMonth(),
            'cc_exp_year' => $paymentInfo->getCcExpYear(),
            'cc_number_enc' => $paymentInfo->getCcNumberEnc(),
            'cc_last4' => $paymentInfo->getCcLast4(),
            'cpf_cnpj' => $cpfCnpj,
            'installments' => $installments,
        );

        /** @var Mage_Sales_Model_Order_Payment $payment */
        $payment = $order->getPayment();

        $payment->setCcTransId($tid);

        $payment->setCcType($paymentInfo->getCcType());
        $payment->setCcOwner($paymentInfo->getCcOwner());
        $payment->setCcExpMonth($paymentInfo->getCcExpMonth());
        $payment->setCcExpYear($paymentInfo->getCcExpYear());
        $payment->setCcNumberEnc($paymentInfo->getCcNumberEnc());
        $payment->setCcLast4($paymentInfo->getCcLast4());

        $payment->setAdditionalInformation('cpf_cnpj', $cpfCnpj);
        $payment->setAdditionalInformation('cc_installments', $installments);

        $payment->setAdditionalInformation('recurring_profile', true);
        $payment->setCcInstallments($installments);
        $payment->setIsTransactionClosed(1);

        $this->createInvoice($order, $tid);

        $profile->setAdditionalInfo(serialize($additionalInfo));

    }

    public function getSellerByProductId($productId)
    {
        /** @var Mage_Catalog_Model_Product $product */
        $product = Mage::getModel('catalog/product')->load($productId);

        if ($product->getData('maxipago_seller')) {

            /** @var MaxiPago_Payment_Model_Seller $seller */
            $seller = Mage::getModel('maxipago/seller')->load($product->getData('maxipago_seller'), 'seller_id');

            if ($seller && $seller->getId())
                return $seller;
        }

        return false;
    }

    /**
     * @return MaxiPago_Payment_Helper_Data|Mage_Core_Helper_Abstract
     */
    protected function _getHelper()
    {
        if (!$this->_helper) {
            $this->_helper = Mage::helper('maxipago');
        }

        return $this->_helper;
    }

}
