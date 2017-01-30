//
function ciniki_poma_settings() {
    this.toggleOptions = {'no':'Hide', 'yes':'Display'};
    this.yesNoOptions = {'no':'No', 'yes':'Yes'};
    this.viewEditOptions = {'view':'View', 'edit':'Edit'};
    this.positionOptions = {'left':'Left', 'center':'Center', 'right':'Right', 'off':'Off'};
    this.weightUnits = {
        '10':'lb',
        '20':'kg',
        };

    //
    // The menu panel
    //
    this.menu = new M.panel('Settings', 'ciniki_poma_settings', 'menu', 'mc', 'narrow', 'sectioned', 'ciniki.poma.settings.menu');
    this.menu.sections = {
        'invoice':{'label':'Invoices', 'list':{
            'invoice':{'label':'Invoices', 'fn':'M.ciniki_poma_settings.invoice.open(\'M.ciniki_poma_settings.showMenu();\');'},
            }},
//        'expenses':{'label':'Expenses', 'visible':'no', 'list':{
//            'expenses':{'label':'Expense Categories', 'fn':'M.ciniki_poma_settings.showExpenseCategories(\'M.ciniki_poma_settings.showMenu();\');'},
//            }},
    };
    this.menu.addClose('Back');

    //
    // The invoice settings panel
    //
    this.invoice = new M.panel('Invoice Settings', 'ciniki_poma_settings', 'invoice', 'mc', 'medium', 'sectioned', 'ciniki.poma.settings.invoice');
    this.invoice.sections = {
        'image':{'label':'Header Image', 'fields':{
            'invoice-header-image':{'label':'', 'type':'image_id', 'hidelabel':'yes', 'controls':'all', 'history':'no'},
            }},
        'header':{'label':'Header Address Options', 'fields':{
            'invoice-header-contact-position':{'label':'Position', 'type':'toggle', 'default':'center', 'toggles':this.positionOptions},
            'invoice-header-business-name':{'label':'Business Name', 'type':'toggle', 'default':'yes', 'toggles':this.toggleOptions},
            'invoice-header-business-address':{'label':'Address', 'type':'toggle', 'default':'yes', 'toggles':this.toggleOptions},
            'invoice-header-business-phone':{'label':'Phone', 'type':'toggle', 'default':'yes', 'toggles':this.toggleOptions},
            'invoice-header-business-cell':{'label':'Cell', 'type':'toggle', 'default':'yes', 'toggles':this.toggleOptions},
            'invoice-header-business-fax':{'label':'Fax', 'type':'toggle', 'default':'yes', 'toggles':this.toggleOptions},
            'invoice-header-business-email':{'label':'Email', 'type':'toggle', 'default':'yes', 'toggles':this.toggleOptions},
            'invoice-header-business-website':{'label':'Website', 'type':'toggle', 'default':'yes', 'toggles':this.toggleOptions},
            }},
        '_bottom_msg':{'label':'Invoice Message', 'fields':{
            'invoice-bottom-message':{'label':'', 'hidelabel':'yes', 'type':'textarea'},
            }},
        '_footer_msg':{'label':'Footer Message', 'fields':{
            'invoice-footer-message':{'label':'', 'hidelabel':'yes', 'type':'text'},
            }},
        '_invoice_email_msg':{'label':'Default Invoice Email Message', 'fields':{
            'invoice-email-message':{'label':'', 'hidelabel':'yes', 'type':'textarea'},
            }},
        '_buttons':{'label':'', 'buttons':{
            'save':{'label':'Save', 'fn':'M.ciniki_poma_settings.invoice.save();'},
            }},
    };
    this.invoice.fieldHistoryArgs = function(s, i) {
        return {'method':'ciniki.poma.settingsHistory', 'args':{'business_id':M.curBusinessID, 'setting':i}};
    }
    this.invoice.fieldValue = function(s, i, d) {
        if( this.data[i] == null && d.default != null ) { return d.default; }
        return this.data[i];
    };
    this.invoice.addDropImage = function(iid) {
        M.ciniki_poma_settings.invoice.setFieldValue('invoice-header-image', iid);
        return true;
    };
    this.invoice.deleteImage = function(fid) {
        this.setFieldValue(fid, 0);
        return true;
    };
    this.invoice.open = function(cb) {
        M.api.getJSONCb('ciniki.poma.settingsGet', {'business_id':M.curBusinessID}, function(rsp) {
            if( rsp.stat != 'ok' ) {
                M.api.err(rsp);
                return false;
            }
            var p = M.ciniki_poma_settings.invoice;
            p.data = rsp.settings;
            p.refresh();
            p.show(cb);
        });
    };
    this.invoice.save = function() {
        var c = this.serializeForm('no');
        if( c != '' ) {
            M.api.postJSONCb('ciniki.poma.settingsUpdate', {'business_id':M.curBusinessID}, 
                c, function(rsp) {
                    if( rsp.stat != 'ok' ) {
                        M.api.err(rsp);
                        return false;
                    }
                    M.ciniki_poma_settings.invoice.close();
                });
        } else {
            this.invoice.close();
        }
    };
    this.invoice.addButton('save', 'Save', 'M.ciniki_poma_settings.invoice.save();');
    this.invoice.addClose('Cancel');

    //
    // Arguments:
    // aG - The arguments to be parsed into args
    //
    this.start = function(cb, appPrefix, aG) {
        args = {};
        if( aG != null ) { args = eval(aG); }

        //
        // Create the app container if it doesn't exist, and clear it out
        // if it does exist.
        //
        var appContainer = M.createContainer(appPrefix, 'ciniki_poma_settings', 'yes');
        if( appContainer == null ) {
            alert('App Error');
            return false;
        } 
    
        this.invoice.open(cb);
    }
}
