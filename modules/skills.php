<?php

use Lotgd\Nav;
use Lotgd\Output;
use Lotgd\Translator;
use Lotgd\MySQL\Database;

function skills_getmoduleinfo(): array
{
    return [
        'name' => 'Skill Core Module',
        'version' => '1.1.0',
        'author' => '`7J`te`7f`tf`7r`te`7y `tH`7o`te`7g`te`7e',
        'category' => 'Skills',
        'download' => 'core_module',
        'description' => 'Enables Player skills that can be trained to a maximum of 99.',
        'settings' => skills_get_settings_definition(),
    ];
}

function skills_install(): bool
{
    skills_create_schema();
    module_addhook('charstats');
    module_addhook('village');

    return true;
}

function skills_uninstall(): bool
{
    return true;
}

function skills_dohook(string $hookName, array $args): array
{
    switch ($hookName) {
        case 'charstats':
            skills_render_charstats();
            break;

        case 'village':
            $marketHeader = $args['marketnav'] ?? '';
            $schemas = $args['schemas'] ?? [];

            Translator::tlschema('module-skills');

            Nav::add('Artisan Square');


                
            if (is_module_active('smithing')) {
                Nav::add('The Iron Anvil', 'runmodule.php?module=smithing'); 
                Nav::add('Blacksmith\'s Furnace', 'runmodule.php?module=smithing'); 
            }

            if (is_module_active('jeweller')) {
                Nav::add('Jeweler\'s Bench', 'runmodule.php?module=jeweler'); 
            }
                        
            if (is_module_active('pottery')) {
                Nav::add('Potter\'s Kiln', 'runmodule.php?module=pottery');
            }
            
            if (is_module_active('runecrafting')) {
                Nav::add('Runesmith\'s Table', 'runmodule.php?module=runecrafting'); 
            }
            
            if (is_module_active('herblore')) {
                Nav::add('The Green Mortar', 'runmodule.php?module=herblore'); 
            }            

            if (is_module_active('fletching')) {
                Nav::add('Oakshaft Fletchery', 'runmodule.php?module=fletching'); 
            }      


            Nav::add('Guild Halls');
            Nav::add('Cooking Guild', 'runmodule.php?module=cookingguild');
            Nav::add('Crafting Guild', 'runmodule.php?module=craftingguild');
            Nav::add('Mining Guild', 'runmodule.php?module=miningguild');
            Nav::add('Farming Guild', 'runmodule.php?module=farmingguild');
            Nav::add('Fishing Guild', 'runmodule.php?module=fishingguild');
            Nav::add('Magic Guild', 'runmodule.php?module=magicguild');
            Nav::add('Ranging Guild', 'runmodule.php?module=rangingguild');
            Nav::add('Warriors Guild', 'runmodule.php?module=warriorsguild');
            Nav::add('Woodcutting Guild', 'runmodule.php?module=woodcuttingguild');

            Translator::tlschema();

            //$args['schemas']['Artisan Square'] = 'module-skills';
            //$args['schemas']['A?Master Artisan'] = 'module-skills';

            try {
                $ref = new \ReflectionClass(Nav::class);
                $prop = $ref->getProperty('sections');
                $prop->setAccessible(true);

                /** @var array<string, mixed> $sections */
                $sections = $prop->getValue();

                $artisanSection = $sections['Artisan Square'] ?? null;
                $guildSection = $sections['Guild Halls'] ?? null;

                if ($artisanSection !== null) {
                    unset($sections['Artisan Square']);
                }

                if ($guildSection !== null) {
                    unset($sections['Guild Halls']);
                }

                if ($artisanSection !== null || $guildSection !== null) {
                    $reordered = [];

                    foreach ($sections as $key => $value) {
                        $reordered[$key] = $value;

                        if ($key === $marketHeader && $artisanSection !== null) {
                            $reordered['Artisan Square'] = $artisanSection;
                            $artisanSection = null;

                            if ($guildSection !== null) {
                                $reordered['Guild Halls'] = $guildSection;
                                $guildSection = null;
                            }

                            continue;
                        }

                        if ($key === 'Tavern Street') {
                            if ($artisanSection !== null) {
                                $reordered['Artisan Square'] = $artisanSection;
                                $artisanSection = null;
                            }

                            if ($guildSection !== null) {
                                $reordered['Guild Halls'] = $guildSection;
                                $guildSection = null;
                            }
                        }
                    }

                    if ($artisanSection !== null) {
                        $reordered['Artisan Square'] = $artisanSection;
                        $artisanSection = null;
                    }

                    if ($guildSection !== null) {
                        $reordered['Guild Halls'] = $guildSection;
                    }

                    $prop->setValue(null, $reordered);
                }
            } catch (\ReflectionException $exception) {
                Output::getInstance()->debug(sprintf('Guild nav reordering failed: %s', $exception->getMessage()));
            }

            

            break;
    }

    return $args;
}

