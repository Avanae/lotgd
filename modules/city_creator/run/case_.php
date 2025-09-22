<?php
use Lotgd\MySQL\Database;

addnav('Editor');
addnav('Add a City', $from . '&op=edit');

switch ($sop) {
    case 'del':
        $cityidEsc = (int)$cityid;
        $sql = "SELECT cityname FROM " . Database::prefix('cities') . " WHERE cityid = $cityidEsc";
        $result = Database::query($sql);
        $row = Database::fetchAssoc($result);

        $capital = Database::escape(getsetting('villagename', LOCATION_FIELDS));
        $cityNameEsc = Database::escape($row['cityname']);
        Database::query("UPDATE " . Database::prefix('accounts') . " SET location = '$capital' WHERE location = '$cityNameEsc'");
        Database::query("DELETE FROM " . Database::prefix('cities') . " WHERE cityid = $cityidEsc");

        if (Database::numRows($result) > 0) {
            output('`n`@City successfully deleted.`0`n`n');
            modulehook('cityinvalidatecache', ['cityid' => $cityid, 'cityname' => $row['cityname']]);
            modulehook('citydeleted', ['cityid' => $cityid, 'cityname' => $row['cityname']]);
            module_delete_objprefs('cities', $cityid);
        } else {
            Database::query("UPDATE " . Database::prefix('cities') . " SET cityactive = 0 WHERE cityid = $cityidEsc");
            modulehook('cityinvalidatecache', ['cityid' => $cityid, 'cityname' => $row['cityname']]);
            output('`n`$City `#%s `$was not deleted because: `&%s`$, deactivated instead.`0`n`n', $row['cityname'], Database::error());
        }
        break;

    case 'activate':
        Database::query("UPDATE " . Database::prefix('cities') . " SET cityactive = 1 WHERE cityid = " . (int)$cityid);
        $row = Database::fetchAssoc(Database::query("SELECT cityname FROM " . Database::prefix('cities') . " WHERE cityid = " . (int)$cityid));
        modulehook('cityinvalidatecache', ['cityid' => $cityid, 'cityname' => $row['cityname']]);
        output('`n`2City `#%s `2has been `@Activated`2.`0`n`n', $row['cityname']);
        break;

    case 'deactivate':
        Database::query("UPDATE " . Database::prefix('cities') . " SET cityactive = 0 WHERE cityid = " . (int)$cityid);
        $row = Database::fetchAssoc(Database::query("SELECT cityname FROM " . Database::prefix('cities') . " WHERE cityid = " . (int)$cityid));
        modulehook('cityinvalidatecache', ['cityid' => $cityid, 'cityname' => $row['cityname']]);
        output('`n`2City `#%s `2has been `@Deactivated`2.`0`n`n', $row['cityname']);
        break;
}

// Translations
$opshead = translate_inline('Ops');
$id = translate_inline('City ID');
$name = translate_inline('City Name');
$routes = translate_inline('Routes');
$travel = translate_inline('Travel To');
$traveltype = translate_inline(['Safe','Dangerous','Off']);
$requirements = translate_inline('Requirements');
$author = translate_inline('Author');
$activity = translate_inline('Activity');
$edit = translate_inline('Edit');
$del = translate_inline('Del');
$deac = translate_inline('Deactivate');
$act = translate_inline('Activate');
$visit = translate_inline('Visit');
$yesno = translate_inline(['`@Yes','`$No']);
$conf = translate_inline('This city was installed by another module, to remove it please uninstall the module!');
$conf2 = translate_inline('Are you sure you wish to delete this city? Any object prefs will also be deleted!');

$city_routes_active = is_module_active('city_routes');

if ($city_routes_active) {
    $result = Database::query("SELECT cityname FROM " . Database::prefix('cities') . " WHERE cityactive = 1");
    $active_cities = [];
    while ($row = Database::fetchAssoc($result)) {
        $active_cities[] = $row['cityname'];
    }
}

$order = httpget('order');
$order2 = ($order == 1) ? 'DESC' : 'ASC';
$sortby = httpget('sortby');
$orderby = 'cityname ' . $order2;
if ($sortby == 'cityid') $orderby = 'cityid ' . $order2;

addnav('', $from . '&sortby=cityid&order=' . ($sortby == 'cityid' ? !$order : 1));
addnav('', $from . '&sortby=cityname&order=' . ($sortby == 'cityname' ? !$order : 1));

