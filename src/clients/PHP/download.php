<?php

require_once('despotify.class.php');

$gw = 'localhost';
$port = '8910'; // or wherever you're running gateway

$ds = new Despotify($gw, $port);
$ds->connect(); 
$ds->auth('1', 'pass'); // You'll need to change this

$pl = $ds->playlist('02975fefc216048561a262ee1c0bdebf02'); //Select the playlist

foreach($pl['items'] as $track) {
	$track = substr(trim($track), 0, 32);
	$t = $ds->browsetrack($track);
	print_r($t);
	$key = (array)$ds->key($t[0]['formats']['Ogg Vorbis,320000,1,32,4'], $track);
	$count = 0;
	$bs = 81920;
	$fh = fopen($t[0]['title'].".ogg", 'w');
	while (true) {
		$data = $ds->substream($t[0]['formats']['Ogg Vorbis,320000,1,32,4'], $count, $bs, $key[0]);
		print ".";
		$ret = fwrite($fh, $data[1], $data[0]);
		$count += $bs;
		if ($data[0] < $bs) { break; }
	}
	fclose($fh);
}
