<?php
/**
 * Copyright 2018-2019 © Intelligent IT SRL. All rights reserved.
 */

namespace SmartBill\Integration\Setup;

use Magento\Framework\Setup\InstallSchemaInterface;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\SchemaSetupInterface;
use Magento\Framework\DB\Ddl\Table;

class InstallSchema implements InstallSchemaInterface
{
    /**
     * {@inheritdoc}
     */
    public function install(SchemaSetupInterface $setup, ModuleContextInterface $context)
    {
        $setup->startSetup();

        if ($setup->getConnection()->isTableExists('smartbill_invoice') != true){
            $table = $setup->getConnection()->newTable(
                $setup->getTable('smartbill_invoice')
            )->addColumn(
                'id',
                Table::TYPE_INTEGER,
                null,
                ['identity' => true, 'nullable' => false, 'primary' => true],
                'Table ID'
            )->addColumn(
                'invoice_id',
                Table::TYPE_INTEGER,
                null,
                ['unsigned' => true,'identity' => false, 'nullable' => false, 'primary' => false],
                'Magento Invoice ID'
            )->addColumn(
                'order_id',
                Table::TYPE_INTEGER,
                null,
                ['unsigned' => true,'identity' => false, 'nullable' => false, 'primary' => false],
                'Magento Order ID'
            )->addColumn(
                'smartbill_invoice_id',
                Table::TYPE_TEXT,
                255,
                [],
                'SmartBill Cloud Invoice ID'
            )
            ->addColumn(
                'smartbill_series',
                Table::TYPE_TEXT,
                255,
                [],
                'Invoice series returned by call'
            )->addColumn(
                'smartbill_document_url',
                Table::TYPE_TEXT,
                Table::MAX_TEXT_SIZE,
                [],
                'The internal SmartBill Cloud document URL for the invoice'
            )->addColumn(
                'smartbill_document_type',
                Table::TYPE_SMALLINT,
                1,
                ['nullable' => false, 'default' => 1],
                'SmartBill Cloud Document type : estimate/proforma (0), invoice/factura (1)'
            )->addColumn(
                'smartbill_status',
                Table::TYPE_SMALLINT,
                1,
                ['nullable' => false, 'default' => 0],
                'Status for entity : initial (0), complete (1)'
            )->addColumn(
                'sent_data',
                Table::TYPE_TEXT,
                Table::MAX_TEXT_SIZE,
                [],
                'Sent data log to SmartBill'
            )->addColumn(
                'received_data',
                Table::TYPE_TEXT,
                Table::MAX_TEXT_SIZE,
                [],
                'Received data log from SmartBill'
            )->addColumn(
                'settings_data',
                Table::TYPE_TEXT,
                Table::MAX_TEXT_SIZE,
                [],
                'Magento settings object when requested'
            )->addColumn(
                'created_at',
                Table::TYPE_TIMESTAMP,
                255,
                ['nullable' => false, 'default' => Table::TIMESTAMP_INIT],
                'When the request was created'
            )->addColumn(
                'updated_at',
                Table::TYPE_TIMESTAMP,
                255,
                ['nullable' => false, 'default' => Table::TIMESTAMP_INIT],
                'When the request was updated'
            )->addIndex(
                $setup->getIdxName('smartbill_invoice', ['smartbill_invoice_id']),
                ['id']
            )->setComment(
                'SmartBill Invoices'
            );
            $setup->getConnection()->createTable($table);


        }
        $setup->endSetup();
    }
}
