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
    this.menu.account_nplist = [];
    this.menu.date_nplist = [];
    this.menu.liveSearchRN = 0;
    this.menu.sections = {
        '_tabs':{'label':'', 'type':'menutabs', 'selected':'accounts', 'tabs':{
            'accounts':{'label':'Accounts', 'fn':'M.ciniki_poma_main.menu.open(null,"dates");'},
            'dates':{'label':'Dates', 'fn':'M.ciniki_poma_main.menu.open(null,"dates");'},
            'taxes':{'label':'Taxes', 'fn':'M.ciniki_poma_main.menu.open(null,"taxes");'},
//            'history':{'label':'History', 'fn':'M.ciniki_poma_main.menu.open(null,"history");'},
            }},
//        'account_search':{'label':'', 'type':''},
        'account_search':{'label':'', 'type':'livesearchgrid', 'livesearchcols':1, 'hint':'Search',
            'noData':'No accounts found.',
            },
        'accounts':{'label':'Accounts', 'type':'simplegrid', 'num_cols':1,
            'visible':function() { return (M.ciniki_poma_main.menu.sections._tabs.selected == 'accounts') ? 'yes':'no'; },
            'noData':'No accounts have been setup.',
            },
        'dates':{'label':'Order Date', 'type':'simplegrid', 'num_cols':3,
            'visible':function() { return (M.ciniki_poma_main.menu.sections._tabs.selected == 'dates') ? 'yes':'no'; },
            'headerValues':['Status', 'Date', '# Orders'],
            'noData':'No order dates have been setup.',
            'addTxt':'Add Order Date',
            'addFn':'M.ciniki_poma_main.editdate.open(\'M.ciniki_poma_main.menu.open();\',0,null);'
            },
        'tax_quarters':{'label':'', 'type':'simplegrid', 'num_cols':2,
            'visible':function() { return (M.ciniki_poma_main.menu.sections._tabs.selected == 'taxes') ? 'yes':'no'; },
            'noData':'No taxes found',
            },
    }
    this.menu.liveSearchCb = function(s, i, v) {
        this.liveSearchRN++;
        var sN = this.liveSearchRN;
        if( s == 'account_search' && v != '' ) {
            M.api.getJSONBgCb('ciniki.poma.accountSearch', {'tnid':M.curTenantID, 'search_str':v, 'limit':'50'}, function(rsp) {
                if( sN == M.ciniki_poma_main.menu.liveSearchRN ) {
                    M.ciniki_poma_main.menu.liveSearchShow('account_search',null,M.gE(M.ciniki_poma_main.menu.panelUID + '_' + s), rsp.accounts);
                }
            });
        }
    }
    this.menu.liveSearchResultValue = function(s, f, i, j, d) {
        if( s == 'account_search' ) { 
            switch(j) {
                case 0: return d.display_name;
            }
        }
    }
    this.menu.liveSearchResultRowFn = function(s, f, i, j, d) {
        if( s == 'account_search' ) { 
            return 'M.ciniki_poma_main.account.open(\'M.ciniki_poma_main.menu.open();\',\'' + d.customer_id + '\',0,[]);';
        }
    }
    this.menu.fieldValue = function(s, i, d) {
        return this.date_id;
    }
    this.menu.headerValue = function(s, i, d) {
        if( s == 'tax_quarters' ) {
            if( i == 0 ) { return 'Quarter'; }
            if( i > 1 && i == (this.sections.tax_quarters.num_cols - 1) ) { return 'Total'; }
            return this.data.taxrates[(i-1)].name;
        }
    };
    this.menu.cellValue = function(s, i, j, d) {
        if( s == 'accounts' ) {
            switch(j) {
                case 0: return d.display_name;
            }
        }
        if( s == 'dates' ) {
            switch(j) {
                case 0: return d.status_text;
                case 1: return d.display_name;
                case 2: return d.num_orders;
            }
        }
        if( s == 'tax_quarters' ) {
            if( j == 0 ) { return d.start_date + ' - ' + d.end_date; }
            if( j > 1 && j == (this.sections.tax_quarters.num_cols - 1) ) { return d.total_amount_display; }
            return d.taxrates[this.data.taxrates[(j-1)].id].amount_display;
        }
    }
    this.menu.rowFn = function(s, i, d) {
        if( s == 'accounts' ) {
            return 'M.ciniki_poma_main.account.open(\'M.ciniki_poma_main.menu.open();\',\'' + d.customer_id + '\',0,M.ciniki_poma_main.menu.account_nplist);';
        }
        if( s == 'dates' ) {
            return 'M.ciniki_poma_main.editdate.open(\'M.ciniki_poma_main.menu.open();\',\'' + d.id + '\',M.ciniki_poma_main.menu.date_nplist);';
        }
        return '';
    }
    this.menu.open = function(cb, tab) {
        if( tab != null ) { this.sections._tabs.selected = tab; }
        if( this.sections._tabs.selected == 'accounts' ) {
            M.api.getJSONCb('ciniki.poma.accountList', {'tnid':M.curTenantID}, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                var p = M.ciniki_poma_main.menu;
                p.size = 'medium';
                p.data = rsp;
                p.account_nplist = (rsp.nplist != null ? rsp.nplist : null);
                p.refresh();
                p.show(cb);
            });
        }
        else if( this.sections._tabs.selected == 'dates' ) {
            M.api.getJSONCb('ciniki.poma.dateList', {'tnid':M.curTenantID}, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                var p = M.ciniki_poma_main.menu;
                p.size = 'medium';
                p.data = rsp;
                p.date_nplist = (rsp.nplist != null ? rsp.nplist : null);
                p.refresh();
                p.show(cb);
            });
        }
        else if( this.sections._tabs.selected == 'taxes' ) {
            M.api.getJSONCb('ciniki.poma.reportOrderTaxes', {'tnid':M.curTenantID}, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                var p = M.ciniki_poma_main.menu;
                p.size = 'large';
                p.data = rsp;
                if( p.data.taxrates.length > 1 ) {
                    p.sections.tax_quarters.num_cols = p.data.taxrates.length + 2;
                } else {
                    p.sections.tax_quarters.num_cols = 2;
                }
                p.date_nplist = (rsp.date_nplist != null ? rsp.date_nplist : null);
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
        '_repeats':{'label':'Apply repeats on', 'fields':{
            'repeats_date':{'label':'Date', 'type':'date'},
            'repeats_time':{'label':'Time', 'type':'text', 'size':'small'},
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
        return {'method':'ciniki.poma.dateHistory', 'args':{'tnid':M.curTenantID, 'date_id':this.date_id, 'field':i}};
    }
    this.editdate.open = function(cb, did, list) {
        if( did != null ) { this.date_id = did; }
        if( list != null ) { this.nplist = list; }
        M.api.getJSONCb('ciniki.poma.dateGet', {'tnid':M.curTenantID, 'date_id':this.date_id}, function(rsp) {
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
                M.api.postJSONCb('ciniki.poma.dateUpdate', {'tnid':M.curTenantID, 'date_id':this.date_id}, c, function(rsp) {
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
            M.api.postJSONCb('ciniki.poma.dateAdd', {'tnid':M.curTenantID}, c, function(rsp) {
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
            M.api.getJSONCb('ciniki.poma.dateDelete', {'tnid':M.curTenantID, 'date_id':this.date_id}, function(rsp) {
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
    // The account panel
    //
    this.account = new M.panel('Account', 'ciniki_poma_main', 'account', 'mc', 'large narrowaside', 'sectioned', 'ciniki.poma.main.account');
    this.account.data = null;
    this.account.customer_id = 0;
    this.account.order_id = 0;
    this.account.nplist = [];
    this.account.sections = {
        '_tabs':{'label':'', 'type':'menutabs', 'selected':'orders', 'tabs':{
            'orders':{'label':'Orders', 'fn':'M.ciniki_poma_main.account.switchTab("orders");'},
            'records':{'label':'Records', 'fn':'M.ciniki_poma_main.account.switchTab("records");'},
            }},
        'customer_details':{'label':'Customer', 'aside':'yes', 'type':'simplegrid', 'num_cols':1,
            'cellClasses':[''],
            'changeTxt':'Edit',
            'changeFn':'M.startApp(\'ciniki.customers.edit\',null,\'M.ciniki_poma_main.account.open();\',\'mc\',{\'customer_id\':M.ciniki_poma_main.account.customer_id});',
            },
        'orders':{'label':'Orders', 'aside':'yes', 'type':'simplegrid', 'num_cols':3,
            'visible':function() { return (M.ciniki_poma_main.account.sections._tabs.selected == 'orders') ? 'yes' : 'no'; },
            'cellClasses':['alignright', '', 'alignright'],
            },
        'order_items':{'label':'Items', 'type':'simplegrid', 'num_cols':4,
            'visible':function() { return (M.ciniki_poma_main.account.sections._tabs.selected == 'orders' && M.ciniki_poma_main.account.order_id > 0)? 'yes' : 'no'; },
            'headerValues':['', 'Item', 'Quantity/Price', 'Total'],
            'headerClasses':['', '', 'alignright', 'alignright'],
            'cellClasses':['alignright', 'multiline', 'multiline nobreak', 'multiline alignright nobreak'],
            },
        'order_tallies':{'label':'', 'type':'simplegrid', 'num_cols':2,
            'visible':function() { return (M.ciniki_poma_main.account.sections._tabs.selected == 'orders' && M.ciniki_poma_main.account.order_id > 0)? 'yes' : 'no'; },
            'cellClasses':['alignright', 'alignright'],
            },
        'order_payments':{'label':'', 'type':'simplegrid', 'num_cols':2,
            'visible':function() { return (M.ciniki_poma_main.account.sections._tabs.selected == 'orders' && M.ciniki_poma_main.account.order_id > 0)? 'yes' : 'no'; },
            'cellClasses':['alignright', 'alignright'],
            },
        'order_messages':{'label':'Messages', 'type':'simplegrid', 'num_cols':2,
            'visible':function() { return (M.ciniki_poma_main.account.sections._tabs.selected == 'orders' && M.ciniki_poma_main.account.order_id > 0)? 'yes' : 'no'; },
            'cellClasses':['multiline', 'multiline'],
            'addTxt':'Email Customer',
            'addFn':'M.ciniki_poma_main.email.open(\'M.ciniki_poma_main.account.open();\',M.ciniki_poma_main.account.order_id);',
            },
        '_buttons':{'label':'', 
            'visible':function() { return (M.ciniki_poma_main.account.sections._tabs.selected == 'orders' && M.ciniki_poma_main.account.order_id > 0)? 'yes' : 'no'; },
            'buttons':{
                'downloadpdf':{'label':'Print Invoice', 'fn':'M.ciniki_poma_main.account.printOrder();'},
                'downloadrawpdf':{'label':'Print Invoice/No Balance', 'fn':'M.ciniki_poma_main.account.printOrderNoBalance();'},
                'delete':{'label':'Delete Order', 
                    'visible':function() {return (M.ciniki_poma_main.account.data.order.total_amount == 0 && M.ciniki_poma_main.account.data.order_items.length == 0 ?'yes':'no');},
                    'fn':'M.ciniki_poma_main.account.deleteOrder();'},
            }},
        // Account records
        'records':{'label':'Invoices & Transactions', 'type':'simplegrid', 'num_cols':5,
            'visible':function() { return (M.ciniki_poma_main.account.sections._tabs.selected == 'records') ? 'yes' : 'no'; },
            'headerValues':['Date', 'Transaction', 'Debit', 'Credit', 'Balance'],
            },
    }
    this.account.cellValue = function(s, i, j, d) {
        if( s == 'customer_details' ) {
            switch(d.detail.label) {
                case 'Account': return 'Account Balance: ' + d.detail.value;
                case 'Email': return M.linkEmail(d.detail.value);
            }
            return d.detail.value;
        }
        if( s == 'orders' ) {
            switch(j) {
                case 0: return '<span class="subdue">#' + d.order_number + '</span>';
                case 1: return d.order_date;
//                case 2: return d.total_amount_display + 
                case 2: return (d.payment_status == 50 ? '<span class="subdue">Paid</span> ' : '') + d.total_amount_display;
            }
        }
        if( s == 'order_items' ) {
            switch(j) {
                case 0: return '<span class="subdue">' + (parseInt(i) + 1) + '</span>';
                case 1: 
                    if( d.notes != '' ) {
                        return '<span class="maintext">' + d.description + '</span><span class="subtext">' + d.notes + '</span>';
                    }
                    return d.description;
                case 2:
                    var q = '';
                    if( d.itype == '10' ) { 
                        q = parseFloat(d.weight_quantity);
                    } else if( d.itype == '20' ) {
                        q = '(' + parseFloat(d.quantity) + ') ';
                        if( parseFloat(d.weight_quantity) != 0 ) {
                            q += parseFloat(d.weight_quantity);
                        } else {
                            q += 'TBD';
                        }
                    } else {
                        q = parseFloat(d.quantity);
                    }
                    if( d.discount_text != '' && d.deposit_text != '' ) {
                        return ' <span class="maintext">' + q + ' @ ' + d.unit_price_text + '</span>'
                            + '<span class="subtext">' + d.discount_text + '</span>'
                            + '<span class="subtext">' + d.deposit_text + '</span>';
                    } else if( d.deposit_text != '' ) {
                        return ' <span class="maintext">' + q + ' @ ' + d.unit_price_text + '</span><span class="subtext">' + d.deposit_text + '</span>';
                    } else if( d.discount_text != '' ) {
                        return ' <span class="maintext">' + q + ' @ ' + d.unit_price_text + '</span><span class="subtext">' + d.discount_text + '</span>';
                    }
                    return q + ' @ ' + d.unit_price_text;
                case 3: 
                    if( d.taxtype_name != null && d.taxtype_name != '' ) {
                        return '<span class="maintext">' + d.total_text + '</span><span class="subtext">' + d.taxtype_name + '</span>';
                    }
                    return d.total_text;
            }
        }
        if( s == 'order_tallies' || s == 'order_payments' ) {
            switch(j) {
                case 0: return d.label;
                case 1: return d.value;
            }
        }
        if( s == 'order_messages' ) {
            switch(j) {
                case 0: return '<span class="maintext">' + d.message.status_text + '</span><span class="subtext">' + d.message.date_sent + '</span>';
                case 1: return '<span class="maintext">' + d.message.customer_email + '</span><span class="subtext">' + d.message.subject + '</span>';
            }
        }
        if( s == 'records' ) {
            switch(j) {
                case 0: return d.record_date;
                case 1: return d.transaction_name;
                case 2: return (d.amount < 0 ? d.amount_display : '');
                case 3: return (d.amount >= 0 ? d.amount_display : '');
                case 4: return d.balance_display;
            }
        }
    }
    this.account.rowClass = function(s, i, d) {
        if( s == 'orders' && this.order_id == d.id ) {
            return 'highlight';
        }
        if( s == 'records' ) {
            if( i > 1 ) {
                return 'alignright';
            }
        }
    }
    this.account.rowFn = function(s, i, d) {
        if( s == 'orders' ) {
            return 'M.ciniki_poma_main.account.open(null,\'' + d.customer_id + '\',\'' + d.id + '\');';
        }
        return '';
    }
    this.account.switchTab = function(t) {
        this.sections._tabs.selected = t;
        this.open();
    }
    this.account.open = function(cb, cid, oid) {
        if( cid != null ) { this.customer_id = cid; }
        if( oid != null ) { this.order_id = oid; }
        var args = {'tnid':M.curTenantID, 'customer_id':this.customer_id, 'order_id':this.order_id};
        if( this.sections._tabs.selected == 'orders' ) {
            args['sections'] = 'details,orders';
        } else if( this.sections._tabs.selected == 'records' ) {
            args['sections'] = 'details,records';
        }
        M.api.getJSONCb('ciniki.poma.customerAccountGet', args, function(rsp) {
            if( rsp.stat != 'ok' ) {
                M.api.err(rsp);
                return false;
            }
            var p = M.ciniki_poma_main.account;
            p.data = rsp;
            if( rsp.order != null ) {
                p.data.order_items = rsp.order.items;
                p.data.order_tallies = rsp.order.tallies;
                p.data.order_payments = rsp.order.payments;
            }
            p.refresh();
            p.show(cb);
        });
    }
    this.account.printOrder = function() {
        M.api.openPDF('ciniki.poma.invoicePDF', {'tnid':M.curTenantID, 'order_id':this.order_id});
    }
    this.account.printOrderNoBalance = function() {
        M.api.openPDF('ciniki.poma.invoicePDF', {'tnid':M.curTenantID, 'order_id':this.order_id, 'template':'rawinvoice'});
    }
    this.account.deleteOrder = function() {
        M.api.getJSONCb('ciniki.poma.orderDelete', {'tnid':M.curTenantID, 'order_id':this.order_id}, function(rsp) {
            if( rsp.stat != 'ok' ) {
                M.api.err(rsp);
                return false;
            }
            M.ciniki_poma_main.account.open(null,null,0);
        });
    }
    this.account.nextButtonFn = function() {
        if( this.nplist != null && this.nplist.indexOf('' + this.item_id) < (this.nplist.length - 1) ) {
            return 'M.ciniki_poma_main.account.open(null,' + this.nplist[this.nplist.indexOf('' + this.item_id) + 1] + ');';
        }
        return null;
    }
    this.account.prevButtonFn = function() {
        if( this.nplist != null && this.nplist.indexOf('' + this.item_id) > 0 ) {
            return 'M.ciniki_poma_main.account.open(null,' + this.nplist[this.nplist.indexOf('' + this.item_id) - 1] + ');';
        }
        return null;
    }
    this.account.addClose('Back');
    this.account.addButton('next', 'Next');
    this.account.addLeftButton('prev', 'Prev');

    //
    // The email invoice panel
    //
    this.email = new M.panel('Email Invoice', 'ciniki_poma_main', 'email', 'mc', 'medium', 'sectioned', 'ciniki.poma.main.email');
    this.email.order_id = 0;
    this.email.data = {};
    this.email.sections = {
        '_subject':{'label':'', 'fields':{
            'subject':{'label':'Subject', 'type':'text', 'history':'no'},
            }},
        '_textmsg':{'label':'Message', 'fields':{
            'textmsg':{'label':'', 'hidelabel':'yes', 'type':'textarea', 'size':'large', 'history':'no'},
            }},
        '_buttons':{'label':'', 'buttons':{
            'send':{'label':'Send', 'fn':'M.ciniki_poma_main.email.send();'},
            }},
    };
    this.email.fieldValue = function(s, i, d) {
        return this.data[i];
    };
    this.email.open = function(cb, oid) {
        if( oid != null ) { this.order_id = oid; }
        //
        // Get the email template
        //
        M.api.getJSONCb('ciniki.poma.orderEmailGet', {'tnid':M.curTenantID, 'order_id':this.order_id}, function(rsp) {
            if( rsp.stat != 'ok' ) {
                M.api.err(rsp);
                return false;
            }
            var p = M.ciniki_poma_main.email;
            p.data = rsp.email;
            p.refresh();
            p.show(cb);
            });
    };
    this.email.send = function() {
        var subject = this.formFieldValue(this.sections._subject.fields.subject, 'subject');
        var textmsg = this.formFieldValue(this.sections._textmsg.fields.textmsg, 'textmsg');
        M.api.getJSONCb('ciniki.poma.invoicePDF', {'tnid':M.curTenantID, 
            'order_id':this.order_id, 'subject':subject, 'textmsg':textmsg, 'output':'pdf', 'email':'yes'}, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                M.ciniki_poma_main.email.close();
            });
    };
    this.email.addClose('Cancel');

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
       
        if( args.customer_id != null && args.customer_id > 0 ) {    
            this.account.open(cb, args.customer_id, args.order_id);
        } else {
            this.menu.open(cb);
        }
    }
}