$result = Database::query("SELECT cityid, cityname, cityactive, cityauthor, citytravel FROM " . Database::prefix('cities') . " ORDER BY $orderby");

if (Database::numRows($result) > 0) {
    rawoutput('<table border="0" cellpadding="2" cellspacing="1" bgcolor="#999999" align="center">');
    rawoutput("<tr class=\"trhead\"><td>$opshead</td><td align=\"center\"><a href=\"$from&sortby=cityid&order=" . ($sortby=='cityid'?!$order:1) . "\">$id</a></td><td align=\"center\"><a href=\"$from&sortby=cityname&order=" . ($sortby=='cityname'?!$order:1) . "\">$name</a></td><td align=\"center\">$travel</td>");
    if ($city_routes_active) rawoutput("<td align=\"center\">$routes</td>");
    rawoutput("<td align=\"center\">$requirements</td><td align=\"center\">$author</td></tr>");

    $i = 0;
    while ($row = Database::fetchAssoc($result)) {
        rawoutput('<tr class="'.($i%2?'trlight':'trdark').'">');
        rawoutput('<td align="center" nowrap="nowrap">[ <a href="'.$from.'&op=edit&cityid='.$row['cityid'].'">'.$edit.'</a> |');
        addnav('', $from.'&op=edit&cityid='.$row['cityid']);

        if ($row['cityactive'] == 1) {
            rawoutput('<a href="'.$from.'&sop=deactivate&cityid='.$row['cityid'].'">'.$deac.'</a>');
            addnav('', $from.'&sop=deactivate&cityid='.$row['cityid']);
        } else {
            $delConfirm = $row['module'] ? $conf : $conf2;
            rawoutput('<a href="'.$from.'&sop=del&cityid='.$row['cityid'].'" onClick="return confirm(\''.$delConfirm.'\');">'.$del.'</a> |');
            addnav('', $from.'&sop=del&cityid='.$row['cityid']);

            rawoutput('<a href="'.$from.'&sop=activate&cityid='.$row['cityid'].'">'.$act.'</a>');
            addnav('', $from.'&sop=activate&cityid='.$row['cityid']);
        }

        rawoutput(' | <a href="runmodule.php?module=cities&op=travel&city='.$row['cityname'].'&su=1">'.$visit.'</a>');
        addnav('', 'runmodule.php?module=cities&op=travel&city='.$row['cityname'].'&su=1');
        rawoutput(' ]</td><td align="center">'.$row['cityid'].'</td><td align="center">');
        output_notl('%s', $row['cityname']);
        rawoutput('</td><td align="center">'.$traveltype[$row['citytravel']].'</td>');

        if ($city_routes_active) {
            rawoutput('<td align="center">');
            $result2 = Database::queryCached("SELECT value FROM " . Database::prefix('module_objprefs') . " WHERE modulename = 'city_routes' AND objtype = 'city' AND setting = 'routes' AND objid = '{$row['cityid']}'", 'city_routes-'.$row['cityid'], 86400);
            $row2 = Database::fetchAssoc($result2);
            $routes = $row2['value'] ?? '';
            if ($routes != '') {
                $routes = explode(',', $routes);
                foreach ($routes as $route) {
                    output_notl(in_array($route, $active_cities) ? '`@%s`n' : '`$%s`n', $route);
                }
            } else {
                output('`&All');
            }
            rawoutput('</td>');
        }

        rawoutput('<td>');
        modulehook('cityrequirements', ['cityid' => $row['cityid']]);
        rawoutput('</td><td align="center">');
        output_notl('%s', $row['cityauthor']);
        rawoutput('</td></tr>');
        $i++;
    }
    rawoutput('</table><br />');

    		if( $city_routes_active == TRUE )
		{
			output('`&`bCity Routes:`b `7Green means the city is active, red inactive.`0`n`n');
		}

		output('`2If you wish to delete a city, you have to deactivate it first. If there is anyone in this city when it is deleted then they will be transported to the Capital, %s.`n`n', getsetting('villagename', LOCATION_FIELDS));
		output('The city ID is unique to each city. If you change it then just remember that any object prefs assigned to it wont show.');
	}
	else
	{
		output('`n`3There are no cities installed, how about adding a few?`n`n');
	}