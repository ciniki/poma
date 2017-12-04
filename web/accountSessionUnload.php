<?php
//
// Description
// -----------
// This function will check if a web session as any information for this module and remove it.
//
// Arguments
// ---------
//
// Returns
// -------
//
function ciniki_poma_web_accountSessionUnload($ciniki, $settings, $tnid) {

    if( isset($ciniki['session']['ciniki.poma']) ) {
        unset($ciniki['session']['ciniki.poma']);
    }

    return array('stat'=>'ok');
}
?>
