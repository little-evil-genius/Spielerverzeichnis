# Spielerverzeichnis & Statistiken
Dieses Plugin erstellt eine Übersicht von allen Usern mit ihren Charakteren. Zusätzlich wird für jeden Spieler und Charakter eine persönliche Statistik erstellt.<br>
<br>
<b>HINWEIS:</b><br>
Das Plugin ist kompatibel mit den klassischen Profilfeldern von MyBB und/oder dem <a href="https://github.com/katjalennartz/application_ucp">Steckbrief-Plugin von Risuena</a>.<br>
Auch ist das Plugin mit verschiedenen Inplaytracker/Szenentracker kompatibel: mit dem <a href="https://github.com/its-sparks-fly/Inplaytracker-2.0">Inplaytracker 2.0 von sparks fly</a>, dem Nachfolger <a href="https://github.com/ItsSparksFly/mybb-inplaytracker">Inplaytracker 3.0 von sparks fly</a> und dem <a href="https://github.com/katjalennartz/scenetracker">Szenentracker von Risuena</a>.<br>
Genauso kann auch das Listen-Menü angezeigt werden, wenn man das <a href="https://github.com/ItsSparksFly/mybb-lists">Automatische Listen-Plugin von sparks fly</a> verwendet. Beides muss nur vorher eingestellt werden.

# Vorrausetzung
- Der <a href="https://www.mybb.de/erweiterungen/18x/plugins-verschiedenes/enhanced-account-switcher/" target="_blank">Accountswitcher</a> von doylecc <b>muss</b> installiert sein. <br>
- Für eine Ausgabe von einem zufälligen Inplayzitat wird das <a href="https://github.com/its-sparks-fly/Inplayzitate-2.0"> Inplayzitate 2.0</a> oder <a href="https://github.com/ItsSparksFly/mybb-inplayquotes">Inplayzitate 3.0</a> Plugin von sparks fly verwendet. Wenn man diese Option nicht möchte, muss keins der beiden Plugins installiert sein.

# Datenbank-Änderungen
### hinzugefügte Tabelle:
* PRÄFIX_playerdirectory_statistics

### hinzugefügte Spalten in der DB-Tabelle PRÄFIX_users:
* playerdirectory_playerstat
* playerdirectory_playerstat_guest
* playerdirectory_characterstat
* playerdirectory_characterstat_guest

# Neue Sprachdateien
- deutsch_du/admin/playerdirectory.lang.php
- deutsch_du/playerdirectory.lang.php

# Einstellungen - Spielerverzeichnis und Statistiken
- Spielerverzeichnis aktivieren
- Gästeberechtigung
- Spieleranzahl pro Seite
- Ausgeschlossene Accounts
- Spieler-Statistik aktivieren
- Gästeberechtigung
- Charakter-Statistik aktivieren
- Gästeberechtigung
- Profilfeldsystem
- Spielername
- Standard-Avatar
- Avatar verstecken
- Geburtstage der Charaktere
- Geburtstagsfeld
- Letzter Inplaytag
- Feld fürs Alter
- Inplaytracker
- letzte 12 Monate-Statistik
- Szenenanzahl pro Charakter - Statistik
- Szenenanzahl-Statistik - Legende
- Inplaypostanzahl pro Charakter - Statistik
- Inplaypostanzahl-Statistik - Legende
- Farben für die Säulen/Kreisflächen
- Inplayzitate
- Listen PHP
- Listen Menü Template
<br><br>
<b>HINWEIS:</b><br>
Einige Einstellungen sind abhängig voneinander und werden nur angezeigt, wenn bei einer anderen Einstellung Option 1 oder 2 ausgewählt wurde. 

# Neue Template-Gruppe innerhalb der Design-Templates
- Spielerverzeichnis und Statistiken

# Neue Templates (nicht global!)
- playerdirectory_characterstat
- playerdirectory_characterstat_inplayquote
- playerdirectory_directory
- playerdirectory_directory_characters
- playerdirectory_directory_user
- playerdirectory_menu_link
- playerdirectory_notice_banner
- playerdirectory_playerstat
- playerdirectory_playerstat_characters
- playerdirectory_playerstat_inplayquote
- playerdirectory_playerstat_ownstat
- playerdirectory_playerstat_ownstat_bar
- playerdirectory_playerstat_ownstat_bit
- playerdirectory_playerstat_ownstat_pie
- playerdirectory_postactivity_months
- playerdirectory_postactivity_months_bit
- playerdirectory_postactivity_months_chart
- playerdirectory_postactivity_perChara
- playerdirectory_postactivity_perChara_poststat
- playerdirectory_postactivity_perChara_poststat_bit
- playerdirectory_postactivity_perChara_poststat_chart_bar
- playerdirectory_postactivity_perChara_poststat_chart_pie
- playerdirectory_postactivity_perChara_scenestat
- playerdirectory_postactivity_perChara_scenestat_bit
- playerdirectory_postactivity_perChara_scenestat_chart_bar
- playerdirectory_postactivity_perChara_scenestat_chart_pie
- playerdirectory_usercp_options
- playerdirectory_usercp_options_bit<br><br>
<b>HINWEIS:</b><br>
Alle Templates wurden größtenteils ohne Tabellen-Struktur gecodet. Das Layout wurde auf ein MyBB Default Design angepasst.

# Template Änderungen - neue Variablen
- header - {$menu_playerdirectory}
- usercp_options - {$playerdirectory_options}

# Neues CSS - playerdirectory.css
Es wird automatisch in jedes bestehende und neue Design hinzugefügt. Man sollte es einfach einmal abspeichern, bevor man dies im Board mit der Untersuchungsfunktion bearbeiten will, da es dann passieren kann, dass das CSS für dieses Plugin in einen anderen Stylesheet gerutscht ist, obwohl es im ACP richtig ist.

# Links
- euerforum.de/admin/index.php?module=user-playerdirectory
- euerforum.de/misc.php?action=playerdirectory
- euerforum.de/misc.php?action=playerstatistic&uid=X
- euerforum.de/misc.php?action=characterstatistic&uid=X
