<?php
/**
 * Copyright 2018-2019 © Intelligent IT SRL. All rights reserved.
 */

namespace SmartBill\Integration\Helper;
use Magento\Framework\App\Helper\Context;
use Magento\Directory\Model\CountryFactory;
use Magento\Sales\Api\OrderRepositoryInterface;
use Magento\Framework\App\ProductMetadata;

use SmartBill\Integration\Model\MagentoSmartBillCloudRestClient;
use SmartBill\Integration\Model\SmartBillCloudRestClient;
use SmartBill\Integration\Helper\Settings;
use SmartBill\Integration\Model\ANAFAPIClient;



class DataOrder extends DataCommon{
    public function __construct(
        Context $context, 
        CountryFactory $countryFactory,
        Settings $settings,
        ANAFAPIClient $anafClient,
        OrderRepositoryInterface $orderRepository,
        ProductMetadata $magentoInfo
    ){
        parent::__construct($context, $countryFactory, $settings, $anafClient, $magentoInfo);
        $this->orderRepository = $orderRepository;
    }
    //returns Magento\Sales\Model\Order
    public function getMagentoOrder($orderId = null){
        if (is_numeric($orderId)) return $this->orderRepository->get($orderId);
        //se poate ajunge sa se paseze direct un obiect
        else if(! $orderId) {
            return $orderId;
        }
        else return false;

    }
 
    /**
     * Functia construieste array-ul necesar pentru entitatea client, care va fi pasat catre SmartBill Cloud API
     *
     * @return array $smartBillClient
     */
   
    public function buildSmartBillClientDetails($orderId = null){

        if (is_numeric($orderId)){
            $order = $this->getMagentoOrder($orderId);
        }
        else if ( $orderId){
            $order = $orderId;
            $orderId = $order->getId();
        }
        $billingAddress = $order->getBillingAddress();

        $smartBillClient = $this->buildSmartBillClientDetailsByAddress($billingAddress);
        return $smartBillClient;

    }

