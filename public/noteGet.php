<?php
//
// Description
// ===========
// This method will return all the information about an note.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:         The ID of the tenant the note is attached to.
// note_id:          The ID of the note to get the details for.
//
// Returns
// -------
//
function ciniki_poma_noteGet($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'note_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Note'),
        'customer_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Customer'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Make sure this module is activated, and
    // check permission to run this function for this tenant
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'checkAccess');
    $rc = ciniki_poma_checkAccess($ciniki, $args['tnid'], 'ciniki.poma.noteGet');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Load tenant settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'intlSettings');
    $rc = ciniki_tenants_intlSettings($ciniki, $args['tnid']);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];
    $intl_currency_fmt = numfmt_create($rc['settings']['intl-default-locale'], NumberFormatter::CURRENCY);
    $intl_currency = $rc['settings']['intl-default-currency'];

    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'dateFormat');
    $date_format = ciniki_users_dateFormat($ciniki, 'php');

    //
    // Return default for new Note
    //
    if( $args['note_id'] == 0 ) {
        $dt = new DateTime('now', new DateTimezone($intl_timezone));
        $note = array('id'=>0,
            'note_date'=>$dt->format($date_format),
            'status'=>'10',
            'customer_id'=>(isset($args['customer_id']) ? $args['customer_id'] : '0'),
            'content'=>'',
        );
    }

    //
    // Get the details for an existing Note
    //
    else {
        $strsql = "SELECT ciniki_poma_notes.id, "
            . "ciniki_poma_notes.note_date, "
            . "ciniki_poma_notes.status, "
            . "ciniki_poma_notes.customer_id, "
            . "ciniki_poma_notes.content "
            . "FROM ciniki_poma_notes "
            . "WHERE ciniki_poma_notes.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "AND ciniki_poma_notes.id = '" . ciniki_core_dbQuote($ciniki, $args['note_id']) . "' "
            . "";
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
            array('container'=>'notes', 'fname'=>'id', 
                'fields'=>array('note_date', 'status', 'customer_id', 'content'),
                'utctotz'=>array('note_date'=>array('timezone'=>'UTC', 'format'=>$date_format)),                ),
            ));
        if( $rc['stat'] != 'ok' ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.141', 'msg'=>'Note not found', 'err'=>$rc['err']));
        }
        if( !isset($rc['notes'][0]) ) {
            return array('stat'=>'fail', 'err'=>array('code'=>'ciniki.poma.142', 'msg'=>'Unable to find Note'));
        }
        $note = $rc['notes'][0];
    }

    $rsp = array('stat'=>'ok', 'note'=>$note);

    $strsql = "SELECT ciniki_customers.id, "
        . "ciniki_customers.display_name AS name "
        . "FROM ciniki_customers "
        . "WHERE ciniki_customers.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "AND status = 10 "
        . "ORDER BY display_name "
        . "";
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
        array('container'=>'customers', 'fname'=>'id', 'fields'=>array('id', 'name')),
        ));
    if( $rc['stat'] != 'ok' ) { 
        return $rc;
    }
    if( !isset($rc['customers']) ) {
        $rsp['customers'] = array();
    } else {
        $rsp['customers'] = $rc['customers'];
    }
    
    return $rsp;
}
?>
