<?php
/**
 * Copyright 2018-2019 © Intelligent IT SRL. All rights reserved.
 */

namespace SmartBill\Integration\Model;
use SmartBill\Integration\Helper\Settings;
use Magento\Framework\App\ProductMetadata;

class MagentoSmartBillCloudRestClient extends \Magento\Framework\Model\AbstractModel {

    private $settings;
    private $connector;
    private $debugInfo;

    public function __construct(
        Settings $settings,
        ProductMetadata $magentoInfo
    ) {
        $this->settings = $settings;
        $userSettingsKey = Settings::USER_SETTINGS_KEY;
        $tokenSettingsKey = Settings::TOKEN_SETTINGS_KEY;
        $user = $this->settings->getSettingsValue($userSettingsKey);
        $token = $this->settings->getSettingsValue($tokenSettingsKey);

        $this->connector = new \SmartBill\Integration\Model\SmartBillCloudRestClient($user, $token);
        $this->connector->setMagentoInfo($magentoInfo);
         
        $debugModeKey = Settings::DEBUG_MODE_KEY;
        $debugModeOn = $this->settings->getSettingsValue($debugModeKey);
        if (! $debugModeOn){
            $this->connector->setMagentoSettingsDetails(null);
        }
        else {
            $storeDetails = $this->settings->buildSettingsData();
            $this->connector->setMagentoSettingsDetails($storeDetails);
            if($this->debugInfo){
                $this->connector->setMagentoFullDetails($debugInfo);
            }
        }

    }

    /**
     * Functia returneaza clientul REST de la SmartBill Cloud
     *
     * @return \SmartBill\Integration\Model\SmartBillCloudRestClient
     */
    public function getConnector()
    {
        return $this->connector;
    }

    /**
     * setter pentru $debugInfo
     *
     * @return void
     */
    public function setDebugInfo($debugInfo = null)
    {
        $this->debugInfo = $debugInfo;
    }
    
    


}
