<?php
/**
 * Copyright 2018-2019 © Intelligent IT SRL. All rights reserved.
 */

namespace SmartBill\Integration\Helper;
use Magento\Framework\App\Helper\Context;
use Magento\Directory\Model\CountryFactory;
use Magento\Sales\Api\InvoiceRepositoryInterface;
use Magento\Framework\App\ProductMetadata;

use SmartBill\Integration\Model\MagentoSmartBillCloudRestClient;
use SmartBill\Integration\Model\SmartBillCloudRestClient;
use SmartBill\Integration\Helper\Settings;
use SmartBill\Integration\Model\ANAFAPIClient;



class DataInvoice extends DataCommon{
    public function __construct(
        Context $context, 
        CountryFactory $countryFactory,
        Settings $settings,
        ANAFAPIClient $anafClient,
        InvoiceRepositoryInterface $invoiceRepository,
        ProductMetadata $magentoInfo
    ){
        parent::__construct($context, $countryFactory, $settings, $anafClient, $magentoInfo);
        $this->invoiceRepository = $invoiceRepository;
    }
    //returns Magento\Sales\Model\Order\Invoice
    public function getMagentoInvoice($invoiceId = null){
        return $this->invoiceRepository->get($invoiceId);
    }
    /**
     * Functia construieste array-ul necesar pentru entitatea client, care va fi pasat catre SmartBill Cloud API
     *
     * @return array $smartBillClient
     */
   
    public function buildSmartBillClientDetails($invoiceId = null){

        $invoice = $this->getMagentoInvoice($invoiceId);
        $billingAddress = $invoice->getBillingAddress();

        $smartBillClient = $this->buildSmartBillClientDetailsByAddress($billingAddress);
        return $smartBillClient;
    }

    /**
     * Functia construieste array-ul necesar pentru entitatea client, care va fi pasat catre SmartBill Cloud API
     *
     * @return array $productData
     */
    public function buildProductData($invoiceId = null, $taxDetails = null)
    {
        if (! is_numeric($invoiceId)) return false;
        $invoice = $this->getMagentoInvoice($invoiceId);
        $items = $invoice->getAllItems();
        foreach($items as $item){
            if($item->getOrderItem()->getParentItem()){
                //skip produse cu parents
                continue;
            }
            if ($item->getBasePrice() > 0 ){
                //verificam sa fie mai mare decat zero, mai ales pentru calcularea discount-ului
                //sau pentru calcularea cotei TVA in caz ca este setata sa vina din Magento
                $smartBillItem = $this->buildInvoiceItem($item, $taxDetails);
                $smartBillItems[] = $smartBillItem;
                if( (float)$item->getDiscountAmount() > 0){
                    $discountItem = $this->buildDiscountItem($item, $taxDetails);
                    $smartBillItems[] = $discountItem;
                }
            }
        }
        $includeTransport = $taxDetails['includeTransport'];

        if ($includeTransport){
            $transportItem = $this->buildInvoiceTransportData($invoice, $taxDetails);
            if ($transportItem) $smartBillItems[] = $transportItem;
            else throw new \Exception(__("Eroare la includerea transportului in factura."));

        }
        return $smartBillItems;

    }
    /**
     * Functia construieste datele necesare pentru o inregistrare in produse
     *
     * @return array
     */
    public function buildInvoiceItem($item, $taxDetails)
    {
        if (! $item || ! $taxDetails) throw new \Exception(__("Date invalide furnizate in momentul citirii inregistrarilor din Magento"));

        
        $result['name'] = $item->getName() ;
        $result['code'] = $item->getSku();
        $result['isDiscount'] = false; 
        $result['measuringUnitName'] = isset($taxDetails['measuringUnitName']) ? $taxDetails['measuringUnitName'] : 'buc';
        $result['currency'] = isset($taxDetails['currency']) ? $taxDetails['currency'] : 'RON'; 
        $result['quantity'] =  $item->getQty(); 
        if(isset($taxDetails['isTaxIncluded']) && $taxDetails['isTaxIncluded']){
            $result['price'] = round($item->getPriceInclTax(),2);
            $result['isTaxIncluded'] = true;
        }
        else {
            $result['price'] = round($item->getBasePrice(),2);
            $result['isTaxIncluded'] = false;
        }
        $taxNameDetails = $this->getTaxNameDetailsForItem($item, $taxDetails);
        $result['taxName'] = $taxNameDetails['taxName'];
        $result['taxPercentage'] = $taxNameDetails['taxPercentage'];
        if(isset($taxDetails['useStock']) && $taxDetails['useStock']){
            $result['warehouseName'] = $taxDetails['warehouseName'];
        }

        $result['isService'] =  isset($taxDetails['isService']) ? $taxDetails['isService'] : false; 
        $result['saveToDb'] =  isset($taxDetails['saveProductToDb']) ? $taxDetails['saveProductToDb'] : false; 
        return $result;

    }
    
    /**
     * Functia returneaza obiectul care determina daca factura este deja platita la emitere, conform setarilor din baza de date
     *
     * @return array
     */
    public function buildInvoiceAlreadyPaidData($invoiceId = null, $taxDetails = null)
    {
        //util pentru teste
        if (is_numeric($invoiceId))  $invoice = $this->getMagentoInvoice($invoiceId);
        else $invoice = $invoiceId;
        $value = $invoice->getBaseGrandTotal();
        $data = [];
        $data['value'] = $value;
        $data['paymentSeries'] = $taxDetails['paymentSeries'];
        $data['type'] = $taxDetails['paymentType'];
        $data['isCash'] = false;
        return $data;
    }
    /**
     * Functia returneaza valorile necesare pentru includerea transportului
     *
     * @return array|null
     */
    public function buildInvoiceTransportData($invoice = null, $taxDetails = null)
    {
        if(! $invoice || ! $taxDetails) return null;
        $result['name'] = $taxDetails['transportLabel'] ? $taxDetails['transportLabel'] : 'Transport';
        $result['isDiscount'] = false; 
        $result['code'] = 'Transport';
        $result['measuringUnitName'] = isset($taxDetails['measuringUnitName']) ? $taxDetails['measuringUnitName'] : 'buc';
        $result['currency'] = isset($taxDetails['currency']) ? $taxDetails['currency'] : 'RON'; 
        $result['quantity'] =  1;
        //inclusiv tax si apoi setam taxIncluded
        $priceWithTax =  round($invoice->getShippingInclTax(),2);
        $result['price'] = $priceWithoutTax = $invoice->getShippingAmount();
        $result['isTaxIncluded'] = isset($taxDetails['isTransportTaxIncluded']) ? $taxDetails['isTransportTaxIncluded'] : false; 
        //suprascriere valori pentru a returna cota TVA pentru transport
        $taxDetails['taxName'] = isset($taxDetails['transportTaxName']) ? $taxDetails['transportTaxName'] : '';
        $taxDetails['taxPercentage'] = isset($taxDetails['transportTaxPercentage']) ? $taxDetails['transportTaxPercentage'] : '';

        $taxNameDetails = $this->getTaxNameDetailsForValues($priceWithTax, $priceWithoutTax, $taxDetails);
        $result['taxName'] = $taxNameDetails['taxName'];
        $result['taxPercentage'] = $taxNameDetails['taxPercentage'];
        $result['isService'] =  true;
        $result['saveToDb'] =  false;
        return $result;

    }
   
}


