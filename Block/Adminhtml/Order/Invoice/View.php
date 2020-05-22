<?php
/**
 * Copyright 2018-2019 © Intelligent IT SRL. All rights reserved.
 */

namespace SmartBill\Integration\Block\Adminhtml\Order\Invoice;

use SmartBill\Integration\Model\ResourceModel\Invoice\CollectionFactory as InvoiceCollectionFactory;
use SmartBill\Integration\Helper\Settings;
use SmartBill\Integration\Helper\DataEmail;
use Magento\Framework\Message\ManagerInterface;

class View extends \Magento\Sales\Block\Adminhtml\Order\AbstractOrder
{
    private $invoiceCollectionFactory;
    private $emailHelper;
    public function __construct(
        \Magento\Backend\Block\Template\Context $context,
        \Magento\Framework\Registry $registry,
        \Magento\Sales\Helper\Admin $adminHelper,
        \Magento\Backend\Model\UrlInterface $backendUrl,
        DataEmail $emailHelper,
        Settings $settings,
        InvoiceCollectionFactory $invoiceCollectionFactory,
        ManagerInterface $messageManager
        ){
        $this->backendUrl = $backendUrl;
        $data = [];
        parent::__construct($context, $registry, $adminHelper, $data);
        $this->invoiceCollectionFactory = $invoiceCollectionFactory;
        $this->settings = $settings;
        $this->emailHelper = $emailHelper;
        $this->messageManager = $messageManager;
    }
    public function beforeSetLayout(\Magento\Sales\Block\Adminhtml\Order\Invoice\View $subject)
    {
        try{

            $canIssueFromInvoice = $this->settings->getSettingsValue(Settings::SMARTBILL_INVOICE_FROM_ECOMMERCE_PLATFORM_INVOICE_KEY );
            //daca nu poate sa emita facturi SmartBill dintr-un Magento Invoice, nu afisam butoanele
            if (! $canIssueFromInvoice) return;

            if( $subject->getInvoice()->getState() == \Magento\Sales\Model\Order\Invoice::STATE_PAID ){

                $invoiceId = $subject->getInvoice()->getId() ;
                $invoiceFactory = null;
                $invoiceCollectionFactoryResults = $this->invoiceCollectionFactory->create()->getItemByColumnValue('invoice_id', $invoiceId);
                if ($invoiceCollectionFactoryResults != null && $invoiceCollectionFactoryResults->getData('invoice_id') == $invoiceId){
                    $invoiceFactory = $invoiceCollectionFactoryResults;
                }

                $storeUrl = $this->backendUrl->getUrl('smartbill_settings/smartinvoice/create/', [ 'invoice' => $invoiceId ]);
                //Daca nu este salvat nimic in baza de date, atunci permitem generarea de facturi in SmartBill Cloud
                if (!$invoiceFactory){
                    $subject->addButton(
                        'smartbill_generate_invoice',
                        [
                            'label' => __('Emitere factura in SmartBill'),
                            'class' => 'action-primary',
                            'data_attribute' => [
                                'smartbill-url' => $storeUrl,
                                'smartbill-magento-invoice' => $invoiceId
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
                            $subject->addButton(
                                'smartbill_undo_document',
                                [
                                    'label' => __('Remitere document'),
                                    'class' => 'action-secondary',
                                    'title' => __('Deblocheaza comanda pentru a mai putea emite inca o data documentul in SmartBill Cloud'),
                                    'data_attribute' => [
                                        'smartbill-url' => $storeUrl,
                                        'is-order' => true,
                                        'smartbill-magento-invoice' => $invoiceId
                                    ]
                                ]
                            );

                            $subject->addButton(
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
                            $subject->addButton(
                                'smartbill_generate_invoice',
                                [
                                    'label' => __('Emitere factura in SmartBill'),
                                    'class' => 'action-primary',
                                    'data_attribute' => [
                                        'smartbill-url' => $storeUrl,
                                        'smartbill-magento-invoice' => $invoiceId
                                    ]
                                ]
                            );
                        }

                    }
                    else if ($invoiceFactory->getData('smartbill_status') == Settings::SMARTBILL_DATABASE_INVOICE_STATUS_FINAL ){
                        $subject->addButton(
                            //NB: daca API-ul nou ar returna ID-ul facturii, aici s-ar putea personaliza mai mult
                            'smartbill_view_final',
                            [
                                'label' => __('Vizualizare factura'),
                                'class' => 'action-primary',
                                'data_attribute' => [
                                    'smartbill-url' =>  $document_url
                                ]
                            ]
                        );
                        $subject->addButton(
                            'smartbill_undo_document',
                            [
                                'label' => __('Remitere document'),
                                'class' => 'action-secondary',
                                'title' => __('Deblocheaza comanda pentru a mai putea emite inca o data documentul in SmartBill Cloud'),
                                'data_attribute' => [
                                    'smartbill-url' => $storeUrl,
                                    'is-order' => true,
                                    'smartbill-magento-invoice' => $invoiceId
                                ]
                            ]
                        );



                        //daca este activa optiunea de notificare client - putem sa retrimitem factura
                        $sendInvoiceToClientEnabled = $this->emailHelper->isClientNotificationEnabled();
                        if ($sendInvoiceToClientEnabled){
                            $storeUrl = $this->backendUrl->getUrl('smartbill_settings/smartinvoice/sendinvoice/', [ 'invoice' => $invoiceId ]);
                            $subject->addButton(
                                'smartbill_send_invoice',
                                [
                                    'label' => __('Retrimitere factura pe email'),
                                    'class' => 'action-secondary',
                                    'data_attribute' => [
                                        'smartbill-url' => $storeUrl,
                                        'smartbill-magento-invoice' => $invoiceId
                                    ]
                                ]
                            );
                        }
                    }
                }
            }
            else{
                $subject->addButton(
                    'smartbill_unable_to_generate_for_unpaid',
                    [
                        'label' => __('SmartBill: Te rugam sa marchezi invoice-ul ca platit'),
                        'class' => 'send-email',
                        'title' => __('Inainte de emiterea facturii in SmartBill Cloud, invoice-ul din Magento trebuie sa aiba statusul platit')
                    ]
                );

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
