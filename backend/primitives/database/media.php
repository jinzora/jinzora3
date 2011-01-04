<?php if (!defined(JZ_SECURE_ACCESS)) die ('Security breach detected.');
	/**
	* - JINZORA | Web-based Media Streamer -  
	* 
	* Jinzora is a Web-based media streamer, primarily desgined to stream MP3s 
	* (but can be used for any media file that can stream from HTTP). 
	* Jinzora can be integrated into a CMS site, run as a standalone application, 
	* or integrated intof any PHP website.  It is released under the GNU GPL.
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
	* This is the media backend for the database adaptor.
	*
	* @since 05.10.04
	* @author Ben Dodson <bdodson@seas.upenn.edu>
	*/
	// Most classes should be included from header.php
	
	
	// The music root is $media_dir.
	class jzRawMediaNode extends jzMediaNodeClass {
		
		var $nodecount;
		var $leafcount;
		var $filepath;
		var $root;
		
		/**
		* Constructor wrapper for jzMediaNode.
		* 
		* @author Ben Dodson <bdodson@seas.upenn.edu>
		* @version 5/14/04
		* @since 5/13/04
		*/
		function jzRawMediaNode($par = array(), $mode = "path") {
			$this->_constructor($par,$mode);
		}
		
		/**
		* Constructor wrapper for jzMediaNode.
		* 
		* @author Ben Dodson <bdodson@seas.upenn.edu>
		* @version 11/12/04
		* @since 5/13/04
		*/
		function _constructor($par = array(),$mode) {
			global $media_dir;
			
			$this->nodecount = false;
			$this->leafcount = false;
			$this->filepath  = false;
			$this->root      = $media_dir;
			parent::_constructor($par,$mode);
		}

		function updateCache($recursive = true, $root = false, $showStatus = false, $force = false, $readTags = true) {
			global $sql_usr, $sql_type, $sql_pw, $sql_socket, $sql_db,$media_dir, $web_root, $root_dir;
			$be = &new jzBackend();
			
			// FAST CASE:
			// if we are non-recursive and haven't been modified, we are done.
			$my_dir = $root;
			if ($my_dir === false) $my_dir = $this->getFilePath();
			if ($my_dir == "/" || $my_dir == "" || !is_dir($my_dir)) {
			  if ($this->getLevel() == 0) {
				return false;
			  } else {
			    $parent = $this->getParent();
			    return $parent->updateCache(false,false,$showStatus,$force,$readTags);
			  }
			}
			
			if ($recursive === false) {
				$nodePath = $this->getPath("String");
				// If our naturalDepth is greater than 1, we need to update the cache
				// at lower levels.
				if ($this->getNaturalDepth() > 1) {
					if (!($handle = opendir($my_dir))) 
						die("Could not access directory $my_dir in database::updateCache");
				
					while ($file = readdir($handle)) {
						$nextPath = ($nodePath == "") ? $file : $nodePath . "/" . $file;
						if ($file == "." || $file == "..") {
							continue;
						} else if (is_dir($my_dir . "/" .$file)) {
							$next = &new jzMediaNode($nextPath);
							$next->setNaturalDepth($this->getNaturalDepth() - 1);
							$next->updateCache(false,$my_dir . "/" . $file,$showStatus,$force,$readTags);
						}
					}
				}
				// find out when we were last updated; if it was after our last modification,
				// we are done.
				$stamp = $be->getUpdated($my_dir);
				if ($stamp > date("U",filemtime($my_dir))) {
					return true;
				}
			}

			// Do we want this error check?
			//if ($my_dir === false || !is_dir($my_dir)) return;
			
			// connect to the database.
			if (!$link = jz_db_connect())
            	die ("could not connect to database.");
			
			// Ok, now that we're updating the node we should purge the page cache
			$_SESSION['jz_purge_file_cache'] = "true";
			
			if (!isset($nodePath)){$nodePath="";}
				$slashedNodePath = jz_db_escape($nodePath);
				$slashedFilePath = jz_db_escape($my_dir);
				$level = $this->getLevel();
				// Mark my children as invalid if they exist in the DB.
				// Mark as valid only if/when they are found.
				$sql = "UPDATE jz_nodes SET valid = 'false' WHERE valid != 'perm'";
				$slash = ($level == 0) ? "" : "/";
				if ($recursive) {
					$sql .= " AND level > $level";
				} else {
					$sql .= " AND level = ";
					$sql .= $level+1;
				}
				$sql .= " AND path LIKE '${slashedNodePath}${slash}%'";
				$sql .= " AND filepath LIKE '${slashedFilePath}%'";
				
				if (jz_db_query($link, $sql) === false) die (jz_db_error($link));
				
				$sql = "UPDATE jz_tracks SET valid = 'false' WHERE valid != 'perm'";
				$sql .= " AND path LIKE '${slashedNodePath}${slash}%'";
				$sql .= " AND filepath LIKE '${slashedFilePath}%'";
				if (!$recursive) {
					$sql .= " AND level = ";
					$sql .= $level+1;
			}
			if (false === jz_db_query($link, $sql)) die (jz_db_error($link));

			$this->updateCacheHelper($be, $link, $recursive, $my_dir, $showStatus, $force, $readTags);
			
			$be = &new jzBackend();
			$be->setUpdated();
			//jz_db_close($link);
		}

		/**
		 * Updates the cache using $this as the base node.
		 * 
		 * @author Ben Dodson <bdodson@seas.upenn.edu>
		 * @version 11/12/04
		 * @since 5/13/04
		 */
		function updateCacheHelper(&$be, &$link, $recursive, $root, $showStatus, $force = false, $readTags = true) {
		  global $sql_usr, $sql_type, $sql_pw, $sql_socket, $sql_db,
		    $audio_types, $video_types, $playlist_types, $ext_graphic, $default_art,
		    $track_num_seperator, $hierarchy,$backend;

	 	  // Make upgrades work.
		  // This should be a more general routine,
		  // like getSetting("playlist_types");
		  if (!isset($playlist_types)) {
		    $playlist_types = "m3u";
		  }

		  // The database adaptor builds itself based on the filesystem.
		  if ($root !== false)
		    $media_dir = $root;
		  else
		    $media_dir = $this->getFilePath();
		  
		  if (!is_readable($media_dir)) return false;
		  $mySlashedName = jz_db_escape($this->getName());
		  $pathArray = $this->getPath();
		  $nodePath = $this->getPath("String");
		  $slashedNodePath = jz_db_escape($nodePath); // SQL no likey the quotes.
		  $fullSlashedNodePath = jz_db_escape($media_dir);
		  
		  $level = $this->getLevel();              		
		  $ptype = findPType($this);
		  
		  /* Echo out our current status, if they want it: */
		  if ($showStatus === true){
		    showStatus($media_dir);
		  } else if ($showStatus == "cli") {
		    echo word("Scanning: ") . $media_dir . "\n";
		  }
		  
		  // Now handle $this.
		  $mdate = filemtime($media_dir);
		  if ($mdate > ($curtime = time())) {
		    if (@touch($media_dir) === false) {
		      $mdate = $curtime - ($mdate - $curtime);
		    } else {
		      $mdate = filemtime($media_dir);
		    }
		  }
		  $stamp = $be->getUpdated($media_dir);		

		  $sql = "INSERT INTO jz_nodes(name,path,filepath,ptype,level,date_added,my_id) ";
		  $sql .= "VALUES('$mySlashedName','$slashedNodePath','$fullSlashedNodePath','$ptype',$level,'$mdate','".uniqid("T")."')";			
		  // ADD MORE INFO TO DB.
		  if (!jz_db_query($link, $sql)) {
		    // the node was already in the database.
		    $sql = "UPDATE jz_nodes SET valid = 'true' ";
		    $sql .= "WHERE path " . jz_db_case_sensitive() . " '$slashedNodePath'";
		    
		    jz_db_query($link, $sql);
		  }
		  writeLogData('importer',"Importing node: ". $mySlashedName. " - ". $fullSlashedNodePath);
		  
		  // Now move inside $this's path.
		  if (!($handle = opendir($media_dir))) 
		    die("Could not access directory $media_dir in database::updateCacheHelper.");
		  
		  // let's look for the best files to use while we are scanning this directory:
		  $leafcount = 0;
		  $nodecount = 0;
		  $bestImage = "";
		  $bestDescription = "";
		  
		  while ($file = readdir($handle)) {
		    $nextPath = ($nodePath == "") ? $file : $nodePath . "/" . $file;
		    $nextFile = $media_dir . "/" . $file;
		    $slashedNextFile = jz_db_escape($nextFile);
		    $slashedFileName = jz_db_escape($file);
		    $slashedFilePath = jz_db_escape($nextPath);
		    if ($file == "." || $file == "..") {
		      continue;
		    } else if (is_dir($nextFile)) {
		      $next = &new jzMediaNode($nextPath);
		      if ($recursive) {
						$next->updateCacheHelper($be, $link,true,$nextFile,$showStatus, $force, $readTags);
		      }
		      else {
			// NOT RECURSIVE, just mark as valid, or add if it wasn't there.
			$sdate = filemtime($nextFile);
			if ($sdate > ($curtime = time())) {
			  if (@touch($nextFile) === false) {
			    $sdate = $curtime - ($sdate - $curtime);
			  } else {
			    $sdate = filemtime($nextFile);
			  }
			}
			
			$spt = findPType($next);

			if ($backend == "mysql") {
			  $n1 = jz_db_query($link,"SELECT COUNT(*) FROM jz_nodes WHERE path LIKE '$slashedFilePath'");
			  $n2 = jz_db_query($link,"SELECT COUNT(*) FROM jz_nodes WHERE path LIKE BINARY '$slashedFilePath'");
			  if ($n1->data[0][0] - $n2->data[0][0] > 0) {
			    // A folder was renamed.
			    jz_db_query($link,"DELETE FROM jz_nodes WHERE path LIKE '$slashedFilePath%'");
			  }
			}

			$sqln = "INSERT INTO jz_nodes(name,path,filepath,ptype,level,date_added,my_id)";
			$sqln .= " VALUES('$slashedFileName','$slashedFilePath','$slashedNextFile','$spt',$level+1,'$sdate','".uniqid("T")."')";

			$sqlu = "UPDATE jz_nodes SET valid = 'true' ";
			$sqlu .= "WHERE path " . jz_db_case_sensitive() . " '$slashedFilePath'";
						
			jz_db_query($link, $sqln) || jz_db_query($link,$sqlu);
		      }
		      $nodecount++;
		    } else {  // is a regular file.
		      // That means check for media file (leaf),
		      // or picture or text for $this.
		      if (preg_match("/\.(txt)$/i", $file)) {
			// TODO: GET THE CORRECT DESCRIPTION IN $bestDescription
			// $bestDescription = $slashedNodePath . "/" . jz_db_escape($file);
			// This will need testing...
			$descFile = $media_dir. "/". jz_db_escape($file);
			if (is_file($descFile)){
			  $bestDescription = jz_db_escape(nl2br(implode("\n",@file($descFile))));
			}
		      }
		      if (preg_match("/\.($ext_graphic)$/i", $file) && !stristr($file,".thumb.")) {
			$fav_image_name = $default_art;
				// An image
				if (@preg_match("/($fav_image_name)/i",$file)) {
			  		$bestImage = $slashedNextFile;
				} else if ($bestImage == "") {
			  		$bestImage = $slashedNextFile;
				}
		      } else if (preg_match("/\.($playlist_types)$/i", $file)) {
			echo 'm3u: ' + $file;
			$ext = substr($file, strrpos($file, '.') + 1);
			if (0 == strcasecmp($ext,'m3u')) {
			  $m3u_lines = file($nextFile);
			  $is_local_m3u = false;
			  foreach ($m3u_lines as $line) {
			    if ($lines[0]=='#') {
				// TODO: get metadata.
				continue;
			    } else {
			      if (false === strpos($lines[0],'://')) {
			        $is_local_m3u = true;
				break;
                              }
  			      $mediaref = $lines[0];
			      
			      // add to DB
 			      $mdate = filemtime($nextFile);
			      if ($mdate > ($curtime = time())) {
			        if (@touch($nextFile) === false) {
			          $mdate = $curtime - ($mdate - $curtime);
			        } else {
			          $mdate = filemtime($nextFile);
			        }
			      }
		              $leafcount++;
			      addWebTrack($mediaref,$nodePath,$mdate,$level,$link);
			      

			    }
			  }
			}
		      } else if (preg_match("/\.($audio_types)$/i", $file) || preg_match("/\.($video_types)$/i", $file)) {
			// A media file
			// add it as an element.
			$leafcount++;

			$mdate = filemtime($nextFile);
			if ($mdate > ($curtime = time())) {
			  if (@touch($nextFile) === false) {
			    $mdate = $curtime - ($mdate - $curtime);
			  } else {
			    $mdate = filemtime($nextFile);
			  }
			}
			
			// First, try putting me in the DB.
			$slashedFileName = jz_db_escape($file);
			$slashedFilePath = ($slashedNodePath == "") ? $slashedFileName : $slashedNodePath . "/" . $slashedFileName;
			$fullSlashedFilePath = jz_db_escape($media_dir) . "/" . $slashedFileName;
			$mid = uniqid("T");
			$sql = "INSERT INTO jz_nodes(name,path,filepath,ptype,level,date_added,leaf,my_id) ";
			$sql .= "VALUES('$slashedFileName','$slashedFilePath','$fullSlashedFilePath','track',$level+1,'$mdate','true','".$mid."') ";
			$updatesql = "UPDATE jz_nodes SET valid = 'true' WHERE path " . jz_db_case_sensitive() . " '$slashedFilePath'";
			
			jz_db_query($link,$sql) || jz_db_query($link,$updatesql);
			
			// Now, did they want to force this?
			if (($stamp < $mdate) or $force) {
			  $track = &new jzMediaTrack($nextPath);
			  $track->filepath = $track->getPath("String");
			  $track->playpath = $nextFile;
			  if ($readTags === true) {
			    $meta = $track->getMeta("file"); // read meta info from the file;
			  } else {	
					$meta = array();
					$meta['bitrate'] = "";
					$meta['length'] = "";
					$meta['size'] = "";
					$meta['title'] = "";
					$meta['artist'] = "";
					$meta['album'] = "";
					$meta['year'] = "";
					$meta['number'] = "";
					$meta['genre'] = "";
					$meta['frequency'] = "";
					$meta['comment'] = "";
					$meta['lyrics'] = "";
					$meta['type'] = "";
			  }
			  
			  $pname = jz_db_escape($file);
			  $bitrate = $meta['bitrate'];
			  $length = $meta['length'];
			  $filesize = $meta['size'];
			  $name = jz_db_escape($meta['title']);
			  $artist = jz_db_escape($meta['artist']);
			  $album = jz_db_escape($meta['album']);
			  $year = jz_db_escape($meta['year']);
			  $track = $meta['number'];
			  $genre = jz_db_escape($meta['genre']);
			  $frequency = $meta['frequency'];
			  $description = jz_db_escape($meta['comment']);
			  $lyrics = jz_db_escape($meta['lyrics']);
			  $fileExt = $meta['type'];

			  // Now let's see if there is a description file...
			  $desc_file = str_replace(".". $fileExt,"",$media_dir . "/" . $file). ".txt";
			  $long_description = "";
			  if (@is_file($desc_file) and filesize($desc_file) <> 0){
			    // Ok, let's read the description file
			    $handle2 = fopen($desc_file, "rb");
			    $long_description = jz_db_escape(fread($handle2, filesize($desc_file)));
			    fclose($handle2);
			  }
			  			  
			  // Now let's see if there is a thumbnail for this track
			  $thumb_file = jz_db_escape(searchThumbnail($media_dir . "/" . $file));
			  
			  $sql = "INSERT INTO jz_tracks(path,level,my_id,filepath,name,trackname,bitrate,filesize,frequency,length,lyrics,genre,artist,album,year,number,extension)
			       VALUES('$slashedFilePath',$level+1,'".$mid."','$fullSlashedFilePath','$pname','$name','$bitrate','$filesize','$frequency','$length','$lyrics','$genre','$artist','$album','$year','$track','$fileExt')";
			  
			  // Now let's update status and log this
			  if (isset($_SESSION['jz_import_full_progress'])) {
			    $_SESSION['jz_import_full_progress']++;
			  } else {
			    $_SESSION['jz_import_full_progress'] = 1;
			  }
			  writeLogData('importer',"Importing track: ". $fullSlashedFilePath);
			  
			  $updatesql = "UPDATE jz_tracks SET valid = 'true',
					level = $level+1,
					trackname = '$name',
					bitrate = '$bitrate',
					filesize = '$filesize',
					frequency = '$frequency',
					length = '$length',";
			  if (!isNothing($lyrics)) {
			    $updatesql .= "lyrics = '$lyrics',";
			  }
			  $updatesql .= "year = '$year',
					genre = '$genre',
					artist = '$artist',
					album = '$album',
					number = '$track',
					extension = '$fileExt'";
			  
			  $updatesql .= " WHERE path " . jz_db_case_sensitive() . " '$slashedFilePath'";
			  	
			  jz_db_query($link,$sql) || jz_db_query($link,$updatesql);
			} else {
			  $sql = "UPDATE jz_tracks SET valid = 'true' WHERE path " . jz_db_case_sensitive() . " '$slashedFilePath'";
			  jz_db_query($link,$sql);
			}
			
			// last thing: add thumb and/or descriptions.
			$sql = "valid = 'true'";
			
			if (isset($thumb_file)) {
			  $sql .= ", main_art = '$thumb_file'";
			}
			if (isset($description)) {
			  $sql .= ", descr = '$description'";		
			}
			if (isset($long_description)) {
			  $sql .= ", longdesc = '$long_description'";
			}
			jz_db_query($link,"UPDATE jz_nodes SET $sql WHERE path " . jz_db_case_sensitive() . " '$slashedFilePath'");
			
		      }
		    }
		  }
		  // Back to $this node:
		  // add new info to $this's database entry.
		  if ($readTags === true) {
		    $be->setUpdated($media_dir);
		  } else {
		    $be->setUpdated($media_dir,0); // pretend it hasn't been updated since 1979.
		  }
		  
		  
		  $sqlUpdate = "nodecount = $nodecount, leafcount = $leafcount";
		  if ($bestImage != "") {
		    $sqlUpdate .= ", main_art = '$bestImage'";
		  }
		  if ($bestDescription != "") {
		    //$bestDescription = jz_db_escape(file_get_contents($bestDescription));
		    $sqlUpdate .= ", longdesc = '$bestDescription'";
		  }
		  $sql = "UPDATE jz_nodes SET $sqlUpdate WHERE path LIKE '$slashedNodePath'";
		  if (false === jz_db_query($link,$sql)) die (jz_db_error($link));
		  
		  $slash = ($level == 0) ? "" : "/";
		  // all done; remove everything beyond $this's path that is still not valid.
		  $sql = "DELETE FROM jz_nodes WHERE ";
		  $sql .= "level > $level AND path LIKE '${slashedNodePath}${slash}%' AND valid = 'false'";
		  $sql .= " AND filepath LIKE '${fullSlashedNodePath}%'";
		  jz_db_query($link, $sql);
		  
		  $sql = "DELETE FROM jz_tracks WHERE ";
		  $sql .= "path LIKE '${slashedNodePath}${slash}%' AND valid = 'false'";
		  $sql .= " AND filepath LIKE '${fullSlashedNodePath}%'";
		  jz_db_query($link, $sql);
		}
		

		/**
		 * Helper function to add a single web track
		 * to the database.
		 * @author Ben Dodson
		 * @since 1/29/10
		 */
		function addWebTrack($mediaref,$parent,$mdate,$level,$link) {
			$medianame = $mediaref;
			while ($medianame[strlen($medianame)-1] == '/') {
				$medianame = substr($medianame,0,strlen($medianame)-1);
			}
			$medianame = substr($medianame,strrpos($medianame,'/')+1);
			$slashedNodePath = jz_db_escape($parent); 
			$slashedFileName = jz_db_escape($medianame);

			// First, try putting me in the DB.
			$slashedFileName = jz_db_escape($medianame);
			$slashedFilePath = ($slashedNodePath == "") ? $slashedFileName : $slashedNodePath . "/" . $slashedFileName;
			$fullSlashedFilePath = jz_db_escape($mediaref);

			$mid = uniqid("T");
			$sql = "INSERT INTO jz_nodes(name,path,filepath,ptype,level,date_added,leaf,my_id) ";
			$sql .= "VALUES('$slashedFileName','$slashedFilePath','$fullSlashedFilePath','track',$level+1,'$mdate','true','".$mid."') ";
			$updatesql = "UPDATE jz_nodes SET valid = 'true' WHERE path " . jz_db_case_sensitive() . " '$slashedFilePath'";
			
			jz_db_query($link,$sql) || jz_db_query($link,$updatesql);
			
			  $pname = jz_db_escape($medianame);
			  $bitrate = "";
			  $length = "";
			  $filesize = "";
			  $name = jz_db_escape($medianame);
			  $artist = "";
			  $album = "";
			  $year = "";
			  $track = "";
			  $genre = "";
			  $frequency = "";
			  $description = "";
			  $lyrics = "";
			  $fileExt = "";

			  $long_description = "";
			  $thumb_file = "";
			  
			  $sql = "INSERT INTO jz_tracks(path,level,my_id,filepath,name,trackname,bitrate,filesize,frequency,length,lyrics,genre,artist,album,year,number,extension)
			       VALUES('$slashedFilePath',$level+1,'".$mid."','$fullSlashedFilePath','$pname','$name','$bitrate','$filesize','$frequency','$length','$lyrics','$genre','$artist','$album','$year','$track','$fileExt')";
			  
			  // Now let's update status and log this
			  if (isset($_SESSION['jz_import_full_progress'])) {
			    $_SESSION['jz_import_full_progress']++;
			  } else {
			    $_SESSION['jz_import_full_progress'] = 1;
			  }
			  writeLogData('importer',"Importing track: ". $fullSlashedFilePath);
			  
			  $updatesql = "UPDATE jz_tracks SET valid = 'true',
					level = $level+1,
					trackname = '$name',
					bitrate = '$bitrate',
					filesize = '$filesize',
					frequency = '$frequency',
					length = '$length',";
			  
			  $updatesql .= "year = '$year',
					genre = '$genre',
					artist = '$artist',
					album = '$album',
					number = '$track',
					extension = '$fileExt'";
			  
			  $updatesql .= " WHERE path " . jz_db_case_sensitive() . " '$slashedFilePath'";
			  	
			  jz_db_query($link,$sql) || jz_db_query($link,$updatesql);
		
			
			// last thing: add thumb and/or descriptions.
			$sql = "valid = 'true'";
			
			jz_db_query($link,"UPDATE jz_nodes SET $sql WHERE path " . jz_db_case_sensitive() . " '$slashedFilePath'");
		  
		}

		
		/**
		* Counts the number of subnodes $distance steps down.
		* $distance = -1 does a recursive count.
		*
		* @author Ben Dodson <bdodson@seas.upenn.edu>
		* @version 5/14/2004
		* @since 5/14/2004
		*/
		function getSubNodeCount($type='both', $distance=false) {
			global $sql_type, $sql_pw, $sql_socket, $sql_db, $sql_usr,$backend,$default_importer;
			
			if ($distance === false) {
				$distance = $this->getNaturalDepth();	
			}
			// alias:
			if ($type == "tracks") {
				$type = "leaves";
			}
			
			// can we do it quickly?
			if ($distance == 1 && !($this->nodecount === false)) {
			  if ($type == 'nodes') {
			    return $this->nodecount;
			  }
			  if ($type == 'leaves') {
			    return $this->leafcount;
			  }
			  return $this->nodecount + $this->leafcount;
			}

			
			if ($default_importer == "id3tags") {
			  //return sizeof($this->getSubNodes($type,$distance,true,0));
			  
				$pathArray = $this->getPath();
				$level = $this->getLevel();
				$pathString = jz_db_escape($this->getPath("String"));

				$pathArray2 = $pathArray;
				for ($i = 0; $i < sizeof($pathArray2) - 1; $i++) {
			    	$pathArray2[$i] = '%';
			  	}
				$pathString = implode('/',$pathArray2);

				if ($pathString != "") { $pathString .= "/"; }
				$pathString = jz_db_escape($pathString);

          		if ($distance < 0) {
            		$op = ">";
          		} else {
              		$op = "=";
                	$level = $level + $distance;
          		}      
			
			
				// now the query.
				if ($type == "leaves") {
					$sql = "SELECT COUNT(*) FROM jz_tracks WHERE level $op $level AND hidden = 'false' AND path LIKE '${pathString}%'";
					
					$res = jz_db_simple_query($sql);
					return $res[0];
				} else {
					$sql = "SELECT COUNT(*) FROM jz_nodes WHERE level $op $level AND hidden = 'false' ";
					if ($pathString != "") {
				  		$sql .= "AND path LIKE '${pathString}%' ";
					}
					$sql .= "$artString";
					if ($type == "nodes") {
						$sql .= " AND leaf = 'false'";
					}

					$res = jz_db_simple_query($sql);
					return $res[0];
				}
			  
			}

			if ($distance == 1) {
				if (!$link = jz_db_connect())
	                     		die ("could not connect to database.");
	                     		
				$pathArray = $this->getPath();
				$level = $this->getLevel();
				$pathString = jz_db_escape($this->getPath("String"));
				if ($pathString == "") {
				  $sql = "SELECT count(*) as nodecount, 0 as leafcount FROM jz_nodes WHERE level = 1";
				} else {
	                     	  $sql = "SELECT nodecount,leafcount FROM jz_nodes WHERE path LIKE '${pathString}'";
	                     	}

	                     	$results = jz_db_query($link,$sql);
	                     	jz_db_close($link);

	                     	if (!isset($results->data[0]))
	                     		return 0;
	                     		
	                     	if ($type == "nodes") {
	                     		return $results->data[0]['nodecount'];
	                     	}
	                     	if ($type == "leaves") {
	                     		return $results->data[0]['leafcount'];
	                     	}
	                     	else {
	                     		return $results->data[0]['leafcount'] + $results->data[0]['nodecount'];
	                     	}
			}
			
			$pathArray = $this->getPath();
			$level = $this->getLevel();
			$pathString = jz_db_escape($this->getPath("String"));
			if ($pathString != "") { $pathString .= "/"; }
			
			if (!$link = jz_db_connect())
                     		die ("could not connect to database.");
                     	
                     	// speed up the common case of counting all tracks.
                     	if ($distance == -1 && $type == "leaves" && $this->getLevel() == 0) {
                     		$sql = "SELECT COUNT(*) FROM jz_tracks";
	                     	$results = jz_db_query($link,$sql);
				jz_db_close($link);
				return $results->data[0][0];
                     	}
	
                     	if ($distance == -1) {
                     		$op = ">";
                     	}
                     	else {
                     		$op = "=";
                     		$level = $level + $distance;
                     	}
                     	
                     	$lim = "";
                     	if ($type == "leaves") {
                     		$lim = "AND leaf = 'true'";
                     	}
                     	else if ($type == "nodes") {
                     		$lim = "AND leaf = 'false'";
                     	}
                     	$sql = "SELECT COUNT(*) FROM jz_nodes WHERE level $op $level $lim AND path LIKE '${pathString}%'";
                     	$results = jz_db_query($link,$sql);
                     	jz_db_close($link);
			return $results->data[0][0];
		}



		/**
		* Returns the subnodes as an array.
		* 
		* @author Ben Dodson <bdodson@seas.upenn.edu>
		* @version 10/31/04
		* @since 5/14/2004
		*/
		function getSubNodes($type="nodes", $distance=false, $random=false, $limit=0, $hasArt = false, $offset=false) {
			global $sql_type, $sql_pw, $sql_socket, $sql_db, $sql_usr,$backend,$default_importer;
			
			if ($distance === false) {
				$distance = $this->getNaturalDepth();	
			}
			// alias:
			if ($type == "tracks") {
				$type = "leaves";
			}
			
			$pathArray = $this->getPath();
			$level = $this->getLevel();
			$pathString = jz_db_escape($this->getPath("String"));

			if ($default_importer == "id3tags") {
			  $pathArray2 = $pathArray;
			  for ($i = 0; $i < sizeof($pathArray2) - 1; $i++) {
			    $pathArray2[$i] = '%';
			  }
			  $pathString = implode('/',$pathArray2);
			}

			if ($pathString != "") { $pathString .= "/"; }
			$pathString = jz_db_escape($pathString);

          	if ($distance < 0) {
            	$op = ">";
          	} else {
              	$op = "=";
                $level = $level + $distance;
          	}      
            if ($type != "leaves" && $hasArt !== false) {
            	$artString = "AND main_art != ''";
            } else {
            	$artString = "";
            }
			
			// now the query.
			if ($type == "leaves") {
				$sql = "SELECT * FROM jz_tracks WHERE level $op $level AND hidden = 'false' AND path LIKE '${pathString}%'";
				
				if ($random) {
				  $sql .= " ORDER BY " . jz_db_rand_function();
				}
				else {
					$sql .= " ORDER BY path,name";
					// should we ORDER BY number?
				}
				if ($limit > 0) {
					$sql .= " LIMIT $limit";
				}
				if ($offset !== false) {
				   $sql .= " OFFSET $offset";
				}
				return jz_db_object_query($sql);
			} else {
				$sql = "SELECT * FROM jz_nodes WHERE level $op $level AND hidden = 'false' ";
				if ($pathString != "") {
				  $sql .= "AND path LIKE '${pathString}%' ";
				}
				$sql .= "$artString";
				if ($type == "nodes") {
					$sql .= " AND leaf = 'false'";
				}
				if ($random) {
				  $sql .= " ORDER BY " . jz_db_rand_function();
				}
				else {
					$sql .= " ORDER BY name,path";
				}
				if ($limit > 0) {
					$sql .= " LIMIT $limit";
				}
				if ($offset !== false) {
				   $sql .= " OFFSET $offset";
				}
				return jz_db_object_query($sql);
			}
		}
	
		
		/**
		* Returns the hidden subnodes as an array.
		* 
		* @author Ben Dodson <bdodson@seas.upenn.edu>
		* @version 10/31/04
		* @since 10/31/04
		*/
		function getHiddenSubNodes($distance = false) {
			global $sql_type, $sql_pw, $sql_socket, $sql_db, $sql_usr;
		
			if ($distance === false) {
				$distance = $this->getNaturalDepth();	
			}
			
			$pathArray = $this->getPath();
			$level = $this->getLevel();
			$pathString = jz_db_escape($this->getPath("String"));
			if ($pathString != "") { $pathString .= "/"; }
			
			if (!$link = jz_db_connect())
                     		die ("could not connect to database.");

          		if ($distance < 0) {
                     		$op = ">";
                     	}
                     	else {
                     		$op = "=";
                     		$level = $level + $distance;
                     	}        
                     	
                     	$sql = "SELECT * FROM jz_nodes WHERE level $op $level AND hidden = 'true' AND path LIKE '${pathString}%'";
                     	
                     	$results = jz_db_query($link,$sql);
			jz_db_close($link);

			// have $results.
			$arr = array();
			for ($i = 0; $i < $results->rows; $i++) {
				if ($results->data[$i]['leaf'] == "false") {
					$me = &new jzMediaNode(jz_db_unescape($results->data[$i]['path']));
					$me->leafcount = $results->data[$i]['leafcount'];
					$me->nodecount = $results->data[$i]['nodecount'];
					$me->myid = $results->data[$i]['my_id'];
					$arr[] = $me;
				}
				else {
					$me = &new jzMediaTrack(jz_db_unescape($results->data[$i]['path']));
					$me->myid = $results->data[$i]['my_id'];
					$arr[] = $me;
				}
			}
			return $arr;
                     	      
		}
		
		/**
		* Alphabetical listing of a node.
		* 
		* @author Ben Dodson <bdodson@seas.upenn.edu>
		* @version 5/14/2004
		* @since 5/14/2004
		*/	
		function getAlphabetical($letter, $type = "nodes", $distance = false) {
			global $sql_type, $sql_pw, $sql_socket, $sql_db, $sql_usr,$compare_ignores_the,$backend;

                        $letter = strtolower($letter);
			if ($distance === false) {
				$distance = $this->getNaturalDepth();	
			}

			$pathString = jz_db_escape($this->getPath("String"));
			if ($pathString != "") { $pathString .= "/"; }
			
			$level = $this->getLevel();

			if ($distance == -1) {
			// recursive
				$op = ">";
			}
			else {
				$level = $level + $distance;
				$op = "=";
			}
			
			if ($type == "nodes") {
				$t = "AND leaf = 'false'";
			}
			
			$LIKE = jz_db_case_insensitive();

			if ($type == "tracks" || $type == "leaves") {
			  // Check the trackname:
			  if ($letter == "#") {
              	$results = jz_db_object_query("SELECT path FROM jz_tracks WHERE path LIKE '${pathString}%' AND (" . jz_db_leading_digit('trackname') . ") ORDER BY trackname");
			  } else if ($letter == "*") {
			    jz_db_object_query("SELECT path FROM jz_tracks WHERE path LIKE '${pathString}%' ORDER BY trackname");
			  } else {
                            if ($compare_ignores_the != "false") {
                              if ($letter == "t") {
                                $results = jz_db_object_query("SELECT path FROM jz_tracks WHERE path LIKE '${pathString}%' AND ((trackname $LIKE '${letter}%' AND trackname NOT $LIKE 'the %') OR (trackname $LIKE 'the t%')) ORDER BY trackname");
                              } else {
                                $results = jz_db_object_query("SELECT path FROM jz_tracks WHERE path LIKE '${pathString}%' AND (trackname $LIKE '${letter}%' OR trackname $LIKE 'the ${letter}%') ORDER BY trackname");
                              }
                            } else {
			      				$results = jz_db_object_query("SELECT path FROM jz_tracks WHERE path LIKE '${pathString}%' AND trackname $LIKE '${letter}%' ORDER BY trackname");
                            }
			  }
			  
			}
			
			else if ($letter == "#") {
                $results = jz_db_object_query("SELECT * FROM jz_nodes WHERE level $op $level $t AND path LIKE '${pathString}%' AND (" . jz_db_leading_digit('name') . ") ORDER BY name");
				  //TODO: add special characters (IE, anything not a-zA-Z)
			}
			else if ($letter == "*") {
				$results = jz_db_object_query("SELECT * FROM jz_nodes 
				  WHERE level $op $level $t AND path LIKE '${pathString}%' ORDER BY name");
			}
			else {
                                if ($compare_ignores_the != "false") {
                                  if ($letter == "t") {
                                    $results = jz_db_object_query("SELECT * FROM jz_nodes 
				      WHERE level $op $level $t AND path LIKE '${pathString}%'
				      AND ((name $LIKE 't%' AND name NOT $LIKE 'the %') OR (name $LIKE 'the t%')) ORDER BY name");
                                  } else {
                                    $results = jz_db_object_query("SELECT * FROM jz_nodes 
				      WHERE level $op $level $t AND path LIKE '${pathString}%'
				      AND (name $LIKE '${letter}%' OR name $LIKE 'the ${letter}%')  ORDER BY name");
                                  }
                                } else {
			 	  $results = jz_db_object_query("SELECT * FROM jz_nodes 
				    WHERE level $op $level $t AND path LIKE '${pathString}%'
				    AND name $LIKE '${letter}%' ORDER BY name");
                               }
			}
			// have $results.
			return $results;
		}
	
	

		/**
		* Searches media
		* 
		* @author Ben Dodson
		* @version 12/26/04
		* @since 9/21/04
		*/
		function search($searchArray2, $type='both', $depth = -1, $limit=0, $op = "and", $metasearch = array(), $exclude = array()) {
		  global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db,$backend;
		
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
					if ($searchArray2 == "") {
						$searchArray = array();
					} else {
						$searchArray = explode(" ",$searchArray2);
					}
				} else { // gets nasty..
					$open_quote = false;
					$searchArray = array();
					$word = "";
					for ($i = 0; $i < strlen($searchArray2); $i++) {
						if ($searchArray2[$i] == ' ' && $open_quote == false) {
							$searchArray[] = $word;
							$word = "";
						} else if ($searchArray2[$i] == '"') {
							$open_quote = !$open_quote;
						} else {
							$word .= $searchArray2[$i];
						}
					}
					if ($word != "") {
						$searchArray[] = jz_db_escape($word);
					}
				}
			} else {
				$searchArray = $searchArray2;
			}
			
			// exclude array, too:
			if (is_string($exclude)) {
				if ($exclude == "") {
					$excludeArray = array();
				} else if (stristr($exclude,"\"") === false) {
					if ($exclude == "") {
						$excludeArray = array();
					} else {
						$excludeArray = explode(" ",$exclude);
					}
				} else { // gets nasty..
					$open_quote = false;
					$excludeArray = array();
					$word = "";
					for ($i = 0; $i < strlen($exclude); $i++) {
						if ($exclude[$i] == ' ' && $open_quote == false) {
							$excludeArray[] = $word;
							$word = "";
						} else if ($exclude[$i] == '"') {
							$open_quote = !$open_quote;
						} else {
							$word .= jz_db_escape($exclude[$i]);
						}
					}
					if ($word != "") {
						$excludeArray[] = $word;
					}
				}
			} else {
				$excludeArray = $exclude;
			}
			
			
			// Now that we have search array, let's jz_db_escape here so we don't have to later.
			for ($i = 0; $i < sizeof($searchArray); $i++) {
				$searchArray[$i] = jz_db_escape($searchArray[$i]);
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

			// INSENSITIVE OPERATION:
			$INSOP = jz_db_case_insensitive();
			
			// SEARCH:
			$constraints = array();
		

			// LYRICS: a different kind of search.
			if ($type == "lyrics") {
				if ($this->getLevel() > 0) {
					$constraints[] = "path LIKE '".jz_db_escape($this->getPath("String"))."/%'";
				}
			
				// Level
				if ($depth < 0) {
					$lvl = $this->getLevel();
					$constraints[] = "level > $lvl";
				} else {
					$lvl = $this->getLevel() + $depth;
					$constraints[] = "level = $lvl";
				}
			
				if ($op == "exact") {
					$constraints[] = "lyrics $INSOP '%$searchArray2'%";
				} else {
					if ($op == "or") {
						$OPR = "OR";
					} else { // and
						$OPR = "AND";
					}
					$string = "";
					if (sizeof($searchArray) > 0) {
						$string .= "lyrics $INSOP '%$searchArray[0]%'";
					}
					for ($i = 1; $i < sizeof($searchArray); $i++) {
						$string .= "$OPR lyrics $INSOP '%$searchArray[$i]%'";
					}
					$constraints[] = $string;
				}
		     
				// TERMS TO EXCLUDE
				if ($excludeArray != array()) {
					$string = "(lyrics NOT $INSOP";
					$string .= " '%$excludeArray[0]%'";
					for ($i = 1; $i < sizeof($excludeArray); $i++) {
						$string .= " AND lyrics NOT $INSOP '%$excludeArray[$i]%'";	
					}
					$string .= ")";
					$constraints[] = $string;
				}


				if ($constraints == array()) {
					die("Error: no constraints in search.");
				}
				$sql = "SELECT path FROM jz_tracks WHERE";
				$sql .= " ($constraints[0])";
				for ($i = 1; $i < sizeof($constraints); $i++) {
					$sql .= " AND ($constraints[$i])";
				}
				if ($limit > 0) {
					$sql .= " LIMIT $limit";
				}
		    
				if (!$link = jz_db_connect())
					die ("could not connect to database.");
		    
				$results = jz_db_query($link, $sql);
				jz_db_close($link);
				$return = array();
				
				if ($results === false) {
					return $return;
				}
				
				foreach ($results->data as $row) {
					$return[] = &new jzMediaTrack(jz_db_unescape($row['path']));
				}
				
				return $return;
			}  


			// MEDIA SEARCH (not lyrics)
			if ($this->getLevel() > 0) {
				$constraints[] = "path LIKE '".jz_db_escape($this->getPath("String"))."/%'";
			}
			
			// Type
			if ($type == "leaves") {
				$constraints[] = "leaf = 'true'";
			}	else if ($type == "nodes") {
				$constraints[] = "leaf = 'false'";
			}
			
			// Level
			if ($depth < 0) {
				$lvl = $this->getLevel();
				$constraints[] = "level > $lvl";
			} else {
				$lvl = $this->getLevel() + $depth;
				$constraints[] = "level = $lvl";
			}
		  

			// ID search:
			if ($type == "id") {
			  if (sizeof($searchArray) > 1) {
			    // for now. Maybe search on all terms?
			    return array();
			  }
			  $mid = jz_db_escape($searchArray[0]);
			  $string = "my_id = '${mid}'";
			  $constraints[] = $string;
			} else {
			  // String search:
			  if ($op == "exact") {
			    $searchArray2 = str_replace("\"","",$searchArray2);
			    $constraints[] = "name $INSOP '$searchArray2'";
			  } else if ($op == "or") { // "or"
			    if (sizeof($searchArray) > 0) {
			      $string = "name $INSOP";
			      $string .= " '%$searchArray[0]%'";
			      for ($i = 1; $i < sizeof($searchArray); $i++) {
				$string .= " OR name $INSOP '%$searchArray[$i]%'";
			      }
			    }
			    $constraints[] = $string;
			  } else { // "and"
			    // first match at least part in our name:
			    if (sizeof($searchArray) > 0 ) {
			      $string = "((name $INSOP";
			      // if at a specific level, don't worry about full path:
			      if ($lvl > 0) {
				$string .= " '%$searchArray[0]%')";
				// Otherwise, let's try to avoid repeat results:
			      } else {
				$string .= " '%$searchArray[0]%' AND path NOT $INSOP '%$searchArray[0]%/%')";
			      }
			      for ($i = 1; $i < sizeof($searchArray); $i++) {
				$string .= " OR (name $INSOP '%$searchArray[$i]%' AND path NOT $INSOP '%$searchArray[$i]%/%')";
			      }
			      $string .= ")";
			      
			      // Now require the rest to be in the path.
			      $string .= " AND (path $INSOP";
			      $string .= " '%$searchArray[0]%'";
			      for ($i = 1; $i < sizeof($searchArray); $i++) {
				$string .= " AND path $INSOP '%$searchArray[$i]%'";
			      }
			      $string .= ")";
			      $constraints[] = $string;
			    }
			  }
			  
			  // Stuff to exclude:
			  if ($excludeArray != array()) {
			    $string = "(path NOT $INSOP";
			    $string .= " '%$excludeArray[0]%'";
			    for ($i = 1; $i < sizeof($excludeArray); $i++) {
			      $string .= " AND path NOT $INSOP '%$excludeArray[$i]%'";	
			    }
			    $string .= ")";
			    $constraints[] = $string;
			  }
			}
			// Put it all together.
			if ($constraints == array()) {
				die("Error: no constraints in search.");
			}
			
			$sql = "SELECT path,leaf FROM jz_nodes WHERE";
			$sql .= " ($constraints[0])";
			for ($i = 1; $i < sizeof($constraints); $i++) {
				$sql .= " AND ($constraints[$i])";
			}
			if ($limit > 0) {
				$sql .= " LIMIT $limit";
			}
			if (!$link = jz_db_connect())
				die ("could not connect to database.");
				
			$results = jz_db_query($link, $sql);
			jz_db_close($link);
			$return = array();
			$hash = array();
			if ($results === false) {
				return $return;
			}
			
			for ($i = 0; $i < $results->rows; $i++) {
				if ($results->data[$i]['leaf'] == "true") {
				  $me = &new jzMediaTrack(jz_db_unescape($results->data[$i]['path']));
				} else {
				  $me = &new jzMediaNode(jz_db_unescape($results->data[$i]['path']));
				}
				if ($default_importer == "id3tags") {
				  if (!isset($hash[pathize(strtolower($me->getName()))])) {
				    $return[] = $me;
				    $hash[pathize(strtolower($me->getName()))] = true;
				  }
				} else {
				  $return[] = $me;
				}
			}
			
			if ($type == "leaves" && $metasearch != array()) {
				$return = filterSearchResults($return,$metasearch);
			}
			
			return $return;
		}
		


		/**
		* Add this node to the featured list.
		* 
		* @author Ben Dodson
		* @version 6/8/04
		* @since 6/8/04
		*/
		function addFeatured() {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
			
			if (!$link = jz_db_connect())
                     		die ("could not connect to database.");
                     		
                     	$path = jz_db_escape($this->getPath("String"));
                     	jz_db_query($link, "UPDATE jz_nodes SET featured = 'true' WHERE path = '$path'");
		}
		
		/**
		* Removes this node from the featured list.
		* 
		* @author Ben Dodson
		* @version 6/8/04
		* @since 6/8/04
		*/
		function removeFeatured() {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
			
			if (!$link = jz_db_connect())
                     		die ("could not connect to database.");
                     		
                     	$path = jz_db_escape($this->getPath("String"));
                     	jz_db_query($link, "UPDATE jz_nodes SET featured = 'false' WHERE path = '$path'");
		}

		/**
		* Removes this node from the featured list.
		* 
		* @author Ben Dodson
		* @version 6/8/04
		* @since 6/8/04
		*/
		function isFeatured() {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
			
			if (!$link = jz_db_connect())
                     		die ("could not connect to database.");
                     		
                     	$path = jz_db_escape($this->getPath("String"));
                     	$results = jz_db_query($link, "SELECT featured FROM jz_nodes WHERE path = '$path'");
                     	if ($results->data[0]['featured'] == "true") { return true; }
                     	else { return false; }
		}
		
		/**
		* Removes this node from the featured list.
		* 
		* @author Ben Dodson
		* @version 6/8/04
		* @since 6/8/04
		*/
		function getFeatured($distance = -1, $limit = 1) {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
			
			if ($distance === false) {
				$distance = $this->getNaturalDepth();	
			}
			
			$slash = ($this->getLevel() == 0) ? "" : "/";
                     		
                     	$path = jz_db_escape($this->getPath("String"));
			if ($path != "") { $path .= "/"; }
			
			if ($limit > 0) {
				$lim = " LIMIT $limit";
			} else {
				$lim = "";
			}
			
			
			if ($distance <= 0) {
				$dis = "";
			}
			else {
				$level = $this->getLevel();
				$level += $distance;
				$dis = "AND level = '$level'";
			}

			$query = "SELECT * FROM jz_nodes WHERE path LIKE '${path}%' AND featured = 'true' $dis ORDER BY " . jz_db_rand_function();
			$query .= " " . $lim;

            return jz_db_object_query($query);
		}
		
		/**
		* Returns the 'ptype' of this node.
		* 
		* @author Ben Dodson
		* @version 10/31/04
		* @since 10/31/04
		*/
		function getPType() {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
			
			if ($this->getLevel() == 0) return "root";				
			if (!$link = jz_db_connect())
                		die ("could not connect to database.");
                		
            $path = jz_db_escape($this->getPath("String"));
            $results = jz_db_query($link, "SELECT ptype FROM jz_nodes WHERE path = '$path'");
			jz_db_close($link);
            return jz_db_unescape($results->data[0]['ptype']);
		}
		
		
		/**
		* Sets the 'ptype' of this node.
		* 
		* @author Ben Dodson
		* @version 10/31/04
		* @since 10/31/04
		*/
		function setPType($type) {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
								
			if (!$link = jz_db_connect())
                		die ("could not connect to database.");
                	$ptype = jz_db_escape($ptype);
                	
                	$path = jz_db_escape($this->getPath("String"));
                	jz_db_query($link, "UPDATE jz_nodes SET ptype='$type' WHERE path = '$path'");
		}
		
		/**
		* Adds a request.
		* 
		* @author Ben Dodson
		* @version 9/2/04
		* @since 9/2/04
		*/
		function addRequest($entry, $comment, $user, $type = "request") {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
			
			$entry = jz_db_escape($entry);
			$comment = jz_db_escape($comment);
			$user = jz_db_escape($user);
			$type = jz_db_escape(strtolower($type)); // ...you never know :)
			$path = jz_db_escape($this->getPath("String"));
			
			if (!$link = jz_db_connect())
                     		die ("could not connect to database.");
			
			$res = jz_db_query($link,"SELECT * FROM jz_requests");
			$num = $res->rows + 1;
			if (false === jz_db_query($link, "INSERT INTO jz_requests(my_id,entry,comment,my_user,type,path)
                     	                  VALUES($num,'$entry','$comment','$user','$type','$path')")) die(jz_db_error($link));
		}
		
		
		/**
		* Gets the requests
		* 
		* @author Ben Dodson
		* @version 9/2/04
		* @since 9/2/04
		*/
		function getRequests($distance = -1, $type = "all") {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
			
			$path = jz_db_escape($this->getPath("String"));
			
			$type = strtolower($type);
			if ($type == "all") {
				$typequery = "";
			} else {
				$typequery = "AND type LIKE '$type'";
			}
			
			if ($this->getLevel() == 0) {
			  $slash = '';
			} else {
			  $slash = '/';
			}
			if (!$link = jz_db_connect())
                     		die ("could not connect to database.");
			
			$results = jz_db_query($link, "SELECT * FROM jz_requests WHERE path LIKE '${path}${slash}%' $typequery");
			
			$return = array();
			foreach ($results->data as $a) {
				$el = array();
				$el['id'] = $a['my_id'];
				$el['entry'] = jz_db_unescape($a['entry']);
				$el['comment'] = jz_db_unescape($a['comment']);
				$el['user'] = jz_db_unescape($a['my_user']);
				$el['type'] = jz_db_unescape($a['type']);
				$el['path'] = jz_db_unescape($a['path']);
				
				if ($distance <= 0) {
					$return[] = $el;
				} else {
					$temp = &new jzMediaNode($el['path']);
					if ($temp->getLevel() - $this->getLevel() == $distance) {
						$return[] = $el;
					}
				}
			}
			return $return;
		}
		
		
		/**
		* Removes request of given $id.
		* 
		* @author Ben Dodson
		* @version 9/2/04
		* @since 9/2/04
		*/
		function removeRequest($id) {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
			
			if (!$link = jz_db_connect())
                     		die ("could not connect to database.");
                     		
                     	if (false === jz_db_query($link, "DELETE FROM jz_requests WHERE my_id=$id")) die(jz_db_error($link));
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
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
			
			$parent = jz_db_escape($this->getPath("String"));
			$type = ($node->isLeaf()) ? 'leaf' : 'node';
			$path = jz_db_escape($node->getPath("String"));
			
			if (!$link = jz_db_connect())
                     		die ("could not connect to database.");
			
			// let's remove it so we don't have duplicates.
			$this->removeLink($node);
			
			// now we'll add it.
			$res = jz_db_query($link,"SELECT * FROM jz_links");
			$num = $res->rows + 1;
			if (false === jz_db_query($link, "INSERT INTO jz_links(my_id,parent,path,type)
                     	                  VALUES($num,'$parent','$path','$type')")) die(jz_db_error($link));
			
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
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
			
			$parent = jz_db_escape($this->getPath("String"));
			$type = ($node->isLeaf()) ? 'leaf' : 'node';
			$path = jz_db_escape($node->getPath("String"));
			
			if (!$link = jz_db_connect())
                     		die ("could not connect to database.");
			
			if (false === jz_db_query($link, "DELETE FROM jz_links WHERE parent = '$parent' AND path = '$path' AND type = '$type'")) die(jz_db_error($link));
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
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
			
			// alias:
			if ($type == "tracks") {
				$type = "leaves";
			}
			
			$parent = jz_db_escape($this->getPath("String"));
			
			if (!$link = jz_db_connect())
                     		die ("could not connect to database.");
			
			if ($type == 'both') {
				$tstring = "";
			}
			if ($type == 'nodes') {
				$tstring = "AND type = 'node'";
			}
			if ($type == 'leaves') {
				$tstring = "AND type = 'leaf'";
			}
			
			$results = jz_db_query($link, "SELECT * FROM jz_links WHERE parent = '$parent' $tstring");

			$return = array();
			foreach ($results->data as $a) {
				if ($a['type'] == 'leaf') {
					$return[] = &new jzMediaTrack(jz_db_unescape($a['path']));
				}
				else {
					$return[] = &new jzMediaNode(jz_db_unescape($a['path']));
				}
			}
			return $return;
		}
			
		/**
		 * Injects a leaf or a node into $this.
		 * If sizeof($path) > 1, does so 'recursively'
		 * 
		 * @author Ben Dodson
		 * @version 10/15/04
		 * @since 10/15/04
		 */	
		function oldInject($path, $filepath, $type = "leaf") {
		  global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db,$media_dir,$hierarchy;

		  if (is_string($path)) {
		    // todo: be more flexible (be carefully of '://')
		    return false;
		  }

		  if (!$link = jz_db_connect())
		    die ("could not connect to database.");
		  
		  if ($type == "track") {
		    $type = "leaf";
		  }
		  // Handle $path[0].
		  if ($path == array()) {
		    return $this;
		  } else {
		    $head = array_shift($path);
		    $nextpath = $this->getPath();
		    $nextpath[] = $head;
		    $nexttrack = &new jzMediaTrack($nextpath);
		    $nextnode = &new jzMediaNode($nextpath);
		    
		    $ptype = findPType($this);
		    
		    $media_path = "." . $media_dir;
		    if (file_exists($filepath)) {
		      $date = filemtime($filepath);
		    } else {
		      $date = 0;
		    }				
		    
		    if (sizeof($path) == 0) {
		      // just add $next to cache.
		      $tlevel = $this->getLevel()+1;
		      $tpath = jz_db_escape(implode("/",$nextpath));
		      $tfilepath = jz_db_escape($filepath);
		      $thead = jz_db_escape($head);
		      
		      $tptype = jz_db_escape($hierarchy[$this->getLevel()]);
		      if ($tptype == "track") {
				$tptype = "disk";
		      }
		      
		      if ($type == "leaf") {
				$mid = uniqid("T");
				$sql = "INSERT INTO jz_nodes(path,ptype,level,name,leaf,date_added,filepath,valid,my_id)";
				$sql .= " VALUES('$tpath','track',$tlevel,'$thead','true','$date','$tfilepath','perm','".$mid."')";
				jz_db_query($link,$sql);

				$sql = "INSERT INTO jz_tracks(path,level,name,filepath,valid,my_id) VALUES('$tpath','$tlevel','$thead','$tfilepath','perm','".$mid."')";
				if (jz_db_query($link,$sql)) {	
			 	 $ppath = jz_db_escape($this->getPath("String"));
			  	$sql = "UPDATE jz_nodes SET leafcount = leafcount+1 WHERE path = '$ppath'";
			  	jz_db_query($link,$sql);
			 	 return $nexttrack;
				}
				else {
			 	 return false;
				}
			
		      }
		      else {
			// Remember, INSERT will fail if the path already exists.
			// this is ok.
			$sql = "INSERT INTO jz_nodes(path,ptype,level,name,leaf,date_added,valid,my_id)";
			$sql .= " VALUES('$tpath','$tptype',$tlevel,'$thead','false','$date','perm','".uniqid("T")."')";
			if (jz_db_query($link,$sql)) {
			  $ppath = jz_db_escape($this->getPath("String"));
			  $sql = "UPDATE jz_nodes SET nodecount = nodecount+1 WHERE path = '$ppath'";
			  jz_db_query($link,$sql);	
			}
			
			return $nextnode;
		      }
		    }
		    else {
		      $npath = jz_db_escape($nextnode->getPath("String"));
		      $nlevel = $nextnode->getLevel();
		      $nhead = jz_db_escape($head);
		      
		      $nptype = findPType($nextnode);
		      
		      $sql = "INSERT INTO jz_nodes(path,ptype,level,name,leaf,date_added,valid,my_id)";
		      $sql .= " VALUES('$npath','$nptype',$nlevel,'$nhead','false','$date','perm','".uniqid("T")."')";
		      if (jz_db_query($link,$sql)) {
						$ppath = jz_db_escape($this->getPath("String"));
						if ($this->getLevel() == 0) {
							$sql = "UPDATE jz_nodes SET nodecount = nodecount+1 WHERE level = 0";
						} else {
							$sql = "UPDATE jz_nodes SET nodecount = nodecount+1 WHERE path = '$ppath'";
						}
						
						jz_db_query($link,$sql);	
		      }
		      return $nextnode->oldInject($path,$filepath,$type);
		    }
		  }
		}		


		/**
		*
		* @author Ross Carlson
		* @since 1/27/05
		* @version 1/27/05
		*
		* @param $type (defaults to nodes)
		* @param $distance (distance from where we are, defaults no false)
		* @param $limit how many items to return (defaults to 10)
		**/
		function getMostDownloaded($type = "nodes", $distance = false, $limit = 10) {
			if ($distance === false) {
				$distance = $this->getNaturalDepth();	
			}
			
			$pathArray = $this->getPath();
			$level = $this->getLevel();
			$pathString = jz_db_escape($this->getPath("String"));
			if ($pathString != "") { $pathString .= "/"; }
			
			$sql = "SELECT path,leaf FROM jz_nodes";
						
			if ($distance <= 0) {
				$sql .= " WHERE level > $level";
			} else {
				$level = $level + $distance;
				$sql .= " WHERE level = $level";
			}
			
			$sql .= " AND path LIKE '${pathString}%' and dlcount <> 0";
			
			if ($type == "tracks" || $type == "leaves") {
				$sql .= " AND leaf = 'true'";
			} else if ($type == "nodes") {
				$sql .= " AND leaf = 'false'";
			}
			
			$sql .= " ORDER BY dlcount desc LIMIT $limit";

			return jz_db_object_query($sql);
		}

		/**
		*
		* @author Ross Carlson
		* @since 1/27/05
		* @version 1/27/05
		*
		* @param $type (defaults to nodes)
		* @param $distance (distance from where we are, defaults no false)
		* @param $limit how many items to return (defaults to 10)
		**/
		function getMostPlayed($type = "nodes", $distance = false, $limit = 10) {
			if ($distance === false) {
				$distance = $this->getNaturalDepth();	
			}
			
			$pathArray = $this->getPath();
			$level = $this->getLevel();
			$pathString = jz_db_escape($this->getPath("String"));
			if ($pathString != "") { $pathString .= "/"; }
			
			$sql = "SELECT * FROM jz_nodes";
			
			if ($distance <= 0) {
				$sql .= " WHERE level > $level";
			} else {
				$level = $level + $distance;
				$sql .= " WHERE level = $level";
			}
			
			$sql .= " AND path LIKE '${pathString}%' and playcount <> 0";
			
			if ($type == "tracks" || $type == "leaves") {
				$sql .= " AND leaf = 'true'";
			} else if ($type == "nodes") {
				$sql .= " AND leaf = 'false'";
			}
			
			$sql .= " ORDER BY playcount desc LIMIT $limit";

			return jz_db_object_query($sql);
		}


		/**
		*
		* @author Ben Dodson
		* @since 1/27/05
		* @version 1/27/05
		*
		* @param $type (defaults to nodes)
		* @param $distance (distance from where we are, defaults no false)
		* @param $limit how many items to return (defaults to 10)
		**/
		function getMostViewed($type = "nodes", $distance = false, $limit = 10) {
			if ($distance === false) {
				$distance = $this->getNaturalDepth();	
			}
			
			$pathArray = $this->getPath();
			$level = $this->getLevel();
			$pathString = jz_db_escape($this->getPath("String"));
			if ($pathString != "") { $pathString .= "/"; }
			
			$sql = "SELECT * FROM jz_nodes";

			if ($distance <= 0) {
				$sql .= " WHERE level > $level";
			} else {
				$level = $level + $distance;
				$sql .= " WHERE level = $level";
			}
			
			$sql .= " AND path LIKE '${pathString}%' and viewcount <> 0";
			
			if ($type == "tracks" || $type == "leaves") {
				$sql .= " AND leaf = 'true'";
			} else if ($type == "nodes") {
				$sql .= " AND leaf = 'false'";
			}
			
			$sql .= " ORDER BY viewcount desc LIMIT $limit";

			return jz_db_object_query($sql);

		}

		
		/**
		*
		* @author Ben Dodson
		* @since 1/27/05
		* @version 1/27/05
		* @param $type (defaults to nodes)
		* @param $distance (distance from where we are, defaults no false)
		* @param $limit how many items to return (defaults to 10)
		*
		**/
		function getRecentlyAdded($type = "nodes", $distance = false, $limit = 10) {
		  if ($distance === false) {
		    $distance = $this->getNaturalDepth();	
		  }
		  
		  $pathArray = $this->getPath();
		  $level = $this->getLevel();
		  $pathString = jz_db_escape($this->getPath("String"));
		  if ($pathString != "") { $pathString .= "/"; }
		  
		  $sql = "SELECT * FROM jz_nodes";

		  if ($distance <= 0) {
		    $sql .= " WHERE level > $level";
		  }
		  else {
		    $level = $level + $distance;
		    $sql .= " WHERE level = $level";
		  }

		  $sql .= " AND path LIKE '${pathString}%'";
		  
		  if ($type == "tracks" || $type == "leaves") {
		    $sql .= " AND leaf = 'true'";
		  } else if ($type == "nodes") {
		    $sql .= " AND leaf = 'false'";
		  }
		  $sql .= " ORDER BY date_added desc LIMIT $limit";
		  return jz_db_object_query($sql);
		}

		/**
		* Gets the top rated elements.
		*
		* @author Ben Dodson
		* @since 1/27/05
		* @version 1/27/05
		* @param $type (defaults to nodes)
		* @param $distance (distance from where we are, defaults no false)
		* @param $limit how many items to return (defaults to 10)
		*
		**/
		function getTopRated($type = "nodes", $distance = false, $limit = 10) {
		  if ($distance === false) {
		    $distance = $this->getNaturalDepth();	
		  }
		  
		  $pathArray = $this->getPath();
		  $level = $this->getLevel();
		  $pathString = jz_db_escape($this->getPath("String"));
		  if ($pathString != "") { $pathString .= "/"; }
		  
		  $sql = "SELECT path,leaf FROM jz_nodes";

		  if ($distance <= 0) {
		    $sql .= " WHERE level > $level";
		  }
		  else {
		    $level = $level + $distance;
		    $sql .= " WHERE level = $level";
		  }

		  $sql .= " AND path LIKE '${pathString}%' AND rating_val != 0";
		  
		  if ($type == "tracks" || $type == "leaves") {
		    $sql .= " AND leaf = 'true'";
		  } else if ($type == "nodes") {
		    $sql .= " AND leaf = 'false'";
		  }
		  $sql .= " ORDER BY rating_val desc,rating_count desc LIMIT $limit";
		  $results = jz_db_object_query($sql);
		}

		/**
		*
		* @author Ben Dodson
		* @since 1/27/05
		* @version 1/27/05
		* @param $type (defaults to nodes)
		* @param $distance (distance from where we are, defaults no false)
		* @param $limit how many items to return (defaults to 10)
		*
		**/
		function getRecentlyPlayed($type = "nodes", $distance = false, $limit = 10) {
		  if ($distance === false) {
		    $distance = $this->getNaturalDepth();	
		  }
		  
		  $pathArray = $this->getPath();
		  $level = $this->getLevel();
		  $pathString = jz_db_escape($this->getPath("String"));
		  if ($pathString != "") { $pathString .= "/"; }
		  
		  $sql = "SELECT path,leaf FROM jz_nodes";

		  if (!$link = jz_db_connect())
		    die ("could not connect to database.");
		  
		  if ($distance <= 0) {
		    $sql .= " WHERE level > $level";
		  }
		  else {
		    $level = $level + $distance;
		    $sql .= " WHERE level = $level";
		  }

		  $sql .= " AND path LIKE '${pathString}%' AND lastplayed != 0";
		  
		  if ($type == "tracks" || $type == "leaves") {
		    $sql .= " AND leaf = 'true'";
		  } else if ($type == "nodes") {
		    $sql .= " AND leaf = 'false'";
		  }
		  $sql .= " ORDER BY lastplayed desc LIMIT $limit";
		  return jz_db_object_query($sql);
		}


		function generateStats() {

		  $stats = array();
		  if ($this->getLevel() == 0) {
		    $pathstring = "";
		    $fullpathstring = "";
		  } else {
		    $pathstring = "path LIKE '" . jz_db_escape($this->getPath("String")) . "/%' AND ";
		    $fullpathstring = "WHERE path LIKE '" . jz_db_escape($this->getPath("String")) . "/%'";
		  }

		  $link = jz_db_connect();
		  
		  $results = jz_db_query($link, "SELECT sum(filesize) FROM jz_tracks WHERE $pathstring filesize != '-' AND filesize > 0");
		  $stats['total_size'] = $results->data[0][0];
		  
		  $results = jz_db_query($link, "SELECT sum(length) FROM jz_tracks WHERE $pathstring length != '-' AND length > 0");
		  $stats['total_length'] = $results->data[0][0];
		  
		  $results = jz_db_query($link, "SELECT count(*) FROM jz_tracks $fullpathstring");
		  $stats['total_tracks'] = $tracks = $results->data[0][0];
		  
		  $results = jz_db_query($link, "SELECT avg(bitrate) FROM jz_tracks WHERE $pathstring bitrate != '-' AND bitrate > 0");
		  $stats['avg_bitrate'] = round($results->data[0][0],2);
		  
		  $results = jz_db_query($link, "SELECT avg(length) FROM jz_tracks WHERE $pathstring length != '-' AND length > 0");
		  $stats['avg_length'] = round($results->data[0][0],0);


		  $results = jz_db_query($link, "SELECT avg(filesize) FROM jz_tracks WHERE $pathstring filesize != '-' AND filesize > 0");
		  $stats['avg_size'] = round($results->data[0][0],2);
		  
		  $results = jz_db_query($link, "SELECT avg(year) FROM jz_tracks WHERE $pathstring year != '-' AND year > 1000");
		  $stats['avg_year'] = round($results->data[0][0],2);
		  
		  $str = "";
		  // stringize stuff:
		  $stats['avg_length_str'] = stringize_time($stats['avg_length']);
		  $stats['total_length_str'] = stringize_time($stats['total_length']);
		  $stats['total_size_str'] = stringize_size($stats['total_size']);
		  
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
			return $stats;
		}

		/**
		* Removes media from the database.
		* This is a dangerous function, as it also removes sub-media. 
		*
		* @author Ben Dodson
		* @version 7/31/05
		* @since 7/31/05
		*/
		function removeMedia($element) {
		  global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
		  
		  if ($element->getLevel() == 0) {
		    return false;
		  }

		  $parent = $element->getParent();
		  if (!$link = jz_db_connect())
		    die ("could not connect to database.");

		  $path = jz_db_escape($element->getPath("String"));
		  $parentpath = jz_db_escape($parent->getPath("String"));

		  jz_db_query($link,"DELETE FROM jz_nodes WHERE path = '${path}'");

		  if ($element->isLeaf()) {
		    jz_db_query($link, "DELETE FROM jz_tracks WHERE path = '${path}'");
		  } else {
		    jz_db_query($link, "DELETE FROM jz_tracks WHERE path LIKE '${path}/%'");		    
		    jz_db_query($link,"DELETE FROM jz_nodes WHERE path LIKE '${path}/%'");
		  }
		  // Now let's reset our node/leaf counts for the parent.
		  $plvl = $parent->getLevel();

		  $res = jz_db_query($link, "SELECT COUNT(*) FROM jz_nodes WHERE leaf = 'true' AND level = ${plvl}+1 AND path LIKE '${parentpath}/%'");
		  $lcount = $res->data[0][0];
		  jz_db_query($link, "UPDATE jz_nodes SET leafcount=${lcount} WHERE path = '${parentpath}'");
		  
		  $res = jz_db_query($link, "SELECT COUNT(*) FROM jz_nodes WHERE leaf = 'false' AND level = ${plvl}+1 AND path LIKE '${parentpath}/%'");
		  $ncount = $res->data[0][0];
		  jz_db_query($link, "UPDATE jz_nodes SET nodecount=${ncount} WHERE path = '${parentpath}'");


		  if (($lcount + $ncount) == 0 && $parent->getLevel() > 0) {
		    $this->removeMedia($parent);
		  }

		  jz_db_close($link);
		  return;
		}

		


		/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *
		 * Do NOT modify the below: modify overrides.php instead,        *
		 * change to jinzora/backend, and run `php global_include.php`   *
		 * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
		// begin global_include: overrides.php
		/* * * * * * * * * * * * * * * * * * *
		 *            Overrides              *
		 * * * * * * * * * * * * * * * * * * */
		
/**
 * Returns the date the node was added.
 * 
 * @author Ben Dodson <bdodson@seas.upenn.edu>
 * @version 5/14/2004
 * @since 5/14/2004
 */
function getDateAdded() {
	global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
	               		
    $path = jz_db_escape($this->getPath("String"));
    $results = jz_db_simple_query("SELECT date_added FROM jz_nodes WHERE path = '$path'");
    return $results['date_added'];
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
function newSince($days = false) {
  global $days_for_new;

  if ($days === false) {
    $days = $days_for_new;
  }

  $time = $curtime = time();
  $time -= ($days*24*60*60);

  if ($this->getLevel() == 0) {
    $pathstring = "%";
  } else {
    $pathstring = jz_db_escape($this->getPath("String"));
    $pathstring .= "/%";
  }
  $sql = "SELECT date_added FROM jz_nodes WHERE ";
  $sql .= "path LIKE '$pathstring' AND date_added >= $time ORDER BY date_added desc LIMIT 1";

  $results = jz_db_simple_query($sql);

  if (false === $results) {
    return false;
  } else {
    return ceil(abs($curtime - $results['date_added']) / (24*60*60));
  }
}


/**
 * Returns the element's ID
 *
 * @author Ben Dodson
 * @version 3/11/05
 * @since 3/11/05
 **/
function getID() {
  if (isset($this->myid) && $this->myid !== false) {
    return $this->myid;
  } else {
    $path = jz_db_escape($this->getPath("String"));
    $results = jz_db_simple_query("SELECT my_id FROM jz_nodes WHERE path = '$path'");
    return $results['my_id'];
  }
}

/**
 * Sets the elements ID.
 * Returns true on success, false on failure.
 *
 * @author Ben Dodson
 * @version 3/11/05
 * @since 3/11/05
 **/
function setID($id) {
  if (!$link = jz_db_connect())
    die ("could not connect to database.");
  
  $path = jz_db_escape($this->getPath("String"));
  $mid = jz_db_escape($id);
  $res = jz_db_query($link, "UPDATE jz_nodes SET my_id='${mid}' WHERE path = '$path'");
  if ($res === false) { // bad ID; could be a collision.
    jz_db_close($link);
    return false;
  }
  
  if ($this->isLeaf()) {
    $res = jz_db_query($link, "UPDATE jz_tracks SET my_id='${mid}' WHERE path = '$path'");
  }
  
  $this->myid = $id;
  jz_db_close($link);
  return true;
}

/**
 * Converts an id to a path
 *
 * @author Ben Dodson
 * @version 3/11/05
 * @since 3/11/05
 **/
function idToPath($id) {
    $results = jz_db_simple_query("SELECT path FROM jz_nodes WHERE my_id = '$id'");
    return $results['path'];
}

/**
 * Converts a filename to a path
 *
 * @author Ben Dodson
 * @version 3/11/05
 * @since 3/11/05
 **/
function filenameToPath($fp) {
	$fp = jz_db_escape($fp);
    $results = jz_db_simple_query("SELECT path FROM jz_nodes WHERE filepath = '$fp'");
    return $results['path'];
}

		/**
		* Returns the number of times the node has been played.
		* 
		* @author Ben Dodson <bdodson@seas.upenn.edu>
		* @version 5/14/2004
		* @since 5/14/2004
		*/
		function getPlayCount() {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
			
			if (isset($this->playcount)) {
			  return $this->playcount;
			}
	
           	$path = jz_db_escape($this->getPath("String"));
           	$results = jz_db_simple_query("SELECT playcount FROM jz_nodes WHERE path = '$path'");
			$this->playcount = $results['playcount'];
            return $results['playcount'];
		}
		
		
		/**
		* Increments the node's playcount, as well
		* as the playcount of its parents.
		* 
		* @author Ben Dodson <bdodson@seas.upenn.edu>
		* @version 5/14/2004
		* @since 5/14/2004
		*/
		function increasePlayCount() {
		  global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db,$jzUSER;
      		
		  $path = jz_db_escape($this->getPath("String"));
			$sql = "UPDATE jz_nodes SET playcount = playcount+1, lastplayed = " . time();

			jz_db_simple_query("$sql  WHERE path = '$path'");

			$sql = "INSERT INTO jz_playcounts VALUES ";
			$sql .= "('" . $jzUSER->getName()."','" .$this->getID(). "',now())";
			jz_db_simple_query($sql);

			                     	
			if (sizeof($ar = $this->getPath()) > 0) {
			  array_pop($ar);
			  $next = &new jzMediaNode($ar);
			  $next->increasePlayCount();
			}
		}
	
		/**
		* Sets the elements playcount.
		* 
		* @author Ben Dodson <bdodson@seas.upenn.edu>
		* @version 5/14/2004
		* @since 5/14/2004
		*/
		function setPlayCount($n) {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
			
			if (!(is_int($n) || is_numeric($n))) {
				return false;
			}
            $path = jz_db_escape($this->getPath("String"));
            jz_db_simple_query("UPDATE jz_nodes SET playcount = $n WHERE path = '$path'");
		}


	
       /**
		* Increments the node's view count
		*
		* 
		* @author Ben Dodson <bdodson@seas.upenn.edu>
		* @version 3/15/2005
		* @since 3/15/2005
		*/
		function increaseViewCount() {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
			
			$path = jz_db_escape($this->getPath("String"));
            jz_db_simple_query("UPDATE jz_nodes SET viewcount = viewcount+1 WHERE path = '$path'");
		}


		/**
		* Returns the number of times the node has been viewed.
		* 
		* @author Ben Dodson <bdodson@seas.upenn.edu>
		* @version 3/15/2005
		* @since 3/15/2005
		*/
		function getViewCount() {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
			
            $path = jz_db_escape($this->getPath("String"));
            $results = jz_db__simple_query("SELECT viewcount FROM jz_nodes WHERE path = '$path'");
            return $results['viewcount'];
		}

       /**
		* Sets the elements viewcount
		* 
		* @author Ben Dodson <bdodson@seas.upenn.edu>
		* @version 8/11/05
		* @since 8/11/05
		*/
		function setViewCount($n) {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;

			if (!(is_int($n) || is_numeric($n))) {
				return false;
			}
            $path = jz_db_escape($this->getPath("String"));
            jz_db_simple_query("UPDATE jz_nodes SET viewcount = $n WHERE path = '$path'");
		}

		/**
		* Returns the number of times the node has been downloaded.
		* 
		* @author Ben Dodson <bdodson@seas.upenn.edu>
		* @version 5/14/2004
		* @since 5/14/2004
		*/
		function getDownloadCount() {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
					       
			if (isset($this->dlcount)) {
			  return $this->dlcount;
			}
	
            $path = jz_db_escape($this->getPath("String"));
            $results = jz_db_simple_query("SELECT dlcount FROM jz_nodes WHERE path = '$path'");
			$this->dlcount = $results['dlcount'];
        	return $results['dlcount'];
		}
		
		
		/**
		* Increments the node's download count, as well
		* as the count of its parents.
		* 
		* @author Ben Dodson <bdodson@seas.upenn.edu>
		* @version 5/14/2004
		* @since 5/14/2004
		*/
		function increaseDownloadCount() {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
			 		
            $path = jz_db_escape($this->getPath("String"));
            jz_db_simple_query("UPDATE jz_nodes SET dlcount = dlcount+1 WHERE path = '$path'");
                     	
            if (sizeof($ar = $this->getPath()) > 0) {
            	array_pop($ar);
                $next = &new jzMediaNode($ar);
				$next->increasePlayCount();
            }
		}

		/**
		* Sets the elements download count.
		* 
		* @author Ben Dodson <bdodson@seas.upenn.edu>
		* @version 5/14/2004
		* @since 5/14/2004
		*/
		function setDownloadCount($n) {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
			
			if (!(is_int($n) || is_numeric($n))) {
				return false;
			}
            $path = jz_db_escape($this->getPath("String"));
            jz_db_simple_query("UPDATE jz_nodes SET dlcount = $n WHERE path = '$path'");
		}

		/**
		* Returns the main art for the node.
		* 
		* @author Ben Dodson <bdodson@seas.upenn.edu>
		* @version 5/14/04
		* @since 5/14/04
		*/
		function getMainArt($dimensions = false, $createBlank = true, $imageType="audio") {
		  global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db,$jzSERVICES;
		  
		  $path = jz_db_escape($this->getPath("String"));
		  $results = jz_db_simple_query("SELECT main_art FROM jz_nodes WHERE path = '$path'");
		  
		  if ($results['main_art']) {
		    // Now let's make create the resized art IF needed
		    $this->artpath = jz_db_unescape($results['main_art']);
		    return parent::getMainArt($dimensions,$createBlank, $imageType);
		  } else if ($this->isLeaf() === false) { 
		    // Now let's see if we can get art from the tags
		    $tracks = $this->getSubNodes("tracks");
		    if (count($tracks) > 0){
		      $meta = $jzSERVICES->getTagData($tracks[0]->getDataPath());
		      // Did we get it?
		      if ($meta['pic_name'] <> ""){
			if ($dimensions){
			  // Now lets check or create or image and return the resized one
			  return $jzSERVICES->resizeImage("ID3:". $tracks[0]->getDataPath(), $dimensions, $imageType);
			} else {
			  return "ID3:". $tracks[0]->getDataPath();
			}
		      }
		    }
		  }
		  // inheritance is sweet.
		  return parent::getMainArt($dimensions,$createBlank, $imageType);
		}
		
		/**
		* Sets the node's main art
		* 
		* @author Ben Dodson
		* @version 6/7/04
		* @since 6/7/04
		*/
		function addMainArt($image) {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
			
			$image = jz_db_escape($image);
            $path = jz_db_escape($this->getPath("String"));
            $results = jz_db_simple_query("UPDATE jz_nodes SET main_art = '$image' WHERE path = '$path'");
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
		* @author Ben Dodson
		* @version 5/21/04
		* @since 5/21/04
		*/
		function getShortDescription() {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
					
            $path = jz_db_escape($this->getPath("String"));
            $results = jz_db_simple_query("SELECT descr FROM jz_nodes WHERE path = '$path'");
            if (isset($results['descr'])) {
	        	return jz_db_unescape($results['descr']);
	        } else { return false; }
		}
		
		
		/**
		* Adds a brief description.
		* 
		* @author Ben Dodson 
		* @version 5/21/04 
		* @since 5/21/04
		*/		
		function addShortDescription($text) {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
			
			$text = jz_db_escape($text); 		
            $path = jz_db_escape($this->getPath("String"));
            $results = jz_db_simple_query("UPDATE jz_nodes SET descr = '$text' WHERE path = '$path'");
		}


		/**
		* Returns the description of the node.
		* 
		* @author Ben Dodson
		* @version 5/21/04
		* @since 5/21/04
		*/
		function getDescription() {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
			
			if (isset($this->longdesc)) {
			  $desc = $this->longdesc;
				while (substr($desc,0,4) == "<br>" or substr($desc,0,6) == "<br />"){
					if (substr($desc,0,4) == "<br>"){
						$desc = substr($desc,5);
					}
					if (substr($desc,0,6) == "<br />"){
						$desc = substr($desc,7);
					}
				}
				return $desc;
			}
    		
			$path = jz_db_escape($this->getPath("String"));
			$results = jz_db_simple_query("SELECT longdesc FROM jz_nodes WHERE path = '$path'");

			if ($results['longdesc']) {
				
				$desc = jz_db_unescape($results['longdesc']);
				while (substr($desc,0,4) == "<br>" or substr($desc,0,6) == "<br />"){
					if (substr($desc,0,4) == "<br>"){
						$desc = substr($desc,5);
					}
					if (substr($desc,0,6) == "<br />"){
						$desc = substr($desc,7);
					}
				}
				$this->longdesc = $desc;
				return $desc;
			} else { 
				$this->longdesc = false;
				return false; 
			}
		}
		
		/**
		* Adds a description.
		* 
		* @author Ben Dodson
		* @version 5/21/04
		* @since 5/21/04
		*/		
		function addDescription($text) {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
			
			$text = jz_db_escape($text);
            $path = jz_db_escape($this->getPath("String"));
            $results = jz_db_simple_query("UPDATE jz_nodes SET longdesc = '$text' WHERE path = '$path'");
		}


		/**
		* Gets the overall rating for the node.
		* 
		* @author Ben Dodson
		* @version 6/7/04
		* @since 6/7/04
		*/
		function getRating() {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
				
            $path = jz_db_escape($this->getPath("String"));
            $results = jz_db_simple_query("SELECT rating_val FROM jz_nodes WHERE path = '$path'");
            return $results['rating_val'];
		}
		
		
		/**
		* Add a rating for the node.
		* 
		* @author Ben Dodson
		* @version 6/7/04
		* @since 6/7/04
		*/		
		function addRating($rating, $weight = false) {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db, $rating_weight, $jzUSER;
			
			if ($weight === false) {
			  $weight = $jzUSER->getSetting('ratingweight');
			}

			if (!$link = jz_db_connect())
                     		die ("could not connect to database.");
                     	
			$addRating = $rating * $weight;
			$addWeight = $weight;

                     	$path = jz_db_escape($this->getPath("String"));

			$results = jz_db_query($link, "SELECT rating,rating_count FROM jz_nodes WHERE path = '$path'");
			if ($results->data[0]['rating_count'] == 0) {
			  $rval = $rating;
			} else {
			  $rval = estimateRating(($results->data[0]['rating'] + $addRating) / ($results->data[0]['rating_count'] + $addWeight));
			}

			$results = jz_db_query($link, "UPDATE jz_nodes SET rating=rating+$addRating,
                                                     rating_count=rating_count+$addWeight, rating_val=$rval WHERE path = '$path'");
		
			if ($rating_weight > 0 && $this->getLevel() > 0) {
				$path = $this->getPath();
				array_pop($path);
				$next = &new jzMediaNode($path);
				$next->addRating($rating, $weight * $rating_weight);
			}
			jz_db_close($link);
		}
		
		/**
		* Gets the number of people who have rated this element
		* 
		* @author Ben Dodson
		* @version 6/11/04
		* @since 6/11/04
		*/
		function getRatingCount() {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
			
            $path = jz_db_escape($this->getPath("String"));
            $results = jz_db_simple_query("SELECT rating_count FROM jz_nodes WHERE path = '$path'");
            return $results['rating_count'];
		}


		/**
		* Returns the node's discussion
		* 
		* @author Ben Dodson
		* @version 6/7/04
		* @since 6/7/04
		*/
		function getDiscussion() {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
			
			if (!$link = jz_db_connect())
                     		die ("could not connect to database.");
                     		
                     	$path = jz_db_escape($this->getPath("String"));
                     	
                     	$results = jz_db_query($link, "SELECT * FROM jz_discussions WHERE path LIKE '$path' ORDER BY my_id");
                     	
                     	$discussion = array();
                     	$i = 0;
                     	foreach ($results->data as $key => $data) {
                     		$discussion[$i]['user'] = jz_db_unescape($data['user']);
                     		$discussion[$i]['comment'] = jz_db_unescape($data['comment']);
				$discussion[$i]['id'] = $data['my_id'];
				$discussion[$i]['date'] = $data['date_added'];
                     		
                     		$i++;
                     	}
                     	jz_db_close($link);
                     	return ($discussion == array()) ? false : $discussion;
		}


		/**
		* Adds a blurb to the node's discussion
		* 
		* @author Ben Dodson <bdodson@seas.upenn.edu>
		* @version 8/11/05
		* @since 5/15/04
		*/				
		function addDiscussion($text,$username) {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
			
			if (!$link = jz_db_connect())
                     		die ("could not connect to database.");
                     		
                     	$path = jz_db_escape($this->getPath("String"));
                     	$text = jz_db_escape($text);
                     	$username = jz_db_escape($username);
                     	
			$res = jz_db_query($link,"SELECT * FROM jz_discussions");
			$num = $res->rows + 1;
                     	if (false === jz_db_query($link, "INSERT INTO jz_discussions(my_id,path,my_user,comment,date_added)
                     	                  VALUES($num,'$path','$username','$text',".time().")")) die(jz_db_error($link));
			jz_db_close($link);
		}


		
        /**
		 * Adds a full discussion,
		 * given from $element->getDiscussion();
		 *
		 * @author Ben Dodson
		 * @version 8/11/05
		 * @since 8/11/05
		 **/
         function addFullDiscussion($disc) {
		   global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
		   
		   $path = jz_db_escape($this->getPath("String"));
		   foreach ($disc as $entry) {
		     $user = jz_db_escape($entry['user']);
		     $comment = jz_db_escape($entry['comment']);
		     $id = $entry['id'];
		     $date = $entry['date'];
		     
		     jz_db_simple_query("INSERT INTO jz_discussions(my_id,path,user,comment,date_added)
                     	                  VALUES($id,'$path','$user','$comment',$date)");
		   }	     
		 }
		



		/**
		* Returns the year of the element;
		* if it is a leaf, returns the info from getMeta[year]
		* else, returns the first matching year it finds.
		* Entry is '-' for no year.
		* 
		* @author Ben Dodson
		* @version 5/21/04
		* @since 5/21/04
		*/		
		function getYear() {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
			
			if (isset($this->year)) {
			  return $this->year;
			}

            $path = jz_db_escape($this->getPath("String"));
            if ($this->isLeaf()) {
            	$results = jz_db_simple_query("SELECT year FROM jz_tracks WHERE path = '$path'");
				$this->year = $results['year'];
	            return $results['year'];
            } else { 
	        	$results = jz_db_simple_query( "SELECT year FROM jz_tracks WHERE path LIKE '${path}/%' AND year != '-' ORDER BY path LIMIT 1");
	            if (false !== $results) {
					$this->year = $results['year'];
					return $results['year'];
	       		} else { 
					$this->year = "-";
					return "-"; 
				}
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
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db, $allow_filesystem_modify;
									
			if ($allow_filesystem_modify) {
				$path = jz_db_escape($this->getPath("String"));
                $results = jz_db_simple_query("SELECT filepath FROM jz_nodes WHERE path = '$path'");
                return jz_db_unescape($results['filepath']);
			}
			else {
				return $this->data_dir;
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
		function getFilePath() {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db, $allow_filesystem_modify;
										
            $path = jz_db_escape($this->getPath("String"));
            $results = jz_db_simple_query("SELECT filepath FROM jz_nodes WHERE path = '$path'");
           	return jz_db_unescape($results['filepath']);
		}
		
		
		function setFilePath($mypath) {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db, $allow_filesystem_modify;
	
            $path = jz_db_escape($this->getPath("String"));
            $mypath = jz_db_escape($mypath);
            $results = jz_db_simple_query("UPDATE jz_nodes SET filepath = '$mypath' WHERE path = '$path'");
		}
		
		
		/**
		* Marks this element as hidden.
		* 
		* @author Ben Dodson
		* @version 9/18/04
		* @since 9/18/04
		*/
		function hide() {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
	
            $path = jz_db_escape($this->getPath("String"));
            jz_db_simple_query("UPDATE nodes SET hidden='true' WHERE path = '$path'");
                     	
            if ($this->isLeaf()) {
            	jz_db_simple_query("UPDATE jz_tracks SET hidden='true' WHERE path = '$path'");
            }	
		}


		/**
		* Unhides the element
		* 
		* @author Ben Dodson
		* @version 9/18/04
		* @since 9/18/04
		*/
		function unhide() {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;

            $path = jz_db_escape($this->getPath("String"));
            jz_db_simple_query("UPDATE jz_nodes SET hidden='false' WHERE path = '$path'");
                     	
            if ($this->isLeaf()) {
            	jz_db_simple_query("UPDATE jz_tracks SET hidden='false' WHERE path = '$path'");
            }
		}
		// end global_include: overrides.php
		
	}

	class jzRawMediaTrack extends jzMediaTrackClass {
		// Meta stuff
		var $title;
		/**
		* Constructor wrapper for jzMediaTrack.
		* 
		* @author Ben Dodson
		* @version 5/11/04
		* @since 5/11/04
		*/
		function jzMediaTrack($par = array(),$mode="path") {
			$this->title = false;
			$this->_constructor($par,$mode);
		}
		
		
		/**
		* Returns the track's name (not filename)
		* 
		* @author Ben Dodson
		* @version 5/21/04
		* @since 5/21/04
		*/	
		function getName() {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
			
			if (!isNothing($this->title)) {
			  return $this->title;
			}
			
			if (!$link = jz_db_connect())
                     		die ("could not connect to database.");
                     		
                     	$path = jz_db_escape($this->getPath("String"));
                     	$results = jz_db_query($link, "SELECT trackname FROM jz_tracks WHERE path LIKE '$path'");
                     	$name = jz_db_unescape($results->data[0]['trackname']);
			if (isNothing($name)) {
			  return $this->path[sizeof($this->path)-1];
			} else {
			  return $name;
			}
		}
		
		
		/**
		* Returns the track's complete, useable file path.
		* $target is one of: user|host|general
		* 
		* @author Ben Dodson
		* @version 11/12/04
		* @since 5/21/04
		*/		
		function getFileName($target = "user") {
		
			if ($this->playpath === false || $this->playpath == "") {
				// Let's check the DB connection?
				if (!$link = jz_db_connect())
					die ("could not connect to database.");
			
				$path = jz_db_escape($this->getPath("String"));
				$results = jz_db_query($link, "SELECT filepath FROM jz_nodes WHERE path LIKE '$path'");
				jz_db_close($link);
				$string = jz_db_unescape($results->data[0]['filepath']);
				
				if ($string == "" || $string == false) {
					// ERROR?
					return false;
				}
				$this->playpath = $string;
			}
			return parent::getFileName($target);
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
		* number
		* genre
		* artist
		* album
		* lyrics
		* type [extension]
		* 
		* These are taken mostly from the ID3.
		*
		* @author
		* @version
		* @since
		*/		
		function getMeta($mode = "cache") {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db,$media_dirs;
		
			if ($mode == "cache" && $this->meta != array()) {
				return $this->meta;
			}
			if ($mode == "cache") {
				$meta = array();
				if (!$link = jz_db_connect())
					die ("could not connect to database.");
					
				$path = jz_db_escape($this->getPath("String"));
				$results = jz_db_query($link, "SELECT jz_tracks.*,jz_nodes.name,jz_nodes.descr FROM jz_tracks,jz_nodes 
				WHERE jz_nodes.path = '$path' AND jz_nodes.path = jz_tracks.path");
				
				$meta['title'] = jz_db_unescape($results->data[0]['trackname']);
				$meta['bitrate'] = jz_db_unescape($results->data[0]['bitrate']);
				$meta['frequency'] = jz_db_unescape($results->data[0]['frequency']);
				$meta['filename'] = jz_db_unescape($results->data[0]['name']);
				$meta['size'] = jz_db_unescape($results->data[0]['filesize']);
				$meta['year'] = jz_db_unescape($results->data[0]['year']);
				$meta['comment'] = jz_db_unescape($results->data[0]['descr']);
				$meta['length'] = jz_db_unescape($results->data[0]['length']);
				$meta['number'] = jz_db_unescape($results->data[0]['number']);
				$meta['genre'] = jz_db_unescape($results->data[0]['genre']);
				$meta['artist'] = jz_db_unescape($results->data[0]['artist']);
				$meta['album'] = jz_db_unescape($results->data[0]['album']);
				$meta['lyrics'] = jz_db_unescape($results->data[0]['lyrics']);
				$meta['type'] = jz_db_unescape($results->data[0]['extension']);
				
				// Now let's get the ID
				$pArr = $this->getPath();
				unset($pArr[count($pArr)-1]);
				$path = implode("/",$pArr);
				if (is_file($media_dirs. "/". $path. "/album.id")){
					$meta['id'] =  file_get_contents($media_dirs. "/". $path. "/album.id");
				}
				
				if (isNothing($meta['type'])) {
				  $meta = parent::getMeta("file");
				  $this->setMeta($meta,"cache");
				}

				if (isNothing($meta['artist'])) {
				  $mnode = $this->getAncestor("artist");
				  if ($mnode !== false) {
				    $meta['artist'] = $mnode->getName();
				  }
				}
				if (isNothing($meta['length'])) {
				  $meta['length'] = '';
				}

				return $meta;
			}
			else {
				return parent::getMeta($mode);
			}
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
		function setMeta($meta, $mode = false) {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
			
			if ($mode == false) {
				$this->setMeta($meta,"file");
				$this->setMeta($meta,"cache");
			}
			if ($mode == "cache") {
				if (!$link = jz_db_connect())
                     			die ("could not connect to database.");
			
				$slashedFilePath = jz_db_escape($this->getPath("String"));
				$updatesql = "UPDATE jz_tracks SET valid = 'true'";
				
				
				if (isset($meta['title']))
					$updatesql .= ", trackname = '" . jz_db_escape($meta['title']) ."'";
	                     	if (isset($meta['bitrate']))
	                     		$updatesql .= ", bitrate = '" . jz_db_escape($meta['bitrate']) ."'";
	                     	if (isset($meta['frequency']))
	                     		$updatesql .= ", frequency = '" . jz_db_escape($meta['frequency']) . "'";
	                     	if (isset($meta['size']))
	                     		 $updatesql .= ", filesize = '" . jz_db_escape($meta['size']) . "'";
	                     	if (isset($meta['year']))
	                     		 $updatesql .= ", year = '" . jz_db_escape($meta['year']) . "'";
	                     	
	                     	if (isset($meta['length']))
	                     		$updatesql .= ", length = '" . jz_db_escape($meta['length']) . "'";
	                     	if (isset($meta['number']))
	                     		$updatesql .= ", number = '" . jz_db_escape($meta['number']) . "'";
	                     	if (isset($meta['genre']))
	                     		$updatesql .= ", genre = '" . jz_db_escape($meta['genre']) . "'";
	                     	if (isset($meta['artist']))
	                     		$updatesql .= ", artist = '" . jz_db_escape($meta['artist']) . "'";
	                     	if (isset($meta['album']))
	                     		$updatesql .= ", album = '" . jz_db_escape($meta['album']) . "'";
	                     	if (isset($meta['lyrics']))
	                     		$updatesql .= ", lyrics = '" . jz_db_escape($meta['lyrics']) . "'";
	                     	if (isset($meta['type']))
	                     		$updatesql .= ", extension = '" . jz_db_escape($meta['type']) . "'";
						
				$updatesql .= " WHERE path LIKE '$slashedFilePath'";
				jz_db_query($link,$updatesql);
				
				
				
				$updatesql = "UPDATE jz_nodes SET valid = 'true'";								
				if (isset($meta['filename']))
	                     		 $updatesql .= ", name = '" . jz_db_escape($meta['filename']) . "'";
	                    	if (isset($meta['comment']))
	                     		$updatesql .= ", descr = '" . jz_db_escape($meta['comment']) . "'";		
	                     		
				$updatesql .= " WHERE path LIKE '$slashedFilePath'";
				jz_db_query($link,$updatesql);

				return true;
			} else if( $mode != false ) {
				return parent::setMeta($meta,$mode);
			}
		}

		/**
		* Returns the track's lyrics.
		* 
		* @author Ben Dodson
		* @version 5/21/04
		* @since 5/21/04
		*/		
		function getLyrics() {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
			
			if (!$link = jz_db_connect())
                     		die ("could not connect to database.");
                     		
                     	$path = jz_db_escape($this->getPath("String"));
                     	$results = jz_db_query($link, "SELECT lyrics FROM jz_tracks WHERE path LIKE '$path'");
                     	return jz_db_unescape($results->data[0]['lyrics']);
		}
		
		//////////////////////////////////////
		// begin global_include: overrides.php
		/* * * * * * * * * * * * * * * * * * *
		 *            Overrides              *
		 * * * * * * * * * * * * * * * * * * */
		
/**
 * Returns the date the node was added.
 * 
 * @author Ben Dodson <bdodson@seas.upenn.edu>
 * @version 5/14/2004
 * @since 5/14/2004
 */
function getDateAdded() {
	global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
	               		
    $path = jz_db_escape($this->getPath("String"));
    $results = jz_db_simple_query("SELECT date_added FROM jz_nodes WHERE path = '$path'");
    return $results['date_added'];
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
function newSince($days = false) {
  global $days_for_new;

  if ($days === false) {
    $days = $days_for_new;
  }

  $time = $curtime = time();
  $time -= ($days*24*60*60);

  if ($this->getLevel() == 0) {
    $pathstring = "%";
  } else {
    $pathstring = jz_db_escape($this->getPath("String"));
    $pathstring .= "/%";
  }
  $sql = "SELECT date_added FROM jz_nodes WHERE ";
  $sql .= "path LIKE '$pathstring' AND date_added >= $time ORDER BY date_added desc LIMIT 1";

  $results = jz_db_simple_query($sql);

  if (false === $results) {
    return false;
  } else {
    return ceil(abs($curtime - $results['date_added']) / (24*60*60));
  }
}


/**
 * Returns the element's ID
 *
 * @author Ben Dodson
 * @version 3/11/05
 * @since 3/11/05
 **/
function getID() {
  if (isset($this->myid) && $this->myid !== false) {
    return $this->myid;
  } else {
    $path = jz_db_escape($this->getPath("String"));
    $results = jz_db_simple_query("SELECT my_id FROM jz_nodes WHERE path = '$path'");
    return $results['my_id'];
  }
}

/**
 * Sets the elements ID.
 * Returns true on success, false on failure.
 *
 * @author Ben Dodson
 * @version 3/11/05
 * @since 3/11/05
 **/
function setID($id) {
  if (!$link = jz_db_connect())
    die ("could not connect to database.");
  
  $path = jz_db_escape($this->getPath("String"));
  $mid = jz_db_escape($id);
  $res = jz_db_query($link, "UPDATE jz_nodes SET my_id='${mid}' WHERE path = '$path'");
  if ($res === false) { // bad ID; could be a collision.
    jz_db_close($link);
    return false;
  }
  
  if ($this->isLeaf()) {
    $res = jz_db_query($link, "UPDATE jz_tracks SET my_id='${mid}' WHERE path = '$path'");
  }
  
  $this->myid = $id;
  jz_db_close($link);
  return true;
}

/**
 * Converts an id to a path
 *
 * @author Ben Dodson
 * @version 3/11/05
 * @since 3/11/05
 **/
function idToPath($id) {
    $results = jz_db_simple_query("SELECT path FROM jz_nodes WHERE my_id = '$id'");
    return $results['path'];
}

/**
 * Converts an filename to a path
 *
 * @author Ben Dodson
 * @version 3/11/05
 * @since 3/11/05
 **/
function filenameToPath($fp) {
	$fp = jz_db_escape($fp);
    $results = jz_db_simple_query("SELECT path FROM jz_nodes WHERE filepath = '$fp'");
    return $results['path'];
}

		/**
		* Returns the number of times the node has been played.
		* 
		* @author Ben Dodson <bdodson@seas.upenn.edu>
		* @version 5/14/2004
		* @since 5/14/2004
		*/
		function getPlayCount() {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
			
			if (isset($this->playcount)) {
			  return $this->playcount;
			}
	
           	$path = jz_db_escape($this->getPath("String"));
           	$results = jz_db_simple_query("SELECT playcount FROM jz_nodes WHERE path = '$path'");
			$this->playcount = $results['playcount'];
            return $results['playcount'];
		}
		
		
		/**
		* Increments the node's playcount, as well
		* as the playcount of its parents.
		* 
		* @author Ben Dodson <bdodson@seas.upenn.edu>
		* @version 5/14/2004
		* @since 5/14/2004
		*/
		function increasePlayCount() {
		  global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db,$jzUSER;
      		
            $path = jz_db_escape($this->getPath("String"));
			$sql = "UPDATE jz_nodes SET playcount = playcount+1, lastplayed = " . time();

            jz_db_simple_query("$sql  WHERE path = '$path'");
			                     	
	    $sql = "INSERT INTO jz_playcounts VALUES ";
	    $sql .= "('" . $jzUSER->getName()."','" .$this->getID(). "',now())";
	    jz_db_simple_query($sql);

            if (sizeof($ar = $this->getPath()) > 0) {
            	array_pop($ar);
                $next = &new jzMediaNode($ar);
				$next->increasePlayCount();
            }
		}
	
		/**
		* Sets the elements playcount.
		* 
		* @author Ben Dodson <bdodson@seas.upenn.edu>
		* @version 5/14/2004
		* @since 5/14/2004
		*/
		function setPlayCount($n) {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
			
			if (!(is_int($n) || is_numeric($n))) {
				return false;
			}
            $path = jz_db_escape($this->getPath("String"));
            jz_db_simple_query("UPDATE jz_nodes SET playcount = $n WHERE path = '$path'");
		}


	
       /**
		* Increments the node's view count
		*
		* 
		* @author Ben Dodson <bdodson@seas.upenn.edu>
		* @version 3/15/2005
		* @since 3/15/2005
		*/
		function increaseViewCount() {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
			
			$path = jz_db_escape($this->getPath("String"));
            jz_db_simple_query("UPDATE jz_nodes SET viewcount = viewcount+1 WHERE path = '$path'");
		}


		/**
		* Returns the number of times the node has been viewed.
		* 
		* @author Ben Dodson <bdodson@seas.upenn.edu>
		* @version 3/15/2005
		* @since 3/15/2005
		*/
		function getViewCount() {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
			
            $path = jz_db_escape($this->getPath("String"));
            $results = jz_db__simple_query("SELECT viewcount FROM jz_nodes WHERE path = '$path'");
            return $results['viewcount'];
		}

       /**
		* Sets the elements viewcount
		* 
		* @author Ben Dodson <bdodson@seas.upenn.edu>
		* @version 8/11/05
		* @since 8/11/05
		*/
		function setViewCount($n) {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;

			if (!(is_int($n) || is_numeric($n))) {
				return false;
			}
            $path = jz_db_escape($this->getPath("String"));
            jz_db_simple_query("UPDATE jz_nodes SET viewcount = $n WHERE path = '$path'");
		}

		/**
		* Returns the number of times the node has been downloaded.
		* 
		* @author Ben Dodson <bdodson@seas.upenn.edu>
		* @version 5/14/2004
		* @since 5/14/2004
		*/
		function getDownloadCount() {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
					       
			if (isset($this->dlcount)) {
			  return $this->dlcount;
			}
	
            $path = jz_db_escape($this->getPath("String"));
            $results = jz_db_simple_query("SELECT dlcount FROM jz_nodes WHERE path = '$path'");
			$this->dlcount = $results['dlcount'];
        	return $results['dlcount'];
		}
		
		
		/**
		* Increments the node's download count, as well
		* as the count of its parents.
		* 
		* @author Ben Dodson <bdodson@seas.upenn.edu>
		* @version 5/14/2004
		* @since 5/14/2004
		*/
		function increaseDownloadCount() {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
			 		
            $path = jz_db_escape($this->getPath("String"));
            jz_db_simple_query("UPDATE jz_nodes SET dlcount = dlcount+1 WHERE path = '$path'");
                     	
            if (sizeof($ar = $this->getPath()) > 0) {
            	array_pop($ar);
                $next = &new jzMediaNode($ar);
				$next->increasePlayCount();
            }
		}

		/**
		* Sets the elements download count.
		* 
		* @author Ben Dodson <bdodson@seas.upenn.edu>
		* @version 5/14/2004
		* @since 5/14/2004
		*/
		function setDownloadCount($n) {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
			
			if (!(is_int($n) || is_numeric($n))) {
				return false;
			}
            $path = jz_db_escape($this->getPath("String"));
            jz_db_simple_query("UPDATE jz_nodes SET dlcount = $n WHERE path = '$path'");
		}

		/**
		* Returns the main art for the node.
		* 
		* @author Ben Dodson <bdodson@seas.upenn.edu>
		* @version 5/14/04
		* @since 5/14/04
		*/
		function getMainArt($dimensions = false, $createBlank = true, $imageType="audio") {
		  global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db,$jzSERVICES;
		  
		  $path = jz_db_escape($this->getPath("String"));
		  $results = jz_db_simple_query("SELECT main_art FROM jz_nodes WHERE path = '$path'");
		  
		  if ($results['main_art']) {
		    // Now let's make create the resized art IF needed
		    $this->artpath = jz_db_unescape($results['main_art']);
		    return parent::getMainArt($dimensions,$createBlank, $imageType);
		  } else if ($this->isLeaf() === false) { 
		    // Now let's see if we can get art from the tags
		    $tracks = $this->getSubNodes("tracks");
		    if (count($tracks) > 0){
		      $meta = $jzSERVICES->getTagData($tracks[0]->getDataPath());
		      // Did we get it?
		      if ($meta['pic_name'] <> ""){
			if ($dimensions){
			  // Now lets check or create or image and return the resized one
			  return $jzSERVICES->resizeImage("ID3:". $tracks[0]->getDataPath(), $dimensions, $imageType);
			} else {
			  return "ID3:". $tracks[0]->getDataPath();
			}
		      }
		    }
		  }
		  // inheritance is sweet.
		  return parent::getMainArt($dimensions,$createBlank, $imageType);
		}
		
		/**
		* Sets the node's main art
		* 
		* @author Ben Dodson
		* @version 6/7/04
		* @since 6/7/04
		*/
		function addMainArt($image) {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
			
			$image = jz_db_escape($image);
            $path = jz_db_escape($this->getPath("String"));
            $results = jz_db_simple_query("UPDATE jz_nodes SET main_art = '$image' WHERE path = '$path'");
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
		* @author Ben Dodson
		* @version 5/21/04
		* @since 5/21/04
		*/
		function getShortDescription() {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
					
            $path = jz_db_escape($this->getPath("String"));
            $results = jz_db_simple_query("SELECT descr FROM jz_nodes WHERE path = '$path'");
            if (isset($results['descr'])) {
	        	return jz_db_unescape($results['descr']);
	        } else { return false; }
		}
		
		
		/**
		* Adds a brief description.
		* 
		* @author Ben Dodson 
		* @version 5/21/04 
		* @since 5/21/04
		*/		
		function addShortDescription($text) {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
			
			$text = jz_db_escape($text); 		
            $path = jz_db_escape($this->getPath("String"));
            $results = jz_db_simple_query("UPDATE jz_nodes SET descr = '$text' WHERE path = '$path'");
		}


		/**
		* Returns the description of the node.
		* 
		* @author Ben Dodson
		* @version 5/21/04
		* @since 5/21/04
		*/
		function getDescription() {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
			
			if (isset($this->longdesc)) {
			  $desc = $this->longdesc;
				while (substr($desc,0,4) == "<br>" or substr($desc,0,6) == "<br />"){
					if (substr($desc,0,4) == "<br>"){
						$desc = substr($desc,5);
					}
					if (substr($desc,0,6) == "<br />"){
						$desc = substr($desc,7);
					}
				}
				return $desc;
			}
    		
			$path = jz_db_escape($this->getPath("String"));
			$results = jz_db_simple_query("SELECT longdesc FROM jz_nodes WHERE path = '$path'");

			if ($results['longdesc']) {
				
				$desc = jz_db_unescape($results['longdesc']);
				while (substr($desc,0,4) == "<br>" or substr($desc,0,6) == "<br />"){
					if (substr($desc,0,4) == "<br>"){
						$desc = substr($desc,5);
					}
					if (substr($desc,0,6) == "<br />"){
						$desc = substr($desc,7);
					}
				}
				$this->longdesc = $desc;
				return $desc;
			} else { 
				$this->longdesc = false;
				return false; 
			}
		}
		
		/**
		* Adds a description.
		* 
		* @author Ben Dodson
		* @version 5/21/04
		* @since 5/21/04
		*/		
		function addDescription($text) {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
			
			$text = jz_db_escape($text);
            $path = jz_db_escape($this->getPath("String"));
            $results = jz_db_simple_query("UPDATE jz_nodes SET longdesc = '$text' WHERE path = '$path'");
		}


		/**
		* Gets the overall rating for the node.
		* 
		* @author Ben Dodson
		* @version 6/7/04
		* @since 6/7/04
		*/
		function getRating() {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
				
            $path = jz_db_escape($this->getPath("String"));
            $results = jz_db_simple_query("SELECT rating_val FROM jz_nodes WHERE path = '$path'");
            return $results['rating_val'];
		}
		
		
		/**
		* Add a rating for the node.
		* 
		* @author Ben Dodson
		* @version 6/7/04
		* @since 6/7/04
		*/		
		function addRating($rating, $weight = false) {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db, $rating_weight, $jzUSER;
			
			if ($weight === false) {
			  $weight = $jzUSER->getSetting('ratingweight');
			}

			if (!$link = jz_db_connect())
                     		die ("could not connect to database.");
                     	
			$addRating = $rating * $weight;
			$addWeight = $weight;

                     	$path = jz_db_escape($this->getPath("String"));

			$results = jz_db_query($link, "SELECT rating,rating_count FROM jz_nodes WHERE path = '$path'");
			if ($results->data[0]['rating_count'] == 0) {
			  $rval = $rating;
			} else {
			  $rval = estimateRating(($results->data[0]['rating'] + $addRating) / ($results->data[0]['rating_count'] + $addWeight));
			}

			$results = jz_db_query($link, "UPDATE jz_nodes SET rating=rating+$addRating,
                                                     rating_count=rating_count+$addWeight, rating_val=$rval WHERE path = '$path'");
		
			if ($rating_weight > 0 && $this->getLevel() > 0) {
				$path = $this->getPath();
				array_pop($path);
				$next = &new jzMediaNode($path);
				$next->addRating($rating, $weight * $rating_weight);
			}
			jz_db_close($link);
		}
		
		/**
		* Gets the number of people who have rated this element
		* 
		* @author Ben Dodson
		* @version 6/11/04
		* @since 6/11/04
		*/
		function getRatingCount() {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
			
            $path = jz_db_escape($this->getPath("String"));
            $results = jz_db_simple_query("SELECT rating_count FROM jz_nodes WHERE path = '$path'");
            return $results['rating_count'];
		}


		/**
		* Returns the node's discussion
		* 
		* @author Ben Dodson
		* @version 6/7/04
		* @since 6/7/04
		*/
		function getDiscussion() {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
			
			if (!$link = jz_db_connect())
                     		die ("could not connect to database.");
                     		
                     	$path = jz_db_escape($this->getPath("String"));
                     	
                     	$results = jz_db_query($link, "SELECT * FROM jz_discussions WHERE path LIKE '$path' ORDER BY my_id");
                     	
                     	$discussion = array();
                     	$i = 0;
                     	foreach ($results->data as $key => $data) {
                     		$discussion[$i]['user'] = jz_db_unescape($data['user']);
                     		$discussion[$i]['comment'] = jz_db_unescape($data['comment']);
				$discussion[$i]['id'] = $data['my_id'];
				$discussion[$i]['date'] = $data['date_added'];
                     		
                     		$i++;
                     	}
                     	jz_db_close($link);
                     	return ($discussion == array()) ? false : $discussion;
		}


		/**
		* Adds a blurb to the node's discussion
		* 
		* @author Ben Dodson <bdodson@seas.upenn.edu>
		* @version 8/11/05
		* @since 5/15/04
		*/				
		function addDiscussion($text,$username) {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
			
			if (!$link = jz_db_connect())
                     		die ("could not connect to database.");
                     		
                     	$path = jz_db_escape($this->getPath("String"));
                     	$text = jz_db_escape($text);
                     	$username = jz_db_escape($username);
                     	
			$res = jz_db_query($link,"SELECT * FROM jz_discussions");
			$num = $res->rows + 1;
                     	if (false === jz_db_query($link, "INSERT INTO jz_discussions(my_id,path,my_user,comment,date_added)
                     	                  VALUES($num,'$path','$username','$text',".time().")")) die(jz_db_error($link));
			jz_db_close($link);
		}


		
        /**
		 * Adds a full discussion,
		 * given from $element->getDiscussion();
		 *
		 * @author Ben Dodson
		 * @version 8/11/05
		 * @since 8/11/05
		 **/
         function addFullDiscussion($disc) {
		   global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
		   
		   $path = jz_db_escape($this->getPath("String"));
		   foreach ($disc as $entry) {
		     $user = jz_db_escape($entry['user']);
		     $comment = jz_db_escape($entry['comment']);
		     $id = $entry['id'];
		     $date = $entry['date'];
		     
		     jz_db_simple_query("INSERT INTO jz_discussions(my_id,path,user,comment,date_added)
                     	                  VALUES($id,'$path','$user','$comment',$date)");
		   }	     
		 }
		



		/**
		* Returns the year of the element;
		* if it is a leaf, returns the info from getMeta[year]
		* else, returns the first matching year it finds.
		* Entry is '-' for no year.
		* 
		* @author Ben Dodson
		* @version 5/21/04
		* @since 5/21/04
		*/		
		function getYear() {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
			
			if (isset($this->year)) {
			  return $this->year;
			}

            $path = jz_db_escape($this->getPath("String"));
            if ($this->isLeaf()) {
            	$results = jz_db_simple_query("SELECT year FROM jz_tracks WHERE path = '$path'");
				$this->year = $results['year'];
	            return $results['year'];
            } else { 
	        	$results = jz_db_simple_query( "SELECT year FROM jz_tracks WHERE path LIKE '${path}/%' AND year != '-' ORDER BY path LIMIT 1");
	            if (false !== $results) {
					$this->year = $results['year'];
					return $results['year'];
	       		} else { 
					$this->year = "-";
					return "-"; 
				}
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
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db, $allow_filesystem_modify;
									
			if ($allow_filesystem_modify) {
				$path = jz_db_escape($this->getPath("String"));
                $results = jz_db_simple_query("SELECT filepath FROM jz_nodes WHERE path = '$path'");
                return jz_db_unescape($results['filepath']);
			}
			else {
				return $this->data_dir;
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
		function getFilePath() {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db, $allow_filesystem_modify;
										
            $path = jz_db_escape($this->getPath("String"));
            $results = jz_db_simple_query("SELECT filepath FROM jz_nodes WHERE path = '$path'");
           	return jz_db_unescape($results['filepath']);
		}
		
		
		function setFilePath($mypath) {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db, $allow_filesystem_modify;
	
            $path = jz_db_escape($this->getPath("String"));
            $mypath = jz_db_escape($mypath);
            $results = jz_db_simple_query("UPDATE jz_nodes SET filepath = '$mypath' WHERE path = '$path'");
		}
		
		
		/**
		* Marks this element as hidden.
		* 
		* @author Ben Dodson
		* @version 9/18/04
		* @since 9/18/04
		*/
		function hide() {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;
	
            $path = jz_db_escape($this->getPath("String"));
            jz_db_simple_query("UPDATE nodes SET hidden='true' WHERE path = '$path'");
                     	
            if ($this->isLeaf()) {
            	jz_db_simple_query("UPDATE jz_tracks SET hidden='true' WHERE path = '$path'");
            }	
		}


		/**
		* Unhides the element
		* 
		* @author Ben Dodson
		* @version 9/18/04
		* @since 9/18/04
		*/
		function unhide() {
			global $sql_type,$sql_pw,$sql_usr,$sql_socket,$sql_db;

            $path = jz_db_escape($this->getPath("String"));
            jz_db_simple_query("UPDATE jz_nodes SET hidden='false' WHERE path = '$path'");
                     	
            if ($this->isLeaf()) {
            	jz_db_simple_query("UPDATE jz_tracks SET hidden='false' WHERE path = '$path'");
            }
		}
		// end global_include: overrides.php
	}
?>
