<?php
/**
 * Copyright 2018-2019 © Intelligent IT SRL. All rights reserved.
 */

namespace SmartBill\Integration\Model;
use SmartBill\Integration\Model\Invoice as SmartBillInvoice;
use SmartBill\Integration\Helper\Settings;

class SmartBillCloudRestClient {
    const INVOICE_URL             = 'https://ws.smartbill.ro/SBORO/api/invoice';
    const INVOICE_URL_WITH_DOCUMENT_ADDRESS             = 'https://ws.smartbill.ro/SBORO/api/invoice/v2';
    const MEASURING_UNITS_URL             = 'https://ws.smartbill.ro/SBORO/api/company/mu?cif=%s';
    const STATUS_INVOICE_URL      = 'https://ws.smartbill.ro/SBORO/api/invoice/paymentstatus';
    const PROFORMA_URL            = 'https://ws.smartbill.ro/SBORO/api/estimate';
    const PROFORMA_URL_WITH_DOCUMENT_ADDRESS            = 'https://ws.smartbill.ro/SBORO/api/estimate/v2';
    const STATUS_PROFORMA_URL     = 'https://ws.smartbill.ro/SBORO/api/estimate/invoices';
    const PAYMENT_URL             = 'https://ws.smartbill.ro/SBORO/api/payment';
    const EMAIL_URL               = 'https://ws.smartbill.ro/SBORO/api/document/send';
    const TAXES_URL               = 'https://ws.smartbill.ro/SBORO/api/tax?cif=%s';
    const SERIES_URL              = 'https://ws.smartbill.ro/SBORO/api/series?cif=%s&type=%s';
    const PRODUCTS_STOCK_URL      = 'https://ws.smartbill.ro/SBORO/api/stocks?cif=%s&date=%s&warehouseName=%s&productName=%s&productCode=%s';
    const PARAMS_PDF              = '/pdf?cif=%s&seriesname=%s&number=%s';
    const PARAMS_DELETE           = '?cif=%s&seriesname=%s&number=%s';
    const PARAMS_DELETE_RECEIPT   = '/chitanta?cif=%s&seriesname=%s&number=%s';
    const PARAMS_CANCEL           = '/cancel?cif=%s&seriesname=%s&number=%s';
    const PARAMS_RESTORE          = '/restore?cif=%s&seriesname=%s&number=%s';
    const PARAMS_STATUS           = '?cif=%s&seriesname=%s&number=%s';
    const PARAMS_FISCAL_RECEIPT   = '/text?cif=%s&id=%s';

    const PaymentType_OrdinPlata  = 'Ordin plata';
    const PaymentType_Chitanta    = 'Chitanta';
    const PaymentType_Card        = 'Card';
    const PaymentType_CEC         = 'CEC';
    const PaymentType_BiletOrdin  = 'Bilet ordin';
    const PaymentType_MandatPostal= 'Mandat postal';
    const PaymentType_Other       = 'Alta incasare';
    const PaymentType_BonFiscal   = 'Bon';

    const DiscountType_Valoric    = 1;
    const DiscountType_Value      = 1; // en
    const DiscountType_Procentual = 2;
    const DiscountType_Percent    = 2; // en

    const DocumentType_Invoice    = 'factura'; // en
    const DocumentType_Factura    = 'factura';
    const DocumentType_Proforma   = 'proforma';
    const DocumentType_Receipt    = 'chitanta'; // en
    const DocumentType_Chitanta   = 'chitanta';

    const DEBUG_ON_ERROR = false; // use this only in development phase; DON'T USE IN PRODUCTION !!!
    const DATA_TYPES = array(
        "string" => array("address", "aviz", "bank", "bcc", "bodyText", "cc", "city", "clientName", "clientCif", "code", "companyVatCode", "contact", "country", "county", "currency", "delegateAuto", "delegateIdentityCard", "delegateName", "deliveryDate", "dueDate", "email", "iban", "invoiceNumber", "invoiceSeries", "invoicesList", "issueDate", "issuerCnp", "issuerName", "language", "measuringUnitName", "mentions", "name", "number", "observation", "observations", "paymentDate", "paymentType", "paymentSeries", "paymentURL",  "phone", "productDescription", "regCom", "seriesName", "subject", "taxName", "text", "to", "translatedMeasuringUnit", "translatedName", "translatedText", "type", "vatCode", "warehouseName", "warehouseType"),
        "boolean" => array("isTaxPayer", "saveToDb", "isDraft", "useStock", "useEstimateDetails", "usePaymentTax", "isDiscount", "isTaxIncluded", "isService", "isCash", "useInvoiceDetails", "returnFiscalPrinterText", "areInvoicesCreated"),
        "double" => array( "colectedTax", "discountPercentage", "discountValue", "exchangeRate", "invoiceTotalAmount", "paidAmount", "paymentBase", "paymentTotal", "paymentValue", "price", "quantity", "receivedBonuriValoareFixa", "receivedCard", "receivedCash", "receivedCec", "receivedCredit", "receivedCupon", "receivedMonedaAlternativa", "receivedOrdinDePlata", "receivedPuncteDeFidelitate", "receivedTicheteCadou", "receivedTicheteMasa", "taxPercentage", "unpaidAmount", "value"),
        "integer" => array("precision", "numberOfItems", "discountType")
    );
    private $hash   = '';
    //@param $dataLogger \SmartBill\Integration\Model\ResourceModel\Invoice\Collection
    private $dataLogger = null;

