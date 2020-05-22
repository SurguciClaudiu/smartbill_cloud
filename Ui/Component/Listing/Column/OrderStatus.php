<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace SmartBill\Integration\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Sales\Model\ResourceModel\Order\Status\CollectionFactory;

use SmartBill\Integration\Model\ResourceModel\Invoice\CollectionFactory as InvoiceCollectionFactory;

/**
 * Class Status
 */
class OrderStatus extends Column
{
    /**
     * @var string[]
     */
    protected $statuses;

    /**
     * Constructor
     *
     * @param ContextInterface $context
     * @param UiComponentFactory $uiComponentFactory
     * @param CollectionFactory $collectionFactory
     * @param array $components
     * @param array $data
     */
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        CollectionFactory $collectionFactory,
        InvoiceCollectionFactory $invoiceCollectionFactory,
        array $components = [],
        array $data = []
    ) {
        $this->invoiceCollectionFactory = $invoiceCollectionFactory->create();
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    /**
     * Prepare Data Source
     *
     * @param array $dataSource
     * @return void
     */
    public function prepareDataSource(array $dataSource)
    {

        if (isset($dataSource['data']['items'])) {
            foreach ($dataSource['data']['items'] as & $item) {
                $orderId = $item['entity_id'];
                try{
                    $invoiceCollectionFactoryResults = $this->invoiceCollectionFactory->getItemByColumnValue('order_id', $orderId);
                    if ($invoiceCollectionFactoryResults != null && $invoiceCollectionFactoryResults->getData('order_id') == $orderId){
                        if( $smartbillDocumentUrl = $invoiceCollectionFactoryResults->getData('smartbill_document_url') ){
                            //Modificam sa duca spre vizualizare, nu spre editare
                            $pattern = '/editare/';
                            $replacement = 'vizualizare';
                            $smartbillDocumentUrl = preg_replace($pattern, $replacement, $smartbillDocumentUrl, -1 );
                            $smartbillSeries = $invoiceCollectionFactoryResults->getData('smartbill_series');
                            $smartbillInvoiceID = $invoiceCollectionFactoryResults->getData('smartbill_invoice_id');


                            $item[$this->getData('name')] = [
                                'view' => [
                                    'href' => $smartbillDocumentUrl,
                                    'label' => $smartbillSeries. ' '. $smartbillInvoiceID,
                                    'target' => '_blank',
                                ]
                            ];

                        }
                        //daca nu exista document URL dar totusi a fost emisa factura
                        else {
                            $item[$this->getData('name')] = __('Da');
                        }
                    }
                    else{
                        $item[$this->getData('name')] = __('Nu');
                    }
                }
                catch(\Exception $e){
                    $item[$this->getData('name')] = __('Eroare');
                }
            }
        }

        return $dataSource;
    }
}
