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
        'invoice':{'label':'', 'list':{
            'invoice':{'label':'Invoices', 'fn':'M.ciniki_poma_settings.invoice.open(\'M.ciniki_poma_settings.menu.open();\');'},
            'emails':{'label':'Emails', 'fn':'M.ciniki_poma_settings.emails.open(\'M.ciniki_poma_settings.menu.open();\');'},
            'dates':{'label':'Order Dates', 'fn':'M.ciniki_poma_settings.dates.open(\'M.ciniki_poma_settings.menu.open();\');'},
            }},
//        'expenses':{'label':'Expenses', 'visible':'no', 'list':{
//            'expenses':{'label':'Expense Categories', 'fn':'M.ciniki_poma_settings.showExpenseCategories(\'M.ciniki_poma_settings.showMenu();\');'},
//            }},
    };
    this.menu.open = function(cb) {
        this.refresh();
        this.show(cb);
    }
    this.menu.addClose('Back');

    //
    // The invoice settings panel
    //
    this.invoice = new M.panel('Invoice Settings', 'ciniki_poma_settings', 'invoice', 'mc', 'medium narrowaside', 'sectioned', 'ciniki.poma.settings.invoice');
    this.invoice.sections = {
        'image':{'label':'Header Image', 'aside':'yes', 'fields':{
            'invoice-header-image':{'label':'', 'type':'image_id', 'hidelabel':'yes', 'controls':'all', 'history':'no'},
            }},
        'header':{'label':'Header Address Options', 'fields':{
            'invoice-header-contact-position':{'label':'Position', 'type':'toggle', 'default':'center', 'toggles':this.positionOptions},
            'invoice-header-tenant-name':{'label':'Tenant Name', 'type':'toggle', 'default':'yes', 'toggles':this.toggleOptions},
            'invoice-header-tenant-address':{'label':'Address', 'type':'toggle', 'default':'yes', 'toggles':this.toggleOptions},
            'invoice-header-tenant-phone':{'label':'Phone', 'type':'toggle', 'default':'yes', 'toggles':this.toggleOptions},
            'invoice-header-tenant-cell':{'label':'Cell', 'type':'toggle', 'default':'yes', 'toggles':this.toggleOptions},
            'invoice-header-tenant-fax':{'label':'Fax', 'type':'toggle', 'default':'yes', 'toggles':this.toggleOptions},
            'invoice-header-tenant-email':{'label':'Email', 'type':'toggle', 'default':'yes', 'toggles':this.toggleOptions},
            'invoice-header-tenant-website':{'label':'Website', 'type':'toggle', 'default':'yes', 'toggles':this.toggleOptions},
            }},
        '_bottom_msg':{'label':'Invoice Message', 'fields':{
            'invoice-bottom-message':{'label':'', 'hidelabel':'yes', 'type':'textarea'},
            }},
        '_footer_msg':{'label':'Footer Message', 'fields':{
            'invoice-footer-message':{'label':'', 'hidelabel':'yes', 'type':'text'},
            }},
        '_buttons':{'label':'', 'buttons':{
            'save':{'label':'Save', 'fn':'M.ciniki_poma_settings.invoice.save();'},
            }},
    };
    this.invoice.fieldHistoryArgs = function(s, i) {
        return {'method':'ciniki.poma.settingsHistory', 'args':{'tnid':M.curTenantID, 'setting':i}};
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
        M.api.getJSONCb('ciniki.poma.settingsGet', {'tnid':M.curTenantID}, function(rsp) {
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
            M.api.postJSONCb('ciniki.poma.settingsUpdate', {'tnid':M.curTenantID}, 
                c, function(rsp) {
                    if( rsp.stat != 'ok' ) {
                        M.api.err(rsp);
                        return false;
                    }
                    M.ciniki_poma_settings.invoice.close();
                });
        } else {
            this.close();
        }
    };
    this.invoice.addButton('save', 'Save', 'M.ciniki_poma_settings.invoice.save();');
    this.invoice.addClose('Cancel');

    //
    // The email templates panel
    //
    this.emails = new M.panel('Email Templates', 'ciniki_poma_settings', 'emails', 'mc', 'medium mediumaside', 'sectioned', 'ciniki.poma.settings.emails');
    this.emails.selected = 'repeats';
    this.emails.sections = {
        '_menu':{'label':'Email Templates', 'aside':'yes', 'list':{
            'repeats':{'label':'Standing Order Added', 'fn':'M.ciniki_poma_settings.emails.switchTab("repeats");'},
//            'subs':{'label':'Substitutions Enabled', 'fn':'M.ciniki_poma_settings.emails.switchTab("subs");'},
            'locking':{'label':'Locking Notice 24 Hours', 'fn':'M.ciniki_poma_settings.emails.switchTab("locking");'},
//            'locked':{'label':'Order Locked', 'fn':'M.ciniki_poma_settings.emails.switchTab("locked");'},
            'pickup':{'label':'Pickup Reminder', 'fn':'M.ciniki_poma_settings.emails.switchTab("pickup");'},
            'updated':{'label':'Order Updated', 'fn':'M.ciniki_poma_settings.emails.switchTab("updated");'},
            'order':{'label':'Email Order', 'fn':'M.ciniki_poma_settings.emails.switchTab("order");'},
            'invoiceunpaid':{'label':'Email Unpaid Invoice', 'fn':'M.ciniki_poma_settings.emails.switchTab("invoiceunpaid");'},
            'invoicepaid':{'label':'Email Paid Invoice', 'fn':'M.ciniki_poma_settings.emails.switchTab("invoicepaid");'},
            }},
        '_repeats_help':{'label':'Standing Order Emails', 'type':'htmlcontent', 'aside':'yes',
            'visible':function() { return (M.ciniki_poma_settings.emails.selected == 'repeats' ? 'yes' : 'hidden'); },
            'html':'Standing order emails will be sent when the items are added to the customers order.'
            },
        '_repeats_subject':{'label':'Subject', 
            'visible':function() { return (M.ciniki_poma_settings.emails.selected == 'repeats' ? 'yes' : 'hidden'); },
            'fields':{
                'email-repeats-added-subject':{'label':'', 'hidelabel':'yes', 'type':'text'},
            }},
        '_repeats_content':{'label':'Message', 
            'visible':function() { return (M.ciniki_poma_settings.emails.selected == 'repeats' ? 'yes' : 'hidden'); },
            'fields':{
                'email-repeats-added-message':{'label':'', 'hidelabel':'yes', 'type':'textarea', 'size':'large'},
            }},
        '_locking_help':{'label':'Locking Notice 24 Hours', 'type':'htmlcontent', 'aside':'yes',
            'visible':function() { return (M.ciniki_poma_settings.emails.selected == 'locking' ? 'yes' : 'hidden'); },
            'html':'This email will be sent 24 hours before the order is locked.'
            },
        '_locking_subject':{'label':'Subject', 
            'visible':function() { return (M.ciniki_poma_settings.emails.selected == 'locking' ? 'yes' : 'hidden'); },
            'fields':{
                'email-locking-reminder-subject':{'label':'', 'hidelabel':'yes', 'type':'text'},
            }},
        '_locking_content':{'label':'Message', 
            'visible':function() { return (M.ciniki_poma_settings.emails.selected == 'locking' ? 'yes' : 'hidden'); },
            'fields':{
                'email-locking-reminder-message':{'label':'', 'hidelabel':'yes', 'type':'textarea', 'size':'large'},
            }},
        '_locked_help':{'label':'Order Locked', 'type':'htmlcontent', 'aside':'yes',
            'visible':function() { return (M.ciniki_poma_settings.emails.selected == 'locked' ? 'yes' : 'hidden'); },
            'html':'This email will be sent when the order is locked and no more changes are allowed.'
            },
        '_locked_subject':{'label':'Subject', 
            'visible':function() { return (M.ciniki_poma_settings.emails.selected == 'locked' ? 'yes' : 'hidden'); },
            'fields':{
                'email-locked-notice-subject':{'label':'', 'hidelabel':'yes', 'type':'text'},
            }},
        '_locked_content':{'label':'Message', 
            'visible':function() { return (M.ciniki_poma_settings.emails.selected == 'locked' ? 'yes' : 'hidden'); },
            'fields':{
                'email-locked-notice-message':{'label':'', 'hidelabel':'yes', 'type':'textarea', 'size':'large'},
            }},
        '_pickup_help':{'label':'Pickup Reminder', 'type':'htmlcontent', 'aside':'yes',
            'visible':function() { return (M.ciniki_poma_settings.emails.selected == 'pickup' ? 'yes' : 'hidden'); },
            'html':'This email will be sent typically on the pickup date to remind the customer of their order.'
            },
        '_pickup_subject':{'label':'Subject', 
            'visible':function() { return (M.ciniki_poma_settings.emails.selected == 'pickup' ? 'yes' : 'hidden'); },
            'fields':{
                'email-pickup-reminder-subject':{'label':'', 'hidelabel':'yes', 'type':'text'},
            }},
        '_pickup_content':{'label':'Message', 
            'visible':function() { return (M.ciniki_poma_settings.emails.selected == 'pickup' ? 'yes' : 'hidden'); },
            'fields':{
                'email-pickup-reminder-message':{'label':'', 'hidelabel':'yes', 'type':'textarea', 'size':'large'},
            }},
        '_updated_help':{'label':'Order Updated', 'type':'htmlcontent', 'aside':'yes',
            'visible':function() { return (M.ciniki_poma_settings.emails.selected == 'updated' ? 'yes' : 'hidden'); },
            'html':'This email will be sent 30 minutes after their order was changed.'
            },
        '_updated_subject':{'label':'Subject', 
            'visible':function() { return (M.ciniki_poma_settings.emails.selected == 'updated' ? 'yes' : 'hidden'); },
            'fields':{
                'email-updated-order-subject':{'label':'', 'hidelabel':'yes', 'type':'text'},
            }},
        '_updated_content':{'label':'Message', 
            'visible':function() { return (M.ciniki_poma_settings.emails.selected == 'updated' ? 'yes' : 'hidden'); },
            'fields':{
                'email-updated-order-message':{'label':'', 'hidelabel':'yes', 'type':'textarea', 'size':'large'},
            }},
        '_order_help':{'label':'Email Order', 'type':'htmlcontent', 'aside':'yes',
            'visible':function() { return (M.ciniki_poma_settings.emails.selected == 'order' ? 'yes' : 'hidden'); },
            'html':'This is the default message for emailing an order from Ciniki Manager.'
            },
        '_order_subject':{'label':'Subject', 
            'visible':function() { return (M.ciniki_poma_settings.emails.selected == 'order' ? 'yes' : 'hidden'); },
            'fields':{
                'email-order-details-subject':{'label':'', 'hidelabel':'yes', 'type':'text'},
            }},
        '_order_content':{'label':'Message', 
            'visible':function() { return (M.ciniki_poma_settings.emails.selected == 'order' ? 'yes' : 'hidden'); },
            'fields':{
                'email-order-details-message':{'label':'', 'hidelabel':'yes', 'type':'textarea', 'size':'large'},
            }},
        '_invoiceunpaid_help':{'label':'Email Invoice', 'type':'htmlcontent', 'aside':'yes',
            'visible':function() { return (M.ciniki_poma_settings.emails.selected == 'invoiceunpaid' ? 'yes' : 'hidden'); },
            'html':'This is the default message for emailing an unpaid invoice from Ciniki Manager. This is when the order has be invoiced but is not yet paid.'
            },
        '_invoiceunpaid_subject':{'label':'Subject', 
            'visible':function() { return (M.ciniki_poma_settings.emails.selected == 'invoiceunpaid' ? 'yes' : 'hidden'); },
            'fields':{
                'email-invoice-unpaid-subject':{'label':'', 'hidelabel':'yes', 'type':'text'},
            }},
        '_invoiceunpaid_content':{'label':'Message', 
            'visible':function() { return (M.ciniki_poma_settings.emails.selected == 'invoiceunpaid' ? 'yes' : 'hidden'); },
            'fields':{
                'email-invoice-unpaid-message':{'label':'', 'hidelabel':'yes', 'type':'textarea', 'size':'large'},
            }},
        '_invoicepaid_help':{'label':'Email Invoice', 'type':'htmlcontent', 'aside':'yes',
            'visible':function() { return (M.ciniki_poma_settings.emails.selected == 'invoicepaid' ? 'yes' : 'hidden'); },
            'html':'This is the default message for emailing an paid invoice from Ciniki Manager. Typically this is considered a receipt.'
            },
        '_invoicepaid_subject':{'label':'Subject', 
            'visible':function() { return (M.ciniki_poma_settings.emails.selected == 'invoicepaid' ? 'yes' : 'hidden'); },
            'fields':{
                'email-invoice-paid-subject':{'label':'', 'hidelabel':'yes', 'type':'text'},
            }},
        '_invoicepaid_content':{'label':'Message', 
            'visible':function() { return (M.ciniki_poma_settings.emails.selected == 'invoicepaid' ? 'yes' : 'hidden'); },
            'fields':{
                'email-invoice-paid-message':{'label':'', 'hidelabel':'yes', 'type':'textarea', 'size':'large'},
            }},
        '_buttons':{'label':'', 'buttons':{
            'save':{'label':'Save', 'fn':'M.ciniki_poma_settings.emails.save();'},
            }},
    };
    this.emails.fieldHistoryArgs = function(s, i) {
        return {'method':'ciniki.poma.settingsHistory', 'args':{'tnid':M.curTenantID, 'setting':i}};
    }
    this.emails.fieldValue = function(s, i, d) {
        if( this.data[i] == null && d.default != null ) { return d.default; }
        return this.data[i];
    };
    this.emails.listClass = function(s, i, d) {
        if( i == this.selected ) { return 'highlight'; }
        return null;
    }
    this.emails.switchTab = function(t) {
        this.selected = t;
        this.refreshSection('_menu');
        this.showHideSections();
    }
    this.emails.open = function(cb) {
        M.api.getJSONCb('ciniki.poma.settingsGet', {'tnid':M.curTenantID}, function(rsp) {
            if( rsp.stat != 'ok' ) {
                M.api.err(rsp);
                return false;
            }
            var p = M.ciniki_poma_settings.emails;
            p.data = rsp.settings;
            p.refresh();
            p.show(cb);
        });
    };
    this.emails.save = function() {
        var c = this.serializeForm('no');
        if( c != '' ) {
            M.api.postJSONCb('ciniki.poma.settingsUpdate', {'tnid':M.curTenantID}, c, function(rsp) {
                if( rsp.stat != 'ok' ) {
                    M.api.err(rsp);
                    return false;
                }
                M.ciniki_poma_settings.emails.close();
            });
        } else {
            this.close();
        }
    };
    this.emails.addButton('save', 'Save', 'M.ciniki_poma_settings.emails.save();');
    this.emails.addClose('Cancel');

    //
    // The dates settings panel
    //
    this.dates = new M.panel('Invoice Settings', 'ciniki_poma_settings', 'dates', 'mc', 'medium', 'sectioned', 'ciniki.poma.settings.dates');
    this.dates.sections = {
        'open':{'label':'Auto Open Order Dates', 'fields':{
            'dates-open-auto':{'label':'Auto open', 'type':'toggle', 'default':'no', 'toggles':this.yesNoOptions},
            'dates-open-offset':{'label':'Days Prior', 'type':'toggle', 'toggles':{'5':'5', '6':'6', '7':'7', '8':'8', '13':'13', '14':'14'}},
            'dates-open-time':{'label':'Time', 'type':'text', 'size':'small'},
            }},
        'lock':{'label':'Auto Lock Orders', 'fields':{
            'dates-lock-auto':{'label':'Auto Lock', 'type':'toggle', 'default':'no', 'toggles':this.yesNoOptions},
            'dates-lock-offset':{'label':'Days Prior', 'type':'toggle', 'toggles':{'0':'0', '1':'1', '2':'2', '3':'3', '4':'4', '5':'5', '6':'6'}},
            'dates-lock-time':{'label':'Time', 'type':'text', 'size':'small'},
            }},
        'pickupreminder':{'label':'Pickup Reminders', 'fields':{
            'dates-pickup-reminder':{'label':'Reminders', 'type':'toggle', 'default':'no', 'toggles':this.yesNoOptions},
            'dates-pickup-reminder-offset':{'label':'Days Prior', 'type':'toggle', 'toggles':{'0':'0', '1':'1', '2':'2'}},
            'dates-pickup-reminder-time':{'label':'Time', 'type':'text', 'size':'small'},
            }},
        'repeats':{'label':'Apply Repeat', 'fields':{
            'dates-apply-repeats-offset':{'label':'Days Prior', 'type':'toggle', 'toggles':{'0':'0', '1':'1', '2':'2', '3':'3', '4':'4', '5':'5', '6':'6'}},
            'dates-apply-repeats-time':{'label':'Time', 'type':'text', 'size':'small'},
            }},
        '_buttons':{'label':'', 'buttons':{
            'save':{'label':'Save', 'fn':'M.ciniki_poma_settings.dates.save();'},
            }},
    };
    this.dates.fieldHistoryArgs = function(s, i) {
        return {'method':'ciniki.poma.settingsHistory', 'args':{'tnid':M.curTenantID, 'setting':i}};
    }
    this.dates.fieldValue = function(s, i, d) {
        if( this.data[i] == null && d.default != null ) { return d.default; }
        return this.data[i];
    };
    this.dates.open = function(cb) {
        M.api.getJSONCb('ciniki.poma.settingsGet', {'tnid':M.curTenantID}, function(rsp) {
            if( rsp.stat != 'ok' ) {
                M.api.err(rsp);
                return false;
            }
            var p = M.ciniki_poma_settings.dates;
            p.data = rsp.settings;
            p.refresh();
            p.show(cb);
        });
    };
    this.dates.save = function() {
        var c = this.serializeForm('no');
        if( c != '' ) {
            M.api.postJSONCb('ciniki.poma.settingsUpdate', {'tnid':M.curTenantID}, 
                c, function(rsp) {
                    if( rsp.stat != 'ok' ) {
                        M.api.err(rsp);
                        return false;
                    }
                    M.ciniki_poma_settings.dates.close();
                });
        } else {
            this.close();
        }
    };
    this.dates.addButton('save', 'Save', 'M.ciniki_poma_settings.dates.save();');
    this.dates.addClose('Cancel');

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
    
        this.menu.open(cb);
    }
}
