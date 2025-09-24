<?php
declare(strict_types=1);

use Lotgd\MySQL\Database;

/**
 * Module uninstall
 */
function city_creator_uninstall(): bool
{
    global $session;

    output("`n`c`b`Q'city_creator' Module Uninstalled`0`b`c");

    $city = getsetting('villagename', LOCATION_FIELDS);

    Database::query(
        'UPDATE ' . Database::prefix('accounts') . ' SET location = ? WHERE location != ?',
        [$city, $city]
    );

    $session['user']['location'] = $city;

    $sql    = 'SELECT cityid, cityname FROM ' . Database::prefix('cities');
    $result = Database::query($sql);

    while ($row = Database::fetchAssoc($result)) {
        modulehook('cityinvalidatecache', [
            'cityid'   => $row['cityid'],
            'cityname' => $row['cityname'],
        ]);
    }

    Database::query('DROP TABLE IF EXISTS ' . Database::prefix('cities'));

    return true;
}

/**
 * Get city by ID or name
 */
function city_creator_getcity(int|string $city = 0): ?array
{
    global $citycreator_citydata;

    if (isset($citycreator_citydata['cityname'])
        && ($citycreator_citydata['cityname'] === $city || (string) $citycreator_citydata['cityid'] === (string) $city)
    ) {
        return $citycreator_citydata;
    }

    $where = false;
    $param = null;

    if (is_int($city) && $city > 0) {
        $where = 'cityid = ?';
        $param = $city;
    } elseif (is_string($city)) {
        $where = 'cityname = ? LIMIT 1';
        $param = $city;
    }

    if ($where) {
        $sql = 'SELECT * FROM ' . Database::prefix('cities') . ' WHERE ' . $where;

        $result = Database::queryCached(
            $sql,
            'city_cityid_' . (string) $city,
            86400,
            [$param]
        );

        $citycreator_citydata = Database::fetchAssoc($result);
    }

    return $citycreator_citydata ?? null;
}

/**
 * Example hook handler
 */
function city_creator_dohook(string $hookname, array $args): array
{
    switch ($hookname) {
        case 'villagetext':
            $city = city_creator_getcity($args['city'] ?? 0);

            if ($city && !empty($city['cityname'])) {
                $args['text'] .= "`n`&This is part of the city of `^{$city['cityname']}`&.`0";
            }
            break;
    }

    return $args;
}