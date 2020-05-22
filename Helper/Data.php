<?php
/**
 * Copyright 2018-2019 © Intelligent IT SRL. All rights reserved.
 */

namespace SmartBill\Integration\Helper;
use Magento\Framework\App\Helper\Context;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Customer\Api\CustomerRepositoryInterface;
use Magento\Framework\App\ProductMetadata;

use SmartBill\Integration\Model\MagentoSmartBillCloudRestClient;
use SmartBill\Integration\Model\SmartBillCloudRestClient;
use SmartBill\Integration\Helper\Settings;
use SmartBill\Integration\Helper\DataInvoice;
use SmartBill\Integration\Helper\DataOrder;
use SmartBill\Integration\Model\Config\VatDetails;

class Data extends \Magento\Framework\App\Helper\AbstractHelper{

    private $invoiceRepository;
    private $orderRepository;
    private $magentoSmartBillWrapper;
    private $settings;
    //codul fiscal al companiei
    private $companyVAT;
    //compania este sau nu platitoare de TVA
    private $companyVATPayable;

    const ORDER_TYPE = 0;
    const INVOICE_TYPE = 1;

    //setare TVA
    private $VATValue;

    private $invoiceHelper;

    public function __construct(
        Context $context,
        InvoiceRepositoryInterface $invoiceRepository,
        OrderRepositoryInterface $orderRepository,
        DataInvoice $invoiceHelper,
        DataOrder $orderHelper,
        Settings $settings,
        ProductMetadata $magentoInfo
    ){
        parent::__construct($context);
        $this->invoiceRepository = $invoiceRepository;
        $this->orderRepository = $orderRepository;
        $this->magentoSmartBillWrapper = new MagentoSmartBillCloudRestClient($settings, $magentoInfo);
        $this->settings = $settings;
        $this->invoiceHelper = $invoiceHelper;
        $this->orderHelper = $orderHelper;
        $this->companyVAT = $this->settings->getSettingsValue(Settings::VAT_SETTINGS_KEY);
        $this->companyVATPayable = $this->settings->getSettingsValue(Settings::VAT_COMPANY_SETTINGS_KEY);
        $this->VATIndexValue = $this->settings->getSettingsValue(Settings::VAT_DETAILS_SETTINGS_KEY);
        $this->transportVATIndexValue = $this->settings->getSettingsValue(Settings::INVOICE_TRANSPORTATION_VAT_KEY);
        //se alege seria salvata din baza de date
        $this->seriesName = $this->settings->getSettingsValue(Settings::INVOICE_SERIES_SETTINGS_KEY);

    }
    /**
     * Functia returneaza setarile din acest modul
     *
     * @return \SmartBill\Integration\Helper\Settings $settings
     */
    public function getSettings()
    {
        return $this->settings;
    }

    /**
     * Functia returneaza obiectul OrderHelper
     *
     * @return SmartBill\Integration\Helper\DataOrder
     */
    public function getOrderHelper()
    {
        return $this->orderHelper;
    }
     /**
     * Functia returneaza obiectul InvoiceHelper
     *
     * @return SmartBill\Integration\Helper\DataInvoice
     */
    public function getInvoiceHelper()
    {
        return $this->invoiceHelper;
    }

    /**
     * Functia returneaza conectorul catre SmartBill API
     *
     * @return \SmartBill\Integration\SmartBillCloudRestClient
     */
    public function getSmartBillConnector()
    {
        return $this->magentoSmartBillWrapper->getConnector();
    }
    /**
     * Returneaza din setari codul fiscal al companiei
     *
     * @return string companyVAT
     */
    public function getCompanyVAT()
    {
        return $this->companyVAT;
    }
    /**
     * setter for companyVAT
     *
     * @return void
     */
    public function setCompanyVAT($vat = null)
    {
        $this->companyVAT = $vat;
    }

