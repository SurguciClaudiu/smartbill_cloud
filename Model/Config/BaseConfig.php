<?php
/**
 * Copyright 2018-2019 © Intelligent IT SRL. All rights reserved.
 */


namespace SmartBill\Integration\Model\Config;


use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Message\MessageInterface;
use SmartBill\Integration\Helper\Settings;

class BaseConfig implements OptionSourceInterface
{
    /**
     * Returns array to be used in options array
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [];
    }
    /**
     * Functia returneaza toate valorile setarilor de unitati de masura
     *
     * @return array
     */
    public static function getAllMeasuringUnits($helper, $messageManager, $errorsShown = false)
    {
        try{
            $mu = $helper->getSmartBillMeasuringUnits();
            $finalValues = [];
            if (is_array($mu)){
                foreach($mu as $key => $unit){
                    $item['value'] = $key;
                    $item['label'] = __($unit);
                    $finalValues[] = $item;
                }
            }
            return $finalValues;
        }
        catch (\Exception $e){
            if ($errorsShown) $messageManager->addUniqueMessages(
                [
              $messageManager->createMessage(MessageInterface::TYPE_ERROR)->setText( $e->getMessage())
            ]);
        }

    }
    /**
     * Functia va interoga SmartBill Cloud pentru afisarea seriilor de facturi
     *
     * @return array $finalValues
     */
    public static function getInvoiceSeries($helper, $messageManager, $errorsShown = false, $document_type = 'f')
    {
        try{
            $connector = $helper->getSmartBillConnector();
            $series = $connector->getDocumentSeries($helper->getCompanyVAT(), $document_type);
            $finalValues = [];
            foreach($series['list'] as $ser){
                $item['value'] = $ser['name'];
                $item['label'] = $ser['name'];
                $finalValues[] = $item;
            }
            return $finalValues;

        }
        catch (\Exception $e){
            if ($errorsShown) $messageManager->addUniqueMessages(
                [
              $messageManager->createMessage(MessageInterface::TYPE_ERROR)->setText( $e->getMessage())
            ]);
        }
    }    

    //adaugam verificare daca setarile din Magento 2 coincid cu SmartBill Cloud
    public static function checkIfCompanyIsVatPayable($helper, $messageManager, $errorsShown = false){
        try {
            $taxes = $helper->getSmartBillTaxes();
            $settings  = $helper->getSettings();
            $isVatPayable = $settings->getSettingsValue(Settings::VAT_COMPANY_SETTINGS_KEY);
            //daca exista o neconcordanta intre setarea daca este platitoare sau nu de TVA din Magento 2 vs SmartBill
            //sa se genereze o eroare in interfata
            if($taxes == 0 && $isVatPayable){
                throw new \Exception(__('Firma configurata este neplatitoare de TVA in SmartBill Cloud'));
            }
            if(is_array($taxes) && !$isVatPayable){
                throw new \Exception(__('Firma configurata este platitoare de TVA in SmartBill Cloud'));
            }
        } catch (\Exception $e) {
            if ($errorsShown) $messageManager->addUniqueMessages(
                [
              $messageManager->createMessage(MessageInterface::TYPE_ERROR)->setText( $e->getMessage())
            ]);

        }


    }
    /**
     * Functia va verifica daca versiunea platformei Magento 2 este 2.2.x  
     *
     * @return array $finalValues
     */
    public static function checkPlatformVersion($helper, $messageManager, $errorsShown = false)
    {
        try{
            $connector = $helper->getSmartBillConnector();
            $version = $connector->getMagentoInfo()->getVersion();
            if (! version_compare($version, '2.2', '>=')){
                throw new \Exception(__( 'Versiunea curenta a platformei Magento 2 nu este suportata. Va rugam sa folositi versiunile 2.2.x (2.2.0, 2.2.1, 2.2.2 etc).'));
            }

        }
        catch (\Exception $e){
            if ($errorsShown) $messageManager->addUniqueMessages(
                [
              $messageManager->createMessage(MessageInterface::TYPE_ERROR)->setText( $e->getMessage())
            ]);

        }
    }

    /**
     * Functia va interoga SmartBill Cloud pentru afisarea denumirilor gestiunilor
     *
     * @return array $finalValues
     */
    public static function getInvoiceStocks($helper, $messageManager, $errorsShown = false)
    {
        try{
            $connector = $helper->getSmartBillConnector();
            $data = [
                "cif" => $helper->getCompanyVAT(),
                "date" => date('Y-m-d'),
                "warehouseName" => "",
                "productName" => "",
                "productCode" => ""
            ];
            $stocks = $connector->productsStock($data);
            $finalValues = [];
            foreach($stocks as $stock){
                $item['value'] = $stock['warehouse']['warehouseName'];
                $item['label'] = $stock['warehouse']['warehouseName'];
                $finalValues[] = $item;
            }
            return $finalValues;

        }
        catch (\Exception $e){
            if ($errorsShown) $messageManager->addUniqueMessages(
                [
              $messageManager->createMessage(MessageInterface::TYPE_ERROR)->setText( $e->getMessage())
            ]);
        }
    }
    /**
     * Functia returneaza toate valorile setarilor de TVA
     *
     * @return array
     */
    public static function getAllTaxes($helper)
    {
        $taxes = $helper->getSmartBillTaxes();
        $firstSetting = ['value' => Settings::INVOICE_VAT_FROM_ECOMMERCE_PLATFORM_KEY, 'label' => __('Setare per comanda, din Magento')];
        $finalValues[] = $firstSetting;
        if (is_array($taxes)){
            foreach($taxes as $key => $tax){
                //sarim peste valoarea -1% rezervata pentru TVA-ul preluat din Magento 2
                if ($tax['percentage'] == "-1") continue;
                $item['value'] = $key;
                $item['label'] = __('TVA valoare '. $tax['percentage'] . '% - '. $tax['name']);
                $finalValues[] = $item;
            }
        }
       return $finalValues;
    }

}


