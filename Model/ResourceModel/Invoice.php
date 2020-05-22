<?php
/**
 * Copyright 2018-2019 © Intelligent IT SRL. All rights reserved.
 */

namespace SmartBill\Integration\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Invoice extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('smartbill_invoice', 'id');
    }
}
