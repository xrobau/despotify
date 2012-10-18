<?php

include_once('File.php');
include_once('global.php');

/**
* Represents a track
*/
class Track
{
	private $id;
	private $name;
	private $albumId;
	private $albumName;
	private $albumArtistId;
	private $albumArtistName;
	private $artistId;
	private $artistName;
	private $redirect;
	private $length;
	private $year;
	private $trackNumber;
	private $coverId;
	private $popularity;
	private $externalIds;
	private $allowedCountries;
	private $forbiddenCountries;
	private $allowedCatalogues;
	private $files = array();
	
	private $connection;

	private $country;
	
	
	
	public function __construct($xmlOrId, $connection, $country)
	{
		// Despotify gateway connection
		$this->connection = $connection;

		// Country
		$this->country = $country;
		
		// faking multiple constructors, kind of
		if(is_a($xmlOrId, 'SimpleXMLElement')) // load from XML
		{
			$xmlObject = $xmlOrId;
			
			$this->trackFromXMLObject($xmlObject);
		}
		else // load from the track's id
		{
			$trackId = $xmlOrId;
			$this->trackFromId($trackId);
		}
	}
	
	
	/*
	* Populate this object's member variables by supplying an id. This id is sent to the gateway to retrieve the track's data.
	*/
	private function trackFromId($trackId)
	{
		// make sure its a 32 byte despotify id, not a 34 byte one
		$trackId = chopDespotifyId($trackId);
		
		if($this->connection->isConnected())
		{
			// make sure it's a valid id
			if(isValidDespotifyId($trackId))
			{
				$this->connection->write('browsetrack '. $trackId . "\n");
				
				if(($length = $this->connection->readHeader()) === false)
				{
					return false;
				}
				
				$xmlResult = $this->connection->read($length);
				
				
				$xmlObject = new SimpleXMLElement($xmlResult);
				
				
				// dirty fix
				if(is_array($xmlObject))
				{
					$xmlObject = $xmlObject[0];
				}
				
				
				$this->trackFromXMLObject($xmlObject);
			}
			else
			{
				echo 'invalid id: ' . $trackId . '<br/>';
				return false;
			}
		}
	}
	
	
	/*
	* Populate this object's member variables by extracting the data from XML.
	* @param xmlObject A SimpleXMLElement object holding track data
	*/
	private function trackFromXMLObject($xmlObject)
	{
		if($xmlObject->xpath('/result/tracks/track')) // because SearchResult's constructor sends a differently structured XML object
		{
			$xmlObject = $xmlObject->xpath('/result/tracks/track');
			$xmlObject = $xmlObject[0];
		}
		
		$xmlArray = (array)$xmlObject;

		
		// store data in member variables
		$this->id = $xmlArray['id'];
		$this->name = $xmlArray['title'];
		$this->albumId = $xmlArray['album-id'];
		$this->albumName = $xmlArray['album'];
		$this->albumArtistId = $xmlArray['album-artist-id'];
		$this->albumArtistName = $xmlArray['album-artist'];
		$this->artistId = $xmlArray['artist-id'];
		$this->artistName = $xmlArray['artist'];
		$this->redirect = $xmlArray['redirect'];
		$this->length = $xmlArray['length'];
		$this->year = $xmlArray['year'];
		$this->trackNumber = $xmlArray['track-number'];
		$this->coverId = $xmlArray['cover'];
		$this->popularity = $xmlArray['popularity'];
		$this->externalIds = $xmlArray['external-ids'];
		
		
		// restrictions
		$this->allowedCountries = $xmlObject->xpath('restrictions/restriction/@allowed');
		$this->allowedCountries = $this->allowedCountries[0];
		$this->allowedCatalogues = $xmlObject->xpath('restrictions/restriction/@catalogues');
		$this->allowedCatalogues = $this->allowedCatalogues[0];
		$this->forbiddenCountries =  $xmlObject->xpath('restrictions/restriction/@forbidden');
		$this->forbiddenCountries = $this->forbiddenCountries[0];
		
		// external ids
		if(!empty($this->externalIds))
		{
			$this->externalIds = $this->externalIds->xpath('/external-ids');
		}

		// files
		//$fileArray = $xmlArray['files'];
		$fileArray = $xmlObject->xpath('files/file');
		if(is_array($fileArray))
		{
			foreach($fileArray as $currentFile)
			{
				array_push($this->files, new File($currentFile));
			}
		}
		
		// clean up some weird values
		if(count($this->allowedCountries) == 1 && $this->allowedCountries[0] == '') // empty string
		{
			$this->allowedCountries = NULL;
		}
		
		if(count($this->allowedCatalogues) == 1 && $this->allowedCatalogues[0] == '') // empty string
		{
			$this->allowedCatalogues = NULL;
		}

		// Check to see if we can have it.
		$f = $this->forbiddenCountries;
		$a = $this->allowedCountries;
		
		// If we're explicitly allowed, then it's all good.
		if ( $this->checkRegion($a))
			return;

		// Check to see if we're blocked, or, if no-one's allowed.
		// or
		// Check to see if forbidden is blank and we're not in the allow list
		if ( ($this->checkRegion($f) || ( $a == "" && $f == "")) || ($f == "" && !$this->checkRegion($a)) )
		{
			// Delete files, we can't get 'em.
			$this->files = array();

			// Are there lternatives?
			if (isset($xmlArray['alternatives']))
			{
				foreach ($xmlArray['alternatives'] as $track)
				{
					if (isset($track->restrictions->restriction->attributes()->forbidden))
					{
						$f = $track->restrictions->restriction->attributes()->forbidden;
					} 

					if (isset($track->restrictions->restriction->attributes()->allowed))
					{
						$a = $track->restrictions->restriction->attributes()->allowed;
					}

					// If we're forbidden, we can't. Next.
					if ($this->checkRegion($f))
						continue;

					// Is no-one allowed?
					if ( (isset($a) && $a == "") && (isset($f) && $f == "") )
						continue;

					// Are we not allowed?
					if (!$this->checkRegion($a) && $a != "") 
						continue;

					// Woot. We can use this one. 
					$this->files = array();
					foreach($track->files->file as $currentFile)
					{
						array_push($this->files, new File($currentFile));
					}
				}
			}
		}
	}
	
	
	
	
	/* GETTERS */
	
