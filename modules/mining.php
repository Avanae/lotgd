<?php

use Lotgd\Nav;
use Lotgd\Http;
use Lotgd\Output;
use Lotgd\Translator;
use Lotgd\MySQL\Database;

function mining_getmoduleinfo(): array
{
    return [
        'name' => 'Mining Basics',
        'version' => '1.0.0',
        'author' => '`7J`te`7f`tf`7r`te`7y `tH`7o`te`7g`te`7e',
        'category' => 'Skills',
        'download' => 'core_module',
        'description' => 'Introduces the Mining skill with core ores and ties into the Skills module.',
        'requires' => [
            'skills' => '1.1.0|`7J`te`7f`tf`7r`te`7y `tH`7o`te`7g`te`7e',
        ],
        'prefs' => [
            'Mining Player Preferences,title',
            'skill' => 'Current mining rank for the player,int|0',
            'heat' => 'Reserved for compatibility,int|0',
        ],
    ];
}

function mining_install(): bool
{
    if (! is_module_active('skills')) {
        output("The Skills module must be active before installing Mining.`n");
        return false;
    }

    module_addhook('skilldisplay');
    module_addhook('village');

    return true;
}

function mining_uninstall(): bool
{
    return true;
}

function mining_ensure_skills_module(): bool
{
    if (function_exists('skills_load_player_data')) {
        return true;
    }

    if (! is_module_active('skills')) {
        return false;
    }

    if (function_exists('injectmodule')) {
        injectmodule('skills');
    } else {
        require_once 'modules/skills.php';
    }

    return function_exists('skills_load_player_data');
}




function mining_dohook(string $hookName, array $args): array
{
    switch ($hookName) {
        case 'skilldisplay':
            $args = mining_collect_skill_stats($args);
            break;

        case 'village':
            $header = $args['gatenav'] ?? 'City Gates';
            $navigation = Nav::getInstance();
            $previousSection = $navigation->getNavSection();

            $navigation->setNavSection($header);
            addnav('H?Mine Entrance', 'runmodule.php?module=mining');

            if ($previousSection !== '') {
                $navigation->setNavSection($previousSection);
            }
            break;
    }

    return $args;
}
function mining_run(): void
{
    global $session;

    $op = Http::get('op');
    $oreKey = (string) Http::get('ore');
    $ores = mining_get_ores();

    page_header('The Mine');

    $playerId = (int) ($session['user']['acctid'] ?? 0);
    $mining = mining_load_player_skill($playerId);
    $playerLevel = (int) ($mining['level'] ?? 1);

    addnav('Navigation');
    addnav('Back to the Village', 'village.php');

    $availableOres = array_values(array_filter(
        $ores,
        static function (array $ore) use ($playerLevel): bool {
            return $playerLevel >= (int) ($ore['level'] ?? 0);
        }
    ));

    if ($availableOres !== []) {
        addnav('Actions');
        foreach ($availableOres as $ore) {
            addnav(
                sprintf('Mine %s', $ore['name']),
                sprintf('runmodule.php?module=mining&op=mine&ore=%s', rawurlencode($ore['key']))
            );
        }
    }

    output('`c`bThe Mine`b`c`n');
    output('`7This sprawling network of tunnels and shafts has been worked for generations, its stone walls scarred with the marks of countless pickaxes.`n');
    output('Lanterns hang from thick beams, casting a dim glow over the wide chambers where miners haul carts laden with coal, iron, and mithril.`n');
    output('The deeper you go, the louder the echoes of hammer and stone, until it feels as though the earth itself is alive with industry.`n');
    output('Though busy and well-guarded, whispers tell of hidden passages that lead into darker, unexplored caverns best left undisturbed.`n`n');

    $experienceDisplay = (float) ($mining['experience'] ?? 0);

    if ($playerId > 0) {
        $experienceDisplay += (float) get_module_pref('experience_remainder', 'mining', $playerId);
    }

    output('Your mining level is `^%s`0 with `^%s`0 experience.`n`n', $mining['level'], mining_format_experience($experienceDisplay));

    if ($op === 'mine') {
        $selectedOre = null;
        foreach ($ores as $ore) {
            if ($ore['key'] === $oreKey) {
                $selectedOre = $ore;
                break;
            }
        }

        if ($selectedOre !== null) {
            $requiredLevel = (int) ($selectedOre['level'] ?? 0);

            if ($playerLevel < $requiredLevel) {
                output('`$You need a mining level of `^%s`$ to mine this rock.`0`n`n', $selectedOre['level']);
            } else {
                $availableTurns = (int) ($session['user']['turns'] ?? 0);

                if ($availableTurns <= 0) {
                    output('`$You are too exhausted to work another rock today.`0`n`n');
                } else {
                    $session['user']['turns'] = max(0, $availableTurns - 1);
                    debuglog(sprintf('Spent a turn mining %s.', $selectedOre['name']), false, false, 'turns', -1);

                    output('`@You swing your pickaxe at the %s rock.`0`n`n', $selectedOre['name']);

                    $levelDelta = max(0, $playerLevel - $requiredLevel);
                    $successChance = min(0.95, 0.35 + ($levelDelta * 0.05));
                    $successPercent = (int) round($successChance * 100);
                    output('`7(Success chance: `^%s%%`7)`0`n', $successPercent);

                    $roll = random_int(1, 100);
                    $progress = null;

                    if (mining_should_trigger_collapse()) {
                        $progress = mining_handle_collapse_event($selectedOre, $playerId);
                    } elseif ($roll <= $successPercent) {
                        output('`@You managed to mine some %s ore!`0`n', $selectedOre['name']);

                        if (mining_store_ore_in_inventory($selectedOre, $playerId)) {
                            output('`2You place the %s ore safely into your inventory.`0`n`n', $selectedOre['name']);
                        } else {
                            output('`$Your inventory is full so you drop the %s ore onto the ground.`0`n`n', $selectedOre['name']);
                        }

                        $progress = mining_award_experience($selectedOre, $playerId, true);
                    } else {
                        output('`$Despite your efforts, the rock yields nothing this time. You did however learn something.`0`n`n');

                        $progress = mining_award_experience($selectedOre, $playerId, false);
                    }

                    mining_output_experience_gain($progress);

                    $mining = mining_load_player_skill($playerId, true);
                }
            }
        } else {
            output('`$The vein you were looking for isn\'t available here.`0`n`n');
        }
    }

    page_footer();
}

