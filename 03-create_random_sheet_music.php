<?php

//Zufaellige Noten erstellen als Uebung

require_once __DIR__ . '/vendor/autoload.php';

//Config laden welche Modus aktiv ist und welche Noten erlaubt sind
$random_sheet_config = json_decode(file_get_contents("random_sheet_config.json"), true);
$notes_config = json_decode(file_get_contents("notes_stock.json"), true);

$program = "MuseScore4.exe";

//read -> Notennamen erkennen, write -> Noten schreiben
$type = "read";
$header_suffix = $type === "read" ? "lesen" : "schreiben";

//violin vs. bass
$clef = "violin";
$template = "random_sheet_template_{$clef}.musicxml";

//Wo werden fertige Dateien abgelegt
$output_dir = "random_sheets";

//Ueber Modi (david, maya,...) gehen und radnom notes erstellen
$modes = $random_sheet_config["modes"];
foreach ($modes as $mode) {
    echo "create random {type} {$clef} sheet music for {$mode}\n";

    //Liste der Noten auslesen
    $notes_stock = $notes_config[$mode][$clef];

    //Template Datei laden, deren Noten mit random Noten ersetzt werden
    $domdoc = new DOMDocument();
    $domdoc->loadXML(file_get_contents($template));
    $xpath = new DOMXPath($domdoc);

    //Ueberschrift fuer Loesungs-pdf setzen
    $header = ucfirst($clef) . "-Schlüssel: Noten {$header_suffix} [" . ucfirst($mode) . "]\n(" . date('d.m.Y') . ")";
    $xpath->query("//credit-words")->item(0)->nodeValue = $header . " - Lösung";

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
        switch ($clef) {
            case "violin":
                $stem_dir = (($random_octave > 1) || $random_octave === 1 && $random_step === "B") ? "down" : "up";
                break;

            case "bass":
                $stem_dir = (($random_octave < 0) || $random_octave === 0 && in_array($random_step, ["C", "D"])) ? "up" : "down";
                break;
        }
        $stem_node->nodeValue = $stem_dir;

        //# oder b setzen falls gesetzt
        $accidental = "";
        if (isset($random_note[2])) {

            //Alter-Tag erstellen und anpassender Stelle einfeugen
            $alter_node = $domdoc->createElement("alter");
            $alter_node->nodeValue = $random_note[2] === "#" ? 1 : -1;
            $pitch_node = $xpath->query("pitch", $score_note)->item(0);
            $pitch_node->insertBefore($alter_node, $octave_node);

            //Accidental-Tag erstellen und an passender Stelle einfuegen
            $acc_node = $domdoc->createElement("accidental");
            $accidental = $random_note[2] === "#" ? "sharp" : "flat";
            $acc_node->nodeValue = $accidental;
            $score_note->insertBefore($acc_node, $stem_node);
        }

        //Notennamen fuer Loesungs-PDF ermitteln. Sondernfall h vs. b
        $note_name = $random_step === "B" ? "h" : strtolower($random_step);
        switch ($accidental) {

                //bei # immer "is" anhaengen
            case "sharp":
                $note_name .= "is";
                break;

                //bei b
            case "flat":
                switch ($note_name) {

                        //manche Noten mit "es"
                    case "d":
                    case "g":
                        $note_name .= "es";
                        break;

                        //manche Noten nur mit "s"
                    case "e":
                    case "a":
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
        $xpath->query("lyric/text", $score_note)->item(0)->nodeValue = $note_name . $random_octave;
    }

    //Aus musicxml-Datei (mit Notentext / Notenkoepfen) eine pdf-Datei erzeugen, Style Datei damit 1. Zeile keine Einrueckung hat
    $random_sheet_file = "random.musicxml";
    $fh = fopen($output_dir . "/" . $random_sheet_file, "w");
    fwrite($fh, $domdoc->saveXML());
    fclose($fh);
    $musicxml_to_pdf_command = "{$program} {$output_dir}/{$random_sheet_file} -o {$output_dir}/02.pdf --style no-indent-style.mss";
    shell_exec($musicxml_to_pdf_command);

    //Erstellung der Uebungs-PDF anhand des bereits geanderten musicxml
    //Ueberschrift anpassen
    $xpath->query("//credit-words")->item(0)->nodeValue = $header;

    //Uebungsseite anpassen: Noten entfernen vs. Notennamen entfernen
    switch ($type) {

            //Beim "Noten lesen" ueber Texttages gehen und Text entfernen
        case "read":
            $lyric_nodes = $xpath->query("//lyric/text");
            foreach ($lyric_nodes as $lyric_node) {
                $lyric_node->nodeValue = "";
            }
            break;

            //Beim "Noten schreiben" Stem und Notehead ausblenden
        case "write":
            $score_notes = $xpath->query("//note");
            foreach ($score_notes as $score_note) {

                //Stem ausblenden
                $lyric = $xpath->query('stem', $score_note)->item(0)->nodeValue = "none";

                //Notehead none vor lyrics-Tag einfuegen
                $lyric = $xpath->query('lyric', $score_note)->item(0);
                $notehead = $domdoc->createElement('notehead', 'none');
                $score_note->insertBefore($notehead, $lyric);
            }
            break;
    }

    //Aus musicxml-Datei (ohne Notentext / Notenkoepfe) eine pdf-Datei erzeugen
    $fh = fopen($output_dir . "/" . $random_sheet_file, "w");
    fwrite($fh, $domdoc->saveXML());
    fclose($fh);
    $musicxml_to_pdf_command = "{$program} {$output_dir}/{$random_sheet_file} -o {$output_dir}/01.pdf --style no-indent-style.mss";
    shell_exec($musicxml_to_pdf_command);

    //Uebungs-PDF und Loesungs-PDF zu einer pdf mergen
    $pdf = new \Jurosh\PDFMerge\PDFMerger;
    $pdf->addPDF($output_dir . "/01.pdf")->addPDF($output_dir . "/02.pdf");
    $pdf->merge("file", "{$output_dir}/" . date('Y-m-d') . " - {$mode}_{$type}_{$clef}.pdf");

    //Arbeitsdateien loeschen
    unlink($output_dir . "/01.pdf");
    unlink($output_dir . "/02.pdf");
    unlink($output_dir . "/random.musicxml");
}
