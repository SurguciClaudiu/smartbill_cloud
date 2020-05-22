<?php
/**
 * Copyright 2018-2019 © Intelligent IT SRL. All rights reserved.
 */


namespace SmartBill\Integration\Model\Config;


use Magento\Framework\Data\OptionSourceInterface;

/**
 * Source model for InvoiceCurrency
 */
class InvoiceCurrency implements OptionSourceInterface
{
    /**
     * Returns array to be used in options array
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            ['value' => "RON", 'label' => __('RON - Leu')],
            ['value' => "EUR", 'label' => __('EUR - Euro')],
            ['value' => "USD", 'label' => __('USD - Dolar')],
            ['value' => "GBP", 'label' => __('GBP - Lira sterlina')],
            ['value' => "CAD", 'label' => __('CAD - Dolar canadian')],
            ['value' => "AUD", 'label' => __('AUD - Dolar australian')],
            ['value' => "CHF", 'label' => __('CHF - Franc elvetian')],
            ['value' => "TRY", 'label' => __('TRY - Lira turceasca')],
            ['value' => "CZK", 'label' => __('CZK - Coroana ceheasca')],
            ['value' => "DKK", 'label' => __('DKK - Coroana daneza')],
            ['value' => "HUF", 'label' => __('HUF - Forintul maghiar')],
            ['value' => "MDL", 'label' => __('MDL - Leu moldovenesc')],
            ['value' => "SEK", 'label' => __('SEK - Coroana suedeza')],
            ['value' => "BGN", 'label' => __('BGN - Leva bulgareasca')],
            ['value' => "NOK", 'label' => __('NOK - Coroana norvegiana')],
            ['value' => "JPY", 'label' => __('JPY - Yenul japonez')],
            ['value' => "EGP", 'label' => __('EGP - Lira egipteana')],
            ['value' => "PLN", 'label' => __('PLN - Zlotul polonez')],
            ['value' => "RUB", 'label' => __('RUB - Rubla')],
            ['value' => "MAGENTO", 'label' => __('Preluata din Magento')],
        ];

    }
}

