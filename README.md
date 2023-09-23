# Spielerverzeichnis
Dieses Plugin erstellt eine Übersicht von allen Usern mit ihren Charakteren. Zusätzlich wird für jeden Spieler und Charakter eine persönliche Statistik erstellt. Auf Wunsch kann auch eine persönliche Board Statistik angezeigt werden.


# Extra Variabeln
<b>playerdirectory_directory<br></b>
{$allPlayers} - nur die Zahl aller Spieler<br>
{$allCharacters} - nur die Zahl aller Charaktere<br>
{$averagecharacters} - nur die Zahl der durschnittlichen Charakteranzahl<br>
<br>
<b>playerdirectory_directory_user<br></b>
{$playerID} - User-ID vom Hauptcharakter<br>
{$allinplayposts_formatted} - nur die Zahl aller Inplayposts<br>
{$allinplayscenes_formatted} - nur die Zahl aller Szenen<br>
{$allacc['XXX']} - Ausgabe von Profilfelder/Steckbrieffelder/Spalten der Users DB Tabelle vom Hauptcharakter<br>
<br>
<b>playerdirectory_directory_characters<br></b>
{$characterID} - User-ID von dem Charakter<br>
{$regdate} - Registriert seit<br>
{$lastactivity} - Zuletzt online<br>
{$usertitle} - Benutzertitel vom Charakter<br>
{$age} - Alter (automatisch berechnet, wenn der Geburtstag angegeben wurde)<br>
{$charactername} - nur der Charaktername (ohne Link, ohne Formatierung)<br>
{$charactername_link} - Charaktername (als Link, ohne Formatierung)<br>
{$charactername_formated} - Charaktername (als Link + Formatierung der Gruppenfarbe)<br>
{$first_name} & {$last_name} - Vorname & Nachname (+ Zweitname falls vorhanden im Benutzername)<br>
{$allinplayposts_formatted} - nur die Zahl aller Inplayposts<br>
{$allinplayscenes_formatted} - nur die Zahl aller Szenen<br>
{$character['XXX']} - Ausgabe von Profilfelder/Steckbrieffelder/Spalten der Users DB Tabelle<br>
<br>
<br>
<b>playerdirectory_playerstat<br></b>
{$mainID} - User-ID vom Haupt-Charakter<br>
<b>erster Charakter</b><br>
{$firstchara} - nur der Charaktername ohne Reistrierungsdatum (als Link, ohne Formatierung)<br>
{$firstchara_formated} - nur der Charaktername ohne Reistrierungsdatum (als Link, mit Formatierung)<br>
{$firstchara_reg} - Charaktername mit Reistrierungsdatum (als Link, ohne Formatierung)<br>
{$firstchara_formated_reg} - Charaktername mit Reistrierungsdatum (als Link, mit Formatierung)<br>
<b>neuster Charakter</b><br>
{$lastchara} - nur der Charaktername ohne Reistrierungsdatum (als Link, ohne Formatierung)<br>
{$lastchara_formated} - nur der Charaktername ohne Reistrierungsdatum (als Link, mit Formatierung)<br>
{$lastchara_reg} - Charaktername mit Reistrierungsdatum (als Link, ohne Formatierung)<br>
{$lastchara_formated_reg} - Charaktername mit Reistrierungsdatum (als Link, mit Formatierung)<br>
{$lastchara} - nur der Charaktername ohne Reistrierungsdatum (als Link, ohne Formatierung)<br>
<b>heißester Charakter</b><br>
{$hotCharacter} - nur der Charaktername und Postanzahl (als Link, ohne Formatierung)<br>
{$hotCharacter_formated} - nur der Charaktername und Postanzahl (als Link, mit Formatierung)<br>
{$playerstat['XXX']} - Ausgabe von Profilfelder/Steckbrieffelder/Spalten der Users DB Tabelle von Daten vom <b>HAUPT</b>-Account<br>
<br>
<br>
<b>playerdirectory_characterstat<br></b>
{$charaID} - User-ID von dem Charakter<br>
{$playername} - Name vom Spieler<br>
{$usertitle} - Benutzertitel vom Charakter<br>
{$age} - Alter (automatisch berechnet, wenn der Geburtstag angegeben wurde)<br>
{$charactername} - nur der Charaktername (ohne Link, ohne Formatierung)<br>
{$charactername_link} - Charaktername (als Link, ohne Formatierung)<br>
{$charactername_formated} - Charaktername (als Link + Formatierung der Gruppenfarbe)<br>
{$first_name} & {$last_name} - Vorname & Nachname (+ Zweitname falls vorhanden im Benutzername)<br>
{$characterstat['XXX']} - Ausgabe von Profilfelder/Steckbrieffelder/Spalten der Users DB Tabelle<br>
<br>
<br>
<b>playerdirectory_playerstat_inplayquote & playerdirectory_characterstat_inplayquote<br></b>
{$charactername} - nur der Charaktername (ohne Link, ohne Formatierung)<br>
{$charactername_link} - Charaktername (als Link, ohne Formatierung)<br>
{$charactername_formated} - Charaktername (als Link + Formatierung der Gruppenfarbe)<br>
{$first_name} & {$last_name} - Vorname & Nachname (+ Zweitname falls vorhanden im Benutzername)<br>
{$quotes['XXX']} - Ausgabe von Profilfelder/Steckbrieffelder/Spalten der Users DB Tabelle<br>
