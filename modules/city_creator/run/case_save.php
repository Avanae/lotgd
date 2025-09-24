<?php
use Lotgd\MySQL\Database;

include_once('lib/gamelog.php');
require_once('lib/sanitize.php');

// Fields arrays
$field_array  = ['cityactive','cityid','cityauthor','cityname','citytype','citychat','citytravel','module'];
$field_array2 = ['villtitle','villtext','villclock','villnewest1','villnewest2','villtalk','villsayline','villgatenav','villfightnav','villmarketnav','villtavernnav','villinfonav','villothernav','villinnname','villstablename','villmercenarycamp','villarmorshop','villweaponshop','villpvpstart','villpvpwin','villpvploss'];
$field_array3 = ['stabtitle','stabdesc','stabnosuchbeast','stabfinebeast','stabtoolittle','stabreplacemount','stabnewmount','stabnofeed','stabnothungry','stabhalfhungry','stabhungry','stabmountfull','stabnofeedgold','stabconfirmsale','stabmountsold','staboffer','stablass','stablad'];
$field_array4 = ['armtitle','armdesc','armtradein','armnosuchweapon','armtryagain','armnotenoughgold','armpayarmor'];
$field_array5 = ['weaptitle','weapdesc','weaptradein','weapnosuchweapon','weaptryagain','weapnotenoughgold','weappayweapon'];
$field_array6 = ['merctitle','mercdesc','mercbuynav','merchealnav','merchealtext','merchealnotenough','merchealpaid','merctoomanycompanions','mercmanycompanions','merconecompanion','mercnocompanions'];
$field_array7 = ['modsall','modsother'];
$field_array8 = ['navsother','navsforest','navspvp','navsmercenarycamp','navstrain','navslodge','navsweapons','navsarmor','navsbank','navsgypsy','navsinn','navsstables','navsgardens','navsrock','navsclan','navsnews','navslist','navshof'];

$field2 = $field3 = $field4 = $field5 = $field6 = $field7 = $field8 = [];

// Add new?
if (httppost('addnew') == 1) $_POST['cityid'] = 0;

// Author fallback
$_POST['cityauthor'] = $_POST['cityauthor'] !== '' ? strip_tags($_POST['cityauthor']) : $session['user']['login'];

// Sanitize name and type
$_POST['cityname'] = $_POST['cityname'] === '' ? getsetting('villagename', LOCATION_FIELDS) : full_sanitize(str_replace(['"',"'"], '', strip_tags($_POST['cityname'])));
$_POST['citytype'] = $_POST['citytype'] === '' ? 'City' : full_sanitize(str_replace(['"',"'"], '', strip_tags($_POST['citytype'])));
if ($_POST['module'] == '') $_POST['module'] = 'city_creator';

$post = httpallpost();
$cityid = httppost('cityid');

// Get modules with prefs-city
$module_array = [];
$result = db_query("SELECT modulename FROM " . db_prefix('modules') . " WHERE infokeys LIKE '%|prefs-city|%' ORDER BY formalname");
while ($row = db_fetch_assoc($result)) {
    $module_array[] = $row['modulename'];
}

$db = Database::getConnection();

