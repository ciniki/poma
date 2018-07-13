<?php
//
// Description
// -----------
// This method will return the list of Notes for a tenant.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:        The ID of the tenant to get Note for.
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
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'customer_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Customer ID'),
        'ntype'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Note Type'),
        'archive_note_id'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Archive Note ID'),
        'customers'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Customers'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Check access to tnid as owner, or sys admin.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'checkAccess');
    $rc = ciniki_poma_checkAccess($ciniki, $args['tnid'], 'ciniki.poma.noteList');
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
    // Check if note to be archived
    //
    if( isset($args['archive_note_id']) && $args['archive_note_id'] > 0 ) {
        $strsql = "SELECT id, status "
            . "FROM ciniki_poma_notes "
            . "WHERE id = '" . ciniki_core_dbQuote($ciniki, $args['archive_note_id']) . "' "
            . "AND ciniki_poma_notes.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "";
        $rc = ciniki_core_dbHashQuery($ciniki, $strsql, 'ciniki.poma', 'note');
        if( $rc['stat'] != 'ok' ) {
            return $rc;
        }
        if( isset($rc['note']) && $rc['note']['status'] == 10 ) {
            ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'objectUpdate');
            $rc = ciniki_core_objectUpdate($ciniki, $args['tnid'], 'ciniki.poma.note', $args['archive_note_id'], array('status'=>60), 0x07);
            if( $rc['stat'] != 'ok' ) {
                return $rc;
            }
        }
    }

    //
    // Get the list of notes which are active
    //
    $strsql = "SELECT ciniki_poma_notes.id, "
        . "ciniki_poma_notes.note_date, "
        . "DATE_FORMAT(ciniki_poma_notes.note_date, '" . ciniki_core_dbQuote($ciniki, $date_format) . "') AS note_date_text, "
        . "ciniki_poma_notes.ntype, "
        . "ciniki_poma_notes.status, "
        . "ciniki_poma_notes.status AS status_text, "
        . "ciniki_poma_notes.customer_id, "
        . "ciniki_poma_notes.content "
        . "FROM ciniki_poma_notes "
        . "WHERE ciniki_poma_notes.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "AND status = 10 "
        . "";
    if( isset($args['ntype']) && $args['ntype'] >= 0 ) {
        $strsql .= "AND ntype = '" . ciniki_core_dbQuote($ciniki, $args['ntype']) . "' ";
    }
    if( isset($args['customer_id']) && $args['customer_id'] >= 0 ) {
        $strsql .= "AND customer_id = '" . ciniki_core_dbQuote($ciniki, $args['customer_id']) . "' ";
    }
    $strsql .= "ORDER BY note_date DESC ";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
        array('container'=>'notes', 'fname'=>'id', 
            'fields'=>array('id', 'ntype', 'note_date', 'note_date_text', 'status', 'status_text', 'customer_id', 'content'),
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
        . "ciniki_poma_notes.ntype, "
        . "ciniki_poma_notes.status, "
        . "ciniki_poma_notes.status AS status_text, "
        . "ciniki_poma_notes.customer_id, "
        . "ciniki_poma_notes.content "
        . "FROM ciniki_poma_notes "
        . "WHERE ciniki_poma_notes.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "AND status = 60 "
        . "";
    if( isset($args['ntype']) && $args['ntype'] >= 0 ) {
        $strsql .= "AND ntype = '" . ciniki_core_dbQuote($ciniki, $args['ntype']) . "' ";
    }
    if( isset($args['customer_id']) && $args['customer_id'] >= 0 ) {
        $strsql .= "AND customer_id = '" . ciniki_core_dbQuote($ciniki, $args['customer_id']) . "' ";
    }
    $strsql .= "ORDER BY note_date DESC ";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
        array('container'=>'notes', 'fname'=>'id', 
            'fields'=>array('id', 'ntype', 'note_date', 'note_date_text', 'status', 'status_text', 'customer_id', 'content'),
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
            . "ciniki_poma_notes.status, "
            . "IFNULL(ciniki_customers.display_name, 'General Notes') AS display_name, "
            . "COUNT(ciniki_poma_notes.id) AS num_items "
            . "FROM ciniki_poma_notes "
            . "LEFT JOIN ciniki_customers ON ("
                . "ciniki_poma_notes.customer_id = ciniki_customers.id "
                . "AND ciniki_customers.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
                . ") "
            . "WHERE ciniki_poma_notes.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . "";
        if( isset($args['ntype']) && $args['ntype'] != '' ) {
            $strsql .= "AND ciniki_poma_notes.ntype = '" . ciniki_core_dbQuote($ciniki, $args['ntype']) . "' ";
        }
        $strsql .= "GROUP BY customer_id, status "
            . "ORDER BY ciniki_customers.display_name "
            . "";
        $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
            array('container'=>'customers', 'fname'=>'customer_id', 'fields'=>array('id'=>'customer_id', 'display_name')),
            array('container'=>'statuses', 'fname'=>'status', 'fields'=>array('status', 'num_items')),
            ));
        if( $rc['stat'] != 'ok' ) { 
            return $rc;
        }
        if( !isset($rc['customers']) ) {
            $rsp['customers'] = array();
        } else {
            $rsp['customers'] = $rc['customers'];
            foreach($rsp['customers'] as $cid => $customer) {
                $rsp['customers'][$cid]['num_items'] = 0;
                if( isset($customer['statuses']) ) {
                    foreach($customer['statuses'] as $status) {
                        if( $status['status'] == 10 ) {
                            $rsp['customers'][$cid]['num_items'] = $status['num_items'];
                        }
                    }
                }
            }
        }
    }

    return $rsp;
}
?>
