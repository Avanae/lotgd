<?php

declare(strict_types=1);

use Lotgd\PageParts;

global $session;

if (! isset($session['user']['acctid']) || ! $session['user']['acctid']) {
    return;
}

if (! get_module_setting('withcharstats', 'inventory')) {
    return;
}

require_once 'modules/inventory/lib/itemhandler.php';

$result = get_inventory();
$totalItems = 0;
$totalWeight = 0;

if ($result) {
    while ($row = db_fetch_assoc($result)) {
        $quantity = (int) ($row['quantity'] ?? 0);
        $totalItems += $quantity;

        $weight = (int) ($row['weight'] ?? 0);
        $totalWeight += $weight * $quantity;
    }

    if (function_exists('db_free_result')) {
        db_free_result($result);
    }
}

$slotsLimit = (int) get_module_setting('limit', 'inventory');
$weightLimit = (int) get_module_setting('weight', 'inventory');

$itemsValue = ($slotsLimit > 0)
    ? sprintf('%d/%d', $totalItems, $slotsLimit)
    : sprintf('%d (%s)', $totalItems, translate_inline('Unlimited'));

if ($weightLimit > 0) {
    $weightValue = sprintf('%d/%d', $totalWeight, $weightLimit);
} elseif ($totalWeight > 0) {
    $weightValue = sprintf('%d (%s)', $totalWeight, translate_inline('Unlimited'));
} else {
    $weightValue = translate_inline('Unlimited');
}

setcharstat('Inventory', 'Items', sprintf('`^%s`0', $itemsValue));
setcharstat('Inventory', 'Weight', sprintf('`^%s`0', $weightValue));

$link = 'runmodule.php?module=inventory&op=charstat';
$viewLabel = translate_inline('Open');

setcharstat(
    'Inventory',
    'View',
    sprintf(
        "<a href='%s' target='_blank' onClick=\"%s;return false;\">%s</a>",
        $link,
        PageParts::popup($link),
        $viewLabel
    )
);
