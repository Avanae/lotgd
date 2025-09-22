<?php
declare(strict_types=1);


function specialtysystem_air_getmoduleinfo(): array {
	$info = array(
		"name" => "Specialty System - Air",
		"author" => "`2Oliver`0",
		"version" => "1.0",
		"download" => "",
		"category" => "Specialty System",
		"requires"=> array(
			"specialtysystem"=>"1.0|Specialty System by `2Oliver Brendel",
			),
	);
	return $info;
}

function specialtysystem_air_install(): bool {
	module_addhook("specialtysystem-register");
	return true;
}

/**
 * Uninstall the module.
 *
 * @return bool
 */
function specialtysystem_air_uninstall(): bool {
	require_once("modules/specialtysystem/uninstall.php");
	specialtysystem_uninstall("specialtysystem_air");
	return true;
}

/**
 * Build fight navigation entries.
 *
 * @return array
 */
function specialtysystem_air_fightnav(): array {
	global $session;
	require_once("modules/specialtysystem/functions.php");
	$uses=specialtysystem_availableuses("specialtysystem_air");
	$name=translate_inline('Fuuton Ninjutsu (air)');
	tlschema('module-specialtysystem_air');
	if ($uses > 0) {
		specialtysystem_addfightheadline($name, 
$uses,specialtysystem_getskillpoints("specialtysystem_air"));
		specialtysystem_addfightnav("Kaze no Yoroi","air1&cost=1",1);
	}
	if ($uses > 2) {
		specialtysystem_addfightnav("Kamaitachi no Jutsu","air2&cost=3",3);
	}
	if ($uses > 5) {
		specialtysystem_addfightnav("Kaze Kiri","air3&cost=6",6);
	}
	if ($uses > 9) {
		specialtysystem_addfightnav("Daikamaitachi no Jutsu","air4&cost=10",10);
	}
	if ($uses > 14) {
		specialtysystem_addfightnav("Kaze no Yaiba","air5&cost=15",15);
	}
	if ($uses > 17) {
		specialtysystem_addfightnav("Fuuton - Tatsu no Oshigoto","air6&cost=18",18);
	}
	if ($uses > 19 && $session['user']['hashorse']==12) { //shukaku
		specialtysystem_addfightnav("Renkuudan","renkuudan&cost=20",20);
	}
	tlschema();
	return specialtysystem_getfightnav();
}

/**
 * Apply a selected specialty skill.
 *
 * @param string $skillname
 */
