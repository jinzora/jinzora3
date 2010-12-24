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
	
	class jzMediaElement {

		var $name;
		var $path;
		var $data_dir;
	
		// If you want to add specifics to the constructor, call _constructor() as well.
		
		/**
		* Constructor wrapper for a jzMediaElement
		* 
		* @author Ben Dodson
		* @version 5/13/04
		* @since 5/13/04
		*/
		
		function jzMediaElement($par = array(),$mode="path") {
			$this->_constructor($par,$mode);
		}
		
		
		/**
		* Universal Constructor for a jzMediaElement
		* 
		* @author Ben Dodson
		* @version 5/13/04
		* @since 5/13/04
		*/	
		
		function _constructor($arg = array(),$mode) {
			global $backend, $include_path, $jzUSER;

			if (!isset($jzUSER)) {
			  $jzUSER = new jzUser(false);
			  $remove_user = true;
			} else {
			  $remove_user = false;
			}

			$this->data_dir = $include_path. "data/${backend}";
			
			if ($mode == "filename") {
			  $arg = $this->filenameToPath($arg);
			} else if ($mode != "path") {
			  $arg = $this->idToPath($arg);
			}
			
			if (is_string($arg)) {
				// make sure it's well formatted.
			  if ($arg != "") {
			    if ($arg[0] == "/") {
			      $arg = substr($arg,1);
			    }
			    if ($arg[strlen($arg)-1] == "/") { 
			      $arg = substr($arg,0,strlen($arg)-1);
			    }
			  }
				// root?
				if ($arg == "") {
				  $dir = $jzUSER->getSetting('home_dir');
				  if ($dir === false || $jzUSER->getSetting('home_read') === false) {
				    $this->path = array();
				    $this->name = "";
				  } else { 
				    $this->path = explode("/",$dir);
				    $this->name = $this->path[sizeof($this->path)-1];
				  }
				}
				else {
					$arrayize = explode("/",$arg);
					$this->path = $arrayize;
					$this->name = $arrayize[sizeof($arrayize)-1];
				}
			}
			else {
				if ($arg == array()) {
				  $dir = $jzUSER->getSetting('home_dir');
				  if ($dir === false || $jzUSER->getSetting('home_read') === false) {
				    $this->path = array();
				    $this->name = "";
				  } else {
				    $this->path = explode("/",$dir);
				    $this->name = $this->path[sizeof($this->path)-1];
				  }
				} else {
					$this->name = $arg[sizeof($arg)-1];
					$this->path = $arg;
				}
			}

			if ($remove_user) {
			  unset($jzUSER);
			}
		}
	  
	  /**
	   * Reconstructs an element.
	   * This is needed for if an element is moved
	   * and must be internally updated.
	   * 
	   * @author Ben Dodson
	   * @since 8/11/05
	   * @version 8/11/05
	   **/
	  function reconstruct($path) {
	    if (is_string($path)) {
	      $patha = explode("/",$path);
	    } else if (is_array($path)) {
	      $patha = $path;
	    } else {
	      return false;
	    }

	    $this->path = $patha;
	    $this->name = $patha[sizeof($patha)-1];
	  }

		/**
		* Returns the name of the node.
		* 
		* @author Ben Dodson
		* @version 5/13/04
		* @since 5/13/04
		*/
		function getName() {
		  return str_replace("_"," ",$this->name);
		}

		/**
		 * Checks whether or not
		 * this media has been updated within $days days.
		 * If not, returns false. Otherwise, returns
		 * the number of days since the most recent media was added.
		 *
		 * @author Ben Dodson
		 * @version 4/9/05
		 * @since 4/9/05
		 **/
		function newSince($days) {
		  return false;
		}

		/**
		 * Returns the ID for this element.
		 * Default is to return its path
		 * since it is unique
		 * but it is preferred that a backend
		 * overrides this.
		 *
		 * @author Ben Dodson
		 * @version 3/11/05
		 * @since 3/12/05
		 *
		 **/
		function getID() {
		  return $this->getPath("String");
		}

		/**
		 * Stub for setting an element's ID.
		 * Default backend cannot set this yet,
		 * so false is returned.
		 * This should be updated
		 * so users can use things like barcodes.
		 *
		 * @author Ben Dodson
		 * @version 3/11/05
		 * @since 3/12/05
		 *
		 **/
		function setID($id) {
		  return false;
		}

		/**
		 * Converts an ID to a path.
		 * The default is to return the ID
		 * since the id is the path.
		 *
		 * @author Ben Dodson
		 * @since 3/11/05
		 * @version 3/11/05
		 **/
		function idToPath($id) {
		  return $id;
		}

		function filenameToPath($fp) {
			$be = new jzBackend();
			$reg = $be->lookupFile($fp);
			if (false !== $reg) {
				return $reg['path'];
			}
			return false;
		}

		/**
		* Returns the type of the node.
		* 
		* @author Ben Dodson
		* @version 10/31/04
		* @since 10/31/04
		*/
		function getType() {
			return "jzMediaElement";
		}

		/**
		* Returns the depth of the node.
		* 
		* @author Ben Dodson
		* @version 5/14/04
		* @since 5/14/04
		*/		
		function getLevel() {
			return ($this->path == array()) ? 0 : sizeof($this->path);
		}
		
		/**
		* Gets the node's parent (self if root)
		* This function is depricated.
		* Please use getAncestor() instead.
		* 
		* @author Ben Dodson
		* @version 11/3/04
		* @since 11/3/04
		*/		
		function getParent() {
		  global $jzUSER;
			if ($this->getLevel() == 0 || $this->getPath("String") == $jzUSER->getSetting("home_dir")) return $this;
			$newpath = $this->path;
			array_pop($newpath);
			$node = &new jzMediaNode($newpath);
			return $node;
		}
		
		/**
		* Gets the node's natural parent (self if root)
		* This is the 'inverse' of the naturalDepth.
		* 
		* @author Ben Dodson
		* @version 9/18/04
		* @since 9/18/04
		*/		
		function getNaturalParent() {
		  global $jzUSER;
			if ($this->getLevel() == 0 || $this->getPath("String") == $jzUSER->getSetting("home_dir")) return $this;
			$newpath = $this->path;
			array_pop($newpath);
			$node = &new jzMediaNode($newpath);
			if (findPType($node) == "hidden")
				return $node->getNaturalParent();
			return $node;
		}
		

		/**
		 * Returns an ancestor node
		 * of the desired type,
		 * for example $node->getAncestor("artist")
		 *
		 * @author Ben Dodson
		 * @since 4/8/05
		 * @version 4/8/05
		 *
		 **/
		function getAncestor($type) {
		  global $hierarchy;
		  
		  $path = $this->getPath();
		  $retpath = array();
		  
		  if (is_string($hierarchy))
		    $hierarchy = explode("/",$hierarchy);
		  

		  if ($type == "disk") {
		    $parent = $this->getParent();
		    if ($parent->getPType() != "disk") {
		      return false;
		    }
		    return $parent;
		  }

		  for ($i = 0; $i < sizeof($hierarchy); $i++) {
		    if ($hierarchy[$i] == $type) {
		      if ($i < sizeof($path)) {
			$retpath[] = $path[$i];
			$a = new jzMediaNode($retpath);
			return $a;
		      } else {
			return false;
		      }
		    } else {
		      $retpath[] = $path[$i];
		    }
		  }
		  return false;
		}
		

		/**
		* Returns the full path to the node.
		* 
		* @author Ben Dodson
		* @version 5/16/04
		* @since 5/13/04
		*/
		function getPath($type = "array") {
			$type = strtolower($type);
			if ($type == "string") {
				return implode("/",$this->path);
			}
			else {
				return $this->path;
			}
		}

		/**
		* Returns a string that points to the location
		* where this node's non-jinzora-specific data should be stored
		* (album art, text, etc.)
		* 
		* @author Ben Dodson
		* @version 9/18/04
		* @since 9/18/04
		*/
		function getDataPath() {
			global $data_in_filesystem;
			
			if ($this->isLeaf()) {
				$temp = $this->getParent();
				return $temp->getDataPath();
			}
			else {
				if ($data_in_filesystem) {
					$cache = $this->readCache();
					return $cache[13];
				}
				else {
					return $this->data_dir;
				}
			}
		}
		
		/**
		* Returns the physical path for our element.
		* 
		* @author Ben Dodson
		* @version 11/12/04
		* @since 11/12/04
		*/
		function getFilePath() {
		  $cache = $this->readCache();
		  if ($this->isLeaf()) {
		    return $cache[0];
		  } else {
		    return $cache[13];
		  }
		}
		
		/*
		 * Sets this object's filepath.
		 * 
		 * @author Ben Dodson
		 * @since 8/5/06
		 */
		function setFilePath($path) {
		  $cache = $this->readCache();
		  if ($this->isLeaf()) {
		    $cache[0] = $path;
		  } else {
		    $cache[13] = $path;
		  }
		  $this->writeCache($cache);
		}
		
		/**
		* Returns the date the node was added.
		* 
		* @author Ben Dodson
		* @version 6/5/04
		* @since 6/5/04
		*/
		function getDateAdded() {
			$cache = $this->readCache();
			return $cache[6];
		}



		/**
		* Returns the number of times the node has been played.
		* 
		* @author Ben Dodson
		* @version 6/5/04
		* @since 6/5/04
		*/
		function getPlayCount() {
			$cache = $this->readCache();
			return $cache[3];
		}
		
		
		/**
		* Increments the node's playcount, as well
		* as the playcount of its parents.
		* 
		* @author Ben Dodson
		* @version 6/5/04
		* @since 6/5/04
		*/
		function increasePlayCount() {
			$cache = $this->readCache();
			$cache[3] = $cache[3] + 1;

			$this->writeCache($cache);
			if ($this->getLevel() > 0) {
				$next = $this->getParent();
				$next->increasePlayCount();
			}
		}


		/**
		* Sets this element's playcount directly.
		* 
		* @author Ben Dodson
		* @version 6/5/04
		* @since 6/5/04
		*/
		function setPlayCount($n) {
			$cache = $this->readCache();
			$cache[3] = $n;
			$this->writeCache($cache);
		}


		/**
		* Returns the number of times the node has been downloaded.
		* 
		* @author Ben Dodson
		* @version 9/12/04
		* @since 9/12/04
		*/
		function getDownloadCount() {
			$cache = $this->readCache();
			return $cache[12];
		}
		
		
		/**
		* Increments the node's download count, as well
		* as the download count of its parents.
		* 
		* @author Ben Dodson
		* @version 9/12/04
		* @since 9/12/04
		*/
		function increaseDownloadCount() {
			$cache = $this->readCache();
			$cache[12] = $cache[12] + 1;
			$this->writeCache($cache);
			if ($this->getLevel() > 0) {
                   		$next = $this->getParent();
				$next->increaseDownloadCount();
			}
		}


		/**
		* Sets this element's download count directly.
		* 
		* @author Ben Dodson
		* @version 9/12/04
		* @since 9/12/04
		*/
		function setDownloadCount($n) {
			$cache = $this->readCache();
			$cache[12] = $n;
			$this->writeCache($cache);
		}


		/**
		* Returns the number of times the node has been viewed
		* 
		* @author Ben Dodson
		* @version 3/15/05
		* @since 3/15/05
		*/
		function getViewCount() {
			$cache = $this->readCache();
			return $cache[25];
		}
		
		
		/**
		* Increments the node's download count, as well
		* as the download count of its parents.
		* 
		* @author Ben Dodson
		* @version 3/15/05
		* @since 3/15/05
		*/
		function increaseViewCount() {
			$cache = $this->readCache();
			$cache[25] = $cache[25] + 1;
			$this->writeCache($cache);
		}

	        /**
		 * Sets the items viewcount directly.
		 *
		 * @author Ben Dodson
		 * @version 8/11/05
		 * @since 8/11/05
		 **/
	         function setViewCount($n) {
		   $cache = $this->readCache();
		   $cache[25] = $n;
		   $this->writeCache($cache);
		 }

		/**
		* Returns the main art for the node.
		*
		* @author Ben Dodson
		* @version 6/4/04
		* @since 6/4/04
		*/
		function getMainArt($dimensions = false, $createBlank = true, $imageType = "audio") {
			global $jzSERVICES;

			if (isset($this->artpath) && ($this->artpath !== false)) {
			 	$artpath = $this->artpath;
			} else {				
				$cache = $this->readCache();
				$artpath = $cache[1];
			}

			if ($artpath == "-" && $this->isLeaf() === false) {
				// Now let's see if we can get art from the tags
				return false;
				$tracks = $this->getSubNodes("tracks");
				if (count($tracks) > 0){
					$meta = $jzSERVICES->getTagData($tracks[0]->getFilePath());
					// Did we get it?
					if ($meta['pic_name'] <> ""){
						return $jzSERVICES->resizeImage("ID3:". $tracks[0]->getFilePath(), $dimensions, false, $imageType);
					} else {
						return false;
					}
				}
				// Now let's make create the resized art IF needed
			}
			
			if ($dimensions && $artpath != "-"){
				// Now lets check or create or image and return the resized one
				$retVal = $jzSERVICES->resizeImage($artpath, $dimensions, false, $imageType);
				if ($retVal){
					return $retVal;
				} else {
					return $jzSERVICES->createImage($artpath. "/". $this->getName(). ".jpg", $dimensions, $this->getName(), $imageType);
				}
			} else if ($artpath != "-") {
				return $artpath;
			}

			if ($dimensions and $createBlank){
				// Ok, no image, let's try to create one
				// First let's create the default image name
				return $jzSERVICES->createImage($artpath. "/". $this->getName(). ".jpg", $dimensions, $this->getName(), $imageType);
			} else {
				return false;
			}
		}
		
		
		/**
		* Sets the node's main art
		* 
		* @author Ben Dodson
		* @version 6/7/04
		* @since 6/7/04
		*/
		function addMainArt($image) {
			$cache = $this->readCache();
			$cache[1] = $image;
			$this->writeCache($cache);
		}



		/**
		* Returns the miscellaneous artwork attached to the node.
		* 
		* @author 
		* @version 
		* @since 
		*/
		function getRandomArt() {}
		
		
		/**
		* Adds misc. artwork to the node.
		* 
		* @author 
		* @version 
		* @since 
		*/
		function addRandomArt($image) {}



		/**
		* Returns a brief description for the node.
		* 
		* @author 
		* @version 
		* @since 
		*/
		function getShortDescription() {
			$cache = $this->readCache();
			return ($cache[9] == "-") ? false : $cache[9];
		}
		
		
		/**
		* Adds a brief description.
		* 
		* @author Ben Dodson
		* @version 6/5/04
		* @since 6/5/04
		*/
		function addShortDescription($text) {
			$cache = $this->readCache();
			$cache[9] = $text;
			$this->writeCache($cache);
		}


		/**
		* Returns the description of the node.
		* 
		* @author Ben Dodson
		* @version 6/5/04
		* @since 6/5/04
		*/
		function getDescription() {
			$cache = $this->readCache();
			return ($cache[10] == "-") ? false : $cache[10];
		}
		
		
		/**
		* Adds a description.
		* 
		* @author Ben Dodson
		* @version 6/5/04
		* @since 6/5/04
		*/		
		function addDescription($text) {
			$cache = $this->readCache();
			$cache[10] = $text;
			$this->writeCache($cache);
		}

		/**
		* Gets the number of people who have rated this element.
		* 
		* @author Ben Dodson
		* @version 6/11/04
		* @since 6/11/04
		*/
		function getRatingCount() {
			$cache = $this->readCache();
			
			return $cache[5];
		}


		/**
		* Gets the overall rating for the node.
		* 
		* @author Ben Dodson
		* @version 6/5/04
		* @since 6/5/04
		*/
		function getRating() {
			$cache = $this->readCache();
			
			return ($cache[5] == 0) ? 0 : estimateRating($cache[4] / $cache[5]);
		}
		
		
		/**
		* Returns the date the node was added.
		* 
		* @author Ben Dodson
		* @version 6/5/04
		* @since 6/5/04
		*/		
		function addRating($rating, $weight = false) {
			global $rating_weight, $jzUSER;
			
			if ($weight === false) {
			  $weight = $jzUSER->getSetting('ratingweight');
			}

			$cache = $this->readCache();
			$cache[4] = $cache[4] + $rating * $weight;
			$cache[5] = $cache[5] + $weight;
			
			$this->writeCache($cache);
			
			if ($rating_weight > 0 && $this->getLevel() > 0) {
				$next = $this->getParent();
				$next->addRating($rating, $weight * $rating_weight);
			}
		}


		/**
		* Returns the node's discussion
		* 
		* @author Ben Dodson
		* @version 6/7/04
		* @since 6/7/04
		*/
		function getDiscussion() {
			$disc = $this->readCache("discussions");
			return ($disc == array()) ? false : $disc;
		}
		
		
		
		
		/**
		* Adds a blurb to the node's discussion
		* 
		* @author Ben Dodson
		* @version 6/7/04
		* @since 6/7/04
		*/		
		function addDiscussion($text,$username) {
			$discussion = $this->readCache("discussions");
			$i = sizeof($discussion);
			$discussion[$i]['user'] = $username;
			$discussion[$i]['comment'] = $text;
			$discussion[$i]['date'] = time();
			$discussion[$i]['id'] = $i;
			$this->writeCache($discussion,"discussions");
		}

	        /** 
		 * Adds a previously created discussion
		 * to this element.
		 * The input is a discussion array from
		 * $element->getDiscussion();
		 *
		 * @author Ben Dodson
		 * @version 8/11/05
		 * @since 8/11/05
		 */
	         function addFullDiscussion($disc) {
		   $this->writeCache($disc,"discussions");
		 }

		/**
		* Returns the year of the element;
		* if it is a leaf, returns the info from getMeta[year]
		* else, returns the average of the result from its children.
		* Entry is '-' for no year.
		* 
		* @author Ben Dodson
		* @version 6/7/04
		* @since 6//704
		*/		
		function getYear() {
			$cache = $this->readCache();
			
			return $cache[11];
		}
		
		/**
		* Exports the given media so it can
		* be used for something else.
		* 
		* @author Ben Dodson
		* @version 11/20/04
		* @since 11/20/04
		*/		
		function mediaExport($type, $options = array()) {
			switch ($type) {
			// TODO: get artwork and other stuff.
			case "jzLibrary":
				$output = array();
				$i = 0;
				if ($this->isLeaf()) {
					$arr = array($this);
				}
				else {
					$arr = $this->getSubNodes('tracks',-1);
				}
				// have $arr. do something with it.
				foreach ($arr as $track) {
					$output[$i]['path'] = $track->getPath("String");
					if (isset($options['target']) && $options['target'] == "local") {
						$output[$i]['filepath'] = $track->getFileName("host");
					} else {
						$output[$i]['filepath'] = $track->getFileName("general");
					}
					$output[$i]['meta'] = $track->getMeta();
					$i++;
				}
				
				if (isset($options['header'])) {
					// send with header?
					header("Content-Type: text/plain");
					header("Content-Disposition: inline; filename=\"jzLibrary.bak\"");
				}
				echo serialize($output);
				break;
			}
		}
		
		
		/**
		* Hides the element.
		* 
		* @author Ben Dodson
		* @version 10/31/04
		* @since 10/31/04
		*/
		function hide() {
			$cache = $this->readCache();
			$cache[22] = 'true';
			$this->writeCache($cache);
		}
		
		
		/**
		* Unhides the element
		* 
		* @author Ben Dodson
		* @version 10/31/04
		* @since 10/31/04
		*/		
		function unhide() {
			$cache = $this->readCache();
			$cache[22] = 'false';
			$this->writeCache($cache);
		}
		
		
		/**
		* Returns whether or not this element is a leaf.
		* 
		* @author 
		* @version 
		* @since 
		*/
		function isLeaf() {}



		
		/**
		* Returns the cache as an array formatted as specified in updateCache().
		* If the cache does not exist, returns false.
		*
		* @access private
		* @author Ben Dodson
		* @version 5/10/04
		* @since 5/10/04
		*/
		function readCache($type = false) {
			global $backend;

			if ($type == "track" || $type == "leaf" || $type == "leaves") {
				$type = "tracks";
			}
			else if ($type == "node") {
				$type = "nodes";
			}
			else if ($type === false) {
				if ($this->isLeaf()) {
					$type = "tracks";
				}
				else {
					$type = "nodes";
				}
			}
			
			$type = strtolower($type);
			
			if ($this->getLevel() == 0) {
				$cachename = "jzroot";
				
			}
			// To avoid 20000+ entries in data/tracks...
			else if ($this->isLeaf() && $type == "tracks") {
				$temp = $this->getPath();
				array_pop($temp);
				$cachename = implode("---",$temp);
			}
			else {
				$cachename = implode("---",$this->getPath());
			}
			$be = &new jzBackend();
			$datapath = $be->getDataDir();
			
			if (!is_file($datapath . "/$type/" . $cachename)) {
				// Give an empty cache.
				// Note that there are different 'empty caches'.
				if ($type == "nodes") {
					return blankCache("node");
				}
				else if ($type == "tracks") {
					return blankCache("track");
				}
				else {
					return array();
				}
			}
			
			if ($this->isLeaf() && $type == "tracks") {
				$temp = $this->getPath();
				$name = $temp[sizeof($temp)-1];
				$temp = unserialize(file_get_contents($datapath . "/tracks/" . $cachename));

				for ($i = 0; $i < sizeof($temp); $i++) {
					if (isset($this->playpath) && $this->playpath !== false && $this->playpath != "") {
					// the best key:
						if ($temp[$i][0] == $this->playpath) {
							return $temp[$i];
						}
					// if we don't have that:
					} else {
						if ($temp[$i][2] == $name) {
							return $temp[$i];
						}
					}
				}
				return blankCache("track");
			} else {
				return unserialize(file_get_contents($datapath . "/$type/" . $cachename));
			}
		}


		/**
		* Writes the cache.
		* 
		* @access private
		* @author Ben Dodson
		* @version 6/4/04
		* @since 6/4/04
		*/
		function writeCache($cache, $type = false) {
			global $backend;
			
			if ($type == "track" || $type == "leaf" || $type == "leaves") {
				$type = "tracks";
			}
			else if ($type == "node") {
				$type = "nodes";
			}
			else if ($type === false) {
				if ($this->isLeaf()) {
					$type = "tracks";
				}
				else {
					$type = "nodes";
				}
			}
			$type = strtolower($type);
			
			if ($this->getLevel() == 0) {
				$cachename = "jzroot";
				
			}
			else if ($this->isLeaf() && $type == "tracks") {
				$temp = $this->getPath();
				array_pop($temp);
				$cachename = implode("---",$temp);
			}
			else {
				$cachename = implode("---",$this->getPath());
			}
			$be = &new jzBackend();
			$datapath = $be->getDataDir();
			$filename = $datapath . "/$type/" . $cachename;
			
			if ($this->isLeaf() && $type == "tracks") {
				$temp = $this->getPath();
				$name = $temp[sizeof($temp)-1];
				$block = unserialize(@file_get_contents($filename));
				if (!$handle = @fopen($filename,"w")) {
					return false;
				}
				
				if ($block === false) {
					$block = array();
					$block[] = $cache;
					fwrite($handle,serialize($block));
					fclose($handle);
					return true;
				}
				for ($i = 0; $i < sizeof($block); $i++) {
					if (isset($this->playpath) && $this->playpath !== false && $this->playpath != "") {
						if ($block[$i][0] == $this->playpath) {
							$block[$i] = $cache;
							fwrite($handle,serialize($block));
							fclose($handle);
							return true;
						}
					}
					else {
						if ($block[$i][2] == $name) {
							$block[$i] = $cache;
							fwrite($handle,serialize($block));
							fclose($handle);
							return true;
						}
					}
				}
				$block[] = $cache;
				fwrite($handle,serialize($block));
				fclose($handle);
				return true;
				
			} else {
				if (!$handle = @fopen($filename,"w")) {
					touch($filename);
					if (!$handle = @fopen($filename,"w")) {
						die("could not open empty cache ($filename) for writing.");
					}
				}
				fwrite($handle,serialize($cache));
			}
			fclose($handle);
			return true;
		}

		/**
		* Removes the caches found for the node.
		* 
		* @access private
		* @author Ben Dodson
		* @version 6/7/04
		* @since 6/7/04
		*/
		function deleteCache() {
			global $backend;
			
			if ($this->isLeaf()) {
				return false;
				// This is not used right now, but it could be written.
			}
			
			if ($this->getLevel() == 0) {
				$cachename = "jzroot";
				
			}
			else {
				$cachename = implode("---",$this->getPath());
			}
			$filename = $this->data_dir . "/nodes/${cachename}";
			@unlink($filename);
			$filename = $this->data_dir . "/tracks/${cachename}";
			@unlink($filename);
			
			// Let's keep discussions for now, since they can't be gotten back...
			// $filename = $this->data_dir "/discussions/${cachename}";
			// unlink($filename);
		}

		/**
		 * Returns a playable HREF for this element
		 * @author Ben Dodson
		 * @since 1/3/08
		 */
		function getPlayHREF($random=false,$limit=0) {
		  global $jzUSER;
		  // do they have permissions or should we just do text?
		  if (!checkPermission($jzUSER,"play",$this->getPath("String"))) {
		    return null;
		  } 
		  $clips=false;

		  $arr = array();
		  $arr['jz_path'] = $this->getPath("String");
		  $arr['action'] = "playlist";

		  if (false !== strpos($_SERVER['PHP_SELF'],'api.php')) {
			$arr['target']='raw';
		  }

		  if ($limit != 0) { $arr['limit'] = $limit; }
		  if ($random){ $arr['mode'] = "random"; }
		  if ($clips){ $arr['clips'] = "true"; }
		  if ($this->isLeaf()) {
		    $arr['type'] = 'track';
		  } else {
		    $arr['type'] = 'node';
		  }
		  if (isset($_GET['frame'])){
		    $arr['frame'] = $_GET['frame'];
		  }
		  if (isset($_REQUEST['user'])) {
		    $arr['user'] = $_REQUEST['user'];
		  }
		  if (isset($_REQUEST['pass'])) {
		    $arr['pass'] = $_REQUEST['pass'];
		  }
		  if (isset($_REQUEST['pw_hashed'])) {
		    $arr['pw_hashed'] = $_REQUEST['pw_hashed'];
		  }
		  if (isset($_REQUEST['jb_id'])) {
		    $arr['jb_id'] = $_REQUEST['jb_id'];
		  }
		  return urlize($arr);
		}

		function getDownloadHREF() {
		  $arr = array();
		  $arr['jz_path'] = $this->getPath("String");
		  $arr['action'] = 'download';

		  if ($this->isLeaf()) {
		    $arr['type'] = 'track';
		  } else {
		    $arr['type'] = 'node';
		  } 
		  if (isset($_REQUEST['user'])) {
		    $arr['user'] = $_REQUEST['user'];
		  }
		  if (isset($_REQUEST['pass'])) {
		    $arr['pass'] = $_REQUEST['pass'];
		  }
		  if (isset($_REQUEST['pw_hashed'])) {
		    $arr['pw_hashed'] = $_REQUEST['pw_hashed'];
		  }
		  return urlize($arr);
		}
	}
?>
