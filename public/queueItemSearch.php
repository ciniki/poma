<?php
//
// Description
// -----------
// This method will search for available items that can be added to a customers queue.
//
// Arguments
// ---------
// api_key:
// auth_token:
// tnid:        The ID of the tenant to get Order Item for.
// start_needle:       The search string to search for.
// limit:              The maximum number of entries to return.
//
// Returns
// -------
//
function ciniki_poma_queueItemSearch($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'tnid'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Tenant'),
        'start_needle'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Search String'),
        'limit'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Limit'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Check access to tnid as owner, or sys admin.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'checkAccess');
    $rc = ciniki_poma_checkAccess($ciniki, $args['tnid'], 'ciniki.poma.queueItemSearch');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Load tenant INTL settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'tenants', 'private', 'intlSettings');
    $rc = ciniki_tenants_intlSettings($ciniki, $args['tnid']);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];
    $intl_currency_fmt = numfmt_create($rc['settings']['intl-default-locale'], NumberFormatter::CURRENCY);
    $intl_currency = $rc['settings']['intl-default-currency'];

    //
    // Prepare the search string
    //
    $uwords = explode(' ', $args['start_needle']);
    $kwords = array();
    foreach($uwords as $word) {
        if( trim($word) == '' ) {
            continue;
        }
        $kwords[] = $word;
    }
    sort($kwords);
    $keywords = implode(' ', array_unique($kwords));

    //
    // Setup the array for the items
    //
    $items = array();

    //
    // Check for modules which have searchable items
    //
    foreach($ciniki['tenant']['modules'] as $module => $m) {
        list($pkg, $mod) = explode('.', $module);
        $rc = ciniki_core_loadMethod($ciniki, $pkg, $mod, 'poma', 'queueItemSearch');
        if( $rc['stat'] != 'ok' ) {
            continue;
        }
        $fn = $rc['function_call'];
        $rc = $fn($ciniki, $args['tnid'], array(
            'keywords'=>$keywords,
            'limit'=>$args['limit']));
        if( $rc['stat'] != 'ok' ) {
            continue;
        }
        if( isset($rc['items']) ) {
            $items = array_merge($items, $rc['items']);
        }
    }

    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'formatItems');
    $rc = ciniki_poma_formatItems($ciniki, $args['tnid'], $items);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $items = $rc['items'];

    return array('stat'=>'ok', 'items'=>$items);
}
?>
