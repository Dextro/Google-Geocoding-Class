<?php

header('Content-type: text/html; charset=utf-8');

require_once 'geocoding.php';
	
$geo = new GoogleGeocoding();

// get all the info about an address
$result = $geo->getInfo('Champs-Élysées, Paris');
var_dump($result);

// get the longitude, lattitude and accuracy
$result = $geo->getLngLat('Champs-Élysées, Paris');
var_dump($result);

// get the most specific address of a location
$result = $geo->getReverse(2.3075859, 48.8698008);
var_dump($result);

// get all the addresses of a location (most specific to generic)
$result = $geo->getReverseAll(2.3075859, 48.8698008);
var_dump($result);

// @todo example for viewport and country code biasing