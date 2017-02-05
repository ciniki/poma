<?php
//
// Description
// -----------
// This method searchs for a Order Items for a business.
//
// Arguments
// ---------
// api_key:
// auth_token:
// business_id:        The ID of the business to get Order Item for.
// start_needle:       The search string to search for.
// limit:              The maximum number of entries to return.
//
// Returns
// -------
//
function ciniki_poma_repeatItemSearch($ciniki) {
    //
    // Find all the required and optional arguments
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'core', 'private', 'prepareArgs');
    $rc = ciniki_core_prepareArgs($ciniki, 'no', array(
        'business_id'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Business'),
        'start_needle'=>array('required'=>'yes', 'blank'=>'no', 'name'=>'Search String'),
        'limit'=>array('required'=>'no', 'blank'=>'yes', 'name'=>'Limit'),
        ));
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $args = $rc['args'];

    //
    // Check access to business_id as owner, or sys admin.
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'checkAccess');
    $rc = ciniki_poma_checkAccess($ciniki, $args['business_id'], 'ciniki.poma.repeatItemSearch');
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }

    //
    // Load business INTL settings
    //
    ciniki_core_loadMethod($ciniki, 'ciniki', 'businesses', 'private', 'intlSettings');
    $rc = ciniki_businesses_intlSettings($ciniki, $args['business_id']);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $intl_timezone = $rc['settings']['intl-default-timezone'];
    $intl_currency_fmt = numfmt_create($rc['settings']['intl-default-locale'], NumberFormatter::CURRENCY);
    $intl_currency = $rc['settings']['intl-default-currency'];

    //
    // Prepare the search string
    //
    $words = explode(' ', $args['start_needle']);
    $keywords = '';
    foreach($words as $word) {
        if( trim($word) == '' ) {
            continue;
        }
        $keywords .= ($keywords != '' ? ' ' : '') . $word;
    }

    //
    // Setup the array for the items
    //
    $items = array();

    //
    // Check for modules which have searchable items
    //
    foreach($ciniki['business']['modules'] as $module => $m) {
        list($pkg, $mod) = explode('.', $module);
        $rc = ciniki_core_loadMethod($ciniki, $pkg, $mod, 'poma', 'itemSearch');
        if( $rc['stat'] != 'ok' ) {
            continue;
        }
        $fn = $rc['function_call'];
        $rc = $fn($ciniki, $args['business_id'], array(
            'keywords'=>$keywords,
            'order_id'=>$args['order_id'],
            'limit'=>$args['limit']));
        if( $rc['stat'] != 'ok' ) {
            continue;
        }
        if( isset($rc['items']) ) {
            $items = array_merge($items, $rc['items']);
        }
    }

    ciniki_core_loadMethod($ciniki, 'ciniki', 'poma', 'private', 'formatItems');
    $rc = ciniki_poma_formatItems($ciniki, $args['business_id'], $items);
    if( $rc['stat'] != 'ok' ) {
        return $rc;
    }
    $items = $rc['items'];

    return array('stat'=>'ok', 'items'=>$items);
}
?>
