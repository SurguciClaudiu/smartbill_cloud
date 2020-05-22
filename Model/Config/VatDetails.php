<?php
/**
 * Copyright 2018-2019 © Intelligent IT SRL. All rights reserved.
 */


namespace SmartBill\Integration\Model\Config;

use SmartBill\Integration\Helper\Data;
use SmartBill\Integration\Model\Config\BaseConfig;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * Source model VatDetails
 */
class VatDetails extends BaseConfig
{
    public function __construct
    (
        Data $helper
    )
    {
        $this->helper = $helper;
    }
    /**
     * Returns array to be used in options array
     *
     * @return array
     */
    public function toOptionArray()
    {
        $helper = $this->helper;
        $finalValues = self::getAllTaxes($helper);
        return $finalValues;

    }
    
}

