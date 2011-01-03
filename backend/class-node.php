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
	
	class jzMediaNodeClass extends jzMediaElement {

		var $natural_depth; // if the level before us is 'hidden'
		
		/**
		* Constructor for a jzMediaNodeClass
		* 
		* @author Ben Dodson
		* @version 5/14/04
		* @since 5/14/04
		*/	
		function jzMediaNodeClass($par = array(),$mode = "path") {
			$this->_constructor($par,$mode);
		}
		function _constructor($par = array(),$mode) {
			$this->natural_depth = 1;
			parent::_constructor($par,$mode);
		}

		/**
		* Returns the type of the node.
		* 
		* @author Ben Dodson
		* @version 10/31/04
		* @since 10/31/04
		*/
		function getType() {
			return "jzMediaNode";
		}
		
		/**
		* Gets the 'natural depth' of this node.
		* This has no real purpose outside of the class. 
		*
		* @author Ben Dodson
		* @version 5/21/04
		* @since 5/21/04
		*/	
		function getNaturalDepth() {
			return $this->natural_depth;
		}
		
		/**
		* Sets the natural depth (for searching, counting etc.) of this node.
		* Useful if the node is preceded by a hidden level.
		* 
		* @author Ben Dodson
		* @version 5/14/04
		* @since 5/14/04
		*/	
		function setNaturalDepth($n) {
			$this->natural_depth = $n;
		}		

		/**
		* Counts the number of subnodes $distance steps down of type $type.
		* $distance = -1 means do it recursively.
		* 
		* @author Ben Dodson
		* @version 5/14/04
		* @since 5/14/04
		*/
		function getSubNodeCount($type='both', $distance = false) {
			
			if ($distance === false) {
				$distance = $this->getNaturalDepth();	
			}

			// alias:
			if ($type == "tracks") {
				$type = "leaves";
			}
			//  EASY METHOD:
			return sizeof($this->getSubNodes($type,$distance,true,0)); // $random = true is faster than sorting.
			// POSSIBLE TODO 1) make another cache for counting.
			// POSSIBLE TODO 2) don't return the array; just count as you go.
		}

		/**
		* Returns the subnodes as an array. A $distance of -1 means do it recursively.
		* 
		* @author Ben Dodson
		* @version 6/4/04
		* @since 6/4/04
		*/
		function getSubNodes($type='nodes',$distance=false, $random=false, $limit=0, $hasArt = false, $offset=false) {
		
		  if ($distance === false) {
		    $distance = $this->getNaturalDepth();	
		  }
		  
		  // alias:
		  if ($type == "tracks") {
		    $type = "leaves";
		  }
		  
		  // 2 cases:
		  $search = array();
		  $vals = array();
		  
		  // 1) recursive
		  if ($distance <= 0) {
			$node = $this;
			$search[] = $node;
			while ($search != array()) {
				$node = array_pop($search);
				$cache = $node->readCache("nodes");
				if ($nodearray = $cache[7]) {
					foreach ($nodearray as $name) {
						$search[] = &new jzMediaNode($node->getPath("String") . "/" . $name);
			
						if ($type == "nodes" || $type == "both") {
						$me = new jzMediaNode($node->getPath("String") . "/" . $name);
						if ($hasArt) {
							if ($me->getMainArt() != "-" && $me->getMainArt() !== false)
								$vals[] = $me;
							} else {
								$vals[] = $me;
							}
						}
					}
				}
				if ($type == "leaves" || $type == "both") {
					if ($trackarray = $cache[8]) {
						foreach ($trackarray as $track) {
							$vals[] = &new jzMediaTrack($node->getPath("String") . "/" . $track);
						}
					}
				}
			}
		  }
		  // 2: not.
		  else {
			$i = 1;
			$node = $this;
			$search[] = $node;
			while ($distance != $i) {
				$i++;
				$temp = array();
				while ($search != array()) {
					$node = array_pop($search);
					$cache = $node->readCache("nodes");
					if ($nodearray = $cache[7]) {
						foreach ($nodearray as $name) {
							$temp[] = &new jzMediaNode($node->getPath("String") . "/" . $name);
						}
					}
				}
				$search = $temp;
			}

			foreach ($search as $node) {
				$cache = $node->readCache("nodes");
				if ($type == "both" || $type == "nodes") {
					if ($nodearray = $cache[7]) {
						foreach ($nodearray as $name) {
							$me = new jzMediaNode($node->getPath("String") . "/" . $name);
							if ($hasArt) {
							  if ($me->getMainArt() != "-" && $me->getMainArt() !== false) {
									$vals[] = $me;
							  }
							} else {
							  $vals[] = $me;
							}
						}
					}
				}
				if ($type == "both" || $type == "leaves") {
				  if ($trackarray = $cache[8]) {
				    foreach ($trackarray as $track) {
				      $vals[] = &new jzMediaTrack($node->getPath("String") . "/" . $track);
				    }
				  }
				}
			}
		    
		  }
		  if ($random === true) {
		    srand((float)microtime() * 1000000);
		    shuffle($vals);
		  }
		  else if ($random === false) {
		    usort($vals, "compareNodes");
		  }
		  else {
		    // do nothing.
		  }

		  if ($limit > 0 && $limit < sizeof($vals)) {
		    $final = array();
		    for ($i = 0; $i < $limit; $i++) {
		      $final[] = $vals[$i];
		    }
		    return $final;
		  }
		  else {
		    return $vals;
		  }
		}
		

		/**
		* Returns the 'top' subnodes. $distance = -1 is recursive.
		* 
		* $top_type is one of:
		* most-played, best-rated
		*
		* @author 
		* @version 
		* @since 
		*/		
		function getTopSubNodes($type='nodes',$top_type='most-played', $distance=false, $limit=0) {
			if ($distance === false) {
				$distance = $this->getNaturalDepth();	
			}
			// alias:
			if ($type == "tracks") {
				$type = "leaves";
			}

		}
		
		/**
		* Returns the subnode named $name.
		* 
		* @author Ben Dodson
		* @version 5/16/04
		* @since 5/16/04
		*/		
		function getSubNode($name) {
			$p = $this->path;
			$p[] = $name;
			// TODO: check for jzMediaTrack or jzMediaNode?
			// I'm not sure how needed this function is anyways...
			return jzMediaElement($p);
			
		}

		/**
		 * Completely handles a power search
		 *
		 * @author Ben Dodson
		 * @since 4/6/05
		 * @version 4/6/05
		 *
		 **/
		function powerSearch() {
		  $roots = array();
		  $roots[] = $this;

		  // Exploit our hierarchical design.
		  // A relational database should handle this search differently.
		  if (isset($_POST['genre']) && $_POST['genre'] != "") {
		    $roots = $this->search($_POST['genre'],"nodes",distanceTo("genre",$this));
		  }

		  if (isset($_POST['artist']) && $_POST['artist'] != "") {
		    $new_roots = array();
		    foreach ($roots as $r) {
		      $new_roots = $new_roots + $r->search($_POST['artist'],"nodes",distanceTo("artist",$r));
		    }
		    $roots = $new_roots;
		  }

		  if (isset($_POST['album']) && $_POST['album'] != "") {
		    $new_roots = array();
		    foreach ($roots as $r) {
		      $new_roots = $new_roots + $r->search($_POST['album'],"nodes",distanceTo("album",$r));
		    }
		    $roots = $new_roots;
		  } 

		  // If nothing in their query is track-specifc, we are done.
		  if (powerSearchType() != "tracks") {
		    return $roots;
		  }

		  $results = array();
		  $metasearch = getSearchMeta();
		  foreach ($roots as $r) {
		    $results = $results + $r->search($_POST['song_title'],"tracks",-1,0,$_POST['operator'],$metasearch);
		  }
		  
		  return $results;
		}

		/**
		* Searches a specified level for elements.
		* The level is -1 for any level.
		* These parameters may change before the coding gets done.
		* The 'op' is one of: and|or|exact
		* If it is 'exact', the search term should be a string.
		*
		* @author Ben Dodson
		* @version 9/21/04
		* @since 9/20/04
		*/
		function search($searchArray2, $type='both', $depth = -1, $limit = 0, $op = "and", $metasearch = array(), $exclude = array()) {

		  // for now, no search by id:
		  if ($type == "id") {
		    return array();
		  }
			// alias:
			if ($type == "tracks") {
				$type = "leaves";
			}
			
			if ($depth === false) {
				$depth = $this->getNaturalDepth();
			}
			
			// allow strings as well as arrays for searching:
			if (is_string($searchArray2) && $op != "exact") {
				if (stristr($searchArray2,"\"") === false) {
					if ($searchArray2 == "")
						$searchArray = array();
					else {
						$searchArray = explode(" ",$searchArray2);
					}
				}
				else { // gets nasty..
					$open_quote = false;
					$searchArray = array();
					$word = "";
					for ($i = 0; $i < strlen($searchArray2); $i++) {
						if ($searchArray2[$i] == ' ' && $open_quote == false) {
							$searchArray[] = $word;
							$word = "";
						}
						else if ($searchArray2[$i] == '"') {
							$open_quote = !$open_quote;
						}
						else {
							$word .= $searchArray2[$i];
						}
					}
					if ($word != "") {
						$searchArray[] = $word;
					}
				}
			} else {
				$searchArray = $searchArray2;
			}
			// Exclude array, too:
			if (is_string($exclude)) {
				if ($exclude == "") {
					$excludeArray = array();
				}
				else if (stristr($exclude,"\"") === false) {
					if ($exclude == "") {
						$excludeArray = array();
					} else {
						$excludeArray = explode(" ",$exclude);
					}
				}
				else { // gets nasty..
					$open_quote = false;
					$excludeArray = array();
					$word = "";
					for ($i = 0; $i < strlen($exclude); $i++) {
						if ($exclude[$i] == ' ' && $open_quote == false) {
							$excludeArray[] = $word;
							$word = "";
						}
						else if ($exclude[$i] == '"') {
							$open_quote = !$open_quote;
						}
						else {
							$word .= $exclude[$i];
						}
					}
					if ($word != "") {
						$excludeArray[] = $word;
					}
				}
			} else {
				$excludeArray = $exclude;
			}
			
			// Clean up:
			$tmp = array();
			if (is_array($searchArray)) {
			  foreach ($searchArray as $term) {
			    if (!($term == "" || $term == " ")) {
			      $tmp[] = $term; 
			    }
			  }
			  $searchArray = $tmp;
			}
			$tmp = array();
			foreach ($excludeArray as $term) {
			  if (!($term == "" || $term == " ")) {
			    $tmp[] = $term; 
			  }
			}
			$excludeArray = $tmp;
			
			// LYRICS SEARCHING: different kind of search.
			if ($type == "lyrics") {
			  $matches = array();
			  $kids = $this->getSubNodes("tracks",$depth,"leave",0);
			  foreach ($kids as $kid) {
			    $meta = $kid->getMeta();
				$path = $kid->getPath("String");
			    if ($op == "exact") {
			      if (stristr($meta['lyrics'], $searchArray2) !== false) {
				$matches[] = $kid;
			      }
			    } else if ($op == "or") {
			      $valid = true;
			      if ($excludeArray != array()) {
				foreach($excludeArray as $word) {
				  if (stristr($meta['lyrics'],$word) !== false) {
				    $valid = false;
				  }
				}
			      }
			      if ($valid) {
				if (sizeof($searchArray) == 0) {
				  $matches[] = $kid;
				  if ($limit > 0 && sizeof($matches) >= $limit) {
				    return $matches;
				  }
				}
				else {
				  foreach($searchArray as $word) {
				    if (stristr($meta['lyrics'],$word) !== false) {
				      $matches[] = $kid;
				      if ($limit > 0 && sizeof($matches) >= $limit) {
					return $matches;
				      }
				    }
				  }
				}
			      }
			    } else { // $op == "and"
			      $possible_match = false;
			      $all_words = true;
			      $valid = true;
			      if ($excludeArray != array()) {
				foreach($excludeArray as $word) {
				  if (stristr($meta['lyrics'],$word) !== false) {
				    $valid = false;
				  }
				}
			      }
			      if ($valid) {
				if (sizeof($searchArray) == 0) {
				  $matches[] = $kid;
				  if ($limit > 0 && sizeof($matches) >= $limit) {
				    return $matches;
				  }
				}
				else {
				  foreach($searchArray as $word) {
				    if (stristr($meta['lyrics'],$word) !== false) {
				      $possible_match = true;
				    }
				    else if (stristr($path,$word) === false) {
				      $all_words = false;
				    }
				  }
				}
				if ($possible_match && $all_words) {
				  $matches[] = $kid;
				  if ($limit > 0 && sizeof($matches) >= $limit) {
				    return $matches;
				  }
				}
			      }
			    }
			  }
			  return $matches;
			}




			$kids = $this->getSubNodes($type,$depth,"leave",0); 
			// don't sort our nodes, because it takes time.
			// but don't randomize either.
			$matches = array();
			foreach ($kids as $kid) {
				$path = $kid->getPath("String");
				$pathArray = $kid->getPath();
				// NOTE: The 'name' of the child is taken from the filename,
				// NOT from the ID3 or any other metadata method.
				// This is currently (9/21/04) DIFFERENT than what $this->getName() returns
				// ..and it is MUCH faster!!
				$name = $pathArray[sizeof($pathArray)-1];
				// op == 'exact'
				if ($op == "exact") {
					if ($name == $searchArray2) {
						$matches[] = $kid;
						if ($limit > 0 && sizeof($matches) >= $limit) {
							return $matches;
						}
					}
				// op == 'or'
				} else if ($op == "or") {
					$valid = true;
					if ($excludeArray != array()) {
						foreach($excludeArray as $word) {
							if (stristr($name,$word) !== false) {
								$valid = false;
							}
						}
					}
					if ($valid) {
						if (sizeof($searchArray) == 0) {
							$matches[] = $kid;
							if ($limit > 0 && sizeof($matches) >= $limit) {
								return $matches;
							}
						}
						else {
							foreach($searchArray as $word) {
					 			if (stristr($name,$word) !== false) {
									$matches[] = $kid;
									if ($limit > 0 && sizeof($matches) >= $limit) {
										return $matches;
									}
					   			}
							}
						}
					}
			   	}
			   	else { // "and"
			   		$possible_match = false;
			   		$all_words = true;
			   		$valid = true;
			   		if ($excludeArray != array()) {
						foreach($excludeArray as $word) {
							if (stristr($name,$word) !== false) {
								$valid = false;
							}
						}
					}
					if ($valid) {
						if (sizeof($searchArray) == 0) {
							$matches[] = $kid;
							if ($limit > 0 && sizeof($matches) >= $limit) {
								return $matches;
							}
						}
						else {
							foreach($searchArray as $word) {
						 		if (stristr($name,$word) !== false) {
									$possible_match = true;
						   		}
								else if (stristr($path,$word) === false) {
						       				$all_words = false;
						     		}
						  	}
					  	}
					  	if ($possible_match && $all_words) {
					  		$matches[] = $kid;
					  		if ($limit > 0 && sizeof($matches) >= $limit) {
									return $matches;
							}
					  	}
					  }
				}
			}
			if ($metasearch != array() && $type == "leaves") {
			  $matches = filterSearchResults($matches,$metasearch);
			}
			return $matches;
		}
		
		
		/**
		* Updates the cache using this node as a base.
		* 
		* @author Ben Dodson
		* @version 6/7/04
		* @since 5/17/04
		*/		
		function updateCache($recursive = true, $root = false, $showStatus = false, $readID3 = true) {		

			/* Serialized array:
			 * nodes/path---to---node:
			 * (root is called jzroot)
			 * See backend.php.
			 */

			 $this->updateCacheHelper($recursive, $this->getNaturalDepth()-1, $root, $showStatus, $readID3);
			 $be = &new jzBackend();
			 $be->setUpdated();
		}
		
		function updateCacheHelper($recursive, $levelsLeft, $root, $showStatus, $readID3) {
			global $audio_types, $video_types, $ext_graphic, $default_art, $backend,
			$track_num_seperator, $hierarchy, $protocols;

			if ($root !== false)
				$mediapath = $root;
			else
				$mediapath = $this->getFilePath();
				if ($mediapath == "-") { // let's find it..
				  $parent = $this->getParent();
				  $mediapath = $parent->getFilePath() . "/" . $this->getName();
				}

			$nodepath = $this->getPath("String");
			
			/* Echo out our current status, if they want it: */
			if ($showStatus === true) {
				showStatus($mediapath);
			} else if ($showStatus == "cli") {
			  echo word("Scanning: ") . $mediapath . "\n";
			}
			
			// First add $this.
			// Was I already cached?
			$cache = $this->readCache();
			
			if ($cache[0] == "-") { $cache[0] = $nodepath; }
			if ($cache[13] == "-") { $cache[13] = $mediapath; }
			if ($cache[6] == "-") { $cache[6] = jz_filemtime($mediapath); }
			if ($cache[15] == "-") {
				$ptype = findPType($this);
				$cache[15] = $ptype;
			}
			$blankfilecache = blankCache("track");
			// Recurse and add $this's media files.
			if (!($handle = opendir($mediapath))) 
				die("Could not access directory $mediapath");
				
			// scan for info while going through directory:
			$trackcount = 0;
			$new_nodes = $cache[7];
			$new_tracks = $cache[8];	
			$bestImage = "";
			$bestDescription = "";
			while ($file = readdir($handle)) {
				$childpath = ($nodepath == "") ? $file : $nodepath . "/" . $file;
				$fullchildpath = $mediapath . "/" . $file;
				if ($file == "." || $file == "..") {
					continue;
				}
				else if (is_dir($fullchildpath)) {
					if ($recursive) {
						$next = &new jzMediaNode($childpath);
						$next->updateCacheHelper(true,$levelsLeft, $fullchildpath,$showStatus,$readID3);
					} else {
						if ($levelsLeft === false && $this->getNaturalDepth() > 1) {
							$next = &new jzMediaNode($childpath);
							$next->updateCacheHelper(false,$this->getNaturalDepth()-2,$fullchildpath,$showStatus,$readID3);
						} else if ($levelsLeft > 0) {
							$next = &new jzMediaNode($childpath);
							$next->updateCacheHelper(false,$levelsLeft-1,$fullchildpath,$showStatus,$readID3);
						}
					}

					if ($new_nodes != array()) {
						$key = array_search($file,$new_nodes); 
						if (false === $key) {
							$new_nodes[] = $file;
							$next = &new jzMediaNode($childpath);
						}
					} else {
						$new_nodes[] = $file;
						$next = &new jzMediaNode($childpath);
					}
					
				}
				else {
					if (preg_match("/\.(txt)$/i", $file)) {
						// TODO: GET THE CORRECT DESCRIPTION IN $bestDescription
						// $bestDescription = $fullchildpath;
					
					}
					else if (preg_match("/\.($ext_graphic)$/i", $file) && !stristr($file,".thumb.")) {
						// An image
					        if (@preg_match("/($default_art)/i",$file)) {
							$bestImage = $fullchildpath;
						}
						else if ($bestImage == "") {
							$bestImage = $fullchildpath;
						}
					}
					else if (preg_match("/\.($audio_types)$/i", $file)
					      || preg_match("/\.($video_types)$/i", $file)) {
						//* * * A track * * * *//
						// Add it to the track list.
						if ($new_tracks != array()) {
							$key = array_search($file,$new_tracks); 
							if (false === $key) {
								$new_tracks[] = $file;
							}
						} else {
							$new_tracks[] = $file;
						}
						
						// And at it's details..
						$childnode = &new jzMediaTrack($childpath);
						if (($cache[2] == "-" || $cache[2] < date("U",jz_filemtime($fullchildpath))) || !$childnode->readCache()) {
							// Add as a new/updated track.
							
							$filecache[$trackcount] = $childnode->readCache();

							if ($filecache[$trackcount][0] == "-") {
								$filecache[$trackcount][0] = $fullchildpath;
								$filecache[$trackcount][6] = jz_filemtime($fullchildpath);
								$filecache[$trackcount][2] = $file;
							}
							 //////////
							// META //
						       //////////
						       
							$track = &new jzMediaTrack($childpath);
							$track->playpath = $fullchildpath;
							if ($readID3 === true) {
							  $meta = $track->getMeta("file"); // read meta info from the file;
							} else {
							  $meta = array();
							}
							
							
							$filecache[$trackcount][7] = $meta['title'];
							$filecache[$trackcount][8] = $meta['frequency'];
							$filecache[$trackcount][9] = $meta['comment'];
							$filecache[$trackcount][11] = $meta['year'];
							$filecache[$trackcount][13] = $meta['size'];
							$filecache[$trackcount][14] = $meta['length'];
							$filecache[$trackcount][15] = $meta['genre'];
							$filecache[$trackcount][16] = $meta['artist'];
							$filecache[$trackcount][17] = $meta['album'];
							$filecache[$trackcount][18] = $meta['type'];
							$filecache[$trackcount][19] = $meta['lyrics'];
							$filecache[$trackcount][20] = $meta['bitrate'];
							$filecache[$trackcount][21] = $meta['number'];
							
							// Now let's see if there is a description file...
							$desc_file = str_replace(".". $meta['type'],"",$fullchildpath). ".txt";
							$long_description = "";
							if (is_file($desc_file) and filesize($desc_file) <> 0){
								// Ok, let's read the description file
								$handle2 = fopen($desc_file, "rb");
								$filecache[$trackcount][10] = fread($handle2, filesize($desc_file));
								fclose($handle2);
							} else { 
								$filecache[$trackcount][10] = "";
							}
							// Now let's see if there is a thumbnail for this track
							$filecache[$trackcount][1] = searchThumbnail($fullchildpath);
						} else {
							// slow but necessary..
							//$filecache[$trackcount] = $childnode->readCache();
						}
						$trackcount++;
						
						// Let's track this
						writeLogData('importer',"Importing track: ". $fullchildpath);
						$_SESSION['jz_import_full_progress']++;
					}
				}
			}

			if ($new_nodes != array()) {
          foreach ($new_nodes as $i => $my_path) { 
					$me = &new jzMediaNode($nodepath . "/" . $my_path);
					if ($me->getFilePath() == "-") {
						$arr = explode("/",$my_path);
						$mfp = $this->getFilePath() . "/" . $arr[sizeof($arr)-1];
					} else {
						$mfp = $me->getFilePath(); 
					}
					if (!is_dir($mfp)) {
					  // TODO: The commented out part should check to see if there are 'permanent' subnodes.
					  // It is possible a directory was created to house links (and does not have
					  // any data on the filesystem)
						$remove_me = true;
						$list = $me->getSubNodes("tracks",-1);
						foreach ($list as $el) {
							if (stristr($el->getFilePath(),"://")) {
								$remove_me = false;
								break;
							}
						} 

						if ($remove_me) {
							$me->deleteCache();
							unset($new_nodes[$i]);
					  }
					}
				}	
			}
			if ($new_tracks != array()) {
                                foreach ($new_nodes as $i => $my_path) {
					$me = &new jzMediaTrack($nodepath . "/" . $my_path);
					if (!is_file($fpath = $me->getFilePath())) {
						$valid = false;
						$parr = explode("|",$protocols);
						if (strlen($fpath) > 2) {
							foreach ($parr as $p) {
								if (stristr($fpath,$p) !== false) {
									$valid = true;
								}
							}
						}
						if (!$valid) {
							$me->deleteCache();
							unset($new_tracks[$i]);
						}
					}
				}	
			}
			
			
			// Update $this
			if ($bestImage != "") {
				$cache[1] = $bestImage;
			}
			if ($bestDescription != "") {
				$cache[10] = file_get_contents($bestDescription);
			}
			$cache[2] = date("U");
			natcasesort($new_nodes);
			$cache[7] = array_values($new_nodes);
			natcasesort($new_tracks);
			$cache[8] = array_values($new_tracks);
			// * * * * * * * *
			// Write the cache.
			$this->writeCache($cache,"nodes");
			if ($filecache != array()) {
				$this->writeCache($filecache,"tracks");
			}
		}

		/**
		* Returns whether or not this is a leaf (which it isn't)
		* 
		* @author Laurent Perrin
		* @version 5/10/04
		* @since 5/10/04
		*/
		function isLeaf() { return false; }




		/**
		* Returns the nodes starting with the specified letter.
		* if $letter is "#" it returns nodes that don't start with a-z.
		* if $letter is "*" it returns all nodes.
		* 
		* @author Ben Dodson <bdodson@seas.upenn.edu>
		* @version 6/4/04
		* @since 5/11/04
		*/
		function getAlphabetical($letter, $type = "nodes", $depth = false) { 
			if ($depth === false) {
				$depth = $this->getNaturalDepth();	
			}
			if ($depth == 0) { 
			 	$first = strtolower(substr($this->getName(),0,1));
			 	$letter = strtolower($letter);
			 	if ($letter == "#") {
					if (!('a' <= $first && $first <= 'z')) {
						return array($this);
					}
				} else if ($letter == "*") {
					return array($this);
				}
				else { // standard case
					if ($letter == $first) {
						return array($this);
					}
				}
			}
			else { 
				$array = array();
				foreach ($this->getSubNodes() as $subnode) {
					if (!$subnode->isLeaf()) {
						if (($arr = $subnode->getAlphabetical($letter, $type,$depth - 1)) != array()) {
							foreach ($arr as $item) {
								$array[] = $item;
							}
						}
					}
				}
				usort($array,"compareNodes");
				return $array;
			}
		}
		
		/**
		* Marks this node as 'featured.'
		* 
		* @author Ben Dodson
		* @version 6/8/04
		* @since 6/8/04
		*/
		function addFeatured() {
			// Just 1 cache: from the root node.
			$root = &new jzMediaNode();
			$cache = $root->readCache("featured");
			$cache[] = $this->getPath("String");
			$root->writeCache($cache, "featured");
		}
		
		/**
		* Checks to see if this node is featured
		* 
		* @author Ben Dodson
		* @version 6/8/04
		* @since 6/8/04
		*/
		function isFeatured() {
			$root = &new jzMediaNode();
			$path = $this->getPath("String");
			$cache = $root->readCache("featured");
			for ($i = 0; $i < sizeof($cache); $i++) {
				if ($cache[$i] == $path) {
					return true;
				}
			}
			return false;
		}
		
		
		/**
		* Removes this node from the featured list.
		* 
		* @author Ben Dodson
		* @version 6/8/04
		* @since 6/8/04
		*/
		function removeFeatured() {
			$root = &new jzMediaNode();
			$path = $this->getPath("String");
			$cache = $root->readCache("featured");
			for ($i = 0; $i < sizeof($cache); $i++) {
				if ($cache[$i] == $path) {
					$cache[$i] = $cache[sizeof($cache)-1];
					unset($cache[sizeof($cache)-1]);
					$root->writeCache($cache, "featured");
					return true;
				}
			}
			return false;
		}
		
		/**
		* Returns featured nodes. Limit 0 means get them all.
		* Order is random.
		* 
		* @author Ben Dodson
		* @version 6/8/04
		* @since 6/8/04
		*/
		function getFeatured($distance = -1, $limit = 1) {
			$root = &new jzMediaNode();
			$cache = $root->readCache("featured");
			$path = $this->getPath("String");
			$level = $this->getLevel();
			$vals = array();
			$i = 0;
			
			if ($distance === false) {
				$distance = $this->getNaturalDepth();	
			}
			
			if (sizeof($cache) == 0) { return false; }
			
			srand((float) microtime() * 10000000);
			$keys = array_rand($cache,sizeof($cache));
			
			if (!is_array($keys)) {
				// only 1 key:
				$keys = array($keys);
			}
			
			foreach ($keys as $key) {
				$temp = $cache[$key];
				if ($path == "" || (strpos($temp, $path) == 0  && strpos($temp, $path) !== false && $temp != $path)) {
					$temp2 = &new jzMediaNode($temp);
					if ($distance <= 0 || $temp2->getLevel() - $level == $distance) {
						$vals[$i] = $temp2;
						$i++;
						if ($i >= $limit) {
							return $vals;
						}
					}
				}
			}
			return $vals;
			
		}
		
		
		/**
		* Adds a request. $type is either 'request' or 'broken.'
		* 
		* 
		* @author Ben Dodson
		* @version 9/1/04
		* @since 9/1/04
		*/
		function addRequest($entry, $comment, $user, $type = "request") {
			// Just 1 cache: from the root node.
			$root = &new jzMediaNode();
			$cache = $root->readCache("request");
			
			$new = array();
			$new['entry'] = $entry;
			$new['comment'] = $comment;
			$new['user'] = $user;
			$new['type'] = strtolower($type);
			$new['id'] = md5($entry . $comment . $user . $type);
			$new['path'] = $this->getPath("String");
			
			$cache[] = $new;
			$root->writeCache($cache, "request");
		}
		
		
		/**
		* Gets requests. Default is to do so recursively and return all types.
		* Returns results as an array with the following fields:
		* entry,comment,user,type,id,path
		*
		* Path is the parent path for the request.
		* Type is the type of the request, currently 'broken' or 'request'
		* 
		* @author Ben Dodson
		* @version 9/1/04
		* @since 9/1/04
		*/
		function getRequests($distance = -1, $type = "all") {
			$root = &new jzMediaNode();
			$cache = $root->readCache("request");
			$path = $this->getPath("String");
			$level = $this->getLevel();
			$results = array();
			
			if (sizeof($cache) == 0) { return false; }
			
			for ($i = 0; $i < sizeof($cache); $i++) {
			
				if (strtolower($type) == "all" || strtolower($type) == $cache[$i]['type']) {
					$temp = $cache[$i]['path'];
					$tnode = &new jzMediaNode($temp);
					if ($path == "" || (strpos($temp, $path) == 0  && strpos($temp, $path) !== false)) {
						if ($distance <= 0) {
							$results[] = $cache[$i];
						}
						else if ($tnode->getLevel() - $level == $distance) {
							$results[] = $cache[$i];
						}
					}
				}
			}
			return $results;
		}
		
		
		/**
		* Removes request with id $id. (from getRequests['id']).
		* 
		* 
		* @author Ben Dodson
		* @version 9/1/04
		* @since 9/1/04
		*/
		function removeRequest($id) {
			$root = &new jzMediaNode();
			$cache = $root->readCache("request");
			for ($i = 0; $i < sizeof($cache); $i++) {
				if ($cache[$i]['id'] == $id) {
					$cache[$i] = $cache[sizeof($cache)-1];
					unset($cache[sizeof($cache)-1]);
					$root->writeCache($cache, "request");
					return true;
				}
			}
			return false;
		}
		
		/**
		* Adds link to the given node.
		* 
		* 
		* @author Ben Dodson
		* @version 9/18/04
		* @since 9/18/04
		*/
		function addLink ($node) {
			$cache = $this->readCache();
			$linkarray = $cache[14];
			$count = sizeof($linkarray);
			
			
			// let's not add it twice.
			$path = $node->getPath("String");
			$type = ($node->isLeaf()) ? 'leaf' : 'node';
			for ($i = 0; $i < $count; $i++) {
				if ($linkarray[$i]['path'] == $path && $linkarray[$i]['type'] == $type) {
					return true;
				}
			}
			
			
			$linkarray[$count]['path'] = $node->getPath("String");
			if ($node->isLeaf()) {
				$linkarray[$count]['type'] = 'leaf';
			}
			else {
				$linkarray[$count]['type'] = 'node';
			}
			$cache[14] = $linkarray;
			$this->writeCache($cache);
		}
		
		/**
		* Removes the link to the given node.
		* 
		* 
		* @author Ben Dodson
		* @version 9/18/04
		* @since 9/18/04
		*/
		function removeLink ($node) {
			$cache = $this->readCache();
			$linkarray = $cache[14];
			$newlinkarray = array();
			$count = sizeof($linkarray);
			
			$path = $node->getPath("String");
			$type = ($node->isLeaf()) ? 'leaf' : 'node';
			
			for ($i = 0; $i < $count; $i++) {
				if (!($linkarray[$i]['path'] == $path && $linkarray[$i]['type'] == $type)) {
					$newlinkarray[] = $linkarray[$i];
				}
			}
			$cache[14] = $newlinkarray;
			$this->writeCache($cache);
		}
		
		
		/**
		* Gets the node's links.
		* 
		* 
		* @author Ben Dodson
		* @version 9/18/04
		* @since 9/18/04
		*/
		function getLinks($type = 'both') {
			$cache = $this->readCache();
			$linkarray = $cache[14];
			$return = array();
			// alias:
			if ($type == "tracks") {
				$type = "leaves";
			}
			
			for ($i = 0; $i < sizeof($linkarray); $i++) {
				if ($linkarray[$i]['type'] == 'node') {
					if ($type == 'both' || $type == 'nodes') {
						$return[] = &new jzMediaNode($linkarray[$i]['path']);	
					}
				}
				if ($linkarray[$i]['type'] == 'leaf') {
					if ($type == 'both' || $type == 'leaves') {
						$return[] = &new jzMediaTrack($linkarray[$i]['path']);	
					}
				}
			}
			
			return $return;			
		}		
	
		
		/**
		 *  Injects a leaf or a node into $this.
		 * Updated parameters for Jinzora 3.0.
		 * 
		 * $pathArray can set the following fields:
		 * genre, subgenre, artist, album, disk, track
		 * If anything is not set, this function will try and pull
		 * the information from $this.
		 * 
		 * @author Ben Dodson
		 * @since version - Jul 28, 2006
		 */
		function inject($pathArray, $filename, $meta = false) {
			
			if (!isset($pathArray['genre']) && false !== ($info = getInformation($this, "genre"))) {
				$pathArray['genre'] = $info;
			}
			if (!isset($pathArray['subgenre']) && false !== ($info = getInformation($this, "subgenre"))) {
				$pathArray['subgenre'] = $info;
			}
			if (!isset($pathArray['artist']) && false !== ($info = getInformation($this, "artist"))) {
				$pathArray['artist'] = $info;
			}
			if (!isset($pathArray['album']) && false !== ($info = getInformation($this, "album"))) {
				$pathArray['album'] = $info;
			}
			if (!isset($pathArray['disk']) && false !== ($info = getInformation($this, "disk"))) {
				$pathArray['disk'] = $info;
			}
			$mpath = buildPath($pathArray);
			$root = new jzMediaNode();
			$res = $root->oldInject($mpath, $filename);
			if (is_object($res) and $res->isLeaf()) {
				$be = new jzBackend();
				$be->registerFile($filename, $mpath);
				if (is_array($meta) && sizeof($meta) > 0) {
					$res->setMeta($meta,"cache");
					$res->playpath =  $filename;
				}
			}
			return $res;
		}
	
		/*
		 * Adds tracks in bulk to Jinzora.
		 * 
		 * @author Ben Dodson
		 * @since 9/5/04
		 */
		 function bulkInject($paths,$filenames,$metas) {
		 	$results = array();
		 	for ($i = 0; $i < sizeof($paths); $i++) {
		 		$results[] = $this->inject($paths[$i],$filenames[$i],$metas[$i]);
		 	}
		 	return $results;
		 }
	
		/**
		* Injects a leaf or a node into $this.
		* If sizeof($path) > 1, does so 'recursively'
		* If the element path is found, do nothing and return false.
		* 
		* @author Ben Dodson
		* @version 10/14/04
		* @since 10/14/04
		*/	
		function oldInject($path, $filepath, $type = "leaf") {
			global $hierarchy;

			if (is_string($path)) {
			  // todo: be more flexible (be carefully of '://')
			  return false;
			}

			if ($type == "track") {
				$type = "leaf";
			}
			// Handle $path[0].
			if ($path == array()) {
				return false;
			}
			else {
				$rawhead = array_shift($path);
				$head = pathize($rawhead);
				$nextpath = $this->getPath();
				$nextpath[] = $head;
				$nexttrack = &new jzMediaTrack($nextpath);
				$nexttrack->playpath = $filepath;
				$nextnode = &new jzMediaNode($nextpath);
				
				if (file_exists($filepath)) {
					$date = jz_filemtime($filepath);
				} else {
					$date = 0;
				}	
				// add $next to cache, add its child, and continue.
				
				$cache = $this->readCache("nodes");
				
				if ($cache[15] == "-") {
					$ptype = findPType($this);
					$cache[15] = $ptype;
				}
				
				if (sizeof($path) == 0 && $type == "leaf") {
					$found = false;
					foreach ($cache[8] as $el) {
						if ($el == $rawhead) {
							return false;
						}
					}
					if (!$found) {
						$cache[8][] = $rawhead;
					}
					natcasesort($cache[8]);
				}
				else {
					$found = false;
					if (!is_array($cache[7])) {
					  //print_r($this);
						die();
					}
					foreach ($cache[7] as $el) {
						if (strtolower($el) == strtolower($head)) {
							$found = true;
						}
					}
					if (!$found) {
						$cache[7][] = $head;
					}
					natcasesort($cache[7]);
				}
				$this->writeCache($cache,"nodes");
				
				if (sizeof($path) == 0) { // gets its own cache.
					if ($type == "leaf") {
						$cache = $nexttrack->readCache();
						
						$cache[6] = $date;
						$cache[0] = $filepath;
						$cache[2] = $rawhead;
						
						$nexttrack->playpath = $filepath;
						$nexttrack->writeCache($cache);
						return $nexttrack;
						
					}
					else {
						$cache = $nextnode->readCache();
						$cache[6] = ($cache[6] < $date) ? $date : $cache[6];
						
						// Don't need filepath. Writing to filesystem in id3 mode is stupid-
						// everything will go in the data_dir.
						$nextnode->writeCache($cache);
						return $nextnode;
					}
				}
				else {
					return $nextnode->oldInject($path,$filepath,$type);
				}
			}
		}
		
		
		/**
		* Gets the ptype for this node.
		* 
		* @author Ben Dodson
		* @version 10/31/04
		* @since 10/31/04
		*/		
		function getPType() {
			if ($this->getLevel() == 0) return "root";
			$cache = $this->readCache();
			return $cache[15];
		}
		
		
		/**
		* Sets the ptype for this node.
		
		* @author Ben Dodson
		* @version 10/31/04
		* @since 10/31/04
		*/				
		function setPType($type) {
			$cache = $this->readCache();
			$cache[15] = $type;
			$this->writeCache($cache);
		}

		/**
		 * Adds meta data to all subnodes
		 *
		 * @author Ben Dodson
		 * @version 1/21/05
		 * @since 1/21/05
		 *
		 **/
		function bulkMetaUpdate($meta,$mode = false, $displayOutput = false) {
		  $tracks = $this->getSubNodes("tracks",-1);
		  foreach ($tracks as $track) {
		    $track->setMeta($meta,$mode,$displayOutput);
		  }
			$display = new jzDisplay();
			$display->purgeCachedPage($this);
		}
		
		/**
		* Imports media in the specified format.
		
		* @author Ben Dodson
		* @version 10/31/04
		* @since 10/31/04
		*/				
		function mediaImport($type, $file) {
			switch ($type) {
			case "jzLibrary":
				echo "Importing media...<br>";
				ob_flush();
				if ($file == "URL") {
					$contents = "";
					$url = $_GET['query'];
					$fp = fopen($url,'r');
					while (!feof($fp)) {
						$contents .= fread($fp, 2048);
					}
					$arr = unserialize($contents);
				} else {
					$arr = unserialize($file);
				}
				for ($i = 0; $i < sizeof($arr); $i++) {
					$mpath = explode("/",$arr[$i]['path']);
					if (sizeof($mpath) > 0) {
						if ($child = $this->oldInject($mpath,$arr[$i]['filepath'])) {
							$child->setMeta($arr[$i]['meta'],"cache");
						}
					}
				}
				echo "Import complete.";
				break;
			}
		}
	
		/**
		* Gathers all of our statistics as an array with
		* the following keys:
		*
		* avg_bitrate
		* avg_length (seconds)
		* avg_length_str (string version)
		* total_length (seconds)
		* total_length_str (string version)
		* avg_size (megs)
		* total_size (megs)
		* total_size_str (string version)
		* total_genres
		* total_artists
		* total_albums
		* total_disks
		* total_tracks
		* avg_year
		*
		* @author Ben Dodson
		* @version 11/16/04
		* @since 11/16/04
		*/
		function getStats($return = false) {
			if (!$return){
				$this->generateStats($return);
				return $this->stats;
			} else {
				$data = $this->generateStats($return);
				return $data[$return];
			}
		}
		
		/**
		* Creates all of our statistics.
		* 
		* @author Ben Dodson
		* @version 11/16/04
		* @since 11/16/04
		* @param $string specifically what to return, defaults to everything
		*/
		function generateStats($return = false) {
			$stats = array();
			
			// have to do it...
			$elements = $this->getSubNodes("tracks",-1);
			
			$length = $size = $bitrate = $year = $yearc = $lengthc = $brc = $sizec = $tracks = 0;
			foreach ($elements as $track) {
				$tracks++;
				$meta = $track->getMeta();
				if ($meta['length'] != '-' && $meta['length'] > 0) {
					$length += $meta['length'];
					$lengthc++;
				}
					
				if ($meta['size'] != '-' && $meta['size'] > 0) {
					$size += $meta['size'];
					$sizec++;
				}
					
				if ($meta['year'] != '-' && $meta['year'] > 1000) {	
					$year += $meta['year'];
					$yearc++;
				}
					
				if ($meta['bitrate'] != '-' && $meta['bitrate'] > 0) {	
					$bitrate += $meta['bitrate'];
					$brc++;
				}
			}

			if ($tracks == 0) return false;
			
			$stats['total_size'] = $size;
			$stats['total_length'] = $length;
			$stats['total_tracks'] = $tracks;			
			$stats['avg_bitrate'] = @round($bitrate / $brc,2);
			$stats['avg_length'] = @round($length / $lengthc,0);
			$stats['avg_size'] = @round($size / $sizec,2);
			$stats['avg_year'] = @round($year / $yearc,2);
			
			$str = "";
			// stringize stuff:
			$stats['avg_length_str'] = stringize_time($stats['avg_length']);
			$stats['total_length_str'] = stringize_time($stats['total_length']);
			$stats['total_size_str'] = stringize_size($stats['total_size']);
			
			// Now did we want to return something specific
			if ($return){
				return $stats[$return];
			}
			
			if (($d = distanceTo('genre',$this)) !== false)
				$stats['total_genres'] = $this->getSubNodeCount('nodes',$d);
			else
				$stats['total_genres'] = 0;
			
			if (($d = distanceTo('artist',$this)) !== false)
				$stats['total_artists'] = $this->getSubNodeCount('nodes',$d);
			else
				$stats['total_artists'] = 0;
				
			if (($d = distanceTo('album',$this)) !== false)
				$stats['total_albums'] = $this->getSubNodeCount('nodes',$d);
			else
				$stats['total_albums'] = 0;
				
			// nodes at track level are disks.
			if (($d = distanceTo('track',$this)) !== false)
				$stats['total_disks'] = $this->getSubNodeCount('nodes',$d);
			else
				$stats['total_disks'] = 0;
			
			$this->stats = $stats;
		}

	  /**
	   * Removes media from the cache.
	   * 
	   * @author Ben Dodson
	   * @version 11/16/04
	   * @since 11/16/04
	   */
	  function removeMedia($element) {
	    $parent = $element->getParent();
	    
	    if ($element->isLeaf()) {
	      // Delete parent references to $element:
	      $nodecache = $parent->readCache("nodes");
	      $tracklist = $nodecache[8];
	      $name = $element->getPath();
	      $name = $name[sizeof($name)-1];
	      removeFromArray($tracklist,$name);
	      $nodecache[8] = $tracklist;
	      $parent->writeCache($nodecache,"nodes");

	      $trackcache = $parent->readCache("tracks");
	      foreach ($trackcache as $id=>$arr) {
		if ($arr[2] == $name) {
		  unset($trackcache[$id]);
		}
	      }
	      $trackcache = array_values($trackcache);
	      $parent->writeCache($trackcache,"tracks");
	    } else {
	      // First wipe out the children:
	      $children = $element->getSubNodes("nodes",-1);
	      foreach ($children as $child) {
		$this->removeMedia($child);
	      }

	      // Now clear $element from its parent:
	      $nodecache = $parent->readCache("nodes");
	      $nodelist = $nodecache[7];
	      $name = $element->getName();
	      removeFromArray($nodelist,$name);
	      $nodecache[7] = $nodelist;
	      $parent->writeCache($nodecache,"nodes");

	      // Now kill $element's caches:
	      $element->deleteCache();
	    }

	    // Now see if the parent should be removed:
	    $count = $parent->getSubNodeCount("both",1);
	    if ($count == 0 && $parent->getLevel() > 0) {
	      $this->removeMedia($parent);
	    }
	  }

	  /* Moves media from one location to another
	   * This function calls other backend functions
	   * to make it work for all backends.
	   * It is not meant to be the most efficient implementation.
	   *
	   * @author Ben Dodson
	   * @since 8/10/05
	   * @version 8/11/05
	   **/
	  function moveMedia($element, $newpath) {
	    $root = new jzMediaNode();
	    $be = new jzBackend();

	    $playpath = $element->getFilePath();
	    $type = ($element->isLeaf()) ? "leaf" : "node";
	    if (is_string($newpath)) {
	      $path = explode("/",$newpath);
	    } else if (is_array($newpath)) {
	      $path = $newpath;
	    } else {
	      return false;
	    }
	    
	    if ($type == "node") { return false; }
	    
	    
	    $pc = $element->getPlayCount();
	    $dc = $element->getDownloadCount();
	    $vc = $element->getViewCount();
	    $desc = $element->getDescription();
	    $sdesc = $element->getShortDescription();
	    $art = $element->getMainArt();
	    $discussion = $element->getDiscussion();
	    $rating = $element->getRating();
	    $rating_count = $element->getRatingCount();
	    if ($be->hasFeature('setID')) {
	      $mid = $element->getID();
	    }

	    // TODO:
	    // This does not work correctly with nodes yet.
	    // You should pull the above data, then recursively move children, 
	    // then remove me, and finally set the data as below.
	    // I did not do this yet (8/11/05) because I would not have been
	    // able to test it. (for things like collisions, 
	    // and also how to handle the filesystem)

	    // If the backend has a lookup file, update it.
	    if ($element->isLeaf()) {
	      $media_path = getMediaDir($element);
	      $be = new jzBackend();
	      $rl_name = 'reverse_lookup-' . str_replace(':','',str_replace('\\','-',str_replace('/','-',$media_path)));
	      $LOOKUP = $be->loadData($rl_name);
	      if (is_array($LOOKUP)) {
		if (isset($LOOKUP[$element->getFilename("host")])) {
		  $LOOKUP[$element->getFilename("host")] = implode('/',$path);
		  $be->storeData($rl_name,$LOOKUP);
		}
	      }
	    }

	    $this->removeMedia($element);
	    if (false !== ($new = $root->inject($path,$playpath))) {
	      $new->setPlayCount($pc);
	      $new->setDownloadCount($dc);
	      $new->setViewCount($vc);
	      $new->addRating($rating,$rating_count);

		  if ($be->hasFeature('setID')) {
			$new->setID($mid);
		  }
		  if (!isNothing($desc)) {
			$new->addDescription($desc);
		  }
		  if (!isNothing($sdesc)) {
			$new->addShortDescription($sdesc);
		  }
		  if (!isNothing($art)) {
			$new->addMainArt($art);
		  }
		  if ($discussion != array() && !isNothing($discussion)) {
			$new->addFullDiscussion($discussion);
		  }
			return $new;
	    }
	  }
		


	  function getMostDownloaded($type = "nodes", $distance = false, $limit = 10) {
	    return array();
	  }
	  
	  function getMostPlayed($type = "nodes", $distance = false, $limit = 10) {
	    return array();
	  }
	  
	  function getMostViewed($type = "nodes", $distance = false, $limit = 10) {
	    return array();
	  }
	  
	  function getRecentlyAdded($type = "nodes", $distance = false, $limit = 10) {
	    return array();
	  }
	  
	  function getRecentlyPlayed($type = "nodes", $distance = false, $limit = 10) {
	    return array();
	  }
	  
	  function getTopRated($type = "nodes", $distance = false, $limit = 10) {
	    return array();
	  }
	  
}		
	
	

?>
