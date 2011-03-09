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
	* - This is the getID3 tag data service
	*
	* @since 02.17.05
	* @author Ben Dodson <ben@jinzora.org>
	* @author Ross Carlson <ross@jinzora.org>
	*/
	
	$jzSERVICE_INFO = array();
	$jzSERVICE_INFO['name'] = "getID3";
	$jzSERVICE_INFO['url'] = "http://www.getid3.org";
	
	define('SERVICE_TAGDATA_getid3','true');
	
	function SERVICE_SET_TAGDATA_getid3($fname, $meta){
		global $include_path;
		
		// Ok, now we need to convert out tags to the getID3 format
		// We support the following tags:
		/*
		title
		artist
		album
		year
		number
		genre
		comment
		pic_data
		pic_ext
		pic_name
		pic_mime						
		
		TODO: support writing lyrics, but need to correctly encode
		  into the id3v2 tag
		*/
		
		// Ok, first we need to include the getID3 functions
		define('GETID3_INCLUDEPATH',$include_path. "services/services/tagdata/getid3/");
		include_once($include_path. "services/services/tagdata/getid3/getid3.php");
		
		// Ok, now let's setup our getID3 object
		$getID3 = new getID3;
		$getID3->encoding = 'UTF-8';
		getid3_lib::IncludeDependency(GETID3_INCLUDEPATH.'write.php', __FILE__, true);
		$tagwriter = new getid3_writetags;
		$tagwriter->filename = $fname;
		$fileInfo = $getID3->analyze($fname);
		getid3_lib::CopyTagsToComments($fileInfo);

		switch ($fileInfo['fileformat']) {
			case 'mp3':
			case 'mp2':
			case 'mp1':
				$ValidTagTypes = array('id3v1', 'id3v2.3', 'ape');
				break;

			case 'mpc':
				$ValidTagTypes = array('ape');
				break;

			case 'ogg':
				if (@$OldThisFileInfo['audio']['dataformat'] == 'flac') {
					//$ValidTagTypes = array('metaflac');
					// metaflac doesn't (yet) work with OggFLAC files
					$ValidTagTypes = array();
				} else {
					$ValidTagTypes = array('vorbiscomment');
				}
				break;

			case 'flac':
				$ValidTagTypes = array('metaflac');
				break;

			case 'real':
				$ValidTagTypes = array('real');
				break;

			default:
				$ValidTagTypes = array();
				break;
		}
		$tagwriter->tagformats = $ValidTagTypes;		
		
		
		$data = array();		
		if (isset($meta['pic_data'])){
			$data['attached_picture'][0]['data'] = $meta['pic_data'];
		} else {
			if (!empty($data['attached_picture'][0]['data'])) {
				$data['attached_picture'][0]['data'] = $fileInfo['attached_picture'][0]['data'];
			}			
		}
		if (isset($meta['pic_ext'])){
			$data['attached_picture'][0]['picturetypeid'] = $meta['pic_ext'];
		} else {
			if (!empty($data['attached_picture'][0]['picturetypeid'])) {
				$data['attached_picture'][0]['picturetypeid'] = $fileInfo['attached_picture'][0]['picturetypeid'];
			}			
		}	
		if (isset($meta['pic_name'])){
			$data['attached_picture'][0]['description'] = $meta['pic_name'];
		} else {
			if (!empty($data['attached_picture'][0]['description'])) {
				$data['attached_picture'][0]['description'] = $fileInfo['attached_picture'][0]['description'];
			}			
		}
		if (isset($meta['pic_mime'])){
			$data['attached_picture'][0]['mime'] = $meta['pic_mime'];
		} else {
			if (!empty($data['attached_picture'][0]['mime'])) {
				$data['attached_picture'][0]['mime'] = $fileInfo['attached_picture'][0]['mime'];
			}			
		}		
		if (isset($meta['year']) && $meta['year'] != "-"){
			$data['year'][] = $meta['year'];
		} else {
			if (!empty($fileInfo['comments']['year'][0])) {
				$data['year'][] = $fileInfo['comments']['year'][0];
			} else {
				$data['year'][] = "";
			}			
		}
		if (isset($meta['number']) && $meta['number'] != "-"){
			$data['track'][] = $meta['number'];
		} else if (!isset($meta['track'])){ // handle 'track' later
			if (!empty($fileInfo['comments']['comment'][0])) {
				$data['track'][] = $fileInfo['comments']['track'][0];
			} else {
				$data['track'][] = "1";
			}			
		}
		if (isset($meta['comment'])){
			$data['comment'][] = $meta['comment'];
		} else {
			if (!empty($fileInfo['comments']['comment'][0])) {
				$data['comment'][] = $fileInfo['comments']['comment'][0];
			} else {
				$data['comment'][] = "";
			}			
		}
		if (isset($meta['title']) && $meta['title'] != "-"){
			$data['title'][] = $meta['title'];
		} else {
			if (!empty($fileInfo['comments']['title'][0])) {
				$data['title'][] = $fileInfo['comments']['title'][0];
			} else {
				$data['title'][] = "";
			}		
		}
		if (isset($meta['artist']) && $meta['artist'] != "-"){
			$data['artist'][] = $meta['artist'];
		} else {
			if (!empty($fileInfo['comments']['artist'][0])) {
				$data['artist'][] = $fileInfo['comments']['artist'][0];
			} else {
				$data['artist'][] = "";
			}		
		}
		if (isset($meta['album']) && $meta['album'] != "-"){
			$data['album'][] = $meta['album'];
		} else {
			if (!empty($fileInfo['comments']['album'][0])) {
				$data['album'][] = $fileInfo['comments']['album'][0];
			} else {
				$data['album'][] = "";
			}		
		}
		if (isset($meta['genre']) && $meta['genre'] != "-"){
			$data['genre'][] = $meta['genre'];
		} else {
			if (!empty($fileInfo['comments']['genre'][0])) {
				$data['genre'][] = $fileInfo['comments']['genre'][0];
			} else {
				$data['genre'][] = "";
			}		
		}
		if (!isset($meta['number']) && isset($meta['track'])){
		  $data['track'][] = $meta['track'];
		} else if (!isset($meta['number'])) {
		  if (!empty($fileInfo['comments']['track'][0])) {
		    $data['track'][] = $fileInfo['comments']['track'][0];
		  } else {
		    $data['track'][] = "";
		  }
		}

		// Now let's write
		$tagwriter->tag_data = $data;
		if ($tagwriter->WriteTags()) {
		  return true;
		} else {
		  return false;
		}
	}
	
	function SERVICE_GET_TAGDATA_getid3($fname, $installer = false) {
		global $include_path;

		// Ok, first we need to include the getID3 functions		
		// Ok, now let's setup our getID3 object
		@define('GETID3_INCLUDEPATH',$include_path. "services/services/tagdata/getid3/");
		include_once($include_path. "services/services/tagdata/getid3/getid3.php");
		$getID3 = new getID3;
		$fileInfo = $getID3->analyze($fname);
		getid3_lib::CopyTagsToComments($fileInfo);

		// Now let's get the meta data
		if (!empty($fileInfo['audio']['bitrate'])) {
			$meta['bitrate'] = round($fileInfo['audio']['bitrate'] / 1000,0);
		} else {
			$meta['bitrate'] = "-";
		}
		if (!empty($fileInfo['playtime_seconds'])) {
			$meta['length'] = round($fileInfo['playtime_seconds'],0);
		} else {
			$meta['length'] = "-";
		}
		if (!empty($fileInfo['filesize'])) {
			$meta['size'] = round($fileInfo['filesize']/1000000,2);
		} else {
			$meta['size'] = "-";
		}
		$meta['title'] = getCommentValue($fileInfo, 'title', "-");
		$meta['artist'] = getCommentValue($fileInfo, 'artist', "-");
		$meta['album'] = getCommentValue($fileInfo, 'album', "-");
		if(! ($year = getCommentValue($fileInfo, 'year'))) {
		  # Ogg files store the year in the first 4 characters of the date field (yyyy-mm-ddThh:mm:ss)
		  # This also deals with those taggers that only fill in the year (yyyy)
		  if(($year = getCommentValue($fileInfo, 'date'))) {
		    $year = substr($year, 0, 4);
		  } else {
		    $year = "-";
		  }
		}
		$meta['year'] = $year;
		if (!($track = getCommentValue($fileInfo, 'track_number'))) {
		  $track = getCommentValue($fileInfo, 'track', "-");
		}
		if (strstr($track, '/')) {
		  // strip "/<total>"
		  $track = substr($track, 0, strpos($track, '/'));
		}
		if($track[0] == '0') {
		  // strip leading 0
		  $track = substr($track, 1, strlen($track));
		}
		$meta['number'] = $track;
		$meta['genre'] = getCommentValue($fileInfo, 'genre', "-");
		if (!empty($fileInfo['audio']['sample_rate'])) {
			$meta['frequency'] = round($fileInfo['audio']['sample_rate']/1000,1);
		} else {
			$meta['frequency'] = "-";
		}
		$meta['comment'] = getCommentValue($fileInfo, 'comment', "");
		$meta['lyrics'] = getCommentValue($fileInfo, 'unsynchronised_lyric', "");
		if (!empty($fileInfo['video']['resolution_x'])){
			$meta['width'] = $fileInfo['video']['resolution_x'];
		} else {
			$meta['width'] = "";
		}
		if (!empty($fileInfo['video']['resolution_y'])){
			$meta['height'] = $fileInfo['video']['resolution_y'];
		} else {
			$meta['height'] = "";
		}
				
		if (!empty($fileInfo['id3v2']['APIC'][0]['data'])){
			$meta['pic_data'] = $fileInfo['id3v2']['APIC'][0]['data'];
		} else {
			$meta['pic_data'] = "";			
		}
		if (!empty($fileInfo['id3v2']['APIC'][0]['description'])){
			$meta['pic_name'] = $fileInfo['id3v2']['APIC'][0]['description'];
		} else {
			$meta['pic_name'] = "";			
		}
		if (!empty($fileInfo['id3v2']['APIC'][0]['mime'])){
			$meta['pic_mime'] = $fileInfo['id3v2']['APIC'][0]['mime'];
		} else {
			$meta['pic_mime'] = "";			
		}
		$meta['extension'] = substr($fname,-3);
		
		// common to both methods:
		$temp = explode("/",$fname);
		$meta['filename'] = $temp[sizeof($temp)-1];
		$fileInfo = pathinfo($fname);
		$meta['type'] = $fileInfo["extension"];
		$name = $temp[sizeof($temp)-1];
		
		// Now let's see if the track name has the number
		if (is_numeric(substr($meta['filename'],0,3))){
			$meta['number'] = substr($meta['filename'],0,3);
		} else if (is_numeric(substr($meta['filename'],0,2))){
			$meta['number'] = substr($meta['filename'],0,2);
		} else if (is_numeric(substr($meta['filename'],0,1))){
			$meta['number'] = substr($meta['filename'],0,1);
		}

		
		if ($meta['title'] == "-" or $meta['title'] == "Tconv"){
			$name = str_replace(".". $meta['type'], "", $name);
			// Let's see if this is a .fake file or not
			if (stristr($name,".fake")){
				$name = str_replace(".fake","",$name);
			}
		}	
		
		// Now let's see if the name needs the file number stripped off of it (thus giving us the track number)
		if (is_numeric(substr($name,0,2))){
			$pos = 2;
			// Ok, we found numbers so let's fix it up!
			// Now let's figure out the new track names...
			if ($meta['number'] == "-") {
				if (is_numeric(substr($meta['filename'],03))){
					$meta['number'] = substr($meta['filename'],0,3);
					$pos = 3;
				} else {
					$meta['number'] = substr($name,0,2);
				}
			}
			$name1 = substr($name,$pos,strlen($name));
			
			if ($name1 == "") {
				$name = $name1;
			}
			else {
				$name = $name1;
				if (!isset($track_num_seperator) || $track_num_seperator == "") {
					$trackSepArray = array(".","-"," - ");
				} else {
					$trackSepArray = explode("|",$track_num_seperator);
				}
				for ($i=0; $i < count($trackSepArray); $i++){
					if (stristr($name,$trackSepArray[$i])){
						// Now let's strip from the beginning up to and past the seperator
						$name = substr($name,strpos($name,$trackSepArray[$i]) + strlen($trackSepArray[$i]),999999);
					}
				}
			}
		}	else {
			if (!isset($meta['number'])){$meta['number']="-";}
			if (!isset($file)){$file="";}
			if ($meta['number'] == "-" && is_numeric(substr($file,0,2))) {
				 if (is_numeric(substr($meta['filename'],0,3))){
					$meta['number'] = substr($meta['filename'],0,3);
				} else { 
					$meta['number'] = substr($fname,0,2); 
				}
			}
		}
		
		if ($meta['title'] == "-" or $meta['title'] == "Tconv" or $meta['title'] == "") {
			$meta['title'] = $name;
		}

		// Now let's return what we go
		return $meta;
	}

	function getCommentValue($fileInfo, $name, $defvalue = false) {
	  if (!empty($fileInfo['comments'][$name]) && is_array($fileInfo['comments'][$name]) && !empty($fileInfo['comments'][$name][0])) {
	    return $fileInfo['comments'][$name][0];
	  }
	  return $defvalue;
	}
	      
?>