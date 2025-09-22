<?php

declare(strict_types=1);

use Lotgd\MySQL\Database;
use Lotgd\Page\Header;
use Lotgd\Page\Footer;
use Lotgd\Nav\VillageNav;
use Lotgd\Nav;
use Lotgd\Http;

/**
 * Module info
 */
function city_creator_getmoduleinfo()
{
    return [
        "name" => "City Creator",
        "description" => "Create and manage all custom cities.",
        "version" => "1.0.3",
        "author" => "`@MarcTheSlayer",
        "category" => "Cities",
        "settings" => [
            "Settings,title",
            "Enable creation of cities?,bool|1",
        ],
    ];
}

/**
 * Install the module
 */
function city_creator_install()
{
    // Create table if not exists
    $sql = "CREATE TABLE IF NOT EXISTS " . Database::prefix("cities") . " (
        cityid INT AUTO_INCREMENT PRIMARY KEY,
        cityname VARCHAR(255) NOT NULL,
        cityauthor VARCHAR(255) NOT NULL,
        citytype VARCHAR(50) DEFAULT 'Village',
        cityactive TINYINT(1) DEFAULT 1,
        module VARCHAR(255) DEFAULT ''
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    Database::query($sql);

    return true;
}

/**
 * Uninstall the module
 */
function city_creator_uninstall()
{
    $sql = "DROP TABLE IF EXISTS " . Database::prefix("cities");
    Database::query($sql);

    return true;
}

/**
 * Get city by ID or name
 */
function city_creator_getcity($city = null)
{
    static $cache = [];

    if (!$city) {
        return null;
    }

    // Use cached result if available
    if (isset($cache[$city])) {
        return $cache[$city];
    }

    $where = "";
    $params = [];

    if (is_numeric($city)) {
        $where = "cityid = :city";
        $params[':city'] = (int)$city;
    } elseif (is_string($city)) {
        $where = "cityname = :city LIMIT 1";
        $params[':city'] = $city;
    }

    $sql = "SELECT * FROM " . Database::prefix("cities") . " WHERE $where";
    $result = Database::query($sql, $params);
    $cityData = Database::fetchAssoc($result);

    $cache[$city] = $cityData;

    return $cityData;
}

/**
 * Get all cities
 */
function city_creator_getall()
{
    $sql = "SELECT * FROM " . Database::prefix("cities") . " ORDER BY cityname";
    $result = Database::query($sql);
    $cities = [];

    while ($row = Database::fetchAssoc($result)) {
        $cities[] = $row;
    }

    return $cities;
}

/**
 * Add a new city
 */
function city_creator_addcity(array $data)
{
    $sql = "INSERT INTO " . Database::prefix("cities") . " 
        (cityname, cityauthor, citytype, cityactive, module)
        VALUES (:cityname, :cityauthor, :citytype, :cityactive, :module)";
    
    $params = [
        ':cityname' => $data['cityname'],
        ':cityauthor' => $data['cityauthor'],
        ':citytype' => $data['citytype'] ?? 'Village',
        ':cityactive' => $data['cityactive'] ?? 1,
        ':module' => $data['module'] ?? '',
    ];

    Database::query($sql, $params);
}

/**
 * Delete a city
 */
function city_creator_deletecity($cityid)
{
    $sql = "DELETE FROM " . Database::prefix("cities") . " WHERE cityid = :cityid";
    Database::query($sql, [':cityid' => (int)$cityid]);
}

/**
 * Module run (example for displaying cities)
 */
function city_creator_run()
{
    Header::pageHeader("City Creator");

    $cities = city_creator_getall();
    rawoutput("<table border='0' cellpadding='2'>");
    rawoutput("<tr class='trhead'><td>ID</td><td>Name</td><td>Author</td><td>Type</td></tr>");
    $i = 0;
    foreach ($cities as $city) {
        rawoutput("<tr class='" . ($i % 2 == 0 ? "trlight" : "trdark") . "'>");
        rawoutput("<td>{$city['cityid']}</td>");
        rawoutput("<td>{$city['cityname']}</td>");
        rawoutput("<td>{$city['cityauthor']}</td>");
        rawoutput("<td>{$city['citytype']}</td>");
        rawoutput("</tr>");
        $i++;
    }
    rawoutput("</table>");

    VillageNav::render();
    Footer::pageFooter();
}