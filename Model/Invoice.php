<?php
/**
 * Copyright 2018-2019 © Intelligent IT SRL. All rights reserved.
 */

namespace SmartBill\Integration\Model;

class Invoice extends \Magento\Framework\Model\AbstractModel {

    protected function _construct(){
        $this->_init(\SmartBill\Integration\Model\ResourceModel\Invoice::class);
    }
   
}


