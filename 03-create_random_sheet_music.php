<?php

//Zufaellige Noten erstellen als Uebung

//PDF-Tool laden
require_once __DIR__ . '/vendor/autoload.php';

//Config laden welche Modus aktiv ist und welche Noten erlaubt sind
$random_sheet_config = json_decode(file_get_contents("random_sheet_config.json"), true);
$notes_config = json_decode(file_get_contents("notes_stock.json"), true);

//Liste der Noten und Titel auslesen
$mode = $random_sheet_config["mode"];
$notes_stock = $notes_config[$mode];

//Wo werden fertige Dateien abgelegt
$output_dir = "random_sheets";

//Template Datei laden, deren Noten mit random Noten ersetzt werden
$domdoc = new DOMDocument();
$domdoc->loadXML(file_get_contents("random_sheet_template.musicxml"));
$xpath = new DOMXPath($domdoc);

//Ueberschrift fuer Loesungs-pdf setzen
$header = "Notenübung " . ucfirst($mode);
$xpath->query("//credit-words")->item(0)->nodeValue = $header . " (Lösung)";

//Ueber Noten der Partitur gehen und deren Wert + Text aendern
$score_notes = $xpath->query("//note");
foreach ($score_notes as $score_note) {

    //Zufaellige Note waehlen
    shuffle($notes_stock);
    $random_note = $notes_stock[0];

    //Notenwert (A) und Oktvae (2) extrahieren
    $random_step = $random_note[1];
    $random_octave = (int) $random_note[0];

    //Note und Oktave von zuf. Note setzen
    $step_node = $xpath->query("pitch/step", $score_note)->item(0)->nodeValue = $random_step;
    $octave_node = $xpath->query("pitch/octave", $score_note)->item(0);
    $octave_node->nodeValue = $random_octave + 3;

    //Notenhals nach oben oder unten setzen
    $stem_node = $xpath->query("stem", $score_note)->item(0);
    $stem_node->nodeValue = (($random_octave > 1) || $random_octave === 1 && $random_step === "B") ? "down" : "up";

    //# oder b setzen falls gesetzt
    $accidental = "";
    if (isset($random_note[2])) {

        //Alter-Tag erstellen
        $alter_node = $domdoc->createElement("alter");
        $alter_node->nodeValue = $random_note[2] === "#" ? 1 : -1;

        //Alter-Tag an passender Stelle einfuegen
        $pitch_node = $xpath->query("pitch", $score_note)->item(0);
        $pitch_node->insertBefore($alter_node, $octave_node);

        //Accidental-Tag erstellen
        $acc_node = $domdoc->createElement("accidental");
        $accidental = $random_note[2] === "#" ? "sharp" : "flat";
        $acc_node->nodeValue = $accidental;

        //Accidental-Tag an passender Stelle einfuegen
        $score_note->insertBefore($acc_node, $stem_node);
    }

    //Notennamen fuer Loesungs-PDF ermitteln. Sondernfall h vs. b
    $note_name = $random_step === "B" ? "h" : strtolower($random_step);

    //Vorzeichen auswerten
    switch ($accidental) {

    //bei # immer "is" anhaengen
    case "sharp":
        $note_name .= "is";
        break;

    //bei b
    case "flat":
        switch ($note_name) {

        //manche Noten mit "es"
        case "d":case "g":
            $note_name .= "es";
            break;

        //manche Noten nur mit "s"
        case "e":case "a":
            $note_name .= "s";
            break;

        //Sonderfall b
        case "h":
            $note_name = "b";
            break;
        }
        break;
    }

    //Notennamen mit passender Oktave als Notentext setzen
    $xpath->query("lyric/text", $score_note)->item(0)->nodeValue = $note_name . " " . $random_octave;
}

//musicxml-Datei mit random Noten und Notentext erzeugen
$random_sheet_file = "random.musicxml";
$fh = fopen($output_dir . "/" . $random_sheet_file, "w");
fwrite($fh, $domdoc->saveXML());
fclose($fh);

//Aus musicxml-Datei (mit Notentext) eine pdf-Datei erzeugen
$musicxml_to_pdf_command = 'MuseScore3.exe "' . $output_dir . "/" . $random_sheet_file . '" -o ' . $output_dir . "/02.pdf";
shell_exec($musicxml_to_pdf_command);

//Erstellung der Uebungs-PDF anhand des bereits geanderten musicxml
//Ueberschrift anpassen
$xpath->query("//credit-words")->item(0)->nodeValue = $header;

//Ueber Texttags gehen und Text entfernen
$lyric_nodes = $xpath->query("//lyric/text");
foreach ($lyric_nodes as $lyric_node) {
    $lyric_node->nodeValue = "";
}

//Aus musicxml-Datei (ohne Notentext) eine pdf-Datei erzeugen
$fh = fopen($output_dir . "/" . $random_sheet_file, "w");
fwrite($fh, $domdoc->saveXML());
fclose($fh);

//Aus musicxml-Datei (ohne Notentext) eine pdf-Datei erzeugen
$musicxml_to_pdf_command = 'MuseScore3.exe "' . $output_dir . "/" . $random_sheet_file . '" -o ' . $output_dir . "/01.pdf";
shell_exec($musicxml_to_pdf_command);

//Uebungs-PDF und Loesungs-PDF zu einer pdf mergen
$pdf = new \Jurosh\PDFMerge\PDFMerger;
$pdf->addPDF($output_dir . "/01.pdf")->addPDF($output_dir . "/02.pdf");
$pdf->merge("file", $output_dir . "/" . $mode . ".pdf");

//Arbeitsdateien loeschen
unlink($output_dir . "/01.pdf");
unlink($output_dir . "/02.pdf");
unlink($output_dir . "/random.musicxml");