    /**
     * Functia construieste array-ul necesar pentru entitatea client, care va fi pasat catre SmartBill Cloud API
     *
     * @return array $productData
     */
    public function buildProductData($orderId = null, $taxDetails = null)
    {
        if (is_numeric($orderId)){
            $order = $this->getMagentoOrder($orderId);
        }
        else if ( $orderId){
            $order = $orderId;
            $orderId = $order->getId();
        }
        else {
            return false;
        }
        $smartBillItems = null;

        $items = $order->getAllItems();
        foreach($items as $item){
            if($item->getParentItem()){
                //skip produse cu parents
                continue;
            }
            if ($item->getBasePrice() > 0 ){
                //verificam sa fie mai mare decat zero, mai ales pentru calcularea discount-ului
                //sau pentru calcularea cotei TVA in caz ca este setata sa vina din Magento
                $smartBillItem = $this->buildOrderItem($item, $taxDetails);
                $smartBillItems[] = $smartBillItem;
                if( (float)$item->getDiscountAmount() > 0){
                    $discountItem = $this->buildDiscountItem($item, $taxDetails);
                    $smartBillItems[] = $discountItem;
                }
            }
			
			 $discountItem = $this->buildOrderDiscountData($item, $taxDetails);
            if ($discountItem) $smartBillItems[] = $discountItem;
            else throw new \Exception(__("Eroare la includerea discountului in factura."));
        }
        $includeTransport = $taxDetails['includeTransport'];

        if ($includeTransport){
            $transportItem = $this->buildOrderTransportData($order, $taxDetails);
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
    public function buildOrderItem($item, $taxDetails)
    {
        if (! $item || ! $taxDetails) throw new \Exception(__("Date invalide furnizate in momentul citirii inregistrarilor din Magento"));

        $result['name'] = $item->getName();
        $result['code'] = $item->getSku();
        $result['isDiscount'] = false; 
        $result['measuringUnitName'] = isset($taxDetails['measuringUnitName']) ? $taxDetails['measuringUnitName'] : 'buc';
        $result['currency'] = isset($taxDetails['currency']) ? $taxDetails['currency'] : 'RON'; 
        $result['quantity'] =  $item->getQtyOrdered(); 
        if(isset($taxDetails['isTaxIncluded']) && $taxDetails['isTaxIncluded']){
            $result['price'] = round($item->getOriginalPrice(),2);
            $result['isTaxIncluded'] = true;
        }
        else {
            $result['price'] = round($item->getOriginalPrice(),2);
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
    public function buildOrderAlreadyPaidData($orderId = null, $taxDetails = null)
    {
        //util pentru teste
        if (is_numeric($orderId)){
            $order = $this->getMagentoOrder($orderId);
        }
        else if ( $orderId){
            $order = $orderId;
            $orderId = $order->getId();
        }

        $value = $order->getBaseGrandTotal();
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
    public function buildOrderTransportData($order = null, $taxDetails = null)
    {
        if(! $order || ! $taxDetails) return null;
        $result['name'] = $taxDetails['transportLabel'] ? $taxDetails['transportLabel'] : 'Transport';
        $result['code'] = 'Transport';
        $result['isDiscount'] = false; 
        $result['measuringUnitName'] = isset($taxDetails['measuringUnitName']) ? $taxDetails['measuringUnitName'] : 'buc';
        $result['currency'] = isset($taxDetails['currency']) ? $taxDetails['currency'] : 'RON'; 
        $result['quantity'] =  1;
        //inclusiv tax si apoi setam taxIncluded
        $priceWithTax =  round($order->getShippingInclTax(),2);
        $result['price'] = $priceWithoutTax = round($order->getShippingAmount(),2);
        $result['isTaxIncluded'] = isset($taxDetails['isTransportTaxIncluded']) ? $taxDetails['isTransportTaxIncluded'] : false; 

        //suprascriere valori pentru a returna cota TVA pentru transport
        $taxDetails['taxName'] = $taxDetails['transportTaxName'];
        $taxDetails['taxPercentage'] = $taxDetails['transportTaxPercentage'];
        $taxNameDetails = $this->getTaxNameDetailsForValues($priceWithTax, $priceWithoutTax, $taxDetails);
        $result['taxName'] = $taxNameDetails['taxName'];
        $result['taxPercentage'] = $taxNameDetails['taxPercentage'];
        $result['isService'] =  true;
        $result['saveToDb'] =  false;
        return $result;

    }
	
	// INCERCARE ADAUGARE RAND CU DISCOUNT
	
	public function buildOrderDiscountData($item = null, $taxDetails = null)
    {
        if(! $item || ! $taxDetails) return null;
		
		//AFLARE CAT % E DISCOUNTUL
		$pretdiscount = round($item->getOriginalPrice(),2);
		$pretspecialdiscount = round($item->getBasePrice(),2);;
		$procentDiferenta = ($pretspecialdiscount / $pretdiscount) * 100;
		$procentFinal = (100 - $procentDiferenta) . "%" ;
		
		$numeDiscount = "Discount " . $procentFinal . " - " . $item->getName();
        $result['name'] = $numeDiscount;
        $result['code'] = 'Discount';
        
		
        $result['measuringUnitName'] = isset($taxDetails['measuringUnitName']) ? $taxDetails['measuringUnitName'] : 'buc';
        $result['currency'] = isset($taxDetails['currency']) ? $taxDetails['currency'] : 'RON'; 
        $result['quantity'] =  1;
		
		$qtyitems = $item->getQtyOrdered();
		
        //inclusiv tax si apoi setam taxIncluded
		$pret = round($item->getOriginalPrice(),2);
		$pretspecial = round($item->getBasePrice(),2);;
		$pretfinal = $pret - $pretspecial;
		$priceWithTax = (-1) * $pretfinal; 
		$result['price'] = (-1) * $pretfinal;
		
		$result['isDiscount'] = true;
		$result['discountType'] = SmartBillCloudRestClient::DiscountType_Valoric; 
		$result['discountValue'] = (-1) * $pretfinal * $qtyitems;
        $result['discountPercentage'] = 1;
      
        $result['isTaxIncluded'] = isset($taxDetails['isDiscountTaxIncluded']) ? $taxDetails['isDiscountTaxIncluded'] : false; 

        //suprascriere valori pentru a returna cota TVA pentru transport
       
        return $result;

    }
     
  
}