function mining_collect_skill_stats(array $args): array
{
    global $session;

    if (empty($session['user']['loggedin'])) {
        return $args;
    }

    $enabled = $args['enabled'] ?? [];
    if (is_array($enabled) && ! in_array('mining', $enabled, true)) {
        return $args;
    }

    $playerInfo = $args['player']['mining'] ?? null;

    if (is_array($playerInfo)) {
        $skill = (int) ($playerInfo['level'] ?? 1);
        $experience = (int) ($playerInfo['experience'] ?? 0);
    } else {
        $skill = 1;
        $experience = 0;
    }

    if (function_exists('skills_clamp_level')) {
        $skill = skills_clamp_level($skill);
    }

    if (function_exists('skills_clamp_experience')) {
        $experience = skills_clamp_experience($experience);
    }

    $playerId = (int) ($playerInfo['userid'] ?? ($session['user']['acctid'] ?? 0));
    $experienceDisplay = (float) $experience;

    if ($playerId > 0) {
        $experienceDisplay += (float) get_module_pref('experience_remainder', 'mining', $playerId);
    }

    $entries = $args['skills'] ?? [];
    if (! is_array($entries)) {
        $entries = [];
    }

    $entries['mining'] = [
        'label' => 'Mining',
        'value' => sprintf('Level %d (%s XP)', $skill, mining_format_experience($experienceDisplay)),
        'data' => [
            'level' => $skill,
            'experience' => $experience,
            'experience_display' => $experienceDisplay,
        ],
    ];

    $args['skills'] = $entries;

    return $args;
}

