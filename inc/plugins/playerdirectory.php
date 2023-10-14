<?php
//error_reporting ( -1 );
//ini_set ( 'display_errors', true );
// Diagramm mithilfe der open source Lösung Chart.js (https://www.chartjs.org/) gebaut
// Direktzugriff auf die Datei aus Sicherheitsgründen sperren
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

// HOOKS
$plugins->add_hook('admin_config_settings_change', 'playerdirectory_settings_change');
$plugins->add_hook('admin_settings_print_peekers', 'playerdirectory_settings_peek');
$plugins->add_hook("global_intermediate", "playerdirectory_menu");
$plugins->add_hook("misc_start", "playerdirectory_misc");
$plugins->add_hook("fetch_wol_activity_end", "playerdirectory_online_activity");
$plugins->add_hook("build_friendly_wol_location_end", "playerdirectory_online_location");
$plugins->add_hook("usercp_options_start", "playerdirectory_usercp_options");
$plugins->add_hook("usercp_do_options_end", "playerdirectory_usercp_do_options");
$plugins->add_hook("admin_user_action_handler", "playerdirectory_admin_user_action_handler");
$plugins->add_hook("admin_user_permissions", "playerdirectory_admin_user_permissions");
$plugins->add_hook("admin_user_menu", "playerdirectory_admin_user_menu");
$plugins->add_hook("admin_load", "playerdirectory_admin_manage");
 
// Die Informationen, die im Pluginmanager angezeigt werden
function playerdirectory_info(){

    global $db;

	$playerdirectory_info = array(
		"name"		=> "Spielerverzeichnis und Statistiken",
		"description"	=> "Dieses Plugin erstellt eine Übersicht von allen Usern mit ihren Charakteren. Zusätzlich wird für jeden Spieler und Charakter eine persönliche Statistik erstellt.<br>Diagramm wurden mithilfe der open source Lösung <a href=\"https://www.chartjs.org/\"> Chart.js</a> gebaut.",
		"website"	=> "https://github.com/little-evil-genius/Spielerverzeichnis",
		"author"	=> "little.evil.genius",
		"authorsite"	=> "https://storming-gates.de/member.php?action=profile&uid=1712",
		"version"	=> "1.2",
		"compatibility" => "18*"
	);

    $result = $db->simple_select("settinggroups", "gid", "name = 'playerdirectory'");
    $set = $db->fetch_array($result);
    if (!empty($set)) {
        $playerdirectory_info['description'] .= '<div style="float: right; margin-top: 5px;">';
        $playerdirectory_info['description'] .= '<img src="styles/default/images/icons/custom.png" alt="" ';
        $playerdirectory_info['description'] .= 'style="margin-right: 5px;" /><a href="';
        $playerdirectory_info['description'] .= 'index.php?module=config-settings&amp;action=change&amp;gid=';
        $playerdirectory_info['description'] .= (int)$set['gid'].'" style="margin-right: 10px;">Einstellungen</a>';
        $playerdirectory_info['description'] .= '<hr style="margin-bottom: 5px;" /></div>';
    }
            
    return $playerdirectory_info;
}
 
