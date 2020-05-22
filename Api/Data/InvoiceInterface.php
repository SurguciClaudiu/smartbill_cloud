<?php
/**
 * Copyright 2018-2019 © Intelligent IT SRL. All rights reserved.
 */

namespace SmartBill\Integration\Api\Data;

interface InvoiceInterface extends \Magento\Framework\Api\ExtensibleDataInterface{
    /**
     * Get Table ID
     *
     * @return integer id
     */
    public function getId();

    /**
     * Get Magento Invoice ID
     *
     * @return integer invoiceId
     */
    public function getInvoiceId();

    /**
     * Get SmartBill Invoice ID
     *
     * @return integer smartbillInvoiceId
     */
    public function getSmartbillInvoiceId();




}


