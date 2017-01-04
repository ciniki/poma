//
// This is the main app for the poma module
//
function ciniki_poma_main() {
    //
    // The panel to list the orderdate
    //
    this.menu = new M.panel('Order Management', 'ciniki_poma_main', 'menu', 'mc', 'large narrowaside', 'sectioned', 'ciniki.poma.main.menu');
    this.menu.data = {};
    this.menu.customer_id = 0;
    this.menu.date_nplist = [];
    this.menu.sections = {
        '_tabs':{'label':'', 'type':'menutabs', 'selected':'dates', 'tabs':{
            'checkout':{'label':'Checkout', 'fn':'M.ciniki_poma_main.menu.open(null,"checkout");'},
            'repeats':{'label':'Standing', 'fn':'M.ciniki_poma_main.menu.open(null,"repeats");'},
            'queue':{'label':'Queue', 'fn':'M.ciniki_poma_main.menu.open(null,"queue");'},
            'dates':{'label':'Dates', 'fn':'M.ciniki_poma_main.menu.open(null,"dates");'},
            'favourites':{'label':'Favourites', 'fn':'M.ciniki_poma_main.menu.open(null,"favourites");'},
            }},
        '_dates':{'label':'Change Date', 'aside':'yes',
            'visible':function() { return (M.ciniki_poma_main.menu.sections._tabs.selected == 'checkout') ? 'yes':'no'; },
            'fields':{
    //            'date_id':{'label':'', 'hidelabel':'yes', 'type':'select', 'options':{}},
            }},
        'customer_details':{'label':'Customer', 'aside':'yes', 
            'visible':function() { return (M.ciniki_poma_main.menu.sections._tabs.selected == 'checkout') ? 'yes':'no'; },
            },
        'pending_customers':{'label':'Open Orders', 'type':'simplegrid', 'num_cols':1, 'aside':'yes',
            'visible':function() { return (M.ciniki_poma_main.menu.sections._tabs.selected == 'checkout') ? 'yes':'no'; },
            },
        'delivered_customers':{'label':'Closed Orders', 'type':'simplegrid', 'num_cols':1, 'aside':'yes',
            'visible':function() { return (M.ciniki_poma_main.menu.sections._tabs.selected == 'checkout') ? 'yes':'no'; },
            },
        'order':{'label':'Order', 'type':'simplegrid', 'num_cols':1,
            'visible':function() { return (M.ciniki_poma_main.menu.sections._tabs.selected == 'checkout') ? 'yes':'no'; },
            },
        'customers':{'label':'Customers', 'type':'simplegrid', 'num_cols':1, 'aside':'yes',
            'visible':function() { var t=M.ciniki_poma_main.menu.sections._tabs.selected; return (t=='repeats'||t=='queue'||t=='favourites') ? 'yes':'no'; },
            'noData':'No customers.',
            },
        'suppliers':{'label':'Suppliers', 'type':'simplegrid', 'num_cols':1, 'aside':'yes',
            'visible':function() { return (M.ciniki_poma_main.menu.sections._tabs.selected == 'queue') ? 'yes':'no'; },
            'noData':'No queued items.',
            },
        'repeat_items':{'label':'Standing Order Items', 'type':'simplegrid', 'num_cols':3,
            'visible':function() { return (M.ciniki_poma_main.menu.sections._tabs.selected == 'checkout') ? 'yes':'no'; },
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
    this.menu.cellValue = function(s, i, j, d) {
        if( s == 'pending_customers' ) { return d.display_name; }
        if( s == 'delivered_customers' ) { return d.display_name; }
        if( s == 'customers' ) { 
            if( d.num_items != null && d.num_items != '' ) {
                return d.display_name + ' <span class="count">' + d.num_items + '</span>';
            }
            return d.display_name; 
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
        } else if( s == 'customers' ) {
            return 'M.ciniki_poma_main.menu.open(null,null,\'' + d.id + '\');';
        }
        return '';
    }
    this.menu.open = function(cb, tab, cid) {
        if( tab != null ) { this.sections._tabs.selected = tab; }
        if( cid != null ) { 
            this.customer_id = cid; 
            this.customer_name = '';
            if( M.ciniki_poma_main.menu.data.customers != null ) {
                for(var i in M.ciniki_poma_main.menu.data.customers) {
                    if( M.ciniki_poma_main.menu.data.customers[i].id == this.customer_id ) {
                        this.customer_name = M.ciniki_poma_main.menu.data.customers[i].display_name;
                    }
                }
            }
        }
       
        if( this.sections._tabs.selected == 'checkout' ) {
            M.api.getJSONCb('ciniki.poma.orderList', {'business_id':M.curBusinessID}, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                var p = M.ciniki_poma_main.menu;
                p.size = 'large narrowaside';
                p.data = rsp;
                p.order_nplist = (rsp.order_nplist != null ? rsp.order_nplist : null);
                p.refresh();
                p.show(cb);
            });
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
            M.api.getJSONCb('ciniki.poma.dateList', {'business_id':M.curBusinessID, 'upcoming':'yes'}, function(rsp) {
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
        else if( this.sections._tabs.selected == 'favourites' ) {
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
