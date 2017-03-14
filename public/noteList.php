<?php
//
// Description
// -----------
// This method will return the list of Notes for a business.
//
// Arguments
// ---------
// api_key:
// auth_token:
// business_id:        The ID of the business to get Note for.
//
// Returns
// -------
//
function ciniki_poma_noteList($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'business_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Business'),
        'customer_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Customer ID'),
        'customers'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Customers'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Check access to business_id as owner, or sys admin.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'checkAccess');
    $rc = ciniki_poma_checkAccess($ciniki, $args['business_id'], 'ciniki.poma.noteList');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    ciniki_core_loadMethod($ciniki, 'ciniki', 'users', 'private', 'dateFormat');
    $date_format = ciniki_users_dateFormat($ciniki, 'mysql');

    //
    // Load poma maps
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'maps');
    $rc = ciniki_poma_maps($ciniki);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $maps = $rc['maps'];

    //
    // Get the list of notes which are active
    //
    $strsql = "SELECT ciniki_poma_notes.id, "
        . "ciniki_poma_notes.note_date, "
        . "DATE_FORMAT(ciniki_poma_notes.note_date, '" . ciniki_core_dbQuote($ciniki, $date_format) . "') AS note_date_text, "
        . "ciniki_poma_notes.status, "
        . "ciniki_poma_notes.status AS status_text, "
        . "ciniki_poma_notes.customer_id, "
        . "ciniki_poma_notes.content "
        . "FROM ciniki_poma_notes "
        . "WHERE ciniki_poma_notes.business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
        . "AND status = 10 "
        . "";
    if( isset($args['customer_id']) && $args['customer_id'] >= 0 ) {
        $strsql .= "AND customer_id = '" . ciniki_core_dbQuote($ciniki, $args['customer_id']) . "' ";
    }
    $strsql .= "ORDER BY note_date DESC ";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
        array('container'=>'notes', 'fname'=>'id', 
            'fields'=>array('id', 'note_date', 'note_date_text', 'status', 'status_text', 'customer_id', 'content'),
            'maps'=>array('status_text'=>$maps['note']['status']),
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['notes']) ) {
        $notes = $rc['notes'];
        $note_ids = array();
        foreach($notes as $iid => $note) {
            $note_ids[] = $note['id'];
        }
    } else {
        $notes = array();
        $note_ids = array();
    }

    //
    // Get the list of notes for archived
    //
    $strsql = "SELECT ciniki_poma_notes.id, "
        . "ciniki_poma_notes.note_date, "
        . "DATE_FORMAT(ciniki_poma_notes.note_date, '" . ciniki_core_dbQuote($ciniki, $date_format) . "') AS note_date_text, "
        . "ciniki_poma_notes.status, "
        . "ciniki_poma_notes.status AS status_text, "
        . "ciniki_poma_notes.customer_id, "
        . "ciniki_poma_notes.content "
        . "FROM ciniki_poma_notes "
        . "WHERE ciniki_poma_notes.business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
        . "AND status = 60 "
        . "";
    if( isset($args['customer_id']) && $args['customer_id'] >= 0 ) {
        $strsql .= "AND customer_id = '" . ciniki_core_dbQuote($ciniki, $args['customer_id']) . "' ";
    }
    $strsql .= "ORDER BY note_date DESC ";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
        array('container'=>'notes', 'fname'=>'id', 
            'fields'=>array('id', 'note_date', 'note_date_text', 'status', 'status_text', 'customer_id', 'content'),
            'maps'=>array('status_text'=>$maps['note']['status']),
            ),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['notes']) ) {
        $archived_notes = $rc['notes'];
        $archived_note_ids = array();
        foreach($archived_notes as $iid => $note) {
            $archived_note_ids[] = $note['id'];
        }
    } else {
        $archived_notes = array();
        $archived_note_ids = array();
    }

    $rsp = array('stat'=>'ok', 'notes'=>$notes, 'archived_notes'=>$archived_notes, 'nplist'=>$note_ids);

    //
    // Get the list of customers with notes
    //
    if( isset($args['customers']) && $args['customers'] == 'yes' ) {
        $strsql = "SELECT ciniki_poma_notes.customer_id, "
            . "IFNULL(ciniki_customers.display_name, 'General Notes') AS display_name, "
            . "COUNT(ciniki_poma_notes.id) AS num_items "
            . "FROM ciniki_poma_notes "
            . "LEFT JOIN ciniki_customers ON ("
                . "ciniki_poma_notes.customer_id = ciniki_customers.id "
                . "AND ciniki_customers.business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
                . ") "
            . "WHERE ciniki_poma_notes.business_id = '" . ciniki_core_dbQuote($ciniki, $args['business_id']) . "' "
            . "GROUP BY customer_id "
            . "ORDER BY ciniki_customers.display_name "
            . "";
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
            array('container'=>'customers', 'fname'=>'customer_id', 'fields'=>array('id'=>'customer_id', 'display_name', 'num_items')),
            ));
        if( $rc['stat'] != 'ok' ) { 
            return $rc;
        }
        if( !isset($rc['customers']) ) {
            $rsp['customers'] = array();
        } else {
            $rsp['customers'] = $rc['customers'];
        }
    }

    return $rsp;
}
?>