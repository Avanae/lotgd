<?php

use Lotgd\Nav;
use Lotgd\Http;
use Lotgd\Output;
use Lotgd\Translator;
use Lotgd\MySQL\Database;
use Lotgd\Modules\ModuleManager;

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
        'settings' => [
            'Mining Module Settings,title',
            'deduct_turns' => 'Lose a forest turn when mining?,bool|1',
            'collapse_enabled' => 'Enable mine collapse events?,bool|1',
            'collapse_damage_percent' => 'Percent of max hitpoints lost per collapse,enum,10,10%,20,20%,30,30%,40,40%,50,50%,60,60%,70,70%,80,80%|10',
            'gem_drop_enabled' => 'Chance to find gems while mining?,bool|1',
            'gem_drop_chance' => 'Percent chance to find gems on success,enum,5,5%,10,10%,15,15%,20,20%|10',
            'gem_drop_max' => 'Maximum gems awarded per successful mine,int|3',
            'skillcape_enabled' => 'Allow purchasing the Mining skillcape?,bool|1',
            'skillcape_cost_gold' => 'Gold cost for the Mining skillcape,int|150000',
            'skillcape_cost_gems' => 'Gem cost for the Mining skillcape,int|50',
        ],
        'prefs' => [
            'Mining Player Preferences,title',
            'skill' => 'Current mining rank for the player,int|0',
            'heat' => 'Reserved for compatibility,int|0',
            'skillcape_owned' => 'Has purchased the Mining skillcape,bool|0',
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
    $previousModule = ModuleManager::getMostRecentModule();

    if (function_exists('skills_load_player_data')) {
        if ($previousModule === '') {
            ModuleManager::setMostRecentModule('mining');
        }

        return true;
    }

    if (! is_module_active('skills')) {
        if ($previousModule === '') {
            ModuleManager::setMostRecentModule('mining');
        } else {
            ModuleManager::setMostRecentModule($previousModule);
        }

        return false;
    }

    if (function_exists('injectmodule')) {
        injectmodule('skills');
    } else {
        require_once 'modules/skills.php';
    }

    if ($previousModule === '') {
        ModuleManager::setMostRecentModule('mining');
    } else {
        ModuleManager::setMostRecentModule($previousModule);
    }

    return function_exists('skills_load_player_data');
}

function mining_enter_context(): string
{
    $previousModule = ModuleManager::getMostRecentModule();

    ModuleManager::setMostRecentModule('mining');

    return $previousModule;
}

function mining_leave_context(string $previousModule): void
{
    ModuleManager::setMostRecentModule($previousModule);
}


function mining_dohook(string $hookName, array $args): array
{
    $previousModule = mining_enter_context();

    try {
        global $session;

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

            case 'skillguildnav':
                $playerData = $args['player'] ?? [];
                $miningData = $playerData['mining'] ?? [];
                $miningLevel = (int) ($miningData['level'] ?? 0);

                if ($miningLevel === 0) {
                    $userId = (int) ($playerData['userid'] ?? $playerData['acctid'] ?? 0);

                    if ($userId <= 0) {
                        $userId = (int) ($session['user']['acctid'] ?? 0);
                    }

                    if ($userId > 0) {
                        $loadedSkill = mining_load_player_skill($userId);
                        $miningLevel = (int) ($loadedSkill['level'] ?? 0);
                    }
                }

                $label = $miningLevel < 60
                ? sprintf('Mining Guild (Requires 60 Mining, you have %d)', $miningLevel)
                : 'Mining Guild (Requires 60 Mining)';

                Nav::add($label, 'runmodule.php?module=mining&op=guild');
                break;
        }

        return $args;
    } finally {
        mining_leave_context($previousModule);
    }
}

