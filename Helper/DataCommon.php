<?php
/**
 * Copyright 2018-2019 © Intelligent IT SRL. All rights reserved.
 */

namespace SmartBill\Integration\Helper;
use Magento\Framework\App\Helper\Context;
use Magento\Directory\Model\CountryFactory;
use Magento\Framework\App\ProductMetadata;

use SmartBill\Integration\Model\MagentoSmartBillCloudRestClient;
use SmartBill\Integration\Model\SmartBillCloudRestClient;
use SmartBill\Integration\Helper\Settings;
use SmartBill\Integration\Model\ANAFAPIClient;


class DataCommon extends \Magento\Framework\App\Helper\AbstractHelper{
    public function __construct(
        Context $context, 
        CountryFactory $countryFactory,
        Settings $settings,
        ANAFAPIClient $anafClient,
        ProductMetadata $magentoInfo
    ){
        parent::__construct($context);
        $this->settings = $settings;
        $this->magentoSmartbill = new MagentoSmartBillCloudRestClient($settings, $magentoInfo);
        $this->countryFactory = $countryFactory;
        $this->anafClient = $anafClient;
    }
    //returns Magento\Sales\Model\Order\order
    public function getMagentoOrder($orderId = null){
        return $this->orderRepository->get($orderId);
    }
    /**
     * Functia returneaza ID-ul tarii in functie de codul tarii
     *
     * @return false|string $countryName
     */
    public function getCountryByCountryId($countryId = null)
    {
        if ( ! $countryId) return false;
        $country = $this->countryFactory->create()->loadByCode($countryId);
        $countryName = $country->getName();
        return $countryName;
    }
   
    /**
     * Functia returneaza valoarea procentuala pentru TVA de pe un rand din order
     *
     * @return float
     */
    public function getTaxValueForItem($item, $taxDetails)
    {
        if ($item->getBasePrice() == 0) return 0;
        //rotunjim la 0 zecimale si apoi convertim in int
        $priceWithVat = $item->getPriceInclTax();
        $priceWithoutVat = $item->getBasePrice();
        $taxPercentage = $this->calculateTaxPercentage($priceWithVat, $priceWithoutVat);
        return $taxPercentage;
    }
    /**
     * Functia returneaza calculul TVA-ului pentru doua valori
     *
     * @return int
     */
    public function calculateTaxPercentage($priceWithVat, $priceWithoutVat)
    {
        if(! $priceWithVat || ! $priceWithoutVat) return -1;

        $taxPercentage = (int) round(((($priceWithVat / $priceWithoutVat ) - 1 ) * 100), 0);
        return $taxPercentage;
    }
    
     /**
     * Functia returneaza numele TVA-ului si valoarea, deoarece este posibil ca valoarea sa fie preluata din Magento
     * caz in care trebuie calculata
     *
     * @return array
     */
    public function getTaxNameDetailsForValues($priceWithVat, $priceWithoutVat, $taxDetails)
    {

        if( ! is_numeric( $priceWithVat ) || ! is_numeric($priceWithoutVat) || ! $taxDetails) throw new \Exception(__('Eroare la calcularea TVA-ului pentru aceste perechi de valori.'));

        if($taxDetails['taxName'] != 'Magento'){
            $result['taxName'] = isset($taxDetails['taxName']) ? $taxDetails['taxName'] : 'Normala'; 
            $result['taxPercentage'] = isset($taxDetails['taxPercentage']) ? $taxDetails['taxPercentage'] : false; 
        }
        else{
            $taxPercentage = $this->calculateTaxPercentage($priceWithVat, $priceWithoutVat);
            $taxName = $this->getTaxNameByPercentage($taxPercentage, $taxDetails['smartbillTaxes']);
            $result['taxName'] = $taxName;
            $result['taxPercentage'] = $taxPercentage;
        }
        return $result;

    }
    
    /**
     * Functia returneaza numele TVA-ului si valoarea, deoarece este posibil ca valoarea sa fie preluata din Magento
     * caz in care trebuie calculata
     *
     * @return array
     */
    public function getTaxNameDetailsForItem($item, $taxDetails)
    {

        if( ! $item || ! $taxDetails) throw new \Exception(__('Eroare la calcularea TVA-ului pentru aceasta inregistrare.'));

        if($taxDetails['taxName'] != 'Magento'){
            $result['taxName'] = isset($taxDetails['taxName']) ? $taxDetails['taxName'] : 'Normala'; 
            $result['taxPercentage'] = isset($taxDetails['taxPercentage']) ? $taxDetails['taxPercentage'] : false; 
        }
        else{
            $taxPercentage = $this->getTaxValueForItem($item, $taxDetails);
            $taxName = $this->getTaxNameByPercentage($taxPercentage, $taxDetails['smartbillTaxes']);
            $result['taxName'] = $taxName;
            $result['taxPercentage'] = $taxPercentage;
        }
        return $result;

    }
    
