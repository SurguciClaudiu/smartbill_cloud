<?php
/**
 * Copyright 2018-2019 © Intelligent IT SRL. All rights reserved.
 */


namespace SmartBill\Integration\Model\Config;

use SmartBill\Integration\Helper\Data;
use SmartBill\Integration\Model\Config\BaseConfig;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Message\ManagerInterface;

/**
 * Source model for StocksDetails
 */
class StocksDetails extends BaseConfig
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

        $invoiceStocks = self::getInvoiceStocks($helper, $messageManager);


        return $invoiceStocks;

    }

}

