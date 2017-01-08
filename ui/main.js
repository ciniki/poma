//
// This is the main app for the poma module
//
function ciniki_poma_main() {
    this.weightToggles = {'20':'lb', '25':'oz', '60':'kg', '65':'g'};
    this.unitFlags = {'9':{'name':'Each'}, '10':{'name':'Pair'}, '11':{'name':'Bunch'}, '12':{'name':'Bag'}};
    this.caseFlags = {'17':{'name':'Case'}, '18':{'name':'Bushel'}, };

    //
    // The panel to list the orderdate
    //
    this.menu = new M.panel('Order Management', 'ciniki_poma_main', 'menu', 'mc', 'large narrowaside', 'sectioned', 'ciniki.poma.main.menu');
    this.menu.data = {};
    this.menu.customer_id = 0;
    this.menu.date_id = 0;
    this.menu.order_id = 0;
    this.menu.nplists = {'orderitems':[]};
    this.menu.date_nplist = [];
    this.menu.sections = {
        '_tabs':{'label':'', 'type':'menutabs', 'selected':'checkout', 'tabs':{
            'checkout':{'label':'Checkout', 'fn':'M.ciniki_poma_main.menu.open(null,"checkout");'},
            'orders':{'label':'Orders', 'fn':'M.ciniki_poma_main.menu.open(null,"orders");'},
            'repeats':{'label':'Standing', 'fn':'M.ciniki_poma_main.menu.open(null,"repeats");'},
            'queue':{'label':'Queue', 'fn':'M.ciniki_poma_main.menu.open(null,"queue");'},
            'dates':{'label':'Dates', 'fn':'M.ciniki_poma_main.menu.open(null,"dates");'},
//            'favourites':{'label':'Favourites', 'fn':'M.ciniki_poma_main.menu.open(null,"favourites");'}, // MOVED TO foodmarket
            }},
        '_dates':{'label':'Change Date', 'aside':'yes',
            'visible':function() { return (M.ciniki_poma_main.menu.sections._tabs.selected == 'checkout') ? 'yes':'no'; },
            'fields':{
                'date_id':{'label':'', 'hidelabel':'yes', 'type':'select', 'onchangeFn':'M.ciniki_poma_main.menu.switchDate', 
                    'complex_options':{'name':'display_name', 'value':'id'}, 'options':{},
                    },
            }},
        'customer_details':{'label':'Customer', 'aside':'yes', 'type':'simplegrid', 'num_cols':2,
            'visible':function() { return (M.ciniki_poma_main.menu.sections._tabs.selected == 'checkout' && M.ciniki_poma_main.menu.customer_id > 0) ? 'yes':'no'; },
            'cellClasses':['label',''],
            'addTxt':'Edit',
            'addFn':'M.startApp(\'ciniki.customers.edit\',null,\'M.ciniki_poma_main.menu.open();\',\'mc\',{\'customer_id\':M.ciniki_poma_main.menu.customer_id});',
            },
//        'customer_details':{'label':'Customer', 'type':'simplegrid', 'num_cols':1, 'aside':'yes', 
//            'visible':function() { return (M.ciniki_poma_main.menu.sections._tabs.selected == 'checkout' && M.ciniki_poma_main.menu.customer_id > 0 ) ? 'yes':'no'; },
//            },
        'open_orders':{'label':'Open Orders', 'type':'simplegrid', 'num_cols':1, 'aside':'yes',
            'visible':function() { return (M.ciniki_poma_main.menu.sections._tabs.selected == 'checkout') ? 'yes':'no'; },
            'noData':'No open orders',
            'addTxt':'Add',
            'addFn':'M.startApp(\'ciniki.customers.edit\',null,\'M.ciniki_poma_main.menu.open();\',\'mc\',{\'next\':\'M.ciniki_poma_main.menu.newOrder\',\'customer_id\':0});',
            },
        'closed_orders':{'label':'Closed Orders', 'type':'simplegrid', 'num_cols':1, 'aside':'yes',
            'visible':function() { return (M.ciniki_poma_main.menu.sections._tabs.selected == 'checkout') ? 'yes':'no'; },
            'noData':'No closed orders',
            },
        'orderitems':{'label':'Items', 'type':'simplegrid', 'num_cols':4,
            'visible':function() { return (M.ciniki_poma_main.menu.sections._tabs.selected == 'checkout' && M.ciniki_poma_main.menu.order_id > 0 ) ? 'yes':'no'; },
            'headerValues':['', 'Item', 'Price', 'Total'],
            'headerClasses':['', '', 'alignright', 'alignright'],
            'cellClasses':['alignright', 'multiline', 'multiline alignright', 'multiline alignright'],
            'addTxt':'Add',
            'addFn':'M.ciniki_poma_main.orderitem.open(\'M.ciniki_poma_main.menu.open();\',0,M.ciniki_poma_main.menu.order_id,[]);',
            },
        'tallies':{'label':'', 'type':'simplegrid', 'num_cols':2,
            'visible':function() { return (M.ciniki_poma_main.menu.sections._tabs.selected == 'checkout' && M.ciniki_poma_main.menu.order_id > 0 ) ? 'yes':'no'; },
            'cellClasses':['alignright', 'alignright'],
            },
        'customers':{'label':'Customers', 'type':'simplegrid', 'num_cols':1, 'aside':'yes',
            'visible':function() { var t=M.ciniki_poma_main.menu.sections._tabs.selected; return (t=='repeats'||t=='queue') ? 'yes':'no'; },
            'noData':'No customers.',
            },
        'suppliers':{'label':'Suppliers', 'type':'simplegrid', 'num_cols':1, 'aside':'yes',
            'visible':function() { return (M.ciniki_poma_main.menu.sections._tabs.selected == 'queue') ? 'yes':'no'; },
            'noData':'No queued items.',
            },
        'repeat_items':{'label':'Standing Order Items', 'type':'simplegrid', 'num_cols':3,
            'visible':function() { return (M.ciniki_poma_main.menu.sections._tabs.selected == 'repeats') ? 'yes':'no'; },
            },
        'queue_items':{'label':'Queued Items', 'type':'simplegrid', 'num_cols':3,
            'visible':function() { return (M.ciniki_poma_main.menu.sections._tabs.selected == 'queue') ? 'yes':'no'; },
            },
        'favourite_items':{'label':'Favourites', 'type':'simplegrid', 'num_cols':2,
            'visible':function() { return (M.ciniki_poma_main.menu.sections._tabs.selected == 'favourites' && M.ciniki_poma_main.menu.customer_id == 0 ) ? 'yes':'no'; },
            'headerValues':['Item', '# Customers'],
            'noData':'No favourites',
            },
        'customer_favourites':{'label':'Favourites', 'type':'simplegrid', 'num_cols':2,
            'visible':function() { return (M.ciniki_poma_main.menu.sections._tabs.selected == 'favourites' && M.ciniki_poma_main.menu.customer_id > 0 ) ? 'yes':'no'; },
            'headerValues':['Item', '# Orders'],
            'sortable':'yes', 'sortTypes':['text', 'number'],
            'noData':'No favourites for customer',
            },
        'dates':{'label':'Order Date', 'type':'simplegrid', 'num_cols':3,
            'visible':function() { return (M.ciniki_poma_main.menu.sections._tabs.selected == 'dates') ? 'yes':'no'; },
            'headerValues':['Status', 'Date', '# Orders'],
            'noData':'No order dates have been setup.',
            'addTxt':'Add Order Date',
            'addFn':'M.ciniki_poma_main.editdate.open(\'M.ciniki_poma_main.menu.open();\',0,null);'
            },
        '_orderbuttons':{'label':'', 
            'visible':function() { return (M.ciniki_poma_main.menu.sections._tabs.selected == 'checkout' && M.ciniki_poma_main.menu.order_id > 0 ) ? 'yes':'no'; },
            'buttons':{
                'delete':{'label':'Delete Order', 'visible':function() { return (M.ciniki_poma_main.menu.data.orderitems != null && M.ciniki_poma_main.menu.data.orderitems.length == 0 ? 'yes' : 'no');},
                    'fn':'M.ciniki_poma_main.menu.orderRemove()',
                    },
            }},
    }
    this.menu.fieldValue = function(s, i, d) {
        return this.date_id;
    }
    this.menu.liveSearchCb = function(s, i, v) {
        if( s == 'search' && v != '' ) {
            M.api.getJSONBgCb('ciniki.poma.dateSearch', {'business_id':M.curBusinessID, 'start_needle':v, 'limit':'25'}, function(rsp) {
                M.ciniki_poma_main.menu.liveSearchShow('search',null,M.gE(M.ciniki_poma_main.menu.panelUID + '_' + s), rsp.dates);
                });
        }
    }
    this.menu.liveSearchResultValue = function(s, f, i, j, d) {
        return d.name;
    }
    this.menu.liveSearchResultRowFn = function(s, f, i, j, d) {
        return 'M.ciniki_poma_main.date.open(\'M.ciniki_poma_main.menu.open();\',\'' + d.id + '\');';
    }
    this.menu.rowStyle = function(s, i, d) {
/*        if( (s == 'open_orders' || s == 'closed_orders') && this.order_id == d.id ) {
            return 'background: #ffa;';
        }
        if( s == 'customers' && this.customer_id == d.id ) {
            return 'background: #ffa;';
        } */
        return '';
    }
    this.menu.rowClass = function(s, i, d) {
        if( (s == 'open_orders' || s == 'closed_orders') && this.order_id == d.id ) {
            return 'highlight';
        }
        if( s == 'customers' && this.customer_id == d.id ) {
            return 'highlight';
        }
    }
    this.menu.cellValue = function(s, i, j, d) {
        if( s == 'open_orders' ) { return d.billing_name; }
        if( s == 'closed_orders' ) { return d.billing_name; }
        if( s == 'orderitems' ) {
            switch(j) {
                case 0: return '<span class="subdue">' + (parseInt(i) + 1) + '</span>';
                case 1: 
                    if( d.notes != '' ) {
                        return '<span class="maintext">' + d.description + '</span><span class="subtext">' + d.notes + '</span>';
                    }
                    return d.description;
                case 2:
                    if( d.notes != '' ) {
                        return '<span class="maintext">' + d.price_text + '</span><span class="subtext">' + d.discount_text + '</span>';
                    }
                    return d.price_text;
                case 3: 
                    if( d.taxtype_name != null && d.taxtype_name != '' ) {
                        return '<span class="maintext">' + d.total_text + '</span><span class="subtext">' + d.taxtype_name + '</span>';
                    }
                    return d.total_text;
            }
        }
        if( s == 'tallies' ) {
            switch(j) {
                case 0: return d.label;
                case 1: return d.value;
            }
        }
        if( s == 'customers' ) { 
            if( d.num_items != null && d.num_items != '' ) {
                return d.display_name + ' <span class="count">' + d.num_items + '</span>';
            }
            return d.display_name; 
        }
        if( s == 'customer_details' ) {
            switch (j) {
                case 0: return d.detail.label;
                case 1: return (d.detail.label == 'Email'?M.linkEmail(d.detail.value):d.detail.value);
            }
        }
        if( s == 'dates' ) {
            switch(j) {
                case 0: return d.status_text;
                case 1: return d.display_name;
                case 2: return d.num_orders;
            }
        }
        if( s == 'favourite_items' ) {
            switch(j) {
                case 0: return d.description;
                case 1: return d.num_customers;
            }
        }
        if( s == 'customer_favourites' ) {
            switch(j) {
                case 0: return d.description;
                case 1: return d.num_orders;
            }
        }
    }
    this.menu.rowFn = function(s, i, d) {
        if( s == 'dates' ) {
            return 'M.ciniki_poma_main.editdate.open(\'M.ciniki_poma_main.menu.open();\',\'' + d.id + '\',M.ciniki_poma_main.menu.date_nplist);';
        } else if( s == 'open_orders' || s == 'closed_orders' ) {
            return 'M.ciniki_poma_main.menu.openOrder(\'' + d.id + '\');';
        } else if( s == 'orderitems' ) {
            return 'M.ciniki_poma_main.orderitem.open(\'M.ciniki_poma_main.menu.open();\',\'' + d.id + '\',null,M.ciniki_poma_main.menu.nplists.orderitems);';
        } else if( s == 'customers' ) {
            return 'M.ciniki_poma_main.menu.openFavourites(\'' + d.id + '\');';
        }
        return '';
    }
    this.menu.switchDate = function(s, i) {
        this.date_id = this.formValue(i);
        this.order_id = 0;
        this.open();
    }
    this.menu.openOrder = function(oid) {
        this.order_id = oid;
        this.open();
    }
/*    this.menu.openFavourites = function(cid) {
        this.customer_id = cid; 
        this.customer_name = '';
        if( M.ciniki_poma_main.menu.data.customers != null ) {
            for(var i in M.ciniki_poma_main.menu.data.customers) {
                if( M.ciniki_poma_main.menu.data.customers[i].id == this.customer_id ) {
                    this.customer_name = M.ciniki_poma_main.menu.data.customers[i].display_name;
                }
            }
        }
        this.open();
    } */
    this.menu.openCheckout = function(rsp) {
        if( rsp.stat != 'ok' ) {
            M.api.err(rsp);
            return false;
        }
        var p = M.ciniki_poma_main.menu;
        p.size = 'large narrowaside';
        p.data = rsp;
        p.nplists = [];
        if( rsp.nplists != null ) {
            p.nplists = rsp.nplists;
        }
        p.sections._dates.fields.date_id.options = rsp.dates;
        if( rsp.date_id != null && rsp.date_id > 0 ) {
            p.date_id = rsp.date_id;
        }
        if( rsp.order != null && rsp.order.customer_id > 0 ) {
            p.order_id = rsp.order.id;
            p.customer_id = rsp.order.customer_id;
        }
        p.order_nplist = (rsp.order_nplist != null ? rsp.order_nplist : null);
        p.refresh();
        p.show();
    }
    this.menu.newOrder = function(cid) {
        this.customer_id = cid;
        M.api.getJSONCb('ciniki.poma.dateCheckout', 
            {'business_id':M.curBusinessID, 'date_id':this.date_id, 'order_id':0, 'order':'new', 'customer_id':this.customer_id}, 
            M.ciniki_poma_main.menu.openCheckout);
    }
    this.menu.open = function(cb, tab) {
        if( tab != null ) { this.sections._tabs.selected = tab; }
        if( this.sections._tabs.selected == 'checkout' ) {
            if( cb != null ) {
                this.cb = cb;
            }
            M.api.getJSONCb('ciniki.poma.dateCheckout', 
                {'business_id':M.curBusinessID, 'date_id':this.date_id, 'order_id':this.order_id, 'customer_id':this.customer_id}, 
                M.ciniki_poma_main.menu.openCheckout);
        }
        else if( this.sections._tabs.selected == 'repeats' ) {
            M.api.getJSONCb('ciniki.poma.repeatList', {'business_id':M.curBusinessID}, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                var p = M.ciniki_poma_main.menu;
                p.size = 'medium';
                p.data = rsp;
                p.standing_nplist = (rsp.standing_nplist != null ? rsp.standing_nplist : null);
                p.refresh();
                p.show(cb);
            });
        }
        else if( this.sections._tabs.selected == 'queue' ) {
            M.api.getJSONCb('ciniki.poma.queueList', {'business_id':M.curBusinessID}, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                var p = M.ciniki_poma_main.menu;
                p.size = 'large narrowaside';
                p.data = rsp;
                p.queue_nplist = (rsp.queue_nplist != null ? rsp.queue_nplist : null);
                p.refresh();
                p.show(cb);
            });
        }
        else if( this.sections._tabs.selected == 'dates' ) {
            M.api.getJSONCb('ciniki.poma.dateList', {'business_id':M.curBusinessID}, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                var p = M.ciniki_poma_main.menu;
                p.size = 'medium';
                p.data = rsp;
                p.date_nplist = (rsp.date_nplist != null ? rsp.date_nplist : null);
                p.refresh();
                p.show(cb);
            });
        }