// Diese Funktion wird aufgerufen, wenn das Plugin installiert wird (optional).
function playerdirectory_install(){

    global $db, $cache, $mybb, $lang;

    // SPRACHDATEI
    $lang->load("playerdirectory");

    // Accountswitcher muss vorhanden sein
    if (!function_exists('accountswitcher_is_installed')) {
		flash_message($lang->playerdirectory_error_accountswitcher, 'error');
		admin_redirect('index.php?module=config-plugins');
	}

    // DATENBANKSPALTE USERS
	$db->query("ALTER TABLE `".TABLE_PREFIX."users` ADD `playerdirectory_playerstat` INT(1) NOT NULL DEFAULT '0';");
	$db->query("ALTER TABLE `".TABLE_PREFIX."users` ADD `playerdirectory_playerstat_guest` INT(1) NOT NULL DEFAULT '0';");
	$db->query("ALTER TABLE `".TABLE_PREFIX."users` ADD `playerdirectory_characterstat` INT(1) NOT NULL DEFAULT '0';");
	$db->query("ALTER TABLE `".TABLE_PREFIX."users` ADD `playerdirectory_characterstat_guest` INT(1) NOT NULL DEFAULT '0';");

    // DATENBANK ERSTELLEN
    $db->query("CREATE TABLE ".TABLE_PREFIX."playerdirectory_statistics(
        `psid` int(10) unsigned NOT NULL AUTO_INCREMENT,
        `name` VARCHAR(500) COLLATE utf8_general_ci NULL,
        `identification` VARCHAR(500) COLLATE utf8_general_ci NULL,
        `type` int(1) unsigned NOT NULL,
        `legend` int(1) unsigned NOT NULL DEFAULT '0',
        `field` VARCHAR(500) COLLATE utf8_general_ci NULL,
        `ignor_option` VARCHAR(500) COLLATE utf8_general_ci NULL,
        `usergroups` VARCHAR(500) COLLATE utf8_general_ci NULL,
        `group_option` VARCHAR(500) COLLATE utf8_general_ci NULL,
        `colors` VARCHAR(5000) COLLATE utf8_general_ci NOT NULL,
        `custom_properties` int(1) unsigned NOT NULL DEFAULT '0',
        PRIMARY KEY(`psid`),
        KEY `psid` (`psid`)
        )
        ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci AUTO_INCREMENT=1 "
    );

	// EINSTELLUNGEN HINZUFÜGEN
    $maxdisporder = $db->fetch_field($db->query("SELECT MAX(disporder) FROM ".TABLE_PREFIX."settinggroups"), "MAX(disporder)");
	$setting_group = array(
		'name'          => 'playerdirectory',
		'title'         => $lang->playerdirectory_setting_title,
		'description'   => $lang->playerdirectory_setting_desc,
		'disporder'     => $maxdisporder+1,
		'isdefault'     => 0
	);
			
	$gid = $db->insert_query("settinggroups", $setting_group); 
			
	$setting_array = array(
		'playerdirectory_directory' => array(
			'title' => $lang->playerdirectory_setting_directory,
			'description' => $lang->playerdirectory_setting_directory_desc,
			'optionscode' => 'yesno',
			'value' => '0', // Default
			'disporder' => 1
		),
		'playerdirectory_directory_guest' => array(
			'title' => $lang->playerdirectory_setting_directory_guest,
			'description' => $lang->playerdirectory_setting_directory_guest_desc,
			'optionscode' => 'yesno',
			'value' => '0', // Default
			'disporder' => 2
		),
		'playerdirectory_directory_multipage' => array(
			'title' => $lang->playerdirectory_setting_directory_multipage,
			'description' => $lang->playerdirectory_setting_directory_multipage_desc,
			'optionscode' => 'numeric',
			'value' => '20', // Default
			'disporder' => 3
		),
		'playerdirectory_directory_teamaccounts' => array(
			'title' => $lang->playerdirectory_setting_directory_teamaccounts,
			'description' => $lang->playerdirectory_setting_directory_teamaccounts_desc,
			'optionscode' => 'text',
			'value' => '1', // Default
			'disporder' => 4
		),
		'playerdirectory_playerstat' => array(
			'title' => $lang->playerdirectory_setting_playerstat,
			'description' => $lang->playerdirectory_setting_playerstat_desc,
			'optionscode' => 'yesno',
			'value' => '0', // Default
			'disporder' => 5
		),
		'playerdirectory_playerstat_guest' => array(
			'title' => $lang->playerdirectory_setting_playerstat_guest,
			'description' => $lang->playerdirectory_setting_playerstat_guest_desc,
			'optionscode' => 'yesno',
			'value' => '0', // Default
			'disporder' => 6
		),
		'playerdirectory_characterstat' => array(
			'title' => $lang->playerdirectory_setting_characterstat,
			'description' => $lang->playerdirectory_setting_characterstat_desc,
			'optionscode' => 'yesno',
			'value' => '0', // Default
			'disporder' => 7
		),
		'playerdirectory_characterstat_guest' => array(
			'title' => $lang->playerdirectory_setting_characterstat_guest,
			'description' => $lang->playerdirectory_setting_characterstat_guest_desc,
			'optionscode' => 'yesno',
			'value' => '0', // Default
			'disporder' => 8
		),
		'playerdirectory_profilfeldsystem' => array(
			'title' => $lang->playerdirectory_setting_profilfeldsystem,
			'description' => $lang->playerdirectory_setting_profilfeldsystem_desc,
			'optionscode' => 'select\n0='.$lang->playerdirectory_setting_profilfeldsystem_profilefield.'\n1='.$lang->playerdirectory_setting_profilfeldsystem_applicationfield.'\n2='.$lang->playerdirectory_setting_profilfeldsystem_both,
			'value' => '0', // Default
			'disporder' => 9
		),
		'playerdirectory_playername' => array(
			'title' => $lang->playerdirectory_setting_playername,
			'description' => $lang->playerdirectory_setting_playername_desc,
			'optionscode' => 'text',
			'value' => '4', // Default
			'disporder' => 10
		),
		'playerdirectory_avatar_default' => array(
			'title' => $lang->playerdirectory_setting_avatar_default,
			'description' => $lang->playerdirectory_setting_avatar_default_desc,
			'optionscode' => 'text',
			'value' => 'default_avatar.png', // Default
			'disporder' => 11
		),
		'playerdirectory_avatar_guest' => array(
			'title' => $lang->playerdirectory_setting_avatar_guest,
			'description' => $lang->playerdirectory_setting_avatar_guest_desc,
			'optionscode' => 'yesno',
			'value' => '0', // Default
			'disporder' => 12
		),
		'playerdirectory_birthday' => array(
			'title' => $lang->playerdirectory_setting_birthday,
			'description' => $lang->playerdirectory_setting_birthday_desc,
			'optionscode' => 'select\n0='.$lang->playerdirectory_setting_birthday_field.'\n1='.$lang->playerdirectory_setting_birthday_mybb.'\n2='.$lang->playerdirectory_setting_birthday_age,
			'value' => '0', // Default
			'disporder' => 13
		),
		'playerdirectory_birthday_field' => array(
			'title' => $lang->playerdirectory_setting_birthday_field_id,
			'description' => $lang->playerdirectory_setting_birthday_field_id_desc,
			'optionscode' => 'text',
			'value' => '', // Default
			'disporder' => 14
		),
		'playerdirectory_age_field' => array(
			'title' => $lang->playerdirectory_setting_birthday_age_field,
			'description' => $lang->playerdirectory_setting_birthday_age_field_desc,
			'optionscode' => 'text',
			'value' => '', // Default
			'disporder' => 15
		),
		'playerdirectory_inplayday' => array(
			'title' => $lang->playerdirectory_setting_inplayday,
			'description' => $lang->playerdirectory_setting_inplayday_desc,
			'optionscode' => 'text',
			'value' => '31.03.2020', // Default
			'disporder' => 16
		),
		'playerdirectory_inplaytracker' => array(
			'title' => $lang->playerdirectory_setting_inplaytracker,
			'description' => $lang->playerdirectory_setting_inplaytracker_desc,
			'optionscode' => 'select\n0='.$lang->playerdirectory_setting_inplaytracker_jule2.'\n1='.$lang->playerdirectory_setting_inplaytracker_jule3.'\n2='.$lang->playerdirectory_setting_inplaytracker_katja.'\n3='.$lang->playerdirectory_setting_inplaytracker_lara.'\n4='.$lang->playerdirectory_setting_inplaytracker_ales,
			'value' => '1', // Default
			'disporder' => 17
		),
		'playerdirectory_inplaystat' => array(
			'title' => $lang->playerdirectory_setting_inplaystat,
			'description' => $lang->playerdirectory_setting_inplaystat_desc,
			'optionscode' => 'select\n0='.$lang->playerdirectory_setting_inplaystat_none.'\n1='.$lang->playerdirectory_setting_inplaystat_bar.'\n2='.$lang->playerdirectory_setting_inplaystat_word,
			'value' => '0', // Default
			'disporder' => 18
		),
		'playerdirectory_scenestat' => array(
			'title' => $lang->playerdirectory_setting_scenestat,
			'description' => $lang->playerdirectory_setting_scenestat_desc,
			'optionscode' => 'select\n0='.$lang->playerdirectory_setting_scenestat_none.'\n1='.$lang->playerdirectory_setting_scenestat_bar.'\n2='.$lang->playerdirectory_setting_scenestat_pie.'\n3='.$lang->playerdirectory_setting_scenestat_word,
			'value' => '0', // Default
			'disporder' => 19
		),
		'playerdirectory_scenestat_legend' => array(
			'title' => $lang->playerdirectory_setting_scenestat_legend,
			'description' => $lang->playerdirectory_setting_scenestat_legend_desc,
			'optionscode' => 'yesno',
			'value' => '0', // Default
			'disporder' => 20
		),
		'playerdirectory_poststat' => array(
			'title' => $lang->playerdirectory_setting_poststat,
			'description' => $lang->playerdirectory_setting_poststat_desc,
			'optionscode' => 'select\n0='.$lang->playerdirectory_setting_poststat_none.'\n1='.$lang->playerdirectory_setting_poststat_bar.'\n2='.$lang->playerdirectory_setting_poststat_pie.'\n3='.$lang->playerdirectory_setting_poststat_word,
			'value' => '0', // Default
			'disporder' => 21
		),
		'playerdirectory_poststat_legend' => array(
			'title' => $lang->playerdirectory_setting_poststat_legend,
			'description' => $lang->playerdirectory_setting_poststat_legend_desc,
			'optionscode' => 'yesno',
			'value' => '0', // Default
			'disporder' => 22
		),
		'playerdirectory_colorstat' => array(
			'title' => $lang->playerdirectory_setting_colorstat,
			'description' => $lang->playerdirectory_setting_colorstat_desc,
			'optionscode' => 'textarea',
			'value' => '#8baddc, #5e7596, #70aab5, #365358, #90cec1, #5a9286, #afd49b, #6d875f, #cdbca5, #887d6e, #8f99cd, #697198, #6c6c6c, #4b4b4b, #fff2ca, #fae29a, #fccb8d, #f7b284, #946b6e, #50383a', // Default
			'disporder' => 23
		),
		'playerdirectory_inplayquotes' => array(
			'title' => $lang->playerdirectory_setting_inplayquotes,
			'description' => $lang->playerdirectory_setting_inplayquotes_desc,
			'optionscode' => 'yesno',
			'value' => '0', // Default
			'disporder' => 24
		),
		'playerdirectory_lists' => array(
			'title' => $lang->playerdirectory_setting_lists,
			'description' => $lang->playerdirectory_setting_lists_desc,
			'optionscode' => 'text',
			'value' => 'lists.php', // Default
			'disporder' => 25
		),
		'playerdirectory_lists_menu' => array(
			'title' => $lang->playerdirectory_setting_lists_menu,
			'description' => $lang->playerdirectory_setting_lists_menu_desc,
			'optionscode' => 'select\n0='.$lang->playerdirectory_setting_lists_menu_none.'\n1='.$lang->playerdirectory_setting_lists_menu_jule.'\n2='.$lang->playerdirectory_setting_lists_menu_own,
			'value' => '0', // Default
			'disporder' => 26
		),
        'playerdirectory_lists_menu_tpl' => array(
            'title' => $lang->playerdirectory_setting_lists_menu_tpl,
            'description' => $lang->playerdirectory_setting_lists_menu_tpl_desc,
            'optionscode' => 'text',
            'value' => 'lists_nav', // Default
            'disporder' => 27
        ),
	);
			
	foreach($setting_array as $name => $setting)
	{
		$setting['name'] = $name;
		$setting['gid']  = $gid;
		$db->insert_query('settings', $setting);
	}
	rebuild_settings();

	// TEMPLATES ERSTELLEN
	// Template Gruppe für jedes Design erstellen
    $templategroup = array(
        "prefix" => "playerdirectory",
        "title" => $db->escape_string("Spielerverzeichnis und Statistiken"),
    );
    $db->insert_query("templategroups", $templategroup);

    $insert_array = array(
        'title'		=> 'playerdirectory_characterstat',
        'template'	=> $db->escape_string('<html>
        <head>
            <title>
                {$mybb->settings[\'bbname\']} - {$lang->playerdirectory_characterstat}
            </title>
            {$headerinclude}
        </head>
        <body>
            {$header}
            <table width="100%" cellspacing="0" cellpadding="0">
                <tr>
                    <td>
                        {$notice_banner}
                        <div class="playerdirectory_headline">{$lang->playerdirectory_characterstat}</div>
                        {$random_inplayquote}
    
                        <div class="playerdirectory_characterstat_statistic">
    
                            <div class="playerdirectory_characterstat_stat">
                                <div class="playerdirectory_characterstat_question">{$lang->playerdirectory_statistic_regdate}</div>
                                <div class="playerdirectory_characterstat_answer">{$regdate}</div>		
                            </div>
                            <div class="playerdirectory_characterstat_stat">	
                                <div class="playerdirectory_characterstat_question">{$lang->playerdirectory_statistic_lastactivity}</div>
                                <div class="playerdirectory_characterstat_answer">{$lastactivity}</div>	
                            </div>
                            <div class="playerdirectory_characterstat_stat">	
                                <div class="playerdirectory_characterstat_question">{$lang->playerdirectory_statistic_timeonline}</div>
                                <div  class="playerdirectory_characterstat_answer">{$timeonline}</div>	
                            </div>
                            <div class="playerdirectory_characterstat_stat">
                                <div class="playerdirectory_characterstat_question">{$lang->playerdirectory_statistic_lastinplaypost}</div>
                                <div class="playerdirectory_characterstat_answer">{$lastinplaypost}</div>		
                            </div>
    
    
                            <div class="playerdirectory_characterstat_stat">
                                <div class="playerdirectory_characterstat_question">{$lang->playerdirectory_statistic_allinplayposts}</div>
                                <div class="playerdirectory_characterstat_answer">{$allinplayposts_formatted}</div>		
                            </div>
                            <div class="playerdirectory_characterstat_stat">	
                                <div class="playerdirectory_characterstat_question">{$lang->playerdirectory_statistic_allinplayscenes}</div>
                                <div class="playerdirectory_characterstat_answer">{$allinplayscenes_formatted}</div>	
                            </div>
                            <div class="playerdirectory_characterstat_stat">	
                                <div class="playerdirectory_characterstat_question">{$lang->playerdirectory_statistic_hotscene}</div>
                                <div class="playerdirectory_characterstat_answer">{$hotscene}</div>	
                            </div>
                            <div class="playerdirectory_characterstat_stat">	
                                <div class="playerdirectory_characterstat_question">{$lang->playerdirectory_statistic_viewscene}</div>
                                <div class="playerdirectory_characterstat_answer">{$viewscene}</div>	
                            </div>
    
    
                            <div class="playerdirectory_characterstat_stat">
                                <div class="playerdirectory_characterstat_question">{$lang->playerdirectory_statistic_charactersall}</div>
                                <div class="playerdirectory_characterstat_answer">{$charactersall_formatted}</div>		
                            </div>
                            <div class="playerdirectory_characterstat_stat">	
                                <div class="playerdirectory_characterstat_question">{$lang->playerdirectory_statistic_averageCharacters}</div>
                                <div class="playerdirectory_characterstat_answer">{$averageCharacters_formatted}</div>	
                            </div>
                            <div class="playerdirectory_characterstat_stat">	
                                <div class="playerdirectory_characterstat_question">{$lang->playerdirectory_statistic_wordsall}</div>
                                <div class="playerdirectory_characterstat_answer">{$wordsall_formatted}</div>	
                            </div>
                            <div class="playerdirectory_characterstat_stat">	
                                <div class="playerdirectory_characterstat_question">{$lang->playerdirectory_statistic_averageWords}</div>
                                <div class="playerdirectory_characterstat_answer">{$averageWords_formatted}</div>	
                            </div>
    
                            {$postactivity_months}
    
                        </div>
                    </td>
                </tr>
            </table>
            {$footer}
        </body>
    </html>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title'		=> 'playerdirectory_characterstat_inplayquote',
        'template'	=> $db->escape_string('<div class="playerdirectory_inplayquote">
        <div class="playerdirectory_inplayquote_avatar"><img src="{$avatar_url}"></div>
        <div class="playerdirectory_inplayquote_container">
            <div class="playerdirectory_quote">
            {$quote}
            </div>
            <div class="playerdirectory_quote_user">
                <b>{$charactername}</b><br>
                <span>{$scenelink}</span>
            </div>
        </div>
    </div>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title'		=> 'playerdirectory_directory',
        'template'	=> $db->escape_string('<html>
        <head>
            <title>{$mybb->settings[\'bbname\']} - {$lang->playerdirectory_directory}</title>
            {$headerinclude}
        </head>
        <body>
            {$header}
            {$lists_menu}
            <table border="0" cellspacing="{$theme[\'borderwidth\']}" cellpadding="{$theme[\'tablespace\']}" class="tborder" width="100%">
                <tr>
                    <td class="thead" colspan="3"><strong>{$lang->playerdirectory_directory}</strong></td>
                </tr>
                <tr>
                    <td class="tcat" align="center" style="width:33%"><span class="smalltext"><strong>{$count_allPlayers}</strong></span></td>
                    <td class="tcat" align="center" style="width:33%"><span class="smalltext"><strong>{$count_allCharacters}</strong></span></td>
                    <td class="tcat" align="center" style="width:33%"><span class="smalltext"><strong>{$count_averagecharacters}</strong></span></td>
                </tr>
                <tr>
                    <td class="trow1" colspan="3">
                        <div class="playerdirectory_directory">
                            {$all_players}
                        </div>
                        {$multipage}
                    </td>
                </tr>
            </table>
            {$footer}
        </body>
    </html>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title'		=> 'playerdirectory_directory_characters',
        'template'	=> $db->escape_string('<div class="directory_characters">
        <div class="directory_characters_avatar">
            <img src="{$avatar_url}">
        </div>
        <div>
            <div class="directory_characters_fact"><strong>{$charactername}</strong></div>
            <div class="directory_characters_fact">{$character_inplaystat}</div>
            {$character_button}
        </div>
    </div>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title'		=> 'playerdirectory_directory_user',
        'template'	=> $db->escape_string('<div class="playerdirectory_user">
        <div class="playerdirectory_headline">{$playername}</div>
        <div class="playerdirectory_user_information">
            <div class="playerdirectory_user_information_item"><b>{$lang->playerdirectory_directory_user_regdate}</b> {$regdate}</div>
            <div class="playerdirectory_user_information_item"><b>{$lang->playerdirectory_directory_user_lastactivity}</b> {$lastactivity}</div>
            <div class="playerdirectory_user_information_item">{$player_inplaystat}</div>
            {$player_button}
        </div>
        <div class="playerdirectory_subline">{$charas_count}</div>
        <div class="playerdirectory_user_accounts">
            {$characters}
        </div>
    </div>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title'		=> 'playerdirectory_menu_link',
        'template'	=> $db->escape_string('<li><a href="{$mybb->settings[\'bburl\']}/misc.php?action=playerdirectory" class="memberlist">{$lang->playerdirectory_directory}</a></li>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title'		=> 'playerdirectory_notice_banner',
        'template'	=> $db->escape_string('<div class="pm_alert">{$banner_text}</div>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title'		=> 'playerdirectory_playerstat',
        'template'	=> $db->escape_string('<html>
        <head>
            <title>
                {$mybb->settings[\'bbname\']} - {$lang->playerdirectory_playerstat}
            </title>
            {$headerinclude}
        </head>
        <body>
            {$header}
            <table width="100%" cellspacing="0" cellpadding="0">
                <tr>
                    <td>
                        {$notice_banner}
                        <div class="playerdirectory_headline">{$lang->playerdirectory_playerstat}</div>
                        <div class="playerdirectory_subline">{$lang->playerdirectory_inplaystatistic}</div>
    
                        <div class="playerdirectory_playerstat_statistic">
    
                            <div class="playerdirectory_playerstat_stat">
                                <div class="playerdirectory_playerstat_question">{$lang->playerdirectory_statistic_regdate}</div>
                                <div class="playerdirectory_playerstat_answer">{$regdate}</div>		
                            </div>
                            <div class="playerdirectory_playerstat_stat">	
                                <div class="playerdirectory_playerstat_question">{$lang->playerdirectory_statistic_lastactivity}</div>
                                <div class="playerdirectory_playerstat_answer">{$lastactivity}</div>	
                            </div>
                            <div class="playerdirectory_playerstat_stat">	
                                <div class="playerdirectory_playerstat_question">{$lang->playerdirectory_statistic_timeonline}</div>
                                <div  class="playerdirectory_playerstat_answer">{$timeonline}</div>	
                            </div>
                            <div class="playerdirectory_playerstat_stat">
                                <div class="playerdirectory_playerstat_question">{$lang->playerdirectory_statistic_lastinplaypost}</div>
                                <div class="playerdirectory_playerstat_answer">{$lastinplaypost}</div>		
                            </div>
    
    
                            <div class="playerdirectory_playerstat_stat">
                                <div class="playerdirectory_playerstat_question">{$lang->playerdirectory_statistic_allinplayposts}</div>
                                <div class="playerdirectory_playerstat_answer">{$allinplayposts_formatted}</div>		
                            </div>
                            <div class="playerdirectory_playerstat_stat">	
                                <div class="playerdirectory_playerstat_question">{$lang->playerdirectory_statistic_allinplayscenes}</div>
                                <div class="playerdirectory_playerstat_answer">{$allinplayscenes_formatted}</div>	
                            </div>
                            <div class="playerdirectory_playerstat_stat">	
                                <div class="playerdirectory_playerstat_question">{$lang->playerdirectory_statistic_hotscene}</div>
                                <div class="playerdirectory_playerstat_answer">{$hotscene}</div>	
                            </div>
                            <div class="playerdirectory_playerstat_stat">	
                                <div class="playerdirectory_playerstat_question">{$lang->playerdirectory_statistic_viewscene}</div>
                                <div class="playerdirectory_playerstat_answer">{$viewscene}</div>	
                            </div>
    
    
                            <div class="playerdirectory_playerstat_stat">
                                <div class="playerdirectory_playerstat_question">{$lang->playerdirectory_statistic_charactersall}</div>
                                <div class="playerdirectory_playerstat_answer">{$charactersall_formatted}</div>		
                            </div>
                            <div class="playerdirectory_playerstat_stat">	
                                <div class="playerdirectory_playerstat_question">{$lang->playerdirectory_statistic_averageCharacters}</div>
                                <div class="playerdirectory_playerstat_answer">{$averageCharacters_formatted}</div>	
                            </div>
                            <div class="playerdirectory_playerstat_stat">	
                                <div class="playerdirectory_playerstat_question">{$lang->playerdirectory_statistic_wordsall}</div>
                                <div class="playerdirectory_playerstat_answer">{$wordsall_formatted}</div>	
                            </div>
                            <div class="playerdirectory_playerstat_stat">	
                                <div class="playerdirectory_playerstat_question">{$lang->playerdirectory_statistic_averageWords}</div>
                                <div class="playerdirectory_playerstat_answer">{$averageWords_formatted}</div>	
                            </div>
    
                        </div>
    
                        {$postactivity_months}
    
                        <div class="playerdirectory_subline">{$lang->playerdirectory_characterstatistic}</div>
                        
                        {$random_inplayquote}
                        
                        <div class="playerdirectory_playerstat_statistic">
                            <div class="playerdirectory_playerstat_stat">
                                <div class="playerdirectory_playerstat_question">{$lang->playerdirectory_statistic_charas}</div>
                                <div class="playerdirectory_playerstat_answer">{$count_charas}</div>		
                            </div>
                            <div class="playerdirectory_playerstat_stat">	
                                <div class="playerdirectory_playerstat_question">{$lang->playerdirectory_statistic_firstchara}</div>
                                <div class="playerdirectory_playerstat_answer">{$firstchara}</div>	
                            </div>
                            <div class="playerdirectory_playerstat_stat">	
                                <div class="playerdirectory_playerstat_question">{$lang->playerdirectory_statistic_lastchara}</div>
                                <div class="playerdirectory_playerstat_answer">{$lastchara}</div>	
                            </div>
                            <div class="playerdirectory_playerstat_stat">	
                                <div class="playerdirectory_playerstat_question">{$lang->playerdirectory_statistic_hotchara}</div>
                                <div class="playerdirectory_playerstat_answer">{$hotCharacter}</div>	
                            </div>
    
                            <div class="playerdirectory_playerstat_stat">
                                <div class="playerdirectory_playerstat_question">{$lang->playerdirectory_statistic_minage}</div>
                                <div class="playerdirectory_playerstat_answer">{$minage}</div>		
                            </div>
                            <div class="playerdirectory_playerstat_stat">	
                                <div class="playerdirectory_playerstat_question">{$lang->playerdirectory_statistic_maxage}</div>
                                <div class="playerdirectory_playerstat_answer">{$maxage}</div>	
                            </div>
                            <div class="playerdirectory_playerstat_stat">	
                                <div class="playerdirectory_playerstat_question">{$lang->playerdirectory_statistic_averageage}</div>
                                <div class="playerdirectory_playerstat_answer">{$averageage}</div>	
                            </div>
                            <div class="playerdirectory_playerstat_stat">	
                                <div class="playerdirectory_playerstat_question">{$lang->playerdirectory_statistic_hotcharascene}</div>
                                <div class="playerdirectory_playerstat_answer">{$hotcharascene}</div>	
                            </div>
    
                        </div>
                        {$postactivity_perChara}
                        {$characters_bit}
                    </td>
                </tr>
            </table>
            {$footer}
        </body>
    </html>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title'		=> 'playerdirectory_playerstat_characters',
        'template'	=> $db->escape_string('<div class="playerdirectory_playerstat_characters">
        <div class="playerdirectory_playerstat_avatar">
            <img src="{$avatar_url}">
        </div>
        <div class="playerdirectory_playerstat_infos">
            <div class="playerdirectory_playerstat_username">{$charactername} | {$age_years}
                <span style="float:right">{$character_button}</span>
            </div>
            <div class="playerdirectory_playerstat_usertitle">{$usertitle}</div>
            <div class="playerdirectory_playerstat_fact"><b>{$lang->playerdirectory_statistic_regdate}</b> {$regdate}</div>
            <div class="playerdirectory_playerstat_fact"><b>{$lang->playerdirectory_statistic_lastactivity}</b> {$lastactivity}</div>
            <div class="playerdirectory_playerstat_fact">{$character_inplaystat}</div>
        </div>
    </div>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title'		=> 'playerdirectory_playerstat_inplayquote',
        'template'	=> $db->escape_string('<div class="playerdirectory_inplayquote">
        <div class="playerdirectory_inplayquote_avatar"><img src="{$avatar_url}"></div>
        <div class="playerdirectory_inplayquote_container">
            <div class="playerdirectory_quote">
            {$quote}
            </div>
            <div class="playerdirectory_quote_user">
                <b>{$charactername}</b><br>
                <span>{$scenelink}</span>
            </div>
        </div>
    </div>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title'		=> 'playerdirectory_playerstat_ownstat',
        'template'	=> $db->escape_string('<div class="playerdirectory_playerstat_ownstat_headline">{$statisticname}</div>{$ownstat_bit}'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title'		=> 'playerdirectory_playerstat_ownstat_bar',
        'template'	=> $db->escape_string('<div class="playerdirectory_playerstat_ownstat_headline">{$statisticname}</div>
        <div class="playerdirectory_playerstat_ownstat_chart">
            <canvas id="{$chartname}"></canvas>
        </div>
        
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.js"></script>
        <script>
            var style = getComputedStyle(document.body);
            var text = style.getPropertyValue(\'--chart-text\');
            // data define for bar chart
            var myData = {
                labels: {$labels_chart},
                datasets: [{
                    backgroundColor: {$backgroundColor},
                    data: {$data_chart}
                }]
            };
            // Options define for display value on top of bars
            var myoption = {
                maintainAspectRatio: false,
                legend: {
                    "display": false
                },
                responsive: true,
                tooltips: {
                    enabled: true
                },
                hover: {
                    animationDuration: 1
                },
                animation: {
                    duration: 1,
                    onComplete: function () {
                        var chartInstance = this.chart,
                            ctx = chartInstance.ctx;
                        ctx.textAlign = \'center\';
                        ctx.fillStyle = text;
                        ctx.textBaseline = \'bottom\';
                        // Loop through each data in the datasets
                        this.data.datasets.forEach(function (dataset, i) {
                            var meta = chartInstance.controller.getDatasetMeta(i);
                            meta.data.forEach(function (bar, index) {
                                var data = dataset.data[index];
                                ctx.fillText(data, bar._model.x, bar._model.y - 5);
        
                            });
                        });
                    }
                },
                scales: {
                    yAxes: [{
                        display: true,
                        gridLines: {
                            display: false
                        },
                        ticks: {
                            max: {$maxCount},
                            display: false,
                            beginAtZero: true,
                            color: accent
                        }
                    }],
                    xAxes: [{
                        gridLines: {
                            display: false
                        },
                        ticks: {
                            beginAtZero: true,
                            fontColor: text
                        }
                    }]
                }
            };
            // Code to draw Chart
            var ctx = document.getElementById(\'{$chartname}\').getContext(\'2d\');
            var myChart = new Chart(ctx, {
                type: \'bar\', // Define chart type
                data: myData, // Chart data
                options: myoption // Chart Options [This is optional paramenter use to add some extra things in the chart].
            });
        </script>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title'		=> 'playerdirectory_playerstat_ownstat_bit',
        'template'	=> $db->escape_string('<div class="playerdirectory_playerstat_ownstat_bit_option">
        <div class="playerdirectory_playerstat_ownstat_bit_optionname">{$fieldname}</div> 
        {$fieldcount}
    </div>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title'		=> 'playerdirectory_playerstat_ownstat_pie',
        'template'	=> $db->escape_string('<div class="playerdirectory_playerstat_ownstat_headline">{$statisticname}</div>
        <div class="playerdirectory_playerstat_ownstat_chart">
            <canvas id="{$chartname}"></canvas>
        </div>
        
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.js"></script>
        <script>
            var style = getComputedStyle(document.body);
            var text = style.getPropertyValue(\'--chart-text\');
            {$propertyValue}
            // data define for bar chart
            var myData = {
                labels: {$labels_chart},
                datasets: [{
                    data: {$data_chart},
                    backgroundColor: {$backgroundColor},
                    borderWidth: 0
                }]
            };
            // Options define for display value on top of bars
            var myoption = {
                maintainAspectRatio: false,
                legend: {
                    display: {$legend},
                    position: \'right\',
                    labels: {
                        fontColor: text,
                        fontSize: 12
                    },
                },
                responsive: true,
            };
            // Code to draw Chart
            var ctx = document.getElementById(\'{$chartname}\').getContext(\'2d\');
            var myChart = new Chart(ctx, {
                type: \'pie\', // Define chart type
                data: myData, // Chart data
                options: myoption // Chart Options [This is optional paramenter use to add some extra things in the chart].
            });
        </script>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title'		=> 'playerdirectory_postactivity_months',
        'template'	=> $db->escape_string('<div class="playerdirectory_postactivity_months_headline">{$lang->playerdirectory_postactivity_months}</div>
        <div class="playerdirectory_postactivity_months">
            <div class="playerdirectory_postactivity_months_poststat">
                {$months_bit}
            </div>
        </div>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title'		=> 'playerdirectory_postactivity_months_bit',
        'template'	=> $db->escape_string('<div class="playerdirectory_postactivity_months_month">
        <div class="playerdirectory_postactivity_months_monthname">{$month_name}</div> 
        {$allmonthposts_formatted}    
    </div>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title'		=> 'playerdirectory_postactivity_months_chart',
        'template'	=> $db->escape_string('<div class="playerdirectory_postactivity_months_headline">{$lang->playerdirectory_postactivity_months}</div>
        <div class="playerdirectory_postactivity_months_chart">
            <canvas id="postactivityChart"></canvas>
        </div>
        
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.js"></script>
        <script>
            var style = getComputedStyle(document.body);
            var accent = style.getPropertyValue(\'--chart-primary\');
            var text = style.getPropertyValue(\'--chart-text\');
            // data define for bar chart
            var myData = {
                labels: {$labels_chart},
                datasets: [{
                    backgroundColor: accent,
                    hoverBackgroundColor: accent,
                    data: {$data_chart}
                }]
            };
            // Options define for display value on top of bars
            var myoption = {
                maintainAspectRatio: false,
                legend: {
                    "display": false
                },
                tooltips: {
                    enabled: true,
                },
                hover: {
                    animationDuration: 1
                },
                animation: {
                    duration: 1,
                    onComplete: function () {
                        var chartInstance = this.chart,
                            ctx = chartInstance.ctx;
                        ctx.textAlign = \'center\';
                        ctx.fillStyle = text;
                        ctx.textBaseline = \'bottom\';
                        // Loop through each data in the datasets
                        this.data.datasets.forEach(function (dataset, i) {
                            var meta = chartInstance.controller.getDatasetMeta(i);
                            meta.data.forEach(function (bar, index) {
                                var data = dataset.data[index];
                                ctx.fillText(data, bar._model.x, bar._model.y - 5);
                            });
                        });
                    }
                },
                scales: {
                    yAxes: [{
                        display: true,
                        gridLines: {
                            display: false
                        },
                        ticks: {
                            max: {$maxCount},
                            display: false,
                            beginAtZero: true,
                            color: accent
                        }
                    }],
                    xAxes: [{
                        gridLines: {
                            display: false
                        },
                        ticks: {
                            beginAtZero: true,
                            fontColor: text
                        }
                    }]
                }
            };
            // Code to draw Chart
            var ctx = document.getElementById(\'postactivityChart\').getContext(\'2d\');
            var myChart = new Chart(ctx, {
                type: \'bar\', // Define chart type
                data: myData, // Chart data
                options: myoption // Chart Options [This is optional paramenter use to add some extra things in the chart].
            });
        </script>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title'		=> 'playerdirectory_postactivity_perChara',
        'template'	=> $db->escape_string('<div class="playerdirectory_postactivity_perChara">
        {$postactivity_scenestat}
        {$postactivity_poststat}
    </div>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title'		=> 'playerdirectory_postactivity_perChara_poststat',
        'template'	=> $db->escape_string('<div class="playerdirectory_postactivity_perChara_stat">
        <div class="playerdirectory_postactivity_perChara_headline">{$lang->playerdirectory_postactivity_perChara_poststat}</div>
        {$poststat_bit}
    </div>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title'		=> 'playerdirectory_postactivity_perChara_poststat_bit',
        'template'	=> $db->escape_string('<div class="playerdirectory_postactivity_perChara_bit_chara">
        <div class="playerdirectory_postactivity_perChara_bit_charactername">{$first_name}<br>{$last_name}</div> 
        {$postcount_formatted} Posts
    </div>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title'		=> 'playerdirectory_postactivity_perChara_poststat_chart_bar',
        'template'	=> $db->escape_string('<div class="playerdirectory_postactivity_perChara_chart">
        <canvas id="poststatChart"></canvas>
        </div>
    
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.js"></script>
        <script>
        var style = getComputedStyle(document.body);
        var text = style.getPropertyValue(\'--chart-text\');
        // data define for bar chart
        var myData = {
            labels: {$labels_chart},
            datasets: [{
                backgroundColor: {$backgroundColor},
                data: {$data_chart}
            }]
        };
        // Options define for display value on top of bars
        var myoption = {
            maintainAspectRatio: false,
            legend: {
                "display": false
            },
            responsive: true,
            tooltips: {
                enabled: true,
            },
            hover: {
                animationDuration: 1
            },
            animation: {
                duration: 1,
                onComplete: function () {
                    var chartInstance = this.chart,
                        ctx = chartInstance.ctx;
                    ctx.textAlign = \'center\';
                    ctx.fillStyle = text;
                    ctx.textBaseline = \'bottom\';
                    // Loop through each data in the datasets
                    this.data.datasets.forEach(function (dataset, i) {
                        var meta = chartInstance.controller.getDatasetMeta(i);
                        meta.data.forEach(function (bar, index) {
                            var data = dataset.data[index];
                            ctx.fillText(data, bar._model.x, bar._model.y - 5);
                        });
                    });
                }
            },
            scales: {
                yAxes: [{
                    display: true,
                    gridLines: {
                        display: false
                    },
                    ticks: {
                        max: {$maxCount},
                        display: false,
                        beginAtZero: true,
                        color: accent
                    }
                }],
                xAxes: [{
                    gridLines: {
                        display: false
                    },
                    ticks: {
                        beginAtZero: true,
                        fontColor: text
                    }
                }]
            }
        };
        // Code to draw Chart
        var ctx = document.getElementById(\'poststatChart\').getContext(\'2d\');
        var myChart = new Chart(ctx, {
            type: \'bar\', // Define chart type
            data: myData, // Chart data
            options: myoption // Chart Options [This is optional paramenter use to add some extra things in the chart].
        });
    </script>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title'		=> 'playerdirectory_postactivity_perChara_poststat_chart_pie',
        'template'	=> $db->escape_string('<div class="playerdirectory_postactivity_perChara_chart">
        <canvas id="poststatChart"></canvas>  
        </div>

        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.js"></script>
        <script>
        var style = getComputedStyle(document.body);
        var text = style.getPropertyValue(\'--chart-text\');
        // data define for bar chart
        var myData = {
            labels: {$labels_chart},
            datasets: [{
                data: {$data_chart},
                backgroundColor: {$backgroundColor},
                borderColor: {$backgroundColor},
                borderWidth: 0
            }]
        };
        // Options define for display value on top of bars
        var myoption = {
            maintainAspectRatio: false,
            legend: {
                display: {$legend},
                position: \'right\',
                labels: {
                        fontColor: text,
                    fontSize: 12
                    },
            },
            tooltips: {
                enabled: true,
            },
            responsive: true,
        };
        // Code to draw Chart
        var ctx = document.getElementById(\'poststatChart\').getContext(\'2d\');
        var myChart = new Chart(ctx, {
            type: \'pie\', // Define chart type
            data: myData, // Chart data
            options: myoption // Chart Options [This is optional paramenter use to add some extra things in the chart].
        });
    </script>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title'		=> 'playerdirectory_postactivity_perChara_scenestat',
        'template'	=> $db->escape_string('<div class="playerdirectory_postactivity_perChara_stat">
        <div class="playerdirectory_postactivity_perChara_headline">{$lang->playerdirectory_postactivity_perChara_scenestat}</div>
        {$scenestat_bit}
    </div>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title'		=> 'playerdirectory_postactivity_perChara_scenestat_bit',
        'template'	=> $db->escape_string('<div class="playerdirectory_postactivity_perChara_bit_chara">
        <div class="playerdirectory_postactivity_perChara_bit_charactername">{$first_name}<br>{$last_name}</div> 
        {$scenecount_formatted} Szenen
    </div>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title'		=> 'playerdirectory_postactivity_perChara_scenestat_chart_bar',
        'template'	=> $db->escape_string('<div class="playerdirectory_postactivity_perChara_chart">
        <canvas id="scenestatChart"></canvas>
        </div>
     
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.js"></script>
        <script>
        var style = getComputedStyle(document.body);
        var text = style.getPropertyValue(\'--chart-text\');
        // data define for bar chart
        var myData = {
            labels: {$labels_chart},
            datasets: [{
                backgroundColor: {$backgroundColor},
                data: {$data_chart}
            }]
        };
        // Options define for display value on top of bars
        var myoption = {
            maintainAspectRatio: false,
            legend: {
                "display": false
            },
            responsive: true,
            tooltips: {
                enabled: true
            },
            hover: {
                animationDuration: 1
            },
            animation: {
                duration: 1,
                onComplete: function () {
                    var chartInstance = this.chart,
                        ctx = chartInstance.ctx;
                    ctx.textAlign = \'center\';
                    ctx.fillStyle = text;
                    ctx.textBaseline = \'bottom\';
                    // Loop through each data in the datasets
                    this.data.datasets.forEach(function (dataset, i) {
                        var meta = chartInstance.controller.getDatasetMeta(i);
                        meta.data.forEach(function (bar, index) {
                            var data = dataset.data[index];
                            ctx.fillText(data, bar._model.x, bar._model.y - 5);
                        });
                    });
                }
            },
            scales: {
                yAxes: [{
                    display: true,
                    gridLines: {
                        display: false
                    },
                    ticks: {
                        max: {$maxCount},
                        display: false,
                        beginAtZero: true,
                        color: accent
                    }
                }],
                xAxes: [{
                    gridLines: {
                        display: false
                    },
                    ticks: {
                        beginAtZero: true,
                        fontColor: text
                    }
                }]
            }
        };
        // Code to draw Chart
        var ctx = document.getElementById(\'scenestatChart\').getContext(\'2d\');
        var myChart = new Chart(ctx, {
            type: \'bar\', // Define chart type
            data: myData, // Chart data
            options: myoption // Chart Options [This is optional paramenter use to add some extra things in the chart].
        });
    </script>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title'		=> 'playerdirectory_postactivity_perChara_scenestat_chart_pie',
        'template'	=> $db->escape_string('<div class="playerdirectory_postactivity_perChara_chart">
        <canvas id="scenestatChart"></canvas>
        </div>
    
        <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/2.9.4/Chart.min.js"></script>
        <script>
        var style = getComputedStyle(document.body);
        var text = style.getPropertyValue(\'--chart-text\');
        // data define for bar chart
        var myData = {
            labels: {$labels_chart},
            datasets: [{
                data: {$data_chart},
                backgroundColor: {$backgroundColor},
                borderColor: {$backgroundColor},
                borderWidth: 0
            }]
        };
        // Options define for display value on top of bars
        var myoption = {
            maintainAspectRatio: false,
            legend: {
                display: {$legend},
                position: \'right\',
                labels: {
                        fontColor: text,
                    fontSize: 12
                    },
            },
            responsive: true,
        };
        // Code to draw Chart
        var ctx = document.getElementById(\'scenestatChart\').getContext(\'2d\');
        var myChart = new Chart(ctx, {
            type: \'pie\', // Define chart type
            data: myData, // Chart data
            options: myoption // Chart Options [This is optional paramenter use to add some extra things in the chart].
        });
    </script>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title'		=> 'playerdirectory_usercp_options',
        'template'	=> $db->escape_string('<fieldset class="trow2">
        <legend><strong>{$lang->playerdirectory_usercp_options}</strong></legend>
        <table cellspacing="0" cellpadding="2">
            {$option_playerstat}
            {$option_playerstat_guest}
            {$option_characterstat}
            {$option_characterstat_guest}
        </table>
    </fieldset><br>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);

    $insert_array = array(
        'title'		=> 'playerdirectory_usercp_options_bit',
        'template'	=> $db->escape_string('<tr>
        <td valign="top" width="1">
            <input type="checkbox" class="checkbox" name="{$nameID}" id="{$nameID}" value="1" {$checked} />
        </td>
        <td>
            <span class="smalltext">
                <label for="{$nameID}">{$option_text}</label>
            </span>
        </td>
    </tr>'),
        'sid'		=> '-2',
        'dateline'	=> TIME_NOW
    );
    $db->insert_query("templates", $insert_array);
    

    require_once MYBB_ADMIN_DIR."inc/functions_themes.php";

    // STYLESHEET HINZUFÜGEN
    $css = array(
        'name' => 'playerdirectory.css',
        'tid' => 1,
        'attachedto' => '',
        "stylesheet" => ':root {	
            --chart-primary: #0066a2;
            --chart-text: #000;
        }
        
        /* SPIELERVERZEICHNIS */
        
        .playerdirectory_directory {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-start;
            gap: 10px;
            align-items: flex-start;
        }
        
        .playerdirectory_user {
            width: 32.8%;
        }
        
        .playerdirectory_headline {
            background: #0066a2 url(../../../images/thead.png) top left repeat-x;
            color: #ffffff;
            padding: 8px;
            font-weight: bold;
        }
        
        .playerdirectory_subline {
            background: #0f0f0f url(../../../images/tcat.png) repeat-x;
            color: #fff;
            border-top: 1px solid #444;
            border-bottom: 1px solid #000;
            padding: 6px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .playerdirectory_user_information {
            padding: 5px 0;
        }
        
        .playerdirectory_user_information_item {
            padding: 1px 0;
        }
        
        .playerdirectory_user_accounts {
            height: 200px;
            overflow: auto;
            padding-top: 10px;
        }
        
        .directory_characters {
            display: flex;
            width: 100%;
            margin-bottom: 5px;
            flex-wrap: nowrap;
            align-items: flex-start;
            justify-content: flex-start;
            gap: 10px;
        }
        
        .directory_characters_avatar {
            width: 15%;
        }
        
        .directory_characters_avatar img {
            width: 100%;
        }
        
        .directory_characters_fact {
            padding-top: 3px;
        }
        
        /* SPIELERSTATISTIK */
        
        .playerdirectory_playerstat_statistic {
            display: flex;
            flex-flow: wrap;
            margin: 10px 0;
        }
        
        .playerdirectory_playerstat_stat {
            width: calc(100% / 4);
            display: flex;
            flex-flow: column;
            padding: 10px 5px;
            box-sizing: border-box;
            justify-content: flex-start;
            align-items: center;
        }
        
        .playerdirectory_playerstat_question {
            color: #333;
            font-size: small;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .playerdirectory_playerstat_answer {
            text-align: center;
        }
        
        .playerdirectory_playerstat_characters {
            display: flex;
            justify-content: flex-start;
            flex-wrap: nowrap;
            gap: 10px;
            width: 100%;
            margin-bottom: 10px;
        }
        
        .playerdirectory_playerstat_avatar {
            width: 10%;
        }
        
        .playerdirectory_playerstat_avatar img {
            width: 100%;
        }
        
        .playerdirectory_playerstat_infos {
            width: 90%;
        }
        
        .playerdirectory_playerstat_username {
            background: #0066a2 url(../../../images/thead.png) top left repeat-x;
            color: #ffffff;
            padding: 8px;
            font-weight: bold;
        }
        
        .playerdirectory_playerstat_usertitle {
            background: #0f0f0f url(../../../images/tcat.png) repeat-x;
            color: #fff;
            border-top: 1px solid #444;
            border-bottom: 1px solid #000;
            padding: 6px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .playerdirectory_playerstat_username a:link,
        .playerdirectory_playerstat_username a:visited,
        .playerdirectory_playerstat_username a:active,
        .playerdirectory_playerstat_username a:hover {
            color: #ffffff;
        }
        
        /* CHARAKTERSTATISTIK */
        
        .playerdirectory_characterstat_statistic {
            display: flex;
            flex-flow: wrap;
            margin: 10px 0;
        }
        
        .playerdirectory_characterstat_stat {
            width: calc(100% / 4);
            display: flex;
            flex-flow: column;
            padding: 10px 5px;
            box-sizing: border-box;
            justify-content: flex-start;
            align-items: center;
        }
        
        .playerdirectory_characterstat_question {
            color: #333;
            font-size: small;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .playerdirectory_characterstat_answer {
            text-align: center;
        }
        
        .playerdirectory_characterstat_characters {
            display: flex;
            justify-content: flex-start;
            flex-wrap: nowrap;
            gap: 10px;
            width: 100%;
            margin-bottom: 10px;
        }
        
        .playerdirectory_characterstat_avatar {
            width: 10%;
        }
        
        .playerdirectory_characterstat_avatar img {
            width: 100%;
        }
        
        .playerdirectory_characterstat_infos {
            width: 90%;
        }
        
        .playerdirectory_characterstat_username {
            background: #0066a2 url(../../../images/thead.png) top left repeat-x;
            color: #ffffff;
            padding: 8px;
            font-weight: bold;
        }
        
        .playerdirectory_characterstat_usertitle {
            background: #0f0f0f url(../../../images/tcat.png) repeat-x;
            color: #fff;
            border-top: 1px solid #444;
            border-bottom: 1px solid #000;
            padding: 6px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .playerdirectory_characterstat_username a:link,
        .playerdirectory_characterstat_username a:visited,
        .playerdirectory_characterstat_username a:active,
        .playerdirectory_characterstat_username a:hover {
            color: #ffffff;
        }
        
        /* INPLAYZITATET */
        
        .playerdirectory_inplayquote {
            width: 100%;
            display: flex;
            margin: 10px 0;
            flex-wrap: nowrap;
            align-items: center;
        }
        
        .playerdirectory_inplayquote_avatar {
            width: 10%;
            text-align: center;
        }
        
        .playerdirectory_inplayquote_avatar img {
            border-radius: 100%;
            border: 2px solid #0071bd;
            width: 100px;
        }
        
        .playerdirectory_inplayquote_container {
            width: 90%;
        }
        
        .playerdirectory_quote {
            width: 95%;
            margin: auto;
            font-size: 15px;
            text-align: justify;
            margin-bottom: 10px;
        }
        
        .playerdirectory_quote_user {
            text-align: right;
        }
        
        .playerdirectory_quote_user b {
            text-transform: uppercase;
            letter-spacing: 2px;
            font-size: 13px;
        }
        
        .playerdirectory_quote_user span {
            font-style: italic;
            font-size: 11px;
        }
        
        /* 12 MONATE STATISTIK */
        
        .playerdirectory_postactivity_months_headline {
            margin-bottom: 5px;
            color: #333;
            font-size: small;
            font-weight: bold;
            text-transform: uppercase;
            text-align: center;
            width: 100%;
        }
        
        .playerdirectory_postactivity_months {
            width: 100%;
            text-align: center;
            margin: 10px 10px;
        }
        
        .playerdirectory_postactivity_months_poststat {
            width: 100%;
            display: flex;
            flex-flow: wrap;
            flex-wrap: nowrap;
            justify-content: space-between;
        }
        
        .playerdirectory_postactivity_months_month {
            justify-content: center;
            align-items: center;
            display: flex;
            flex-flow: column;
        }
        
        .playerdirectory_postactivity_months_monthname {
            color: #293340;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .playerdirectory_postactivity_months_chart {
            height: 250px;
            width: 100%;
        }
        
        /* PRO CHARAKTER */
        
        .playerdirectory_postactivity_perChara {
            text-align: center;
            margin: 10px 10px;
            display: flex;
            justify-content: space-around;
            align-content: flex-start;
            flex-wrap: nowrap;
        }
        
        .playerdirectory_postactivity_perChara_stat {
            width: 50%;
            text-align: center;
        }
        
        .playerdirectory_postactivity_perChara_headline {
            margin-bottom: 5px;
            color: #333;
            font-size: small;
            font-weight: bold;
            text-transform: uppercase;
            text-align: center;
            width: 100%;
        }
        
        .playerdirectory_postactivity_perChara_bit {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
            height: 150px;
            align-items: center;
        }
        
        .playerdirectory_postactivity_perChara_bit_chara {
            justify-content: center;
            align-items: center;
            display: flex;
            flex-flow: column;
        }
        
        .playerdirectory_postactivity_perChara_bit_charactername {
            color: #293340;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .playerdirectory_postactivity_perChara_chart {
            height: 150px;
            width: 100%;
        }
        
        /* EIGENE STATISTIKEN */
        
        .playerdirectory_playerstat_ownstat_headline {
            margin-bottom: 5px;
            color: #333;
            font-size: small;
            font-weight: bold;
            text-transform: uppercase;
            text-align: center;
            width: 100%;
        }
        
        .playerdirectory_playerstat_ownstat_bit {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
            align-items: center;
        }
        
        .playerdirectory_playerstat_ownstat_bit_option {
            justify-content: center;
            align-items: center;
            display: flex;
            flex-flow: column;
        }
        
        .playerdirectory_playerstat_ownstat_bit_optionname {
            color: #293340;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .playerdirectory_playerstat_ownstat_chart {
            height: 150px;
            width: 100%;
        }',
        'cachefile' => $db->escape_string(str_replace('/', '', 'playerdirectory.css')),
        'lastmodified' => time()
    );
    
    $sid = $db->insert_query("themestylesheets", $css);
    $db->update_query("themestylesheets", array("cachefile" => "playerdirectory.css"), "sid = '".$sid."'", 1);

    $tids = $db->simple_select("themes", "tid");
    while($theme = $db->fetch_array($tids)) {
        update_theme_stylesheet_list($theme['tid']);
    }
}
 
// Funktion zur Überprüfung des Installationsstatus; liefert true zurürck, wenn Plugin installiert, sonst false (optional).
function playerdirectory_is_installed(){

    global $mybb, $db;
    
    if ($db->table_exists("playerdirectory_statistics")) {
        return true;
    }
    return false;
}
 
// Diese Funktion wird aufgerufen, wenn das Plugin deinstalliert wird (optional).
function playerdirectory_uninstall(){

    global $db;

	require_once MYBB_ADMIN_DIR."inc/functions_themes.php";

    // USER SPALTEN LÖSCHEN
	if ($db->field_exists("playerdirectory_playerstat", "users")) {
		$db->drop_column("users", "playerdirectory_playerstat");
	}
    if ($db->field_exists("playerdirectory_playerstat_guest", "users")) {
		$db->drop_column("users", "playerdirectory_playerstat_guest");
	}
    if ($db->field_exists("playerdirectory_characterstat", "users")) {
		$db->drop_column("users", "playerdirectory_characterstat");
	}
    if ($db->field_exists("playerdirectory_characterstat_guest", "users")) {
		$db->drop_column("users", "playerdirectory_characterstat_guest");
	}

    // DATENBANK LÖSCHEN
    if($db->table_exists("playerdirectory_statistics")){
        $db->drop_table("playerdirectory_statistics");
    }

	// EINSTELLUNGEN LÖSCHEN
    $db->delete_query('settings', "name LIKE 'playerdirectory%'");
    $db->delete_query('settinggroups', "name = 'playerdirectory'");

    rebuild_settings();

    // TEMPLATE LÖSCHEN
    $db->delete_query("templates", "title LIKE '%playerdirectory%'");

	// STYLESHEET ENTFERNEN
	$db->delete_query("themestylesheets", "name = 'playerdirectory.css'");
	$query = $db->simple_select("themes", "tid");
	while($theme = $db->fetch_array($query)) {
		update_theme_stylesheet_list($theme['tid']);
    }
	
} 
 
// Diese Funktion wird aufgerufen, wenn das Plugin aktiviert wird.
function playerdirectory_activate(){

	// VARIABLE HINZUFÜGEN
    include MYBB_ROOT."/inc/adminfunctions_templates.php";
	find_replace_templatesets("header", "#".preg_quote('{$menu_memberlist}')."#i", '{$menu_memberlist}{$menu_playerdirectory}');
    find_replace_templatesets("usercp_options", "#".preg_quote('{$canbeinvisible}')."#i", '{$canbeinvisible}{$playerdirectory_options}');
}
 
// Diese Funktion wird aufgerufen, wenn das Plugin deaktiviert wird.
function playerdirectory_deactivate(){

	// VARIABLE ENTFERNEN
	include MYBB_ROOT."/inc/adminfunctions_templates.php";
    find_replace_templatesets("header", "#".preg_quote('{$menu_playerdirectory}')."#i", '', 0);
    find_replace_templatesets("usercp_options", "#".preg_quote('{$playerdirectory_options}')."#i", '', 0);
}

#####################################
### THE BIG MAGIC - THE FUNCTIONS ###
#####################################

// ADMIN BEREICH - KONFIGURATION //
// action handler fürs acp konfigurieren
function playerdirectory_admin_user_action_handler(&$actions) {
	$actions['playerdirectory'] = array('active' => 'playerdirectory', 'file' => 'playerdirectory');
}

// Berechtigungen im ACP - Adminrechte
function playerdirectory_admin_user_permissions(&$admin_permissions) {
	global $lang;
	
    $lang->load('playerdirectory');

	$admin_permissions['playerdirectory'] = $lang->playerdirectory_permission;

	return $admin_permissions;
}

// Menü einfügen
function playerdirectory_admin_user_menu(&$sub_menu) {
	global $mybb, $lang;
	
    $lang->load('playerdirectory');

	$sub_menu[] = [
		"id" => "playerdirectory",
		"title" => $lang->playerdirectory_manage,
		"link" => "index.php?module=user-playerdirectory"
	];
}

// Verwaltung im ACP
function playerdirectory_admin_manage() {

	global $mybb, $db, $lang, $page, $run_module, $action_file, $cache;

	$lang->load('playerdirectory');

    $playerstat_activated = $mybb->settings['playerdirectory_playerstat'];
    $profilfeldsystem = $mybb->settings['playerdirectory_profilfeldsystem'];

    $typeselect_list = array(
        "0" => $lang->playerdirectory_manage_add_type_select,
		"1" => $lang->playerdirectory_manage_overview_stat_presentation_bar,
		"2" => $lang->playerdirectory_manage_overview_stat_presentation_pie,
		"4" => $lang->playerdirectory_manage_overview_stat_presentation_pie_legend,
		"3" => $lang->playerdirectory_manage_overview_stat_presentation_word
	);

    // Katjas Steckbrief Plugin
    if ($profilfeldsystem != 0) {
        $application_plugin = $lang->playerdirectory_manage_add_dataselect_applicationfield;
    } else {
        $application_plugin = "";
    }
    
    // Klassische Profilfelder
    if ($profilfeldsystem != 1) {
        $profilefields = $lang->playerdirectory_manage_add_dataselect_profilefield;
    } else {
        $profilefields = "";
    }

    $nonefields_list = array(
        "full" => $lang->playerdirectory_manage_add_nonefields,
	);
    
    $dataselect_list = array(
        "0" => $lang->playerdirectory_manage_add_dataselect_select,
        "1" => $profilefields,
        "2" => $application_plugin,
        "3" => $lang->playerdirectory_manage_add_dataselect_usergroups
    );
    $dataselect_list = array_diff($dataselect_list, array(""));

    $groupoption_list = array(
        "0" => $lang->playerdirectory_manage_add_groupoption_select,
		"1" => $lang->playerdirectory_manage_add_groupoption_primary,
		"2" => $lang->playerdirectory_manage_add_groupoption_secondary,
		"3" => $lang->playerdirectory_manage_add_groupoption_both
	);

    if ($page->active_action != 'playerdirectory') {
		return false;
	}

	// Add to page navigation
	$page->add_breadcrumb_item($lang->playerdirectory_manage, "index.php?module=user-playerdirectory");

	if ($run_module == 'user' && $action_file == 'playerdirectory') {

        // ÜBERSICHT
		if ($mybb->get_input('action') == "" || !$mybb->get_input('action')) {

			// Optionen im Header bilden
			$page->output_header($lang->playerdirectory_manage_header." - ".$lang->playerdirectory_manage_overview);

			// Übersichtsseite Button
			$sub_tabs['playerdirectory'] = [
				"title" => $lang->playerdirectory_manage_overview,
				"link" => "index.php?module=user-playerdirectory",
				"description" => $lang->playerdirectory_manage_overview_desc
			];
			// Hinzufüge Button
			$sub_tabs['playerdirectory_add'] = [
				"title" => $lang->playerdirectory_manage_add,
				"link" => "index.php?module=user-playerdirectory&amp;action=add",
				"description" => $lang->playerdirectory_manage_add_desc
			];

			$page->output_nav_tabs($sub_tabs, 'playerdirectory');

			// Show errors
			if (isset($errors)) {
				$page->output_inline_error($errors);
			}
            
			// Übersichtsseite
			$form = new Form("index.php?module=user-playerdirectory", "post", "", 1);
			$form_container = new FormContainer($lang->playerdirectory_manage_overview);

            // Spielerstatistiken muss aktiviert sein
            if($playerstat_activated == 1){

                // Informationen
                $form_container->output_row_header($lang->playerdirectory_manage_overview_stat, array('style' => 'text-align: justify; width: 90%;'));
                // Optionen
                $form_container->output_row_header($lang->playerdirectory_manage_overview_options, array('style' => 'text-align: center; width: 10%;'));
	
                // Alle Statistiken
                $query_statistics = $db->query("SELECT * FROM ".TABLE_PREFIX."playerdirectory_statistics
                ORDER BY name ASC
                ");

                while ($stat = $db->fetch_array($query_statistics)) {

                    // Darstellung (Typ) - Balken, Kreis oder wort/zahl
                    if ($stat['type'] == 1) {
                        $type = $lang->playerdirectory_manage_overview_stat_presentation_bar;
                    } else if ($stat['type'] == 2) {
                        if ($stat['legend'] == 0) {
                            $type = $lang->playerdirectory_manage_overview_stat_presentation_pie;
                        } else {
                            $type = $lang->playerdirectory_manage_overview_stat_presentation_pie_legend;
                        }

                    } else if ($stat['type'] == 3) {
                        $type = $lang->playerdirectory_manage_overview_stat_presentation_word;
                    }

                    // Daten
                    if (!empty($stat['field'])) {

                        // wenn Zahl => klassisches Profilfeld
                        if (is_numeric($stat['field'])) {
                            // Name
                            $fieldname = $db->fetch_field($db->simple_select("profilefields", "name", "fid = '".$stat['field']."'"), "name");
                            $fieldsystem = $lang->playerdirectory_manage_overview_stat_fieldsystem_profilefield;

                            // Daten Optionen
                            $options = $db->fetch_field($db->simple_select("profilefields", "type", "fid = '".$stat['field']."'"), "type");
                            // in Array splitten
                            $expoptions = explode("\n", $options);
                            // Typ löschen (select, multiselect)
                            unset($expoptions['0']);

                            // gewünschte Optionen rauslöschen
                            if(!empty($stat['ignor_option'])) {
                                $ignor_option = str_replace(", ", ",", $stat['ignor_option']);
                                $ignor_option = explode (",", $ignor_option);

                                foreach ($ignor_option as $option) {
                                    unset($expoptions[$option]);
                                }
                            }

                            $data_options = "";
                            foreach ($expoptions as $data_option) {
                                $data_options .= $data_option.", ";
                            }
                            // letztes Komma vom String entfernen
                            $data_options = substr($data_options, 0, -2);

                        } 
                        // Katjas Steckbriefplugin
                        else {
                            $fieldname = $db->fetch_field($db->simple_select("application_ucp_fields", "label", "fieldname = '".$stat['field']."'"), "label");
                            $fieldsystem = $lang->playerdirectory_manage_overview_stat_fieldsystem_applicationfield;

                            // Daten Optionen
                            $options = $db->fetch_field($db->simple_select("application_ucp_fields", "options", "fieldname = '".$stat['field']."'"), "options");
                            // in Array splitten
                            $expoptions = str_replace(", ", ",", $options);
                            $expoptions = explode (",", $expoptions);

                            // gewünschte Optionen rauslöschen
                            if(!empty($stat['ignor_option'])) {
                                $ignor_option = str_replace(", ", ",", $stat['ignor_option']);
                                $ignor_option = explode (",", $ignor_option);

                                foreach ($ignor_option as $option) {
                                    $option_index = $option-1;
                                    unset($expoptions[$option_index]);
                                }
                            }

                            $data_options = "";
                            foreach ($expoptions as $data_option) {
                                $data_options .= $data_option.", ";
                            }
                            // letztes Komma vom String entfernen
                            $data_options = substr($data_options, 0, -2);
                        }


                        // Datenausgabe
                        $dataoption = $lang->sprintf($lang->playerdirectory_manage_overview_stat_field, $fieldsystem, $fieldname)."<br>
                        ".$lang->sprintf($lang->playerdirectory_manage_overview_stat_dataoptions, $data_options);
                    } else {

                        // Beachten Gruppe
                        // primär
                        if($stat['group_option'] == 1) {
                            $observe_option = $lang->playerdirectory_manage_overview_stat_observe_primary;
                        } 
                        // nur sekundär
                        else if($stat['group_option'] == 2) {
                            $observe_option = $lang->playerdirectory_manage_overview_stat_observe_secondary;
                        } 
                        // beides
                        else {
                            $observe_option = $lang->playerdirectory_manage_overview_stat_observe_both;
                        }

                        // Usergruppen Namen
                        $usergroups = explode(",", $stat['usergroups']);
                        $data_options = "";
                        foreach($usergroups as $usergroup) {				
                            $groupname = $db->fetch_field($db->simple_select("usergroups", "title", "gid = '".$usergroup."'"), "title");

                            $data_options .= $groupname.", ";
                        }
                        // letztes Komma vom String entfernen
                        $data_options = substr($data_options, 0, -2);

                        $dataoption = $lang->sprintf($lang->playerdirectory_manage_overview_stat_field, $lang->playerdirectory_manage_overview_stat_usergroups, $observe_option)."<br>
                        ".$lang->sprintf($lang->playerdirectory_manage_overview_stat_dataoptions, $data_options);
                    }

                    // Farben-Vorschau
                    if ($stat['type'] != 3) {

                        if ($stat['custom_properties'] != 1) {
                            $colors_string = str_replace(", ", ",", $stat['colors']);
                            $colors_array = explode (",", $colors_string);
                            $datacolor = "";
                            foreach ($colors_array as $color) {
                                $datacolor .= '<div style="display: flex;justify-content: center;align-items: center;"><div style="height: 11px;width: 10px;background:'.$color.';margin-right: 5px;"></div><div>'.$color.'</div></div>';
                            }
                            $data_colors = "<br><div style=\"display:flex;gap:5px;\">".$lang->sprintf($lang->playerdirectory_manage_overview_stat_color, $datacolor)."</div>";
                        } else {
                            $colors_string = str_replace(",", ", ", $stat['colors']);
                            $data_colors = "<br>".$lang->sprintf($lang->playerdirectory_manage_overview_stat_color, $colors_string);
                        }

                    } else {
                        $data_colors = "";
                    }

                    // AUSGABE DER INFOS
                    $form_container->output_cell("<strong><span style=\"font-size:0.9rem;\"><a href=\"index.php?module=user-playerdirectory&action=edit&psid=".$stat['psid']."\">".htmlspecialchars_uni($stat['name'])."</a></span></strong> 
                    ".$lang->sprintf($lang->playerdirectory_manage_overview_stat_variable, $stat['identification'])."<br />
                    ".$lang->sprintf($lang->playerdirectory_manage_overview_stat_presentation, $type)."</br>
                    ".$dataoption."
                    ".$data_colors."
                    ");

                    // OPTIONEN
                    $popup = new PopupMenu("playerdirectory_".$stat['psid'], $lang->playerdirectory_manage_overview_options);	
                    $popup->add_item(
                        $lang->playerdirectory_manage_overview_options_edit,
                        "index.php?module=user-playerdirectory&amp;action=edit&amp;psid=".$stat['psid']
                    );
                    $popup->add_item(
                        $lang->playerdirectory_manage_overview_options_delete,
                        "index.php?module=user-playerdirectory&amp;action=delete&amp;psid=".$stat['psid']."&amp;my_post_key={$mybb->post_code}", 
                        "return AdminCP.deleteConfirmation(this, '".$lang->playerdirectory_manage_overview_delete_notice."')"
                    );
                
                    $form_container->output_cell($popup->fetch(), array("class" => "align_center"));
                    $form_container->construct_row();
                }

                // keine Statistiken bisher vorhanden
                if($db->num_rows($query_statistics) == 0){
                    $form_container->output_cell($lang->playerdirectory_manage_no_elements, array("colspan" => 2));
                    $form_container->construct_row();
                }

            } else {
                $setting_gid = $db->fetch_field($db->simple_select("settinggroups", "gid", "name = 'playerdirectory'"), "gid");
                $form_container->output_cell($lang->sprintf($lang->playerdirectory_manage_overview_stat_color, $setting_gid), array("colspan" => 2));
                $form_container->construct_row();
            } 

            $form_container->end();
            $form->end();
            $page->output_footer();
			exit;
        }

        // STATISTIK HINZUFÜGEN
        if ($mybb->get_input('action') == "add") {
    
            // SPEICHERN
            if ($mybb->request_method == "post") {
    
                // Check if required fields are not empty
                if (empty($mybb->get_input('name'))) {
                    $errors[] = $lang->playerdirectory_manage_add_error_name;
                }
                if (empty($mybb->get_input('identification'))) {
                    $errors[] = $lang->playerdirectory_manage_add_error_identification;
                }
                if (empty($mybb->get_input('type'))) {
                    $errors[] = $lang->playerdirectory_manage_add_error_type;
                }
                if (($mybb->get_input('type') == 1 AND empty($mybb->get_input('colors'))) OR ($mybb->get_input('type') == 2 AND empty($mybb->get_input('colors')))  OR ($mybb->get_input('type') == 4 AND empty($mybb->get_input('colors')))) {
                    if ($mybb->get_input('type') == 1) {
                        $type = $lang->playerdirectory_manage_overview_stat_presentation_bar;
                    } else if ($mybb->get_input('type') == 2 OR $mybb->get_input('type') == 4) {
                        $type = $lang->playerdirectory_manage_overview_stat_presentation_pie;
                    }
                    $errors[] = $lang->sprintf($lang->playerdirectory_manage_add_error_colors, $type);
                }
                if (empty($mybb->get_input('dataselect'))) {
                    $errors[] = $lang->playerdirectory_manage_add_error_dataselect;
                }
                if ($mybb->get_input('dataselect') == 1 AND empty($mybb->get_input('profilefield'))) {
                    $dataselect = $lang->playerdirectory_manage_add_dataselect_profilefield; 
                    $errors[] = $lang->sprintf($lang->playerdirectory_manage_add_error_field, $dataselect);
                }
                if ($mybb->get_input('dataselect') == 2 AND empty($mybb->get_input('applicationfield'))) {
                    $dataselect = $lang->playerdirectory_manage_add_dataselect_applicationfield; 
                    $errors[] = $lang->sprintf($lang->playerdirectory_manage_add_error_field, $dataselect);
                }
                if ($mybb->get_input('dataselect') == 1 AND $mybb->get_input('profilefield') == "full") {
                    $dataselect = $lang->playerdirectory_manage_add_dataselect_profilefield; 
                    $errors[] = $lang->sprintf($lang->playerdirectory_manage_add_error_nonefield, $dataselect);
                }
                if ($mybb->get_input('dataselect') == 2 AND $mybb->get_input('applicationfield') == "full") {
                    $dataselect = $lang->playerdirectory_manage_add_dataselect_applicationfield; 
                    $errors[] = $lang->sprintf($lang->playerdirectory_manage_add_error_nonefield, $dataselect);
                }
                if ($mybb->get_input('dataselect') == 3 AND empty($mybb->input['usergroups'])) {
                    $errors[] = $lang->playerdirectory_manage_add_error_usergroups_none;
                }
                if ($mybb->get_input('dataselect') == 3 AND !empty($mybb->input['usergroups'])) {
                    if (count($mybb->input['usergroups']) < 2 ) {
                        $errors[] = $lang->playerdirectory_manage_add_error_usergroups_few;
                    }
                    if (($mybb->get_input('type') != 3 OR $mybb->get_input('type') != 0) AND !empty($mybb->get_input('colors'))){
                        // Zählen, wie viele Farben angegeben + weil letztes Komma abgeschnitten
                        $comma = substr_count($mybb->get_input('colors'), ',')+1;

                        if ($comma < count($mybb->input['usergroups'])) {
                            $missing = count($mybb->input['usergroups']) - $comma;
                            if ($missing == 1) {
                                $errors[] = $lang->sprintf($lang->playerdirectory_manage_add_error_colors_few_singular, $missing);
                            } else {
                                $errors[] = $lang->sprintf($lang->playerdirectory_manage_add_error_colors_few_plural, $missing);
                            }
                        }

                    }
                }
                if ($mybb->get_input('dataselect') == 3 AND empty($mybb->get_input('group_option'))) {
                    $errors[] = $lang->playerdirectory_manage_add_error_groupoption;
                }
                if (!empty($mybb->get_input('identification'))) {
                    $query_usedidentification = $db->query("SELECT * FROM ".TABLE_PREFIX."playerdirectory_statistics
                    WHERE identification = '".$mybb->get_input('identification')."'
                    ");
                    if ($db->num_rows($query_usedidentification) > 0) {
                        $errors[] = $lang->playerdirectory_manage_add_error_identification_double;
                    }
                }
                if (($mybb->get_input('type') != 3 OR $mybb->get_input('type') != 0) AND !empty($mybb->get_input('colors')) AND !empty($mybb->get_input('profilefield'))){

                    // Zählen, wie viele Farben angegeben + weil letztes Komma abgeschnitten
                    $comma = substr_count($mybb->get_input('colors'), ',')+1;

                    // Daten Optionen
                    $options = $db->fetch_field($db->simple_select("profilefields", "type", "fid = '".$mybb->get_input('profilefield')."'"), "type");
                    // in Array splitten
                    $expoptions = explode("\n", $options);
                    // Typ löschen (select, multiselect)
                    unset($expoptions['0']);

                    // gewünschte Optionen rauslöschen
                    if(!empty($mybb->get_input('ignor_option'))) {
                        $ignor_option = str_replace(", ", ",", $mybb->get_input('ignor_option'));
                        $ignor_option = explode (",", $ignor_option);

                        foreach ($ignor_option as $option) {
                            unset($expoptions[$option]);
                        }
                    }

                    $data_options = "";
                    foreach ($expoptions as $data_option) {
                        $data_options .= $data_option.",";
                    }
                    // letztes Komma vom String entfernen
                    $data_options = substr($data_options, 0, -1);

                    $data = substr_count($data_options, ',')+1;

                    if ($comma < $data) {
                        $missing = $data - $comma;
                        if ($missing == 1) {
                            $errors[] = $lang->sprintf($lang->playerdirectory_manage_add_error_colors_few_singular, $missing);
                        } else {
                            $errors[] = $lang->sprintf($lang->playerdirectory_manage_add_error_colors_few_plural, $missing);
                        }
                    }
                }
                if (($mybb->get_input('type') != 3 OR $mybb->get_input('type') != 0) AND !empty($mybb->get_input('colors')) AND !empty($mybb->get_input('applicationfield'))){

                    // Zählen, wie viele Farben angegeben + weil letztes Komma abgeschnitten
                    $comma = substr_count($mybb->get_input('colors'), ',')+1;
                    
                    // Daten Optionen
                    $options = $db->fetch_field($db->simple_select("application_ucp_fields", "options", "fieldname = '".$mybb->get_input('applicationfield')."'"), "options");
                    
                    // in Array splitten
                    $expoptions = str_replace(", ", ",", $options);                                               
                    $expoptions = explode (",", $expoptions);

                    // gewünschte Optionen rauslöschen
                    if(!empty($mybb->get_input('ignor_option'))) {
                        $ignor_option = str_replace(", ", ",", $mybb->get_input('ignor_option'));                        
                        $ignor_option = explode (",", $ignor_option);

                        foreach ($ignor_option as $option) {
                            $option_index = $option-1;
                            unset($expoptions[$option_index]);
                        }                                               
                    }

                    $data_options = "";
                    foreach ($expoptions as $data_option) {
                        $data_options .= $data_option.",";
                    }
                    // letztes Komma vom String entfernen
                    $data_options = substr($data_options, 0, -1);

                    $data = substr_count($data_options, ',')+1;

                    if ($comma < $data) {
                        $missing = $data - $comma;
                        if ($missing == 1) {
                            $errors[] = $lang->sprintf($lang->playerdirectory_manage_add_error_colors_few_singular, $missing);
                        } else {
                            $errors[] = $lang->sprintf($lang->playerdirectory_manage_add_error_colors_few_plural, $missing);
                        }
                    }
                }

                // No errors - insert
                if (empty($errors)) {

                    if (!empty($mybb->input['usergroups'])) {
                        $usergroups = implode(",", $mybb->input['usergroups']);
                    } else {
                        if (!empty($mybb->get_input('applicationfield'))) {
                            $input_field = $mybb->get_input('applicationfield');
                        } else {
                            $input_field = $mybb->get_input('profilefield');
                        }
                    }

                    if ($mybb->get_input('type') != 4) {
                        $type = $mybb->get_input('type', MyBB::INPUT_INT);
                        $legend = 0;
                    } else {
                        $type = 2;
                        $legend = 1;
                    }
    
                    // Daten speichern
                    $new_statistic = array(
                        "name" => $db->escape_string($mybb->get_input('name')),
                        "identification" => $db->escape_string($mybb->get_input('identification')),
                        "type" => $type,
                        "legend" => $legend,
                        "field" => $db->escape_string($input_field),
                        "ignor_option" => $db->escape_string($mybb->get_input('ignor_option')),
                        "usergroups" => $db->escape_string($usergroups),
                        "group_option" => $mybb->get_input('group_option', MyBB::INPUT_INT),
                        "colors" => $db->escape_string($mybb->get_input('colors')),
                        "custom_properties" => $mybb->get_input('custom_properties', MyBB::INPUT_INT),
                    );                    
                    
                    $db->insert_query("playerdirectory_statistics", $new_statistic);

                    $mybb->input['module'] = "Statistiken für die Spielerstatistik";
                    $mybb->input['action'] = $lang->playerdirectory_manage_add_logadmin;
                    log_admin_action(htmlspecialchars_uni($mybb->input['name']));
    
                    flash_message($lang->playerdirectory_manage_add_flash, 'success');
                    admin_redirect("index.php?module=user-playerdirectory");
                }
            }
    
            $page->add_breadcrumb_item($lang->playerdirectory_manage_add);
    
            // Editor scripts
            $page->extra_header .= '
            <link rel="stylesheet" href="../jscripts/sceditor/themes/mybb.css" type="text/css" media="all" />
            <script type="text/javascript" src="../jscripts/sceditor/jquery.sceditor.bbcode.min.js?ver=1832"></script>
            <script type="text/javascript" src="../jscripts/bbcodes_sceditor.js?ver=1832"></script>
            <script type="text/javascript" src="../jscripts/sceditor/plugins/undo.js?ver=1832"></script>
            <link href="./jscripts/codemirror/lib/codemirror.css?ver=1813" rel="stylesheet">
            <link href="./jscripts/codemirror/theme/mybb.css?ver=1813" rel="stylesheet">
            <script src="./jscripts/codemirror/lib/codemirror.js?ver=1813"></script>
            <script src="./jscripts/codemirror/mode/xml/xml.js?ver=1813"></script>
            <script src="./jscripts/codemirror/mode/javascript/javascript.js?ver=1813"></script>
            <script src="./jscripts/codemirror/mode/css/css.js?ver=1813"></script>
            <script src="./jscripts/codemirror/mode/htmlmixed/htmlmixed.js?ver=1813"></script>
            <link href="./jscripts/codemirror/addon/dialog/dialog-mybb.css?ver=1813" rel="stylesheet">
            <script src="./jscripts/codemirror/addon/dialog/dialog.js?ver=1813"></script>
            <script src="./jscripts/codemirror/addon/search/searchcursor.js?ver=1813"></script>
            <script src="./jscripts/codemirror/addon/search/search.js?ver=1821"></script>
            <script src="./jscripts/codemirror/addon/fold/foldcode.js?ver=1813"></script>
            <script src="./jscripts/codemirror/addon/fold/xml-fold.js?ver=1813"></script>
            <script src="./jscripts/codemirror/addon/fold/foldgutter.js?ver=1813"></script>
            <link href="./jscripts/codemirror/addon/fold/foldgutter.css?ver=1813" rel="stylesheet">
            ';
    
            // Build options header
            $page->output_header($lang->playerdirectory_manage_header." - ".$lang->playerdirectory_manage_add);
    
            // Übersichtsseite Button
			$sub_tabs['playerdirectory'] = [
				"title" => $lang->playerdirectory_manage_overview,
				"link" => "index.php?module=user-playerdirectory",
				"description" => $lang->playerdirectory_manage_overview_desc
			];
			// Hinzufüge Button
			$sub_tabs['playerdirectory_add'] = [
				"title" => $lang->playerdirectory_manage_add,
				"link" => "index.php?module=user-playerdirectory&amp;action=add",
				"description" => $lang->playerdirectory_manage_add_desc
			];
    
            $page->output_nav_tabs($sub_tabs, 'playerdirectory_add');
    
            // Show errors
            if (isset($errors)) {
                $page->output_inline_error($errors);
            } else {
                $mybb->input['usergroups'] = "";
            }

            // Katjas Steckbrief Plugin
            if ($profilfeldsystem != 0) {
        
                // Passende Profilfelder auslesen
                $query_applicationfield = $db->query("SELECT * FROM ".TABLE_PREFIX."application_ucp_fields
                WHERE fieldtyp IN ('select','radio','multiselect','select_multiple','checkbox')
                AND fieldname NOT IN (SELECT field FROM ".TABLE_PREFIX."playerdirectory_statistics WHERE field != '')
                ORDER BY sorting ASC, label ASC       
                ");
        
                $applicationfield_list = [];
                while($fields = $db->fetch_array($query_applicationfield)) {
                    $applicationfield_list[$fields['fieldname']] = $fields['label'];
                }
            } else {
                $applicationfield_list = "";
            }
            
            // Klassische Profilfelder
            if ($profilfeldsystem != 1) {
        
                // Passende Profilfelder auslesen
                $query_profilefields = $db->query("SELECT * FROM ".TABLE_PREFIX."profilefields
                WHERE fid NOT IN (SELECT field FROM ".TABLE_PREFIX."playerdirectory_statistics WHERE field != '')
                AND (type LIKE 'select%'
                OR type LIKE 'radio%'
                OR type LIKE 'multiselect%'
                OR type LIKE 'checkbox%'
                OR type LIKE 'select_multiple%')
                ORDER BY disporder ASC, name ASC
                ");
        
                $profilefield_list = [];
                while($fields = $db->fetch_array($query_profilefields)) {
                    $profilefield_list[$fields['fid']] = $fields['name'];
                }
            } else {
                $profilefield_list = "";
            }

            // verwendete Gruppen
            $query_usedgroups = $db->query("SELECT * FROM ".TABLE_PREFIX."playerdirectory_statistics
            WHERE usergroups != ''
            ");
            $usedgroups = "";
            while($usedg = $db->fetch_array($query_usedgroups)) {
                $usedgroups = $usedg['usergroups'].",";
            }
            // letztes Komma vom String entfernen
            $usedgroups = substr($usedgroups, 0, -1);
            if ($db->num_rows($query_usedgroups) > 0) {
                $usergroup_sql = "WHERE gid NOT IN (".$usedgroups.") AND gid != '1'";
            } else {
                $usergroup_sql = "WHERE gid != '1'";
            }
            
            // Benutzergruppen auslesen
            $query_usergroups = $db->query("SELECT * FROM ".TABLE_PREFIX."usergroups
            ".$usergroup_sql."
            ORDER BY disporder ASC
            ");
        
            $usergroups_list = [];
            while($group = $db->fetch_array($query_usergroups)) {
                $usergroups_list[$group['gid']] = $group['title'];
            }

            // Build the form
            $form = new Form("index.php?module=user-playerdirectory&amp;action=add", "post", "", 1);
            $form_container = new FormContainer($lang->playerdirectory_manage_add);
    
            // Name
            $form_container->output_row(
                $lang->playerdirectory_manage_add_name,
                $lang->playerdirectory_manage_add_name_desc,
                $form->generate_text_box('name', $mybb->get_input('name'))
            );

            // Identifikator
            $form_container->output_row(
                $lang->playerdirectory_manage_add_identification,
                $lang->playerdirectory_manage_add_identification_desc,
                $form->generate_text_box('identification', $mybb->get_input('identification'))
            );
    
            // Darstellung
            $form_container->output_row(
                $lang->playerdirectory_manage_add_type,
                $lang->playerdirectory_manage_add_type_desc,
                $form->generate_select_box('type', $typeselect_list, $mybb->get_input('type'), array('id' => 'type')),
				'type'
            );
    
            // Profilfelder oder Usergruppe
            $form_container->output_row(
                $lang->playerdirectory_manage_add_dataselect,
                $lang->playerdirectory_manage_add_dataselect_desc,
                $form->generate_select_box('dataselect', $dataselect_list, $mybb->get_input('dataselect'), array('id' => 'dataselect')),
				'dataselect'
            );

            // Profilfeld/Steckbrieffeld
            // Feld ID / Feld identifikator
            // Profilfeld
            if ($profilfeldsystem != 1) { 
                $count_profilefields = $db->num_rows($query_profilefields);
                if ($count_profilefields > 0) {
                    $form_container->output_row(
                        $lang->playerdirectory_manage_add_profilefield, 
                        $lang->playerdirectory_manage_add_profilefield_desc, 
                        $form->generate_select_box('profilefield', $profilefield_list, $mybb->get_input('profilefield'), array('id' => 'profilefield', 'size' => 5)),
                        'profilefield', array(), array('id' => 'row_profilefield')
                    );
                } else {
                    $form_container->output_row(
                        $lang->playerdirectory_manage_add_profilefield,
                        $lang->playerdirectory_manage_add_profilefield_desc,
                        $form->generate_select_box('profilefield', $nonefields_list, $mybb->get_input('profilefield'), array('id' => 'profilefield')),
                        'profilefield', array(), array('id' => 'row_profilefield')
                    );
                }
            }
            // Steckbrieffeld
            if ($profilfeldsystem != 0) { 
                $count_applicationfield = $db->num_rows($query_applicationfield);
                if ($count_applicationfield > 0) {
                    $form_container->output_row(
                        $lang->playerdirectory_manage_add_applicationfield, 
                        $lang->playerdirectory_manage_add_applicationfield_desc, 
                        $form->generate_select_box('applicationfield', $applicationfield_list, $mybb->get_input('applicationfield'), array('id' => 'applicationfield', 'size' => 5)),
                        'applicationfield', array(), array('id' => 'row_applicationfield')
                    );
                } else {
                    $form_container->output_row(
                        $lang->playerdirectory_manage_add_applicationfield,
                        $lang->playerdirectory_manage_add_applicationfield_desc,
                        $form->generate_select_box('applicationfield', $nonefields_list, $mybb->get_input('applicationfield'), array('id' => 'applicationfield')),
                        'applicationfield', array(), array('id' => 'row_applicationfield')
                    );
                }
            }
            // Auszuschließende Optionen
            $form_container->output_row(
                $lang->playerdirectory_manage_add_ignor,
                $lang->playerdirectory_manage_add_ignor_desc,
                $form->generate_text_box('ignor_option', $mybb->get_input('ignor_option'), array('id' => 'ignor_option')), 
                'ignor_option', array(), array('id' => 'row_ignor_option')
            );

            // Usergruppen
            $form_container->output_row(
                $lang->playerdirectory_manage_add_usergroups, 
                $lang->playerdirectory_manage_add_usergroups_desc, 
                $form->generate_select_box('usergroups[]', $usergroups_list, $mybb->input['usergroups'], array('id' => 'usergroups', 'multiple' => true, 'size' => 5)),
                'usergroups', array(), array('id' => 'row_usergroups')
            );

            // Welche Gruppen beachten
            $form_container->output_row(
                $lang->playerdirectory_manage_add_groupoption,
                $lang->playerdirectory_manage_add_groupoption_desc,
                $form->generate_select_box('group_option', $groupoption_list, $mybb->get_input('group_option'), array('id' => 'group_option')),
				'group_option', array(), array('id' => 'row_group_option')
            );
    
            // Farben  
            $color_options = array(
                "<small class=\"input\">{$lang->playerdirectory_manage_add_colors_desc}</small><br />".
                $form->generate_text_area('colors', $mybb->get_input('colors'), array('id' => 'colors')),
                $form->generate_check_box("custom_properties", 1, $lang->playerdirectory_manage_add_custom_properties, array("checked" => $mybb->input['custom_properties'])),
            );
            $form_container->output_row(
                $lang->playerdirectory_manage_add_colors, 
                "", 
                "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $color_options)."</div>",
				'colors', array(), array('id' => 'row_colors')
            );
        

            $form_container->end();
            $buttons[] = $form->generate_submit_button($lang->playerdirectory_manage_add_button);
            $form->output_submit_wrapper($buttons);
                
            $form->end();

            echo '<script type="text/javascript" src="./jscripts/peeker.js?ver=1821"></script>
			<script type="text/javascript">
			$(function() {
                new Peeker($("#dataselect"), $("#row_usergroups, #row_group_option"), /^3/, false);
                new Peeker($("#dataselect"), $("#row_applicationfield"), /^2/, false);
                new Peeker($("#dataselect"), $("#row_profilefield"), /^1/, false);
                new Peeker($("#dataselect"), $("#row_ignor_option"), /^1|^2/, false);
                new Peeker($("#type"), $("#row_colors"), /^1|^2|^4/, false);
				});
				</script>';
            
            $page->output_footer();
            exit;
        }

        // STATISTIK BEARBEITEN
        if ($mybb->get_input('action') == "edit") {
            
            // Get the data
            $psid = $mybb->get_input('psid', MyBB::INPUT_INT);
            $statistic_query = $db->simple_select("playerdirectory_statistics", "*", "psid = '".$psid."'");
            $statistic = $db->fetch_array($statistic_query);
    
            // SPEICHERN
            if ($mybb->request_method == "post") {
    
                // Check if required fields are not empty
                if (empty($mybb->get_input('name'))) {
                    $errors[] = $lang->playerdirectory_manage_add_error_name;
                }
                if (empty($mybb->get_input('identification'))) {
                    $errors[] = $lang->playerdirectory_manage_add_error_identification;
                }
                if (empty($mybb->get_input('type'))) {
                    $errors[] = $lang->playerdirectory_manage_add_error_type;
                }
                if (($mybb->get_input('type') == 1 AND empty($mybb->get_input('colors'))) OR ($mybb->get_input('type') == 2 AND empty($mybb->get_input('colors')))  OR ($mybb->get_input('type') == 4 AND empty($mybb->get_input('colors')))) {
                    if ($mybb->get_input('type') == 1) {
                        $type = $lang->playerdirectory_manage_overview_stat_presentation_bar;
                    } else if ($mybb->get_input('type') == 2 OR $mybb->get_input('type') == 4) {
                        $type = $lang->playerdirectory_manage_overview_stat_presentation_pie;
                    }
                    $errors[] = $lang->sprintf($lang->playerdirectory_manage_add_error_colors, $type);
                }
                if (empty($mybb->get_input('dataselect'))) {
                    $errors[] = $lang->playerdirectory_manage_add_error_dataselect;
                }
                if ($mybb->get_input('dataselect') == 1 AND empty($mybb->get_input('profilefield'))) {
                    $dataselect = $lang->playerdirectory_manage_add_dataselect_profilefield; 
                    $errors[] = $lang->sprintf($lang->playerdirectory_manage_add_error_field, $dataselect);
                }
                if ($mybb->get_input('dataselect') == 2 AND empty($mybb->get_input('applicationfield'))) {
                    $dataselect = $lang->playerdirectory_manage_add_dataselect_applicationfield; 
                    $errors[] = $lang->sprintf($lang->playerdirectory_manage_add_error_field, $dataselect);
                }
                if ($mybb->get_input('dataselect') == 1 AND $mybb->get_input('profilefield') == "full") {
                    $dataselect = $lang->playerdirectory_manage_add_dataselect_profilefield; 
                    $errors[] = $lang->sprintf($lang->playerdirectory_manage_add_error_nonefield, $dataselect);
                }
                if ($mybb->get_input('dataselect') == 2 AND $mybb->get_input('applicationfield') == "full") {
                    $dataselect = $lang->playerdirectory_manage_add_dataselect_applicationfield; 
                    $errors[] = $lang->sprintf($lang->playerdirectory_manage_add_error_nonefield, $dataselect);
                }
                if ($mybb->get_input('dataselect') == 3 AND empty($mybb->input['usergroups'])) {
                    $errors[] = $lang->playerdirectory_manage_add_error_usergroups_none;
                }
                if ($mybb->get_input('dataselect') == 3 AND !empty($mybb->input['usergroups'])) {
                    if (count($mybb->input['usergroups']) < 2 ) {
                        $errors[] = $lang->playerdirectory_manage_add_error_usergroups_few;
                    }
                    if (($mybb->get_input('type') != 3 OR $mybb->get_input('type') != 0) AND !empty($mybb->get_input('colors'))){
                        // Zählen, wie viele Farben angegeben + weil letztes Komma abgeschnitten
                        $comma = substr_count($mybb->get_input('colors'), ',')+1;

                        if ($comma < count($mybb->input['usergroups'])) {
                            $missing = count($mybb->input['usergroups']) - $comma;
                            if ($missing == 1) {
                                $errors[] = $lang->sprintf($lang->playerdirectory_manage_add_error_colors_few_singular, $missing);
                            } else {
                                $errors[] = $lang->sprintf($lang->playerdirectory_manage_add_error_colors_few_plural, $missing);
                            }
                        }

                    }
                }
                if ($mybb->get_input('dataselect') == 3 AND empty($mybb->get_input('group_option'))) {
                    $errors[] = $lang->playerdirectory_manage_add_error_groupoption;
                }
                if (!empty($mybb->get_input('identification'))) {
                    $query_usedidentification = $db->query("SELECT * FROM ".TABLE_PREFIX."playerdirectory_statistics
                    WHERE identification = '".$mybb->get_input('identification')."'
                    AND psid != '".$psid."' 
                    ");
                    if ($db->num_rows($query_usedidentification) > 0) {
                        $errors[] = $lang->playerdirectory_manage_add_error_identification_double;
                    }
                }
                if (($mybb->get_input('type') != 3 OR $mybb->get_input('type') != 0) AND !empty($mybb->get_input('colors')) AND !empty($mybb->get_input('profilefield'))){

                    // Zählen, wie viele Farben angegeben + weil letztes Komma abgeschnitten
                    $comma = substr_count($mybb->get_input('colors'), ',')+1;

                    // Daten Optionen
                    $options = $db->fetch_field($db->simple_select("profilefields", "type", "fid = '".$mybb->get_input('profilefield')."'"), "type");
                    // in Array splitten
                    $expoptions = explode("\n", $options);
                    // Typ löschen (select, multiselect)
                    unset($expoptions['0']);

                    // gewünschte Optionen rauslöschen
                    if(!empty($mybb->get_input('ignor_option'))) {
                        $ignor_option = str_replace(", ", ",", $mybb->get_input('ignor_option'));
                        $ignor_option = explode (",", $ignor_option);

                        foreach ($ignor_option as $option) {
                            unset($expoptions[$option]);
                        }
                    }

                    $data_options = "";
                    foreach ($expoptions as $data_option) {
                        $data_options .= $data_option.",";
                    }
                    // letztes Komma vom String entfernen
                    $data_options = substr($data_options, 0, -1);

                    $data = substr_count($data_options, ',')+1;

                    if ($comma < $data) {
                        $missing = $data - $comma;
                        if ($missing == 1) {
                            $errors[] = $lang->sprintf($lang->playerdirectory_manage_add_error_colors_few_singular, $missing);
                        } else {
                            $errors[] = $lang->sprintf($lang->playerdirectory_manage_add_error_colors_few_plural, $missing);
                        }
                    }
                }
                if (($mybb->get_input('type') != 3 OR $mybb->get_input('type') != 0) AND !empty($mybb->get_input('colors')) AND !empty($mybb->get_input('applicationfield'))){

                    // Zählen, wie viele Farben angegeben + weil letztes Komma abgeschnitten
                    $comma = substr_count($mybb->get_input('colors'), ',')+1;

                    // Daten Optionen
                    $options = $db->fetch_field($db->simple_select("application_ucp_fields", "options", "fieldname = '".$mybb->get_input('applicationfield')."'"), "options");
                    
                    // in Array splitten
                    $expoptions = str_replace(", ", ",", $options);                                               
                    $expoptions = explode (",", $expoptions);

                    // gewünschte Optionen rauslöschen
                    if(!empty($mybb->get_input('ignor_option'))) {
                        $ignor_option = str_replace(", ", ",", $mybb->get_input('ignor_option'));                        
                        $ignor_option = explode (",", $ignor_option);

                        foreach ($ignor_option as $option) {
                            $option_index = $option-1;
                            unset($expoptions[$option_index]);
                        }                                               
                    }

                    $data_options = "";
                    foreach ($expoptions as $data_option) {
                        $data_options .= $data_option.",";
                    }
                    // letztes Komma vom String entfernen
                    $data_options = substr($data_options, 0, -1);

                    $data = substr_count($data_options, ',')+1;

                    if ($comma < $data) {
                        $missing = $data - $comma;
                        if ($missing == 1) {
                            $errors[] = $lang->sprintf($lang->playerdirectory_manage_add_error_colors_few_singular, $missing);
                        } else {
                            $errors[] = $lang->sprintf($lang->playerdirectory_manage_add_error_colors_few_plural, $missing);
                        }
                    }
                }

                // No errors - insert
                if (empty($errors)) {

                    if (!empty($mybb->input['usergroups'])) {
                        $usergroups = implode(",", $mybb->input['usergroups']);
                    } else {
                        if (!empty($mybb->get_input('applicationfield'))) {
                            $input_field = $mybb->get_input('applicationfield');
                        } else {
                            $input_field = $mybb->get_input('profilefield');
                        }
                    }

                    if ($mybb->get_input('type') != 4) {
                        $type = $mybb->get_input('type', MyBB::INPUT_INT);
                        $legend = 0;
                    } else {
                        $type = 2;
                        $legend = 1;
                    }
    
                    // Daten updaten
                    $update_statistic = array(
                        "name" => $db->escape_string($mybb->get_input('name')),
                        "identification" => $db->escape_string($mybb->get_input('identification')),
                        "type" => $type,
                        "legend" => $legend,
                        "field" => $db->escape_string($input_field),
                        "ignor_option" => $db->escape_string($mybb->get_input('ignor_option')),
                        "usergroups" => $db->escape_string($usergroups),
                        "group_option" => $mybb->get_input('group_option', MyBB::INPUT_INT),
                        "colors" => $db->escape_string($mybb->get_input('colors')),
                        "custom_properties" => $mybb->get_input('custom_properties', MyBB::INPUT_INT),
                    );              

                    $db->update_query("playerdirectory_statistics", $update_statistic, "psid='".$psid."'");
    
                    $mybb->input['module'] = "Statistiken für die Spielerstatistik";
                    $mybb->input['action'] = $lang->playerdirectory_manage_edit_logadmin;
                    log_admin_action(htmlspecialchars_uni($mybb->input['name']));
    
                    flash_message($lang->playerdirectory_manage_edit_flash, 'success');
                    admin_redirect("index.php?module=user-playerdirectory");
                }
            }
    
            $page->add_breadcrumb_item($lang->playerdirectory_manage_edit);
    
            // Editor scripts
            $page->extra_header .= '
            <link rel="stylesheet" href="../jscripts/sceditor/themes/mybb.css" type="text/css" media="all" />
            <script type="text/javascript" src="../jscripts/sceditor/jquery.sceditor.bbcode.min.js?ver=1832"></script>
            <script type="text/javascript" src="../jscripts/bbcodes_sceditor.js?ver=1832"></script>
            <script type="text/javascript" src="../jscripts/sceditor/plugins/undo.js?ver=1832"></script>
            <link href="./jscripts/codemirror/lib/codemirror.css?ver=1813" rel="stylesheet">
            <link href="./jscripts/codemirror/theme/mybb.css?ver=1813" rel="stylesheet">
            <script src="./jscripts/codemirror/lib/codemirror.js?ver=1813"></script>
            <script src="./jscripts/codemirror/mode/xml/xml.js?ver=1813"></script>
            <script src="./jscripts/codemirror/mode/javascript/javascript.js?ver=1813"></script>
            <script src="./jscripts/codemirror/mode/css/css.js?ver=1813"></script>
            <script src="./jscripts/codemirror/mode/htmlmixed/htmlmixed.js?ver=1813"></script>
            <link href="./jscripts/codemirror/addon/dialog/dialog-mybb.css?ver=1813" rel="stylesheet">
            <script src="./jscripts/codemirror/addon/dialog/dialog.js?ver=1813"></script>
            <script src="./jscripts/codemirror/addon/search/searchcursor.js?ver=1813"></script>
            <script src="./jscripts/codemirror/addon/search/search.js?ver=1821"></script>
            <script src="./jscripts/codemirror/addon/fold/foldcode.js?ver=1813"></script>
            <script src="./jscripts/codemirror/addon/fold/xml-fold.js?ver=1813"></script>
            <script src="./jscripts/codemirror/addon/fold/foldgutter.js?ver=1813"></script>
            <link href="./jscripts/codemirror/addon/fold/foldgutter.css?ver=1813" rel="stylesheet">
            ';
    
            // Build options header
            $page->output_header($lang->playerdirectory_manage_header." - ".$lang->playerdirectory_manage_edit);
    
            // Übersichtsseite Button
            $sub_tabs['playerdirectory_edit'] = [
                "title" => $lang->playerdirectory_manage_edit,
                "link" => "index.php?module=user-playerdirectory&amp;action=edit&psid=".$psid,
                "description" => $lang->playerdirectory_manage_edit_desc
            ];
    
            $page->output_nav_tabs($sub_tabs, 'playerdirectory_edit');
    
            // Show errors
            if (isset($errors)) {
                $page->output_inline_error($errors);

                $statistic['name'] = $mybb->get_input('name');
                $statistic['identification'] = $mybb->get_input('identification');
                $statistic['type'] = $mybb->get_input('type');
                $statistic['dataselect'] = $mybb->get_input('dataselect');

                // Profilfeld
                if ($statistic['dataselect'] == 1) {
                    $statistic['field'] = $mybb->get_input('profilefield');
                } 
                // Steckbriefplugin
                else if ($statistic['dataselect'] == 2) {
                    $statistic['field'] = $mybb->get_input('applicationfield');
                }
                // Gruppen
                else if ($statistic['dataselect'] == 3) {
                    if (!empty($mybb->input['usergroups'])) {
                        $statistic['usergroups'] = implode(",", $mybb->input['usergroups']);
                    } else {
                        $statistic['usergroups'] = "";
                    }
                }

                $statistic['ignor_option'] = $mybb->get_input('ignor_option');
                $statistic['group_option'] = $mybb->get_input('group_option');
                $statistic['colors'] = $mybb->get_input('colors');
                $statistic['custom_properties'] = $mybb->get_input('custom_properties');


            } else {

                if (!empty($statistic['field'])) {
                    if (is_numeric($statistic['field'])) {
                        $statistic['dataselect'] = 1;
                    } else {
                        $statistic['dataselect'] = 2;
                    }
                } else {
                    $statistic['dataselect'] = 3;
                }
            }

            // Katjas Steckbrief Plugin
            if ($profilfeldsystem != 0) {   

                // Passende Profilfelder auslesen
                $query_applicationfield = $db->query("SELECT * FROM ".TABLE_PREFIX."application_ucp_fields
                WHERE fieldtyp IN ('select','radio','multiselect','select_multiple','checkbox')
                AND fieldname NOT IN (
                    SELECT field FROM ".TABLE_PREFIX."playerdirectory_statistics 
                    WHERE field != ''
                    AND field != '".$statistic['field']."'
                )
                ORDER BY sorting ASC, label ASC          
                ");

                $applicationfield_list = [];
                while($fields = $db->fetch_array($query_applicationfield)) {
                    $applicationfield_list[$fields['fieldname']] = $fields['label'];
                }
            } else {
                $applicationfield_list = "";
            }
    
            // Klassische Profilfelder
            if ($profilfeldsystem != 1) {   

                // Passende Profilfelder auslesen
                $query_profilefields = $db->query("SELECT * FROM ".TABLE_PREFIX."profilefields
                WHERE fid NOT IN (
                    SELECT field FROM ".TABLE_PREFIX."playerdirectory_statistics 
                    WHERE field != ''
                    AND field != '".$statistic['field']."'
                )
                AND (type LIKE 'select%'
                OR type LIKE 'radio%'
                OR type LIKE 'multiselect%'
                OR type LIKE 'checkbox%'
                OR type LIKE 'select_multiple%')
                ORDER BY disporder ASC, name ASC   
                ");

                $profilefield_list = [];
                while($fields = $db->fetch_array($query_profilefields)) {
                    $profilefield_list[$fields['fid']] = $fields['name'];
                }
            } else {
                $profilefield_list = "";
            }

            // verwendete Gruppen
            $query_usedgroups = $db->query("SELECT * FROM ".TABLE_PREFIX."playerdirectory_statistics
            WHERE usergroups != ''
            AND psid != '".$psid."'
            ");
            $usedgroups = "";
            while($usedg = $db->fetch_array($query_usedgroups)) {
                $usedgroups = $usedg['usergroups'].",";
            }
            // letztes Komma vom String entfernen
            $usedgroups = substr($usedgroups, 0, -1);
            if ($db->num_rows($query_usedgroups) > 0) {
                $usergroup_sql = "WHERE gid NOT IN (".$usedgroups.") AND gid != '1'";
            } else {
                $usergroup_sql = "WHERE gid != '1'";
            }
    
            // Benutzergruppen auslesen
            $query_usergroups = $db->query("SELECT * FROM ".TABLE_PREFIX."usergroups
            ".$usergroup_sql."
            ORDER BY disporder ASC   
            ");

            $usergroups_list = [];
            while($group = $db->fetch_array($query_usergroups)) {
                $usergroups_list[$group['gid']] = $group['title'];
            }
    
            // Build the form
            $form = new Form("index.php?module=user-playerdirectory&amp;action=edit", "post", "", 1);
            $form_container = new FormContainer($lang->sprintf($lang->playerdirectory_manage_edit_container, $statistic['name']));
            echo $form->generate_hidden_field('psid', $psid);
    
            // Name
            $form_container->output_row(
                $lang->playerdirectory_manage_add_name,
                $lang->playerdirectory_manage_add_name_desc,
                $form->generate_text_box('name', $statistic['name'])
            );

            // Identifikator
            $form_container->output_row(
                $lang->playerdirectory_manage_add_identification,
                $lang->playerdirectory_manage_add_identification_desc,
                $form->generate_text_box('identification', $statistic['identification'])
            );
    
            // Darstellung
            $form_container->output_row(
                $lang->playerdirectory_manage_add_type, 
                $lang->playerdirectory_manage_add_type_desc, 
                $form->generate_select_box('type', $typeselect_list, $statistic['type'], array('id' => 'type'))
            );
    
            // Profilfelder oder Usergruppe
            $form_container->output_row(
                $lang->playerdirectory_manage_add_dataselect,
                $lang->playerdirectory_manage_add_dataselect_desc,
                $form->generate_select_box('dataselect', $dataselect_list, $statistic['dataselect'], array('id' => 'dataselect')),
				'dataselect'
            );

            // Profilfeld/Steckbrieffeld
            // Feld ID / Feld identifikator
            // Profilfeld
            if ($profilfeldsystem != 1) {
                $form_container->output_row(
                    $lang->playerdirectory_manage_add_profilefield, 
                    $lang->playerdirectory_manage_add_profilefield_desc, 
                    $form->generate_select_box('profilefield', $profilefield_list, $statistic['field'], array('id' => 'profilefield', 'size' => 5)),
                    'profilefield', array(), array('id' => 'row_profilefield')
                );
            }
            // Steckbrieffeld
            if ($profilfeldsystem != 0) {
                $form_container->output_row(
                    $lang->playerdirectory_manage_add_applicationfield, 
                    $lang->playerdirectory_manage_add_applicationfield_desc, 
                    $form->generate_select_box('applicationfield', $applicationfield_list, $statistic['field'], array('id' => 'applicationfield', 'size' => 5)),
                    'applicationfield', array(), array('id' => 'row_applicationfield')
                );
            }
            
            // Auszuschließende Optionen
            $form_container->output_row(
                $lang->playerdirectory_manage_add_ignor,
                $lang->playerdirectory_manage_add_ignor_desc,
                $form->generate_text_box('ignor_option', $statistic['ignor_option'], array('id' => 'ignor_option')), 
                'ignor_option', array(), array('id' => 'row_ignor_option')
            );

            // Usergruppen
            if (!empty($statistic['usergroups'])) {
                $usergroups = explode(",", $statistic['usergroups']);
            } else {
                $usergroups = "";
            }
            $form_container->output_row(
                $lang->playerdirectory_manage_add_usergroups, 
                $lang->playerdirectory_manage_add_usergroups_desc, 
                $form->generate_select_box('usergroups[]', $usergroups_list, $usergroups, array('id' => 'usergroups', 'multiple' => true, 'size' => 5)),
                'usergroups', array(), array('id' => 'row_usergroups')
            );

            // Welche Gruppen beachten
            $form_container->output_row(
                $lang->playerdirectory_manage_add_groupoption,
                $lang->playerdirectory_manage_add_groupoption_desc,
                $form->generate_select_box('group_option', $groupoption_list, $statistic['group_option'], array('id' => 'group_option')),
				'group_option', array(), array('id' => 'row_group_option')
            );
    
            // Farben  
            $color_options = array(
                "<small class=\"input\">{$lang->playerdirectory_manage_add_colors_desc}</small><br />".
                $form->generate_text_area('colors', $statistic['colors'], array('id' => 'colors')),
                $form->generate_check_box("custom_properties", 1, $lang->playerdirectory_manage_add_custom_properties, array("checked" => $statistic['custom_properties'])),
            );
            $form_container->output_row(
                $lang->playerdirectory_manage_add_colors, 
                "", 
                "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $color_options)."</div>",
				'colors', array(), array('id' => 'row_colors')
            );

            $form_container->end();
            $buttons[] = $form->generate_submit_button($lang->playerdirectory_manage_edit_button);
            $form->output_submit_wrapper($buttons);
                
            $form->end();

            echo '<script type="text/javascript" src="./jscripts/peeker.js?ver=1821"></script>
			<script type="text/javascript">
			$(function() {
                new Peeker($("#dataselect"), $("#row_usergroups, #row_group_option"), /^3/, false);
                new Peeker($("#dataselect"), $("#row_applicationfield"), /^2/, false);
                new Peeker($("#dataselect"), $("#row_profilefield"), /^1/, false);
                new Peeker($("#dataselect"), $("#row_ignor_option"), /^1|^2/, false);
                new Peeker($("#type"), $("#row_colors"), /^1|^2|^4/, false);
				});
				</script>';
            
            $page->output_footer();
            exit;
        }

        // STATISTIK LÖSCHEN
		if ($mybb->input['action'] == "delete") {

			// Get data
			$psid = $mybb->get_input('psid', MyBB::INPUT_INT);
			$query = $db->simple_select("playerdirectory_statistics", "*", "psid='".$psid."'");
			$del_type = $db->fetch_array($query);

			// Error Handling
			if (empty($psid)) {
				flash_message($lang->playerdirectory_manage_error_invalid, 'error');
				admin_redirect("index.php?module=user-playerdirectory");
			}

			// Cancel button pressed?
			if (isset($mybb->input['no']) && $mybb->input['no']) {
				admin_redirect("index.php?module=user-playerdirectory");
			}

			if ($mybb->request_method == "post") {

                // Element aus der DB löschen
				$db->delete_query("playerdirectory_statistics", "psid = '".$psid."'");	

				$mybb->input['module'] = "Statistiken für die Spielerstatistik";
				$mybb->input['action'] = $lang->playerdirectory_manage_overview_delete_logadmin;
				log_admin_action(htmlspecialchars_uni($del_type['name']));

				flash_message($lang->playerdirectory_manage_overview_delete_flash, 'success');
				admin_redirect("index.php?module=user-playerdirectory");
			} else {
				$page->output_confirm_action(
					"index.php?module=user-playerdirectory&amp;action=delete&amp;psid=".$psid,
					$lang->playerdirectory_manage_overview_delete_notice
				);
			}
			exit;
		}

    }
}

// ADMIN-CP PEEKER
function playerdirectory_settings_change(){
    
    global $db, $mybb, $playerdirectory_settings_peeker;

    $result = $db->simple_select('settinggroups', 'gid', "name='playerdirectory'", array("limit" => 1));
    $group = $db->fetch_array($result);
    $playerdirectory_settings_peeker = ($mybb->input['gid'] == $group['gid']) && ($mybb->request_method != 'post');
}
function playerdirectory_settings_peek(&$peekers){

    global $mybb, $playerdirectory_settings_peeker;

    // Spielerverzeichnis
	if ($playerdirectory_settings_peeker) {
        $peekers[] = 'new Peeker($(".setting_playerdirectory_directory"), $("#row_setting_playerdirectory_directory_guest, #row_setting_playerdirectory_directory_multipage, #row_setting_playerdirectory_directory_teamaccounts"),/1/,true)';
    }

    // Spielerstatistik
	if ($playerdirectory_settings_peeker) {
        $peekers[] = 'new Peeker($(".setting_playerdirectory_playerstat"), $("#row_setting_playerdirectory_playerstat_guest"),/1/,true)';
    }

    // Charakterstatistik
	if ($playerdirectory_settings_peeker) {
        $peekers[] = 'new Peeker($(".setting_playerdirectory_characterstat"), $("#row_setting_playerdirectory_characterstat_guest"),/1/,true)';
    }

    // Geburtstag
	if ($playerdirectory_settings_peeker) {
        $peekers[] = 'new Peeker($("#setting_playerdirectory_birthday"), $("#row_setting_playerdirectory_birthday_field"),/^0/,false)';
    }
	if ($playerdirectory_settings_peeker) {
        $peekers[] = 'new Peeker($("#setting_playerdirectory_birthday"), $("#row_setting_playerdirectory_inplayday"),/^0|^1/,false)';
    }
    if ($playerdirectory_settings_peeker) {
        $peekers[] = 'new Peeker($("#setting_playerdirectory_birthday"), $("#row_setting_playerdirectory_age_field"),/^2/,false)';
    }

    // Gegenüberstellung
    if ($playerdirectory_settings_peeker) {
        $peekers[] = 'new Peeker($("#setting_playerdirectory_scenestat"), $("#row_setting_playerdirectory_colorstat"),/^1|^2/,false)';
    }
    if ($playerdirectory_settings_peeker) {
        $peekers[] = 'new Peeker($("#setting_playerdirectory_scenestat"), $("#row_setting_playerdirectory_scenestat_legend"),/^2/,false)';
    }
	if ($playerdirectory_settings_peeker) {
        $peekers[] = 'new Peeker($("#setting_playerdirectory_poststat"), $("#row_setting_playerdirectory_colorstat"),/^1|^2/,false)';
    }
    if ($playerdirectory_settings_peeker) {
        $peekers[] = 'new Peeker($("#setting_playerdirectory_poststat"), $("#row_setting_playerdirectory_poststat_legend"),/^2/,false)';
    }

    // Listen Menü
    if ($playerdirectory_settings_peeker) {
        $peekers[] = 'new Peeker($("#setting_playerdirectory_lists_menu"), $("#row_setting_playerdirectory_lists_menu_tpl"),/^2/,false)';
    }
}

// MENU LINK
function playerdirectory_menu() {
   
    global $db, $mybb, $lang, $templates, $menu_playerdirectory;
   
    // SPRACHDATEI
    $lang->load("playerdirectory");
   
    // EINSTELLUNGEN
    $directory_activated = $mybb->settings['playerdirectory_directory'];
    $directory_activated_guest = $mybb->settings['playerdirectory_directory_guest'];
   
    if($directory_activated == 1) {
        if (($directory_activated_guest == 1 && $mybb->user['uid'] == 0) || $mybb->user['uid'] != 0) {
            eval("\$menu_playerdirectory = \"".$templates->get("playerdirectory_menu_link")."\";");
        } else {
            $menu_playerdirectory = "";
        }
    }  else {
        $menu_playerdirectory = "";
    }
}

// SEITEN
function playerdirectory_misc(){

    global $db, $cache, $mybb, $lang, $templates, $theme, $header, $headerinclude, $footer, $perpage, $page, $multipage, $query_join, $all_players, $birthday_fid, $characters_bit, $text_options, $parser, $notice_banner,$postactivity_months, $postactivity_poststat, $postactivity_perChara, $postactivity_scenestat;
	
	// SPRACHDATEI
    $lang->load('playerdirectory');

    $mybb->input['action'] = $mybb->get_input('action');

    // EINSTELLUNGEN
    $directory_activated = $mybb->settings['playerdirectory_directory'];
    $directory_activated_guest = $mybb->settings['playerdirectory_directory_guest'];
    $directory_multipage = $mybb->settings['playerdirectory_directory_multipage'];
    $directory_teamaccounts = str_replace(", ", ",", $mybb->settings['playerdirectory_directory_teamaccounts']);

    $playerstat_activated = $mybb->settings['playerdirectory_playerstat'];
    $playerstat_activated_guest = $mybb->settings['playerdirectory_playerstat_guest'];
    $characterstat_activated = $mybb->settings['playerdirectory_characterstat'];
    $characterstat_activated_guest = $mybb->settings['playerdirectory_characterstat_guest'];

    $profilfeldsystem = $mybb->settings['playerdirectory_profilfeldsystem'];
    $playername_field = $mybb->settings['playerdirectory_playername'];
    $avatar_default = $mybb->settings['playerdirectory_avatar_default'];
    $avatar_guest = $mybb->settings['playerdirectory_avatar_guest'];
    $birthday_option = $mybb->settings['playerdirectory_birthday'];
    $birthday_field = $mybb->settings['playerdirectory_birthday_field'];
    $age_field = $mybb->settings['playerdirectory_age_field'];
    $last_inplayday = $mybb->settings['playerdirectory_inplayday'];		
    $inplaytrackersystem = $mybb->settings['playerdirectory_inplaytracker'];
    $inplaystat_option = $mybb->settings['playerdirectory_inplaystat'];
    $scenestat_option = $mybb->settings['playerdirectory_scenestat'];
    $scenestat_legend_option = $mybb->settings['playerdirectory_scenestat_legend'];
    $poststat_option = $mybb->settings['playerdirectory_poststat'];
    $poststat_legend_option = $mybb->settings['playerdirectory_poststat_legend'];
    $colorstat_string = str_replace(", ", ",", $mybb->settings['playerdirectory_colorstat']);
    $color_array = explode(",", $colorstat_string);
    $inplayquotes_option = $mybb->settings['playerdirectory_inplayquotes'];

    $listsnav = $mybb->settings['playerdirectory_lists'];
    $listsmenu = $mybb->settings['playerdirectory_lists_menu'];
    $listsmenu_tpl = $mybb->settings['playerdirectory_lists_menu_tpl'];

    // PROFILFELDSYSTEM
    // klassische Profilfelder
    if ($profilfeldsystem == 0) {
        $query_join = "LEFT JOIN ".TABLE_PREFIX."userfields uf ON uf.ufid = u.uid";
    } 
    // Katjas Steckbrief-Plugin
    else if ($profilfeldsystem == 1) {
        //ANFANG DES STRINGS BAUEN
        $selectstring = "LEFT JOIN (select um.uid as auid,";
        //FELDER DIE AKTIV SIND HOLEN
        $getfields = $db->simple_select("application_ucp_fields", "*", "active = 1");

        //DIE FELDER DURCHGEHEN
        while ($searchfield = $db->fetch_array($getfields)) {
            //weiter im Querie, hier modeln wir unsere Felder ders users (apllication_ucp_fields taballe) zu einer Tabellenreihe wie die FELDER um -> name der Spalte ist fieldname, wert wie gehabt value 
            $selectstring .= " max(case when um.fieldid ='{$searchfield['id']}' then um.value end) AS '{$searchfield['fieldname']}',";
        }

        $selectstring = substr($selectstring, 0, -1);
        $selectstring .= " from `" . TABLE_PREFIX . "application_ucp_userfields` as um group by uid) as fields ON auid = u.uid";

        $query_join = $selectstring;
    } 
    // beides
    else {
        //ANFANG DES STRINGS BAUEN
        $selectstring = "LEFT JOIN (select um.uid as auid,";
        //FELDER DIE AKTIV SIND HOLEN
        $getfields = $db->simple_select("application_ucp_fields", "*", "active = 1");

        //DIE FELDER DURCHGEHEN
        while ($searchfield = $db->fetch_array($getfields)) {
            //weiter im Querie, hier modeln wir unsere Felder ders users (apllication_ucp_fields taballe) zu einer Tabellenreihe wie die FELDER um -> name der Spalte ist fieldname, wert wie gehabt value 
            //SIEHE DAZU SCREEN DEN ICH DIR EBEN GEZEIGT
            $selectstring .= " max(case when um.fieldid ='{$searchfield['id']}' then um.value end) AS '{$searchfield['fieldname']}',";
        }

        $selectstring = substr($selectstring, 0, -1);
        $selectstring .= " from `" . TABLE_PREFIX . "application_ucp_userfields` as um group by uid) as fields ON auid = u.uid";

        $query_join = "LEFT JOIN ".TABLE_PREFIX."userfields uf ON uf.ufid = u.uid ".$selectstring;
    }

    // SPIELERNAME
    // wenn Zahl => klassisches Profilfeld
    if (is_numeric($playername_field)) {
        $playername_fid = "fid".$playername_field;
        $playername_sql = "uf.".$playername_fid;
    } else {
        $playername_fid = $db->fetch_field($db->simple_select("application_ucp_fields", "id", "fieldname = '".$playername_field."'"), "id");
        $playername_sql = $playername_field;
    }

    // GEBURTSTAG
    // Profilfeld/Steckbrieffeld
    if ($birthday_option == 0) {
        $age_fid = "";
        if (is_numeric($birthday_field)) {
            $birthday_fid = "fid".$birthday_field;
        } else {
            $birthday_fid = $db->fetch_field($db->simple_select("application_ucp_fields", "id", "fieldname = '".$birthday_field."'"), "id");
        }
    } 
    // MyBB-Geburtstagsfeld
    else if ($birthday_option == 1) {
        $birthday_fid = "birthday";
        $age_fid = "";
    }
    // Nur Alter
    else if ($birthday_option == 2) {
        $birthday_fid = "";
        if (is_numeric($age_field)) {
            $age_fid = "fid".$age_field;
        } else {
            $age_fid = $db->fetch_field($db->simple_select("application_ucp_fields", "id", "fieldname = '".$age_field."'"), "id");
        }
    }

    // Letzter Inplaytag => automatische Berechnung
    if ($birthday_option != 2) {               
        // Inplayjahr splitten - v. Chr.
        $inplay_array = explode(".", $last_inplayday);
        $ingame = new DateTime($last_inplayday);
    } else {
        $ingame = "";
    }

    // Ausgeschlossene User
	if(!empty($directory_teamaccounts)) {
		$teamaccounts_sql_where = "WHERE u.uid NOT IN (".$directory_teamaccounts.")"; 
		$teamaccounts_sql_and = "AND u.uid NOT IN (".$directory_teamaccounts.")"; 
	} else {
        $teamaccounts_sql_where = "";
		$teamaccounts_sql_and = ""; 
	}

    // PARSER - HTML und CO erlauben
    require_once MYBB_ROOT."inc/class_parser.php";;
    $parser = new postParser;
    $text_options = array(
        "allow_html" => 1,
        "allow_mycode" => 1,
        "allow_smilies" => 1,
        "allow_imgcode" => 1,
        "filter_badwords" => 0,
        "nl2br" => 1,
        "allow_videocode" => 0
    );

    // POST STATISTIK
	// aktuelles Jahr 
    $current_year = idate("Y");
	// aktueller Monat - Zahl
    $current_month = idate("m");
    // Monate - Zahl => Name
    $months_array = array(
        "01" => $lang->playerdirectory_month_jan,
        "02" => $lang->playerdirectory_month_feb,
        "03" => $lang->playerdirectory_month_mar,
        "04" => $lang->playerdirectory_month_apr,
        "05" => $lang->playerdirectory_month_may,
        "06" => $lang->playerdirectory_month_jun,
        "07" => $lang->playerdirectory_month_jul,
        "08" => $lang->playerdirectory_month_aug,
        "09" => $lang->playerdirectory_month_sep,
        "10" => $lang->playerdirectory_month_oct,
        "11" => $lang->playerdirectory_month_nov,
        "12" => $lang->playerdirectory_month_dec
    );
 
    // die letzten 12 Monate
    $last12 = [];
    // 12 Mal ausführen -> 1 Jahr 
    for ($i = 0; $i < 12; $i++) {  
        if ($current_month < 10) {
            $current_month = "0".$current_month;
        }

        $last12[$current_month] = $current_year;

        // Voriger Monat  
        --$current_month;  
        // Jahresgrenze  
        if ($current_month == 0) {  
            $current_year--;  
            $current_month = 12;  
        }  
    }
    
    // array einmal umdrehen, wir wollen den ältesten Monat links stehen haben, den neusten ganz rechts
    $last12_sort = [];
    foreach ($last12 as $month => $year) {
        $last12_sort[$year."-".$month] = $month;
    }
    ksort($last12_sort);

    // SPIELERVERZEICHNIS
    if($mybb->input['action'] == "playerdirectory"){

		// Listenmenü
		if($listsmenu != 0){
            // Jules Plugin
            if ($listsmenu == 1) {
                $query_lists = $db->simple_select("lists", "*");
                while($list = $db->fetch_array($query_lists)) {
                    eval("\$menu_bit .= \"".$templates->get("lists_menu_bit")."\";");
                }
                eval("\$lists_menu = \"".$templates->get("lists_menu")."\";");
            } else {
                eval("\$lists_menu = \"".$templates->get($listsmenu_tpl)."\";");
            }
        } else {
            $lists_menu = "";
        }

        // NAVIGATION
		if(!empty($listsnav)){
            add_breadcrumb($lang->playerdirectory_lists, $listsnav);
            add_breadcrumb($lang->playerdirectory_directory, "misc.php?action=playerdirectory");
		} else{
            add_breadcrumb($lang->playerdirectory_directory, "misc.php?action=playerdirectory");
		}

        // Seite ist deaktiviert 
        if ($directory_activated == 0) {
			error($lang->playerdirectory_directory_deactivated);
			return;
		}

		// Gäste ausschließen
		if ($directory_activated_guest == 0 && $mybb->user['uid'] == 0) {
			error($lang->playerdirectory_directory_deactivated_guest);
			return;
		}

        $allPlayers = $db->num_rows($db->query("SELECT uid FROM ".TABLE_PREFIX."users u
        WHERE u.as_uid = '0'
        ".$teamaccounts_sql_and."
        "));
        $count_allPlayers = $lang->sprintf($lang->playerdirectory_directory_allPlayers, $allPlayers);

        // Multipage
        if ($directory_multipage != 0) {
    
            $perpage = $directory_multipage;
            $input_page = $mybb->get_input('page', MyBB::INPUT_INT);;
            if($input_page) {
                $start = ($input_page-1) *$perpage;
            }
            else {
                $start = 0;
                $input_page = 1;
            }
            $end = $start + $perpage;
            $lower = $start+1;
            $upper = $end;
            if($upper > $allPlayers) {
                $upper = $allPlayers;
            }
    
            $page_url = htmlspecialchars_uni("misc.php?action=playerdirectory");
    
            $multipage = multipage($allPlayers, $perpage, $input_page, $page_url);

            $multipage_sql = "LIMIT ".$start.", ".$perpage;
        } else {
            $multipage_sql = "";
        }

		// ALLE ACCOUNTS AUSLESEN
		$allacc_query = $db->query("SELECT * FROM ".TABLE_PREFIX."users u
		".$query_join."
		WHERE u.as_uid = '0'
        ".$teamaccounts_sql_and."
		ORDER BY ".$playername_sql." ASC, u.uid ASC
		".$multipage_sql."
		");

		while($allacc = $db->fetch_array($allacc_query)) {

			// LEER LAUFEN LASSEN
			$playerID = "";
            $playername = "";
            $regdate = "";
            $lastactivity = "";
	
			// MIT INFORMATIONEN FÜLLEN
			$playerID = $allacc['uid'];

			// SPIELERNAME	
            // klassisches Profilfeld
            if (is_numeric($playername_field)) {
                if(empty($allacc[$playername_fid])) {
                    $playername = $lang->playerdirectory_playername_none;
                } else {
                    $playername = $allacc[$playername_fid];
                }
            } 
            // Steckbrief-Plugin
            else {
                if(empty($allacc[$playername_field])) {
                    $playername = $lang->playerdirectory_playername_none;
                } else {
                    $playername = $allacc[$playername_field];
                }
            }
            
            // CHARAKTERE VON DEM USER
			$character_query = $db->query("SELECT * FROM ".TABLE_PREFIX."users u
            ".$query_join."
			WHERE (u.uid = '".$playerID."' OR u.as_uid = '".$playerID."')
            ".$teamaccounts_sql_and."
			ORDER BY u.username ASC
			");

			$count_charas = 0;
            // Versteckt Array
            // uid => invisible
            $user_invisible = [];
            $userids = "";

            $characters = "";
			while($character = $db->fetch_array($character_query)) {
					
				// COUNTER	
				$count_charas ++;	

				// SIGNULAR & PLURAL
				if ($count_charas > 1) {
                    $charas_count = $lang->sprintf($lang->playerdirectory_directory_user_charas_plural, $count_charas);
				} else {
                    $charas_count = $lang->sprintf($lang->playerdirectory_directory_user_charas_singular, $count_charas);
				}
				
				// LEER LAUFEN LASSEN
				$characterID = "";
                $charactername_formated = "";
				$charactername = "";
                $charactername_link = "";
                $first_name = "";
                $last_name = "";
				$avatar_url = "";
                $regdate = "";
                $lastactivity = "";
                $age = "";
                $character_button = "";

				// MIT INFOS BEFÜLLEN
				$characterID = $character['uid'];
                
                if ($characterstat_activated == 1) {
                    if ($mybb->user['uid'] == 0 AND $characterstat_activated_guest != 1) {
                        $character_button = "";
                    } else {
                        $character_button = $lang->sprintf($lang->playerdirectory_directory_character_statbutton, $characterID);
                    }
                    if ($mybb->user['uid'] != 0) {
                        $character_button = $lang->sprintf($lang->playerdirectory_directory_character_statbutton, $characterID);
                    }
                } else {
                    $character_button = "";
                }
			
				// CHARACTER NAME
                // ohne alles
                $charactername = $character['username'];
                // mit Gruppenfarbe
                $charactername_formated = build_profile_link(format_name($charactername, $character['usergroup'], $character['displaygroup']), $characterID);	
                // Nur Link
                $charactername_link = build_profile_link($charactername, $characterID);
                // Name gesplittet
                $fullname = explode(" ", $charactername);
                $first_name = array_shift($fullname);
                $last_name = implode(" ", $fullname); 

				// AVATAR KRAM
				if ($avatar_guest == 1) {
					if ($mybb->user['uid'] == '0' || $character['avatar'] == '') {
						$avatar_url = $theme['imgdir']."/".$avatar_default;
					} else {
						$avatar_url = $character['avatar'];
					}
				} else {
					if ($character['avatar'] == '') {
						$avatar_url = $theme['imgdir']."/".$avatar_default;
					} else {
						$avatar_url = $character['avatar'];
					}
				}

                // USERTITEL       
                if ($character['usertitle'] == '') {
                    $usertitle = $db->fetch_field($db->simple_select("usergroups", "title", "gid = '". $character['usergroup']."'"), "title");
                } else {
                    $usertitle  = $character['usertitle'];
                }

                // EXTRA VARIABELN
                // Registrierungs Datum
                $regdate = my_date('relative', $character['regdate']);
                // Zuletzt online
                // Versteckt
                if ($character['invisible'] == 1 && $mybb->usergroup['canviewwolinvis'] != 1) {
                    $lastactivity = $lang->playerdirectory_statistic_lastactivity_hidden;
                } 
                // Einsehbar
                else {
                    if ($character['lastactive'] == 0) {
                        $lastactivity = $lang->playerdirectory_statistic_lastactivity_never;
                    } else {
                        $lastactivity = my_date('relative', $character['lastactive']);
                    }
                }

                // INPLAY
                // Inplaytracker 2.0 von sparks fly
                if ($inplaytrackersystem == 0) {
                    $sceneTIDs = "";
                    // Szenen des Users auslesen - TID
                    $query_allcharscenes = $db->query("SELECT tid FROM ".TABLE_PREFIX."threads
                    WHERE (concat(',',partners,',') LIKE '%,".$characterID.",%')
                    ORDER by tid ASC                
                    ");     

                    while ($allcharscenes = $db->fetch_array($query_allcharscenes)){
                        // Mit Infos füllen
                        $sceneTIDs .= $allcharscenes['tid'].",";
                    } 
                } 
                // Inplaytracker 3.0 von sparks fly
                else if ($inplaytrackersystem == 1) {
                    $sceneTIDs = "";
                    // Szenen des Users auslesen - TID
                    $query_allcharscenes = $db->query("SELECT ips.tid FROM ".TABLE_PREFIX."ipt_scenes ips
                    LEFT JOIN ".TABLE_PREFIX."ipt_scenes_partners ipsp
                    ON ips.tid = ipsp.tid
                    WHERE ipsp.uid = '".$characterID."'
                    ORDER by ips.tid ASC                
                    ");     

                    while ($allcharscenes = $db->fetch_array($query_allcharscenes)){
                        // Mit Infos füllen
                        $sceneTIDs .= $allcharscenes['tid'].",";
                    } 
                }
                // Szenentracker von risuena
                else if ($inplaytrackersystem == 2) {
                    $sceneTIDs = "";
                    // Szenen des Users auslesen - TID
                    $query_allcharscenes = $db->query("SELECT tid FROM ".TABLE_PREFIX."scenetracker
                    WHERE uid = '".$characterID."'
                    ORDER by tid ASC                
                    ");    

                    while ($allcharscenes = $db->fetch_array($query_allcharscenes)){
                        // Mit Infos füllen
                        $sceneTIDs .= $allcharscenes['tid'].",";
                    } 
                } 
                // Inplaytracker von little.evil.genius
                else if ($inplaytrackersystem == 3) {
                    $sceneTIDs = "";
                    // Szenen des Users auslesen - TID
                    $query_allcharscenes = $db->query("SELECT tid FROM ".TABLE_PREFIX."inplayscenes
                    WHERE (concat(',',partners,',') LIKE '%,".$characterID.",%')
                    ORDER by tid ASC                
                    ");      

                    while ($allcharscenes = $db->fetch_array($query_allcharscenes)){
                        // Mit Infos füllen
                        $sceneTIDs .= $allcharscenes['tid'].",";
                    }
                }
                // Inplaytracker von Ales
                else if ($inplaytrackersystem == 4) {
                    $sceneTIDs = "";
                    $scene_username = "";
                    $scene_username = get_user($characterID)['username'];
                    // Szenen des Users auslesen - TID
                    $query_allcharscenes = $db->query("SELECT tid FROM ".TABLE_PREFIX."threads
                    WHERE (concat(', ',spieler,',') LIKE '%, ".$scene_username.",%')
                    ORDER by tid ASC                
                    ");     
    
                    while ($allcharscenes = $db->fetch_array($query_allcharscenes)){
                        // Mit Infos füllen
                        $sceneTIDs .= $allcharscenes['tid'].",";
                    } 
                }

                if(!empty($sceneTIDs)) {
                    // letztes Komma abschneiden 
                    $sceneTIDs_string = substr($sceneTIDs, 0, -1);
                    // TIDs splitten
                    $sceneTIDs_array = explode(",", $sceneTIDs_string);
                } else {
                    $sceneTIDs_string = 0;
                    $sceneTIDs_array = 0;
                }
    
                // Anzahl Inplay-Posts
                $count_allinplayposts = $db->num_rows($db->query("SELECT pid FROM ".TABLE_PREFIX."posts p
                WHERE p.uid = '".$characterID."'
                AND p.tid IN (".$sceneTIDs_string.")
                AND p.visible = '1'
                "));
        
                $allinplayposts_formatted = "";
                if($count_allinplayposts > 0) {
                    $allinplayposts_formatted = number_format($count_allinplayposts, '0', ',', '.');
                } else {
                    $allinplayposts_formatted = 0;
                }
        
                // Anzahl Inplay-Szenen
                if ($sceneTIDs_array != 0) {
                    $allinplayscenes = count($sceneTIDs_array); 
                } else {
                    $allinplayscenes = 0;
                }
                if($allinplayscenes > 0) {
                    $allinplayscenes_formatted = number_format($allinplayscenes, '0', ',', '.');
                } else {
                    $allinplayscenes_formatted = 0;
                }

                // Alter
                // automatisches berechnen
                if ($birthday_option != 2) {
                    // Profilfeld/Steckbrieffeld
                    if ($birthday_option == 0) {
                        // klassisches Profilfeld
                        if (is_numeric($birthday_field)) {
                            if(!empty($character[$birthday_fid])) {

                                // Geburstag aufsplitten
                                $birthday_array = explode(".", $character[$birthday_fid]);

                                // Vor Christus "v. Chr."
                                if (str_contains($character[$birthday_fid], 'v. Chr.')) {

                                    // Geburtstjahr - v entfernen
                                    $birthyear = str_replace(" v", "", $birthday_array[2]);

                                    // Alter = aktuelles Jahr + v. Chr. Jahr
                                    $age = $inplay_array[2] + $birthyear;

                                }
                                // nach Christus
                                else {
                                    // Jahr überprüfen, ob 4 Ziffern
                                    if (strlen($birthday_array[2]) < 4) {
                                        
                                        $null = "";
                                        for ($i = strlen($birthday_array[2]); $i <= 3; $i++) {
                                            $null .= "0";
                                        }
        
                                        $birthyear = $null.$birthday_array[2];
    
                                    } else {
                                        $birthyear = $birthday_array[2];
                                    }
    
                                    $birthday = new DateTime($birthday_array[0].".".$birthday_array[1].".".$birthyear);
                                    $interval = $ingame->diff($birthday);
                                    $age = $interval->format("%Y");
                                }

                            } else {
                                $age = "00";
                            }
                        } 
                        // Steckbrief Plugin
                        else {
                            if(!empty($character[$birthday_field])) {

                                $field_type = $db->fetch_field($db->simple_select("application_ucp_fields", "fieldtyp" ,"fieldname = '".$birthday_field."'"), "fieldtyp");

                                // Datum -
                                if ($field_type == "date") {
                                    // Geburstag aufsplitten
                                    $birthday_array = explode("-", $character[$birthday_field]);

                                    $birth_day = $birthday_array[2];
                                    $birth_month = $birthday_array[1];
                                    $birth_year = $birthday_array[0];
                                } 
                                // Datum und Zeit T & -
                                else if ($field_type == "datetime-local") {
                                    // Geburstag aufsplitten
                                    $birthday_time = explode("T", $character[$birthday_field]);
                                    $birthday_array = explode("-", $birthday_time[0]);

                                    $birth_day = $birthday_array[2];
                                    $birth_month = $birthday_array[1];
                                    $birth_year = $birthday_array[0];
                                } 
                                // Text .
                                else {
                                    // Geburstag aufsplitten
                                    $birthday_array = explode(".", $character[$birthday_field]);

                                    $birth_day = $birthday_array[0];
                                    $birth_month = $birthday_array[1];
                                    $birth_year = $birthday_array[2];
                                }


                                // Vor Christus "v. Chr."
                                if (str_contains($string, 'v. Chr.')) {
                                
                                    // Geburtstjahr - v entfernen
                                    $birthyear = str_replace(" v", "", $birth_year);
                                
                                    // Alter = aktuelles Jahr + v. Chr. Jahr
                                    $age = $inplay_array[2] + $birthyear;
                                
                                }
                                // nach Christus
                                else {
                                    // Jahr überprüfen, ob 4 Ziffern
                                    if (strlen($birth_year) < 4) {
                                        
                                        $null = "";
                                        for ($i = strlen($birth_year); $i <= 3; $i++) {
                                            $null .= "0";
                                        }
        
                                        $birthyear = $null.$birth_year;
    
                                    } else {
                                        $birthyear = $birth_year;
                                    }
    
                                    $birthday = new DateTime($birth_day.".".$birth_month.".".$birthyear);
                                    $interval = $ingame->diff($birthday);
                                    $age = $interval->format("%Y");
                                }

                            } else {
                                $age = "00";
                            }
                        }
                    } 
                    // MyBB Geburtstagsfeld
                    else {
                        if(!empty($character['birthday'])) {

                            // Geburstag aufsplitten
                            $birthday_array = explode("-", $character['birthday']);

                            $birth_day = $birthday_array[0];
                            $birth_month = $birthday_array[1];
                            $birth_year = $birthday_array[2];

                            // Jahr überprüfen, ob 4 Ziffern
                            if (strlen($birth_year) < 4) {
                                
                                $null = "";
                                for ($i = strlen($birth_year); $i <= 3; $i++) {
                                    $null .= "0";
                                }

                                $birthyear = $null.$birth_year;

                            } else {
                                $birthyear = $birth_year;
                            }


                            $birthday = new DateTime($birth_day.".".$birth_month.".".$birthyear);
                            $interval = $ingame->diff($birthday);
                            $age = $interval->format("%Y");
                        } else {
                            $age = "00";
                        }
                    }
                } 
                // Feld mit Alter
                else {
                    // klassisches Profilfeld
                    if (is_numeric($age_field)) {
                        if(!empty($character[$age_fid])) {
                            // Feld = X Jahre => Jahre rauswerfen
                            $only_age = preg_replace('/[^0-9]/', '', $character[$age_fid]);
                            $age = $only_age;
                        } else {
                            $age = "00";
                        }
                    } 
                    // Steckbrief Plugin
                    else {
                        if(!empty($character[$age_field])) {
                            // Feld = X Jahre => Jahre rauswerfen
                            $only_age = preg_replace('/[^0-9]/', '', $character[$age_field]);
                            $age = $only_age;
                        } else {
                            $age = "00";
                        }
                    }
                }

                $character_inplaystat = $lang->sprintf($lang->playerdirectory_directory_character_inplaystat, $allinplayposts_formatted, $allinplayscenes_formatted);

                // Versteckt Array
                $user_invisible[$characterID] = $character['invisible'];
                // UID String
                $userids .= $characterID.",";
			
				eval("\$characters .= \"".$templates->get("playerdirectory_directory_characters")."\";");
			}

            // Letzte Aktivität
            // Zählen wie oft invisible (1)
            $count_invisible = count(array_keys($user_invisible, 1));
            // Es gibt Accounts mit invisible (1) und es sind NICHT alle Charaktere
            if ($count_invisible > 0 && $count_invisible != $count_charas && $mybb->usergroup['canviewwolinvis'] != 1) {

                foreach ($user_invisible as $key => $value) {
                    // Alle Werte mit invisible (1) löschen
                    if ($value == 1) {
                        unset($user_invisible[$key]);
                    }
                }

                $visible_uids = "";
                foreach ($user_invisible as $key => $value) {
                    $visible_uids .= $key.",";
                }
                // letztes Komma vom UID String entfernen
                $visible_uids = substr($visible_uids, 0, -1);

                // Zuletzt online
                $lastactive = $db->fetch_field($db->simple_select("users", "lastactive", "uid IN (".$visible_uids.") OR as_uid IN (".$visible_uids.")", [ "order_dir" => "DESC", "order_by" => "lastactive", "limit" => "1" ]), "lastactive");
                if ($lastactive == 0) {
                    $lastactivity = $lang->playerdirectory_statistic_lastactivity_never;
                } else {
                    $lastactivity = my_date('relative', $lastactive);
                }
            } 
            // Es gibt Accounts mit invisible (1) und es sind ALLE Charaktere => Versteckt
            else if ($count_invisible > 0 && $count_invisible == $count_charas && $mybb->usergroup['canviewwolinvis'] != 1) {
                // Zuletzt online
                $lastactivity = $lang->playerdirectory_statistic_lastactivity_hidden;
            } else {
                // Zuletzt online
                $lastactive = $db->fetch_field($db->simple_select("users", "lastactive", "uid = ".$playerID." OR as_uid = ".$playerID."", [ "order_dir" => "DESC", "order_by" => "lastactive", "limit" => "1" ]), "lastactive");
            
                if ($lastactive == 0) {
                    $lastactivity = $lang->playerdirectory_statistic_lastactivity_never;
                } else {
                    $lastactivity = my_date('relative', $lastactive);
                }
            
            }

            // Registrierungs Datum
            $regdate = my_date('relative', $db->fetch_field($db->simple_select("users", "regdate", "uid = ".$playerID." OR as_uid = ".$playerID."", [ "order_dir" => "ASC", "order_by" => "regdate", "limit" => "1" ]), "regdate"));
        

            // EXTRA VARIABELN
            // letztes Komma vom UID String entfernen	
            $userids_string = substr($userids, 0, -1);

            // UIDs splitten	
            $userids_array = explode(",", $userids_string);

            // ALLE TIDs ALLER SZENEN 
            // Inplaytracker 2.0 von sparks fly
            if ($inplaytrackersystem == 0) {
                $sceneTIDs = "";
                foreach ($userids_array as $userID) {
                    // Szenen des Users auslesen - TID
                    $query_allcharscenes = $db->query("SELECT tid FROM ".TABLE_PREFIX."threads
                    WHERE (concat(',',partners,',') LIKE '%,".$userID.",%')
                    ORDER by tid ASC    
                    ");     

                    while ($allcharscenes = $db->fetch_array($query_allcharscenes)){
                        // Mit Infos füllen
                        $sceneTIDs .= $allcharscenes['tid'].",";
                    }   
                }
            } 
            // Inplaytracker 3.0 von sparks fly
            else if ($inplaytrackersystem == 1) {
                $sceneTIDs = "";
                foreach ($userids_array as $userID) {
                    // Szenen des Users auslesen - TID
                    $query_allcharscenes = $db->query("SELECT ips.tid FROM ".TABLE_PREFIX."ipt_scenes ips
                    LEFT JOIN ".TABLE_PREFIX."ipt_scenes_partners ipsp
                    ON ips.tid = ipsp.tid
                    WHERE ipsp.uid = '".$userID."'
                    ORDER by ips.tid ASC    
                    ");     

                    while ($allcharscenes = $db->fetch_array($query_allcharscenes)){
                        // Mit Infos füllen
                        $sceneTIDs .= $allcharscenes['tid'].",";
                    } 
                }
            }
            // Szenentracker von risuena
            else if ($inplaytrackersystem == 2) {
                $sceneTIDs = "";
                foreach ($userids_array as $userID) {
                    // Szenen des Users auslesen - TID
                    $query_allcharscenes = $db->query("SELECT tid FROM ".TABLE_PREFIX."scenetracker
                    WHERE uid = '".$userID."'
                    ORDER by tid ASC    
                    ");    

                    while ($allcharscenes = $db->fetch_array($query_allcharscenes)){
                        // Mit Infos füllen
                        $sceneTIDs .= $allcharscenes['tid'].",";
                    }   
                }
            } 
            // Inplaytracker von little.evil.genius
            else if ($inplaytrackersystem == 3) {
                $sceneTIDs = "";
                foreach ($userids_array as $userID) {                  
                    // Szenen des Users auslesen - TID
                    $query_allcharscenes = $db->query("SELECT tid FROM ".TABLE_PREFIX."inplayscenes
                    WHERE (concat(',',partners,',') LIKE '%,".$userID.",%')
                    ORDER by tid ASC    
                    ");      

                    while ($allcharscenes = $db->fetch_array($query_allcharscenes)){
                        // Mit Infos füllen
                        $sceneTIDs .= $allcharscenes['tid'].",";
                    }    
                }
            } 
            // Inplaytracker von Ales
            else if ($inplaytrackersystem == 4) {
                $sceneTIDs = "";
                $scene_username = "";
                foreach ($userids_array as $userID) {
                    $scene_username = get_user($userID)['username'];
                    // Szenen des Users auslesen - TID
                    $query_allcharscenes = $db->query("SELECT tid FROM ".TABLE_PREFIX."threads
                    WHERE (concat(', ',spieler,',') LIKE '%, ".$scene_username.",%')
                    ORDER by tid ASC    
                    ");     

                    while ($allcharscenes = $db->fetch_array($query_allcharscenes)){
                        // Mit Infos füllen
                        $sceneTIDs .= $allcharscenes['tid'].",";
                    }   
                }
            }

            if(!empty($sceneTIDs)) {
                // letztes Komma abschneiden 
                $sceneTIDs_string = substr($sceneTIDs, 0, -1);
                // TIDs splitten
                $sceneTIDs_array = explode(",", $sceneTIDs_string);
            } else {
                $sceneTIDs_string = 0;
                $sceneTIDs_array = 0;
            }

            // Anzahl Inplay-Posts
            $count_allinplayposts = $db->num_rows($db->query("SELECT pid FROM ".TABLE_PREFIX."posts p
            WHERE p.uid IN (".$userids_string.")
            AND p.tid IN (".$sceneTIDs_string.")
            AND p.visible = '1'
            "));
    
            if($count_allinplayposts > 0) {
                $allinplayposts_formatted = number_format($count_allinplayposts, '0', ',', '.');
            } else {
                $allinplayposts_formatted = 0;
            }
    
            // Anzahl Inplay-Szenen
            if ($sceneTIDs_array != 0) {
                $allinplayscenes = count($sceneTIDs_array); 
            } else {
                $allinplayscenes = 0;
            }
            if($allinplayscenes > 0) {
                $allinplayscenes_formatted = number_format($allinplayscenes, '0', ',', '.');
            } else {
                $allinplayscenes_formatted = 0;
            }
                
            $player_inplaystat = $lang->sprintf($lang->playerdirectory_directory_user_inplaystat, $allinplayposts_formatted, $allinplayscenes_formatted);

            if ($playerstat_activated == 1) {
                if ($mybb->user['uid'] == 0 AND $$playerstat_activated_guest != 1) {
                    $player_button = "";
                } else {
                    $player_button = $lang->sprintf($lang->playerdirectory_directory_user_statbutton, $characterID);
                }
                if ($mybb->user['uid'] != 0) {
                    $player_button = $lang->sprintf($lang->playerdirectory_directory_user_statbutton, $characterID);
                }
            } else {
                $player_button = "";
            }

			eval("\$all_players .= \"" . $templates->get ("playerdirectory_directory_user") . "\";");
        }

        $allCharacters = $db->num_rows($db->query("SELECT uid FROM ".TABLE_PREFIX."users u 
        ".$teamaccounts_sql_where."
        "));
        $count_allCharacters = $lang->sprintf($lang->playerdirectory_directory_allCharacters, $allCharacters);

		// DURCHSCHNITTLICHE CHARAKTERANZAHL
		$averagecharacters = round($allCharacters/$allPlayers, 2); 
        $count_averagecharacters = $lang->sprintf($lang->playerdirectory_directory_averagecharacters, $averagecharacters);

        // TEMPLATE FÜR DIE SEITE
		eval("\$page = \"".$templates->get("playerdirectory_directory")."\";");
		output_page($page);
		die();

    }

    // SPIELERSTATISTIK
    if($mybb->input['action'] == "playerstatistic"){

		// DIE SPIELER UID
		$playerID = $mybb->input['uid'];

		// SPIELERNAME
        // wenn Zahl => klassisches Profilfeld
        if (is_numeric($playername_field)) {
            $playername = $db->fetch_field($db->simple_select("userfields",$playername_fid,"ufid = ".$playerID.""), $playername_fid);
        } else {
            $playername = $db->fetch_field($db->simple_select("application_ucp_userfields", "value", "fieldid = '".$playername_fid."' AND uid = ".$playerID.""), "value");
        }
        if(empty($playername)) {
            $playername = $lang->playerdirectory_playername_none;
        } else {
            $playername = $playername;
        }

		// Listenmenü
		if($listsmenu != 2){
            // Jules Plugin
            if ($listsmenu == 1) {
                $query_lists = $db->simple_select("lists", "*");
                while($list = $db->fetch_array($query_lists)) {
                    eval("\$menu_bit .= \"".$templates->get("lists_menu_bit")."\";");
                }
                eval("\$lists_menu = \"".$templates->get("lists_menu")."\";");
            } else {
                eval("\$lists_menu = \"".$templates->get($listsmenu_tpl)."\";");
            }
        } else {
            $lists_menu = "";
        }

        // NAVIGATION
		if(!empty($listsnav)){
            add_breadcrumb($lang->playerdirectory_lists, $listsnav);
            if ($directory_activated == 1) {
                add_breadcrumb($lang->playerdirectory_directory, "misc.php?action=playerdirectory");
            }
            add_breadcrumb($lang->sprintf($lang->playerdirectory_playerstat, $playername), "misc.php?action=playerstatistic&uid=".$playerID);
		} else{
            if ($directory_activated == 1) {
                add_breadcrumb($lang->playerdirectory_directory, "misc.php?action=playerdirectory");
            }
            add_breadcrumb($lang->sprintf($lang->playerdirectory_playerstat, $playername), "misc.php?action=playerstatistic&uid=".$playerID);
		}
        
        $lang->playerdirectory_playerstat = $lang->sprintf($lang->playerdirectory_playerstat, $playername);

        // Seite ist deaktiviert 
        if ($playerstat_activated == 0) {
			error($lang->playerdirectory_playerstat_deactivated);
			return;
		}

		// Gäste ausschließen
		if ($playerstat_activated_guest == 0 && $mybb->user['uid'] == 0) {
			error($lang->playerdirectory_playerstat_deactivated_guest);
			return;
		}

        // keine ID oder User ist nicht vorhanden
		if (empty($mybb->input['uid']) || empty($db->fetch_field($db->simple_select("users", "uid", "uid = '".$playerID."'"), "uid"))) {
			error($lang->playerdirectory_error_uid);
			return;
		}

        // ACCOUNTSWITCHER - HAUPT ID
		$mainID = $db->fetch_field($db->simple_select("users", "as_uid", "uid = '".$playerID."'"), "as_uid");
		if(empty($mainID)) {
			$mainID = $playerID;
		}

        // Spieler hat es eingestellt
        $playerstat_setting = $db->fetch_field($db->simple_select("users", "playerdirectory_playerstat", "uid = '".$mainID."'"), "playerdirectory_playerstat");
		$playerstat_guest_setting = $db->fetch_field($db->simple_select("users", "playerdirectory_playerstat_guest", "uid = '".$mainID."'"), "playerdirectory_playerstat_guest");
		// ... andere Spieler ausschließen
        if (($playerstat_setting == 1 AND ($mybb->user['uid'] != $playerID AND $mybb->user['uid'] != $mainID AND $mybb->user['as_uid'] != $mainID)) AND ($characterstat_setting == 1 AND $mybb->usergroup['canmodcp'] != 1)  && $mybb->user['uid'] != 0) {
            error($lang->sprintf($lang->playerdirectory_playerstat_user_option, $playername));
			return;
		}
        // ... Gäste ausschließen
        if ($playerstat_guest_setting == 1 && $mybb->user['uid'] == 0) {
			error($lang->sprintf($lang->playerdirectory_playerstat_user_option_guest, $playername));
			return;
		}

        // HINWEIS BANNER
        $conf_user = "";
        $conf_guest = "";
        if ($mybb->user['uid'] == $playerID || $mybb->user['uid'] == $mainID || $mybb->user['as_uid'] == $mainID) {

           if ($playerstat_setting == 1) {
            $conf_user = $lang->playerdirectory_notice_banner_conf_hidden;
           } else {
            $conf_user = $lang->playerdirectory_notice_banner_conf;
           }

           if ($playerstat_guest_setting == 1) {
            $conf_guest = $lang->playerdirectory_notice_banner_conf_hidden;
           } else {
            $conf_guest = $lang->playerdirectory_notice_banner_conf;
           }

           $banner_text = $lang->sprintf($lang->playerdirectory_notice_banner, $lang->playerdirectory_notice_banner_playerstat, $conf_user, $conf_guest);

            eval("\$notice_banner = \"".$templates->get("playerdirectory_notice_banner")."\";");
        } else {
            $notice_banner = "";
        }

		/* CHARAKTERE AUSLESEN */
		$allcharacters_query = $db->query("SELECT * FROM ".TABLE_PREFIX."users u
		".$query_join."
		WHERE (u.uid = '".$mainID."' OR u.as_uid = '".$mainID."')
		ORDER BY u.username ASC
		");

		// COUNTER Charaktere
		$count_charas = 0;
		// Alle UIDs von dem User speichern
		$userids = "";

        // Versteckt Array
        // uid => invisible
        $user_invisible = [];
        // Szenen & Posts Array
        // username => Anzahl
        $user_allscenes = [];
        $user_allposts = [];

		while($char = $db->fetch_array($allcharacters_query)) {

			// COUNTER Charaktere
			$count_charas ++;

			// LEER LAUFEN LASSEN
			$charaID = "";
            $charactername = "";
			$charactername_formated = "";
            $charactername_link = "";
            $first_name = "";
            $last_name = "";
            $avatar_url = "";
            $usertitle = "";
            $age = "";

			// MIT INFOS FÜLLEN
			$charaID = $char['uid'];

			// ALLE UIDS SPEICHERN ALS STRING
			$userids .= $char['uid'].",";

			// CHARACTER NAME
            $charactername = $char['username'];
            // mit Gruppenfarbe
			$charactername_formated = build_profile_link(format_name($charactername, $char['usergroup'], $char['displaygroup']), $charaID);	
            // Nur Link
            $charactername_link = build_profile_link($charactername, $charaID);
            // Name gesplittet
            $fullname = explode(" ", $charactername);
            $first_name = array_shift($fullname);
            $last_name = implode(" ", $fullname); 

			// AVATAR KRAM
            if ($avatar_guest == 1) {
                if ($mybb->user['uid'] == '0' || $char['avatar'] == '') {
                    $avatar_url = $theme['imgdir']."/".$avatar_default;
                } else {
                    $avatar_url = $char['avatar'];
                }
            } else {
                if ($char['avatar'] == '') {
                    $avatar_url = $theme['imgdir']."/".$avatar_default;
                } else {
                    $avatar_url = $char['avatar'];
                }
            }

			// USERTITEL       
			if ($char['usertitle'] == '') {
				$usertitle = $db->fetch_field($db->simple_select("usergroups", "title", "gid = '".$char['usergroup']."'"), "title");
			} else {
				$usertitle  = $char['usertitle'];
			}

            // ALTER
            // automatisches berechnen
            if ($birthday_option != 2) {
                // Profilfeld/Steckbrieffeld
                if ($birthday_option == 0) {
                    // klassisches Profilfeld
                    if (is_numeric($birthday_field)) {
                        if(!empty($char[$birthday_fid])) {

                            // Geburstag aufsplitten
                            $birthday_array = explode(".", $char[$birthday_fid]);

                            // Vor Christus "v. Chr."
                            if (str_contains($char[$birthday_fid], 'v. Chr.')) {
                            
                                // Geburtstjahr - v entfernen
                                $birthyear = str_replace(" v", "", $birthday_array[2]);
                            
                                // Alter = aktuelles Jahr + v. Chr. Jahr
                                $age = $inplay_array[2] + $birthyear;
                            }
                            // nach Christus
                            else {
                                // Jahr überprüfen, ob 4 Ziffern
                                if (strlen($birthday_array[2]) < 4) {
                            
                                    $null = "";
                                    for ($i = strlen($birthday_array[2]); $i <= 3; $i++) {
                                        $null .= "0";
                                    }
                            
                                    $birthyear = $null.$birthday_array[2];
                            
                                } else {
                                    $birthyear = $birthday_array[2];
                                }
                            
                                $birthday = new DateTime($birthday_array[0].".".$birthday_array[1].".".$birthyear);
                                $interval = $ingame->diff($birthday);
                                $age = $interval->format("%Y");
                            }
                        } else {
                            $age = "00";
                        }
                    } 
                    // Steckbrief Plugin
                    else {
                        if(!empty($char[$birthday_field])) {

                            $field_type = $db->fetch_field($db->simple_select("application_ucp_fields", "fieldtyp" ,"fieldname = '".$birthday_field."'"), "fieldtyp");

                            // Datum -
                            if ($field_type == "date") {
                                // Geburstag aufsplitten
                                $birthday_array = explode("-", $char[$birthday_field]);

                                $birth_day = $birthday_array[2];
                                $birth_month = $birthday_array[1];
                                $birth_year = $birthday_array[0];
                            } 
                            // Datum und Zeit T & -
                            else if ($field_type == "datetime-local") {
                                // Geburstag aufsplitten
                                $birthday_time = explode("T", $char[$birthday_field]);
                                $birthday_array = explode("-", $birthday_time[0]);

                                $birth_day = $birthday_array[2];
                                $birth_month = $birthday_array[1];
                                $birth_year = $birthday_array[0];
                            } 
                            // Text .
                            else {
                                // Geburstag aufsplitten
                                $birthday_array = explode(".", $char[$birthday_field]);

                                $birth_day = $birthday_array[0];
                                $birth_month = $birthday_array[1];
                                $birth_year = $birthday_array[2];
                            }

                            // Vor Christus "v. Chr."
                            if (str_contains($char[$birthday_field], 'v. Chr.')) {
                            
                                // Geburtstjahr - v entfernen
                                $birthyear = str_replace(" v", "", $birth_year);
                            
                                // Alter = aktuelles Jahr + v. Chr. Jahr
                                $age = $inplay_array[2] + $birthyear;
                            
                            }
                            // nach Christus
                            else {
                                // Jahr überprüfen, ob 4 Ziffern
                                if (strlen($birth_year) < 4) {
                                    
                                    $null = "";
                                    for ($i = strlen($birth_year); $i <= 3; $i++) {
                                        $null .= "0";
                                    }
    
                                    $birthyear = $null.$birth_year;
    
                                } else {
                                    $birthyear = $birth_year;
                                }
    
                                $birthday = new DateTime($birth_day.".".$birth_month.".".$birthyear);
                                $interval = $ingame->diff($birthday);
                                $age = $interval->format("%Y");
                            }

                        } else {
                            $age = "00";
                        }
                    }
                } 
                // MyBB Geburtstagsfeld
                else {
                    if(!empty($char['birthday'])) {

                        // Geburstag aufsplitten
                        $birthday_array = explode("-", $char['birthday']);

                        $birth_day = $birthday_array[0];
                        $birth_month = $birthday_array[1];
                        $birth_year = $birthday_array[2];

                        // Jahr überprüfen, ob 4 Ziffern
                        if (strlen($birth_year) < 4) {
                            
                            $null = "";
                            for ($i = strlen($birth_year); $i <= 3; $i++) {
                                $null .= "0";
                            }

                            $birthyear = $null.$birth_year;

                        } else {
                            $birthyear = $birth_year;
                        }


                        $birthday = new DateTime($birth_day.".".$birth_month.".".$birthyear);
                        $interval = $ingame->diff($birthday);
                        $age = $interval->format("%Y");
                    } else {
                        $age = "00";
                    }
                }
            } 
            // Feld mit Alter
            else {
                // klassisches Profilfeld
                if (is_numeric($age_field)) {
                    if(!empty($char[$age_fid])) {
                        // Feld = X Jahre => Jahre rauswerfen
                        $only_age = preg_replace('/[^0-9]/', '', $char[$age_fid]);
                        $age = $only_age;
                    } else {
                        $age = "00";
                    }
                } 
                // Steckbrief Plugin
                else {
                    if(!empty($char[$age_field])) {
                        // Feld = X Jahre => Jahre rauswerfen
                        $only_age = preg_replace('/[^0-9]/', '', $char[$age_field]);
                        $age = $only_age;
                    } else {
                        $age = "00";
                    }
                }
            }
            $age_years = $lang->sprintf($lang->playerdirectory_statistic_age, $age);
 
            // EXTRA VARIABELN

            // Registrierungs Datum
            $regdate = my_date('relative', $char['regdate']);
            
            // Zuletzt online
            // Versteckt
            if ($char['invisible'] == 1 && $mybb->usergroup['canviewwolinvis'] != 1) {
                $lastactivity = $lang->playerdirectory_statistic_lastactivity_hidden;
            } 
            // Einsehbar
            else {
                if ($char['lastactive'] == 0) {
                    $lastactivity = $lang->playerdirectory_statistic_lastactivity_never;
                } else {
                    $lastactivity = my_date('relative', $char['lastactive']);
                }            
            }

            // INPLAY
            // Inplaytracker 2.0 von sparks fly
            if ($inplaytrackersystem == 0) {
                $sceneTIDs = "";
                // Szenen des Users auslesen - TID
                $query_allcharscenes = $db->query("SELECT tid FROM ".TABLE_PREFIX."threads
                WHERE (concat(',',partners,',') LIKE '%,".$charaID.",%')
                ORDER by tid ASC                
                ");     

                while ($allcharscenes = $db->fetch_array($query_allcharscenes)){
                    // Mit Infos füllen
                    $sceneTIDs .= $allcharscenes['tid'].",";
                } 
            } 
            // Inplaytracker 3.0 von sparks fly
            else if ($inplaytrackersystem == 1) {
                $sceneTIDs = "";
                // Szenen des Users auslesen - TID
                $query_allcharscenes = $db->query("SELECT ips.tid FROM ".TABLE_PREFIX."ipt_scenes ips
                LEFT JOIN ".TABLE_PREFIX."ipt_scenes_partners ipsp
                ON ips.tid = ipsp.tid
                WHERE ipsp.uid = '".$charaID."'
                ORDER by ips.tid ASC                
                ");     

                while ($allcharscenes = $db->fetch_array($query_allcharscenes)){
                    // Mit Infos füllen
                    $sceneTIDs .= $allcharscenes['tid'].",";
                } 
            }
            // Szenentracker von risuena
            else if ($inplaytrackersystem == 2) {
                $sceneTIDs = "";
                // Szenen des Users auslesen - TID
                $query_allcharscenes = $db->query("SELECT tid FROM ".TABLE_PREFIX."scenetracker
                WHERE uid = '".$charaID."'
                ORDER by tid ASC                
                ");    

                while ($allcharscenes = $db->fetch_array($query_allcharscenes)){
                    // Mit Infos füllen
                    $sceneTIDs .= $allcharscenes['tid'].",";
                } 
            } 
            // Inplaytracker von little.evil.genius
            else if ($inplaytrackersystem == 3) {
                $sceneTIDs = "";
                // Szenen des Users auslesen - TID
                $query_allcharscenes = $db->query("SELECT tid FROM ".TABLE_PREFIX."inplayscenes
                WHERE (concat(',',partners,',') LIKE '%,".$charaID.",%')
                ORDER by tid ASC                
                ");      

                while ($allcharscenes = $db->fetch_array($query_allcharscenes)){
                    // Mit Infos füllen
                    $sceneTIDs .= $allcharscenes['tid'].",";
                }
            }
            // Inplaytracker von Ales
            else if ($inplaytrackersystem == 4) {
                $sceneTIDs = "";
                $scene_username = "";
                $scene_username = get_user($charaID)['username'];
                // Szenen des Users auslesen - TID
                $query_allcharscenes = $db->query("SELECT tid FROM ".TABLE_PREFIX."threads
                WHERE (concat(', ',spieler,',') LIKE '%, ".$scene_username.",%')
                ORDER by tid ASC                
                ");     

                while ($allcharscenes = $db->fetch_array($query_allcharscenes)){
                    // Mit Infos füllen
                    $sceneTIDs .= $allcharscenes['tid'].",";
                } 
            }

            if(!empty($sceneTIDs)) {
                // letztes Komma abschneiden 
                $sceneTIDs_string = substr($sceneTIDs, 0, -1);
                // TIDs splitten
                $sceneTIDs_array = explode(",", $sceneTIDs_string);
            } else {
                $sceneTIDs_string = 0;
                $sceneTIDs_array = 0;
            }

            // Anzahl Inplay-Posts
            $count_allinplayposts = $db->num_rows($db->query("SELECT pid FROM ".TABLE_PREFIX."posts p
            WHERE p.uid = '".$charaID."'
            AND p.tid IN (".$sceneTIDs_string.")
            AND p.visible = '1'
            "));
    
            if($count_allinplayposts > 0) {
                $allinplayposts_formatted = number_format($count_allinplayposts, '0', ',', '.');
            } else {
                $allinplayposts_formatted = 0;
            }
    
            // Anzahl Inplay-Szenen
            if ($sceneTIDs_array != 0) {
                $allinplayscenes = count($sceneTIDs_array); 
            } else {
                $allinplayscenes = 0;
            }
            if($allinplayscenes > 0) {
                $allinplayscenes_formatted = number_format($allinplayscenes, '0', ',', '.');
            } else {
                $allinplayscenes_formatted = 0;
            }
            
            $character_inplaystat = $lang->sprintf($lang->playerdirectory_directory_user_inplaystat, $allinplayposts_formatted, $allinplayscenes_formatted);

            if ($characterstat_activated == 1) {
                if ($mybb->user['uid'] == 0 AND $characterstat_activated_guest != 1) {
                    $character_button = "";
                } else {
                    $character_button = $lang->sprintf($lang->playerdirectory_directory_character_statbutton, $charaID);
                }
                if ($mybb->user['uid'] != 0) {
                    $character_button = $lang->sprintf($lang->playerdirectory_directory_character_statbutton, $charaID);
                }
            } else {
                $character_button = "";
            }

            // Versteckt Array
            $user_invisible[$charaID] = $char['invisible'];
            // Szenen Array
            $user_allscenes[$char['username']] = $allinplayscenes;
            // Posts Array
            $user_allposts[$char['username']] = $count_allinplayposts;

			eval("\$characters_bit .= \"".$templates->get("playerdirectory_playerstat_characters")."\";");  
		}

		// letztes Komma vom UID String entfernen
		$userids_string = substr($userids, 0, -1);
		// UIDs splitten
		$userids_array = explode(",", $userids_string);

        // ALLE TIDs ALLER SZENEN 
        // Inplaytracker 2.0 von sparks fly
        if ($inplaytrackersystem == 0) {
            $sceneTIDs = "";
            foreach ($userids_array as $userID) {
                // Szenen des Users auslesen - TID
                $query_allcharscenes = $db->query("SELECT tid FROM ".TABLE_PREFIX."threads
                WHERE (concat(',',partners,',') LIKE '%,".$userID.",%')
                ORDER by tid ASC
                ");     

                while ($allcharscenes = $db->fetch_array($query_allcharscenes)){
                    // Mit Infos füllen
                    $sceneTIDs .= $allcharscenes['tid'].",";
                }   
            }
        } 
        // Inplaytracker 3.0 von sparks fly
        else if ($inplaytrackersystem == 1) {
            $sceneTIDs = "";
            foreach ($userids_array as $userID) {
                // Szenen des Users auslesen - TID
                $query_allcharscenes = $db->query("SELECT ips.tid FROM ".TABLE_PREFIX."ipt_scenes ips
                LEFT JOIN ".TABLE_PREFIX."ipt_scenes_partners ipsp
                ON ips.tid = ipsp.tid
                WHERE ipsp.uid = '".$userID."'
                ORDER by ips.tid ASC
                ");     

                while ($allcharscenes = $db->fetch_array($query_allcharscenes)){
                    // Mit Infos füllen
                    $sceneTIDs .= $allcharscenes['tid'].",";
                } 
            }
        }
        // Szenentracker von risuena
        else if ($inplaytrackersystem == 2) {
            $sceneTIDs = "";
            foreach ($userids_array as $userID) {
                // Szenen des Users auslesen - TID
                $query_allcharscenes = $db->query("SELECT tid FROM ".TABLE_PREFIX."scenetracker
                WHERE uid = '".$userID."'
                ORDER by tid ASC
                ");    

                while ($allcharscenes = $db->fetch_array($query_allcharscenes)){
                    // Mit Infos füllen
                    $sceneTIDs .= $allcharscenes['tid'].",";
                }   
            }
        } 
		// Inplaytracker von little.evil.genius
        else if ($inplaytrackersystem == 3) {
            $sceneTIDs = "";
            foreach ($userids_array as $userID) {
                // Szenen des Users auslesen - TID
                $query_allcharscenes = $db->query("SELECT tid FROM ".TABLE_PREFIX."inplayscenes
                WHERE (concat(',',partners,',') LIKE '%,".$userID.",%')
                ORDER by tid ASC
                ");      

                while ($allcharscenes = $db->fetch_array($query_allcharscenes)){
                    // Mit Infos füllen
                    $sceneTIDs .= $allcharscenes['tid'].",";
                }    
            }
        } 
        // Inplaytracker von Ales
        else if ($inplaytrackersystem == 4) {
            $sceneTIDs = "";
            $scene_username = "";
            foreach ($userids_array as $userID) {
                $scene_username = get_user($userID)['username'];
                // Szenen des Users auslesen - TID
                $query_allcharscenes = $db->query("SELECT tid FROM ".TABLE_PREFIX."threads
                WHERE (concat(', ',spieler,',') LIKE '%, ".$scene_username.",%')
                ORDER by tid ASC
                ");     

                while ($allcharscenes = $db->fetch_array($query_allcharscenes)){
                    // Mit Infos füllen
                    $sceneTIDs .= $allcharscenes['tid'].",";
                }   
            }
        }

        if(!empty($sceneTIDs)) {
            // letztes Komma abschneiden 
            $sceneTIDs_string = substr($sceneTIDs, 0, -1);
            // TIDs splitten
            $sceneTIDs_array = explode(",", $sceneTIDs_string);
        } else {
            $sceneTIDs_string = 0;
            $sceneTIDs_array = 0;
        }

		/* INPLAY/FOREN-STATISTIKEN */
		// Registriert seit
		$regdate = my_date('relative', $db->fetch_field($db->simple_select("users", "regdate", "uid = ".$mainID." OR as_uid = ".$mainID."", [ "order_dir" => "ASC", "order_by" => "regdate", "limit" => "1" ]), "regdate"));
		// Zuletzt oline
		$lastactivity = my_date('relative', $db->fetch_field($db->simple_select("users", "lastactive", "uid = ".$mainID." OR as_uid = ".$mainID."", [ "order_dir" => "DESC", "order_by" => "lastactive", "limit" => "1" ]), "lastactive"));
		
        // Letzte Aktivität && Online-Zeit
        // Zählen wie oft invisible (1)
        $count_invisible = count(array_keys($user_invisible, 1));
        // Es gibt Accounts mit invisible (1) und es sind NICHT alle Charaktere        
        if ($count_invisible > 0 && $count_invisible != $count_charas && $mybb->usergroup['canviewwolinvis'] != 1) {

            foreach ($user_invisible as $key => $value) {
                // Alle Werte mit invisible (1) löschen
                if ($value == 1) {
                    unset($user_invisible[$key]);
                }        
            }

            $visible_uids = "";
            foreach ($user_invisible as $key => $value) {
                $visible_uids .= $key.",";
            }
            // letztes Komma vom UID String entfernen        
            $visible_uids = substr($visible_uids, 0, -1);

            // Zuletzt online
            $lastactive = $db->fetch_field($db->simple_select("users", "lastactive", "uid IN (".$visible_uids.") OR as_uid IN (".$visible_uids.")", [ "order_dir" => "DESC", "order_by" => "lastactive", "limit" => "1" ]), "lastactive");
            if ($lastactive == 0) {
                $lastactivity = $lang->playerdirectory_statistic_lastactivity_never;
            } else {
                $lastactivity = my_date('relative', $lastactive);
            }

            // Online-Zeit
            $sumtime = $db->fetch_field($db->simple_select("users", "SUM(timeonline) AS sumtime", "uid IN (".$visible_uids.") OR as_uid IN (".$visible_uids.")"), "sumtime");
            
            if (!empty($sumtime)) {
                $timeonline = nice_time($sumtime);
            } else {
                $timeonline = $lang->playerdirectory_statistic_lastactivity_never;
            }
        } 
        // Es gibt Accounts mit invisible (1) und es sind ALLE Charaktere => Versteckt
        else if ($count_invisible > 0 && $count_invisible == $count_charas && $mybb->usergroup['canviewwolinvis'] != 1) {
            // Zuletzt online
            $lastactivity = $lang->playerdirectory_statistic_lastactivity_hidden;
            // Online-Zeit
            $timeonline = $lang->playerdirectory_statistic_lastactivity_hidden;
        } 
        // keine Accounts mit invisible (1)
        else {
            // Zuletzt online
            $lastactive = $db->fetch_field($db->simple_select("users", "lastactive", "uid = ".$mainID." OR as_uid = ".$mainID."", [ "order_dir" => "DESC", "order_by" => "lastactive", "limit" => "1" ]), "lastactive");
            
            if ($lastactive == 0) {
                $lastactivity = $lang->playerdirectory_statistic_lastactivity_never;
            } else {
                $lastactivity = my_date('relative', $lastactive);
            }

            // Online-Zeit
            $sumtime = $db->fetch_field($db->simple_select("users", "SUM(timeonline) AS sumtime", "uid = ".$mainID." OR as_uid = ".$mainID.""), "sumtime");
            
            if (!empty($sumtime)) {
                $timeonline = nice_time($sumtime);
            } else {
                $timeonline = $lang->playerdirectory_statistic_lastactivity_never;
            }
        }
        
        // Letzter Inplaybeitrag
		$query_lastinplaypost = $db->query("SELECT * FROM ".TABLE_PREFIX."posts p
		WHERE p.uid IN (".$userids_string.")
		AND p.tid IN (".$sceneTIDs_string.")
        AND p.visible = '1'
		ORDER by p.dateline DESC
		LIMIT 1
		"); 

		// Überprüfen, ob es überhaupt ein letzten Beitrag gibt
		$num_lastinplaypost = $db->num_rows($query_lastinplaypost);
		if ($num_lastinplaypost > 0) {
	
			while($lastip = $db->fetch_array($query_lastinplaypost)) {
	
                // Leer laufen lassen
                $dateline = "";
                $tid = "";
                $pid = "";
                $subject = "";
                $lastinplaypost = "";

                // Mit Infos füllen
				$dateline = my_date('relative', $lastip['dateline']);
				$tid = $lastip['tid'];
				$pid = $lastip['pid'];
				$subject = $db->fetch_field($db->simple_select("threads", "subject", "tid = ".$tid.""), "subject");
	
				if(my_strlen($subject) > 30) {
					$subject = my_substr($subject, 0, 30)."..";
				} else {
					$subject = $subject;
				}

				$lastinplaypost = "<a href=\"showthread.php?tid=".$tid."&pid=".$pid."#pid".$pid."\">".$subject."</a><br>".$dateline;
			}
		} else {
			$lastinplaypost = $lang->playerdirectory_statistic_posts_none;
		}

        // Anzahl Inplay-Posts
        $count_allinplayposts = $db->num_rows($db->query("SELECT pid FROM ".TABLE_PREFIX."posts p
		WHERE p.uid IN (".$userids_string.")
		AND p.tid IN (".$sceneTIDs_string.")
        AND p.visible = '1'
		"));

		if($count_allinplayposts > 0) {
			$allinplayposts_formatted = number_format($count_allinplayposts, '0', ',', '.');
		} else {
			$allinplayposts_formatted = 0;
		}

        // Anzahl Inplay-Szenen
        if ($sceneTIDs_array != 0) {
            $allinplayscenes = count($sceneTIDs_array); 
        } else {
            $allinplayscenes = 0;
        }
        if($allinplayscenes > 0) {
			$allinplayscenes_formatted = number_format($allinplayscenes, '0', ',', '.');
		} else {
			$allinplayscenes_formatted = 0;
		}

        // heißeste Szene => die meisten Posts
        // interessanteste Szene => die meisten Views
        // Überprüfen, ob es überhaupt eine Szene hat
        if($allinplayscenes > 0) {

            // heißeste Szene
            $query_hotscene = $db->query("SELECT * FROM ".TABLE_PREFIX."threads
            WHERE tid IN (".$sceneTIDs_string.")
            ORDER by replies DESC
            LIMIT 1
            ");

            while($hot = $db->fetch_array($query_hotscene)) {

                // Leer laufen lassen
                $tid = "";
                $pid = "";
                $replies = "";
                $subject = "";
                $hotscene = "";
	
                // Mit Infos füllen
				$tid = $hot['tid'];
				$pid = $hot['firstpost'];
				$replies = $hot['replies']+1;
	
				if(my_strlen($hot['subject']) > 30) {
					$subject = my_substr($hot['subject'], 0, 30)."..";
				} else {
					$subject = $hot['subject'];
				}
			
                $hotscene = $lang->sprintf($lang->playerdirectory_statistic_hotscene_link, $tid, $pid, $subject, $replies);
            }

            // interessanteste Szene
            $query_viewscene = $db->query("SELECT * FROM ".TABLE_PREFIX."threads
            WHERE tid IN (".$sceneTIDs_string.")
            ORDER by views DESC
            LIMIT 1
            ");

            while($view = $db->fetch_array($query_viewscene)) {

                // Leer laufen lassen
                $tid = "";
                $pid = "";
                $replies = "";
                $subject = "";
                $viewscene = "";
	
                // Mit Infos füllen
				$tid = $view['tid'];
				$pid = $view['firstpost'];
				$views = $view['views'];
	
				if(my_strlen($view['subject']) > 30) {
					$subject = my_substr($view['subject'], 0, 30)."..";
				} else {
					$subject = $view['subject'];
				}

                $viewscene = $lang->sprintf($lang->playerdirectory_statistic_viewscene_link, $tid, $pid, $subject, $views);
			}
        } else {
            $hotscene = $viewscene = $lang->playerdirectory_statistic_scene_none;
        }
        
        // WÖRTER & ZEICHEN
        $query_allinplaypost = $db->query("SELECT * FROM ".TABLE_PREFIX."posts p
		WHERE p.uid IN (".$userids_string.")
		AND p.tid IN (".$sceneTIDs_string.")
        AND p.visible = '1'
		");

		$wordsall = $charactersall = 0;
        while ($post = $db->fetch_array($query_allinplaypost)){

            $searchexp = array("\"", "-", "_", "<", ">", "/", "–", "[", "]");
            $wordsall += count(explode(' ', preg_replace('/\s+/', ' ', str_ireplace($searchexp, '', trim($post['message']))))); 

            $charactersall += strlen($post['message']);
        }

		// Geschriebene Zeichen
		if($charactersall > 0) {
			$charactersall_formatted = number_format($charactersall, '0', ',', '.');
		} else {
			$charactersall_formatted = 0;
		}

		// Durchschnittliche Zeichen
		if($charactersall > 0) {
            $averageCharacters = round($charactersall/$count_allinplayposts, 2);
			$averageCharacters_formatted = number_format($averageCharacters, 2, ',', '.');
		} else {
			$averageCharacters_formatted = 0;
		}

		// Geschriebene Wörter
		if($wordsall > 0) {
			$wordsall_formatted = number_format($wordsall, '0', ',', '.');
		} else {
			$wordsall_formatted = 0;
		}

		// Durchschnittliche Wörter
		if($wordsall > 0) {
            $averageWords =  round($wordsall/$count_allinplayposts, 2);
			$averageWords_formatted = number_format($averageWords, 2, ',', '.');
		} else {
			$averageWords_formatted = 0;
		}

        /* CHARAKTER-STATISTIKEN */
        // erster Charakter
        $query_firstchara = $db->query("SELECT * FROM ".TABLE_PREFIX."users p
		WHERE uid IN (".$userids_string.")
		ORDER by regdate ASC
		LIMIT 1
		");

        while ($first = $db->fetch_array($query_firstchara)){

            // Leer laufen lassen
            $uid = "";
            $charactername_formated = "";
            $charactername = "";
            $regdate = "";
            $firstchara_formated = "";
            $firstchara = "";
            $firstchara_formated_reg = "";
            $firstchara_reg = "";

            // Mit Infos füllen
            $uid = $first['uid'];
            // mit Gruppenfarbe
			$charactername_formated = build_profile_link(format_name($first['username'], $first['usergroup'], $first['displaygroup']), $uid);	
            // Nur Link
            $charactername = build_profile_link($first['username'], $uid);
            $regdate = my_date('relative', $first['regdate']);

            // Ohne Registriert seit
            $firstchara_formated = $charactername_formated;
            $firstchara = $charactername;

            // Mit Registriert seit
            $firstchara_formated_reg = $charactername_formated."<br>".$regdate;
            $firstchara_reg = $charactername."<br>".$regdate;
        }

        // neuster Charakter
        $query_lastchara = $db->query("SELECT * FROM ".TABLE_PREFIX."users p
		WHERE uid IN (".$userids_string.")
		ORDER by regdate DESC
		LIMIT 1
		");

        while ($last = $db->fetch_array($query_lastchara)){

            // Leer laufen lassen
            $uid = "";
            $charactername_formated = "";
            $charactername = "";
            $regdate = "";
            $lastchara_formated = "";
            $lastchara = "";
            $lastchara_formated_reg = "";
            $lastchara_reg = "";

            // Mit Infos füllen
            $uid = $last['uid'];
            // mit Gruppenfarbe
			$charactername_formated = build_profile_link(format_name($last['username'], $last['usergroup'], $last['displaygroup']), $uid);	
            // Nur Link
            $charactername = build_profile_link($last['username'], $uid);
            $regdate = my_date('relative', $last['regdate']);

            // Ohne Registriert seit
            $lastchara_formated = $charactername_formated;
            $lastchara = $charactername;

            // Mit Registriert seit
            $lastchara_formated_reg = $charactername_formated."<br>".$regdate;
            $lastchara_reg = $charactername."<br>".$regdate;
        }

        // Heißester Charakter
        $query_hotuser = $db->query("SELECT * FROM ".TABLE_PREFIX."posts p
		WHERE uid in (".$userids_string.")
		AND tid IN (".$sceneTIDs_string.")
        AND p.visible = '1'
		"); 

		$hotuser_array = [];
		while($hotU = $db->fetch_array($query_hotuser)) {

            // Leer laufen lassen
            $uid = "";
            $charactername = "";
            $count_inplayposts = "";

            // Mit Infos füllen
			$uid = $hotU['uid'];
			$charactername = $db->fetch_field($db->simple_select("users","username","uid = ".$uid.""), "username");
			$count_inplayposts =  $db->num_rows($db->query("SELECT pid FROM ".TABLE_PREFIX."posts p
			WHERE p.uid = ".$uid."
			AND p.tid IN (".$sceneTIDs_string.")
            AND p.visible = '1'
			"));

            if ($count_inplayposts > 0) {
                $hotuser_array[$uid] = $count_inplayposts;
            }
		}

		if(!empty($hotuser_array)) {

            // Leer laufen lassen
            $hotUID = "";
            $hotusername = "";
            $hotusergroup = "";
            $displaygroup = "";
            $hotCharactername = "";
            $hotCharactername_formated = "";
            $count_hotinplayposts = "";

            // Mit Infos füllen
            $hotUID = array_search(max($hotuser_array),$hotuser_array); 
            // USER DATEN ZIEHEN
            $hotusername = get_user($hotUID)['username'];
            $hotusergroup = get_user($hotUID)['usergroup'];
            $displaygroup = get_user($hotUID)['displaygroup'];

			$hotCharactername =  build_profile_link($hotusername, $hotUID);
            $hotCharactername_formated = build_profile_link(format_name($hotusername, $hotusergroup, $displaygroup), $hotUID);	

			$count_hotinplayposts = $db->num_rows($db->query("SELECT pid FROM ".TABLE_PREFIX."posts p
			WHERE p.uid = ".$hotUID."
			AND p.tid IN (".$sceneTIDs_string.")
            AND p.visible = '1'
			"));

			$hotCharacter = $lang->sprintf($lang->playerdirectory_statistic_hotCharacter_link, $hotCharactername, $count_hotinplayposts);
            $hotCharacter_formated = $lang->sprintf($lang->playerdirectory_statistic_hotCharacter_link, $hotCharactername_formated, $count_hotinplayposts);
		} else {
			$hotCharacter = $lang->playerdirectory_statistic_posts_none; 
		}

        // ALTER
        // Profilfeld/Steckbrieffeld
        if ($birthday_option == 0) {
            $age_fid = "";
            if (is_numeric($birthday_field)) {
                $query_birthdays = $db->query("SELECT * FROM ".TABLE_PREFIX."userfields
                WHERE ufid in (".$userids_string.")
                AND ".$birthday_fid." != ''
                ");

                $bday_array = [];
                while($bdays = $db->fetch_array($query_birthdays)) {

                    // Geburstag aufsplitten
                    $birthday_array = explode(".", $bdays[$birthday_fid]);

                    // Jahr überprüfen, ob 4 Ziffern
                    if (strlen($birthday_array[2]) < 4) {
                        
                        $null = "";
                        for ($i = strlen($birthday_array[2]); $i <= 3; $i++) {
                            $null .= "0";
                        }

                        $birthyear = $null.$birthday_array[2];

                    } else {
                        $birthyear = $birthday_array[2];
                    }

                    $username = get_user($bdays['ufid'])['username'];
                    $bday_array[$username] = $birthday_array[0].".".$birthday_array[1].".".$birthyear;
                } 
            } else {
                $query_birthdays = $db->query("SELECT * FROM ".TABLE_PREFIX."application_ucp_userfields
                WHERE uid in (".$userids_string.")
                AND fieldid = '".$birthday_fid."'
                AND value != ''
                ");

                $bday_array = [];
                $field_type = $db->fetch_field($db->simple_select("application_ucp_fields", "fieldtyp" ,"id = ".$birthday_fid.""), "fieldtyp");
                while($bdays = $db->fetch_array($query_birthdays)) {

                    // Datum -
                    if ($field_type == "date") {
                        // Geburstag aufsplitten
                        $birthday_array = explode("-", $bdays['value']);

                        $birth_day = $birthday_array[2];
                        $birth_month = $birthday_array[1];
                        $birth_year = $birthday_array[0];
                    } 
                    // Datum und Zeit T & -
                    else if ($field_type == "datetime-local") {
                        // Geburstag aufsplitten
                        $birthday_time = explode("T", $bdays['value']);
                        $birthday_array = explode("-", $birthday_time[0]);

                        $birth_day = $birthday_array[2];
                        $birth_month = $birthday_array[1];
                        $birth_year = $birthday_array[0];

                    } 
                    // Text .
                    else {
                        // Geburstag aufsplitten
                        $birthday_array = explode(".", $bdays['value']);

                        $birth_day = $birthday_array[0];
                        $birth_month = $birthday_array[1];
                        $birth_year = $birthday_array[2];
                    }

                    // Jahr überprüfen, ob 4 Ziffern
                    if (strlen($birth_year) < 4) {
                        
                        $null = "";
                        for ($i = strlen($birth_year); $i <= 3; $i++) {
                            $null .= "0";
                        }

                        $birthyear = $null.$birth_year;

                    } else {
                        $birthyear = $birth_year;
                    }
                    

                    $username = get_user($bdays['uid'])['username'];
                    $bday_array[$username] = $birth_day.".".$birth_month.".".$birthyear;
                } 
            }
        } 
        // MyBB-Geburtstagsfeld
        else if ($birthday_option == 1) {
            $query_birthdays = $db->query("SELECT * FROM ".TABLE_PREFIX."users u
            WHERE uid in (".$userids_string.")
            AND birthday != ''
            "); 

            $bday_array = [];
            while($bdays = $db->fetch_array($query_birthdays)) {

                // Geburstag aufsplitten
                $birthday_array = explode("-", $bdays['birthday']);

                $birth_day = $birthday_array[0];
                $birth_month = $birthday_array[1];
                $birth_year = $birthday_array[2];

                // Jahr überprüfen, ob 4 Ziffern
                if (strlen($birth_year) < 4) {
                    
                    $null = "";
                    for ($i = strlen($birth_year); $i <= 3; $i++) {
                        $null .= "0";
                    }

                    $birthyear = $null.$birth_year;

                } else {
                    $birthyear = $birth_year;
                }

                $username = $bdays['username'];
                $bday_array[$username] = $birth_day.".".$birth_month.".".$birthyear;
            } 
        }
        // Nur Alter
        else if ($birthday_option == 2) {
            if (is_numeric($age_field)) {
                $query_birthdays = $db->query("SELECT * FROM ".TABLE_PREFIX."userfields
                WHERE ufid in (".$userids_string.")
                AND ".$age_fid." != ''
                ");

                $bday_array = [];
                while($bdays = $db->fetch_array($query_birthdays)) {
                    $username = get_user($bdays['ufid'])['username'];
                    
                    // Feld = X Jahre => Jahre rauswerfen
                    $only_age = preg_replace('/[^0-9]/', '', $bdays['fid'.$age_field]);
                    $age = $only_age;

                    $bday_array[$username] = $age;
                } 
            } else {
                $query_birthdays = $db->query("SELECT * FROM ".TABLE_PREFIX."application_ucp_userfields
                WHERE uid in (".$userids_string.")
                AND fieldid = '".$age_fid."'
                AND value != ''
                ");

                $bday_array = [];
                while($bdays = $db->fetch_array($query_birthdays)) {
                    $username = get_user($bdays['uid'])['username'];
                    
                    // Feld = X Jahre => Jahre rauswerfen
                    $only_age = preg_replace('/[^0-9]/', '', $bdays[$age_field]);
                    $age = $only_age;

                    $bday_array[$username] = $age;
                }  
            }
        }

        $allalter = "";
		foreach ($bday_array as $name => $bday) {
	
            if ($birthday_option != 2) {
                $geburtstag = new DateTime($bday);
                $interval = $ingame->diff($geburtstag);
                $alter = $interval->format("%Y");
            } else {
                $alter = $bday;
            }
	
			$allalter .= $alter.",";	
		}
	
		// letztes Komma vom Alter String entfernen
		$allalter_string = substr($allalter, 0, -1);

		// Doppelte Alter entfernen 
		$oneAge = implode(',',array_unique(explode(',', $allalter_string)));

		// In Array bringen
		$arrayAge = explode(",", $oneAge);

		// ältester Charakter
		$maxage = max($arrayAge);	
		if(!empty($maxage)) {
			$maxage = $lang->sprintf($lang->playerdirectory_statistic_age, $maxage);
		} else {
			$maxage = $lang->playerdirectory_statistic_age_none; 
		}
		
		// jüngster Charakter
		$minage = min($arrayAge);	
		if(!empty($minage)) {
			$minage = $lang->sprintf($lang->playerdirectory_statistic_age, $minage);
		} else {
			$minage = $lang->playerdirectory_statistic_age_none; 
		}

		// Durschnittliches Alter
		// In Array bringen
		$arrayAges = explode(",", $allalter_string);

		$averageage = merdian($arrayAges);

		if(!empty($averageage)) {
			$averageage = $lang->sprintf($lang->playerdirectory_statistic_age, $averageage);
		} else {
			$averageage = $lang->playerdirectory_statistic_age_none; 
		}

        // Charakter-heißeste Szene => die meisten Posts eigener Charakter
        // Überprüfen, ob es überhaupt eine Szene hat
        if($allinplayscenes > 0) {

            // heißeste Charakter-Szene
            $query_hotcharascene = $db->query("SELECT * FROM ".TABLE_PREFIX."threads t
            LEFT JOIN ".TABLE_PREFIX."posts p
            ON t.tid = p.tid
            WHERE t.tid IN (".$sceneTIDs_string.")
            AND p.uid IN (".$userids_string.")
            AND p.visible = '1'
            ORDER by replies DESC
            LIMIT 1
            ");

            $hotcharascene = $lang->playerdirectory_statistic_posts_none;
            while($hotchara = $db->fetch_array($query_hotcharascene)) {

                // Leer laufen lassen
                $tid = "";
                $pid = "";
                $replies = "";
                $subject = "";
                $hotcharascene = "";
	
                // Mit Infos füllen
				$tid = $hotchara['tid'];
				$pid = $hotchara['firstpost'];

                $count_hotscenePosts = $db->num_rows($db->query("SELECT pid FROM ".TABLE_PREFIX."posts p
                WHERE p.uid IN (".$userids_string.")
                AND p.tid = '".$tid."'
                AND p.visible = '1'
                "));

				$replies = $count_hotscenePosts;
	
				if(my_strlen($hotchara['subject']) > 30) {
					$subject = my_substr($hotchara['subject'], 0, 30)."..";
				} else {
					$subject = $hotchara['subject'];
				}

				$hotcharascene = $lang->sprintf($lang->playerdirectory_statistic_hotscene_link, $tid, $pid, $subject, $replies);
			}
        } else {
            $hotcharascene = $lang->playerdirectory_statistic_scene_none;
        }

        // RANDOM INPLAYZITAT
        if ($inplayquotes_option == 1) {

            $query_inplayquotes = $db->query("SELECT * FROM ".TABLE_PREFIX."inplayquotes ipq
            LEFT JOIN ".TABLE_PREFIX."users u 
            ON u.uid = ipq.uid
            ".$query_join."
            WHERE ipq.uid IN (".$userids_string.")
            ORDER BY rand()
            "); 

            // Überprüfen, ob es überhaupt eine Inplayzitat gibt
            $num_inplayquotes = $db->num_rows($query_inplayquotes);
            if ($num_inplayquotes > 0) {

                while($quotes = $db->fetch_array($query_inplayquotes)) {
                    
                    // Leer laufen lassen
                    $charaID = "";
                    $avatar_url = "";
                    $charactername_formated = "";
                    $charactername = "";
                    $charactername_link = "";
                    $fullname = "";
                    $first_name = "";
                    $last_name = "";
                    $tid = "";
                    $pid = "";
                    $scenelink = "";
                    $subject = "";
                    $quote = "";

                    // Mit Infos füllen
                    $charaID = $quotes['uid'];
                    $tid = $quotes['tid'];
                    $pid = $quotes['pid'];
                    $charactername = $quotes['username'];
                    
                    // CHARACTER NAME
                    // mit Gruppenfarbe
                    $charactername_formated = build_profile_link(format_name($quotes['username'], $quotes['usergroup'], $quotes['displaygroup']), $charaID);	
                    // Nur Link
                    $charactername_link = build_profile_link($quotes['username'], $charaID);
                    // Name gesplittet
                    $fullname = explode(" ", $quotes['username']);
                    $first_name = array_shift($fullname);
                    $last_name = implode(" ", $fullname); 
            
                    // AVATAR KRAM
                    if ($avatar_guest == 1) {
                        if ($mybb->user['uid'] == '0' || $quotes['avatar'] == '') {
                            $avatar_url = $theme['imgdir']."/".$avatar_default;
                        } else {
                            $avatar_url = $quotes['avatar'];
                        }
                    } else {
                        if ($quotes['avatar'] == '') {
                            $avatar_url = $theme['imgdir']."/".$avatar_default;
                        } else {
                            $avatar_url = $quotes['avatar'];
                        }
                    }

                    $subject = $db->fetch_field($db->simple_select("threads", "subject", "tid = ".$tid.""), "subject");

                    $scenelink = "<a href=\"showthread.php?tid=".$tid."&pid=".$pid."#pid".$pid."\">".$subject."</a>";

                    $quote = $parser->parse_message($quotes['quote'], $text_options);
            
                    eval("\$random_inplayquote = \"".$templates->get("playerdirectory_playerstat_inplayquote")."\";");
                }
            } else {
                $random_inplayquote = "";
            }
        } else {
            $random_inplayquote = "";  
        }

        // INPLAYPOST-STATISTIK
        if ($inplaystat_option != 0) {

            $months_bit = "";
            $labels_chart = "";
            $data_chart = "";
            $maxCount = "";
            foreach ($last12_sort as $year_month => $month) {

                $year_splitt = explode("-", $year_month);
                $year = $year_splitt['0'];

                $startdate_setting = "01.".$month.".".$year;
    
                // Letzten Tag berechnen 
                $months31 = ',01,03,05,07,08,10,12,';
                $pos = strpos($months31, ",".$month.",");
    
                if ($pos === false AND $month != "02") {
                    $enddate_setting = "30.".$month.".".$year;
                } elseif ($pos === false AND $month == "02") {
    
                    // Schaltjahr überprüfen
                    if(($year % 400) == 0 || (($year % 4) == 0 && ($year % 100) != 0)) {
                        // Schaltjahr = 29
                        $enddate_setting = "29.".$month.".".$year;
                    } else {
                        // Schaltjahr = 28
                        $enddate_setting = "28.".$month.".".$year;
                    }
    
                } else {
                    $enddate_setting = "31.".$month.".".$year;
                }
    
                $startdate_setting = strtotime($startdate_setting. " 0:00");
                $enddate_setting = strtotime($enddate_setting. " 23:59:59");
    
                $count_allmonthposts = $db->num_rows($db->query("SELECT pid FROM ".TABLE_PREFIX."posts p
                WHERE p.uid IN (".$userids_string.")
                AND p.tid IN (".$sceneTIDs_string.")
                AND p.dateline BETWEEN '".$startdate_setting."' AND '".$enddate_setting."'
                AND p.visible = '1'
                "));

                if($count_allmonthposts > 0) {
                    $allmonthposts_formatted = number_format($count_allmonthposts, '0', ',', '.');
                } else {
                    $allmonthposts_formatted = 0;
                }

                $month_name = $months_array[$month]." ".$year;

                // Chart 
                $labels_chart .= "'".$months_array[$month]." ".$year."', ";
                $data_chart .= "'".$count_allmonthposts."', ";

                if ($inplaystat_option == 2) {
                    eval("\$months_bit .= \"".$templates->get("playerdirectory_postactivity_months_bit")."\";");
                }
            }

            // höchster Wert
            $data_array = explode(", ", $data_chart);
            $count_array = [];
            foreach ($data_array as $dataCount) {
	
                $onlyCount = str_replace("'", "", $dataCount);
                $onlyCount = str_replace(",", "", $onlyCount);
	
                $count_array[] = $onlyCount;
            }
            $maxCount = max($count_array)+30;

            if ($inplaystat_option == 2) {
                eval("\$postactivity_months .= \"".$templates->get("playerdirectory_postactivity_months")."\";");
            } else {
                $labels_chart = "[".$labels_chart."]";
                $data_chart = "[".$data_chart."]";
                eval("\$postactivity_months .= \"".$templates->get("playerdirectory_postactivity_months_chart")."\";");
            }
        } else {
            $postactivity_months = "";  
        }

        // all Szenenanzahl pro Charakter | all Postsanzahl pro Charakter
        if($scenestat_option != 0 || $poststat_option != 0) {

            // Anzahl Farben im ACP
            $colors_count = count($color_array);

            // Mehr Charas als Farben => so oft Farben ranhängen wie nötig
            if ($colors_count < $count_charas) {
                $key = 0;
                for ($i = $colors_count; $i < $count_charas+10; $i++) {
                    array_push($color_array, $color_array[$key]);
                    $key ++;
                }
            }
            //einmal werte verwürfeln
            $colorarray = array_rand($color_array, $count_charas);
            // string zusammenbasteln für farben (eine farbe pro chara)
            $colors = "[";
            for ($i = 0; $i < $count_charas; $i++) {
                $key = $colorarray[$i];
                $colors .= "'" . $color_array[$key] . "',";
            }
            $colors = substr($colors, 0, -1);
            $colors .= "]";

            // all Szenenanzahl pro Charakter
            if ($scenestat_option != 0) {

                $labels_chart = "";
                $data_chart = "";
                $normal_bit = "";
                $maxCount = "";
                foreach ($user_allscenes as $charactername => $scenecount) {
                    // Chart 
                    $labels_chart .= "'".$charactername."', ";
                    $data_chart .= "'".$scenecount."', ";

                    // Name gesplittet
                    $fullname = explode(" ", $charactername);
                    $first_name = array_shift($fullname);
                    $last_name = implode(" ", $fullname); 

                    if($scenecount > 0) {
                        $scenecount_formatted = number_format($scenecount, '0', ',', '.');
                    } else {
                        $scenecount_formatted = 0;
                    }

                    if ($scenestat_option == 3) {
                        eval("\$normal_bit .= \"".$templates->get("playerdirectory_postactivity_perChara_scenestat_bit")."\";");
                        $scenestat_bit = "<div class=\"playerdirectory_postactivity_perChara_bit\">".$normal_bit."</div>";
                    } else {
                        $scenestat_bit = "";
                    }
                }

                // höchster Wert
                $maxCount = max($user_allscenes)+30;

                if ($scenestat_option != 3) {

                    $labels_chart = "[".$labels_chart."]";
                    $data_chart = "[".$data_chart."]";
                    $backgroundColor = $colors;

                    // Säulendiagramm
                    if ($scenestat_option == 1) {
                        eval("\$scenestat_bit .= \"".$templates->get("playerdirectory_postactivity_perChara_scenestat_chart_bar")."\";");
                    } 
                    // Kreisdiagramm
                    else if ($scenestat_option == 2) {

                        if ($scenestat_legend_option == 1) {
                            $legend = "true";
                        } else {
                            $legend = "false";
                        }

                        eval("\$scenestat_bit .= \"".$templates->get("playerdirectory_postactivity_perChara_scenestat_chart_pie")."\";");
                    }
                }

                eval("\$postactivity_scenestat .= \"".$templates->get("playerdirectory_postactivity_perChara_scenestat")."\";");
            } else {
                $postactivity_scenestat = "";
            }

            // all Postsanzahl pro Charakter
            if ($poststat_option != 0) {

                $labels_chart = "";
                $data_chart = "";
                $normal_bit = "";
                $maxCount = "";
                foreach ($user_allposts as $charactername => $postcount) {

                    // Chart 
                    $labels_chart .= "'".$charactername."', ";
                    $data_chart .= "'".$postcount."', ";

                    // Name gesplittet
                    $fullname = explode(" ", $charactername);
                    $first_name = array_shift($fullname);
                    $last_name = implode(" ", $fullname); 

                    if($postcount > 0) {
                        $postcount_formatted = number_format($postcount, '0', ',', '.');
                    } else {
                        $postcount_formatted = 0;
                    }

                    if ($poststat_option == 3) {
                        eval("\$normal_bit .= \"".$templates->get("playerdirectory_postactivity_perChara_poststat_bit")."\";");
                        $poststat_bit = "<div class=\"playerdirectory_postactivity_perChara_bit\">".$normal_bit."</div>";
                    } else {
                        $poststat_bit = "";
                    }
                }

                // höchster Wert
                $maxCount = max($user_allposts)+30;

                if ($poststat_option != 3) {
                    $labels_chart = "[".$labels_chart."]";
                    $data_chart = "[".$data_chart."]";
                    $backgroundColor = $colors;

                    // Säulendiagramm
                    if ($poststat_option == 1) {
                        eval("\$poststat_bit .= \"".$templates->get("playerdirectory_postactivity_perChara_poststat_chart_bar")."\";");
                    } 
                    // Kreisdiagramm
                    else if ($poststat_option == 2) {

                        if ($poststat_legend_option == 1) {
                            $legend = "true";
                        } else {
                            $legend = "false";
                        }

                        eval("\$poststat_bit .= \"".$templates->get("playerdirectory_postactivity_perChara_poststat_chart_pie")."\";");
                    }
                }

                eval("\$postactivity_poststat .= \"".$templates->get("playerdirectory_postactivity_perChara_poststat")."\";");
            } else {
                $postactivity_poststat = "";
            }

            eval("\$postactivity_perChara .= \"".$templates->get("playerdirectory_postactivity_perChara")."\";");
        } else {
            $postactivity_perChara = "";
        }

        // PROFILFELDER/STECKBRIEFFELDER VOM MAINACCOUNT
        // {$playerstat['XXX']}
        $playerstat_query = $db->query("SELECT * FROM ".TABLE_PREFIX."users u
		".$query_join."
		WHERE u.uid = '".$mainID."'
        ");
        $playerstat = $db->fetch_array($playerstat_query);

        // EIGENE STATISTIKEN
        $statistic = playerdirectory_build_statistics($userids_string);
        
        // TEMPLATE FÜR DIE SEITE
		eval("\$page = \"".$templates->get("playerdirectory_playerstat")."\";");
		output_page($page);
		die();
    }

    // CHARAKTERSTATISTIK
    if($mybb->input['action'] == "characterstatistic"){

		// DIE CHARAKTER UID
		$charaID = $mybb->input['uid'];

        // PROFILFELDER/STECKBRIEFFELDER UND USER-Table 
        // {$characterstat['XXX']}
        $characterstat_query = $db->query("SELECT * FROM ".TABLE_PREFIX."users u
		".$query_join."
		WHERE u.uid = '".$charaID."'
        ");
        $characterstat = $db->fetch_array($characterstat_query);

        // ACCOUNTSWITCHER - HAUPT ID
		$playerID = $characterstat['as_uid'];
		if(empty($playerID)) {
			$playerID = $charaID;
		}

		// SPIELERNAME
        // wenn Zahl => klassisches Profilfeld
        if (is_numeric($playername_field)) {
            $playername = $characterstat[$playername_fid];
        } else {
            $playername = $characterstat[$playername_field];
        }
        if(empty($playername)) {
            $playername = $lang->playerdirectory_playername_none;
        } else {
            $playername = $playername;
        }

        // LEER LAUFEN LASSEN
        $charactername = "";
        $charactername_formated = "";
        $charactername_link = "";
        $first_name = "";
        $last_name = "";
        $avatar_url = "";
        $usertitle = "";
        $age = "";
        $regdate = "";
        $lastactivity = "";
        $timeonline = "";

        // MIT INFOS FÜLLEN
        // CHARACTER NAME
        // nur der Name
        $charactername = $characterstat['username'];

		// Listenmenü
		if($listsmenu != 2){
            // Jules Plugin
            if ($listsmenu == 1) {
                $query_lists = $db->simple_select("lists", "*");
                while($list = $db->fetch_array($query_lists)) {
                    eval("\$menu_bit .= \"".$templates->get("lists_menu_bit")."\";");
                }
                eval("\$lists_menu = \"".$templates->get("lists_menu")."\";");
            } else {
                eval("\$lists_menu = \"".$templates->get($listsmenu_tpl)."\";");
            }
        } else {
            $lists_menu = "";
        }

        // NAVIGATION
		if(!empty($listsnav)){
            add_breadcrumb($lang->playerdirectory_lists, $listsnav);
            if ($directory_activated == 1) {
                add_breadcrumb($lang->playerdirectory_directory, "misc.php?action=playerdirectory");
            }
            if ($playerstat_activated == 1) {
                add_breadcrumb($lang->sprintf($lang->playerdirectory_playerstat, $playername), "misc.php?action=playerstatistic&uid=".$playerID);
            }
            add_breadcrumb($lang->sprintf($lang->playerdirectory_characterstat, $charactername), "misc.php?action=characterstatistic");
		} else{
            if ($directory_activated == 1) {
                add_breadcrumb($lang->playerdirectory_directory, "misc.php?action=playerdirectory");
            }
            if ($playerstat_activated == 1) {
                add_breadcrumb($lang->sprintf($lang->playerdirectory_playerstat, $playername), "misc.php?action=playerstatistic&uid=".$playerID);
            }
            add_breadcrumb($lang->sprintf($lang->playerdirectory_characterstat, $charactername), "misc.php?action=characterstatistic");
		}

        $lang->playerdirectory_characterstat = $lang->sprintf($lang->playerdirectory_characterstat, $charactername);

        // Seite ist deaktiviert 
        if ($characterstat_activated == 0) {
			error($lang->playerdirectory_characterstat_deactivated);
			return;
		}

		// Gäste ausschließen
		if ($characterstat_activated_guest == 0 && $mybb->user['uid'] == 0) {
			error($lang->playerdirectory_characterstat_deactivated_guest);
			return;
		}

        // keine ID oder User ist nicht vorhanden
		if (empty($mybb->input['uid']) || empty($characterstat['uid'])) {
			error($lang->playerdirectory_error_uid);
            return;
		}

        // Spieler hat es eingestellt
        $characterstat_setting = $db->fetch_field($db->simple_select("users", "playerdirectory_characterstat", "uid = '".$playerID."'"), "playerdirectory_characterstat");
		$characterstat_guest_setting = $db->fetch_field($db->simple_select("users", "playerdirectory_characterstat_guest", "uid = '".$playerID."'"), "playerdirectory_characterstat_guest");        
        // ... andere Spieler ausschließen
        if (($characterstat_setting == 1 AND ($mybb->user['uid'] != $charaID AND $mybb->user['uid'] != $playerID AND $mybb->user['as_uid'] != $playerID)) AND ($characterstat_setting == 1 AND $mybb->usergroup['canmodcp'] != 1)  && $mybb->user['uid'] != 0) {
			error($lang->sprintf($lang->playerdirectory_characterstat_user_option, $playername));
			return;
		}
        // ... Gäste ausschließen
        if ($characterstat_guest_setting == 1 && $mybb->user['uid'] == 0) {
			error($lang->sprintf($lang->playerdirectory_characterstat_user_option_guest, $playername));
			return;
		}

        // HINWEIS BANNER
        $conf_user = "";
        $conf_guest = "";
        if ($mybb->user['uid'] == $charaID || $mybb->user['uid'] == $playerID || $mybb->user['as_uid'] == $playerID) {

           if ($characterstat_setting == 1) {
            $conf_user = $lang->playerdirectory_notice_banner_conf_hidden;
           } else {
            $conf_user = $lang->playerdirectory_notice_banner_conf;
           }

           if ($characterstat_guest_setting == 1) {
            $conf_guest = $lang->playerdirectory_notice_banner_conf_hidden;
           } else {
            $conf_guest = $lang->playerdirectory_notice_banner_conf;
           }

           $banner_text = $lang->sprintf($lang->playerdirectory_notice_banner, $lang->playerdirectory_notice_banner_characterstat, $conf_user, $conf_guest);

            eval("\$notice_banner = \"".$templates->get("playerdirectory_notice_banner")."\";");
        } else {
            $notice_banner = "";
        }

        // INPLAY
        // Inplaytracker 2.0 von sparks fly
        if ($inplaytrackersystem == 0) {
            $sceneTIDs = "";
            // Szenen des Users auslesen - TID
            $query_allcharscenes = $db->query("SELECT tid FROM ".TABLE_PREFIX."threads
            WHERE (concat(',',partners,',') LIKE '%,".$charaID.",%')
            ORDER by tid ASC                
            ");     
            while ($allcharscenes = $db->fetch_array($query_allcharscenes)){
                // Mit Infos füllen
                $sceneTIDs .= $allcharscenes['tid'].",";
            } 
        } 
        // Inplaytracker 3.0 von sparks fly
        else if ($inplaytrackersystem == 1) {
            $sceneTIDs = "";
            // Szenen des Users auslesen - TID
            $query_allcharscenes = $db->query("SELECT ips.tid FROM ".TABLE_PREFIX."ipt_scenes ips
            LEFT JOIN ".TABLE_PREFIX."ipt_scenes_partners ipsp
            ON ips.tid = ipsp.tid
            WHERE ipsp.uid = '".$charaID."'
            ORDER by ips.tid ASC                
            ");     
            while ($allcharscenes = $db->fetch_array($query_allcharscenes)){
                // Mit Infos füllen
                $sceneTIDs .= $allcharscenes['tid'].",";
            } 
        }
        // Szenentracker von risuena
        else if ($inplaytrackersystem == 2) {
            $sceneTIDs = "";
            // Szenen des Users auslesen - TID
            $query_allcharscenes = $db->query("SELECT tid FROM ".TABLE_PREFIX."scenetracker
            WHERE uid = '".$charaID."'
            ORDER by tid ASC                
            ");    
            while ($allcharscenes = $db->fetch_array($query_allcharscenes)){
                // Mit Infos füllen
                $sceneTIDs .= $allcharscenes['tid'].",";
            } 
        } 
        // Inplaytracker von little.evil.genius
        else if ($inplaytrackersystem == 3) {
            $sceneTIDs = "";
            // Szenen des Users auslesen - TID
            $query_allcharscenes = $db->query("SELECT tid FROM ".TABLE_PREFIX."inplayscenes
            WHERE (concat(',',partners,',') LIKE '%,".$charaID.",%')
            ORDER by tid ASC                        
            ");      

            while ($allcharscenes = $db->fetch_array($query_allcharscenes)){
                // Mit Infos füllen
                $sceneTIDs .= $allcharscenes['tid'].",";
            }        
        }
        // Inplaytracker von Ales
        else if ($inplaytrackersystem == 4) {
            $sceneTIDs = "";
            $scene_username = "";
            $scene_username = get_user($charaID)['username'];
            // Szenen des Users auslesen - TID
            $query_allcharscenes = $db->query("SELECT tid FROM ".TABLE_PREFIX."threads
            WHERE (concat(', ',spieler,',') LIKE '%, ".$scene_username.",%')
            ORDER by tid ASC                
            ");     
            while ($allcharscenes = $db->fetch_array($query_allcharscenes)){
                // Mit Infos füllen
                $sceneTIDs .= $allcharscenes['tid'].",";
            } 
        }

        if(!empty($sceneTIDs)) {
            // letztes Komma abschneiden 
            $sceneTIDs_string = substr($sceneTIDs, 0, -1);
            // TIDs splitten
            $sceneTIDs_array = explode(",", $sceneTIDs_string);
        } else {
            $sceneTIDs_string = 0;
            $sceneTIDs_array = 0;
        }
        
        // MIT INFOS FÜLLEN
        // CHARACTER NAME
        // mit Gruppenfarbe
        $charactername_formated = build_profile_link(format_name($charactername, $characterstat['usergroup'], $characterstat['displaygroup']), $charaID);	
        // Nur Link
        $charactername_link = build_profile_link($charactername, $charaID);
        // Name gesplittet
        $fullname = explode(" ", $charactername);
        $first_name = array_shift($fullname);
        $last_name = implode(" ", $fullname);

        // AVATAR KRAM
        if ($avatar_guest == 1) {
            if ($mybb->user['uid'] == '0' || $characterstat['avatar'] == '') {
                $avatar_url = $theme['imgdir']."/".$avatar_default;
            } else {
                $avatar_url = $characterstat['avatar'];
            }
        } else {
            if ($characterstat['avatar'] == '') {
                $avatar_url = $theme['imgdir']."/".$avatar_default;
            } else {
                $avatar_url = $characterstat['avatar'];
            }
        }

        // USERTITEL       
        if ($characterstat['usertitle'] == '') {
            $usertitle = $db->fetch_field($db->simple_select("usergroups", "title", "gid = '".$characterstat['usergroup']."'"), "title");
        } else {
            $usertitle  = $characterstat['usertitle'];
        }

        // ALTER
        // automatisches berechnen
        if ($birthday_option != 2) {
            // Profilfeld/Steckbriefeld
            if ($birthday_option == 0) {
                // klassisches Profilfeld
                if (is_numeric($birthday_field)) {
                    if(!empty($characterstat[$birthday_fid])) {

                        // Geburstag aufsplitten
                        $birthday_array = explode(".", $characterstat[$birthday_fid]);

                        // Vor Christus "v. Chr."
                        if (str_contains($characterstat[$birthday_fid], 'v. Chr.')) {
                        
                            // Geburtstjahr - v entfernen
                            $birthyear = str_replace(" v", "", $birthday_array[2]);
                        
                            // Alter = aktuelles Jahr + v. Chr. Jahr
                            $age = $inplay_array[2] + $birthyear;
                        
                        }
                        // nach Christus
                        else {
                            // Jahr überprüfen, ob 4 Ziffern
                            if (strlen($birthday_array[2]) < 4) {
                        
                                $null = "";
                                for ($i = strlen($birthday_array[2]); $i <= 3; $i++) {
                                    $null .= "0";
                                }
                        
                                $birthyear = $null.$birthday_array[2];
                        
                            } else {
                                $birthyear = $birthday_array[2];
                            }
                        
                            $birthday = new DateTime($birthday_array[0].".".$birthday_array[1].".".$birthyear);
                            $interval = $ingame->diff($birthday);
                            $age = $interval->format("%Y");
                        }
                    } else {
                        $age = "00";
                    }
                } 
                // Steckbrief Plugin
                else {
                    if(!empty($characterstat[$birthday_field])) {

                        $field_type = $db->fetch_field($db->simple_select("application_ucp_fields", "fieldtyp" ,"fieldname = '".$birthday_field."'"), "fieldtyp");

                        // Datum -
                        if ($field_type == "date") {
                            // Geburstag aufsplitten
                            $birthday_array = explode("-", $characterstat[$birthday_field]);

                            $birth_day = $birthday_array[2];
                            $birth_month = $birthday_array[1];
                            $birth_year = $birthday_array[0];
                        } 
                        // Datum und Zeit T & -
                        else if ($field_type == "datetime-local") {
                            // Geburstag aufsplitten
                            $birthday_time = explode("T", $characterstat[$birthday_field]);
                            $birthday_array = explode("-", $birthday_time[0]);

                            $birth_day = $birthday_array[2];
                            $birth_month = $birthday_array[1];
                            $birth_year = $birthday_array[0];

                        } 
                        // Text .
                        else {
                            // Geburstag aufsplitten
                            $birthday_array = explode(".", $characterstat[$birthday_field]);

                            $birth_day = $birthday_array[0];
                            $birth_month = $birthday_array[1];
                            $birth_year = $birthday_array[2];
                        }

                        // Vor Christus "v. Chr."
                        if (str_contains($characterstat[$birthday_field], 'v. Chr.')) {
                        
                            // Geburtstjahr - v entfernen
                            $birthyear = str_replace(" v", "", $birth_year);
                        
                            // Alter = aktuelles Jahr + v. Chr. Jahr
                            $age = $inplay_array[2] + $birthyear;
                        
                        }
                        // nach Christus
                        else {
                            // Jahr überprüfen, ob 4 Ziffern
                            if (strlen($birth_year) < 4) {
                                
                                $null = "";
                                for ($i = strlen($birth_year); $i <= 3; $i++) {
                                    $null .= "0";
                                }
    
                                $birthyear = $null.$birth_year;
    
                            } else {
                                $birthyear = $birth_year;
                            }
    
                            $birthday = new DateTime($birth_day.".".$birth_month.".".$birthyear);
                            $interval = $ingame->diff($birthday);
                            $age = $interval->format("%Y");
                        }
                    } else {
                        $age = "00";
                    }
                }
            } 
            // MyBB Geburtstagsfeld
            else {
                if(!empty($characterstat['birthday'])) {

                    // Geburstag aufsplitten
                    $birthday_array = explode("-", $characterstat['birthday']);

                    $birth_day = $birthday_array[0];
                    $birth_month = $birthday_array[1];
                    $birth_year = $birthday_array[2];

                    // Jahr überprüfen, ob 4 Ziffern
                    if (strlen($birth_year) < 4) {
                        
                        $null = "";
                        for ($i = strlen($birth_year); $i <= 3; $i++) {
                            $null .= "0";
                        }

                        $birthyear = $null.$birth_year;

                    } else {
                        $birthyear = $birth_year;
                    }


                    $birthday = new DateTime($birth_day.".".$birth_month.".".$birthyear);
                    $interval = $ingame->diff($birthday);
                    $age = $interval->format("%Y");
                } else {
                    $age = "00";
                }
            }
        } 
        // Feld mit Alter
        else {
            // klassisches Profilfeld
            if (is_numeric($age_field)) {
                if(!empty($characterstat[$age_fid])) {
                    // Feld = X Jahre => Jahre rauswerfen
                    $only_age = preg_replace('/[^0-9]/', '', $characterstat[$age_fid]);
                    $age = $only_age;
                } else {
                    $age = "00";
                }
            } 
            // Steckbrief Plugin
            else {
                if(!empty($characterstat[$age_field])) {
                    // Feld = X Jahre => Jahre rauswerfen
                    $only_age = preg_replace('/[^0-9]/', '', $characterstat[$age_field]);
                    $age = $only_age;
                } else {
                    $age = "00";
                }
            }
        }

        // Registrierungs Datum
        $regdate = my_date('relative', $characterstat['regdate']);
            
        // Letzte Aktivität && Online-Zeit
        // Versteckt
        if ($characterstat['invisible'] == 1 && $mybb->usergroup['canviewwolinvis'] != 1) {
            // Zuletzt online
            $lastactivity = $lang->playerdirectory_statistic_lastactivity_hidden;
            // Online-Zeit
            $timeonline = $lang->playerdirectory_statistic_lastactivity_hidden;
        } 
        // Einsehbar
        else {
            if ($characterstat['lastactive'] == 0) {
                // Zuletzt online
                $lastactivity = $lang->playerdirectory_statistic_lastactivity_never;
                // Online-Zeit
                $timeonline = $lang->playerdirectory_statistic_lastactivity_never;
            } else {
                $lastactivity = my_date('relative', $characterstat['lastactive']);
                $timeonline = nice_time($characterstat['timeonline']);
            }            
        }
        
        // Letzter Inplaybeitrag
		$query_lastinplaypost = $db->query("SELECT * FROM ".TABLE_PREFIX."posts p
		WHERE p.uid = '".$charaID."'
		AND p.tid IN (".$sceneTIDs_string.")
        AND p.visible = '1'
		ORDER by p.dateline DESC
		LIMIT 1
		"); 

		// Überprüfen, ob es überhaupt ein letzten Beitrag gibt
		$num_lastinplaypost = $db->num_rows($query_lastinplaypost);
		if ($num_lastinplaypost > 0) {
	
			while($lastip = $db->fetch_array($query_lastinplaypost)) {
	
                // Leer laufen lassen
                $dateline = "";
                $tid = "";
                $pid = "";
                $subject = "";
                $lastinplaypost = "";

                // Mit Infos füllen
				$dateline = my_date('relative', $lastip['dateline']);
				$tid = $lastip['tid'];
				$pid = $lastip['pid'];
				$subject = $db->fetch_field($db->simple_select("threads", "subject", "tid = ".$tid.""), "subject");
	
				if(my_strlen($subject) > 30) {
					$subject = my_substr($subject, 0, 30)."..";
				} else {
					$subject = $subject;
				}

				$lastinplaypost = "<a href=\"showthread.php?tid=".$tid."&pid=".$pid."#pid".$pid."\">".$subject."</a><br>".$dateline;
			}
		} else {
			$lastinplaypost = $lang->playerdirectory_statistic_posts_none;
		}

        // Anzahl Inplay-Posts
        $count_allinplayposts = $db->num_rows($db->query("SELECT pid FROM ".TABLE_PREFIX."posts p
		WHERE p.uid = '".$charaID."'
		AND p.tid IN (".$sceneTIDs_string.")
        AND p.visible = '1'
		"));

		if($count_allinplayposts > 0) {
			$allinplayposts_formatted = number_format($count_allinplayposts, '0', ',', '.');
		} else {
			$allinplayposts_formatted = 0;
		}

        // Anzahl Inplay-Szenen
        if ($sceneTIDs_array != 0) {
            $allinplayscenes = count($sceneTIDs_array); 
        } else {
            $allinplayscenes = 0;
        }
        if($allinplayscenes > 0) {
			$allinplayscenes_formatted = number_format($allinplayscenes, '0', ',', '.');
		} else {
			$allinplayscenes_formatted = 0;
		}

        // heißeste Szene => die meisten Posts
        // interessanteste Szene => die meisten Views
        // Überprüfen, ob es überhaupt eine Szene hat
        if($allinplayscenes > 0) {

            // heißeste Szene
            $query_hotscene = $db->query("SELECT * FROM ".TABLE_PREFIX."threads
            WHERE tid IN (".$sceneTIDs_string.")
            ORDER by replies DESC
            LIMIT 1
            ");

            while($hot = $db->fetch_array($query_hotscene)) {

                // Leer laufen lassen
                $tid = "";
                $pid = "";
                $replies = "";
                $subject = "";
                $hotscene = "";
	
                // Mit Infos füllen
				$tid = $hot['tid'];
				$pid = $hot['firstpost'];
				$replies = $hot['replies']+1;
	
				if(my_strlen($hot['subject']) > 30) {
					$subject = my_substr($hot['subject'], 0, 30)."..";
				} else {
					$subject = $hot['subject'];
				}

				$hotscene = $lang->sprintf($lang->playerdirectory_statistic_hotscene_link, $tid, $pid, $subject, $replies);
			}

            // interessanteste Szene
            $query_viewscene = $db->query("SELECT * FROM ".TABLE_PREFIX."threads
            WHERE tid IN (".$sceneTIDs_string.")
            ORDER by views DESC
            LIMIT 1
            ");

            while($view = $db->fetch_array($query_viewscene)) {

                // Leer laufen lassen
                $tid = "";
                $pid = "";
                $replies = "";
                $subject = "";
                $viewscene = "";
	
                // Mit Infos füllen
				$tid = $view['tid'];
				$pid = $view['firstpost'];
				$views = $view['views'];
	
				if(my_strlen($view['subject']) > 30) {
					$subject = my_substr($view['subject'], 0, 30)."..";
				} else {
					$subject = $view['subject'];
				}

				$viewscene = $lang->sprintf($lang->playerdirectory_statistic_viewscene_link, $tid, $pid, $subject, $views);
			}
        } else {
            $hotscene = $viewscene = $lang->playerdirectory_statistic_scene_none;
        }
        
        // WÖRTER & ZEICHEN
        $query_allinplaypost = $db->query("SELECT * FROM ".TABLE_PREFIX."posts p
		WHERE p.uid = '".$charaID."'
		AND p.tid IN (".$sceneTIDs_string.")
        AND p.visible = '1'
		");

		$wordsall = $charactersall = 0;
        while ($post = $db->fetch_array($query_allinplaypost)){

            $searchexp = array("\"", "-", "_", "<", ">", "/", "–", "[", "]");
            $wordsall += count(explode(' ', preg_replace('/\s+/', ' ', str_ireplace($searchexp, '', trim($post['message']))))); 

            $charactersall += strlen($post['message']);
        }

		// Geschriebene Zeichen
		if($charactersall > 0) {
			$charactersall_formatted = number_format($charactersall, '0', ',', '.');
		} else {
			$charactersall_formatted = 0;
		}

		// Durchschnittliche Zeichen
		if($charactersall > 0) {
            $averageCharacters = round($charactersall/$count_allinplayposts, 2);
			$averageCharacters_formatted = number_format($averageCharacters, 2, ',', '.');
		} else {
			$averageCharacters_formatted = 0;
		}

		// Geschriebene Wörter
		if($wordsall > 0) {
			$wordsall_formatted = number_format($wordsall, '0', ',', '.');
		} else {
			$wordsall_formatted = 0;
		}

		// Durchschnittliche Wörter
		if($wordsall > 0) {
            $averageWords =  round($wordsall/$count_allinplayposts, 2);
			$averageWords_formatted = number_format($averageWords, 2, ',', '.');
		} else {
			$averageWords_formatted = 0;
		}

        // RANDOM INPLAYZITAT
        if ($inplayquotes_option == 1) {

            $query_inplayquotes = $db->query("SELECT * FROM ".TABLE_PREFIX."inplayquotes ipq
            LEFT JOIN ".TABLE_PREFIX."users u 
            ON u.uid = ipq.uid
            ".$query_join."
            WHERE ipq.uid = '".$charaID."'
            ORDER BY rand()
            "); 

            // Überprüfen, ob es überhaupt eine Inplayzitat gibt
            $num_inplayquotes = $db->num_rows($query_inplayquotes);
            if ($num_inplayquotes > 0) {

                while($quotes = $db->fetch_array($query_inplayquotes)) {
                    
                    // Leer laufen lassen
                    $charaID = "";
                    $avatar_url = "";
                    $charactername_formated = "";
                    $charactername = "";
                    $charactername_link = "";
                    $fullname = "";
                    $first_name = "";
                    $last_name = "";
                    $tid = "";
                    $pid = "";
                    $scenelink = "";
                    $subject = "";
                    $quote = "";

                    // Mit Infos füllen
                    $charaID = $quotes['uid'];
                    $tid = $quotes['tid'];
                    $pid = $quotes['pid'];
                    $charactername = $quotes['username'];
                    
                    // CHARACTER NAME
                    // mit Gruppenfarbe
                    $charactername_formated = build_profile_link(format_name($quotes['username'], $quotes['usergroup'], $quotes['displaygroup']), $charaID);	
                    // Nur Link
                    $charactername_link = build_profile_link($quotes['username'], $charaID);
                    // Name gesplittet
                    $fullname = explode(" ", $quotes['username']);
                    $first_name = array_shift($fullname);
                    $last_name = implode(" ", $fullname); 
            
                    // AVATAR KRAM
                    if ($avatar_guest == 1) {
                        if ($mybb->user['uid'] == '0' || $quotes['avatar'] == '') {
                            $avatar_url = $theme['imgdir']."/".$avatar_default;
                        } else {
                            $avatar_url = $quotes['avatar'];
                        }
                    } else {
                        if ($quotes['avatar'] == '') {
                            $avatar_url = $theme['imgdir']."/".$avatar_default;
                        } else {
                            $avatar_url = $quotes['avatar'];
                        }
                    }

                    $subject = $db->fetch_field($db->simple_select("threads", "subject", "tid = ".$tid.""), "subject");

                    $scenelink = "<a href=\"showthread.php?tid=".$tid."&pid=".$pid."#pid".$pid."\">".$subject."</a>";

                    $quote = $parser->parse_message($quotes['quote'], $text_options);
            
                    eval("\$random_inplayquote = \"".$templates->get("playerdirectory_characterstat_inplayquote")."\";");
                }
            } else {
                $random_inplayquote = "";
            }
        } else {
            $random_inplayquote = "";  
        }

        // INPLAYPOST-STATISTIK
        if ($inplaystat_option != 0) {

            $months_bit = "";
            $labels_chart = "";
            $data_chart = "";
            $maxCount = "";
            foreach ($last12_sort as $year_month => $month) {

                $year_splitt = explode("-", $year_month);
                $year = $year_splitt['0'];

                $startdate_setting = "01.".$month.".".$year;
    
                // Letzten Tag berechnen 
                $months31 = ',01,03,05,07,08,10,12,';
                $pos = strpos($months31, ",".$month.",");
    
                if ($pos === false AND $month != "02") {
                    $enddate_setting = "30.".$month.".".$year;
                } elseif ($pos === false AND $month == "02") {
    
                    // Schaltjahr überprüfen
                    if(($year % 400) == 0 || (($year % 4) == 0 && ($year % 100) != 0)) {
                        // Schaltjahr = 29
                        $enddate_setting = "29.".$month.".".$year;
                    } else {
                        // Schaltjahr = 28
                        $enddate_setting = "28.".$month.".".$year;
                    }
    
                } else {
                    $enddate_setting = "31.".$month.".".$year;
                }
    
                $startdate_setting = strtotime($startdate_setting. " 0:00");
                $enddate_setting = strtotime($enddate_setting. " 23:59:59");
    
                $count_allmonthposts = $db->num_rows($db->query("SELECT pid FROM ".TABLE_PREFIX."posts p
                WHERE p.uid = '".$charaID."'
                AND p.tid IN (".$sceneTIDs_string.")
                AND p.dateline BETWEEN '".$startdate_setting."' AND '".$enddate_setting."'
                AND p.visible = '1'
                "));

                if($count_allmonthposts > 0) {
                    $allmonthposts_formatted = number_format($count_allmonthposts, '0', ',', '.');
                } else {
                    $allmonthposts_formatted = 0;
                }

                $month_name = $months_array[$month]." ".$year;

                // Chart 
                $labels_chart .= "'".$months_array[$month]." ".$year."', ";
                $data_chart .= "'".$count_allmonthposts."', ";

                if ($inplaystat_option == 2) {
                    eval("\$months_bit .= \"".$templates->get("playerdirectory_postactivity_months_bit")."\";");
                }
            }

            // höchster Wert
            $data_array = explode(", ", $data_chart);
            $count_array = [];
            foreach ($data_array as $dataCount) {
	
                $onlyCount = str_replace("'", "", $dataCount);
                $onlyCount = str_replace(",", "", $onlyCount);
	
                $count_array[] = $onlyCount;
            }
            $maxCount = max($count_array)+30;

            if ($inplaystat_option == 2) {
                eval("\$postactivity_months .= \"".$templates->get("playerdirectory_postactivity_months")."\";");
            } else {
                $labels_chart = "[".$labels_chart."]";
                $data_chart = "[".$data_chart."]";
                eval("\$postactivity_months .= \"".$templates->get("playerdirectory_postactivity_months_chart")."\";");
            }


        } else {
            $postactivity_months = "";  
        }

        // TEMPLATE FÜR DIE SEITE
		eval("\$page = \"".$templates->get("playerdirectory_characterstat")."\";");
		output_page($page);
		die();
    }
}

// ONLINE ANZEIGE
function playerdirectory_online_activity($user_activity) {
 
    global $parameters, $user;
    
    $split_loc = explode(".php", $user_activity['location']);
    if($split_loc[0] == $user['location']) {
        $filename = '';
    } else {
        $filename = my_substr($split_loc[0], -my_strpos(strrev($split_loc[0]), "/"));
    }
        
    switch ($filename) {
        case 'misc':
            if($parameters['action'] == "playerdirectory" && empty($parameters['site'])) {
                $user_activity['activity'] = "playerdirectory";
            }
            if($parameters['action'] == "playerstatistic"){
				$user_activity['activity'] = "playerstatistic";

				$parameters['uid'] = (int)$parameters['uid'];
				$user_activity['uid'] = $parameters['uid'];
			}
            if($parameters['action'] == "characterstatistic"){
				$user_activity['activity'] = "characterstatistic";

				$parameters['uid'] = (int)$parameters['uid'];
				$user_activity['uid'] = $parameters['uid'];
			}
        break;
    }         

    return $user_activity;
}
function playerdirectory_online_location($plugin_array) {

    global $lang, $db, $mybb, $charactername, $playername, $playername_fid;

    $playername_field = $mybb->settings['playerdirectory_playername'];

    $lang->load("playerdirectory");

    if($plugin_array['user_activity']['activity'] == "playerdirectory") {
        $plugin_array['location_name'] = $lang->playerdirectory_online_location_playerdirectory;
    }
    
    if($plugin_array['user_activity']['activity'] == "playerstatistic") {

        if (is_numeric($playername_field)) {
            $playername_fid = "fid".$playername_field;
            $playername = $db->fetch_field($db->simple_select("userfields", $playername_fid, "ufid = '".$plugin_array['user_activity']['uid']."'"), $playername_fid);
        } else {
            $playername_fid = $db->fetch_field($db->simple_select("application_ucp_fields", "id", "fieldname = '".$playername_field."'"), "id");
            $playername = $db->fetch_field($db->simple_select("application_ucp_userfields", "value", "uid = '".$plugin_array['user_activity']['uid']."' AND fieldid = '".$playername_fid."'"), "value");
        }

        if(empty($playername)) {
            $playername = $lang->playerdirectory_playername_none;
        } else {
            $playername = $playername;
        }
		
		$plugin_array['location_name'] = $lang->sprintf($lang->playerdirectory_online_location_playerstatistic, $plugin_array['user_activity']['uid'], $playername);
	}

    if($plugin_array['user_activity']['activity'] == "characterstatistic") {

        $charactername = $db->fetch_field($db->simple_select("users", "username", "uid = '".$plugin_array['user_activity']['uid']."'"), "username");
		
		$plugin_array['location_name'] = $lang->sprintf($lang->playerdirectory_online_location_characterstatistic, $plugin_array['user_activity']['uid'], $charactername);
	}
    
    return $plugin_array;    
}

// USERCP - EINSTELLUNGEN
function playerdirectory_usercp_options() {

    global $mybb, $lang, $templates, $playerdirectory_playerstat, $playerdirectory_playerstat_guest, $playerdirectory_characterstat, $playerdirectory_characterstat_guest, $playerdirectory_options, $option_characterstat, $option_characterstat_guest;

    $lang->load("playerdirectory");

    $playerstat_activated = $mybb->settings['playerdirectory_playerstat'];
    $playerstat_activated_guest = $mybb->settings['playerdirectory_playerstat_guest'];
    $characterstat_activated = $mybb->settings['playerdirectory_characterstat'];
    $characterstat_activated_guest = $mybb->settings['playerdirectory_characterstat_guest'];

    // ACCOUNTSWITCHER - HAUPT ID
    $this_user = intval($mybb->user['uid']);
    $as_uid = intval($mybb->user['as_uid']);
    if(empty($as_uid)) {
        $as_uid = $this_user;
    }
    $mainSetting = get_user($as_uid);

    if ($playerstat_activated == 1 || $characterstat_activated == 1) {

        if ($playerstat_activated == 1) {
            
            $nameID = "";
            $checked = "";
            $option_text = "";

            // persönliche Spielerstatistik
            if(isset($mainSetting['playerdirectory_playerstat']) && $mainSetting['playerdirectory_playerstat'] == 1){
                $playerdirectory_playerstat = "checked=\"checked\"";
            } else{
                $playerdirectory_playerstat = "";
            }

            $nameID = "playerdirectory_playerstat";
            $checked = $playerdirectory_playerstat;
            $option_text = $lang->playerdirectory_usercp_options_playerstat;

            eval("\$option_playerstat = \"".$templates->get("playerdirectory_usercp_options_bit")."\";");
    
            // persönliche Spielerstatistik => Gäste
            if ($playerstat_activated_guest == 1) {
                $nameID = "";
                $checked = "";
                $option_text = "";

                if(isset($mainSetting['playerdirectory_playerstat_guest']) && $mainSetting['playerdirectory_playerstat_guest'] == 1){
                    $playerdirectory_playerstat_guest = "checked=\"checked\"";
                } else {
                    $playerdirectory_playerstat_guest = "";
                }

                $nameID = "playerdirectory_playerstat_guest";
                $checked = $playerdirectory_playerstat_guest;
                $option_text = $lang->playerdirectory_usercp_options_playerstat_guest;

                eval("\$option_playerstat_guest = \"".$templates->get("playerdirectory_usercp_options_bit")."\";");
            } else {
                $option_playerstat_guest = "";
                $count_playerstat_guest = "";
            }

            eval("\$playerdirectory_options = \"".$templates->get("playerdirectory_usercp_options")."\";");

        } else {
            $option_playerstat = "";
            $option_playerstat_guest = "";
        }
    
        if ($characterstat_activated == 1) {

            $nameID = "";
            $checked = "";
            $option_text = "";
            
            // persönlichen Charakterstatistiken 
            if(isset($mainSetting['playerdirectory_characterstat']) && $mainSetting['playerdirectory_characterstat'] == 1){
                $playerdirectory_characterstat = "checked=\"checked\"";
            } else {
                $playerdirectory_characterstat = "";
            }

            $nameID = "playerdirectory_characterstat";
            $checked = $playerdirectory_characterstat;
            $option_text = $lang->playerdirectory_usercp_options_characterstat;

            eval("\$option_characterstat = \"".$templates->get("playerdirectory_usercp_options_bit")."\";");
        
            // persönlichen Charakterstatistiken => Gäste
            if ($characterstat_activated_guest == 1) {

                $nameID = "";
                $checked = "";
                $option_text = "";

                if(isset($mainSetting['playerdirectory_characterstat_guest']) && $mainSetting['playerdirectory_characterstat_guest'] == 1){
                    $playerdirectory_characterstat_guest = "checked=\"checked\"";
                } else {
                    $playerdirectory_characterstat_guest = "";
                }

                $nameID = "playerdirectory_characterstat_guest";
                $checked = $playerdirectory_characterstat_guest;
                $option_text = $lang->playerdirectory_usercp_options_characterstat_guest;

                eval("\$option_characterstat_guest = \"".$templates->get("playerdirectory_usercp_options_bit")."\";");
            } else {
                $option_characterstat_guest = "";
            }
        } else {
            $option_characterstat = "";
            $option_characterstat_guest = "";
        }

        eval("\$playerdirectory_options = \"".$templates->get("playerdirectory_usercp_options")."\";");
    } else {
        $playerdirectory_options = "";
    }
}
// USERCP - EINSTELLUNGEN => SPEICHERN
function playerdirectory_usercp_do_options() {
    global $mybb, $db;

    $playerdirectory_playerstat = $mybb->get_input('playerdirectory_playerstat', MyBB::INPUT_INT);
    $playerdirectory_playerstat_guest = $mybb->get_input('playerdirectory_playerstat_guest', MyBB::INPUT_INT);
    $playerdirectory_characterstat = $mybb->get_input('playerdirectory_characterstat', MyBB::INPUT_INT);
    $playerdirectory_characterstat_guest = $mybb->get_input('playerdirectory_characterstat_guest', MyBB::INPUT_INT);
    
    // ACCOUNTSWITCHER - HAUPT ID
    $this_user = intval($mybb->user['uid']);
    $as_uid = intval($mybb->user['as_uid']);
    if(empty($as_uid)) {
        $as_uid = $this_user;
    }

    // Für alle Accounts übernehmen
    $db->query ("UPDATE ".TABLE_PREFIX."users SET playerdirectory_playerstat = ".$playerdirectory_playerstat." WHERE uid = ".$as_uid." OR as_uid = ".$as_uid."");
    $db->query ("UPDATE ".TABLE_PREFIX."users SET playerdirectory_playerstat_guest = ".$playerdirectory_playerstat_guest." WHERE uid = ".$as_uid." OR as_uid = ".$as_uid."");
    $db->query ("UPDATE ".TABLE_PREFIX."users SET playerdirectory_characterstat = ".$playerdirectory_characterstat." WHERE uid = ".$as_uid." OR as_uid = ".$as_uid."");
    $db->query ("UPDATE ".TABLE_PREFIX."users SET playerdirectory_characterstat_guest = ".$playerdirectory_characterstat_guest." WHERE uid = ".$as_uid." OR as_uid = ".$as_uid."");
} 

// HILFSFUNKTION MERDIAN - DURCHSCHNITTLICHES ALTER
function merdian($array = array()) {
	$count = count($array);
	
    if( $count <= 0 ) {
		return false;
	}
	
    sort($array, SORT_NUMERIC);

	if( $count % 2 == 0 ) {
		return ( $array[floor($count/2)-1] + $array[floor($count/2)] ) / 2;
	} else {
		return $array[$count/2];
	}
}

// Variabel Bau Funktion - danke Katja <3
function playerdirectory_build_statistics($userids_string){

    global $db, $templates, $labels_chart, $data_chart, $colors, $backgroundColor, $statistic_typ, $chartname, $lang;
  
    // Rückgabe als Array, also einzelne Variablen die sich ansprechen lassen
    $array = array();
      
    // erst einmal Indientifikatoren bekommen
    $allidentification_query = $db->query("SELECT identification FROM ".TABLE_PREFIX."playerdirectory_statistics");
    
    $all_identification = [];
    while($allidentification = $db->fetch_array($allidentification_query)) {
        $all_identification[] = $allidentification['identification'];
    }
      
    foreach ($all_identification as $identification) {

        // Variabel aufrufen => $var['identification']
        $arraylabel = $identification;

        // Infos ziehen von der Statistik
        $statistic_query = $db->query("SELECT * FROM ".TABLE_PREFIX."playerdirectory_statistics
        WHERE identification = '".$identification."'
        ");

        $normal_bit = "";
        while($stat = $db->fetch_array($statistic_query)) {

            // LEER LAUFEN LASSEN
            $psid = "";
            $name = "";
            $statisticname = "";
            $type = "";
            $field = "";
            $ignor_option = "";
            $usergroups = "";
            $group_option = "";
            $colors = "";
            $custom_properties = "";
            $legend = "";
            $statistic_typ = "";
            $backgroundColor = "";
            $chartname = "";
            $maxCount = "";

            // MIT INFOS FÜLLEN
            $psid = $stat['psid'];
            $name = $stat['name'];
            $statisticname = $lang->sprintf($lang->playerdirectory_playerstat_ownstat_name, $name);
            $type = $stat['type'];
            $field = $stat['field'];
            $ignor_option = $stat['ignor_option'];
            $usergroups = $stat['usergroups'];
            $group_option = $stat['group_option'];
            $colors = $stat['colors'];
            $custom_properties = $stat['custom_properties'];
            $legend = $stat['legend'];
            $chartname = $identification."Chart";

            // Daten ermitteln
            // Profilfeld/Steckbrieffeld
            $data_options = "";
            if (!empty($field)) {

                // wenn Zahl => klassisches Profilfeld
                if (is_numeric($field)) {

                    // Auswahlmöglichkeiten vom Feld
                    $options = $db->fetch_field($db->simple_select("profilefields", "type", "fid = '".$field."'"), "type");
                    // in Array splitten
                    $expoptions = explode("\n", $options);
                    $fieldtyp = $expoptions['0'];
                    // Typ löschen (select, multiselect)
                    unset($expoptions['0']);

                    // gewünschte Optionen rauslöschen
                    if(!empty($ignor_option)) {
                        $ignor_option = str_replace(", ", ",", $ignor_option);
                        $ignor_option = explode (",", $ignor_option);

                        foreach ($ignor_option as $option) {
                            unset($expoptions[$option]);
                        }
                    }

                    // Array mit den Möglichkeiten
                    $data_options = [];
                    foreach ($expoptions as $option) {

                        if ($fieldtyp != "multiselect" && $fieldtyp != "checkbox") {
                            // Zählen wie viel von den Charakteren diesen Wert angegeben haben
                            $userdata_query = $db->query("SELECT * FROM ".TABLE_PREFIX."userfields
                            WHERE ufid IN (".$userids_string.")
                            AND fid".$field." = '".$option."'
                            ");
                        } else {
                            // Zählen wie viel von den Charakteren diesen Wert angegeben haben
                            $userdata_query = $db->query("SELECT * FROM ".TABLE_PREFIX."userfields
                            WHERE ufid IN (".$userids_string.")
                            AND (concat('\n',fid".$field.",'\n') LIKE '%\n".$option."\n%')
                            ");
                        }
                        $count = $db->num_rows($userdata_query);

                        $data_options[$option] = $count;
                    }

                } 
                // Katjas Steckbriefplugin
                else {
                    // Auswahlmöglichkeiten vom Feld
                    $options = $db->fetch_field($db->simple_select("application_ucp_fields", "options", "fieldname = '".$field."'"), "options");
                    $fieldtyp = $db->fetch_field($db->simple_select("application_ucp_fields", "fieldtyp", "fieldname = '".$field."'"), "fieldtyp");
                    // in Array splitten
                    $expoptions = str_replace(", ", ",", $options);
                    $expoptions = explode (",", $expoptions);

                    // gewünschte Optionen rauslöschen
                    if(!empty($ignor_option)) {
                        $ignor_option = str_replace(", ", ",", $ignor_option);
                        $ignor_option = explode (",", $ignor_option);

                        foreach ($ignor_option as $option) {
                            $option_index = $option-1;
                            unset($expoptions[$option_index]);
                        }
                    }

                    // Array mit den Möglichkeiten
                    $data_options = [];
                    foreach ($expoptions as $option) {

                        $fieldid = $db->fetch_field($db->simple_select("application_ucp_fields", "id", "fieldname = '".$field."'"), "id");

                        if ($fieldtyp != "select_multiple" && $fieldtyp != "checkbox") {
                            // Zählen wie viel von den Charakteren diesen Wert angegeben haben
                            $userdata_query = $db->query("SELECT * FROM ".TABLE_PREFIX."application_ucp_userfields
                            WHERE uid IN (".$userids_string.")
                            AND fieldid = '".$fieldid."'
                            AND value = '".$option."'
                            ");
                        } else {
                            // Zählen wie viel von den Charakteren diesen Wert angegeben haben
                            $userdata_query = $db->query("SELECT * FROM ".TABLE_PREFIX."application_ucp_userfields
                            WHERE uid IN (".$userids_string.")
                            AND fieldid = '".$fieldid."'
                            AND (concat(',',value,',') LIKE '%,".$option.",%')
                            ");
                        }
                        $count = $db->num_rows($userdata_query);

                        $data_options[$option] = $count;
                    }
                }
            }
            // Benutzergruppen
            else {

                // Usergruppen Namen
                $usergroups = explode(",", $usergroups);
                $data_options = [];
                foreach($usergroups as $usergroup) {	

                    // nur primär
                    if ($group_option == 1) {
                        $groupoption_sql = "AND usergroup = '".$usergroup."'";
                    } 
                    // nur sekundär
                    else if ($group_option == 2) {
                        $groupoption_sql = "AND (concat(',',additionalgroups,',') LIKE '%,".$usergroup.",%')";
                    }
                    // beides
                    else {
                        $groupoption_sql = "AND (usergroup = '".$usergroup."' OR (concat(',',additionalgroups,',') LIKE '%,".$usergroup.",%'))";
                    }

                    // Zählen wie viel von den Charakteren in dieser Gruppe sind 
                    $userdata_query = $db->query("SELECT * FROM ".TABLE_PREFIX."users
                    WHERE uid IN (".$userids_string.")
                    ".$groupoption_sql."
                    ");
                    $count = $db->num_rows($userdata_query);

                    $data_options[$usergroup] = $count;
                }
            }

            $labels_chart = "";
            $data_chart = "";
            foreach ($data_options as $fieldname => $fieldcount) {
                // Gruppennamen
                if (!empty($usergroups)) {
                    $fieldname = $db->fetch_field($db->simple_select("usergroups", "title", "gid = '".$fieldname."'"), "title");
                } else {
                    $fieldname = $fieldname;
                }
                // Chart 
                $labels_chart .= "'".$fieldname."', ";
                $data_chart .= "'".$fieldcount."', ";

                eval("\$normal_bit .= \"".$templates->get("playerdirectory_playerstat_ownstat_bit")."\";");
            }

            // höchster Wert
            $maxCount = max($data_options)+30; 

            // Farben
            if (!empty($colors)) {

                // CSS Variables
                if ($custom_properties == 1) {

                    $colors_array = explode(",", $colors);

                    $propertyValue = "";
                    $colors = "[";
                    foreach ($colors_array as $color) {
                        $bodytag = str_replace("var(", "", $color);
                        $bodytag = str_replace(")", "", $bodytag);

                        $colorname = str_replace("--", "", $bodytag);

                        $colors .= "".$colorname.",";
                        $propertyValue .= "var ".$colorname." = style.getPropertyValue('".$bodytag."');";
                    }
                    $colors = substr($colors, 0, -1);
                    $colors .= "]";

                } else {
                    $colors_array = explode(",", $colors);
                    $colors = "[";
                    foreach ($colors_array as $color) {
                        $colors .= "'" . $color . "',";
                    }
                    $colors = substr($colors, 0, -1);
                    $colors .= "]";

                    $propertyValue = "";
                }
            } else {
                $colors = "";
                $propertyValue = "";
            }

            // Legende
            if ($legend == 1) {
                $legend = "true";
            } else {
                $legend = "false";
            }

            // Welcher Typ
            // Balken
            if ($type == 1) {
                // Option
                $labels_chart = "[".$labels_chart."]";
                // Anzahl
                $data_chart = "[".$data_chart."]";
                // Farben
                $backgroundColor = $colors;
                eval("\$statistic_typ .= \"".$templates->get("playerdirectory_playerstat_ownstat_bar")."\";");
            } 
            // Kreis
            else if ($type == 2) {
                // Option
                $labels_chart = "[".$labels_chart."]";
                // Anzahl
                $data_chart = "[".$data_chart."]";
                // Farben
                $backgroundColor = $colors;
                eval("\$statistic_typ .= \"".$templates->get("playerdirectory_playerstat_ownstat_pie")."\";");
            }
            // Wort/Zahl
            else {
                $ownstat_bit = "<div class=\"playerdirectory_playerstat_ownstat_bit\">".$normal_bit."</div>";

                eval("\$statistic_typ .= \"".$templates->get("playerdirectory_playerstat_ownstat")."\";");
            }
        }

        $array[$arraylabel] = $statistic_typ;  
    }
    return $array;  
}