    /**
     * undocumented function
     *
     * @return void
     */
    public function setCompanyVATPayable($vatp = null)
    {
        $this->companyVATPayable = $vatp;
    }


    /**
     * Functia seteaza codul de moneda pentru un anumit $invoice
     *
     * @return string $currency
     */
    public function setupCurrency($invoice)
    {

        $currency = $this->settings->getSettingsValue(Settings::INVOICE_CURRENCY_SETTINGS_KEY );
        if ($currency){
            if ($currency == "MAGENTO"){
                //poate fi RON sau altceva
                $currency = $invoice->getOrderCurrencyCode();
            }
        }
        else {
            $currency = 'RON';
        }
        $this->currency = $currency;
    }
    /**
     * Se construieste obiectul cu setari folosit pentru a crea ulterior si celelalte sectiuni: produse, transport, discount
     *
     * @return void
     */
    public function buildTaxDetails()
    {
        $taxDetails = [];
        $measuringUnitKey = $this->settings->getSettingsValue(Settings::INVOICE_UNIT_SETTINGS_KEY );
        $measuringUnits = $this->getSmartBillMeasuringUnits();
        $taxDetails['measuringUnitName'] = isset($measuringUnits[$measuringUnitKey]) ? $measuringUnits[$measuringUnitKey] : '';
        $taxDetails['currency'] = $this->currency;
        $pricesContainVAT = (boolean) $this->settings->getSettingsValue(Settings::VAT_PRODUCTS_SETTINGS_KEY );
        $isTransportTaxIncluded = $this->settings->getSettingsValue(Settings::INVOICE_TRANSPORTATION_VAT_INCLUDED_KEY);
        $usePaymentTax = $this->settings->getSettingsValue(Settings::INVOICE_USE_PAYMENT_TAX_KEY);

        $isTaxIncluded = false;
        if ($this->companyVATPayable){
            $taxes = $this->getSmartBillTaxes();
            if ($taxes == 0) throw new \Exception(__("Eroare la conectarea la SmartBill Cloud pentru afisarea valorilor TVA."));
            if ($this->VATIndexValue == Settings::INVOICE_VAT_FROM_ECOMMERCE_PLATFORM_KEY ){
                $taxPercentage = -1;
                $taxName = 'Magento';
            }
            else {
                $taxPercentage = $taxes[$this->VATIndexValue]['percentage'];
                $taxName = $taxes[$this->VATIndexValue]['name'];
            }
            if ($this->transportVATIndexValue == Settings::INVOICE_VAT_FROM_ECOMMERCE_PLATFORM_KEY ){
                $transportTaxPercentage = -1;
                $transportTaxName = 'Magento';
            }
            else {
                $transportTaxPercentage = $taxes[$this->transportVATIndexValue]['percentage'];
                $transportTaxName = $taxes[$this->transportVATIndexValue]['name'];
            }

            if (! $pricesContainVAT){
                $isTaxIncluded = false;
            }
            else {
                $isTaxIncluded = true;
            }
        }
        else {
            //in cazul in care compania este marcata ca neplatitoare de TVA in Magento
            //incercam sa verificam daca este marcata ca neplatitoare de TVA in SmartBill Cloud
            //
            //functia returneaza 0 daca nu exista date despre TVA
            $taxes = $this->getSmartBillTaxes();
            if ($taxes !== 0) throw new \Exception(__("Te rugam sa verifici daca ai setat compania ca neplatitoare de TVA si in Magento si in contul dvs. SmartBill Cloud. "));
            $taxName = '';
            $taxPercentage = '';
            $transportTaxPercentage = '';
            $transportTaxName = '';
        }


        $taxDetails['isTaxIncluded'] = $isTaxIncluded;
        $taxDetails['taxName'] = $taxName;
        $taxDetails['transportTaxName'] = $transportTaxName;
        $taxDetails['saveProductToDb'] = (boolean) $this->settings->getSettingsValue(Settings::INVOICE_SAVE_PRODUCT_SETTINGS_KEY);
        $taxDetails['taxPercentage'] = $taxPercentage;
        $taxDetails['transportTaxPercentage'] = $transportTaxPercentage;
        $taxDetails['isService'] = false;
        $taxDetails['includeTransport'] = (boolean) $this->settings->getSettingsValue(Settings::INVOICE_TRANSPORTATION_KEY);
        $taxDetails['transportLabel'] =  (string) $this->settings->getSettingsValue(Settings::INVOICE_TRANSPORTATION_LABEL_KEY);
        $taxDetails['isTransportTaxIncluded'] = (boolean) $isTransportTaxIncluded;
        $taxDetails['usePaymentTax'] = (boolean) $usePaymentTax;
        $taxDetails['paymentSeries'] = $this->seriesName;
        $taxDetails['paymentType'] = SmartBillCloudRestClient::PaymentType_Other ;
        $taxDetails['smartbillTaxes'] = $taxes;
        $taxDetails['useStock'] = (boolean) $this->settings->getSettingsValue(Settings::INVOICE_USE_STOCK_SETTINGS_KEY);
        if($taxDetails['useStock']){
            $taxDetails['warehouseName'] =  $this->settings->getSettingsValue(Settings::INVOICE_WHICH_STOCK_SETTINGS_KEY);
        }
        return $taxDetails;
    }

