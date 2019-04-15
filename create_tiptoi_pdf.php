<?php

use Mpdf\Mpdf;
require_once __DIR__ . '/vendor/autoload.php';

//Nach wie vielen Takten startet die neue Uebung (fuer Split der mp3)?
$bar_count = 8;

//Projektname
$project_name = "pick-vol-1";

//In Projektordner wechseln
$dir = "C:\Users\Martin\Desktop\Google Drive\Martin\TipToi Tool\david-maya";
$project_dir = $dir . "/" . $project_name;
chdir($project_dir);

//Ueber mp3s gehen, die gesplittet werden muessen
foreach (glob("full_*.mp3") as $full_file) {

    //full_080 -> 3-stelliges Tempo extrahieren
    preg_match_all('/\d{3}/', $full_file, $matches);
    $tempo = $matches[0][0];

    //Berechnen wo du files augesplittet werden muessen
    $split_time = (60 / (int) $tempo) * 4 * $bar_count;

    //per ffmpeg full-Datei in einzelne Teile zerlegen
    $out = shell_exec('ffmpeg -hide_banner -loglevel panic -i ' . $full_file . ' -f segment -segment_time ' . $split_time . ' -c copy split_t_%0d_' . $tempo . '.mp3');
}

//JSON-Config laden. Hier ist neben project-id und Ueberschrift auch hinterlegt, welche Files es gibt und wie sie optisch strukturiert sind
$config = json_decode(file_get_contents(__DIR__ . "/config/" . $project_name . ".json"), true);
$product_id = $config["product-id"];

//Yaml-Datei erzeugen
$yaml_file = $project_name . ".yaml";
$fh = fopen($project_dir . "/" . $yaml_file, "w");

//Product-ID eintragen
fwrite($fh, "product-id: " . $product_id . "\n\n");

//Sound bei Anmeldung ist fix
fwrite($fh, "welcome: start\n\n");

//Scripts = Audiofiles, die gespielt werden
fwrite($fh, "scripts:\n");

//Stop-Script = stumme Audio-Datei, um Playback zu stoppen
fwrite($fh, "  stop: P(stop)\n");

//Count-in-Dateien und start / stop.mp3 in Projekt-Ordner kopieren (wird fuer GME-Erstellung benoetigt)
foreach (glob("../*.mp3") as $file) {
    copy($file, $project_name . "/" . $file);
}

//Ueber Rows (=Uebungen) und deren Files (=Geschwindigkeiten) der config gehen
foreach ($config["rows"] as $row) {
    foreach ($row["files"] as $file) {

        //Abspielcode in YAML-Datei als Script hinterlegen
        fwrite($fh, "  t_" . $row["id"] . "_" . $file["id"] . ": P(t_" . $row["id"] . "_" . $file["id"] . ")\n");

        //Count-in-Datei und Split-Datei einer Uebung zu einer mp3 zusammenfuehren
        shell_exec('copy /b count_in_' . $file["id"] . '.mp3+split_t_' . ($row["id"] - 1) . "_" . $file["id"] . '.mp3 t_' . $row["id"] . '_' . $file["id"] . '.mp3');
    }
}
fclose($fh);

//GME-Datei erstellen
shell_exec('tttool assemble ' . $yaml_file);

//OID-Codes erstellen
shell_exec('tttool oid-codes ' . $yaml_file . ' --pixel-size 4 --code-dim 20');

//PDF-Datei vorbereiten
$mpdf = new Mpdf();
$mpdf->img_dpi = 1200;

//Ueberschrift oben
$html = "<h1>" . $config["header"] . "</h1>";

//Anmelde-Symbol
$html .= "<img src='oid-" . $product_id . "-START.png' />";

//Stop-Symbol
$html .= "<div><h2>Stop</h2><img src='oid-" . $product_id . "-stop.png' /></div>";

//Ueber Rows (=Uebungen) gehen
foreach ($config["rows"] as $row) {

    //Ueberschrift ("Uebung 1")
    $html .= "<div><h2>" . $row["label"] . "</h2>";

    //Benennung und OID-Code der Uebung als th und td einer Tabelle
    $th_row = "";
    $td_row = "";

    //Ueber files (=Geschwindigkeiten) einer Uebung gehen
    foreach ($row["files"] as $file) {

        //Name (=Tempo 80)
        $th_row .= "<th>" . $file["name"] . "</th>";

        //OID-Code als Bild
        $td_row .= "<td><img src='oid-" . $product_id . "-t_" . $row["id"] . "_" . $file["id"] . ".png' /></td>";
    }

    //fuer diese Uebung einer Tabelle anlegen
    $html .= "<table><tr>" . $th_row . "</tr><tr>" . $td_row . "</tr></table></div>";
}

//CSS-Datei laden
$stylesheet = file_get_contents(__DIR__ . '/styles.css');

//CSS und HTML schreiben
$mpdf->WriteHTML($stylesheet, \Mpdf\HTMLParserMode::HEADER_CSS);
$mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);

//Aufraeumen: mp3s fuer scripts entfernen
foreach (glob("t_*.mp3") as $file) {
    unlink($file);
}

//Aufraumen. split mp3s entfernen
foreach (glob("split_*.mp3") as $file) {
    unlink($file);
}

//Aufraeumen: count-in-mp3s entfernen
foreach (glob("count_*.mp3") as $file) {
    unlink($file);
}

//Aufraeumen: oid-pngs entfernen
foreach (glob("oid-*.png") as $file) {
    unlink($file);
}

//Aufrauemen: start / stop.mp3 entfernen
unlink("start.mp3");
unlink("stop.mp3");

//pdf als Datei speichern
$mpdf->Output($project_name . ".pdf");