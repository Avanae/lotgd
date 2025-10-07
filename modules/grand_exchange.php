<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use Lotgd\Http;
use Lotgd\MySQL\Database;
use Lotgd\Nav;
use Lotgd\Output;
use Lotgd\Translator;

const GRAND_EXCHANGE_MODULE_NAME = 'grand_exchange';
const GRAND_EXCHANGE_TABLE_OFFERS = 'module_grand_exchange_offers';
const GRAND_EXCHANGE_TABLE_ITEMS = 'module_grand_exchange_offer_items';
const GRAND_EXCHANGE_ITEM_IMAGE_DIR = 'grand_exchange/items';

function grand_exchange_getmoduleinfo(): array
{
    return [
        'name' => 'Grand Exchange',
        'version' => '1.0.1',
        'author' => '`2Legend Continuum Team`0',
        'category' => 'Market',
        'download' => 'core_module',
        'description' => 'Player marketplace for posting buy and sell offers that trade directly with other adventurers.',
        'settings' => [
            'Grand Exchange Settings,title',
            'offer_limit' => 'Maximum active buy or sell offers per player,int|8',
            'transaction_tax_percent' => 'Percent fee removed from successful trades,int|0',
        ],
    ];
}
function grand_exchange_install(): bool
{
    grand_exchange_ensure_schema();
    module_addhook('village');

    return true;
}

function grand_exchange_uninstall(): bool
{
    return true;
}

function grand_exchange_dohook(string $hookName, array $args): array
{
    if ($hookName === 'village') {
        $header = $args['marketnav'] ?? 'Market Square';
        $schema = $args['schemas']['marketnav'] ?? null;

        $navigation = Nav::getInstance();
        $previousSection = $navigation->getNavSection();

        if ($header !== '') {
            $navigation->setNavSection($header);
        }

        if ($schema) {
            Translator::tlschema($schema);
        }

        addnav('Grand Exchange', 'runmodule.php?module=grand_exchange');

        if ($schema) {
            Translator::tlschema();
        }

        if ($previousSection !== '') {
            $navigation->setNavSection($previousSection);
        }
    }

    return $args;
}