    public function buildSmartBillDocumentFromOrder($magentoOrderId = null){
        //util pentru teste
        if (is_numeric($magentoOrderId)){
            $order = $this->orderHelper->getMagentoOrder($magentoOrderId);
        }
        else {
            $order = $magentoOrderId;
            $magentoOrderId = $order->getId();
        }
        $debugModeKey = Settings::DEBUG_MODE_KEY;
        $debugModeOn = $this->settings->getSettingsValue($debugModeKey);
        if($debugModeOn){
            $this->magentoSmartBillWrapper->getConnector()->setMagentoFullDetails($this->getDebugInfo($order, self::ORDER_TYPE));
        }

        $this->setupCurrency($order);
        $taxDetails = $this->buildTaxDetails();
        $companyVatCode = $this->companyVAT;
        $clientDetails = $this->orderHelper->buildSmartBillClientDetails($magentoOrderId);

        $isDraft = !$this->settings->getSettingsValue(Settings::INVOICE_NOT_DRAFT_SETTINGS_KEY);
        $connector = $this->getSmartBillConnector();


        $seriesName = $this->seriesName;
        $dueDays = (int) $this->settings->getSettingsValue(Settings::INVOICE_DUE_DAYS_SETTINGS_KEY );
        if (!is_numeric($dueDays) || ($dueDays < 0)) $dueDays = 15;

        $deliveryDays = (int) $this->settings->getSettingsValue(Settings::INVOICE_DELIVERY_DAYS_SETTINGS_KEY );
        if (!is_numeric($deliveryDays) || ($deliveryDays < 0)) $deliveryDays = 15;
        if ($deliveryDays < $dueDays) $deliveryDays = $dueDays;




        $smartbillDocument = [
            'companyVatCode'=> $companyVatCode,
            'client' 		=> $clientDetails,
            'issueDate' 	=> date('Y-m-d'),
            'seriesName' 	=> $seriesName,
            'isDraft' 		=> $isDraft,
            'dueDate' 		=> date('Y-m-d', time() + $dueDays * 24 * 3600),
            'mentions' 		=> "comanda online order ". $magentoOrderId,
            'observations' 	=> "",
            'deliveryDate' 	=> date('Y-m-d', time() + $deliveryDays * 24 * 3600),
            'products' 		=> $this->orderHelper->buildProductData($magentoOrderId, $taxDetails),
            'usePaymentTax' => false
        ];
        if ($this->currency != 'RON'){
            //cursul se va prelua automat de pe Internet in ziua curenta
            $smartbillDocument['currency'] = $this->currency;
        }
        if ($taxDetails['useStock']){
            $smartbillDocument['useStock'] = $taxDetails['useStock'];
        }
        $documentType = $this->settings->getSettingsValue(Settings::INVOICE_TYPE_KEY);
        if ($documentType == Settings::SMARTBILL_ESTIMATE_TYPE) {
            unset($smartbillDocument['useStock']);
        }

        // Adaugare TVA la incasare
        if ($taxDetails['usePaymentTax']) {
            $smartbillDocument['usePaymentTax'] = true;
            $smartbillDocument['paymentBase'] = 0;
            $smartbillDocument['colectedTax'] = 0;
            $smartbillDocument['paymentTotal'] = 0;
        }


        $language = $this->settings->getSettingsValue(Settings::INVOICE_LANGUAGE_SETTINGS_KEY );
        if ($language && $language != 'RO'){
            $smartbillDocument['language'] = $language;
        }


        return $smartbillDocument;

    }

