<?php
/**
 * Copyright 2018-2019 © Intelligent IT SRL. All rights reserved.
 */

namespace SmartBill\Integration\Block\Adminhtml\Order;

use SmartBill\Integration\Model\ResourceModel\Invoice\CollectionFactory as InvoiceCollectionFactory;
use SmartBill\Integration\Helper\Settings;
use SmartBill\Integration\Helper\DataEmail;
use Magento\Framework\Message\ManagerInterface;

class View extends \Magento\Sales\Block\Adminhtml\Order\View
{
    private $invoiceCollectionFactory;
    private $emailHelper;
    public function __construct(
        \Magento\Backend\Block\Widget\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Sales\Model\Config $config,
        \Magento\Sales\Helper\Reorder $reorderHelper,
        \Magento\Backend\Model\UrlInterface $backendUrl,
        DataEmail $emailHelper,
        Settings $settings,
        InvoiceCollectionFactory $invoiceCollectionFactory,
        ManagerInterface $messageManager
        ){
        $this->backendUrl = $backendUrl;
        $data = [];
        parent::__construct($context, $registry, $config, $reorderHelper);
        $this->invoiceCollectionFactory = $invoiceCollectionFactory;
        $this->emailHelper = $emailHelper;
        $this->settings = $settings;
        $this->messageManager = $messageManager;

        $this->showSmartBillInvoiceButton();
    }
    public function showSmartBillInvoiceButton(){
    
        try{

            $canIssueFromOrder = ! $this->settings->getSettingsValue(Settings::SMARTBILL_INVOICE_FROM_ECOMMERCE_PLATFORM_INVOICE_KEY );
            //daca nu poate sa emita facturi SmartBill dintr-un Magento Order, nu afisam butoanele
            if (! $canIssueFromOrder) return;


            $orderId = $this->getOrderId();
            $storeUrl = $this->backendUrl->getUrl('smartbill_settings/smartorder/create/', [ 'order' => $orderId ]);
            $invoiceFactory = null;
            //tabelul de corespondenta poate fi accesat fie cautand dupa order_id, fie dupa invoice_id
            $invoiceCollectionFactoryResults = $this->invoiceCollectionFactory->create()->getItemByColumnValue('order_id', $orderId);
            if ($invoiceCollectionFactoryResults != null && $invoiceCollectionFactoryResults->getData('order_id') == $orderId){
                $invoiceFactory = $invoiceCollectionFactoryResults;
            }

            //Daca nu este salvat nimic in baza de date, atunci permitem generarea de facturi in SmartBill Cloud
            if (!$invoiceFactory){
                $this->buttonList->add(
                    'smartbill_generate_invoice',
                    [
                        'label' => __('Emitere factura in SmartBill'),
                        'class' => 'action-primary',
                        'data_attribute' => [
                            'smartbill-url' => $storeUrl,
                            'is-order' => true,
                            'smartbill-magento-invoice' => $orderId
                        ]
                    ]
                );
            }
            else {
                if (null !== $invoiceFactory->getData('smartbill_document_url')){
                    $document_url =  $invoiceFactory->getData('smartbill_document_url');
                    //Modificam sa duca spre vizualizare, nu spre editare
                    $pattern = '/editare/';
                    $replacement = 'vizualizare';
                    $document_url = preg_replace($pattern, $replacement, $document_url, -1 );
                }
                else {
                    $document_url = Settings::SMARTBILL_CLOUD_LOGIN_URL_REDIRECT_TO_INVOICE_REPORTS;
                }

                //daca este draft, redirectam utilizatorul catre SmartBill
                if ($invoiceFactory->getData('smartbill_status') == Settings::SMARTBILL_DATABASE_INVOICE_STATUS_DRAFT ){
                    //se verifica daca este emisa o serie de facturi, pentru ca este posibil sa fie emisa cu eroare
                    if($invoiceFactory->getData('smartbill_series')!= null){
                        $this->buttonList->add(
                            'smartbill_undo_document',
                            [
                                'label' => __('Remitere document'),
                                'class' => 'action-secondary',
                                'title' => __('Deblocheaza comanda pentru a mai putea emite inca o data documentul in SmartBill Cloud'),
                                'data_attribute' => [
                                    'smartbill-url' => $storeUrl,
                                    'is-order' => true,
                                    'smartbill-magento-invoice' => $orderId
                                ]
                            ]
                        );


                        $this->buttonList->add(
                            'smartbill_view_draft',
                            [
                                'label' => __('Vizualizare ciorna'),
                                'class' => 'action-primary',
                                'data_attribute' => [
                                    'smartbill-url' =>  $document_url
                                ]
                            ]
                        );

                    }
                    //daca nu este setata seria, inseamna ca s-a emis cu eroare si trebuie sa reemitem dupa ce corectam configuratia
                    else{
                        $this->buttonList->add(
                            'smartbill_generate_invoice',
                            [
                                'label' => __('Emitere factura in SmartBill'),
                                'class' => 'action-primary',
                                'data_attribute' => [
                                    'smartbill-url' => $storeUrl,
                                    'is-order' => true,
                                    'smartbill-magento-invoice' => $orderId
                                ]
                            ]
                        );
                    }

                }
                else if ($invoiceFactory->getData('smartbill_status') == Settings::SMARTBILL_DATABASE_INVOICE_STATUS_FINAL ){
                    $this->buttonList->add(
                        'smartbill_view_final',
                        [
                            'label' => __('Vizualizare factura'),
                            'class' => 'action-primary',
                            'data_attribute' => [
                                'smartbill-url' =>  $document_url
                            ]
                        ]
                    );
                    $this->buttonList->add(
                        'smartbill_undo_document',
                        [
                            'label' => __('Remitere document'),
                            'class' => 'action-secondary',
                            'title' => __('Deblocheaza comanda pentru a mai putea emite inca o data documentul in SmartBill Cloud'),
                            'data_attribute' => [
                                'smartbill-url' => $storeUrl,
                                'is-order' => true,
                                'smartbill-magento-invoice' => $orderId
                            ]
                        ]
                    );


                    //daca este activa optiunea de notificare client - putem sa retrimitem factura
                    $sendInvoiceToClientEnabled = $this->emailHelper->isClientNotificationEnabled();
                    if ($sendInvoiceToClientEnabled){
                        $storeUrl = $this->backendUrl->getUrl('smartbill_settings/smartorder/sendorder/', [ 'order' => $orderId ]);
                        $this->buttonList->add(
                            'smartbill_send_invoice',
                            [
                                'label' => __('Retrimitere factura pe email'),
                                'class' => 'action-secondary',
                                'data_attribute' => [
                                    'smartbill-url' => $storeUrl,
                                    'is-order' => true,
                                    'smartbill-magento-invoice' => $orderId
                                ]
                            ]
                        );
                    }
                }
            }
        }
        catch(\Exception $e){
            //deoarece acesta este un modul de afisare a unui buton,
            //posibila eroare va fi afisata pe ecran
            $message =  " Exista o eroare la procesarea cererii: ". $e->getMessage();
            $this->messageManager->addErrorMessage($message);
        }
    }


}
