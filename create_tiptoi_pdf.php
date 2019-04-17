<?php

use Mpdf\Mpdf;
require_once __DIR__ . '/vendor/autoload.php';

//Projektname (Name des Ordners in dem die mp3 files liegen)
$project_name = "je-veux-str-v2";
//$project_name = "pick-a-pick-vol-1";

//Allgemeine Config mit Pfaden zu Dateien
$config = json_decode(file_get_contents(__DIR__ . "/config/config.json"), true);

//JSON-Config laden. Hier ist neben project-id und Ueberschrift auch hinterlegt, welche Files es gibt und wie sie optisch strukturiert sind
$project_config = json_decode(file_get_contents(__DIR__ . "/config/" . $project_name . ".json"), true);
$product_id = $project_config["product-id"];

//Nach wie vielen Takten startet die neue Uebung (fuer Split der mp3)?
$split_bar_count = $project_config["split-bar-count"];

//In Projektordner wechseln
$project_dir = $config["tiptoi_dir"] . "/" . $project_name;
chdir($project_dir);

//Ueber mp3s gehen, die gesplittet werden muessen (diese Dateien gibt es nur bei Uebungen, die gesplittet werden muessen)
foreach (glob("full_*.mp3") as $full_file) {

    //full_80 / full_100 -> 2-3-stelliges Tempo extrahieren
    preg_match_all('/\d{2,3}/', $full_file, $matches);
    $tempo = (int) $matches[0][0];

    //Berechnen wo die files augesplittet werden muessen
    $split_time = (60 / $tempo) * 4 * $split_bar_count;

    //per ffmpeg full-Datei in einzelne Teile zerlegen
    $out = shell_exec('ffmpeg -hide_banner -loglevel panic -i ' . $full_file . ' -f segment -segment_time ' . $split_time . ' -c copy split_t_%0d_' . $tempo . '.mp3');
}

//HTML fuer PDF-Datei mit Codes erstellen: Ueberschrift oben
$html = "<h1>" . $project_config["header"] . "</h1>";

//Anmelde-Symbol
$html .= "<img src='oid-" . $product_id . "-START.png' />";

//Stop-Symbol
$html .= "<div><h2>Stop</h2><img src='oid-" . $product_id . "-stop.png' /></div>";

//Yaml-Datei erzeugen
$yaml_file = $project_name . ".yaml";
$fh = fopen($yaml_file, "w");

//Product-ID eintragen
fwrite($fh, "product-id: " . $product_id . "\n\n");

//Sound bei Anmeldung ist fix
fwrite($fh, "welcome: start\n\n");

//Scripts = Audiofiles, die gespielt werden
fwrite($fh, "scripts:\n");

//Stop-Script = stumme Audio-Datei, um Playback zu stoppen
fwrite($fh, "  stop: P(stop)\n");

//Count-in-Dateien (werden mit Uebungsdateien zusammengefuhert) und start / stop.mp3 in Projekt-Ordner kopieren (wird fuer GME-Erstellung benoetigt und ist bei jedem Projekt gleich)
foreach (glob("../*.mp3") as $file) {
    copy($file, $project_name . "/" . $file);
}

//Ueber Rows (=Uebungen) des Projekts gehen
foreach ($project_config["rows"] as $row) {

    //Ueberschrift der Uebung ("Uebung 1" vs. "Rechte Hand")
    $html .= "<div><h2>" . $row["label"] . "</h2>";

    //Benennung und OID-Code der Uebung als th und td einer Tabelle
    $th_row = "";
    $td_row = "";

    //Ueber tempos einer Uebung gehen
    foreach ($row["tempos"] as $tempo) {

        //Benennung -> T 80
        $th_row .= "<th>T " . $tempo . "</th>";

        //OID-Code als Bild
        $td_row .= "<td><img src='oid-" . $product_id . "-t_" . $row["id"] . "_" . $tempo . ".png' /></td>";

        //Abspielcode in YAML-Datei als Script hinterlegen
        fwrite($fh, "  t_" . $row["id"] . "_" . $tempo . ": P(t_" . $row["id"] . "_" . $tempo . ")\n");

        //Wenn die Uebung als gesplittete Datei vorliegt (z.B. pick a pick Uebung 1 als split der full-Datei Uebung 1-4)
        if ($split_bar_count > 0) {

            //Count-in-Datei und Split-Datei einer Uebung zu einer mp3 zusammenfuehren
            shell_exec('copy /b count_in_' . $tempo . '.mp3+split_t_' . ($row["id"] - 1) . "_" . $tempo . '.mp3 t_' . $row["id"] . '_' . $tempo . '.mp3');
        }

        //Datei liegt bereits als vollstaendige Datei vor (z.B. je veux -> rechte Hand)
        else {

            //Count-in-Datei und  voll staendige Datei zu einer mp3 zusammenfuehren
            shell_exec('copy /b count_in_' . $tempo . '.mp3+' . $row["id"] . "_" . $tempo . '.mp3 t_' . $row["id"] . '_' . $tempo . '.mp3');
        }
    }

    //fuer diese Uebung eine Tabelle anlegen (Ueberschriften als th + Bilder als td)
    $html .= "<table><tr>" . $th_row . "</tr><tr>" . $td_row . "</tr></table></div>";
}
fclose($fh);

//GME-Datei erstellen
shell_exec('tttool assemble ' . $yaml_file);

//OID-Codes erstellen
shell_exec('tttool oid-codes ' . $yaml_file . ' --pixel-size 5 --code-dim 20');

//PDF-Datei vorbereiten
$mpdf = new Mpdf();
$mpdf->img_dpi = 1200;

//CSS-Datei laden
$stylesheet = file_get_contents(__DIR__ . '/styles.css');

//CSS und HTML schreiben
$mpdf->WriteHTML($stylesheet, \Mpdf\HTMLParserMode::HEADER_CSS);
$mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);

//pdf als Datei speichern
$mpdf->Output($project_name . ".pdf");

//Dateisystem aufraeumen
cleanDir();

//Dateisystem aufraeumen
function cleanDir() {

    //erzeute split mp3s entfernen
    foreach (glob("split_*.mp3") as $file) {
        unlink($file);
    }

    //count-in-mp3s entfernen
    foreach (glob("count_*.mp3") as $file) {
        unlink($file);
    }

    //generierte mp3s (count-in + Uebung) fuer scripts entfernen
    foreach (glob("t_*.mp3") as $file) {
        unlink($file);
    }

    //oid-pngs entfernen
    foreach (glob("oid-*.png") as $file) {
        unlink($file);
    }

    //start / stop.mp3 entfernen
    unlink("start.mp3");
    unlink("stop.mp3");
}