<?php
//
// Description
// -----------
// This function returns the int to text mappings for the module.
//
// Arguments
// ---------
//
// Returns
// -------
//
function ciniki_poma_maps(&$ciniki) {
    //
    // Build the maps object
    //
    $maps = array();
    $maps['orderdate'] = array('status'=>array(
        '10'=>'Open',
        '20'=>'Open - Repeats Added',
        '30'=>'Substitutions',
        '50'=>'Locked',
        '90'=>'Closed',
    ));

    return array('stat'=>'ok', 'maps'=>$maps);
}
?>
