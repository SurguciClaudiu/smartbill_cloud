<?php
/**
 * Copyright 2018-2019 © Intelligent IT SRL. All rights reserved.
 */

namespace SmartBill\Integration\Controller\Adminhtml\Smartorder;

use Magento\Framework\Controller\ResultFactory;
use Magento\Backend\App\Action\Context;
use SmartBill\Integration\Helper\Data;
use SmartBill\Integration\Helper\DataEmail;
use SmartBill\Integration\Model\ResourceModel\Invoice\Collection;
use SmartBill\Integration\Model\InvoiceFactory;
use SmartBill\Integration\Model\ResourceModel\Invoice\CollectionFactory as InvoiceCollectionFactory;
use SmartBill\Integration\Helper\Settings;

class Create extends \Magento\Backend\App\Action{
    protected $helper;
    protected $emailHelper;
    protected $invoiceCollectionFactory;
    protected $invoiceFactory;
    public function __construct(
        Context $context,
        Data $helper,
        DataEmail $emailHelper,
        InvoiceCollectionFactory $invoiceCollectionFactory,
        InvoiceFactory $invoiceFactory
    ){
        parent::__construct($context);
        $this->helper = $helper;
        $this->emailHelper = $emailHelper;
        $this->invoiceFactory = $invoiceFactory;
        $this->invoiceCollectionFactory = $invoiceCollectionFactory;
    }
    public function execute(){
        $return = [];
        if (! $this->getRequest()->isPost()) {
            $return['status'] = false;
            $return['message'] = 'POST request is required to access the API.';
        }
        else {
            $orderId = (int)$this->getRequest()->getParam('order');
            try{
                //try and check if order exists
                //throws Exception
                $order = $this->helper->getOrderHelper()->getMagentoOrder($orderId);

                $settingsData = $this->helper->getSettings()->buildSettingsData();
                $invoiceCollectionFactoryResults = $this->invoiceCollectionFactory->create()->getItemByColumnValue('order_id', $orderId);
                if ($invoiceCollectionFactoryResults != null && $invoiceCollectionFactoryResults->getData('order_id') == $orderId){
                    $invoiceFactory = $invoiceCollectionFactoryResults;
                }
                else {
                    $invoiceFactory = $this->invoiceFactory->create();
                }
                $smartbillInvoice = $this->helper->buildSmartBillDocumentFromOrder( $orderId);

                $connector = $this->helper->getSmartBillConnector();

                $connector->setMagentoOrderId($orderId);
                $connector->setDataLogger($invoiceFactory);

                $documentType = $this->helper->getSettings()->getSettingsValue(Settings::INVOICE_TYPE_KEY); 
                if ($documentType == Settings::SMARTBILL_INVOICE_TYPE){
                    $serverCall = $connector->createInvoiceWithDocumentAddress($smartbillInvoice);
                }
                else {
                    $serverCall = $connector->createProformaWithDocumentAddress($smartbillInvoice);
                }


                if ($serverCall['errorText']){
                    $return['status'] = false;
                    $return['message'] = $serverCall['message'];
                    $return['error'] = $serverCall['errorText'];

                }
                else {
                    $return['status'] = true;
                    if (isset($serverCall['number']) && ($serverCall['number'])){
                        $return['message'] = 'Factura a fost emisa cu succes: '. $serverCall['message'] . $serverCall['series'] . ' ' . $serverCall['number'] .'.';
                        $invoiceFactory->setData('smartbill_invoice_id', $serverCall['number'])
                            ->setData('smartbill_series', $serverCall['series'])
                            ->setData('smartbill_document_url', $serverCall['documentUrl'])
                            ->setData('settings_data', json_encode($settingsData))
                            ->setData('smartbill_status', Settings::SMARTBILL_DATABASE_INVOICE_STATUS_FINAL )
                            ->save();
                    }
                    else {
                        if (isset($serverCall['series'])){
                            $invoiceFactory->setData('smartbill_series', $serverCall['series']);
                        }
                        $invoiceFactory->setData('settings_data', json_encode($settingsData));
                        $invoiceFactory->setData('smartbill_document_url', $serverCall['documentUrl']);
                        $invoiceFactory->setData('smartbill_status', Settings::SMARTBILL_DATABASE_INVOICE_STATUS_DRAFT)->save();
                        $return['message'] = 'Operatiunea s-a desfasurat cu succes:  '. $serverCall['message'] ;
                    }
                    $return['number'] = $serverCall['number'];
                    $return['series'] = $serverCall['series'];
                    //if need to send email then
                    $sendInvoiceToClientEnabled = $this->emailHelper->isClientNotificationEnabled();
                    if($sendInvoiceToClientEnabled && isset($serverCall['number'])){
                        $data = $this->emailHelper->getEmailParamsForOrder($orderId, $serverCall);
                        $statusEmail = $connector->sendDocument($data);
                        if ($statusEmail['status']['code'] != 0){
                            throw new \Exception(__('Eroare la trimiterea email-ului catre client.'));
                        }
                        $return['message'] .=  ' '. $statusEmail['status']['message'];
                    }
                }
            }
            catch(\Exception $e){
                $return['status'] = false;
                $return['message'] = $e->getMessage();
                //daca nu exista invoice-ul, nu va fi setat
                if (isset($invoiceFactory)){
                    $invoiceFactory->setData('order_id', $orderId)
                        ->setData('settings_data', json_encode($settingsData))
                        ->setData('smartbill_status', Settings::SMARTBILL_DATABASE_INVOICE_STATUS_DRAFT )
                        ->save();
                }
            }

        }
        $result = $this->resultFactory->Create(ResultFactory::TYPE_JSON)->setData($return);
        return $result;
    }
}