    private $magentoInvoiceId = null;
    private $magentoOrderId = null;
    //@param $magentoInfo \Magento\Framework\App\ProductMetadata - used to get Magento version
    private $magentoInfo = null;

    //this will be used to contain store settings details for debugging purposes, if debugging on
    private $magentoSettingsDetails = null;

    //this will be used to get full order info 
    private $magentoFullDetails = null;
    /**
     * setter for $magentoSettingsDetails
     *
     * @return void
     */
    public function setMagentoSettingsDetails($magentoSettingsDetails = null)
    {
        if (! $magentoSettingsDetails){
            $this->magentoSettingsDetails = new \stdClass();
        }
        else {
            $this->magentoSettingsDetails = $magentoSettingsDetails;
        }
    }
    /**
     * setter for $magentoFullDetails
     *
     * @return void
     */
    public function setMagentoFullDetails($magentoFullDetails = null)
    {
        $this->magentoFullDetails = $magentoFullDetails;
    }
    
    

    /**
     * getter for $magentoInfo
     *
     * @return void
     */
    public function getMagentoInfo()
    {
        return $this->magentoInfo;
    }
     /**
     * setter for $magentoInfo
     *
     * @return void
     */
    public function setMagentoInfo($magentoInfo = null)
    {
        $this->magentoInfo = $magentoInfo;
    }
    

    function __construct($user, $token) {
        $this->hash = base64_encode($user.':'.$token);
    }
    /**
     * Getter for dataLogger
     *
     * @return string
     */
    public function getDataLogger()
    {
        return $this->dataLogger;
    }
    
    /**
     * Setter for dataLogger
     *
     * @param string $dataLogger
     * @return SmartBillCloudRestClient
     */
    public function setDataLogger($dataLogger)
    {
        $this->dataLogger = $dataLogger;
    
        return $this;
    }
    /**
     * Getter for magentoOrderId
     *
     * @return string
     */
    public function getMagentoOrderId()
    {
        return $this->magentoOrderId;
    }
    
    /**
     * Setter for magentoOrderId
     *
     * @param string $magentoOrderId
     * @return SmartBillCloudRestClient
     */
    public function setMagentoOrderId($magentoOrderId)
    {
        $this->magentoOrderId = $magentoOrderId;
    
        return $this;
    }
    /**
     * Getter for magentoInvoiceId
     *
     * @return string
     */
    public function getMagentoInvoiceId()
    {
        return $this->magentoInvoiceId;
    }
    
    /**
     * Setter for magentoInvoiceId
     *
     * @param string $magentoInvoiceId
     * @return SmartBillCloudRestClient
     */
    public function setMagentoInvoiceId($magentoInvoiceId)
    {
        $this->magentoInvoiceId = $magentoInvoiceId;
    
        return $this;
    }
    /**

     * Get hash information
     *
     * @return string $hash
     */
    public function getHash()
    {
        return $this->hash;
    }
    /**
     * setter for hash
     *
     * @return void
     */
    public function setHash($hash)
    {
        $this->hash = $hash;
    }
    
    