function specialtysystem_air_apply(string $skillname): void {
	global $session;
	require_once("modules/specialtysystem/functions.php");
	switch($skillname){
		case "air1":
			apply_buff('air1',array(
				"startmsg"=>"`i`2Kaze no Yoroi!`i`n`qYou `gcreate a barrier of air 
around yourself.`b",
				"name"=>"`2Kaze no Yoroi",
				"rounds"=>5,
				"wearoff"=>"The air that surrounds you settles.",
				"defmod"=>1.05,
				"roundmsg"=>"You are protected by the air!",
				"schema"=>"module-specialtysystem_air"
			));
			break;
		case "air2":
			apply_buff('air2',array(
				"startmsg"=>"`i`2Kamaitachi no Jutsu!`i`n`qYou `gswing your weapon and 
create a airstorm that slices through everything in the area.",
				"name"=>"`2Kamaitachi `gno `2Jutsu",
				"rounds"=>3,
				"wearoff"=>"The airstorm settles.",
				"areadamage"=>true,
				"minbadguydamage"=>5+$session['user']['dragonkills'],
				"maxbadguydamage"=>15+$session['user']['dragonkills'],
				"minioncount"=>3,
				"effectmsg"=>"{badguy} suffers {damage} damage from cuts!",
				"schema"=>"module-specialtysystem_air"
			));
			break;
		case "air3":
			apply_buff('air3',array(
				"startmsg"=>"`i`2K`ga`2z`ge `2K`gi`2r`gi!`i`n`qYou `gswing your weapon 
and send a blade of air at {badguy}.",
				"name"=>"`2K`ga`2z`ge `2K`gi`2r`gi",
				"rounds"=>1,
				"minbadguydamage"=>15+$session['user']['dragonkills'],
				"maxbadguydamage"=>45+$session['user']['dragonkills'],
				"minioncount"=>1,
				"effectmsg"=>"{badguy} suffers {damage} damage from the cutting air!",
				"schema"=>"module-specialtysystem_air"
			));
			break;
		case "air4":
			apply_buff('air4',array(
				"startmsg"=>"`i`2Daikamaitachi no Jutsu!`i`n`qYou `gswing your weapon 
and create a huge airstorm that slices through everything in the area.",
				"name"=>"`2Daikamaitachi `gno `2Jutsu",
				"rounds"=>5,
				"wearoff"=>"The airstorm settles.",
				"areadamage"=>true,
				"minbadguydamage"=>15+$session['user']['dragonkills'],
				"maxbadguydamage"=>30+$session['user']['dragonkills'],
				"minioncount"=>3,
				"effectmsg"=>"{badguy} suffers {damage} damage from cuts!",
				"schema"=>"module-specialtysystem_air"
			));
			break;
		case "air5":
			apply_buff('air5',array(
				"startmsg"=>"`i`2Kaze `gno `2Yaiba!`i`n`qYou `gsend an unstoppable blade 
of air at {badguy}.",
				"name"=>"`2Kaze `gno `2Yaiba",
				"rounds"=>1,
				"areadamage"=>true,
				"minbadguydamage"=>80+$session['user']['dragonkills'],
				"maxbadguydamage"=>120+$session['user']['dragonkills'],
				"minioncount"=>1,
				"effectmsg"=>"{badguy} suffers {damage} damage from the cutting air!",
				"schema"=>"module-specialtysystem_air"
			));
			break;
		case "air6":
			apply_buff('air6',array(
				"startmsg"=>"`i`gFuuton - `2Tatsu `gno `2Oshigoto!`i`n`qYou `gsummon a 
powerful tornado from the sky.",
				"name"=>"`gFuuton - `2Tatsu `gno `2Oshigoto",
				"rounds"=>5,
				"wearoff"=>"The tornado settles.",
				"areadamage"=>true,
				"minbadguydamage"=>50+$session['user']['dragonkills'],
				"maxbadguydamage"=>90+$session['user']['dragonkills'],
				"minioncount"=>5,
				"effectmsg"=>"{badguy} suffers {damage} damage from being torn apart by 
the violent air!",
				"schema"=>"module-specialtysystem_air"
			));
			break;
		case "renkuudan":
			apply_buff('renkuudan',array(
				"startmsg"=>"`i`lRen`gku`ldan!`i`n`q`6Shukaku `ltakes a deep breath 
and shoots a large ball of compressed air and chakra at {badguy}!",
				"name"=>"`lRen`gku`ldan",
				"rounds"=>1,
				"areadamage"=>true,
				"minbadguydamage"=>120+$session['user']['dragonkills']*5,
				"maxbadguydamage"=>160+$session['user']['dragonkills']*5,
				"minioncount"=>1,
				"effectmsg"=>"`q{badguy}`q suffers {damage} damage!",
				"schema"=>"module-specialtysystem_air"
			));

	}
	specialtysystem_incrementuses("specialtysystem_air",httpget('cost'));
	return;
}

/**
 * Handle module hooks.
 *
 * @param string $hookname
 * @param array $args
 * @return array
 */
function specialtysystem_air_dohook(string $hookname, array $args): array {
	switch ($hookname) {
	case "specialtysystem-register":
		$args[]=array(
			"spec_name"=>'Fuuton Ninjutsu',
			"spec_colour"=>'`2',
			"spec_shortdescription"=>'`2The cutting air!',
			"spec_longdescription"=>'`5Growing up, you always loved to feel the air 
blowing in your face ... so you studied fuuton ninjutsu, sweeping everything 
in your path away.',
			"modulename"=>'specialtysystem_air',
			"fightnav_active"=>'1',
			"newday_active"=>'0',
			"dragonkill-active"=>'0',
			"dragonkill_minimum_requirement"=>'12',
			"stat_requirements"=>array(
				"intelligence"=>14,
				"wisdom"=>12,
				"dexterity"=>15,
				),
			);
		break;
	}
	return $args;
}

/**
 * Module runtime.
 */
function specialtysystem_air_run(): void {
}
?>

