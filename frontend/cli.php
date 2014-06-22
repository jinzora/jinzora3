<?php if (!defined(JZ_SECURE_ACCESS)) die ('Security breach detected.');
	/**
	* - JINZORA | Web-based Media Streamer -  
	* 
	* Jinzora is a Web-based media streamer, primarily desgined to stream MP3s 
	* (but can be used for any media file that can stream from HTTP). 
	* Jinzora can be integrated into a CMS site, run as a standalone application, 
	* or integrated into any PHP website.  It is released under the GNU GPL.
	* 
	* - Resources -
	* - Jinzora Author: Ross Carlson <ross@jasbone.com>
	* - Web: http://www.jinzora.org
	* - Documentation: http://www.jinzora.org/docs	
	* - Support: http://www.jinzora.org/forum
	* - Downloads: http://www.jinzora.org/downloads
	* - License: GNU GPL <http://www.gnu.org/copyleft/gpl.html>
	* 
	* - Contributors -
	* Please see http://www.jinzora.org/team.html
	* 
	* - Code Purpose -
	* - This page directs 'traffic' to the proper Jinzora component.
	*
	* @since 08.16.2006
	* @author Ross Carlson <ross@jinzora.org>
	* @author Ben Dodson <ben@jinzora.org>
	*/

// Do something and die.
if (!(isset ($argv) && sizeof($argv) > 0 && $_SERVER['argc'] > 0)) {
	return;
}