function mining_run(): void
{
    $previousModule = mining_enter_context();

    try {
        global $session;

        $op = Http::get('op');
        $oreKey = (string) Http::get('ore');
        $context = (string) Http::get('context');
        $action = (string) Http::get('action');
        $guildOreKeys = ['mithril', 'adamantite', 'runite'];
        $isGuildContext = ($context === 'guild');
        $isGuildView = ($op === 'guild') || $isGuildContext;
        if ($op === "skillcape" || $op === "gadrin") {
            $context = "guild";
            $isGuildContext = true;
            $isGuildView = true;
        }

        $ores = mining_get_ores();

        page_header($isGuildView ? 'The Mining Guild' : 'The Mine');

        $playerId = (int) ($session['user']['acctid'] ?? 0);
        $mining = mining_load_player_skill($playerId);
        $playerLevel = (int) ($mining['level'] ?? 1);
        $shouldDeductTurns = (bool) get_module_setting('deduct_turns');
        $collapseEnabled = (bool) get_module_setting('collapse_enabled');
        $collapseDamagePercent = (int) get_module_setting('collapse_damage_percent');
        $collapseDamagePercent = max(1, min(80, $collapseDamagePercent));
        $gemDropEnabled = (bool) get_module_setting('gem_drop_enabled');
        $gemDropChance = (int) get_module_setting('gem_drop_chance');
        $gemDropChance = max(0, min(100, $gemDropChance));
        $gemDropMax = max(1, (int) get_module_setting('gem_drop_max'));
        $skillcapeEnabled = (bool) get_module_setting('skillcape_enabled');
        $skillcapeCostGold = max(0, (int) get_module_setting('skillcape_cost_gold'));
        $skillcapeCostGems = max(0, (int) get_module_setting('skillcape_cost_gems'));
        $hasSkillcape = $playerId > 0 ? (bool) get_module_pref('skillcape_owned', 'mining', $playerId) : false;

        addnav('Navigation');
        addnav('Back to the Village', 'village.php');

        if ($isGuildView) {
            addnav('Leave the Mining Guild', 'runmodule.php?module=mining');
        } elseif ($playerLevel >= 60) {
            addnav('Visit the Mining Guild', 'runmodule.php?module=mining&op=guild');
        }

        if ($isGuildView && $playerLevel < 60) {
            output('`$Two armored guards bar your way. Only miners with a skill of `^60`$ or higher may enter the guild halls.`0`n`n');
            page_footer();

            return;
    }

    $actionsNavAdded = false;
    $guildServicesNavAdded = false;
    $availableOres = array_values(array_filter(
    $ores,
    static function (array $ore) use ($playerLevel, $isGuildView, $guildOreKeys): bool {
        $key = (string) ($ore['key'] ?? '');

        if ($isGuildView && ! in_array($key, $guildOreKeys, true)) {
            return false;
    }

    if (! $isGuildView && in_array($key, $guildOreKeys, true)) {
        return false;
}

return $playerLevel >= (int) ($ore['level'] ?? 0);
}
));

if ($availableOres !== []) {
    addnav('Actions');
    $actionsNavAdded = true;
    foreach ($availableOres as $ore) {
        $link = sprintf(
        'runmodule.php?module=mining&op=mine&ore=%s',
        rawurlencode($ore['key'])
        );

        if ($isGuildView) {
            $link .= '&context=guild';
        }

        addnav(
        sprintf('Mine %s', $ore['name']),
        $link
        );
    }
}

if ($isGuildView) {
    if (! $guildServicesNavAdded) {
        addnav('Guild Services');
        $guildServicesNavAdded = true;
    } else {
        Nav::getInstance()->setNavSection('Guild Services');
    }

    addnav('Speak with Gadrin', 'runmodule.php?module=mining&op=gadrin&context=guild');
}

if ($isGuildView) {
    output('`c`bThe Mining Guild`b`c`n');
    output('`7Deep beneath the stone streets lies the Mining Guild. A sanctuary for hardened pickaxe wielders and treasure seekers.`n');
    output('Master miners can find the best rocks containing Mithril, Adamantite and Runite. The air hums with the clang of steel on rock and the promise of glittering ore.`n');
    output('Since only seasoned miners earn entry when their mastery reaches a certain point, the Guild is heavily guarded.`n`n');
} else {
    output('`c`bThe Mine`b`c`n');
    output('`7This sprawling network of tunnels and shafts has been worked for generations, its stone walls scarred with the marks of countless pickaxes.`n');
    output('Lanterns hang from thick beams, casting a dim glow over the wide chambers where miners haul carts loaded with coal, iron, and gold.`n');
    output('The deeper you go, the louder the echoes of hammer and stone, until it feels as though the earth itself is alive with industry.`n');
    output('Though busy and well-guarded, whispers tell of hidden passages that lead into darker, unexplored caverns best left undisturbed.`n`n');
}

$experienceDisplay = (float) ($mining['experience'] ?? 0);

if ($playerId > 0) {
    $experienceDisplay += (float) get_module_pref('experience_remainder', 'mining', $playerId);
}

output('Your mining level is `^%s`0 with `^%s`0 experience.`n`n', $mining['level'], mining_format_experience($experienceDisplay));

if ($op === 'skillcape') {
    if (! $isGuildView) {
        Http::redirect('runmodule.php?module=mining&op=guild');
    }

    if (! $skillcapeEnabled) {
        output('`$Gadrin shakes his head. "The Guild is not currently issuing skillcapes."`0`n');
        if (! $actionsNavAdded) {
            addnav('Actions');
            $actionsNavAdded = true;
        }
        addnav('Return to the Guild', 'runmodule.php?module=mining&op=guild');
        page_footer();
        return;
}

if ($playerId <= 0) {
    output('`$You must be logged in to make purchases.`0`n');
    if (! $actionsNavAdded) {
        addnav('Actions');
        $actionsNavAdded = true;
    }
    addnav('Return to the Guild', 'runmodule.php?module=mining&op=guild');
    page_footer();
    return;
}

if ($playerLevel < 60) {
    output('`$The guards usher you back toward the main hall before you can speak with Gadrin.`0`n');
    if (! $actionsNavAdded) {
        addnav('Actions');
        $actionsNavAdded = true;
    }
    addnav('Return to the Guild', 'runmodule.php?module=mining&op=guild');
    page_footer();
    return;
}

if ($playerLevel < 99) {
    output('`$Gadrin folds his arms. "Only miners who have mastered their craft may wear the skillcape. Reach level 99 and I will see to it you receive one."`0`n');
    if (! $actionsNavAdded) {
        addnav('Actions');
        $actionsNavAdded = true;
    }
    addnav('Return to the Guild', 'runmodule.php?module=mining&op=guild');
    page_footer();
    return;
}

if ($hasSkillcape) {
    output('`@Gadrin smiles. "You already wear the Mining skillcape with pride."`0`n');
    if (! $actionsNavAdded) {
        addnav('Actions');
        $actionsNavAdded = true;
    }
    addnav('Return to the Guild', 'runmodule.php?module=mining&op=guild');
    page_footer();
    return;
}

$costGold = $skillcapeCostGold;
$costGems = $skillcapeCostGems;
$costParts = [];

if ($costGold > 0) {
    $costParts[] = sprintf('%s gold', number_format($costGold));
}

if ($costGems > 0) {
    $costParts[] = sprintf('%d gem%s', $costGems, $costGems === 1 ? '' : 's');
}

$costText = $costParts === [] ? 'no cost' : implode(' and ', $costParts);

if ($action === 'purchase') {
    if (! $actionsNavAdded) {
        addnav('Actions');
        $actionsNavAdded = true;
    }

    addnav('Return to the Guild', 'runmodule.php?module=mining&op=guild');

    if ($session['user']['gold'] < $costGold || $session['user']['gems'] < $costGems) {
        output('`$You do not have the required resources for the skillcape.`0`n');
        page_footer();
        return;
}

if (! is_module_active('inventory')) {
    output('`$Without the inventory module active, Gadrin has nowhere to store the cape. He asks you to return later.`0`n');
    page_footer();
    return;
}

$originalGold = (int) ($session['user']['gold'] ?? 0);
$originalGems = (int) ($session['user']['gems'] ?? 0);

$session['user']['gold'] = max(0, $originalGold - $costGold);
$session['user']['gems'] = max(0, $originalGems - $costGems);

if (mining_award_skillcape($playerId)) {
    set_module_pref('skillcape_owned', 1, 'mining', $playerId);
    output('`@Gadrin drapes the Mining skillcape across your shoulders. "Wear it proudly, master miner."`0`n');
    debuglog('Purchased the Mining skillcape from Gadrin.');
} else {
    $session['user']['gold'] = $originalGold;
    $session['user']['gems'] = $originalGems;
    output('`$Something went wrong and the skillcape could not be delivered. Your payment has been refunded.`0`n');
}

page_footer();
return;
}

if (! $actionsNavAdded) {
    addnav('Actions');
    $actionsNavAdded = true;
}

addnav('Purchase the Skillcape', 'runmodule.php?module=mining&op=skillcape&context=guild&action=purchase');
addnav('Return to the Guild', 'runmodule.php?module=mining&op=guild');

output('`@Guild quartermaster Gadrin studies your calloused hands and nods. "A level 99 miner deserves the finest cape. I can tailor one for %s."`0`n', $costText);
output('`7The heavy cloak is lined with coal-black velvet and gilded trim that shimmers with latent magic.`0`n`n');

page_footer();
return;
} elseif ($op === 'gadrin') {
    if (! $isGuildView) {
        Http::redirect('runmodule.php?module=mining&op=guild');
    }

    if (! $actionsNavAdded) {
        addnav('Actions');
        $actionsNavAdded = true;
    }

    addnav('Return to the Guild', 'runmodule.php?module=mining&op=guild');

    if ($skillcapeEnabled && $playerLevel >= 99 && ! $hasSkillcape) {
        if (! $guildServicesNavAdded) {
            addnav('Guild Services');
            $guildServicesNavAdded = true;
        } else {
            Nav::getInstance()->setNavSection('Guild Services');
        }

        addnav('Request the Skillcape', 'runmodule.php?module=mining&op=skillcape&context=guild');
    }

    output('`@Gadrin, the guild quartermaster, looks up from his ledger as you approach.`0`n');

    if ($hasSkillcape) {
        output('`@He nods at the cape on your shoulders. "It still sits proud. Keep honoring the guild."`0`n`n');

    } elseif ($playerLevel < 99) {
        output('`7"Keep your pick swinging," he says. "Reach level `^99`7 and I can tailor a skillcape worthy of you."`0`n`n');
    } else {
        output('`@He grins broadly. "Level `^99`@? That is the mark of a master. When you are ready, I can fit you for the guild\'s skillcape."`0`n`n');
    }

    page_footer();
    return;
} elseif ($op === 'mine') {
    $selectedOre = null;
    foreach ($ores as $ore) {
        if ($ore['key'] === $oreKey) {
            $selectedOre = $ore;
            break;
    }
}

if ($selectedOre !== null) {
    $isGuildOre = in_array($selectedOre['key'], $guildOreKeys, true);

    if ($isGuildOre && $playerLevel < 60) {
        output('`$Only miners with guild credentials may extract this vein.`0`n`n');
    } elseif ($isGuildOre && ! $isGuildContext) {
        output('`$Guild wardens stop you before you can swing. You must enter the Mining Guild to work this ore.`0`n`n');
    } else {
        $requiredLevel = (int) ($selectedOre['level'] ?? 0);

        if ($playerLevel < $requiredLevel) {
            output('`$You need a mining level of `^%s`$ to mine this rock.`0`n`n', $selectedOre['level']);
        } else {
            $availableTurns = (int) ($session['user']['turns'] ?? 0);

            if ($shouldDeductTurns && $availableTurns <= 0) {
                output('`$You are too exhausted to work another rock today.`0`n`n');
            } else {
                if ($shouldDeductTurns) {
                    $session['user']['turns'] = max(0, $availableTurns - 1);
                    debuglog(sprintf('Spent a turn mining %s.', $selectedOre['name']), false, false, 'turns', -1);
                }

                output('`@You swing your pickaxe at the %s rock.`0`n`n', $selectedOre['name']);

                $levelDelta = max(0, $playerLevel - $requiredLevel);
                $successChance = min(0.95, 0.35 + ($levelDelta * 0.05));
                $successPercent = (int) round($successChance * 100);

                $roll = random_int(1, 100);
                $progress = null;

                if ($collapseEnabled && ! $isGuildContext && mining_should_trigger_collapse()) {
                    $progress = mining_handle_collapse_event($selectedOre, $playerId, $collapseDamagePercent);
                } elseif ($roll <= $successPercent) {
                    output('`@You managed to mine some %s ore!`0`n', $selectedOre['name']);

                    if (is_module_active('inventory')) {
                        if (mining_store_ore_in_inventory($selectedOre, $playerId)) {
                            output('`@You place the %s ore safely into your inventory.`0`n`n', $selectedOre['name']);
                        } else {
                            output('`$Your inventory is full so you dropped the %s ore on the ground.`0`n`n', $selectedOre['name']);
                        }
                    }

                    $progress = mining_award_experience($selectedOre, $playerId, true);
                    if ($gemDropEnabled) {
                        mining_maybe_award_gems($gemDropChance, $gemDropMax, (string) ($selectedOre['name'] ?? 'ore'));
                    }
                } else {
                    output('`$Despite your efforts, the rock yields nothing this time. You did however learn something.`0`n`n');

                    $progress = mining_award_experience($selectedOre, $playerId, false);
                }

                mining_output_experience_gain($progress);

                $mining = mining_load_player_skill($playerId, true);
            }
        }
    }
} else {
    output('`$The ore you were looking for isn\'t available here.`0`n`n');
}
}

page_footer();
    } finally {
        mining_leave_context($previousModule);
    }
}

