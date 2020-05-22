<?php

namespace SmartBill\Integration\Observer;

use Magento\Framework\Event\ObserverInterface;
use Magento\Framework\Event\Observer as EventObserver;
use Magento\Framework\Message\ManagerInterface;
use SmartBill\Integration\Helper\Data;
use SmartBill\Integration\Model\Config\BaseConfig;


class ConfigObserver implements ObserverInterface
{
    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * @param Logger $logger
     */
    public function __construct(
        ManagerInterface $messageManager,
        Data $helper
    ) {
        $this->messageManager = $messageManager;
        $this->helper = $helper;
    }

    public function execute(EventObserver $observer)
    {
        $helper = $this->helper;
        $messageManager = $this->messageManager;

        BaseConfig::checkPlatformVersion($helper, $messageManager, $showErrors = true);
        BaseConfig::checkIfCompanyIsVatPayable($helper, $messageManager, $showErrors = true);
        BaseConfig::getAllMeasuringUnits($helper, $messageManager, $showErrors = true);
        BaseConfig::getInvoiceSeries($helper, $messageManager, $showErrors = true);
        BaseConfig::getInvoiceStocks($helper, $messageManager, $showErrors = true);

    }
}
