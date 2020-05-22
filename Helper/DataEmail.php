<?php
/**
 * Copyright 2018-2019 © Intelligent IT SRL. All rights reserved.
 */

namespace SmartBill\Integration\Helper;
use Magento\Framework\App\Helper\Context;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use SmartBill\Integration\Model\SmartBillCloudRestClient;
use SmartBill\Integration\Helper\Settings;


class DataEmail extends \Magento\Framework\App\Helper\AbstractHelper{
    public function __construct(
        Context $context, 
        InvoiceRepositoryInterface $invoiceRepository,
        OrderRepositoryInterface $orderRepository,
        Settings $settings
    ){
        parent::__construct($context);
        $this->invoiceRepository = $invoiceRepository;
        $this->orderRepository = $orderRepository;
        $this->settings = $settings;
    }
 
    /**
     * Aceasta functie returneaza datele din baza de date cu privire la setarile de email
     *
     * @return array
     */
    public function getEmailSettings()
    {
        $details = [];
        $details['subject'] = $this->settings->getSettingsValue(Settings::INVOICE_NOTIFICATION_SUBJECT_SETTINGS_KEY);
        $details['cc'] = $this->settings->getSettingsValue(Settings::INVOICE_NOTIFICATION_CC_SETTINGS_KEY);
        $details['bcc'] = $this->settings->getSettingsValue(Settings::INVOICE_NOTIFICATION_BCC_SETTINGS_KEY);
        $details['bodyText'] = $this->settings->getSettingsValue(Settings::INVOICE_NOTIFICATION_BODYTEXT_SETTINGS_KEY);
        return $details;

    }
    /**
     * Functia returneaza un status daca notificarea este activata sau nu
     *
     * @return boolean
     */
    public function isClientNotificationEnabled()
    {
        $notifyClient = (boolean) $this->settings->getSettingsValue(Settings::INVOICE_NOTIFICATION_SETTINGS_KEY);
        return $notifyClient;
    }
    /**
     * Functia creeaza structura de array necesara pentru a trimite prin email factura
     * Primeste invoice ID-ul din Magento, impreuna cu seria si numarul din SmartBill Cloud (in serverCall)
     *
     * @param int invoiceId
     * @param array serverCall 
     * @return array
     */
    
    public function getEmailParamsForInvoice($invoiceId, $serverCall){
        if (! is_numeric($invoiceId) || (isset($serverCall['errorText']) && $serverCall['errorText'])) return false;
        $invoice = $this->invoiceRepository->get($invoiceId);
        $billingAddress = $invoice->getBillingAddress();
        $email = $billingAddress->getEmail();
        $companyVAT = $this->settings->getSettingsValue(Settings::VAT_SETTINGS_KEY);
        $data['companyVatCode'] = $companyVAT;
        $data['to'] = $email;
        $data['seriesName'] = $serverCall['series'];
        $data['number'] = $serverCall['number'];
        $data['type'] = SmartBillCloudRestClient::DocumentType_Factura;
        $emailSettings = $this->getEmailSettings();
        $data = array_merge($data, $emailSettings);
        return $data;


    }
    /**
     * Functia creeaza structura de array necesara pentru a trimite prin email factura
     * Primeste order ID-ul din Magento, impreuna cu seria si numarul din SmartBill Cloud (in serverCall)
     *
     * @param int orderId
     * @param array serverCall 
     * @return array
     */
    
    public function getEmailParamsForOrder($orderId, $serverCall){
        if (! is_numeric($orderId) || (isset($serverCall['errorText']) && $serverCall['errorText'])) return false;
        $order = $this->orderRepository->get($orderId);
        $billingAddress = $order->getBillingAddress();
        $email = $billingAddress->getEmail();
        $companyVAT = $this->settings->getSettingsValue(Settings::VAT_SETTINGS_KEY);
        $data['companyVatCode'] = $companyVAT;
        $data['to'] = $email;
        $data['seriesName'] = $serverCall['series'];
        $data['number'] = $serverCall['number'];
        $data['type'] = SmartBillCloudRestClient::DocumentType_Factura;
        $emailSettings = $this->getEmailSettings();
        $data = array_merge($data, $emailSettings);
        return $data;

    }



}


