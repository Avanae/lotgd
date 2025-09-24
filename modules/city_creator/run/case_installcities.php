<?php
$sop = httpget('sop');

if ($sop == 'install') {
    $cities = httppost('cities');
    $allcities = httppost('allcities');

    $fields = "`cityname`,`citytype`,`cityauthor`,`citytext`,`stabletext`,`armortext`,`weaponstext`,`mercenarycamptext`,`cityblocknavs`,`module`";

    $city_data = [];

    // city0: Romar
    if (($cities['city0'] ?? 0) == 1 || $allcities == 1) {
        $city_data[] = [
            'cityname' => 'Romar',
            'citytype' => 'City',
            'cityauthor' => 'Eric Stevens',
            'citytext' => serialize([
                'title' => 'Romar, City of Men',
                'text' => "`&`c`bRomar, City of Men`b`c`n`7You are standing in the heart of Romar...",
                'clock' => "`n`7The great sundial at the heart of the city reads `&%s`7.`n",
                'newest1' => "`n`7As you wander your new home...",
                'newest2' => "`n`7Wandering the village, jaw agape, is `&%s`7.",
                'sayline' => 'says',
                'talk' => "`n`&Nearby some villagers talk:`n",
                'stablename' => "Bertold's Bestiary",
                'gatenav' => 'Village Gates'
            ]),
            'stabletext' => serialize([
                'title' => "Bertold's Bestiary",
                'desc' => "`6Just outside the outskirts of the village, a training area and riding range has been set up...",
                'lad' => 'friend',
                'lass' => 'friend'
            ]),
            'armortext' => '',
            'weaponstext' => '',
            'mercenarycamptext' => serialize(['train'=>1,'mercenarycamp'=>1,'inn'=>1,'rock'=>1,'clans'=>1,'hof'=>1]),
            'cityblocknavs' => '',
            'module' => 'city_creator'
        ];
    }

    // city1: Glorfindal
    if (($cities['city1'] ?? 0) == 1 || $allcities == 1) {
        $city_data[] = [
            'cityname' => 'Glorfindal',
            'citytype' => 'City',
            'cityauthor' => 'Eric Stevens',
            'citytext' => serialize([
                'title' => 'Glorfindal City',
                'text' => "`^`c`bGlorfindal, Ancestral Home of the Elves`b`c`n`6You stand on the forest floor...",
                'clock' => "`n`6Capturing one of the tiny lights, you peer delicately into your hands...`n",
                'newest1' => "`n`6You stare around in wonder...",
                'newest2' => "`n`6Looking at the buildings high above is `^%s`6.",
                'sayline' => 'converses',
                'talk' => "`n`^Nearby some villagers converse:`n",
                'gatenav' => 'Village Gates',
                'fightnav' => 'Honor Avenue',
                'marketnav' => 'Mercantile',
                'tavernnav' => 'Towering Halls',
                'weaponshop' => "Gadriel's Weapons"
            ]),
            'stabletext' => '',
            'armortext' => serialize([
                'title' => "Gadriel's Weapons",
                'desc' => "`7The Elven Ranger pads gracefully towards you as you enter..."
            ]),
            'weaponstext' => '',
            'mercenarycamptext' => serialize(['train'=>1,'mercenarycamp'=>1,'inn'=>1,'rock'=>1,'stables'=>1,'clans'=>1,'hof'=>1]),
            'cityblocknavs' => '',
            'module' => 'city_creator'
        ];
    }

    // city2: Qexelcrag
    if (($cities['city2'] ?? 0) == 1 || $allcities == 1) {
        $city_data[] = [
            'cityname' => 'Qexelcrag',
            'citytype' => 'Caverns',
            'cityauthor' => 'Eric Stevens',
            'citytext' => serialize([
                'title' => 'The Caverns of Qexelcrag',
                'text' => "`#`c`bCavernous Qexelcrag, home of the dwarves`b`c`nDeep in the heart of Mount Qexelcrag lie the ancient caverns...",
                'clock' => "`n`3A cleverly crafted crystal prism allows a beam of light to fall through a crack in the great ceiling...`n",
                'newest1' => "`n`3Being rather new to this life...",
                'newest2' => "`n`3Pounding an empty stein against a yet unopened barrel of ale is `#%s`3.",
                'sayline' => 'brags',
                'talk' => "`n`#Nearby some villagers brag:`n",
                'gatenav' => 'Village Gates',
                'fightnav' => "Th' Arena",
                'marketnav' => 'Ancient Treasures',
                'tavernnav' => 'Ale Square',
                'mercenarycamp' => 'A Bestiarium'
            ]),
            'stabletext' => '',
            'armortext' => '',
            'weaponstext' => serialize([
                'title' => 'A Bestiarium',
                'desc' => "`5You are making your way to the Bestiarium deep in the bowels of the dwarven mountain stronghold..."
            ]),
            'mercenarycamptext' => serialize(['train'=>1,'inn'=>1,'rock'=>1,'stables'=>1,'clans'=>1,'hof'=>1]),
            'cityblocknavs' => '',
            'module' => 'city_creator'
        ];
    }

    // city3: Glukmoore
    if (($cities['city3'] ?? 0) == 1 || $allcities == 1) {
        $city_data[] = [
            'cityname' => 'Glukmoore',
            'citytype' => 'Swamps',
            'cityauthor' => 'Eric Stevens',
            'citytext' => serialize([
                'title' => 'The Swamps of Glukmoore',
                'text' => "`@`b`cGlukmoore, Home of the Trolls`c`b`n`2You are standing in a pile of mud...",
                'clock' => "`n`2Based on what's left of the morning's kill, you can tell that it is `@%s`2.`n",
                'newest1' => "`n`2You wander the village, picking your teeth with the tiny rib of one of your siblings...",
                'newest2' => "`n`2Picking their teeth with a sliver of bone is `@%s`2.",
                'sayline' => 'squabbles',
                'talk' => "`n`@Nearby some villagers squabble:`n",
                'gatenav' => 'Village Gates',
                'fightnav' => 'Barshem Gud',
                'marketnav' => "Da Gud Stuff",
                'tavernnav' => "Eatz n' Such",
                'infonav' => 'Da Infoz'
            ]),
            'stabletext' => '',
            'armortext' => '',
            'weaponstext' => '',
            'mercenarycamptext' => serialize(['train'=>1,'mercenarycamp'=>1,'inn'=>1,'rock'=>1,'stables'=>1,'clans'=>1,'hof'=>1]),
            'cityblocknavs' => '',
            'module' => 'city_creator'
        ];
    }

    // Insert all selected cities
    foreach ($city_data as $city) {
        $values = [];
        foreach ($city as $val) {
            $values[] = "'" . addslashes($val) . "'";
        }
        $sql = "INSERT INTO " . db_prefix('cities') . " ($fields) VALUES (" . implode(',', $values) . ")";
        db_query($sql);
        if (db_affected_rows() > 0) output("`&The City of {$city['cityname']} has been `@successfully `&installed.`0`n");
        else output("`&The City of {$city['cityname']} `$failed `&to install.`0`n");
    }

} else {
    require_once('lib/showform.php');

    output("`3Which of the following cities do you wish to install?.`n`n");

    $row = ['allcities'=>'','cities'=>[]];
    $form = [
        'Install Which Cities?,title',
        'allcities'=>'Install ALL cities?,bool',
        'cities'=>'Cities:,checklist,city0,'.appoencode('`Q').'Romar,city1,'.appoencode('`2').'Glorfindal,city2,'.appoencode('`e').'Qexelcrag,city3,'.appoencode('`@').'Glukmoore'
    ];

    rawoutput('<form action="runmodule.php?module=city_creator&op=installcities&sop=install" method="POST">');
    addnav('', 'runmodule.php?module=city_creator&op=installcities&sop=install');
    showform($form,$row);
    rawoutput('</form>');
}

addnav('Editor');
addnav('Add a City',$from.'&op=edit');
addnav('Main Page',$from);
?>