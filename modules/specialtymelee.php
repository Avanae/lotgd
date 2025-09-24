<?php

use Lotgd\MySQL\Database;

function specialtymelee_getmoduleinfo()
{
    $info = array(
        "name" => "Specialty - Melee",
        "author" => "`7A`tv`7a`tn`7a`te",
        "version" => "1.0",
        "download" => "core_module",
        "category" => "Specialties",
        "prefs" => array(
            "Specialty - Melee User Prefs,title",
            "skill" => "Skill points in Melee,int|0",
            "uses" => "Uses of Melee allowed,int|0",
        ),
    );
    return $info;
}

function specialtymelee_install()
{
    module_addhook("choose-specialty");
    module_addhook("set-specialty");
    module_addhook("fightnav-specialties");
    module_addhook("apply-specialties");
    module_addhook("newday");
    module_addhook("incrementspecialty");
    module_addhook("specialtynames");
    module_addhook("specialtymodules");
    module_addhook("specialtycolor");
    module_addhook("dragonkill");
    return true;
}

function specialtymelee_uninstall()
{
    $sql = "UPDATE " . Database::prefix("accounts") . " SET specialty='' WHERE specialty='ME'";
    Database::query($sql);
    return true;
}

function specialtymelee_dohook($hookname, $args)
{
    global $session;

    $spec  = "ME";
    $name  = "Melee";
    $ccode = "`4";
    $resline = '';
    switch ($hookname) {
        case "dragonkill":
            set_module_pref("uses", 0);
            set_module_pref("skill", 0);
            break;

        case "choose-specialty":
            if (empty($session['user']['specialty']) || $session['user']['specialty'] == '0') {
                $t1 = translate_inline("Training to be one of the greatest warriors");
                $t2 = appoencode(translate_inline("$ccode$name`0"));

                addnav("$ccode$name`0", "newday.php?setspecialty=$spec$resline");
                rawoutput("<a href='newday.php?setspecialty=$spec$resline'>$t1 ($t2)</a><br>");
                addnav("", "newday.php?setspecialty=$spec$resline");
            }
            break;

        case "set-specialty":
            if ($session['user']['specialty'] === $spec) {
                page_header($name);
                output("`4You were always the first to leap into every scuffle, fists swinging before anyone else had even thought to react.");
                output("Your childhood was filled with bruises and broken toys, each one a lesson in strength and survival.");
                output("By the time you reached adolescence, you could fell larger opponents with a single strike, and you reveled in the thrill of close combat, unafraid of danger or pain.");
                output("Villagers whispered about your scars, claiming they were badges of honor earned in countless battles against beasts and bullies alike.");
                output("Now, armed with nothing but your fists and unyielding resolve, you step into the world eager to prove that no foe is too strong, and no challenge too great.");
            }
            break;

        case "specialtycolor":
            $args[$spec] = $ccode;
            break;

        case "specialtynames":
            $args[$spec] = translate_inline($name);
            break;

        case "specialtymodules":
            $args[$spec] = "specialtymelee";
            break;

        case "incrementspecialty":
            if ($session['user']['specialty'] === $spec) {
                $new = (int)(get_module_pref("skill") ?? 0) + 1;
                set_module_pref("skill", $new);

                $c = $args['color'] ?? '';
                $name_translated = translate_inline($name);

                output("`n%sYou gain a level in %s%s to `#%s%s!", $c, $name_translated, $c, $new, $c);

                $x = $new % 3;
                if ($x === 0) {
                    output("`n`^You gain an extra Melee use!`n");
                    set_module_pref("uses", (int)(get_module_pref("uses") ?? 0) + 1);
                } else {
                    output("`n`^%s more skill levels until you gain an extra use point.`n", (3 - $x));
                }

                output_notl("`0");
            }
            break;

        case "newday":
            $bonus = (int)(getsetting("specialtybonus", 1));
            if ($session['user']['specialty'] === $spec) {
                $dkBonus    = floor($session['user']['dragonkills'] / 2);
                $levelBonus = floor($session['user']['level'] / 5);
                $amt        = 1 + $dkBonus + $levelBonus + $bonus;

                set_module_pref("uses", $amt);
                output("`n`2For being trained in %s%s`2, you receive `^%s`2 Melee uses today.`0`n", $ccode, $name, $amt);
            }
            break;

        case "fightnav-specialties":
            $uses   = (int)(get_module_pref("uses") ?? 0);
            $script = $args['script'] ?? '';

            if ($uses > 0) {
                addnav(array("$ccode$name (`^%s`& uses left)`0", $uses), "");
                if ($uses >= 2)
                    addnav("`4Warstrike `0(`22`0)", $script . "op=fight&skill=$spec&l=1");
                if ($uses >= 3)
                    addnav("`&Judgement `0(`23`0)", $script . "op=fight&skill=$spec&l=2");
                if ($uses >= 5)
                    addnav("`\$Blood Sacrifice `0(`25`0)", $script . "op=fight&skill=$spec&l=3");
            }
            break;

        case "apply-specialties":
            $skill = httpget('skill') ?? '';
            $l     = (int)(httpget('l') ?? 0);
            $uses  = (int)(get_module_pref("uses") ?? 0);

            if ($skill === $spec && $uses >= $l) {
                switch ($l) {
                    case 1:
                        apply_buff('melee1', [
                            "name" => "`4Warstrike",
                            "rounds" => 1,
                            "minbadguydamage" => 6,
                            "maxbadguydamage" => 12,
                            "effectmsg" => "`)You strike {badguy} for `^{damage}`) damage with Warstrike!",
                            "schema" => "module-specialtymelee"
                        ]);
                        break;
                    case 2:
                        apply_buff('melee2', [
                            "name" => "`&Judgement",
                            "rounds" => 1,
                            "minbadguydamage" => 10,
                            "maxbadguydamage" => 18,
                            "effectmsg" => "`)You deliver Judgement to {badguy} for `^{damage}`) damage!",
                            "schema" => "module-specialtymelee"
                        ]);
                        break;
                    case 3:
                        apply_buff('melee3', [
                            "name" => "`\$Blood Sacrifice",
                            "rounds" => 1,
                            "minbadguydamage" => 20,
                            "maxbadguydamage" => 35,
                            "effectmsg" => "`)You unleash Blood Sacrifice on {badguy} for `^{damage}`) damage!",
                            "schema" => "module-specialtymelee"
                        ]);
                        break;
                }
                set_module_pref("uses", $uses - $l);
            } else {
                apply_buff('melee0', [
                    "rounds" => 1,
                    "startmsg" => "Exhausted, you try your specialty, but fail to strike effectively.",
                    "schema" => "module-specialtymelee"
                ]);
            }
            break;
    }

    return $args;
}

function specialtymelee_run()
{
}
