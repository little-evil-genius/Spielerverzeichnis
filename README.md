# Spielerverzeichnis & Statistiken
Dieses Plugin erstellt eine Übersicht der Spieler und Spielerinnen und ihren zugehörigen Accounts. Eine sogenannte Wer-ist-Wer-Liste. Neben dieser Liste gibt es persönliche Spieler-Statistiken und Charakter-Statistiken. Auf diesen Statistik-Seiten werden die unterschiedlichsten Informationen und Statistikwerte zum Spieler bzw. zum Charakter ausgegeben.<br>
Neben den vorgefertigten Statistiken und Diagrammen können im ACP auch eigene Statistiken für die Spieler-Statistik-Seite erstellt werden. Zum Beispiel eine Statistik, wie viele männliche, weibliche oder diverse Charaktere ein User besitzt. Genauere und ausführliche Informationen zu den Einstellungen, Möglichkeiten und eigenen Statistiken findet man im [Wiki](https://github.com/little-evil-genius/Spielerverzeichnis/wiki).<br>
<br>
Wem dieses Plugin zu komplex und umfangreich ist, hat die Möglichkeit das [Wer ist wer? 1.1 - Plugin](https://storming-gates.de/showthread.php?tid=19354&pid=135895#pid135895) von [melancholia](https://storming-gates.de/member.php?action=profile&uid=112) zu verwenden. Und/Oder das Tutorial [Spielerübersicht mit Statistiken - Storming Gates](https://storming-gates.de/showthread.php?tid=1015591&pid=480982#pid480982) | [Spielerübersicht mit Statistiken - Epic](https://epic.quodvide.de/showthread.php?tid=1109&pid=5112#pid5112) von [sparks fly](https://epic.quodvide.de/member.php?action=profile&uid=10) einzubauen. Wer Interesse an Diagrammen hat, der kann, wie ich auch, die Diagramme mit Hilfe der open source Lösung [Chart.js](https://www.chartjs.org/) bauen oder sich das Tutorial [Charts mit Hilfe von Googles Developer erstellen](https://storming-gates.de/showthread.php?tid=1017740&pid=495004#pid495004) von [Ales](https://storming-gates.de/member.php?action=profile&uid=279) anschauen.<br>
<br>
<b>HINWEIS:</b><br>
Das Plugin ist kompatibel mit den klassischen Profilfeldern von MyBB und/oder dem <a href="https://github.com/katjalennartz/application_ucp">Steckbrief-Plugin von Risuena</a>.<br>
Auch ist das Plugin mit verschiedenen Inplaytrackern/Szenentrackern kompatibel: mit dem <a href="https://github.com/its-sparks-fly/Inplaytracker-2.0">Inplaytracker 2.0 von sparks fly</a>, dem Nachfolger <a href="https://github.com/ItsSparksFly/mybb-inplaytracker">Inplaytracker 3.0 von sparks fly</a>, dem <a href="https://github.com/little-evil-genius/Inplayszenen-Manager">Inplayszenen-Manager von mir (little.evil.genius)</a>, dem <a href="https://storming-gates.de/showthread.php?tid=1023729&pid=537783#pid537783">Szenentracker von Risuena</a>, dem <a href="https://github.com/Ales12/inplaytracker">Inplaytracker 1.0 von Ales</a> und dem Nachfolger <a href="https://github.com/Ales12/inplaytracker-2.0">Inplaytracker 2.0 von Ales</a>.<br>
Genauso kann auch das Listen-Menü angezeigt werden, wenn man das <a href="https://github.com/ItsSparksFly/mybb-lists">Automatische Listen-Plugin von sparks fly</a> verwendet. Beides muss nur vorher eingestellt werden.

# Vorrausetzung
- Das ACP Modul <a href="https://github.com/little-evil-genius/rpgstuff_modul" target="_blank">RPG Stuff</a> <b>muss</b> vorhanden sein.
- Der <a href="https://www.mybb.de/erweiterungen/18x/plugins-verschiedenes/enhanced-account-switcher/" target="_blank">Accountswitcher</a> von doylecc <b>muss</b> installiert sein. <br>
- Für eine Ausgabe von einem zufälligen Inplayzitat wird das <a href="https://github.com/its-sparks-fly/Inplayzitate-2.0"> Inplayzitate 2.0</a> oder <a href="https://github.com/ItsSparksFly/mybb-inplayquotes">Inplayzitate 3.0</a> Plugin von sparks fly oder <a href="https://github.com/Ales12/inplayquotes">Inplayzitate</a> Plugin von Ales oder die <a href="https://github.com/little-evil-genius/inplayzitate">Inplayzitate</a> von mir (little.evil.genius) verwendet. Wenn man diese Option nicht möchte, muss keines der Plugins installiert sein.

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
Es wird automatisch in jedes bestehende und neue Design hinzugefügt. Man sollte es einfach einmal abspeichern - auch im Default. Nach einem MyBB Upgrade fehlt der Stylesheets im Masterstyle? Im ACP Modul "RPG Erweiterungen" befindet sich der Menüpunkt "Stylesheets überprüfen" und kann von hinterlegten Plugins den Stylesheet wieder hinzufügen.

# Links
- euerforum.de/admin/index.php?module=rpgstuff-playerdirectory
- euerforum.de/misc.php?action=playerdirectory
- euerforum.de/misc.php?action=playerstatistic&uid=X
- euerforum.de/misc.php?action=characterstatistic&uid=X

# Demo Screenshots
### Spielerverzeichnis
<img src="https://stormborn.at/plugins/spielerverzeichnis_main.png">

### Spielerverzeichnis/Statistik
<img src="https://stormborn.at/plugins/spielerverzeichnis_spieler_inplay.png">
<br><br>
<img src="https://stormborn.at/plugins/spielerverzeichnis_spieler_character.png">

### die letzten 12 Monate
<img src="https://stormborn.at/plugins/spielerverzeichnis_spieler_12monts_bar.png">
<br><br>
<img src="https://stormborn.at/plugins/spielerverzeichnis_spieler_12monts.png">

### Szenen/Inplaypost pro Charakter
<img src="https://stormborn.at/plugins/spielerverzeichnis_spieler_perChara_pie.png">

<img src="https://stormborn.at/plugins/spielerverzeichnis_spieler_perChara_bar.png">

<img src="https://stormborn.at/plugins/spielerverzeichnis_spieler_perChara.png">

<img src="https://stormborn.at/plugins/spielerverzeichnis_spieler_perChara_beides.png">

### Charakterverzeichnis/Charakterstatistik
<img src="https://stormborn.at/plugins/spielerverzeichnis_character.png">

### Einstellungen & Hinweis
<img src="https://stormborn.at/plugins/spielerverzeichnis_ucp.png">

<img src="https://stormborn.at/plugins/spielerverzeichnis_notice.png">

## Eigene Statistiken
### Übersicht
<img src="https://stormborn.at/plugins/spielerverzeichnis_spieler_own_acp.png">

### Hinzufügen
<img src="https://stormborn.at/plugins/spielerverzeichnis_spieler_own_acp_add.png">

### Spielerstatistik
<img src="https://stormborn.at/plugins/spielerverzeichnis_spieler_own.png">
