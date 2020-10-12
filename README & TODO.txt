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
- project.json DIST für Piano / Git / beides
- createAudio paralellisieren
- Parts exportieren
- Anmelde und Stop-Symbol mit überlagerter Graphik ggf. auch maskieren (Anmeldebutton rund)
- createSkript was beide Skripte startet
- Skript 3. Output dir setzen für kürzere Pfade
- Audioerstellung außerhalb von Cloud (wegen Einfügen und Löschen von Dateien)

Mögliche Probleme
- Count-in-Datei (z.B. 85_4_4.mp3) existiert noch nicht