    public function buildSmartBillDocumentFromInvoice($magentoInvoiceId = null){
        //util pentru teste
        if (is_numeric($magentoInvoiceId)){
            $invoice = $this->invoiceHelper->getMagentoInvoice($magentoInvoiceId);
        }
        else {
            $invoice = $magentoInvoiceId;
            $magentoInvoiceId = $invoice->getId();
        }

        $debugModeKey = Settings::DEBUG_MODE_KEY;
        $debugModeOn = $this->settings->getSettingsValue($debugModeKey);
        if($debugModeOn){
            $this->magentoSmartBillWrapper->getConnector()->setMagentoFullDetails($this->getDebugInfo($invoice, self::INVOICE_TYPE));
        }


        $this->setupCurrency($invoice);
        $taxDetails = $this->buildTaxDetails();
        $companyVatCode = $this->companyVAT;
        $clientDetails = $this->invoiceHelper->buildSmartBillClientDetails($magentoInvoiceId);

        $isDraft = !$this->settings->getSettingsValue(Settings::INVOICE_NOT_DRAFT_SETTINGS_KEY);
        $connector = $this->getSmartBillConnector();


        $seriesName = $this->seriesName;
        $dueDays = (int) $this->settings->getSettingsValue(Settings::INVOICE_DUE_DAYS_SETTINGS_KEY );
        if (!is_numeric($dueDays) || ($dueDays < 0)) $dueDays = 15;

        $deliveryDays = (int) $this->settings->getSettingsValue(Settings::INVOICE_DELIVERY_DAYS_SETTINGS_KEY );
        if (!is_numeric($deliveryDays) || ($deliveryDays < 0)) $deliveryDays = 15;
        if ($deliveryDays < $dueDays) $deliveryDays = $dueDays;




        $smartbillDocument = [
            'companyVatCode'=> $companyVatCode,
            'client' 		=> $clientDetails,
            'issueDate' 	=> date('Y-m-d'),
            'seriesName' 	=> $seriesName,
            'isDraft' 		=> $isDraft,
            'dueDate' 		=> date('Y-m-d', time() + $dueDays * 24 * 3600),
            'mentions' 		=> "comanda online invoice ". $magentoInvoiceId,
            'observations' 	=> "",
            'deliveryDate' 	=> date('Y-m-d', time() + $deliveryDays * 24 * 3600),
            'products' 		=> $this->invoiceHelper->buildProductData($magentoInvoiceId, $taxDetails)
        ];
        if ($this->currency != 'RON'){
            //cursul se va prelua automat de pe Internet in ziua curenta
            $smartbillDocument['currency'] = $this->currency;
        }

        $language = $this->settings->getSettingsValue(Settings::INVOICE_LANGUAGE_SETTINGS_KEY );
        if ($language && $language != 'RO'){
            $smartbillDocument['language'] = $language;
        }

        $documentType = $this->settings->getSettingsValue(Settings::INVOICE_TYPE_KEY);
        if ($documentType == Settings::SMARTBILL_ESTIMATE_TYPE) {
            unset($smartbillDocument['useStock']);
        }

        return $smartbillDocument;

    }
    /**
     * Functia solicita lista de taxe de la SmartBill Cloud. In cazul in care compania este neplatitoare de TVA, se returneaza 0
     *
     * @return array|0
     */
    public function getSmartBillTaxes()
    {
        try {
            $connector = $this->getSmartBillConnector();
            $taxes = $connector->getTaxes($this->getCompanyVAT());
            if (is_array($taxes) && isset($taxes['taxes'])){
                //prepend Magento ca prima valoare
                //avem nevoie ca acest array sa coincida in lungime cu cel din setarile Magento
                $magentoTax['name'] = 'Magento';
                $magentoTax['percentage'] = -1;
                array_unshift($taxes['taxes'], $magentoTax);
                return $taxes['taxes'];
            }
            return 0;
        } catch (\Exception $e) {
            return 0;
        }
    }
     /**
     * Functia solicita lista de unitati de masura de la SmartBill Cloud.
     *
     * @return array
     */
    public function getSmartBillMeasuringUnits()
    {
        try {
            $connector = $this->getSmartBillConnector();
            $mu = $connector->getMeasuringUnits($this->getCompanyVAT());
            if (is_array($mu) && isset($mu['mu'])){
                return $mu['mu'];
            }
            throw new \Exception(__("Setari invalide pentru unitatile de masura din SmartBill Cloud"));
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Functia returneaza un array cu toate informatiile comenzii
     *
     * @return array
     */
    public function getDebugInfo($magentoId = null, $type = self::ORDER_TYPE)
    {
        if ($type == self::ORDER_TYPE){
            if (is_numeric($magentoId)){
                $magentoEntity = $this->orderHelper->getMagentoOrder($magentoId);
            }
            else {
                $magentoEntity = $magentoId;
                $magentoId = $magentoEntity->getId();
            }

        }
        else if ($type == self::INVOICE_TYPE){
             if (is_numeric($magentoId)){
                $magentoEntity = $this->invoiceHelper->getMagentoInvoice($magentoId);
            }
            else {
                $magentoEntity = $magentoId;
                $magentoId = $magentoEntity->getId();
            }
        }
        else {
            return null;
        }
        $billingAddress = $magentoEntity->getBillingAddress();

        $billingDetails = $this->getReflectionInfo($billingAddress);
        //este nevoie sa fie un array unidimensional, altfel API-ul il respinge
        if ($billingDetails){
            foreach($billingDetails as $key => $value){
                $data['billing_address_' . $key] = $value;
            }
        }
        $items = $magentoEntity->getAllItems();
        if (is_array($items)){
            foreach($items as $key => $item){
                $detailsForItem = $this->getReflectionInfo($item);
                if ($detailsForItem){
                    foreach($detailsForItem as $subK => $subV){
                        $data['entity_details_' . $key . '_' . $subK] = $subV;
                    }
                }
            }
        }
        return $data;
    }
    public function getReflectionInfo($entity = null){
        if (! $entity) return null;
        $data = array();
        $blacklistNames = [ "getForceApplyDiscountToParentItem"];
        if (is_object($entity)){
            $object = new \ReflectionClass($entity);
            $methods = $object->getMethods(\ReflectionMethod::IS_PUBLIC);

            foreach($methods as $method){
                $methodName = $method->name;
                //sarim peste metodele care au nevoie de parametri
                if ($method->getParameters()) continue;
                if(strtolower(substr($methodName,0,3)) == "get"){

                    if (! method_exists($entity, $methodName)) continue;

                    if (in_array($methodName, $blacklistNames)) continue;

                    //$data['methods'][] = $methodName;

                    $getterValue = $entity->{$methodName}();
                    if (!is_object($getterValue) && !is_array($getterValue)) $data[$methodName] = $getterValue;
                }
            }
        }
        return $data;
    }


}