    /**
     * Functia returneaza datele necesare pentru crearea discount-ului pentru linia $item din Magento order
     *
     * @return array
     */
    public function buildDiscountItem($item = null, $taxDetails = null)
    {
        if( ! $item || ! $taxDetails) throw new \Exception(__('Eroare la calcularea discount-ului pentru aceasta valoare.'));

        $totalDiscount = $item->getDiscountAmount();
        $result['isDiscount'] = true;
        $result['discountType'] = SmartBillCloudRestClient::DiscountType_Valoric; 
        $result['discountValue'] = (-1) * $totalDiscount;
        $result['discountPercentage'] = 0;
        $result['numberOfItems'] = (int)$item->getQty();


        //NB: pe viitor sa se poata personaliza
        $result['name'] = 'Discount';
        $result['measuringUnitName'] = isset($taxDetails['measuringUnitName']) ? $taxDetails['measuringUnitName'] : 'buc';
        $result['currency'] = isset($taxDetails['currency']) ? $taxDetails['currency'] : 'RON'; 
        $result['quantity'] =  1;
        $result['isTaxIncluded'] = $taxDetails['isTaxIncluded'];
        $taxNameDetails = $this->getTaxNameDetailsForItem($item, $taxDetails);
        $result['taxName'] = $taxNameDetails['taxName'];
        $result['taxPercentage'] = $taxNameDetails['taxPercentage'];



        return $result;

    }
    

    /**
     * In cazul in care se preia TVA-ul din Magento, aceasta functie va depista care sunt datele necesare pentru TVA
     * si va returna numele taxei din SmartBill Cloud
     *
     * @return string|throws Exception
     */
    public function getTaxNameByPercentage($taxPercentage = 19, $taxes = 0)
    {
        if ($taxes == 0 ) throw new \Exception(__('Eroare la conectarea la SmartBill Cloud pentru afisarea valorilor TVA.'));
        $taxName = null;
        $taxPercentage = (int)round($taxPercentage,2);
        foreach($taxes as $tax){
            $tax['percentage'] = (int) round($tax['percentage'],2);
            if ( $taxPercentage ==  $tax['percentage']){
               $taxName = $tax['name']; 
               break;
            }
        }
        if ( $taxName) return $taxName;

        $errorMsg = __("Eroare la setarea cotei TVA. Cota TVA furnizata de catre Magento (%1) nu este definita in SmartBill Cloud");
        $errorMsgParsed = str_replace("%1", (float) $taxPercentage, $errorMsg);
        throw new \Exception($errorMsgParsed);
    }
    
    /**
     * Functia creeaza datele necesare pentru informatiile cumparatorului care vor fi incorporate in JSON 
     *
     * @return array
     */
    public function buildSmartBillClientDetailsByAddress($billingAddress = null){
        if ( ! $billingAddress) throw new \Exception(__("Datele de facturare furnizate sunt invalide"));
        $companyName = $billingAddress->getCompany();
        $vatCode = $billingAddress->getVatId();
        $addresses = $billingAddress->getStreet();
        $city = $billingAddress->getCity();
        $countryId = $billingAddress->getCountryId();
        $country = $this->getCountryByCountryId($countryId);
        $county = $billingAddress->getRegion();
        //
        //get country from ID
        $email = $billingAddress->getEmail();
        if (trim($companyName)){
            $invoiceName = $companyName;
            $customerTaxDetails = $this->anafClient->getVATInfo($vatCode);
            $isTaxPayer = $this->anafClient->isTaxPayer($customerTaxDetails);
        }
        else {
            $invoiceName = $billingAddress->getName();
            $isTaxPayer = false;
        }

        //get all address lines
        $street = '';
        if (! empty($addresses)){
            foreach($addresses as $addressLine){
                $street .= $addressLine. " ";
            }
        }
        $smartBillClient = array(
            'name' 			=> $invoiceName,
            'vatCode' 		=> $vatCode,
            'address' 		=> $street,
            'isTaxPayer' 	=> $isTaxPayer,
            'city' 			=> $city,
            'country' 		=> $country,
            'county' 		=> $county,
            'email' 		=> $email
        );

        $saveClient = (boolean) $this->settings->getSettingsValue(Settings::INVOICE_SAVE_CLIENT_SETTINGS_KEY );
        $smartBillClient['saveToDb'] = $saveClient; 


        return $smartBillClient;
    }
    
}