function skills_render_charstats(): void
{
    global $session;

    if (empty($session['user']['loggedin'])) {
        return;
    }

    $acctid = (int) ($session['user']['acctid'] ?? 0);
    if ($acctid <= 0) {
        return;
    }

    $enabledSkills = skills_get_enabled_skills();
    if ($enabledSkills === []) {
        return;
    }

    $playerData = skills_load_player_data($acctid);

    $hookArgs = [
        'skills' => [],
        'enabled' => array_keys($enabledSkills),
        'player' => $playerData,
    ];

    $hookResult = modulehook('skilldisplay', $hookArgs);
    $provided = is_array($hookResult) ? ($hookResult['skills'] ?? []) : [];

    addcharstat('Skills');

    foreach ($enabledSkills as $key => $name) {
        $entry = $provided[$key] ?? null;
        $label = $name;
        $value = isset($playerData[$key])
            ? sprintf('Level %d (%d XP)', $playerData[$key]['level'], $playerData[$key]['experience'])
            : '--';

        if (is_array($entry)) {
            if (isset($entry['label']) && $entry['label'] !== '') {
                $label = $entry['label'];
            }

            if (array_key_exists('value', $entry)) {
                $value = $entry['value'];
            }
        } elseif ($entry !== null) {
            $value = $entry;
        }

        addcharstat($label, $value);
    }
}

function skills_get_settings_definition(): array
{
    $definition = ['Skills Visibility,title'];

    foreach (skills_get_skill_map() as $key => $name) {
        $definition[skills_get_setting_key($key)] = sprintf('Show %s skill in character stats,bool|1', $name);
    }

    return $definition;
}

function skills_get_skill_map(): array
{
    $skills = [
        'construction' => 'Construction',
        'cooking' => 'Cooking',
        'crafting' => 'Crafting',
        'farming' => 'Farming',
        'firemaking' => 'Firemaking',
        'fishing' => 'Fishing',
        'fletching' => 'Fletching',
        'herblore' => 'Herblore',
        'hunter' => 'Hunter',
        'mining' => 'Mining',
        'runecrafting' => 'Runecrafting',
        'smithing' => 'Smithing',
        'summoning' => 'Summoning',
        'woodcutting' => 'Woodcutting',
    ];

    ksort($skills, SORT_NATURAL | SORT_FLAG_CASE);

    return $skills;
}

function skills_get_setting_key(string $skill): string
{
    return sprintf('enable_%s', $skill);
}

function skills_get_enabled_skills(): array
{
    $enabled = [];

    foreach (skills_get_skill_map() as $key => $name) {
        if (skills_is_skill_enabled($key)) {
            $enabled[$key] = $name;
        }
    }

    return $enabled;
}

function skills_is_skill_enabled(string $skill): bool
{
    $settingKey = skills_get_setting_key($skill);

    return (bool) get_module_setting($settingKey);
}

function skills_get_max_level(): int
{
    return 99;
}

function skills_get_max_experience(): int
{
    return 13034431;
}