if ($cityid > 0) {
    // Existing city
    $oldvalues = @unserialize(stripslashes($post['oldvalues']));
    unset($post['oldvalues'], $post['cityid']);

    $sql_update_parts = [];
    foreach ($post as $key => $val) {
        if (in_array($key, $field_array)) {
            if ($key == 'cityname' && $val != $oldvalues[$key]) {
                $db->update(db_prefix('accounts'), ['location' => $val], ['location' => $oldvalues[$key]]);
            }
            $sql_update_parts[$key] = $val;
            unset($post[$key], $oldvalues[$key]);
        } elseif (in_array($key, $field_array2) && $val !== '') {
            $field2[substr($key,4)] = $val; unset($post[$key], $oldvalues[$key]);
        } elseif (in_array($key, $field_array3) && $val !== '') {
            $field3[substr($key,4)] = $val; unset($post[$key], $oldvalues[$key]);
        } elseif (in_array($key, $field_array4) && $val !== '') {
            $field4[substr($key,3)] = $val; unset($post[$key], $oldvalues[$key]);
        } elseif (in_array($key, $field_array5) && $val !== '') {
            $field5[substr($key,4)] = $val; unset($post[$key], $oldvalues[$key]);
        } elseif (in_array($key, $field_array6) && $val !== '') {
            $field6[substr($key,4)] = $val; unset($post[$key], $oldvalues[$key]);
        } elseif (in_array($key, $field_array7) && $val !== '') {
            $field7[substr($key,4)] = $val; unset($post[$key], $oldvalues[$key]);
        } elseif (in_array($key, $field_array8) && $val !== '') {
            $field8[substr($key,4)] = $val; unset($post[$key], $oldvalues[$key]);
        }
    }

    // Serialize arrays for DB
    $sql_update_parts['citytext'] = !empty($field2) ? serialize($field2) : '';
    $sql_update_parts['stabletext'] = !empty($field3) ? serialize($field3) : '';
    $sql_update_parts['armortext'] = !empty($field4) ? serialize($field4) : '';
    $sql_update_parts['weaponstext'] = !empty($field5) ? serialize($field5) : '';
    $sql_update_parts['mercenarycamptext'] = !empty($field6) ? serialize($field6) : '';
    $sql_update_parts['cityblockmods'] = !empty($field7) ? serialize($field7) : '';
    $sql_update_parts['cityblocknavs'] = !empty($field8) ? serialize($field8) : '';

    $db->update(db_prefix('cities'), $sql_update_parts, ['cityid' => $cityid]);

    output(db_affected_rows() > 0 ? '`@City was successfully updated!`n' : '`$City was not updated as nothing was changed!`n');

    // Handle module object prefs
    foreach ($module_array as $modulename) {
        $len = strlen($modulename);
        foreach ($post as $key => $val) {
            if (substr($key,0,$len) === $modulename && isset($oldvalues[$key]) && $oldvalues[$key] != $val) {
                $keyname = substr($key, $len+1);
                set_module_objpref('city', $cityid, $keyname, $val, $modulename);
                gamelog("`7Module: `&$modulename `7Setting: `&$keyname `7ObjectID: `&$cityid `7Value changed from '`&{$oldvalues[$key]}`7' to '`&$val`7'`0","cities");
            }
        }
    }

} else {
    // New city
    unset($post['oldvalues'], $post['cityid'], $post['addnew']);

    $cols = [];
    $vals = [];

    foreach ($post as $key => $val) {
        if (in_array($key, $field_array)) { $cols[$key] = $val; unset($post[$key]); }
        elseif (in_array($key, $field_array2) && $val !== '') { $field2[substr($key,4)] = $val; unset($post[$key]); }
        elseif (in_array($key, $field_array3) && $val !== '') { $field3[substr($key,4)] = $val; unset($post[$key]); }
        elseif (in_array($key, $field_array4) && $val !== '') { $field4[substr($key,3)] = $val; unset($post[$key]); }
        elseif (in_array($key, $field_array5) && $val !== '') { $field5[substr($key,4)] = $val; unset($post[$key]); }
        elseif (in_array($key, $field_array6) && $val !== '') { $field6[substr($key,4)] = $val; unset($post[$key]); }
        elseif (in_array($key, $field_array7) && $val !== '') { $field7[substr($key,4)] = $val; unset($post[$key]); }
        elseif (in_array($key, $field_array8) && $val !== '') { $field8[substr($key,4)] = $val; unset($post[$key]); }
    }

    // Serialize arrays
    $cols['citytext'] = !empty($field2) ? serialize($field2) : '';
    $cols['stabletext'] = !empty($field3) ? serialize($field3) : '';
    $cols['armortext'] = !empty($field4) ? serialize($field4) : '';
    $cols['weaponstext'] = !empty($field5) ? serialize($field5) : '';
    $cols['mercenarycamptext'] = !empty($field6) ? serialize($field6) : '';
    $cols['cityblockmods'] = !empty($field7) ? serialize($field7) : '';
    $cols['cityblocknavs'] = !empty($field8) ? serialize($field8) : '';

    $cityid = $db->insert(db_prefix('cities'), $cols);

    output(db_affected_rows() > 0 ? '`@City was successfully saved!`n' : '`$City was NOT saved!`n');

    // Module prefs
    foreach ($module_array as $modulename) {
        $len = strlen($modulename);
        foreach ($post as $key => $val) {
            if (substr($key,0,$len) === $modulename && $val !== '') {
                $keyname = substr($key,$len+1);
                set_module_objpref('city', $cityid, $keyname, $val, $modulename);
            }
        }
    }
}

// Invalidate cache
modulehook('cityinvalidatecache',['cityid'=>$cityid,'cityname'=>$_POST['cityname']]);

addnav('Editor');
addnav('Re-Edit City',$from.'&op=edit&cityid='.$cityid);
addnav('Add a City',$from.'&op=edit');
addnav('Main Page',$from);
?>