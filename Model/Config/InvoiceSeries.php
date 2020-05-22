<?php
/**
 * Copyright 2018-2019 © Intelligent IT SRL. All rights reserved.
 */


namespace SmartBill\Integration\Model\Config;

use SmartBill\Integration\Helper\Data;
use SmartBill\Integration\Model\Config\BaseConfig;
use SmartBill\Integration\Helper\Settings;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Message\ManagerInterface;

/**
 * Source model for InvoiceSeries
 */
class InvoiceSeries extends BaseConfig
{
    public function __construct
    (
        Data $helper,
        ManagerInterface $messageManager

    )
    {
        $this->helper = $helper;
        $this->messageManager = $messageManager;
    }
    /**
     * Returns array to be used in options array
     *
     * @return array
     */
    public function toOptionArray()
    {

        $helper = $this->helper;
        $messageManager = $this->messageManager;

        $settings  = $helper->getSettings();
        $savedDocumentType = $settings->getSettingsValue(Settings::INVOICE_TYPE_KEY);
        if($savedDocumentType == Settings::SMARTBILL_INVOICE_TYPE ){
            //invoice/factura = 1
            $documentType = 'f';
        }
        else {
            //estimate/proforma = 0
            $documentType = 'p';
        } 

        $invoiceSeries = self::getInvoiceSeries($helper, $messageManager, $showErrors = false, $documentType);

        return $invoiceSeries;

    }
    
}

