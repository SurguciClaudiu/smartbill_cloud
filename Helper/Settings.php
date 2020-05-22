<?php
/**
 * Copyright 2018-2019 © Intelligent IT SRL. All rights reserved.
 */

namespace SmartBill\Integration\Helper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Config;
use Magento\Store\Model\ScopeInterface;

class Settings extends \Magento\Framework\App\Helper\AbstractHelper{
    const SMARTBILL_CLOUD_LOGIN_URL_REDIRECT_TO_INVOICE_REPORTS = 'https://cloud.smartbill.ro/auth/login/?next=/raport/facturi/';
    const SMARTBILL_DATABASE_INVOICE_STATUS_DRAFT = 0;
    const SMARTBILL_DATABASE_INVOICE_STATUS_FINAL = 1;
    const VAT_SETTINGS_KEY = 'smartbill_integration/smartbill_settings/vat_code';
    const VAT_COMPANY_SETTINGS_KEY = 'smartbill_integration/smartbill_settings/vat_company';
    const VAT_DETAILS_SETTINGS_KEY = 'smartbill_integration/smartbill_settings/vat_details';
    const VAT_PRODUCTS_SETTINGS_KEY = 'smartbill_integration/smartbill_settings/vat_products';
    const USER_SETTINGS_KEY = 'smartbill_integration/smartbill_settings/user';
    const TOKEN_SETTINGS_KEY = 'smartbill_integration/smartbill_settings/token';
    const SMARTBILL_INVOICE_FROM_ECOMMERCE_PLATFORM_INVOICE_KEY = 'smartbill_integration/smartbill_settings/smartbill_invoice_from_magento_invoice';
    const INVOICE_TRANSPORTATION_VAT_KEY = 'smartbill_integration/smartbill_settings/invoice_transportation_vat';
    const INVOICE_TRANSPORTATION_VAT_INCLUDED_KEY = 'smartbill_integration/smartbill_settings/invoice_transportation_vat_included';
    const INVOICE_USE_PAYMENT_TAX_KEY = 'smartbill_integration/smartbill_settings/invoice_use_payment_tax';

    const INVOICE_SERIES_SETTINGS_KEY = 'smartbill_integration/smartbill_invoice_settings/invoice_series';
    const INVOICE_NOT_DRAFT_SETTINGS_KEY = 'smartbill_integration/smartbill_invoice_settings/invoice_not_draft';
    const INVOICE_USE_STOCK_SETTINGS_KEY = 'smartbill_integration/smartbill_invoice_settings/invoice_use_stock';
    const INVOICE_WHICH_STOCK_SETTINGS_KEY = 'smartbill_integration/smartbill_invoice_settings/invoice_which_stock';
    const INVOICE_DUE_DAYS_SETTINGS_KEY = 'smartbill_integration/smartbill_invoice_settings/invoice_due_days';
    const INVOICE_DELIVERY_DAYS_SETTINGS_KEY = 'smartbill_integration/smartbill_invoice_settings/invoice_delivery_days';
    const INVOICE_LANGUAGE_SETTINGS_KEY = 'smartbill_integration/smartbill_invoice_settings/invoice_language';
    const INVOICE_CURRENCY_SETTINGS_KEY = 'smartbill_integration/smartbill_invoice_settings/invoice_currency';
    const INVOICE_UNIT_SETTINGS_KEY = 'smartbill_integration/smartbill_invoice_settings/invoice_unit';
    const INVOICE_SAVE_CLIENT_SETTINGS_KEY = 'smartbill_integration/smartbill_invoice_settings/invoice_save_client';
    const INVOICE_SAVE_PRODUCT_SETTINGS_KEY = 'smartbill_integration/smartbill_invoice_settings/invoice_save_product';
    const INVOICE_TRANSPORTATION_KEY = 'smartbill_integration/smartbill_invoice_settings/invoice_transportation';
    const INVOICE_TRANSPORTATION_LABEL_KEY = 'smartbill_integration/smartbill_invoice_settings/invoice_transportation_label';

    const INVOICE_TYPE_KEY = 'smartbill_integration/smartbill_invoice_settings/invoice_type';
    const SMARTBILL_ESTIMATE_TYPE = 0;
    const SMARTBILL_INVOICE_TYPE = 1;

    const DEBUG_MODE_KEY = 'smartbill_integration/smartbill_invoice_settings/debug_mode';

    const INVOICE_NOTIFICATION_SETTINGS_KEY = 'smartbill_integration/smartbill_invoice_email_settings/invoice_notification';
    const INVOICE_NOTIFICATION_SUBJECT_SETTINGS_KEY = 'smartbill_integration/smartbill_invoice_email_settings/invoice_notification_subject';
    const INVOICE_NOTIFICATION_CC_SETTINGS_KEY = 'smartbill_integration/smartbill_invoice_email_settings/invoice_notification_cc';
    const INVOICE_NOTIFICATION_BCC_SETTINGS_KEY = 'smartbill_integration/smartbill_invoice_email_settings/invoice_notification_bcc';
    const INVOICE_NOTIFICATION_BODYTEXT_SETTINGS_KEY = 'smartbill_integration/smartbill_invoice_email_settings/invoice_notification_bodytext';
    //ID-ul din array-ul cu VAT-uri
    const INVOICE_VAT_FROM_ECOMMERCE_PLATFORM_KEY = -1;
    private static $settingsData;

    public function __construct(
        Context $context,
        Config $scopeInterface
    ){
        parent::__construct($context);
        $this->config = $scopeInterface;
    }

    /**
     * Functia returneaza valorile salvate din baza de date din setari
     *
     * @return string
     */
    public function getSettingsValue($settingsKey = null)
    {
        if (! $settingsKey) return false;
        if (isset(self::$settingsData) && isset(self::$settingsData[$settingsKey])){
            return self::$settingsData[$settingsKey];
        }
        return $this->config->getValue($settingsKey, ScopeInterface::SCOPE_STORE);
    }

    /**
     * Functia creeaza o lista cu toate setarile acestui modul existente la un moment dat, cu scopul de a fi salvate ulterior la fiecare cerere
     *
     * @return array
     */
    public function buildSettingsData()
    {
        if (! empty(self::$settingsData)){
            return self::$settingsData;
        }
        $self = new \ReflectionClass($this);
        $constants = $self->getConstants();
        $settingsData = [];
        foreach($constants as $constantKey => $constantValue){
            if(stristr($constantKey, 'KEY')){
                $settingsData[$constantKey] = $this->getSettingsValue($constantValue);
            }
        }
        self::$settingsData = $settingsData;
        return $settingsData;
    }
    /**
     * Functia primeste valori predefinite pentru setari si suprascrie datele primite din baza de date
     * primim valori de format VAT_SETTINGS_KEY = 'RO12345678'
     * si le convertim in valori de forma 'smartbill_integration/smartbill_settings/vat_code' = 'RO12345678'
     *
     * @return void
     */
    public function injectSettingsData($injectedSettingsData = null)
    {
        if (! $injectedSettingsData) return;
        if (! empty(self::$settingsData)){
            return self::$settingsData;
        }
        $self = new \ReflectionClass($this);
        $constants = $self->getConstants();
        $settingsData = [];
        foreach($constants as $constantKey => $constantValue){
            if(stristr($constantKey, 'KEY') && isset($injectedSettingsData[$constantKey])){
                //
                $settingsData[$constantValue] = $injectedSettingsData[$constantKey];
            }
        }
        self::$settingsData = $settingsData;
        return $settingsData;

    }


}
