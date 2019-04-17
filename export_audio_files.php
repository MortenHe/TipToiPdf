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

        //Dateiname fuer temp. musicxml und mscz-Dateien fuer die Audioerzeugung
        $filename_prefix = $project_name . "_" . $tempo;

        //Praefix full wichtig fuer 2. Skript, welches die full-Dateien aufteilt
        $output_filename_prefix = "full_" . $tempo;

        //Audio-Datei erstellen
        createAudioFile($filename_prefix, $output_filename_prefix, $domdoc);
    }
}

//es ist ein Projekt, bei dem nicht gesplittet wird, sondern die ganze Audio-Datei verarbeitet wird
//dafuer ggf. verschiedene Exporte mit gemuteten Spuren
else {

    //Lautstaerke-Tags holen (Klavier 1, Klavier 2, Gitarre, div. Percussions)
    $score_parts = $xpath->query("//score-part/*/volume");

    //Ueber Uebungen (z.B. rechte Hand, linke Hand) gehen
    foreach ($project_config["rows"] as $row) {

        //ID fuer Benennung der files
        $row_id = $row["id"];

        //Zunaechst allen Instrumenten die Lautstaerke 78 geben
        foreach ($score_parts as $score_part) {
            $score_part->nodeValue = 78;
        }

        //Wenn in dieser Uebung ein Instrument gemutet werden soll (z.B. linke Hand gemutet), ueber die Indexe der gemuteten Instrumente gehen
        if (isset($row["mute"])) {
            foreach ($row["mute"] as $mute_idx) {

                //den gewuneschten Part muten -> volume = 0
                $score_parts->item($mute_idx)->nodeValue = 0;
            }
        }

        //Ueber Tempos einer Uebung gehen
        foreach ($row["tempos"] as $tempo) {

            //Tempo-Tag auf passenden Wert setzen (z.B. 60)
            $tempo_tag->setAttribute("tempo", $tempo);

            //Dateiname fuer temp. musicxml und mscz-Dateien fuer die Audioerzeugung
            $filename_prefix = $row_id . "_" . $tempo;

            //Dateiname fuer Audio-Datei (wichtig fuer 2. Skript)
            $output_filename_prefix = $row_id . "_" . $tempo;

            //Audio-Datei erzeugen
            createAudioFile($filename_prefix, $output_filename_prefix, $domdoc);
        }
    }
}

//Audio-Datei erstellen
function createAudioFile($filename_prefix, $output_filename_prefix, $domdoc) {

    //musicxml-Datei mit angepasstem XML (Tempo, ggf. gemutete Instrumente)
    $tempo_musicxml_file = $filename_prefix . ".musicxml";
    $fh = fopen($tempo_musicxml_file, "w");
    fwrite($fh, $domdoc->saveXML());
    fclose($fh);

    //Neu erzeugte musicxml-Datei (z.B. pick-a-pick-vol-1_60.musicxml) zu mscz-Datei konvertieren (z.B. pick-a-pick-vol-1_60.mscz)
    $tempo_mscz_file = $filename_prefix . ".mscz";
    $musicxmal_to_mscz_command = 'MuseScore3.exe "' . $tempo_musicxml_file . '" -o "' . $tempo_mscz_file . '"';
    shell_exec($musicxmal_to_mscz_command);

    //Aus mscz-_Datei (z.B. pick-a-pick-vol-1_60.mscz) eine mp3-Datei erzeugen (full_60.mp3)
    $tempo_mp3_file = $output_filename_prefix . ".mp3";
    $mscz_to_mp3_command = 'MuseScore3.exe "' . $tempo_mscz_file . '" -o "' . $tempo_mp3_file . '"';
    shell_exec($mscz_to_mp3_command);
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