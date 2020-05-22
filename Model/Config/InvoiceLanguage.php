<?php
/**
 * Copyright 2018-2019 © Intelligent IT SRL. All rights reserved.
 */


namespace SmartBill\Integration\Model\Config;


use Magento\Framework\Data\OptionSourceInterface;

/**
 * Source model for InvoiceLanguage
 */
class InvoiceLanguage implements OptionSourceInterface
{
    /**
     * Returns array to be used in options array
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => 'RO', 'label' => __('Romana')],
            ['value' => 'EN', 'label' => __('Engleza')],
            ['value' => 'FR', 'label' => __('Franceza')],
            ['value' => 'IT', 'label' => __('Italiana')],
            ['value' => 'ES', 'label' => __('Spaniola')],
            ['value' => 'HU', 'label' => __('Maghiara')],
            ['value' => 'DE', 'label' => __('Germana')],
        ];
    }
}

