<?php
//
// Description
// -----------
// This method returns the list of accounts in the POMA module. These are customers who have a ledger.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:        The ID of the tenant to get Order Date for.
//
// Returns
// -------
//
function ciniki_poma_accountSearch($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'search_str'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Search String'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Check access to tnid as owner, or sys admin.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'checkAccess');
    $rc = ciniki_poma_checkAccess($ciniki, $args['tnid'], 'ciniki.poma.accountSearch');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Get the list of accounts, those customers who have a ledger
    //
    $strsql = "SELECT DISTINCT ciniki_poma_customer_ledgers.customer_id, "
        . "ciniki_customers.display_name "
        . "FROM ciniki_poma_customer_ledgers "
        . "LEFT JOIN ciniki_customers ON ("
            . "ciniki_poma_customer_ledgers.customer_id = ciniki_customers.id "
            . "AND ciniki_customers.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
            . ") "
        . "WHERE ciniki_poma_customer_ledgers.tnid = '" . ciniki_core_dbQuote($ciniki, $args['tnid']) . "' "
        . "AND ("
            . "ciniki_customers.display_name like '" . ciniki_core_dbQuote($ciniki, $args['search_str']) . "%' "
            . "OR ciniki_customers.display_name like '% " . ciniki_core_dbQuote($ciniki, $args['search_str']) . "%' "
            . ") "
//        . "GROUP BY ciniki_poma_customer_ledgers.id "
        . "ORDER BY ciniki_customers.display_name ASC "
        . "";
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'dbHashQueryArrayTree');
    $rc = ciniki_core_dbHashQueryArrayTree($ciniki, $strsql, 'ciniki.poma', array(
        array('container'=>'accounts', 'fname'=>'customer_id', 'fields'=>array('customer_id', 'display_name')),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    if( isset($rc['accounts']) ) {
        $accounts = $rc['accounts'];
        $account_ids = array();
        foreach($accounts as $iid => $account) {
            $account_ids[] = $account['customer_id'];
        }
    } else {
        $accounts = array();
        $account_ids = array();
    }

    return array('stat'=>'ok', 'accounts'=>$accounts, 'nplist'=>$account_ids);
}
?>
