//
// This is the main app for the poma module
//
function ciniki_poma_main() {
    //
    // The panel to list the orderdate
    //
    this.menu = new M.panel('Order Management', 'ciniki_poma_main', 'menu', 'mc', 'large narrowaside', 'sectioned', 'ciniki.poma.main.menu');
    this.menu.data = {};
    this.menu.date_nplist = [];
    this.menu.sections = {
        '_tabs':{'label':'', 'type':'menutabs', 'selected':'dates', 'tabs':{
            'checkout':{'label':'Checkout', 'fn':'M.ciniki_poma_main.menu.open(null,"checkout");'},
            'standing':{'label':'Standing', 'fn':'M.ciniki_poma_main.menu.open(null,"standing");'},
            'queue':{'label':'Queue', 'fn':'M.ciniki_poma_main.menu.open(null,"queue");'},
            'dates':{'label':'Dates', 'fn':'M.ciniki_poma_main.menu.open(null,"dates");'},
            }},
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
        if( s == 'dates' ) {
            switch(j) {
                case 0: return d.status_text;
                case 1: return d.display_name;
                case 2: return d.num_orders;
            }
        }
    }
    this.menu.rowFn = function(s, i, d) {
        if( s == 'dates' ) {
            return 'M.ciniki_poma_main.editdate.open(\'M.ciniki_poma_main.menu.open();\',\'' + d.id + '\',M.ciniki_poma_main.menu.date_nplist);';
        }
    }
    this.menu.open = function(cb, tab) {
        if( tab != null ) { this.sections._tabs.selected = tab; }
       
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
        else if( this.sections._tabs.selected == 'standing' ) {
            M.api.getJSONCb('ciniki.poma.standingList', {'business_id':M.curBusinessID}, function(rsp) {
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