function mining_get_ores(): array
{
    return [
        ['key' => 'copper', 'name' => 'Copper', 'level' => 1],
        ['key' => 'tin', 'name' => 'Tin', 'level' => 1],
        ['key' => 'iron', 'name' => 'Iron', 'level' => 15],
        ['key' => 'silver', 'name' => 'Silver', 'level' => 20],
        ['key' => 'coal', 'name' => 'Coal', 'level' => 30],
        ['key' => 'gold', 'name' => 'Gold', 'level' => 40],
        ['key' => 'mithril', 'name' => 'Mithril', 'level' => 55],
        ['key' => 'adamantite', 'name' => 'Adamantite', 'level' => 70],
        ['key' => 'runite', 'name' => 'Runite', 'level' => 85],
    ];
}

function mining_load_player_skill(int $userId, bool $forceRefresh = false): array
{
    $defaults = ['level' => 1, 'experience' => 0];

    if ($userId <= 0) {
        return $defaults;
    }

    if (! mining_ensure_skills_module()) {
        return $defaults;
    }

    $skills = skills_load_player_data($userId, $forceRefresh);
    $player = $skills['mining'] ?? null;

    if (! is_array($player)) {
        return $defaults;
    }

    return [
        'level' => (int) ($player['level'] ?? 1),
        'experience' => (int) ($player['experience'] ?? 0),
    ];
}

function mining_get_experience_values(): array
{
    return [
        'copper' => 17.5,
        'tin' => 17.5,
        'iron' => 35.0,
        'silver' => 40.0,
        'coal' => 50.0,
        'gold' => 65.0,
        'mithril' => 80.0,
        'adamantite' => 95.0,
        'runite' => 125.0,
    ];
}

function mining_award_experience(array $ore, int $userId, bool $success): ?array
{
    if ($userId <= 0) {
        return null;
    }

    if (! mining_ensure_skills_module()) {
        return null;
    }

    $xpValues = mining_get_experience_values();
    $oreKey = (string) ($ore['key'] ?? '');

    if (! isset($xpValues[$oreKey])) {
        return null;
    }

    $baseExperience = (float) $xpValues[$oreKey];

    if ($baseExperience <= 0) {
        return null;
    }

    $gainedExperience = $success ? $baseExperience : $baseExperience / 2;

    if ($gainedExperience <= 0) {
        return null;
    }

    if (function_exists('skills_create_player_row')) {
        skills_create_player_row($userId);
    }

    $skills = skills_load_player_data($userId);
    $current = $skills['mining'] ?? ['level' => 1, 'experience' => 0];

    $currentLevel = (int) ($current['level'] ?? 1);
    $currentExperience = (int) ($current['experience'] ?? 0);

    $maxExperience = function_exists('skills_get_max_experience')
        ? skills_get_max_experience()
        : 13034431;

    $previousRemainder = (float) get_module_pref('experience_remainder', 'mining', $userId);

    if ($currentExperience >= $maxExperience) {
        set_module_pref('experience_remainder', '0.00', 'mining', $userId);

        return [
            'xp_gain' => 0.0,
            'xp_awarded' => 0,
            'level_before' => $currentLevel,
            'level_after' => $currentLevel,
            'experience_before' => $currentExperience,
            'experience_after' => $currentExperience,
        ];
    }

    $totalExperience = $gainedExperience + $previousRemainder;
    $experienceAwarded = (int) floor($totalExperience + 1e-6);
    $remainder = $totalExperience - $experienceAwarded;

    if ($remainder < 1e-6) {
        $remainder = 0.0;
    }

    $newExperience = $currentExperience + $experienceAwarded;

    if ($newExperience >= $maxExperience) {
        $experienceAwarded = max(0, $maxExperience - $currentExperience);
        $newExperience = $maxExperience;
        $remainder = 0.0;
    }

    set_module_pref('experience_remainder', sprintf('%.2F', $remainder), 'mining', $userId);

    $totalBefore = $currentExperience + $previousRemainder;
    $totalAfter = $newExperience + $remainder;
    $actualGain = max(0.0, $totalAfter - $totalBefore);

    $newLevel = mining_calculate_level_from_experience($newExperience);

    $table = Database::prefix('skills');

    if (function_exists('skills_create_player_row')) {
        skills_create_player_row($userId);
    }

    $updateSql = sprintf(
        'UPDATE %s SET `mining_experience` = %d, `mining_level` = %d WHERE `userid` = %d',
        $table,
        $newExperience,
        $newLevel,
        $userId
    );

    Database::query($updateSql);

    if (Database::affectedRows() === 0) {
        $insertSql = sprintf(
            'INSERT INTO %s (`userid`, `mining_experience`, `mining_level`) VALUES (%d, %d, %d)',
            $table,
            $userId,
            $newExperience,
            $newLevel
        );

        Database::query($insertSql);
    }

    skills_load_player_data($userId, true);

    return [
        'xp_gain' => $actualGain,
        'xp_awarded' => $experienceAwarded,
        'level_before' => $currentLevel,
        'level_after' => $newLevel,
        'experience_before' => $currentExperience,
        'experience_after' => $newExperience,
    ];
}

