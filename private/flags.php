<?php
//
// Description
// -----------
// The module flags
//
// Arguments
// ---------
//
// Returns
// -------
//
function ciniki_poma_flags(&$ciniki) {
    //
    // The flags for the object
    //
    $flags = array(
        // 0x01
        array('flag'=>array('bit'=>'1', 'name'=>'Standing Orders')),
        array('flag'=>array('bit'=>'2', 'name'=>'Queue')),
        array('flag'=>array('bit'=>'3', 'name'=>'CSA')),
        array('flag'=>array('bit'=>'4', 'name'=>'Pickup Times')),
        // 0x10
//        array('flag'=>array('bit'=>'5', 'name'=>'')),
//        array('flag'=>array('bit'=>'6', 'name'=>'')),
//        array('flag'=>array('bit'=>'7', 'name'=>'')),
//        array('flag'=>array('bit'=>'8', 'name'=>'')),
        // 0x0100
//        array('flag'=>array('bit'=>'9', 'name'=>'')),
//        array('flag'=>array('bit'=>'10', 'name'=>'')),
//        array('flag'=>array('bit'=>'11', 'name'=>'')),
//        array('flag'=>array('bit'=>'12', 'name'=>'')),
        // 0x1000
//        array('flag'=>array('bit'=>'13', 'name'=>'')),
//        array('flag'=>array('bit'=>'14', 'name'=>'')),
//        array('flag'=>array('bit'=>'15', 'name'=>'')),
//        array('flag'=>array('bit'=>'16', 'name'=>'')),
        );
    //
    return array('stat'=>'ok', 'flags'=>$flags);
}
?>
