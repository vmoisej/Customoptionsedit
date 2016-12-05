var AdminOptions = new Class.create();
AdminOptions.prototype = {
    initialize : function(data){
        if(!data) data = {};
        this.loadBaseUrl    = false;
        this.showUpdateResultUrl    = false;
        this.customerId     = data.customer_id ? data.customer_id : false;
        this.storeId        = data.store_id ? data.store_id : false;
        this.itemId     = false;
        this.productConfigureAddFields = {};
    },

    setLoadBaseUrl : function(url){
        this.loadBaseUrl = url;
    },

    setShowUpdateResultUrl : function(url){
        this.showUpdateResultUrl = url;
    },

    /**
     * Show configuration of quote item
     *
     * @param itemId
     * @param quoteId
     */
    showQuoteItemConfiguration: function(itemId, quoteId){
        this.itemId = itemId;
        var listType = 'quote_items';
        var qtyElement = $('order-data').select('input[name="item\['+quoteId+'\]\[qty\]"]')[0];

        productConfigure.setShowWindowCallback(listType, function() {
            // sync qty of grid and qty of popup
            var formCurrentQty = productConfigure.getCurrentFormQtyElement();
            if (formCurrentQty && qtyElement && !isNaN(qtyElement.value)) {
                formCurrentQty.value = qtyElement.value;
            }
        }.bind(this));


        productConfigure.setConfirmCallback(listType, function() {
            // sync qty of popup and qty of grid
            var confirmedCurrentQty = productConfigure.getCurrentConfirmedQtyElement();
            if (qtyElement && confirmedCurrentQty && !isNaN(confirmedCurrentQty.value)) {
                qtyElement.value = confirmedCurrentQty.value;
            }
            this.productConfigureAddFields['item['+quoteId+'][configured]'] = 1;
            this.productConfigureAddFields['item_id'] = itemId;
        }.bind(this));
        productConfigure.showItemConfiguration(listType, quoteId);
    },

    // save Custom Options from popup
    onConfirmBtn: function()
    {
        productConfigure.onConfirmBtn();
        this.itemsUpdate();
    },


    itemsUpdate : function(){
        var area = ['custom_options'];
        // prepare additional fields
        var fieldsPrepare = {update_items: 1};
        fieldsPrepare = Object.extend(fieldsPrepare, this.productConfigureAddFields);
        this.productConfigureSubmit('quote_items', area, fieldsPrepare);
        this.orderItemChanged = false;
    },

    /**
     * Submit batch of configured products
     *
     * @param listType
     * @param area
     * @param fieldsPrepare
     */
    productConfigureSubmit : function(listType, area, fieldsPrepare) {

        // prepare loading areas and build url
        this.loadingAreas = area;
        var url = this.loadBaseUrl + 'block/' + area + '?isAjax=true';

        // prepare additional fields
        fieldsPrepare = this.prepareParams(fieldsPrepare);

        // create fields
        var fields = [];
        for (var name in fieldsPrepare) {
            fields.push(new Element('input', {type: 'hidden', name: name, value: fieldsPrepare[name]}));
        }
        productConfigure.addFields(fields);

        // prepare and do submit
        productConfigure.addListType(listType, {urlSubmit: url});

        productConfigure.setOnLoadIFrameCallback(listType, function(response){
            this.loadAreaResponseHandler(response);
        }.bind(this));

        productConfigure.submit(listType);
        // clean
        this.productConfigureAddFields = {};
        //this.loadArea(area, true)
    },

    loadArea : function(area, indicator, params){
        var url = this.showUpdateResultUrl;
        if (area) {
            url += 'block/' + area;
        }
        if (indicator === true)
            indicator = 'html-body';

        params = {'item_id': this.itemId};
        params = this.prepareParams(params);

        if (!this.loadingAreas)
            this.loadingAreas = [];

        if (indicator) {
            this.loadingAreas = area;
            area = this.getAreaId(area);
            new Ajax.Request(url, {
                parameters:params,
                loaderArea: indicator,
                onSuccess: function(transport) {
                    try {
                        if (transport.responseText.isJSON()) {
                            var response = transport.responseText.evalJSON()
                            if (response.error) {
                                alert(response.message);
                            }
                            if(response.ajaxExpired && response.ajaxRedirect) {
                                setLocation(response.ajaxRedirect);
                            }
                        } else {
                            $(area).update(transport.responseText);
                        }
                    }
                    catch (e) {
                        $(area).update(transport.responseText);
                    }
                }.bind(this)
            });
        }
        else {
            new Ajax.Request(url, {parameters:params,loaderArea: indicator});
        }
        if (typeof productConfigure != 'undefined' && area instanceof Array && area.indexOf('items') != -1) {
            productConfigure.clean('quote_items');
        }
    },

    reloadArea : function(area, indicator){
        var url = this.showUpdateResultUrl;
        if (indicator === true)
            indicator = 'html-body';

        area = this.getAreaId(area);
        new Ajax.Request(url, {
            loaderArea: indicator,
            onSuccess: function(transport) {
                try {
                    if (transport.responseText.isJSON()) {
                        var response = transport.responseText.evalJSON()
                        if (response.error) {
                            alert(response.message);
                        }
                        if(response.ajaxExpired && response.ajaxRedirect) {
                            setLocation(response.ajaxRedirect);
                        }
                    } else {
                        $(area).update(transport.responseText);
                    }
                }
                catch (e) {
                    $(area).update(transport.responseText);
                }
            }.bind(this)
        });
    },

    loadAreaResponseHandler : function (response){
        if (response.error) {
            alert(response.message);
        }
        if(response.ajaxExpired && response.ajaxRedirect) {
            setLocation(response.ajaxRedirect);
        }
        if(!this.loadingAreas){
            this.loadingAreas = [];
        }
        if(typeof this.loadingAreas == 'string'){
            this.loadingAreas = [this.loadingAreas];
        }
        if(this.loadingAreas.indexOf('message') == -1) {
            this.loadingAreas.push('message');
        }

        for(var i=0; i<this.loadingAreas.length; i++){
            var id = this.loadingAreas[i];

            if($(this.getAreaId(id))){
                if ('message' != id || response[id]) {
                    var wrapper = new Element('div');
                    wrapper.update(response[id] ? response[id] : '');
                    $(this.getAreaId(id)).update(Prototype.Browser.IE ? wrapper.outerHTML : wrapper);
                }
                if ($(this.getAreaId(id)).callback) {
                    this[$(this.getAreaId(id)).callback]();
                }
            }
        }
    },

    getAreaId : function(area){
        return 'order-'+area+"-"+this.itemId;
    },

    prepareParams : function(params){
        if (!params) {
            params = {};
        }
        if (!params.customer_id) {
            params.customer_id = this.customerId;
        }
        if (!params.store_id) {
            params.store_id = this.storeId;
        }
        if (!params.form_key) {
            params.form_key = FORM_KEY;
        }
        return params;
    }
};