    private function _cURL($url, $data, $request, $headAccept) {
        $headers = array($headAccept, "Authorization: Basic " . $this->hash);

        $ch = curl_init($url);
        // curl_setopt($ch, CURLOPT_MUTE, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        // curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_VERBOSE, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        if ( !empty($data) ) {
            $headers[] = "Content-Type: application/json; charset=utf-8";
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        if ( !empty($request)) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $request);
        }
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

        // debugging
        $isDebug = self::DEBUG_ON_ERROR;
        if ( !empty($isDebug) ) {
            $debug = array(
                'URL: '     => $url,
                'data: '    => $data,
                'headers: ' => $headAccept,
            );
            echo '<pre>' , print_r($debug, true), '</pre>';
        }

        return $ch;
    }

    private function _callServer($url, $data='', $request='', $headAccept="Accept: application/json") {
        if (empty($url))   return FALSE;

        //Data formatting 
        $data = self::convertDataToStrictDataTypes($data);
        $ch     = $this->_cURL($url, $data, $request, $headAccept);
        $return = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if( isset($this->dataLogger) && $this->dataLogger instanceof SmartBillInvoice ) {
            
            if( is_numeric($this->getMagentoInvoiceId())){
                //celelalte date de tipul serie/numar vor fi salvate in cealalta functie de trimitere cereri de emitere factura
                //care o apeleaza pe aceasta, deoarece este o logica mult mai specifica decat aici
                $current_timestamp = time();
                $sent_data = $data;
                $received_data = json_decode($return, $array = true);
                $received_data['status'] = $status;
                if($this->dataLogger->getData('invoice_id') == $this->getMagentoInvoiceId()){
                    $existing_sent_data = json_decode($this->dataLogger->getData('sent_data'), $array = true);
                    $existing_received_data = json_decode($this->dataLogger->getData('received_data'), $array = true);

                    $existing_sent_data[$current_timestamp] = $sent_data;
                    $existing_received_data[$current_timestamp] = $received_data;


                    $this->dataLogger
                        ->setData('sent_data', json_encode($existing_sent_data))
                        ->setData('received_data', json_encode($existing_received_data))
                        ->setData('updated_at', date('Y-m-d H:i:s', $current_timestamp))
                        ->save();
                }
                else {
                    //creare date initiale
                    $this->dataLogger->setData('invoice_id', $this->getMagentoInvoiceId())
                        ->setData('sent_data', json_encode([$current_timestamp => $sent_data]))
                        ->setData('received_data', json_encode([$current_timestamp => $received_data]))
                        ->setData('created_at', date('Y-m-d H:i:s', $current_timestamp))
                        ->setData('updated_at', date('Y-m-d H:i:s', $current_timestamp))
                        ->save();
                }
            }
            else if( is_numeric($this->getMagentoOrderId())){
                //celelalte date de tipul serie/numar vor fi salvate in cealalta functie de trimitere cereri de emitere factura
                //care o apeleaza pe aceasta, deoarece este o logica mult mai specifica decat aici
                $current_timestamp = time();
                $sent_data = $data;
                $received_data = json_decode($return, $array = true);
                $received_data['status'] = $status;
                if($this->dataLogger->getData('order_id') == $this->getMagentoOrderId()){
                    $existing_sent_data = json_decode($this->dataLogger->getData('sent_data'), $array = true);
                    $existing_received_data = json_decode($this->dataLogger->getData('received_data'), $array = true);

                    $existing_sent_data[$current_timestamp] = $sent_data;
                    $existing_received_data[$current_timestamp] = $received_data;


                    $this->dataLogger
                        ->setData('sent_data', json_encode($existing_sent_data))
                        ->setData('received_data', json_encode($existing_received_data))
                        ->setData('updated_at', date('Y-m-d H:i:s', $current_timestamp))
                        ->save();
                }
                else {
                    //creare date initiale
                    $this->dataLogger->setData('order_id', $this->getMagentoOrderId())
                        ->setData('sent_data', json_encode([$current_timestamp => $sent_data]))
                        ->setData('received_data', json_encode([$current_timestamp => $received_data]))
                        ->setData('created_at', date('Y-m-d H:i:s', $current_timestamp))
                        ->setData('updated_at', date('Y-m-d H:i:s', $current_timestamp))
                        ->save();
                }
            }
        }
        if ($status!=200) {
            $errorMessage = json_decode($return, true);

            if ( false !== strpos($url, self::EMAIL_URL) ) {
                $errorMessage = !empty($errorMessage['status']['code']) ? $errorMessage['status']['message'] : $return;
            } else {
                $errorMessage = !empty($errorMessage['errorText']) ? $errorMessage['errorText'] : $return;
            }

            throw new \Exception($errorMessage );
            // empty response
            $return = '';
        } elseif ( false === strpos($url, '/pdf?') ) {
            $return = json_decode($return, true);
        }

        return $return;
    }

    private function _prepareDocumentData($data) {
        if ( !empty($data['subject']) ) {
            $data['subject'] = base64_encode($data['subject']);
        }
        if ( !empty($data['bodyText']) ) {
            $data['bodyText'] = base64_encode($data['bodyText']);
        }
        return $data;
    }

    private function setPluginInformation($data, $skipDetails = false){
         //plugin info
        $data['ecommercePluginInfo']['platformName'] = 'magento';
        $data['ecommercePluginInfo']['platformVersion'] = $this->getMagentoInfo()->getVersion();
        $data['ecommercePluginInfo']['sbPluginVersion'] = SMARTBILL_PLUGIN_VERSION;
        if ($skipDetails){
            $data['ecommercePluginInfo']['details'] = new \stdClass();
        }
        else {
            $data['ecommercePluginInfo']['details'] = new \stdClass();
            foreach($this->magentoSettingsDetails as $key => $value){
                $newKey = 'settings_' . $key;
                $data['ecommercePluginInfo']['details']->{$newKey} = $value;
            }
            if($this->magentoFullDetails ){
                foreach($this->magentoFullDetails as $key => $value){
                    $newKey = 'order_' . $key;
                    $data['ecommercePluginInfo']['details']->{$newKey} = $value;
                }
            }
        }
        return $data;
    }

    //diferenta intre cele doua metode de createInvoice este ca cea de mai jos
    //returneaza si adresa facturii din SmartBill Cloud - fie ciorna, fie finala
    //spre care se poate trimite o legatura web
    public function createInvoiceWithDocumentAddress($data, $skipDetails = false) {
        $data = $this->setPluginInformation($data, $skipDetails);
        $return = $this->_callServer(self::INVOICE_URL_WITH_DOCUMENT_ADDRESS, $data);
        return $return;
    }


    public function createInvoice($data) {
        $data = $this->setPluginInformation($data);
        return $this->_callServer(self::INVOICE_URL, $data);
    }

    //diferenta intre cele doua metode de createInvoice este ca cea de mai jos
    //returneaza si adresa facturii din SmartBill Cloud - fie ciorna, fie finala
    //spre care se poate trimite o legatura web
    public function createProformaWithDocumentAddress($data, $skipDetails = false) {
        $data = $this->setPluginInformation($data, $skipDetails);
        $return = $this->_callServer(self::PROFORMA_URL_WITH_DOCUMENT_ADDRESS, $data);
        return $return;
    }

    public function createProforma($data) {
        $data = $this->setPluginInformation($data);
        return $this->_callServer(self::PROFORMA_URL, $data);
    }

    public function createPayment($data) {
        return $this->_callServer(self::PAYMENT_URL, $data);
    }

    public function PDFInvoice($companyVatCode, $seriesName, $number) {
        $url = sprintf(self::INVOICE_URL . self::PARAMS_PDF, $companyVatCode, $seriesName, $number);
        return $this->_callServer($url, '', '', "Accept: application/octet-stream");
    }

    public function PDFProforma($companyVatCode, $seriesName, $number) {
        $url = sprintf(self::PROFORMA_URL . self::PARAMS_PDF, $companyVatCode, $seriesName, $number);
        return $this->_callServer($url, '', '', "Accept: application/octet-stream");
    }

    public function deleteInvoice($companyVatCode, $seriesName, $number) {
        $url = sprintf(self::INVOICE_URL . self::PARAMS_DELETE, $companyVatCode, $seriesName, $number);
        return $this->_callServer($url, '', 'DELETE');
    }

    public function deleteProforma($companyVatCode, $seriesName, $number) {
        $url = sprintf(self::PROFORMA_URL . self::PARAMS_DELETE, $companyVatCode, $seriesName, $number);
        return $this->_callServer($url, '', 'DELETE');
    }

    public function deleteReceipt($companyVatCode, $seriesName, $number) {
        $url = sprintf(self::PAYMENT_URL . self::PARAMS_DELETE_RECEIPT, $companyVatCode, $seriesName, $number);
        return $this->_callServer($url, '', 'DELETE');
    }

    public function deletePayment($payment) {
        return $this->_callServer(self::PAYMENT_URL, $payment, 'DELETE');
    }

    public function cancelInvoice($companyVatCode, $seriesName, $number) {
        $url = sprintf(self::INVOICE_URL . self::PARAMS_CANCEL, $companyVatCode, $seriesName, $number);
        return $this->_callServer($url, '', 'PUT');
    }

    public function cancelProforma($companyVatCode, $seriesName, $number) {
        $url = sprintf(self::PROFORMA_URL . self::PARAMS_CANCEL, $companyVatCode, $seriesName, $number);
        return $this->_callServer($url, '', 'PUT');
    }

    public function cancelPayment($companyVatCode, $seriesName, $number) {
        $url = sprintf(self::PAYMENT_URL . self::PARAMS_CANCEL, $companyVatCode, $seriesName, $number);
        return $this->_callServer($url, '', 'PUT');
    }

    public function restoreInvoice($companyVatCode, $seriesName, $number) {
        $url = sprintf(self::INVOICE_URL . self::PARAMS_RESTORE, $companyVatCode, $seriesName, $number);
        return $this->_callServer($url, '', 'PUT');
    }

    public function restoreProforma($companyVatCode, $seriesName, $number) {
        $url = sprintf(self::PROFORMA_URL . self::PARAMS_RESTORE, $companyVatCode, $seriesName, $number);
        return $this->_callServer($url, '', 'PUT');
    }

    public function sendDocument($data) {
        $data = $this->_prepareDocumentData($data);
        return $this->_callServer(self::EMAIL_URL, $data);
    }

    public function getMeasuringUnits($companyVatCode) {
        $url = sprintf(self::MEASURING_UNITS_URL, $companyVatCode);
        return $this->_callServer($url);
    }


    public function getTaxes($companyVatCode) {
        $url = sprintf(self::TAXES_URL, $companyVatCode);
        return $this->_callServer($url);
    }

    public function getDocumentSeries($companyVatCode, $documentType='') {
        $documentType = !empty($documentType) ? substr($documentType, 0, 1) : $documentType; // take the 1st character
        $url = sprintf(self::SERIES_URL, $companyVatCode, $documentType);
        return $this->_callServer($url);
    }

    public function statusInvoicePayments($companyVatCode, $seriesName, $number) {
        $url = sprintf(self::STATUS_INVOICE_URL . self::PARAMS_STATUS, $companyVatCode, $seriesName, $number);
        return $this->_callServer($url);
    }

    public function statusProforma($companyVatCode, $seriesName, $number) {
        $url = sprintf(self::STATUS_PROFORMA_URL . self::PARAMS_STATUS, $companyVatCode, $seriesName, $number);
        return $this->_callServer($url);
    }

    public function detailsFiscalReceipt($companyVatCode, $id) {
        $url  = sprintf(self::PAYMENT_URL . self::PARAMS_FISCAL_RECEIPT, $companyVatCode, $id);
        $text = $this->_callServer($url);
        try {
            $text = base64_decode($text['message']);
        } catch (\Exception $ex) {
            throw new \Exception('invalid / empty response');
        }

        return $text;
    }

    public function productsStock($data) {
        $data = self::_validateProductsStock($data);
        $url  = self::_urlProductsStock($data);
        $list = $this->_callServer($url);
        try {
            $list = $list['list'];
        } catch (\Exception $ex) {
            throw new \Exception('invalid / empty response');
        }

        return $list;
    }
    private static function _validateProductsStock($data) {
        // append required keys in case they are missing
        $data += array(
            'cif'           => '',
            'date'          => date('Y-m-d'),
            'warehouseName' => '',
            'productName'   => '',
            'productCode'   => '',
        );
        // urlencode values
        foreach ($data as $key => $value) {
            $value = urlencode($value);
            $data[$key] = $value;
        }
        return $data;
    }
    private static function _urlProductsStock($data) {
        return sprintf(self::PRODUCTS_STOCK_URL, $data['cif'], $data['date'], $data['warehouseName'], $data['productName'], $data['productCode']);
    }
    public static function convertDataToStrictDataTypes($data){
        $dataTypes = SmartBillCloudRestClient::DATA_TYPES;
        $stringTypes = $dataTypes['string'];
        $booleanTypes = $dataTypes['boolean'];
        $doubleTypes = $dataTypes['double'];
        $integerTypes = $dataTypes['integer'];
        if (is_array($data)){
            foreach($data as $key => $item){
                if(is_array($data[$key])){
                    $data[$key] = self::convertDataToStrictDataTypes($data[$key]);
                }
                else {
                    if(in_array($key, $stringTypes)){
                        $data[$key]  = strval($item);
                    }
                    if(in_array($key, $booleanTypes)){
                        $data[$key]  = boolval($item);
                    }
                    if(in_array($key, $doubleTypes)){
                        $data[$key]  = doubleval($item);
                    }
                    if(in_array($key, $integerTypes)){
                        $data[$key]  = intval($item);
                    }
                }
            }
            return $data;
        }
        return null;
    }
}
