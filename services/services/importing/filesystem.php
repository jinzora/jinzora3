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
 * - This is google search service.
 *
 * @since 07.27.06
 * @author Ben Dodson <ben@jinzora.org>
 * @author Ross Carlson <ross@jinzora.org>
 */

$jzSERVICE_INFO = array();
$jzSERVICE_INFO['name'] = "Jinzora import by filesystem";
$jzSERVICE_INFO['url'] = "http://www.jinzora.org";

define('SERVICE_IMPORTING_filesystem','true');

function SERVICE_IMPORTMEDIA_filesystem($node, $root_path = false, $flags = array()) {
  global $ext_graphic,$audio_types,$video_types,$playlist_types,$default_art;
	
  if (!isset($playlist_types)) {
    $playlist_types = "m3u";
  }

	global $importerLevel;
	if (!isset($importerLevel)) {
		$importerLevel = 0;
	}
	
	$be = new jzBackend();
	
	if ($root_path !== false) {
		$folder = $root_path;
	} else {
		$folder = $node->getFilePath();
	}
	
	$bestImage = "";
	
	// TODO: FIX THE PARAMETER HERE FOR 3.0
	$thisPath = array_values($flags['path']);
	$parent = new jzMediaNode($thisPath);
	
	if (isset($flags['showstatus']) && $flags['showstatus'] && !(is_string($flags['showstatus']) && $flags['showstatus'] == "cli")) {
		if (!isset($_SESSION['jz_import_full_progress'])) {
	 		$_SESSION['jz_import_full_progress'] = 0;
		}
	}
	 
	if (!$handle = opendir($folder)) {
		echo 'SERVICE_IMPORTMEDIA_filesystem: could not open ' . $folder;
		return false;
	}
	
	if (isset($flags['showstatus']) && is_string($flags['showstatus']) && $flags['showstatus'] == "cli") {
	 		echo word("Scanning: %s", $folder) . "\n";
	}
	
	$track_paths = array();
	$track_filenames = array();
	$track_metas = array();
	while ($file = readdir($handle)) {
		if (importSkipFile($file)) {
			continue;
		}
		
		$fullpath = $folder . '/' . $file;
		
		if (is_dir($fullpath)) {
			$entry = $be->lookupFile($fullpath);
			if ($entry === false || (isset($flags['recursive']) && $flags['recursive'])) {
				$flags2 = $flags;
				if (sizeof($flags2['hierarchy']) == 0) {
					$val = 'disk';
				} else { 
					$val = array_shift($flags2['hierarchy']);
					if ($val == 'track') {
						$val = "disk";
					}
				}
				$flags2['path'][$val] = $file;
				$flags2['recursive'] = true;
				$importerLevel++;
				SERVICE_IMPORTMEDIA_filesystem($node,$fullpath,$flags2);
				$importerLevel--; 
			}
		} else  if (preg_match("/\.($ext_graphic)$/i", $file) && !stristr($file,".thumb.")) {
			// An image
			if (@preg_match("/($default_art)/i",$file)) {
				$bestImage = $fullpath;
		  	} else if ($bestImage == "") {
		   		$bestImage = $fullpath;
		  	}
		} else if (preg_match("/\.($playlist_types)$/i",$file)) {

		  $ext = substr($file, strrpos($file, '.') + 1);
			if (0 == strcasecmp($ext,'m3u')) {
			  $m3u_lines = file($fullpath);
			  $is_local_m3u = false;
			  foreach ($m3u_lines as $line) {
			    if ($line=='#') {
				// TODO: get metadata.
				continue;
			    } else {
			      if (false === strpos($line,'://')) {
			        $is_local_m3u = true;
				break;
                              }
  			      $mediaref = $line;
			      $medianame = $mediaref;
			      while ($medianame[strlen($medianame)-1] == '/') {
				$medianame = substr($medianame,0,strlen($medianame)-1);
			      }
			      $medianame = substr($medianame,strrpos($medianame,'/')+1);
			      $mypath = $flags['path'];
			      $mypath['track'] = $medianame;

			      $track_paths[] = $mypath;
			      $track_filenames[] = $mediaref;
			      $track_metas[] = array();
			    }
			  }
			}


		  
		} else if (preg_match("/\.($audio_types)$/i", $file) || preg_match("/\.($video_types)$/i", $file)) {
			$entry = $be->lookupFile($fullpath);
			
			if (isset($flags['showstatus']) && !(is_string($flags['showstatus']) && $flags['showstatus'] == "cli")) {
				if (($_SESSION['jz_import_full_progress'] % 50 == 0) 
					or ($_SESSION['jz_import_full_progress'] == 0)
					or ($_SESSION['jz_import_full_progress'] == 1)){
					showStatus();
				}
	 			$_SESSION['jz_import_full_progress']++;
			}
			
			if ((isset($flags['force']) && $flags['force']) || !(is_array($entry))) {
				$mypath = $flags['path'];
				$mypath['track'] = $file;
				if (isset($flags['readtags']) && $flags['readtags']) {
					$track = &new jzMediaTrack($fullpath);
					$track->playpath = $fullpath;
					$meta = $track->getMeta("file");
				} else {
					$meta = false;
				}
				$track_paths[] = $mypath;
				$track_filenames[] = $fullpath;
				$track_metas[] = $meta;
			}
		}
	}
	$node->bulkInject($track_paths,$track_filenames,$track_metas);
	
	if ($bestImage != "") {
		$parent->addMainArt($bestImage);
	}
	
	$be->registerFile($folder,$thisPath);
	
	if ($parent->getFilePath() != $folder) {
		$parent->setFilePath($folder);
	}
	
	if ($importerLevel == 0) {
		$be->removeDeadFiles($folder,$flags['recursive']);
	}
}
	
function importSkipFile($file) {
	return $file == "." || $file == ".." || $file == "@eaDir";
}

?>