require([
	'jquery',
	'underscore',
    'Magento_Ui/js/modal/confirm',
    'Magento_Ui/js/modal/alert',
    'mage/translate'
], function ($, _, modalConfirm, alert) {
	'use strict';
	$(function() { 
        function smartbillRedirectToReports(ev){
            var linkData = $(this).data();
            var smartbillUrl = linkData.smartbillUrl;
            window.open(smartbillUrl);
        }
        function smartbillInvoiceRequest(ev,params){
            var linkData = $(ev.currentTarget).data();
            var invoiceId = parseInt(linkData.smartbillMagentoInvoice);
            var smartbillUrl = linkData.smartbillUrl;
            var data = {};
            data.invoiceId = invoiceId;
            var title = params.title;
            var content = params.content;
            modalConfirm(
                {
                    title: title,
                    content: content,
                    actions: {
                        confirm : function(){
                            $.ajax({
                                type: "POST",
                                url: smartbillUrl,
                                data: data,
                                success: function(data){
                                    if(data.status){
                                        alert({
                                            title: $.mage.__('Succes!'),
                                            content: data.message,
                                            actions: {
                                                always: function(){
                                                    if (params.reload_on_success){
                                                        window.location.reload();
                                                    }
                                                }
                                            }
                                        });
                                    }
                                    else {
                                        alert({
                                            title: $.mage.__('EROARE'),
                                            content: data.message
                                        });
                                    }
                                },
                                error: function(jqXHR, textStatus, errorThrown){
                                    var content;
                                    if (typeof errorThrown != "undefined" && errorThrown){
                                        content = $.mage.__('Eroare la conexiunea la server:') + textStatus + ' - ' + errorThrown;
                                    }
                                    else if(typeof textStatus != "undefined" && textStatus) {
                                        content = $.mage.__('Eroare la conexiunea la server:') + textStatus;
                                    }
                                    else {
                                        content = $.mage.__('Eroare la conexiunea la server.');
                                    }
                                    alert({
                                        title: $.mage.__('EROARE'),
                                        content: content
                                    });

                                }
                            });

                        }
                    }
                }
            );
        }
        $('#smartbill_view_draft').click(smartbillRedirectToReports);
        $('#smartbill_view_final').click(smartbillRedirectToReports);
        $('#smartbill_generate_invoice').click(function(ev){
            var params = {};
            params.title = $.mage.__('SmartBill Cloud : Generare facturi');
            params.content = $.mage.__('Doresti sa generezi factura pentru aceasta comanda?');
            params.reload_on_success = true;
            smartbillInvoiceRequest(ev, params);
        });
        $('#smartbill_undo_document').click(function(ev){
            var params = {};
            params.title = $.mage.__('SmartBill Cloud : Remitere facturi');
            params.content = '<h3>' + $.mage.__('Doresti sa remiti documentul in SmartBill Cloud?') + '</h3> <br/><h4>' + $.mage.__('Recomandam stergerea sau anularea facturii legate de aceasta comanda inainte de a face remiterea.') + '</h4>';
            params.reload_on_success = true;
            params.undo_redo_document = true;
            smartbillInvoiceRequest(ev, params);
        });
        $('#smartbill_send_invoice').click(function(ev){
            var params = {};
            params.title = $.mage.__('SmartBill Cloud : retrimitere factura');
            params.content = $.mage.__('Doresti sa retrimiti factura acestui client?');
            smartbillInvoiceRequest(ev, params);
        });


    });
});
