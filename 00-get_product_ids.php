<?php

//Ueber Songs gehen und product_ids sammeln
$product_ids = [];
foreach (glob("songs/*.json") as $file) {
    $config = json_decode(file_get_contents($file), true);
    $product_ids[] = $config["product-id"];
}

//Sortierte Liste der pruducts_ids ausgeben
sort($product_ids);
print_r($product_ids);