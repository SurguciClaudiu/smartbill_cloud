<?php
/**
 * Copyright 2018-2019 © Intelligent IT SRL. All rights reserved.
 */

namespace SmartBill\Integration\Model\ResourceModel\Invoice;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use SmartBill\Integration\Model\Invoice;
use SmartBill\Integration\Model\ResourceModel\Invoice as InvoiceResource;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'id';

    protected function _construct()
    {
        $this->_init(Invoice::class, InvoiceResource::class);
    }
}