function skills_clamp_level(int $level): int
{
    return max(0, min($level, skills_get_max_level()));
}

function skills_clamp_experience(int $experience): int
{
    return max(0, min($experience, skills_get_max_experience()));
}

function skills_create_schema(): void
{
    $skillKeys = array_keys(skills_get_skill_map());
    sort($skillKeys, SORT_NATURAL | SORT_FLAG_CASE);

    $table = Database::prefix('skills');
    $accountsTable = Database::prefix('accounts');

    $columns = [
        '`userid` INT UNSIGNED NOT NULL',
    ];

    foreach ($skillKeys as $skill) {
        $columns[] = sprintf('`%s_level` TINYINT UNSIGNED NOT NULL DEFAULT 1', $skill);
        $columns[] = sprintf('`%s_experience` INT UNSIGNED NOT NULL DEFAULT 0', $skill);
    }

    $columns[] = '`updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP';
    $columns[] = 'PRIMARY KEY (`userid`)';
    $columns[] = sprintf('CONSTRAINT `fk_%s_userid` FOREIGN KEY (`userid`) REFERENCES `%s` (`acctid`) ON DELETE CASCADE', $table, $accountsTable);

    $columnsSql = implode(",\n            ", $columns);

    $sql = sprintf(
        "CREATE TABLE IF NOT EXISTS `%s` (\n            %s\n        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        $table,
        $columnsSql
    );

    Database::query($sql);
}

function skills_create_player_row(int $userId): void
{
    if ($userId <= 0) {
        return;
    }

    $table = Database::prefix('skills');
    if (!Database::tableExists($table)) {
        return;
    }

    $sql = sprintf(
        "INSERT INTO `%s` (`userid`) VALUES (%d) ON DUPLICATE KEY UPDATE `updated_at` = `updated_at`",
        $table,
        $userId
    );

    Database::query($sql);
}

function skills_default_player_data(int $userId): array
{
    $data = [
        'userid' => $userId,
        'updated_at' => null,
    ];

    foreach (skills_get_skill_map() as $key => $_name) {
        $data[$key] = [
            'level' => skills_clamp_level(1),
            'experience' => 0,
        ];
    }

    return $data;
}

function skills_normalize_player_row(?array $row, int $userId): array
{
    $data = skills_default_player_data($userId);

    if (!is_array($row)) {
        return $data;
    }

    foreach (skills_get_skill_map() as $key => $_name) {
        $levelColumn = $key . '_level';
        $experienceColumn = $key . '_experience';

        if (array_key_exists($levelColumn, $row)) {
            $data[$key]['level'] = skills_clamp_level((int) $row[$levelColumn]);
        }

        if (array_key_exists($experienceColumn, $row)) {
            $data[$key]['experience'] = skills_clamp_experience((int) $row[$experienceColumn]);
        }
    }

    if (isset($row['updated_at'])) {
        $data['updated_at'] = $row['updated_at'];
    }

    return $data;
}

function skills_load_player_data(int $userId, bool $forceRefresh = false): array
{
    static $cache = [];

    if ($forceRefresh) {
        unset($cache[$userId]);
    }

    if (isset($cache[$userId])) {
        return $cache[$userId];
    }

    $data = skills_default_player_data($userId);

    if ($userId <= 0) {
        return $cache[$userId] = $data;
    }

    $table = Database::prefix('skills');

    if (!Database::tableExists($table)) {
        return $cache[$userId] = $data;
    }

    $sql = sprintf('SELECT * FROM `%s` WHERE `userid` = %d', $table, $userId);
    $result = Database::query($sql);
    $row = Database::fetchAssoc($result);

    if (!$row) {
        skills_create_player_row($userId);
        $result = Database::query($sql);
        $row = Database::fetchAssoc($result);
    }

    if (!$row) {
        return $cache[$userId] = $data;
    }

    return $cache[$userId] = skills_normalize_player_row($row, $userId);
}