function mining_output_experience_gain(?array $progress): void
{
    if (! is_array($progress)) {
        return;
    }

    $xpGain = (float) ($progress['xp_gain'] ?? 0);
    $experienceBefore = (int) ($progress['experience_before'] ?? 0);
    $experienceAfter = (int) ($progress['experience_after'] ?? 0);

    if ($xpGain > 0) {
        output('`#You gained `^%s`# Mining experience.`0`n', mining_format_experience($xpGain));
    } elseif ($experienceAfter === $experienceBefore) {
        //output('`#Your Mining experience cannot increase any further.`0`n');
    }

    if (($progress['level_after'] ?? 0) > ($progress['level_before'] ?? 0)) {
         output('`n`1Congratulations, you just advanced a mining level.`nYour Mining level is now `g%s`0`n', $progress['level_after']);
    }

    if (($progress['level_after'] ?? 0) >= 60 && ($progress['level_before'] ?? 0) < 60) {
        output('`1You have gained access to the Mining Guild. Why not have a look?`0`n');
    }

    if (($progress['level_after'] ?? 0) >= 99 && ($progress['level_before'] ?? 0) < 99) {
        output('`1You are now a master miner. Why not visit the mining guild for something special?`0`n');
    }
}

function mining_calculate_level_from_experience(int $experience): int
{
    static $thresholds = null;

    if ($thresholds === null) {
        $thresholds = [
            1 => 0,
            2 => 83,
            3 => 174,
            4 => 276,
            5 => 388,
            6 => 512,
            7 => 650,
            8 => 801,
            9 => 969,
            10 => 1154,
            11 => 1358,
            12 => 1584,
            13 => 1833,
            14 => 2107,
            15 => 2411,
            16 => 2746,
            17 => 3115,
            18 => 3523,
            19 => 3973,
            20 => 4470,
            21 => 5018,
            22 => 5624,
            23 => 6291,
            24 => 7028,
            25 => 7842,
            26 => 8740,
            27 => 9730,
            28 => 10824,
            29 => 12031,
            30 => 13363,
            31 => 14833,
            32 => 16456,
            33 => 18247,
            34 => 20224,
            35 => 22406,
            36 => 24815,
            37 => 27473,
            38 => 30408,
            39 => 33648,
            40 => 37224,
            41 => 41171,
            42 => 45529,
            43 => 50339,
            44 => 55649,
            45 => 61512,
            46 => 67983,
            47 => 75127,
            48 => 83014,
            49 => 91721,
            50 => 101333,
            51 => 111945,
            52 => 123660,
            53 => 136594,
            54 => 150872,
            55 => 166636,
            56 => 184040,
            57 => 203254,
            58 => 224466,
            59 => 247886,
            60 => 273742,
            61 => 302288,
            62 => 333804,
            63 => 368599,
            64 => 407015,
            65 => 449428,
            66 => 496254,
            67 => 547953,
            68 => 605032,
            69 => 668051,
            70 => 737627,
            71 => 814445,
            72 => 899257,
            73 => 992895,
            74 => 1096278,
            75 => 1210421,
            76 => 1336443,
            77 => 1475581,
            78 => 1629200,
            79 => 1798808,
            80 => 1986068,
            81 => 2192818,
            82 => 2421087,
            83 => 2673114,
            84 => 2951373,
            85 => 3258594,
            86 => 3597792,
            87 => 3972294,
            88 => 4385776,
            89 => 4842295,
            90 => 5346332,
            91 => 5902831,
            92 => 6517253,
            93 => 7195629,
            94 => 7944614,
            95 => 8771558,
            96 => 9684577,
            97 => 10692629,
            98 => 11805606,
            99 => 13034431,
        ];
    }

    $calculatedLevel = 1;
    foreach ($thresholds as $level => $requiredExperience) {
        if ($experience >= $requiredExperience) {
            $calculatedLevel = $level;
        } else {
            break;
        }
    }

    return $calculatedLevel;
}

