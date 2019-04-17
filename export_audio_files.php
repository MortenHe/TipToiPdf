<?php

//mp3-Dateien in verschiedenen Tempos und Remixen (nur linke, nur rechte Hand, beide Haende) aus mscz erstellen
//Dazu Zwischenschritt musicxml waehlen, um Tempo anpassen zu koennen
//Erstellung der mp3 wieder mit mscz, da bei musicxml der Schlagzeug-Sound nicht korrekt geladen wird

//Projektname (Name des Unterordners in score_dir dem die Partitur liegt und Name des Unterordners im tiptoi_dir wohin Audio files exportiert werden)
$project_name = "je-veux-str-v2";
//$project_name = "pick-a-pick-vol-1";

//Allgemeine Config mit Pfaden zu Dateien
$config = json_decode(file_get_contents(__DIR__ . "/config/config.json"), true);

//Projekt-Config laden. Ermittelt welche Audio-Dateien erzeugt werden muessen
$project_config = json_decode(file_get_contents(__DIR__ . "/config/" . $project_name . ".json"), true);

//Nach wie vielen Takten startet die neue Uebung (fuer Split der mp3)?
$split_bar_count = $project_config["split-bar-count"];

//Projektverzeichnis. Hier werden die temp. musicxml und mscz-Dateien erstellt und die Audio-Dateien exportiert
$project_dir = $config["tiptoi_dir"] . "/" . $project_name;
chdir($project_dir);

//Aus mscz-Datei eine musicxml-Datei erzeugen, damit dort das Tempo angepasst werden kann
$mscz_file = $config["score_dir"] . "/" . $project_name . ".mscz";
$musicxml_file = $project_name . ".musicxml";
$mscz_to_musicxml_command = 'MuseScore3.exe "' . $mscz_file . '" -o "' . $musicxml_file . '"';
shell_exec($mscz_to_musicxml_command);

//Musicxml laden, hier kann man das Tempo aendern
$domdoc = new DOMDocument();
$domdoc->preserveWhiteSpace = false;
$domdoc->loadXML(file_get_contents($musicxml_file));
$xpath = new DOMXPath($domdoc);

//Tempo-Tag auslesen
$tempo_tag = $xpath->query("//sound[@tempo]")->item(0);

//Wenn es ein Projekt mit mehreren Uebungen ist (pick a pick, rhythmus-Uebung)
if ($split_bar_count > 0) {

    //ueber Rows (=Uebungen) und deren tempos in der config gehen und unique Tempos sammeln
    //Fuer jedes Tempo muss eine Audio Datei erstellt werden (selbst wenn das Tempo nur in einer Uebung vorkommt)
    $tempos = [];
    foreach ($project_config["rows"] as $row) {
        foreach ($row["tempos"] as $tempo) {
            if (!in_array($tempo, $tempos)) {
                $tempos[] = $tempo;
            }
        }
    }

    //Ueber sortierte Liste der Tempos gehen
    sort($tempos);
    foreach ($tempos as $tempo) {

        //Tempo-Tag auf passenden Wert setzen (z.B. 60)
        $tempo_tag->setAttribute("tempo", $tempo);

        //Neue musicxml-Datei erzeugen (z.B. pick-a-pick-vol-1_60.musicxml) und den angepassten XML-Inhalt schreiben (mit neuem Tempo)
        $tempo_musicxml_file = $project_name . "_" . $tempo . ".musicxml";
        $fh = fopen($tempo_musicxml_file, "w");
        fwrite($fh, $domdoc->saveXML());
        fclose($fh);

        //Neu erzeugte musicxml-Datei mit passendem Tempo (z.B. pick-a-pick-vol-1_60.musicxml) zu mscz-Datei konvertieren (z.B. pick-a-pick-vol-1_60.mscz)
        $tempo_mscz_file = $project_name . "_" . $tempo . ".mscz";
        $musicxmal_to_mscz_command = 'MuseScore3.exe "' . $tempo_musicxml_file . '" -o "' . $tempo_mscz_file . '"';
        shell_exec($musicxmal_to_mscz_command);

        //Aus mscz-_Datei mit passendem Tempo (z.B. pick-a-pick-vol-1_60.mscz) eine mp3-Datei erzeugen mit passendem Tempo (full_60.mp3) -> Praefix full wichtig fuer 2. Skript
        $tempo_mp3_file = "full_" . $tempo . ".mp3";
        $mscz_to_mp3_command = 'MuseScore3.exe "' . $tempo_mscz_file . '" -o "' . $tempo_mp3_file . '"';
        shell_exec($mscz_to_mp3_command);
    }
}

//es ist ein Projekt, bei dem
else {

}

//tempo musicxml und mscz-Dateien in tiptoi_dir-Subfolder loeschen
cleanDir();

//Dateisystem aufraeumen
function cleanDir() {

    //musicxml-Dateien entfernen
    foreach (glob("*.musicxml") as $file) {
        unlink($file);
    }

    //mscz-Dateien entfernen
    foreach (glob("*.mscz") as $file) {
        unlink($file);
    }
}