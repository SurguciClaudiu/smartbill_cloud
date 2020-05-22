<?php
/**
 * Copyright 2018-2019 © Intelligent IT SRL. All rights reserved.
 */

namespace SmartBill\Integration\Controller\Adminhtml\Smartinvoice;

use Magento\Framework\Controller\ResultFactory;
use SmartBill\Integration\Model\MagentoSmartBillCloudRestClient;
use SmartBill\Integration\Model\SmartBillCloudRestClient;
use Magento\Backend\App\Action\Context;
use SmartBill\Integration\Helper\Data;
use SmartBill\Integration\Helper\DataEmail;
use SmartBill\Integration\Model\ResourceModel\Invoice\Collection;
use SmartBill\Integration\Model\InvoiceFactory;
use SmartBill\Integration\Model\ResourceModel\Invoice\CollectionFactory as InvoiceCollectionFactory;
use SmartBill\Integration\Helper\Settings;

class Sendinvoice extends \Magento\Backend\App\Action{
    private $helper;
    private $emailHelper;
    private $invoiceCollectionFactory;
    private $invoiceFactory;
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
            $invoiceId = (int)$this->getRequest()->getParam('invoice');
            try{
                //try and check if invoice exists
                //throws Exception
                $invoice = $this->helper->getInvoiceHelper()->getMagentoInvoice($invoiceId);

                $smartbillInvoice = $this->helper->buildSmartBillDocumentFromInvoice( $invoiceId);
                $invoiceCollectionFactoryResults = $this->invoiceCollectionFactory->create()->getItemByColumnValue('invoice_id', $invoiceId);
                if ($invoiceCollectionFactoryResults != null && $invoiceCollectionFactoryResults->getData('invoice_id') == $invoiceId){
                    $invoiceFactory = $invoiceCollectionFactoryResults;
                    $connector = $this->helper->getSmartBillConnector();

                    $connector->setMagentoInvoiceId($invoiceId);
                    $connector->setDataLogger($invoiceFactory);
                    $serverCall['number'] = $invoiceFactory->getData('smartbill_invoice_id');
                    $serverCall['series'] = $invoiceFactory->getData('smartbill_series');
                    //if need to send email then
                    $sendInvoiceToClientEnabled = $this->emailHelper->isClientNotificationEnabled();
                    if($sendInvoiceToClientEnabled && isset($serverCall['number'])){
                        $data = $this->emailHelper->getEmailParamsForInvoice($invoiceId, $serverCall);
                        $statusEmail = $connector->sendDocument($data);
                        if ($statusEmail['status']['code'] != 0){
                            throw new \Exception(__('Eroare la trimiterea email-ului catre client.'));
                        }
                        $return['message'] =  $statusEmail['status']['message'];
                        $return['status'] = true;
                    }
                    else {
                        $return['message'] =  'Setarea de trimitere facturi prin email este dezactivata. Te rugam sa o activezi din setarile Magento -> Magazine (Stores) -> Configuratie (Configuration) -> SmartBill -> Notificare client -> Yes - ca sa o poti folosi';
                        $return['status'] = false;
                    }
                }
                else {
                    throw new \Exception(__('Documentul nu a fost gasit in baza de date.'));
                }

            }
            catch(\Exception $e){
                $return['status'] = false;
                $return['message'] = $e->getMessage();
            }

        }
        $result = $this->resultFactory->Create(ResultFactory::TYPE_JSON)->setData($return);
        return $result;
    }
}