function mining_format_experience(float $experience): string
{
    $formatted = number_format($experience, 2, '.', '');
    $formatted = rtrim(rtrim($formatted, '0'), '.');

    return $formatted === '' ? '0' : $formatted;
}

function mining_store_ore_in_inventory(array $ore, int $userId): bool
{
    if (! is_module_active('inventory')) {
        debug('Mining: inventory module inactive; ore not stored.');
        return false;
    }

    require_once 'modules/inventory/lib/itemhandler.php';

    $itemName = sprintf('%s Ore', $ore['name']);
    $item = get_item_by_name($itemName);

    if ($item === false) {
        $description = sprintf('This piece of %s needs refining.', strtolower($ore['name']));

        $itemData = [
            'itemid' => 0,
            'class' => 'Ore',
            'name' => $itemName,
            'description' => $description,
            'gold' => 0,
            'gems' => 0,
            'weight' => 1,
            'droppable' => 1,
            'level' => max(1, (int) ($ore['level'] ?? 1)),
            'dragonkills' => 0,
            'buffid' => 0,
            'charges' => 0,
            'link' => '',
            'hide' => 0,
            'customvalue' => '',
            'execvalue' => '',
            'exectext' => '',
            'noeffecttext' => '',
            'activationhook' => '0',
            'findchance' => 0,
            'loosechance' => 0,
            'dkloosechance' => 0,
            'sellable' => 1,
            'buyable' => 0,
            'uniqueforserver' => 0,
            'uniqueforplayer' => 0,
            'equippable' => 0,
            'equipwhere' => '',
        ];

        inject_item($itemData, ['customvalue', 'execvalue', 'exectext', 'noeffecttext', 'link']);

        if (! function_exists('invalidatedatacache')) {
            require_once 'lib/datacache.php';
        }

        invalidatedatacache('item-name-' . $itemName);

        $item = get_item_by_name($itemName);
        if (! is_array($item) || empty($item['itemid'])) {
            debug(sprintf('Mining: failed to create inventory item "%s".', $itemName));
            return false;
        }
    } elseif (! is_array($item) || empty($item['itemid'])) {
        debug(sprintf('Mining: inventory item "%s" is missing its identifier.', $itemName));
        return false;
    }

    $result = add_item_by_id((int) $item['itemid'], 1, $userId);

    if ($result === false) {
        debug(sprintf('Mining: unable to add inventory item "%s" for user %d.', $itemName, $userId));
    }

    return (bool) $result;
}

function mining_should_trigger_collapse(): bool
{
    return random_int(1, 100) <= 10;
}

function mining_handle_collapse_event(array $ore, int $userId): ?array
{
    global $session;

    $currentHitpoints = (int) ($session['user']['hitpoints'] ?? 0);
    $maxHitpoints = (int) ($session['user']['maxhitpoints'] ?? $currentHitpoints);

    if ($maxHitpoints <= 0) {
        $maxHitpoints = max(1, $currentHitpoints);
    }

    $damage = max(1, (int) ceil($maxHitpoints * 0.10));
    $newHitpoints = max(1, $currentHitpoints - $damage);
    $actualDamage = max(0, $currentHitpoints - $newHitpoints);
    $session['user']['hitpoints'] = $newHitpoints;

    output('`$Mine Collapse!`0 Rocks rain down as the tunnel shudders around you. You take `4%s`$ damage.`0`n`n', $actualDamage);
    debuglog(sprintf('Injured for %s HP during a mine collapse.', $actualDamage), false, false, 'hitpoints', -$actualDamage);

    return mining_award_experience($ore, $userId, false);
}
