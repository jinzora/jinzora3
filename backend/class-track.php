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
	* Please see http://www.jinzora.org/modules.php?op=modload&name=jz_whois&file=index
	* 
	* - Code Purpose -
	* These are the classes extended by the backend adaptors.
	*
	* @since 05.10.04
	* @author Ben Dodson <bdodson@seas.upenn.edu>
	*/
	
	class jzMediaTrackClass extends jzMediaElement {
		
		var $playpath;
		var $meta;
		var $startTime;
		
		/**
		* Constructor for a jzMediaTrackClass
		* 
		* @author 
		* @version 
		* @since 
		*/	
		function jzMediaTrackClass($par = array(),$mode = "path") {
			$this->playpath = false;
			$this->meta = array();
			$this->startTime = 0;
			$this->_constructor($par,$mode);
		}

		/**
		* Returns the track's name (from ID3)
		* 
		* @author Ben Dodson
		* @version 6/7/04
		* @since 6/7/04
		*/		
		function getName() {
			$cache = $this->readCache();
			return ($cache[7] == "-") ? parent::getName() : $cache[7];
		}

		/**
		* Returns the type of the node.
		* 
		* @author Ben Dodson
		* @version 10/31/04
		* @since 10/31/04
		*/
		function getType() {
			return "jzMediaTrack";
		}

		/**
		* Returns the track's complete file path (with $media_dir)
		* 
		* The paramater is one of: user|host|general
		*
		* @author Ben Dodson
		* @version 6/7/04
		* @since 6/7/04
		*/		
		function getFileName($target = "user") {
			global $media_dirs, $web_dirs, $web_root, $root_dir, $protocols,
			   	   $jzUSER, $allow_resample, $force_resample, 
			   	   $no_resample_subnets, $always_resample;
			
			if (checkPermission($jzUSER,'play',$this->getPath("String")) === false) {
			  return false;
			}

			if ($this->playpath === false || $this->playpath == "") {
				$cache = $this->readCache();
				
				if ($cache[0] == "-") { // return error?
					$this->playpath = $this->getPath("String");
				}
				else {
					$this->playpath = $cache[0];
				}
			}
			
			if (isset($protocols) && isset($this->playpath)) {
				$parr = explode("|",$protocols);
				foreach ($parr as $p) {
					if (stristr($this->playpath,$p) !== false) {
						return $this->playpath;
					}
				}
			}

			/**********************/
			if ($target == "user" || $target == "general") {
			  if ($target == "user" && ($local_root = $jzUSER->getSetting("localpath")) !== false) {
			    if (stristr($media_dirs,"|") === false) {
			      $tmp = $this->getFileName("host");
			      if (stristr($tmp,$media_dirs) !== false) {
							return str_replace("//","/",str_replace($media_dirs,$local_root,$tmp));
			      }
			    }
			  }

			  $meta = $this->getMeta();
			  $arr = array();
			  //$arr['Artist'] = $meta['artist']; // decoy
			  //$arr['Track'] = $meta['title']; // decoy

			  // Resample?
			  if (
			  		($allow_resample == "true" || $force_resample == "true")
			  	&& !(preg_match("/^${no_resample_subnets}$/", $_SERVER['REMOTE_ADDR']))
			  	 ) {
			  	
			  	if ($jzUSER->getSetting('resample_lock')) {
			  		$arr["resample"] = $jzUSER->getSetting('resample_rate');
			  	}
			    else if (isset($_SESSION['jz_resample_rate'])){
			      if ($_SESSION['jz_resample_rate'] <> ""){
							// Ok already, we are resampling!!!
							$arr["resample"] =  $_SESSION['jz_resample_rate'];
			      }
			    } else if (($jzUSER->getSetting('stream') === false && $jzUSER->getSetting('lofi') === true) || $force_resample == "true") {
			      $arr["resample"] = $jzUSER->getSetting('resample_rate');
			    }
			  }

			  $arr['jz_user'] = $jzUSER->getID(); // user id
			  $arr['sid'] = $_SESSION['sid']; // unique session id
			  if (getGlobal("CLIP_MODE")) {
			    $arr['cl'] = 't';
			  } 
			  
			  // Now, if they are resampling we MUST end with an MP3
			  $arr['ext'] = $meta['type'];
			  if (isset($_SESSION['jz_resample_rate'])){
			      if ($_SESSION['jz_resample_rate'] <> ""){
				  	  $arr['ext'] = "mp3";
				  }
			  }
			  
			  // Now we need to see if this track is a type that always gets resampled
			  if ($allow_resample == "true"){
			  	// Now do we have a type that is going to get resampled
					if (stristr($always_resample,$meta['type'])){
						// Ok, let's set it to MP3
						$arr['ext'] = "mp3";
					}
			  }
				
				// Now should we send a path or ID?
				if ($web_dirs <> ""){
					return jzCreateLink($this->getFileName("host"),"track",$arr);
				} else {
				  return jzCreateLink($this->getID(),"track",$arr);
				}
			} else {
			  return $this->playpath;
			}
		}


		/**
		* Returns the track's metadata as an array with the following keys:
		*
		* title
		* bitrate
		* frequency
		* filename [excluding path]
		* size
		* year
		* comment
		* length
		* length_str
		* number
		* genre
		* artist
		* album
		* lyrics
		* type [extension]
		* 
		* These are taken mostly from the ID3.
		*
		* @author Ben Dodson
		* @version 6/7/04
		* @since 6/7/04
		*/		
		function getMeta($mode = "cache", $installer = false) {
			global $track_num_seperator,$root_dir,$web_root, $include_path, $jzSERVICES;

			$meta = array();
			$cache = $this->readCache();
			if ($mode == "cache" && $this->meta != array()) {
				return $this->meta;
			}
			if ($mode == "cache") {
				$meta['title'] = $cache[7];
				$meta['bitrate'] = $cache[20];
				$meta['frequency'] = $cache[8];
				$meta['filename'] = $cache[2];
				$meta['size'] = $cache[13];
				$meta['year'] = $cache[11];
				$meta['comment'] = ($cache[9] == "-") ? "" : $cache[9];
				$meta['length'] = $len = $cache[14];
				$meta['number'] = $cache[21];
				$meta['genre'] = $cache[15];
				$meta['artist'] = $cache[16];
				$meta['album'] = $cache[17];
				$meta['lyrics'] = ($cache[19] == "" || $cache[19] == "-") ? "" : $cache[19];
				$meta['type'] = $cache[18];
				
				if (isNothing($meta['type'])) {
				  $meta = $this->getMeta("file");
				  $this->setMeta($meta,"cache");
				}
			}
			
			
			else { // Get it from the file.
				// other backend functions use this to get the id3 before the file is cached.
				if ($mode == "direct") {
					$fname = $this->getPath("String");
				} else {
					$fname = $this->getFileName("host");
				}
				
				// Do our services exist?
				if (!$jzSERVICES){
					include_once($include_path. 'services/class.php');
					$jzSERVICES = new jzServices();
					$jzSERVICES->loadStandardServices();
				}
				// Let's setup our tagdata service and return the tag data			
				$meta = $jzSERVICES->getTagData($fname, $installer);

				// Hack to fix bug where first track returns null metadata.
				if ($meta['bitrate'] == '-') {
				  // try again.
				  $meta = $jzSERVICES->getTagData($fname, $installer);
				}
			}
			return $meta;
		}


		/**
		* Sets the track's meta information.
		* $meta is an array of meta fields to set.
		* $mode specifies where to update the meta,
		* false means do it in the cache and in the id3.
		* 
		* @author Ben Dodson
		* @version 10/13/04
		* @since 10/13/04
		*/		
		function setMeta($meta, $mode = false, $displayOutput = false) {
		  global $jzSERVICES, $allow_id3_modify,$backend,$hierarchy;
			
		  if (is_array($hierarchy)) {
		    $hstring = implode("/",$hierarchy);
		  } else {
		    $hstring = $hierarchy;
		  }

		  if ($mode == false) {
		    // TODO: add variable to see if user allows ID3 updating.
		    $this->setMeta($meta,"file");
		    $this->setMeta($meta,"cache");
		  }
		  if ($mode == "cache") {
		    $filecache = $this->readCache();
		    
		    if (isset($meta['title']))
		      $filecache[7] = $meta['title'];
		    if (isset($meta['frequency']))
		      $filecache[8] = $meta['frequency'];
		    if (isset($meta['comment']))
		      $filecache[9] = $meta['comment'];
		    if (isset($meta['year']))
		      $filecache[11] = $meta['year'];
		    if (isset($meta['size']))
		      $filecache[13] = $meta['size'];
		    if (isset($meta['length']))
		      $filecache[14] = $meta['length'];
		    if (isset($meta['genre']))
		      $filecache[15] = $meta['genre'];
		    if (isset($meta['artist']))
		      $filecache[16] = $meta['artist'];
		    if (isset($meta['album']))
		      $filecache[17] = $meta['album'];
		    if (isset($meta['type']))
		      $filecache[18] = $meta['type'];
		    if (isset($meta['lyrics']))
		      $filecache[19] = $meta['lyrics'];
		    if (isset($meta['bitrate']))
		      $filecache[20] = $meta['bitrate'];
		    if (isset($meta['number']))
		      $filecache[21] = $meta['number'];
		    
		    $this->writeCache($filecache);
		    return true;
		  }
		  else  {
		    if ($mode == "direct") {
		      $fname = $this->getPath("String");
		    }
		    else {
		      $fname = $this->getFileName("host");
		    }

		    if ($backend == "id3-database" || $backend == "id3-cache") {
		      $oldmeta = $this->getMeta("file");
		    }

		    // Ok, now we need to write the data to this file IF it's an MP3
		    if ($allow_id3_modify == "true"){
		      $status = $jzSERVICES->setTagData($fname,$meta);
		    } else {
		      $status = true;
		    }

		    if (!isset($meta['genre'])) {
		      $meta['genre'] = $oldmeta['genre'];
		    }
		    if (!isset($meta['artist'])) {
		      $meta['artist'] = $oldmeta['artist'];
		    }
		    if (!isset($meta['album'])) {
		      $meta['album'] = $oldmeta['album'];
		    }
		    if (!isset($meta['filename'])) {
		      $meta['filename'] = $oldmeta['filename'];
		    }

		    if ($backend == "id3-database" || $backend == "id3-cache") {
		      if (($oldmeta['genre'] != $meta['genre'] && stristr($hstring,"genre") !== false)||
						($oldmeta['artist'] != $meta['artist'] && stristr($hstring,"artist") !== false) ||
						($oldmeta['album'] != $meta['album'] && stristr($hstring,"album") !== false) ||
						($oldmeta['filename'] != $meta['filename'])) {
						// The media needs to be moved.
						$arr = buildPath($meta);
						$newpath = implode("/",$arr);
						$root = new jzMediaNode();
						$root->moveMedia($this,$newpath);
						$this->reconstruct($newpath);
		      }
		    }

		    return $status;
		  }
		}
	  
		/**
		* Returns the track's lyrics.
		* 
		* @author Ben Dodson
		* @version 6/7/04
		* @since 6/7/04
		*/		
		function getLyrics() {
			$cache = $this->readCache();
			return $cache[19];
		}
		
		/**
		* Returns true, since this element is a leaf.
		* 
		* @author Laurent Perrin 
		* @version 5/10/04
		* @since 5/10/04
		*/			
		function isLeaf() { return true; }

		function setStartTime($time) {
			$this->startTime = $time;
		}
		function getStartTime() {
			return $this->startTime;
		}

	}	
	
?>
