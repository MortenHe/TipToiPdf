INSTALL
- tttool.exe PATH hinzufügen
- MuseScore3.exe in musescore/bin zu PATH hinzufügen
- Musescore Template für Gitarre / Klavier / Gitarre + Klavier bearbeiten und speichern als project-str-v1.mscz, dabei Schlagzeugspur ausblenden per "i"

RUN
- Mit Skript 00 die nächste product-id ermitteln
- Unter /songs Datei project-str-v1.json anlegen
- config.json anpassen (project_name) und Skripte 01 und 02 aufrufen
- mit Skript 03 (Anpassung über random_sheet_config.json) zufällige Noten erstellen

TODO
- README.txt -> README.md + Issues
- project.json DIST für Piano / Git / beides
- createAudio paralellisieren
- Parts exportieren
- Plugin zum Noten / Text löschen / unsichtbar -> Notenübung: Noten malen bzw. Notennamen hinschreiben
- Anmelde und Stop-Symbol mit überlagerter Graphik ggf. auch maskieren (Anmeldebutton rund)
- TTT-Grafiken auf Noten-PDF-Datei drucken
- createSkript was beide Skripte startet
- Skript 3. Output dir setzen für kürzere Pfade

Mögliche Probleme
- Count-in-Datei (z.B. 85_4_4.mp3) existiert noch nicht