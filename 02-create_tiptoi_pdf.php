<?php

//TipToi-GME-Datei erstellen, dazu TipToi-PDF anhand der JSON-Config, sowie Noten-PDF aus mscz-Datei
//count-in-Dateien muessen vorliegen
//Druck bei 1200 dpi

use Mpdf\Mpdf;
require_once __DIR__ . '/vendor/autoload.php';

//Allgemeine Config mit Pfaden zu Dateien und Projektname
$config = json_decode(file_get_contents("config.json"), true);

//Projektname (Name des Unterordners in score_dir dem die Partitur liegt und Name des Unterordners im tiptoi_dir wohin Audio files exportiert werden)
$project_name = $config["project_name"];
echo "Create tt and pdf files for project " . $project_name . "\n";

//JSON-Config laden. Hier ist neben project-id und Ueberschrift auch hinterlegt, welche Files es gibt und wie sie optisch strukturiert sind
$project_config = json_decode(file_get_contents(__DIR__ . "/songs/" . $project_name . ".json"), true);

//Ueber alle configs gehen und sicherstellen, dass keine doppelten product-ids verwendet werden
confirmUniqueProductIDs();

//product-id dieses Produkts auslesen
$product_id = $project_config["product-id"];

//Nach wie vielen Takten startet die neue Uebung (fuer Split der mp3)? Sofern Merkmal gesetzt
$split_bar_count = $project_config["split-bar-count"] ?? -1;

//Wie viele Schlaege soll einzaehlt werden, Standard 4 Viertel
$count_in = $project_config["count_in"] ?? "4_4";

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

//HTML fuer TT-PDF-Datei mit Codes erstellen: Ueberschrift oben
$html = "<table><tr><td class='t_l'><h1>" . $project_config["header"] . "</h1></td>";

//Anmelde-Symbol
$html .= "<td><img src='oid-" . $product_id . "-START.png' /></td>";

//Instrumentenbild (key / git)
foreach ($project_config["instruments"] as $instrument) {
    $html .= "<td class='t_r instrument'><img class='instrument_img' src='../" . $instrument . ".png' /></td>";
}
$html .= "</tr></table>";

//Info anzeigen, falls gesetzt (z.B. andere Taktart, Auftakt, etc.)
$info = $project_config["info"] ?? "";
if ($info) {
    $html .= "<h2>Info</h2>" . $info;
}

//Stop-Symbol
$html .= "<h2>Stop</h2><img src='oid-" . $product_id . "-stop.png' />";

//Yaml-Datei erzeugen
$yaml_file = "tt-" . $project_name . ".yaml";
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

    //Tempos einer Uebung in Tabelle sammeln
    $td_row = "";

    //Ueber tempos einer Uebung gehen
    foreach ($row["tempos"] as $tempo) {

        //Code-Benennung fuer YAML-Datei und code-images
        $code_id = "t_" . $row["id"] . "_" . $tempo;

        //OID-Code als Bild
        $td_row .= "<td><img src='oid-" . $product_id . "-" . $code_id . ".png' /><img class='checkbox' width=20 height=20 src='" . __DIR__ . "/checkbox.svg' /></td>";

        //Abspielcode in YAML-Datei als Script hinterlegen
        fwrite($fh, "  " . $code_id . ": P(" . $code_id . ")\n");

        //Einzaehldatei mit passendem Tempo, Taktart und ggf. Auftakt
        $count_in_file = "count_in_" . $tempo . "_" . $count_in . ".mp3";

        //Wenn die Uebung als gesplittete Datei vorliegt (z.B. pick a pick Uebung 1 als split der full-Datei Uebung 1-4), ansonsten liegt Datei als vollstaendige Datei vor
        $audio_file = $split_bar_count > 0 ? "split_t_" . ($row["id"] - 1) . "_" . $tempo . ".mp3" : $row["id"] . "_" . $tempo . ".mp3";

        //Count-in-Datei und Audio-Datei einer Uebung zu einer mp3 zusammenfuehren
        $merge_command = 'ffmpeg -y -hide_banner -loglevel panic -i "concat:' . $count_in_file . '|' . $audio_file . '" -acodec copy ' . $code_id . '.mp3';
        shell_exec($merge_command);
    }

    //fuer diese Uebung eine Tabelle anlegen
    $html .= "<table><tr>" . $td_row . "</tr></table></div>";
}
fclose($fh);

//GME-Datei und OID-Codes erstellen
shell_exec('tttool assemble ' . $yaml_file);
shell_exec('tttool oid-codes ' . $yaml_file . ' --pixel-size 5 --code-dim 20');

//Ueber Rows (=Uebungen) und Tempos des Projekts gehen und png-Bilder anpassen (Tempo ueber Code legen)
foreach ($project_config["rows"] as $row) {
    foreach ($row["tempos"] as $tempo) {
        $image = "oid-" . $product_id . "-t_" . $row["id"] . "_" . $tempo . ".png";
        addTextToImage($image, $tempo);
    }
}

//PDF-Datei vorbereiten
$mpdf = new Mpdf();
$mpdf->img_dpi = 1200;

//CSS-Datei laden
$stylesheet = file_get_contents(__DIR__ . '/styles.css');

//CSS und HTML schreiben
$mpdf->WriteHTML($stylesheet, \Mpdf\HTMLParserMode::HEADER_CSS);
$mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);

//Footer mit aktuellem Datum
$mpdf->SetHTMLFooter("<small>" . gmdate("d.m.Y", time()) . "</small>");

//pdf als Datei speichern
$mpdf->Output("tt-" . $project_name . ".pdf");

//Aus mscz-Datei eine PDF-Datei erzeugen
$mscz_file = $config["score_dir"] . "/" . $project_name . ".mscz";
$pdf_file = $project_name . ".pdf";
$mscz_to_musicxml_command = 'MuseScore3.exe "' . $mscz_file . '" -o ' . $pdf_file;
shell_exec($mscz_to_musicxml_command);

//Dateisystem aufraeumen
cleanDir();

//Ueber alle configs gehen und sicherstellen, dass keine doppelten product-ids verwendet werden
function confirmUniqueProductIDs() {
    $other_product_ids = [];
    foreach (glob("songs/*.json") as $file) {

        //config eines products auslesen und auf product-id pruefen
        $config_to_check = json_decode(file_get_contents($file), true);

        //Wenn product-id bereits in Liste ist, Programm abbrechen
        $other_product_id = $config_to_check["product-id"];
        if (in_array($other_product_id, $other_product_ids)) {
            exit("product-id " . $other_product_id . " wurde mehrfach vergeben");
        }

        //product-id ist noch nicht in Liste -> sammeln
        else {
            $other_product_ids[] = $other_product_id;
        }
    }
}

//Bilddatei mit Text ueberlagern
function addTextToImage($image, $text, $font_size = 250) {

    //Bildobjekt erstellen
    $png_image = imagecreatefrompng($image);

    //Textfarbe schwarz und Schriftart setzen
    $black = imagecolorallocate($png_image, 0, 0, 0);
    $font_path = __DIR__ . '/arial-outline.ttf';

    //Text einfuegen
    imagettftext($png_image, $font_size, 0, 35, 350, $black, $font_path, $text);

    //Bild speichern und mem leeren
    imagepng($png_image, $image);
    imagedestroy($png_image);
}

//Dateisystem aufraeumen, temp. Dateien loeschen
function cleanDir() {
    foreach (glob("{split_*.mp3,count_*.mp3,t_*.mp3,oid-*.png,start.mp3,stop.mp3,*.yaml}", GLOB_BRACE) as $file) {
        unlink($file);
    }
}