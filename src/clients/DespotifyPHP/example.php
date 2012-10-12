<?php

require_once('src/Despotify.php');


$gw = 'localhost';
$port = '8910'; // Or wherever gateway is

$ds = new Despotify($gw, $port);
$ds->connect();
$ds->login('1', 'password'); // Change this.

$pl = $ds->getPlayList('e2f20788694efeb3e4bf09eb6bac9342'); // Billboard top 100

$tracks = $pl->getTracks();

foreach($tracks as $track) {
	$files = $track->GetFiles();
	print "Getting ".$track->getName();
	foreach ($files as $f) {
		if ($f->getAudioFormat() == 'Ogg Vorbis' && $f->getAudioBitrate() == 320000) 
		{
			if (file_exists($track->getName().".ogg")) {
				print "(exists, skipping)\n";
				continue;
			}
			print " as ".$track->getName().".ogg\n";
			$key = $track->getKey($f->getId(), $track->getId());
			$count = 0;
			$bs = 81920;
			$fh = fopen($track->getName().".ogg", 'w');
			while (true) 
			{
				$data = $track->substream($f->getId(), $count, $bs, $key[0]);
				print ".";
				$ret = fwrite($fh, $data[1], $data[0]);
				$count += $bs;
				if ($data[0] == 0) { break; }
			}
			fclose($fh);
			print "\n";
		}
	}
}