	/**
	* Get Spotify HTTP address
	*
	* @return Spotify HTTP address, something like http://open.spotify.com/track/2URijinLBt1ECOe9Vw2NT6
	*/
	public function getHTTPLink()
	{
		return 'http://open.spotify.com/track/' . toSpotifyId($this->id);
	}
	
	
	/*
	* Get Spotify address
	*
	* @return Address conforming to the Spotify URI scheme, something like spotify:track:2URijinLBt1ECOe9Vw2NT6
	*/
	public function getSpotifyURI()
	{
		return 'spotify:track:' . toSpotifyId($this->id);
	}
	
	
	public function getId()
	{
		return $this->id;
	}
	
	
	public function getName()
	{
		return $this->name;
	}
	
	
	public function getAlbumId()
	{
		return $this->albumId;
	}
	
	
	public function getAlbumName()
	{
		return $this->albumName;
	}
	
	
	public function getAlbumArtistId()
	{
		return $this->albumArtistId;
	}
	
	
	public function getAlbumArtistName()
	{
		return $this->albumArtistName;
	}
	
	
	public function getArtistName()
	{
		return $this->artistName;
	}
	
	
	public function getArtistId()
	{
		return $this->artistId;
	}
	
	
	public function getRedirect()
	{
		return $this->redirect;
	}
	
	
	public function getLength()
	{
		return $this->length();
	}
	
	
	public function getYear()
	{
		return $this->year;
	}
	
	
	public function getTrackNumber()
	{
		return $this->trackNumber;
	}
	
	
	public function getCoverId()
	{
		return $this->coverId;
	}
	
	
	public function getPopularity()
	{
		return $this->popularity;
	}
	
	
	public function getExternalIds()
	{
		return $this->externalIds;
	}
	
	
	public function getAllowedCountries()
	{
		return $this->allowedCountries;
	}
	
	
	public function getAllowedCatalogues()
	{
		return $this->allowedCatalogues;
	}
	
	
	public function getFiles()
	{
		return $this->files;
	}

	public function getKey($fileid, $trackid)
	{

                $fileid = substr(trim($fileid), 0, 40);
                $trackid = substr(trim($trackid), 0, 32);

                $this->connection->write("key ".$fileid." ".$trackid."\n");
                if (($len = $this->connection->readHeader()) === FALSE)
		{
                        return false;
		}

                $output = $this->connection->read($len);
                $output = str_replace("\0", '', $output);
                $xml = new SimpleXMLElement($output);

                $key = (array)$xml->xpath("/filekey/key");

                return $key[0];
        }

	public function subStream($file, $offset, $blocksize, $key)
	{
		$this->connection->write(sprintf("substream %40s %u %u %32s\n", $file, $offset, $blocksize, $key));

		if(($length = $this->connection->readHeader()) === false)
		{
			return false;
		}

		$rlen = $length;

                if ($offset == 0) {
                        $skip = $this->connection->read(167);
                        $rlen -= 167;
                }

		$data = $this->connection->read($rlen);
                return array($rlen, $data);
        }

	/**
	* Checks to see if the region array (or name) given to it is OK for the current region
	* @return true or false
	*/
	private function checkRegion($regions)
	{
		$regions = explode(',', $regions);

		$ok = false;
		foreach ($regions as $r)
		{
			if ($r === $this->country)
				$ok = true;
		}
		return $ok;
	}

}

?>