/*        else if( this.sections._tabs.selected == 'favourites' ) {
            M.api.getJSONCb('ciniki.poma.favouriteList', {'business_id':M.curBusinessID, 'customers':'yes', 'customer_id':this.customer_id}, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                var p = M.ciniki_poma_main.menu;
                p.size = 'medium narrowaside';
                p.data = rsp;
                p.data.customers.unshift({'id':'0', 'display_name':'All Customers'});
                if( p.customer_id > 0 ) {
                    p.sections.customer_favourites.label = p.customer_name;
                } else {
                    p.sections.customer_favourites.label = 'Favourites';
                }
                p.refresh();
                p.show(cb);
            });
        } */
    }
    this.menu.orderRemove = function() {
        if( confirm('Are you sure you want to remove order?') ) {
            M.api.getJSONCb('ciniki.poma.orderDelete', {'business_id':M.curBusinessID, 'order_id':this.order_id}, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                var p = M.ciniki_poma_main.menu;
                p.order_id = 0;
                p.customer_id = 0;
                p.open();
            });
        }
    }
    this.menu.addClose('Back');

    //
    // The panel to edit Order Date
    //
    this.editdate = new M.panel('Order Date', 'ciniki_poma_main', 'editdate', 'mc', 'medium', 'sectioned', 'ciniki.poma.main.editdate');
    this.editdate.data = null;
    this.editdate.date_id = 0;
    this.editdate.nplist = [];
    this.editdate.sections = {
        'general':{'label':'', 'fields':{
            'order_date':{'label':'Date', 'required':'yes', 'type':'date'},
            'status':{'label':'Status', 'type':'toggle', 'toggles':{'10':'Open', '30':'Substitutions', '50':'Locked', '90':'Closed'}},
            'flags1':{'label':'Autolock', 'type':'flagtoggle', 'field':'flags', 'bit':0x01, 'on_fields':['autolock_date', 'autolock_time']},
            'autolock_date':{'label':'Auto Lock Date', 'visible':'no', 'type':'date'},
            'autolock_time':{'label':'Auto Lock Time', 'visible':'no', 'type':'text', 'size':'small'},
            }},
        '_notices':{'label':'Notices', 'fields':{
            'notices':{'label':'', 'hidelabel':'yes', 'type':'textarea'},
            }},
        '_buttons':{'label':'', 'buttons':{
            'save':{'label':'Save', 'fn':'M.ciniki_poma_main.editdate.save();'},
            'delete':{'label':'Delete', 
                'visible':function() {return M.ciniki_poma_main.editdate.date_id > 0 ? 'yes' : 'no'; },
                'fn':'M.ciniki_poma_main.editdate.remove();'},
            }},
        };
    this.editdate.fieldValue = function(s, i, d) { return this.data[i]; }
    this.editdate.fieldHistoryArgs = function(s, i) {
        return {'method':'ciniki.poma.dateHistory', 'args':{'business_id':M.curBusinessID, 'date_id':this.date_id, 'field':i}};
    }
    this.editdate.open = function(cb, did, list) {
        if( did != null ) { this.date_id = did; }
        if( list != null ) { this.nplist = list; }
        M.api.getJSONCb('ciniki.poma.dateGet', {'business_id':M.curBusinessID, 'date_id':this.date_id}, function(rsp) {
            if( rsp.stat != 'ok' ) {
                M.api.err(rsp);
                return false;
            }
            var p = M.ciniki_poma_main.editdate;
            p.data = rsp.date;
            if( (rsp.date.flags&0x01) == 0x01 ) {
                p.sections.general.fields.autolock_date.visible = 'yes';
                p.sections.general.fields.autolock_time.visible = 'yes';
            } else {
                p.sections.general.fields.autolock_date.visible = 'no';
                p.sections.general.fields.autolock_time.visible = 'no';
            }
            p.refresh();
            p.show(cb);
        });
    }
    this.editdate.save = function(cb) {
        if( cb == null ) { cb = 'M.ciniki_poma_main.editdate.close();'; }
        if( !this.checkForm() ) { return false; }
        if( this.date_id > 0 ) {
            var c = this.serializeForm('no');
            if( c != '' ) {
                M.api.postJSONCb('ciniki.poma.dateUpdate', {'business_id':M.curBusinessID, 'date_id':this.date_id}, c, function(rsp) {
                    if( rsp.stat != 'ok' ) {
                        M.api.err(rsp);
                        return false;
                    }
                    eval(cb);
                });
            } else {
                eval(cb);
            }
        } else {
            var c = this.serializeForm('yes');
            M.api.postJSONCb('ciniki.poma.dateAdd', {'business_id':M.curBusinessID}, c, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                M.ciniki_poma_main.editdate.date_id = rsp.id;
                eval(cb);
            });
        }
    }
    this.editdate.remove = function() {
        if( confirm('Are you sure you want to remove order date?') ) {
            M.api.getJSONCb('ciniki.poma.dateDelete', {'business_id':M.curBusinessID, 'date_id':this.date_id}, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                M.ciniki_poma_main.editdate.close();
            });
        }
    }
    this.editdate.nextButtonFn = function() {
        if( this.nplist != null && this.nplist.indexOf('' + this.date_id) < (this.nplist.length - 1) ) {
            return 'M.ciniki_poma_main.editdate.save(\'M.ciniki_poma_main.editdate.open(null,' + this.nplist[this.nplist.indexOf('' + this.date_id) + 1] + ');\');';
        }
        return null;
    }
    this.editdate.prevButtonFn = function() {
        if( this.nplist != null && this.nplist.indexOf('' + this.date_id) > 0 ) {
            return 'M.ciniki_poma_main.editdate.save(\'M.ciniki_poma_main.date_id.open(null,' + this.nplist[this.nplist.indexOf('' + this.date_id) - 1] + ');\');';
        }
        return null;
    }
    this.editdate.addButton('save', 'Save', 'M.ciniki_poma_main.editdate.save();');
    this.editdate.addClose('Cancel');
    this.editdate.addButton('next', 'Next');
    this.editdate.addLeftButton('prev', 'Prev');

    //
    // The panel to edit Order Item
    //
    this.orderitem = new M.panel('Order Item', 'ciniki_poma_main', 'orderitem', 'mc', 'medium', 'sectioned', 'ciniki.poma.main.orderitem');
    this.orderitem.data = null;
    this.orderitem.item_id = 0;
    this.orderitem.order_id = 0;
    this.orderitem.nplist = [];
    this.orderitem.sections = {
        'general':{'label':'', 'fields':{
//            'flags':{'label':'Options', 'type':'text'},
//            'object':{'label':'Object', 'type':'text'},
//            'object_id':{'label':'Object ID', 'type':'text'},
//            'code':{'label':'Code', 'type':'text'},
            'description':{'label':'Item', 'required':'yes', 'type':'text', 'livesearch':'yes', 'livesearchcols':2},
            'itype':{'label':'Sold By', 'required':'yes', 'type':'toggle', 
                'toggles':{'10':'Weight', '20':'Weighted Units', '30':'Units'}, 
                'onchange':'M.ciniki_poma_main.orderitem.updateForm', 
                },
            'unit_quantity':{'label':'Unit Quantity', 'visible':'no', 'type':'text', 'size':'small'},
            'unit_suffix':{'label':'Unit Suffix', 'visible':'no', 'type':'text', 'size':'small'},
            'weight_quantity':{'label':'Weight', 'visible':'no', 'type':'text', 'size':'small'},
            'weight_units':{'label':'Weight Units', 'visible':'no', 'type':'toggle', 'toggles':{'20':'lb', '25':'oz', '60':'kg', '65':'g'}},
            'packing_order':{'label':'Packing', 'type':'toggle', 'toggles':{'10':'Top', '50':'Middle', '90':'Bottom'}},
            'unit_amount':{'label':'Amount', 'required':'yes', 'type':'text', 'size':'small'},
            'unit_discount_amount':{'label':'Discount Amount', 'type':'text', 'size':'small'},
            'unit_discount_percentage':{'label':'Discount Percentage', 'type':'text', 'size':'small'},
//            'taxtype_id':{'label':'Tax Type', 'type':'text'},
            }},
        '_notes':{'label':'Notes', 'fields':{
            'notes':{'label':'', 'hidelabel':'yes', 'type':'textarea', 'size':'small'},
            }},
        '_buttons':{'label':'', 'buttons':{
            'save':{'label':'Save', 'fn':'M.ciniki_poma_main.orderitem.save();'},
            'delete':{'label':'Delete', 
                'visible':function() {return M.ciniki_poma_main.orderitem.item_id > 0 ? 'yes' : 'no'; },
                'fn':'M.ciniki_poma_main.orderitem.remove();'},
            }},
    }
    this.orderitem.liveSearchCb = function(s, i, v) {
        M.api.getJSONBgCb('ciniki.poma.orderItemSearch', {'business_id':M.curBusinessID,
            'field':i, 'order_id':M.ciniki_poma_main.orderitem.order_id, 'start_needle':v, 'limit':25}, function(rsp) {
            M.ciniki_poma_main.orderitem.liveSearchShow(s,i,M.gE(M.ciniki_poma_main.orderitem.panelUID + '_' + i), rsp.items);
           });
    }
    this.orderitem.liveSearchResultClass = function(s, f, i, j, d) {
        return 'multiline';
    }
    this.orderitem.liveSearchResultValue = function(s,f,i,j,d) {
        switch(j) {
            case 0: 
                if( d.notes != null && d.notes != '' ) {
                    return '<span class="maintext">' + d.description + '</span><span class="subtext">' + d.notes + '</span>';
                }
                return d.description;
            case 1:
                if( d.discount_text != null && d.discount_text != '' ) {
                    return '<span class="maintext">' + d.unit_amount_text + '</span><span class="subtext">' + d.discount_text + '</span>';
                }
                return d.unit_price_text;
            case 2: 
                if( d.taxtype_name != null && d.taxtype_name != '' ) {
                    return '<span class="maintext">' + d.total_text + '</span><span class="subtext">' + d.taxtype_name + '</span>';
                }
                return d.total_text;
        }
        return '';
    }
    this.orderitem.liveSearchResultRowFn = function(s, f, i, j, d) {
        return 'M.ciniki_poma_main.orderitem.updateFromSearch(\'' + s + '\',\'' + f + '\',\'' + d.object + '\',\'' + d.object_id + '\',\'' + escape(d.description) + '\',\'' + d.itype + '\',\'' + d.weight_units + '\',\'' + d.weight_quantity + '\',\'' + d.unit_quantity + '\',\'' + escape(d.unit_suffix) + '\',\'' + d.packing_order + '\',\'' + d.unit_amount_text + '\',\'' + d.unit_discount_amount + '\',\'' + d.unit_discount_percentage + '\',\'' + d.taxtype_id + '\',\'' + escape(d.notes) + '\');';
    }
    this.orderitem.updateFromSearch = function(s,f,o,oid,d,i,wu,wq,uq,us,po,ua,da,dp,t,n) {
        this.object = o;
        this.object_id = oid;
        this.setFieldValue('itype', i);
        this.updateForm();
        this.setFieldValue('description', unescape(d));
        this.setFieldValue('weight_units', wu);
        this.setFieldValue('weight_quantity', wq);
        this.setFieldValue('unit_quantity', uq);
        this.setFieldValue('unit_suffix', unescape(us));
        this.setFieldValue('packing_order', po);
        this.setFieldValue('unit_amount', ua);
        this.setFieldValue('unit_discount_amount', da);
        this.setFieldValue('unit_discount_percentage', dp);
        if( M.curBusiness.modules['ciniki.taxes'] != null ) {
            this.setFieldValue('taxtype_id', t);
        }
        this.setFieldValue('notes', unescape(n));
        this.removeLiveSearch(s, f);
        if( i < 30 ) {
            M.gE(this.panelUID + '_weight_quantity').focus();
        } else {
            M.gE(this.panelUID + '_unit_quantity').focus();
        }
    }
    this.orderitem.fieldValue = function(s, i, d) { return this.data[i]; }
    this.orderitem.fieldHistoryArgs = function(s, i) {
        return {'method':'ciniki.poma.orderItemHistory', 'args':{'business_id':M.curBusinessID, 'item_id':this.item_id, 'field':i}};
    }
    this.orderitem.updateForm = function() {
        var v = this.formValue('itype');
        if( v == '10' ) {
            this.sections.general.fields.weight_quantity.visible = 'yes';
            this.sections.general.fields.weight_units.visible = 'yes';
            this.sections.general.fields.unit_quantity.visible = 'no';
            this.sections.general.fields.unit_suffix.visible = 'no';
        } else if( v == '20' ) {
            this.sections.general.fields.weight_quantity.visible = 'yes';
            this.sections.general.fields.weight_units.visible = 'yes';
            this.sections.general.fields.unit_quantity.visible = 'yes';
            this.sections.general.fields.unit_suffix.visible = 'no';
        } else if( v == '30' ) {
            this.sections.general.fields.weight_quantity.visible = 'no';
            this.sections.general.fields.weight_units.visible = 'no';
            this.sections.general.fields.unit_quantity.visible = 'yes';
            this.sections.general.fields.unit_suffix.visible = 'yes';
        }
        this.refreshFormField('general', 'weight_quantity');
        this.refreshFormField('general', 'weight_units');
        this.refreshFormField('general', 'unit_quantity');
        this.refreshFormField('general', 'unit_suffix');
    }
    this.orderitem.open = function(cb, iid, oid, list) {
        if( iid != null ) { this.item_id = iid; }
        if( list != null ) { this.nplist = list; }
        if( oid != null ) { this.order_id = oid; }
        M.api.getJSONCb('ciniki.poma.orderItemGet', {'business_id':M.curBusinessID, 'item_id':this.item_id}, function(rsp) {
            if( rsp.stat != 'ok' ) {
                M.api.err(rsp);
                return false;
            }
            var p = M.ciniki_poma_main.orderitem;
            p.data = rsp.item;
            p.refresh();
            p.show(cb);
            p.updateForm();
        });
    }
    this.orderitem.save = function(cb) {
        if( cb == null ) { cb = 'M.ciniki_poma_main.orderitem.close();'; }
        if( !this.checkForm() ) { return false; }
        if( this.item_id > 0 ) {
            var c = this.serializeForm('no');
            if( c != '' ) {
                M.api.postJSONCb('ciniki.poma.orderItemUpdate', {'business_id':M.curBusinessID, 'item_id':this.item_id}, c, function(rsp) {
                    if( rsp.stat != 'ok' ) {
                        M.api.err(rsp);
                        return false;
                    }
                    eval(cb);
                });
            } else {
                eval(cb);
            }
        } else {
            var c = this.serializeForm('yes');
            M.api.postJSONCb('ciniki.poma.orderItemAdd', {'business_id':M.curBusinessID, 'order_id':this.order_id}, c, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                M.ciniki_poma_main.orderitem.item_id = rsp.id;
                eval(cb);
            });
        }
    }
    this.orderitem.remove = function() {
        if( confirm('Are you sure you want to remove orderitem?') ) {
            M.api.getJSONCb('ciniki.poma.orderItemDelete', {'business_id':M.curBusinessID, 'item_id':this.item_id}, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                M.ciniki_poma_main.orderitem.close();
            });
        }
    }
    this.orderitem.nextButtonFn = function() {
        if( this.nplist != null && this.nplist.indexOf('' + this.item_id) < (this.nplist.length - 1) ) {
            return 'M.ciniki_poma_main.orderitem.save(\'M.ciniki_poma_main.orderitem.open(null,' + this.nplist[this.nplist.indexOf('' + this.item_id) + 1] + ');\');';
        }
        return null;
    }
    this.orderitem.prevButtonFn = function() {
        if( this.nplist != null && this.nplist.indexOf('' + this.item_id) > 0 ) {
            return 'M.ciniki_poma_main.orderitem.save(\'M.ciniki_poma_main.orderitem.open(null,' + this.nplist[this.nplist.indexOf('' + this.item_id) - 1] + ');\');';
        }
        return null;
    }
    this.orderitem.addButton('save', 'Save', 'M.ciniki_poma_main.orderitem.save();');
    this.orderitem.addClose('Cancel');
    this.orderitem.addButton('next', 'Next');
    this.orderitem.addLeftButton('prev', 'Prev');

    //
    // Start the app
    // cb - The callback to run when the user leaves the main panel in the app.
    // ap - The application prefix.
    // ag - The app arguments.
    //
    this.start = function(cb, ap, ag) {
        args = {};
        if( ag != null ) {
            args = eval(ag);
        }
        
        //
        // Create the app container
        //
        var ac = M.createContainer(ap, 'ciniki_poma_main', 'yes');
        if( ac == null ) {
            alert('App Error');
            return false;
        }
        
        this.menu.open(cb);
    }
}