function mining_collect_skill_stats(array $args): array
{
    $previousModule = mining_enter_context();

    try {
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
    } finally {
        mining_leave_context($previousModule);
    }
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
    $previousModule = mining_enter_context();

    try {
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
    } finally {
        mining_leave_context($previousModule);
    }
}


function mining_maybe_award_gems(int $chancePercent, int $maxGems, string $oreName): int
{
    global $session;

    if ($chancePercent <= 0 || $maxGems <= 0) {
        return 0;
    }

    if (random_int(1, 100) > $chancePercent) {
        return 0;
    }

    $awarded = random_int(1, $maxGems);
    $session['user']['gems'] = (int) ($session['user']['gems'] ?? 0) + $awarded;

    $plural = $awarded === 1 ? '' : 's';
    output('`#You uncover `^%s`# gleaming gem%s among the rubble!`0`n', $awarded, $plural);
    debuglog(sprintf('Found %s gem%s while mining %s.', $awarded, $plural, $oreName), false, false, 'gems', $awarded);

    return $awarded;
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

function mining_award_skillcape(int $userId): bool
{
    if ($userId <= 0) {
        return false;
    }

    if (! is_module_active('inventory')) {
        Output::getInstance()->debug('Mining: inventory module inactive; skillcape not awarded.');
        return false;
    }

    require_once 'modules/inventory/lib/itemhandler.php';

    $itemName = 'Mining Skillcape';
    $item = get_item_by_name($itemName);

    if ($item === false) {
        $description = 'A heavy cloak trimmed in gold that marks mastery of Mining.';

        $itemData = [
            'itemid' => 0,
            'class' => 'Skillcape',
            'name' => $itemName,
            'description' => $description,
            'gold' => 0,
            'gems' => 0,
            'weight' => 1,
            'droppable' => 0,
            'level' => 99,
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
            'sellable' => 0,
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
            Output::getInstance()->debug('Mining: failed to create mining skillcape inventory item.');
            return false;
        }
    } elseif (! is_array($item) || empty($item['itemid'])) {
        Output::getInstance()->debug('Mining: skillcape inventory item is missing its identifier.');
        return false;
    }

    $result = add_item_by_id((int) $item['itemid'], 1, $userId);

    if ($result === false) {
        Output::getInstance()->debug(sprintf('Mining: unable to add skillcape inventory item for user %d.', $userId));
    }

    return (bool) $result;
}

function mining_should_trigger_collapse(): bool
{
    if (! get_module_setting('collapse_enabled')) {
        return false;
    }

    return random_int(1, 100) <= 10;
}

function mining_handle_collapse_event(array $ore, int $userId, ?int $damagePercent = null): ?array
{
    $previousModule = mining_enter_context();

    try {
        global $session;

        if (! get_module_setting('collapse_enabled')) {
            return mining_award_experience($ore, $userId, false);
    }

    $currentHitpoints = (int) ($session['user']['hitpoints'] ?? 0);
    $maxHitpoints = (int) ($session['user']['maxhitpoints'] ?? $currentHitpoints);

    if ($maxHitpoints <= 0) {
        $maxHitpoints = max(1, $currentHitpoints);
    }

    if ($damagePercent === null) {
        $damagePercent = (int) get_module_setting('collapse_damage_percent');
    }

    $damagePercent = max(1, min(80, $damagePercent));
    $damageRatio = $damagePercent / 100;
    $damage = max(1, (int) ceil($maxHitpoints * $damageRatio));
    $newHitpoints = max(1, $currentHitpoints - $damage);
    $actualDamage = max(0, $currentHitpoints - $newHitpoints);
    $session['user']['hitpoints'] = $newHitpoints;

    output('`$Mine Collapse!`0 Rocks rain down as the tunnel shudders around you. You take `4%s`$ damage.`0`n`n', $actualDamage);
    debuglog(sprintf('Injured for %s HP during a mine collapse.', $actualDamage), false, false, 'hitpoints', -$actualDamage);

    return mining_award_experience($ore, $userId, false);
    } finally {
        mining_leave_context($previousModule);
    }
}






