switch ($argv[1]) {

	// UPDATE
	case "force_update" :
		$force_update = true;
	case "update" :
		if (!isset ($force_update)) {
			$force_update = false;
		}

		if (isset ($argv[2])) {
			$node = new jzMediaNode($argv[2]);
		} else {
			$node = new jzMediaNode();
		}
		// Should this force the update? Set the last param here...

		writeLogData("messages", "Command line Jinzora: Updating the node cache");
		updateNodeCache($node, true, "cli", $force_update);
		break;
		// LYRICS SCAN:
	case "scan_lyrics" :
		if (isset ($argv[2])) {
			$node = new jzMediaNode($argv[2]);
		} else {
			$node = new jzMediaNode();
		}
		$tracks = $node->getSubNodes("tracks", -1);
		$nfound = 0;
		$nmissed = 0;
		$nskipped = 0;
		foreach ($tracks as $track) {
			$meta = $track->getMeta();
			if (isNothing($meta['lyrics'])) {
				$lyrics = $jzSERVICES->getLyrics($track);
				if (!isNothing($lyrics)) {
					echo 'FOUND: ' . $track->getPath("String") . "\n";
					$meta['lyrics'] = $lyrics;
					$nfound++;
					$track->setMeta($meta);
				} else {
					echo 'NO MATCH: ' . $track->getPath("String") . "\n";
					$nmissed++;
				}
			} else {
				echo 'SKIPPING: ' . $track->getPath("String") . "\n";
				$nskipped++;
			}
		}
		echo 'Found: ' . $nfound . "\n";
		echo 'Not Found: ' . $nmissed . "\n";
		echo 'Skipped: ' . $nskipped . "\n";
		echo 'Total: ';
		echo $nfound + $nmissed + $nskipped;
		echo "\n";
		break;
		// LIST
	case "list" :
		if (isset ($argv[3])) {
			$root = new jzMediaNode($argv[3]);
		} else {
			$root = new jzMediaNode();
		}
		switch ($argv[2]) {
			case "genre" :
			case "genres" :
				$list = $root->getSubNodes("nodes", distanceTo("genre", $root));
				break;
			case "artist" :
			case "artists" :
				$list = $root->getSubNodes("nodes", distanceTo("artist", $root));
				break;
			case "album" :
			case "albums" :
				$list = $root->getSubNodes("nodes", distanceTo("album", $root));
				break;
			case "track" :
			case "tracks" :
				$list = $root->getSubNodes("tracks", -1);
				break;
		}
		if (isset ($list)) {
			foreach ($list as $el) {
				echo $el->getName() . "\n";
			}
		}
		break;
	case "search_metadata" :
		if (isset ($argv[2])) {
			$node = new jzMediaNode($argv[2]);
		} else {
			$node = new jzMediaNode();
		}

		$nodes = $node->getSubNodes("nodes", -1);

		// Now let's add the node for what we are viewing
		$nodes = array_merge(array (
			$node
		), $nodes);
		$total = count($nodes);
		$c = 0;
		$start = time();

		foreach ($nodes as $item) {
			echo "Retrieving metadata for " . $item->getName() . ".\n";
			// Now let's see if this is an artist
			if ($item->getPType() == 'artist') {
				// Now do we want to look at this?
				// Ok, let's get data for this artist
				// Now let's get the data IF we should
				if (($item->getMainArt() == "") or ($item->getDescription() == "")) {

					$arr = array ();
					$arr = $jzSERVICES->getArtistMetadata($item, true, "array");
					// Now let's see if they want to get art or need to
					if (($item->getMainArt() == "") and $arr['image'] <> "") {
						// Ok, we want the art
						writeArtistMetaData($item, $arr['image'], false, false);
					}
					if (($item->getDescription() == "") and $arr['bio'] <> "") {
						writeArtistMetaData($item, false, $arr['bio'], false);
					}
				}
			}
			// Now let's look at the album
			if ($item->getPType() == 'album') {
				$parent = $item->getParent();
				$artist = $parent->getName();
				$arr = array ();
				$arr = $jzSERVICES->getAlbumMetadata($item, false, "array");

				// Ok, now should we do the art?
				if (($item->getMainArt() == "") and $arr['image'] <> "") {

					writeAlbumMetaData($item, false, $arr['image']);
				} else {
					unset ($arr['image']);
				}
				// Ok, now should we do the description?
				if (($item->getDescription() == "") and $arr['review'] <> "") {
					writeAlbumMetaData($item, false, false, false, $arr['review']);
					usleep(250000);
				} else {
					unset ($arr['review']);
				}
				// Ok, now should we do the year?
				if ($item->getYear() == "") {
				} else {
					unset ($arr['year']);
				}
				// Ok, now should we do the rating?
				if ($arr['rating'] <> "") {
					writeAlbumMetaData($item, false, false, false, false, $arr['rating']);
					usleep(250000);
				}

				// Now let's write the ID to the database
				if ($arr['id'] <> "" and $arr['id'] <> "NULL") {

					if ($allow_filesystem_modify == "true") {
						$fName = $item->getDataPath("String") . "/album.id";
						$handle = @ fopen($fName, "w");
						@ fwrite($handle, $arr['id']);
						@ fclose($handle);
					}
					$item->setID($arr['id']);
				}

				// Did they want to write this to the id3 tags?
				if ($allow_id3_modify == "true" and (isset ($arr['year']) or isset ($arr['image']))) {
					// Now let's set the meta fields so they get updated for all the tracks
					if (isset ($arr['year'])) {
						$meta['year'] = $arr['year'];
					}
					if (isset ($arr['image'])) {
						// Ok, now let's resize this first
						// If art is too big it looks like shit in the players
						$imageData = @ file_get_contents($arr['image']);
						if ($imageData) {
							// Now let's write it out
							$file = $include_path . 'temp/tempimage.jpg';
							$dest = $include_path . 'temp/destimage.jpg';
							$handle = fopen($file, "w");
							fwrite($handle, $imageData);
							fclose($handle);

							// Now let's resize
							if ($jzSERVICES->resizeImage($file, "200x200", $dest)) {
								// Now let's get new data for the tag writing
								unset ($imageData);
								$imageData = file_get_contents($dest);
							}

							// Now let's clean up
							@ unlink($file);
							@ unlink($dest);

							// Now let's make sure that was valid
							if (strlen($imageData) < 2000 or !stristr($arr['image'], ".jpg")) {
								$imageData = "";
							}

							$imgShortName = $item->getName() . ".jpg";

							$meta['pic_mime'] = 'image/jpeg';
							$meta['pic_data'] = $imageData;
							$meta['pic_ext'] = "jpg";
							$meta['pic_name'] = $imgShortName;
						}
						// Now let's update
						$item->bulkMetaUpdate($meta, false, false);
					}
					unset ($rating);
					unset ($image);
					unset ($arr);
				}
			}
			$c++;
		}
		break;
		// TODO: Generate, Search, Pull playlist, stats

		// HELP:
	case "help" :
	case "-h" :
	default :
		echo "Usage: \n";
		echo "php index.php update [root]\n";
		echo "           Updates the cache\n";
		echo "php index.php force_update [root]\n";
		echo "           Updates the cache, ignoring file modification times.\n";
		echo "php index.php list [genres|artists|albums|tracks] [root]\n";
		echo "           Lists content.\n";
		echo "php index.php scan_lyrics [root]\n";
		echo "           Scans for missing lyrics.\n";
		echo "php index.php search_metadata [root]\n";
		echo "           Searches for missing metadata.\n";
		break;
}

exit ();
?>