function grand_exchange_run(): void
{
    global $session;

    $userId = (int) ($session['user']['acctid'] ?? 0);
    $op = (string) (Http::get('op') ?: '');
    $offerId = (int) (Http::get('offer') ?: 0);
    $actionParam = (string) (Http::get('action') ?: '');

    grand_exchange_ensure_schema();
    grand_exchange_register_current_route($op, $offerId, $actionParam);

    page_header('The Grand Exchange');

    addnav('Navigation');
    addnav('Back to the Village', 'village.php');
    addnav('Overview', 'runmodule.php?module=grand_exchange');
    addnav('Create a Buy Offer', 'runmodule.php?module=grand_exchange&op=create_buy');

    if (grand_exchange_is_inventory_available()) {
        addnav('Create a Sell Offer', 'runmodule.php?module=grand_exchange&op=create_sell');
    }

    addnav('Manage My Offers', 'runmodule.php?module=grand_exchange&op=manage');

    switch ($op) {
        case 'create_buy':
            if (Http::post('create_buy_offer') !== false) {
                grand_exchange_process_buy_form($userId);
            }

            grand_exchange_render_buy_interface($userId);
            break;

        case 'create_sell':
            if (Http::post('create_sell_offer') !== false) {
                grand_exchange_process_sell_form($userId);
            }

            grand_exchange_render_sell_interface($userId);
            break;

        case 'manage':
            if ($actionParam === 'cancel' && $offerId > 0) {
                grand_exchange_cancel_offer($userId, $offerId);
            }

            grand_exchange_render_manage_interface($userId);
            break;

        case 'accept_sell':
            if ($offerId > 0 && Http::post('execute_purchase') !== false) {
                $quantity = max(1, (int) Http::post('quantity'));
                grand_exchange_accept_sell_offer($userId, $offerId, $quantity);
            }

            grand_exchange_render_market_overview($userId);
            break;

        case 'fulfill_buy':
            if ($offerId > 0 && Http::post('execute_fulfill') !== false) {
                $quantity = max(1, (int) Http::post('quantity'));
                grand_exchange_fulfill_buy_offer($userId, $offerId, $quantity);
            }

            grand_exchange_render_market_overview($userId);
            break;

        default:
            grand_exchange_render_market_overview($userId);
            break;
    }

    page_footer();
}
function grand_exchange_create_schema(): void
{
    $offersTable = Database::prefix(GRAND_EXCHANGE_TABLE_OFFERS);
    $itemsTable = Database::prefix(GRAND_EXCHANGE_TABLE_ITEMS);
    $accountsTable = Database::prefix('accounts');
    $itemsCoreTable = Database::prefix('item');

    $offersSql = <<<SQL
CREATE TABLE IF NOT EXISTS `$offersTable` (
    `offer_id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `type` VARCHAR(8) NOT NULL,
    `item_id` INT UNSIGNED NOT NULL,
    `item_name` VARCHAR(255) NOT NULL,
    `quantity_total` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `quantity_remaining` SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    `price_gold` INT UNSIGNED NOT NULL DEFAULT 0,
    `price_gems` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `held_gold` INT UNSIGNED NOT NULL DEFAULT 0,
    `held_gems` INT UNSIGNED NOT NULL DEFAULT 0,
    `status` VARCHAR(16) NOT NULL DEFAULT 'active',
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`offer_id`),
    KEY `idx_user_status` (`user_id`, `status`),
    KEY `idx_item` (`item_id`),
    KEY `idx_type_status` (`type`, `status`),
    CONSTRAINT `fk_ge_user` FOREIGN KEY (`user_id`) REFERENCES `$accountsTable` (`acctid`) ON DELETE CASCADE,
    CONSTRAINT `fk_ge_item` FOREIGN KEY (`item_id`) REFERENCES `$itemsCoreTable` (`itemid`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

    $itemsSql = <<<SQL
CREATE TABLE IF NOT EXISTS `$itemsTable` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `offer_id` INT UNSIGNED NOT NULL,
    `specialvalue` TEXT NOT NULL,
    `sellvaluegold` INT UNSIGNED NOT NULL DEFAULT 0,
    `sellvaluegems` INT UNSIGNED NOT NULL DEFAULT 0,
    `charges` INT NOT NULL DEFAULT 0,
    PRIMARY KEY (`id`),
    KEY `idx_offer` (`offer_id`),
    CONSTRAINT `fk_ge_offer_items` FOREIGN KEY (`offer_id`) REFERENCES `$offersTable` (`offer_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL;

    Database::query($offersSql);
    Database::query($itemsSql);
}

function grand_exchange_ensure_schema(): void
{
    $offersTable = Database::prefix(GRAND_EXCHANGE_TABLE_OFFERS);
    $itemsTable = Database::prefix(GRAND_EXCHANGE_TABLE_ITEMS);

    if (! Database::tableExists($offersTable) || ! Database::tableExists($itemsTable)) {
        grand_exchange_create_schema();
    }
}

function grand_exchange_register_current_route(string $op, int $offerId, string $actionParam): void
{
    $base = 'runmodule.php?module=' . GRAND_EXCHANGE_MODULE_NAME;

    addnav('', $base);

    $query = [];

    if ($op !== '') {
        $query[] = 'op=' . rawurlencode($op);
    }

    if ($offerId > 0) {
        $query[] = 'offer=' . $offerId;
    }

    if ($actionParam !== '') {
        $query[] = 'action=' . rawurlencode($actionParam);
    }

    if ($query !== []) {
        addnav('', $base . '&' . implode('&', $query));
    }
}
function grand_exchange_is_inventory_available(): bool
{
    return function_exists('is_module_active') ? is_module_active('inventory') : false;
}

function grand_exchange_ensure_inventory_library(): void
{
    static $loaded = false;

    if ($loaded || ! grand_exchange_is_inventory_available()) {
        return;
    }

    require_once 'modules/inventory/lib/itemhandler.php';
    $loaded = true;
}

function grand_exchange_get_offer_limit(): int
{
    $limit = (int) get_module_setting('offer_limit', GRAND_EXCHANGE_MODULE_NAME);

    return $limit > 0 ? $limit : 8;
}

function grand_exchange_get_tax_percent(): int
{
    $tax = (int) get_module_setting('transaction_tax_percent', GRAND_EXCHANGE_MODULE_NAME);

    return max(0, min(100, $tax));
}

function grand_exchange_count_active_offers(int $userId): int
{
    if ($userId <= 0) {
        return 0;
    }

    $table = Database::prefix(GRAND_EXCHANGE_TABLE_OFFERS);
    $sql = sprintf(
        "SELECT COUNT(*) AS `total` FROM `%s` WHERE `user_id` = %d AND `status` = 'active'",
        $table,
        $userId
    );

    $result = Database::query($sql);
    $row = Database::fetchAssoc($result);

    return (int) ($row['total'] ?? 0);
}

function grand_exchange_load_offer(int $offerId): ?array
{
    if ($offerId <= 0) {
        return null;
    }

    $table = Database::prefix(GRAND_EXCHANGE_TABLE_OFFERS);
    $sql = sprintf('SELECT * FROM `%s` WHERE `offer_id` = %d', $table, $offerId);
    $result = Database::query($sql);
    $row = Database::fetchAssoc($result);

    return is_array($row) ? $row : null;
}

function grand_exchange_begin_transaction(): ?Connection
{
    try {
        $connection = Database::getDoctrineConnection();

        if (! $connection->isTransactionActive()) {
            $connection->beginTransaction();
            return $connection;
        }

        return null;
    } catch (Throwable $throwable) {
        Output::getInstance()->debug('Grand Exchange transaction error: ' . $throwable->getMessage());
        return null;
    }
}

function grand_exchange_commit_transaction(?Connection $connection): void
{
    if ($connection instanceof Connection && $connection->isTransactionActive()) {
        $connection->commit();
    }
}

function grand_exchange_rollback_transaction(?Connection $connection): void
{
    if ($connection instanceof Connection && $connection->isTransactionActive()) {
        $connection->rollBack();
    }
}

function grand_exchange_sanitize(string $value): string
{
    return htmlentities($value, ENT_QUOTES, 'UTF-8');
}

function grand_exchange_format_currency(int $gold, int $gems): string
{
    $parts = [];

    if ($gold > 0) {
        $parts[] = number_format($gold) . ' gold';
    }

    if ($gems > 0) {
        $parts[] = number_format($gems) . ' gems';
    }

    return $parts === [] ? '0' : implode(', ', $parts);
}
function grand_exchange_build_image_name_candidates(string $itemName): array
{
    $trimmed = trim($itemName);

    if ($trimmed === '') {
        return [];
    }

    $lower = strtolower($trimmed);
    $normalized = preg_replace("/[\"'`]+/", '', $lower) ?? $lower;

    $candidates = [
        $trimmed,
        $lower,
        str_replace(' ', '-', $trimmed),
        str_replace(' ', '_', $trimmed),
        str_replace(' ', '-', $lower),
        str_replace(' ', '_', $lower),
        str_replace(' ', '-', $normalized),
        str_replace(' ', '_', $normalized),
    ];

    $slug = preg_replace('/[^a-z0-9]+/i', '-', $normalized);

    if ($slug !== null) {
        $slug = trim($slug, '-');

        if ($slug !== '') {
            $candidates[] = $slug;
            $candidates[] = str_replace('-', '_', $slug);
            $candidates[] = str_replace('-', '', $slug);
        }
    }

    $unique = [];

    foreach ($candidates as $candidate) {
        $candidate = trim($candidate);

        if ($candidate === '') {
            continue;
        }

        $unique[$candidate] = true;
    }

    return array_keys($unique);
}

function grand_exchange_get_item_image_html(string $itemName): string
{
    static $cache = [];

    if ($itemName === '') {
        return '';
    }

    if (array_key_exists($itemName, $cache)) {
        return $cache[$itemName];
    }

    $baseFs = dirname(__DIR__) . DIRECTORY_SEPARATOR . GRAND_EXCHANGE_ITEM_IMAGE_DIR;

    if (! is_dir($baseFs)) {
        return $cache[$itemName] = '';
    }

    $candidates = grand_exchange_build_image_name_candidates($itemName);
    $extensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

    foreach ($candidates as $candidate) {
        foreach ($extensions as $extension) {
            $filename = $candidate . '.' . $extension;
            $path = $baseFs . DIRECTORY_SEPARATOR . $filename;

            if (is_file($path)) {
                $src = GRAND_EXCHANGE_ITEM_IMAGE_DIR . '/' . $filename;

                return $cache[$itemName] = sprintf(
                    '<img src="%s" alt="%s" class="grand-exchange-item-image">',
                    grand_exchange_sanitize($src),
                    grand_exchange_sanitize($itemName)
                );
            }
        }
    }

    return $cache[$itemName] = '';
}

function grand_exchange_output_item_image_styles(): void
{
    static $printed = false;

    if ($printed) {
        return;
    }

    $printed = true;

      rawoutput('<style>.grand-exchange-item-image{width:24px;height:24px;object-fit:contain;margin-right:4px;vertical-align:middle;}.grand-exchange-sell-preview{display:inline-flex;align-items:center;margin-bottom:6px;font-weight:bold;}</style>');
}

function grand_exchange_render_item_label(string $itemName): string
{
    $image = grand_exchange_get_item_image_html($itemName);
    $label = grand_exchange_sanitize($itemName);

    if ($image !== '') {
        grand_exchange_output_item_image_styles();

        return $image . ' ' . $label;
    }

    return $label;
}
function grand_exchange_render_market_overview(int $userId): void
{
    $limit = grand_exchange_get_offer_limit();
    $active = $userId > 0 ? grand_exchange_count_active_offers($userId) : 0;

    output('`b`@Grand Exchange Listings`b`0`n');
    output('You may maintain up to `b%s`b active offers. You currently have `b%s`b.`0`n`n', $limit, $active);

    grand_exchange_render_sell_offers_board($userId);
    output('`n');
    grand_exchange_render_buy_offers_board($userId);
}

function grand_exchange_render_sell_offers_board(int $viewerId): void
{
    output('`b`@Items for Sale`b`0`n');

    $offersTable = Database::prefix(GRAND_EXCHANGE_TABLE_OFFERS);
    $accountsTable = Database::prefix('accounts');

    $conditions = "o.`type` = 'sell' AND o.`status` = 'active' AND o.`quantity_remaining` > 0";

    if ($viewerId > 0) {
        $conditions .= sprintf(' AND o.`user_id` <> %d', $viewerId);
    }

    $sql = sprintf(
        'SELECT o.`offer_id`, o.`item_name`, o.`price_gold`, o.`price_gems`, o.`quantity_remaining`, o.`quantity_total`, o.`updated_at`, a.`name` AS `owner_name`
         FROM `%s` AS o
         INNER JOIN `%s` AS a ON a.`acctid` = o.`user_id`
         WHERE %s
         ORDER BY o.`updated_at` ASC
         LIMIT 25',
        $offersTable,
        $accountsTable,
        $conditions
    );

    $result = Database::query($sql);
    $rows = [];

    while ($row = Database::fetchAssoc($result)) {
        if (! is_array($row)) {
            break;
        }

        $rows[] = $row;
    }

    if ($rows === []) {
        output('`7No player-driven sell offers are available at the moment.`0`n');
        return;
    }

    rawoutput('<table class="grand-exchange-table" cellspacing="1" cellpadding="3" border="0">');
    rawoutput('<tr class="trhead"><th>Item</th><th>Seller</th><th>Qty</th><th>Price (per)</th><th>Action</th></tr>');

    $rowIndex = 0;

    foreach ($rows as $row) {
        $rowClass = $rowIndex++ % 2 ? 'trdark' : 'trlight';
        $offerId = (int) $row['offer_id'];
        $maxQuantity = (int) $row['quantity_remaining'];
        $action = sprintf('runmodule.php?module=grand_exchange&op=accept_sell&offer=%d', $offerId);

        rawoutput(sprintf('<tr class="%s">', $rowClass));
        rawoutput('<td>' . grand_exchange_render_item_label($row['item_name']) . '</td>');
        rawoutput('<td>' . grand_exchange_sanitize($row['owner_name']) . '</td>');
        rawoutput('<td>' . $maxQuantity . '/' . (int) $row['quantity_total'] . '</td>');
        rawoutput('<td>' . grand_exchange_format_currency((int) $row['price_gold'], (int) $row['price_gems']) . '</td>');

        if ($viewerId > 0) {
            rawoutput('<td>');
            rawoutput(sprintf("<form method='POST' action='%s'>", $action));
            addnav('', $action);
            rawoutput("<input type='hidden' name='execute_purchase' value='1'>");
            rawoutput(sprintf("<input type='number' name='quantity' min='1' max='%d' value='1'> ", $maxQuantity));
            rawoutput("<button type='submit'>Buy</button>");
            rawoutput('</form>');
            rawoutput('</td>');
        } else {
            rawoutput('<td>Login to trade</td>');
        }

        rawoutput('</tr>');
    }

    rawoutput('</table>');
}

function grand_exchange_render_buy_offers_board(int $viewerId): void
{
    output('`b`@Active Buy Requests`b`0`n');

    $offersTable = Database::prefix(GRAND_EXCHANGE_TABLE_OFFERS);
    $accountsTable = Database::prefix('accounts');

    $conditions = "o.`type` = 'buy' AND o.`status` = 'active' AND o.`quantity_remaining` > 0";

    if ($viewerId > 0) {
        $conditions .= sprintf(' AND o.`user_id` <> %d', $viewerId);
    }

    $sql = sprintf(
        'SELECT o.`offer_id`, o.`item_name`, o.`price_gold`, o.`price_gems`, o.`quantity_remaining`, o.`quantity_total`, o.`updated_at`, a.`name` AS `owner_name`
         FROM `%s` AS o
         INNER JOIN `%s` AS a ON a.`acctid` = o.`user_id`
         WHERE %s
         ORDER BY o.`updated_at` ASC
         LIMIT 25',
        $offersTable,
        $accountsTable,
        $conditions
    );

    $result = Database::query($sql);
    $rows = [];

    while ($row = Database::fetchAssoc($result)) {
        if (! is_array($row)) {
            break;
        }

        $rows[] = $row;
    }

    if ($rows === []) {
        output('`7No outstanding buy requests are available right now.`0`n');
        return;
    }

    $inventoryActive = grand_exchange_is_inventory_available();

    rawoutput('<table class="grand-exchange-table" cellspacing="1" cellpadding="3" border="0">');
    rawoutput('<tr class="trhead"><th>Item</th><th>Buyer</th><th>Qty</th><th>Price (per)</th><th>Action</th></tr>');

    $rowIndex = 0;

    foreach ($rows as $row) {
        $rowClass = $rowIndex++ % 2 ? 'trdark' : 'trlight';
        $offerId = (int) $row['offer_id'];
        $maxQuantity = (int) $row['quantity_remaining'];
        $action = sprintf('runmodule.php?module=grand_exchange&op=fulfill_buy&offer=%d', $offerId);

        rawoutput(sprintf('<tr class="%s">', $rowClass));
        rawoutput('<td>' . grand_exchange_render_item_label($row['item_name']) . '</td>');
        rawoutput('<td>' . grand_exchange_sanitize($row['owner_name']) . '</td>');
        rawoutput('<td>' . $maxQuantity . '/' . (int) $row['quantity_total'] . '</td>');
        rawoutput('<td>' . grand_exchange_format_currency((int) $row['price_gold'], (int) $row['price_gems']) . '</td>');

        if ($viewerId > 0 && $inventoryActive) {
            rawoutput('<td>');
            rawoutput(sprintf("<form method='POST' action='%s'>", $action));
            addnav('', $action);
            rawoutput("<input type='hidden' name='execute_fulfill' value='1'>");
            rawoutput(sprintf("<input type='number' name='quantity' min='1' max='%d' value='1'> ", $maxQuantity));
            rawoutput("<button type='submit'>Sell</button>");
            rawoutput('</form>');
            rawoutput('</td>');
        } elseif (! $inventoryActive) {
            rawoutput('<td>Inventory module required</td>');
        } else {
            rawoutput('<td>Login to trade</td>');
        }

        rawoutput('</tr>');
    }

    rawoutput('</table>');
}
function grand_exchange_render_buy_interface(int $userId): void
{
    $limit = grand_exchange_get_offer_limit();
    $active = $userId > 0 ? grand_exchange_count_active_offers($userId) : 0;

    output('`b`@Create a Buy Offer`b`0`n');
    output('You may maintain up to `b%s`b active offers. You currently have `b%s`b.`0`n`n', $limit, $active);

    $search = (string) (Http::get('search') ?: '');
    $selectedItemId = (int) (Http::get('item') ?: 0);

    rawoutput("<form action='runmodule.php' method='GET'>");
    rawoutput("<input type='hidden' name='module' value='grand_exchange'>");
    rawoutput("<input type='hidden' name='op' value='create_buy'>");
    rawoutput(sprintf("<label>Search items: <input name='search' value='%s'></label> ", grand_exchange_sanitize($search)));
    rawoutput("<button type='submit'>Search</button>");
    rawoutput('</form><br>');

    if ($selectedItemId > 0) {
        grand_exchange_render_buy_creation_form($selectedItemId);
        return;
    }

    if ($search === '') {
        output('`7Search for an item above to start a buy offer.`0`n');
        return;
    }

    $itemsTable = Database::prefix('item');
    $searchEscaped = Database::escape($search);
    $sql = sprintf(
        "SELECT `itemid`, `name`, `description` FROM `%s` WHERE `name` LIKE '%%%s%%' ORDER BY `name` ASC LIMIT 25",
        $itemsTable,
        $searchEscaped
    );

    $result = Database::query($sql);
    $rows = [];

    while ($row = Database::fetchAssoc($result)) {
        if (! is_array($row)) {
            break;
        }

        $rows[] = $row;
    }

    if ($rows === []) {
        output('`$No items matched your search.`0`n');
        return;
    }

    rawoutput('<table class="grand-exchange-table" cellspacing="1" cellpadding="3" border="0">');
    rawoutput('<tr class="trhead"><th>Item</th><th>Description</th><th></th></tr>');

    $rowIndex = 0;

    foreach ($rows as $row) {
        $rowClass = $rowIndex++ % 2 ? 'trdark' : 'trlight';
        $itemId = (int) $row['itemid'];
        $url = sprintf('runmodule.php?module=grand_exchange&op=create_buy&item=%d', $itemId);

        rawoutput(sprintf('<tr class="%s">', $rowClass));
        rawoutput('<td>' . grand_exchange_render_item_label($row['name']) . '</td>');
        rawoutput('<td>' . grand_exchange_sanitize($row['description']) . '</td>');
        rawoutput('<td>');
        rawoutput(sprintf("<a href='%s'>Select</a>", $url));
        rawoutput('</td>');
        addnav('', $url);
        rawoutput('</tr>');
    }

    rawoutput('</table>');
}

function grand_exchange_render_buy_creation_form(int $itemId): void
{
    $item = grand_exchange_get_item_info($itemId);

    if (! $item) {
        output('`$That item could not be found.`0`n');
        return;
    }

    output('Creating a buy offer for `b%s`b.`0`n`n', grand_exchange_sanitize($item['name']));

    $action = 'runmodule.php?module=grand_exchange&op=create_buy';
    addnav('', $action);

    rawoutput(sprintf("<form method='POST' action='%s'>", $action));
    rawoutput("<input type='hidden' name='create_buy_offer' value='1'>");
    rawoutput(sprintf("<input type='hidden' name='item_id' value='%d'>", (int) $item['itemid']));
    rawoutput("<label>Quantity <input type='number' name='quantity' min='1' max='999' value='1'></label><br>");
    rawoutput(sprintf("<label>Gold per item <input type='number' name='price_gold' min='0' value='%d'></label><br>", (int) $item['gold']));
    rawoutput("<label>Gems per item <input type='number' name='price_gems' min='0' value='0'></label><br>");
    rawoutput("<button type='submit'>Post Buy Offer</button>");
    rawoutput('</form>');
}

function grand_exchange_get_item_info(int $itemId): ?array
{
    if ($itemId <= 0) {
        return null;
    }

    grand_exchange_ensure_inventory_library();

    if (function_exists('get_item')) {
        $item = get_item($itemId);

        return is_array($item) ? $item : null;
    }

    $itemsTable = Database::prefix('item');
    $sql = sprintf('SELECT * FROM `%s` WHERE `itemid` = %d', $itemsTable, $itemId);
    $result = Database::query($sql);
    $row = Database::fetchAssoc($result);

    return is_array($row) ? $row : null;
}
function grand_exchange_process_buy_form(int $userId): void
{
    global $session;

    if ($userId <= 0) {
        output('`$You must be logged in to create offers.`0`n');
        return;
    }

    $itemId = (int) Http::post('item_id');
    $quantity = max(1, (int) Http::post('quantity'));
    $priceGold = max(0, (int) Http::post('price_gold'));
    $priceGems = max(0, (int) Http::post('price_gems'));

    $item = grand_exchange_get_item_info($itemId);

    if (! $item) {
        output('`$The selected item no longer exists.`0`n');
        return;
    }

    if ($priceGold <= 0 && $priceGems <= 0) {
        output('`$You must offer at least some gold or gems for each item.`0`n');
        return;
    }

    $limit = grand_exchange_get_offer_limit();
    $active = grand_exchange_count_active_offers($userId);

    if ($active >= $limit) {
        output('`$You have reached the maximum number of active offers.`0`n');
        return;
    }

    $totalGold = $priceGold * $quantity;
    $totalGems = $priceGems * $quantity;

    if (($session['user']['gold'] ?? 0) < $totalGold || ($session['user']['gems'] ?? 0) < $totalGems) {
        output('`$You do not have enough funds to cover that offer.`0`n');
        return;
    }

    $connection = grand_exchange_begin_transaction();

    try {
        $session['user']['gold'] -= $totalGold;
        $session['user']['gems'] -= $totalGems;

        $offersTable = Database::prefix(GRAND_EXCHANGE_TABLE_OFFERS);
        $itemName = Database::escape($item['name']);

        $sql = sprintf(
            "INSERT INTO `%s` (`user_id`, `type`, `item_id`, `item_name`, `quantity_total`, `quantity_remaining`, `price_gold`, `price_gems`, `held_gold`, `held_gems`, `status`, `created_at`, `updated_at`)
             VALUES (%d, 'buy', %d, '%s', %d, %d, %d, %d, %d, %d, 'active', NOW(), NOW())",
            $offersTable,
            $userId,
            (int) $item['itemid'],
            $itemName,
            $quantity,
            $quantity,
            $priceGold,
            $priceGems,
            $totalGold,
            $totalGems
        );

        Database::query($sql);
        grand_exchange_commit_transaction($connection);

        output('`@Your buy offer has been posted to the market.`0`n');
        debuglog(sprintf('Placed buy offer for %d x %s at %d gold and %d gems each.', $quantity, $item['name'], $priceGold, $priceGems));
    } catch (Throwable $throwable) {
        grand_exchange_rollback_transaction($connection);
        $session['user']['gold'] += $totalGold;
        $session['user']['gems'] += $totalGems;
        output('`$Failed to create the buy offer.`0`n');
        Output::getInstance()->debug('Grand Exchange buy offer error: ' . $throwable->getMessage());
    }
}

function grand_exchange_get_available_inventory_quantity(int $userId, int $itemId): int
{
    if ($userId <= 0 || $itemId <= 0) {
        return 0;
    }

    $inventoryTable = Database::prefix('inventory');
    $sql = sprintf(
        "SELECT COUNT(*) AS `available`
         FROM `%s`
         WHERE `userid` = %d AND `itemid` = %d AND `equipped` = 0",
        $inventoryTable,
        $userId,
        $itemId
    );

    $result = Database::query($sql);
    $row = Database::fetchAssoc($result);

    return (int) ($row['available'] ?? 0);
}
function grand_exchange_process_sell_form(int $userId): void
{
    if ($userId <= 0) {
        output('`$You must be logged in to list items for sale.`0`n');
        return;
    }

    if (! grand_exchange_is_inventory_available()) {
        output('`$The inventory module must be active to sell items here.`0`n');
        return;
    }

    grand_exchange_ensure_inventory_library();

    $itemId = (int) Http::post('item_id');
    $quantity = max(1, (int) Http::post('quantity'));
    $priceGold = max(0, (int) Http::post('price_gold'));
    $priceGems = max(0, (int) Http::post('price_gems'));

    $item = grand_exchange_get_item_info($itemId);

    if (! $item) {
        output('`$The selected item was not found.`0`n');
        return;
    }

    if ($priceGold <= 0 && $priceGems <= 0) {
        output('`$You must set a price in gold, gems, or both to sell an item.`0`n');
        return;
    }

    $availableQuantity = grand_exchange_get_available_inventory_quantity($userId, $itemId);

    if ($availableQuantity < $quantity) {
        output('`$You only have %s unequipped copies of that item available.`0`n', $availableQuantity);
        return;
    }

    $limit = grand_exchange_get_offer_limit();
    $active = grand_exchange_count_active_offers($userId);

    if ($active >= $limit) {
        output('`$You have reached the maximum number of active offers.`0`n');
        return;
    }

    $inventoryTable = Database::prefix('inventory');
    $sql = sprintf(
        "SELECT `specialvalue`, `sellvaluegold`, `sellvaluegems`, `charges`
         FROM `%s`
         WHERE `userid` = %d AND `itemid` = %d AND `equipped` = 0
         LIMIT %d",
        $inventoryTable,
        $userId,
        $itemId,
        $quantity
    );

    $result = Database::query($sql);
    $reservedRows = [];

    while ($row = Database::fetchAssoc($result)) {
        if (! is_array($row)) {
            break;
        }

        $reservedRows[] = $row;
    }

    if (count($reservedRows) < $quantity) {
        output('`$You could not reserve enough unequipped copies of that item.`0`n');
        return;
    }

    $connection = grand_exchange_begin_transaction();

    try {
        $deleteSql = sprintf(
            "DELETE FROM `%s`
             WHERE `userid` = %d AND `itemid` = %d AND `equipped` = 0
             LIMIT %d",
            $inventoryTable,
            $userId,
            $itemId,
            $quantity
        );

        Database::query($deleteSql);

        if (Database::affectedRows() < $quantity) {
            throw new RuntimeException('Unable to remove items from your inventory.');
        }

        $offersTable = Database::prefix(GRAND_EXCHANGE_TABLE_OFFERS);
        $itemName = Database::escape($item['name']);

        $offerSql = sprintf(
            "INSERT INTO `%s` (`user_id`, `type`, `item_id`, `item_name`, `quantity_total`, `quantity_remaining`, `price_gold`, `price_gems`, `held_gold`, `held_gems`, `status`, `created_at`, `updated_at`)
             VALUES (%d, 'sell', %d, '%s', %d, %d, %d, %d, 0, 0, 'active', NOW(), NOW())",
            $offersTable,
            $userId,
            $itemId,
            $itemName,
            $quantity,
            $quantity,
            $priceGold,
            $priceGems
        );

        Database::query($offerSql);
        $offerId = (int) Database::insertId();

        $itemsTable = Database::prefix(GRAND_EXCHANGE_TABLE_ITEMS);

        foreach ($reservedRows as $row) {
            $special = Database::escape($row['specialvalue'] ?? '');
            $sellGold = max(0, (int) ($row['sellvaluegold'] ?? 0));
            $sellGems = max(0, (int) ($row['sellvaluegems'] ?? 0));
            $charges = max(0, (int) ($row['charges'] ?? 0));

            $reserveSql = sprintf(
                "INSERT INTO `%s` (`offer_id`, `specialvalue`, `sellvaluegold`, `sellvaluegems`, `charges`)
                 VALUES (%d, '%s', %d, %d, %d)",
                $itemsTable,
                $offerId,
                $special,
                $sellGold,
                $sellGems,
                $charges
            );

            Database::query($reserveSql);
        }

        invalidatedatacache("inventory-user-$userId");
        grand_exchange_commit_transaction($connection);

        output('`@Your items have been listed for sale on the Grand Exchange.`0`n');
        debuglog(sprintf('Listed %d x %s for sale at %d gold and %d gems each.', $quantity, $item['name'], $priceGold, $priceGems));
    } catch (Throwable $throwable) {
        grand_exchange_rollback_transaction($connection);
        grand_exchange_restore_inventory_items($userId, $itemId, $reservedRows);
        output('`$Failed to create the sell offer.`0`n');
        Output::getInstance()->debug('Grand Exchange sell offer error: ' . $throwable->getMessage());
    }
}

function grand_exchange_fetch_inventory_summary(int $userId, string $search): array
{
    $inventoryTable = Database::prefix('inventory');
    $itemTable = Database::prefix('item');

    $conditions = sprintf('WHERE inv.`userid` = %d', $userId);

    if ($search !== '') {
        $searchEscaped = Database::escape($search);
        $conditions .= sprintf(" AND itm.`name` LIKE '%%%s%%'", $searchEscaped);
    }

    $sql = sprintf(
        "SELECT itm.`itemid`, itm.`name`, itm.`description`,
                SUM(CASE WHEN inv.`equipped` = 0 THEN 1 ELSE 0 END) AS `available`
         FROM `%s` AS inv
         INNER JOIN `%s` AS itm ON itm.`itemid` = inv.`itemid`
         %s
         GROUP BY itm.`itemid`, itm.`name`, itm.`description`
         HAVING `available` > 0
         ORDER BY itm.`name` ASC",
        $inventoryTable,
        $itemTable,
        $conditions
    );

    $result = Database::query($sql);
    $items = [];

    while ($row = Database::fetchAssoc($result)) {
        if (! is_array($row)) {
            break;
        }

        $items[] = $row;
    }

    return $items;
}

function grand_exchange_render_sell_interface(int $userId): void
{
    if ($userId <= 0) {
        output('`$You must be logged in to list items for sale.`0`n');
        return;
    }

    if (! grand_exchange_is_inventory_available()) {
        output('`$The inventory module must be active to sell items here.`0`n');
        return;
    }

    $limit = grand_exchange_get_offer_limit();
    $active = grand_exchange_count_active_offers($userId);

    output('`b`@Create a Sell Offer`b`0`n');
    output('You may maintain up to `b%s`b active offers. You currently have `b%s`b.`0`n`n', $limit, $active);

    $search = (string) (Http::get('search') ?: '');
    $selectedItemId = (int) (Http::get('item') ?: 0);

    rawoutput("<form action='runmodule.php' method='GET'>");
    rawoutput("<input type='hidden' name='module' value='grand_exchange'>");
    rawoutput("<input type='hidden' name='op' value='create_sell'>");
    rawoutput(sprintf("<label>Filter by name: <input name='search' value='%s'></label> ", grand_exchange_sanitize($search)));
    rawoutput("<button type='submit'>Filter</button>");
    rawoutput('</form><br>');

    if ($selectedItemId > 0) {
        grand_exchange_render_sell_creation_form($userId, $selectedItemId);
        return;
    }

    $items = grand_exchange_fetch_inventory_summary($userId, $search);

    if ($items === []) {
        output('`$You have no unequipped items that match that filter.`0`n');
        return;
    }

    rawoutput('<table class="grand-exchange-table" cellspacing="1" cellpadding="3" border="0">');
    rawoutput('<tr class="trhead"><th>Item</th><th>Available</th><th>Description</th><th></th></tr>');

    $rowIndex = 0;

    foreach ($items as $row) {
        $rowClass = $rowIndex++ % 2 ? 'trdark' : 'trlight';
        $itemId = (int) $row['itemid'];
        $url = sprintf('runmodule.php?module=grand_exchange&op=create_sell&item=%d', $itemId);

        rawoutput(sprintf('<tr class="%s">', $rowClass));
        rawoutput('<td>' . grand_exchange_render_item_label($row['name']) . '</td>');
        rawoutput('<td>' . (int) $row['available'] . '</td>');
        rawoutput('<td>' . grand_exchange_sanitize($row['description']) . '</td>');
        rawoutput('<td>');
        rawoutput(sprintf("<a href='%s'>Sell</a>", $url));
        rawoutput('</td>');
        addnav('', $url);
        rawoutput('</tr>');
    }

    rawoutput('</table>');
}

function grand_exchange_render_sell_creation_form(int $userId, int $itemId): void
{
    $item = grand_exchange_get_item_info($itemId);

    if (! $item) {
        output('`$That item could not be found.`0`n');
        return;
    }

    $available = grand_exchange_get_available_inventory_quantity($userId, $itemId);

    if ($available <= 0) {
        output('`$You do not have any unequipped copies of that item to sell.`0`n');
        return;
    }

    $image = grand_exchange_get_item_image_html($item['name']);

    if ($image !== '') {
        grand_exchange_output_item_image_styles();
        rawoutput('<div class="grand-exchange-sell-preview">' . $image . grand_exchange_sanitize($item['name']) . '</div>');
    }

    output('Preparing to sell `b%s`b.`0`n`n', grand_exchange_sanitize($item['name']));
    output('You currently have `%s` unequipped copies available to list.`0`n`n', $available);

    $action = 'runmodule.php?module=grand_exchange&op=create_sell';
    addnav('', $action);

    rawoutput(sprintf("<form method='POST' action='%s'>", $action));
    rawoutput("<input type='hidden' name='create_sell_offer' value='1'>");
    rawoutput(sprintf("<input type='hidden' name='item_id' value='%d'>", $itemId));
    rawoutput(sprintf("<label>Quantity <input type='number' name='quantity' min='1' max='%d' value='1'></label><br>", $available));
    rawoutput(sprintf("<label>Gold per item <input type='number' name='price_gold' min='0' value='%d'></label><br>", (int) $item['gold']));
    rawoutput("<label>Gems per item <input type='number' name='price_gems' min='0' value='0'></label><br>");
    rawoutput("<button type='submit'>Post Sell Offer</button>");
    rawoutput('</form>');
}

function grand_exchange_restore_inventory_items(int $userId, int $itemId, array $rows): void
{
    if ($rows === []) {
        return;
    }

    grand_exchange_ensure_inventory_library();

    foreach ($rows as $row) {
        add_item_by_id(
            $itemId,
            1,
            $userId,
            $row['specialvalue'] ?? '',
            (int) ($row['sellvaluegold'] ?? 0),
            (int) ($row['sellvaluegems'] ?? 0),
            (int) ($row['charges'] ?? 0)
        );
    }

    invalidatedatacache("inventory-user-$userId");
}