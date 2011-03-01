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
	
	/** Users have the following settings:
	  [note that there is a gid for 'all media']
	  
	  stream (true|false)
	  view
	  write
	  download
	  discuss
	  lofi_only
	  resample_rate
	  resample_lock (true|false)
	  theme
	  language
	  admin
	  home_dir (specifies a home path)
	  read_lock (read operations locked to their home)
	  write_lock (write operations locked to their home)
	  email
	  full_name
	  player (an embedded player to use)
	  dialup
	  powersearch
	  ratingweight<1>
	  localpath (a local path to the music collection)
	  
	*/
	
	class jzUserClass {
		var $id;
		var $name;
		var $settings;
		var $data_dir;
		
		/**
		* Sets the artist that the user is browsing for tracking purposes
		* 
		* @author Ross Carlson
		* @version 01.11.05
		* @since 01.11.05
		* @param string $user_id the user id that is viewing
		*/
		function getRecommendations($user_id = false){
			return;
			// Ok, first let's get their entire browsing history
     		if ($user_id === false) {
				$user_id = $this->getID();
		    }
			$dp = $this->data_dir . "/". $user_id. ".tracking";
			$prevArray = unserialize(@file_get_contents($dp));
			
			// Now let's setup our service
			$service = new jzServices();
			$service->loadService("similar", "echocloud");

			$artistList = "";
			for ($i=0; $i < count($prevArray); $i++){					
				if ($prevArray[$i]['artist'] <> ""){
					$artistList .= $prevArray[$i]['artist']. "|";
				}
			}
			$favList = "";$c=0;
			for ($i=0; $i < count($prevArray); $i++){
				if ($prevArray[$i]['artist'] <> ""){
					if (!stristr($favList,$prevArray[$i]['artist'])){
						$favArray[$c]['count'] = substr_count($artistList,$prevArray[$i]['artist']);
						$favArray[$c]['artist'] = $prevArray[$i]['artist'];
						$c++;
						$favList .= $prevArray[$i]['artist'];
					}
				}
			}
			
			$root = new jzMediaNode();
			@usort($favArray, "track_cmp");
			// Now let's trim this to only 6 artists
			$favArray = @array_slice($favArray,0,6);
			for ($i=0; $i < count($favArray); $i++){
				returnSimilar($favArray[$i]['artist'], 1);
			}
		}
		
		/*
		 * Constructor for a jzUser
		 *
		 * @author Ben Dodson
		 *
		 **/		
		function jzUserClass($login = true, $uid = false) {
		  $this->_constructor($login,$uid);
		}
		
		function _constructor($login, $uid) {
		  global $include_path;
			
		  if ($login === false) {
		    $be = &new jzBackend();
		    $this->data_dir = $be->data_dir;
		    if ($uid !== false) {
		      $this->id = $uid;
		      $this->loadSettings();
		    }
		    return;
		  }

			$be = &new jzBackend();
			if (isset($_SESSION['jzUserID'])) {
				$this->id = jz_cookie_decode($_SESSION['jzUserID']);
				$this->data_dir = $be->data_dir;
				$this->loadSettings();
			} else if (isset($_COOKIE['jzUserID'])) {
				$this->id = jz_cookie_decode($_COOKIE['jzUserID']);
				$this->data_dir = $be->data_dir;
				$this->loadSettings();
				$_SESSION['jzUserID'] = $_COOKIE['jzUserID'];
			} else {
				$this->data_dir = $be->data_dir;
				$this->settings = false;
				if (($this->id = $this->lookupUID('NOBODY')) === false) {
				  $this->id = $this->addUser('NOBODY',"");
				}
				$this->loadSettings();
			}
			// Give them a session playlist, too.
			$this->initUser();
		}

		/* Initializes some
		 * user variables.
		 *
		 * @author Ben Dodson
		 * @version 4/23/05
		 * @since 4/23/05
		 **/
		function initUser() {
		  if (!isset($_SESSION['sessionPL'])) {
		    $pl = new jzPlaylist();
		    $pl->id = "session";
		    $pl->name = "Session Playlist";
		    $_SESSION['sessionPL'] = serialize($pl);
		  }
		  if (!isset($_SESSION['sid'])) {
		    $_SESSION['sid'] = uniqid('S'); // a shorter SID than session_id().
		  }
		}

		/* Gets the user's ID
		 *
		 * @author Ben Dodson
		 *
		 */
		function getID() {
			return $this->id;
		}
		
		/* Gets the user's name
		 *
		 * @author Ben Dodson
		 *
		 */
		function getName() {
			return $this->name;
		}

		/* Looks up the UID for a given username
		 *
		 * @author Ben Dodson
		 *
		 */
		function lookupUID($user) {
			$dp = $this->data_dir . "/" . "users";
			if (($f = file_get_contents($dp)) === false) {
			  return false;
			}
			$users = unserialize($f);
			if (isset($users[$user]) && is_array($users[$user])
			    && isset($users[$user]['id'])) {
			  return $users[$user]['id'];
			}
			return false;
		}
		

		function lookupName($id) {
		  $dp = $this->data_dir . "/user_settings";
		  
		  $usersettings = unserialize(file_get_contents($dp));
		  if (isset($usersettings[$id]) && isset($usersettings[$id]['name'])) {
		    return $usersettings[$id]['name'];
		  } else {
		    // fix for a break in 2.7.5.
		    $dp = $this->data_dir . "/users";
		    $users = unserialize(file_get_contents($dp));
		    foreach ($users as $name => $data) {
		      if ($id == $data['id']) {
			return $name;
		      }
		    }
		  }
		  return false;
		}

		/* Looks up the GID for a given username
		 *
		 * @author Ben Dodson
		 *
		 */
		function lookupGID($group) {
			$dp = $this->data_dir . "/" . "groups";
			$groups = unserialize(file_get_contents($dp));
			
			return (isset($groups[$group])) ? $groups[$group] : false;
		}
		
		/* Adds a group to the listings.
		 *
		 * @author Ben Dodson
		 *
		 */
		function addGroup($group) {
			$dp = $this->data_dir . "/" . "groups";
			$groups = unserialize(file_get_contents($dp));
			
			if (isset($groups[$group])) return false;
			
			$groups[$group] = sizeof($groups);
			
			if (!$handle = @fopen($dp,"w")) {
				die("Could not open groups file (" . $dp . ") for writing.");
			}
			fwrite($handle,serialize($groups));
			fclose($handle);
		}
		
		/* Adds a user to the database.
		 *
		 * @author Ben Dodson
		 * @param user the username
		 * @password the password (unencrypted)
		 */
		function addUser($user, $password) {
			$dp = $this->data_dir . "/" . "users";
			$users = unserialize(file_get_contents($dp));

			if (isset($users[$user])) {
				return false;
			}
			else {
				$users[$user]['password'] = jz_password($password);
				$users[$user]['id'] = $my_id = uniqid("USR");
			}
			writeLogData("access", "Adding user '".$user."'");
			$settings = array();
			if ($user != NOBODY) {
			  $settings['name'] = $user;
			}
			$this->setSettings($settings,$my_id);
			
			if (!$handle = @fopen($dp,"w")) {
				die("Could not open users file (" . $dp . "(for writing.");
			}
			fwrite($handle,serialize($users));
			fclose($handle);
			
			return $my_id;
		}


		/**
		 * Removes a user.
		 *
		 * @author Ben Dodson
		 * @version 4/15/05
		 * @since 4/15/05
		 **/
		function removeUser($id) {
		  $dp = $this->data_dir . "/" . "users";
		  $users = unserialize(file_get_contents($dp));

		  $name = $this->lookupName($id);
		  unset($users[$name]);
		  if (!$handle = @fopen($dp,"w")) {
		    die("Could not open users file for writing.");
		  }
		  fwrite($handle,serialize($users));
		  fclose($handle);


		  $dp = $this->data_dir . "/user_settings";
		  $usersettings = unserialize(file_get_contents($dp));
		  unset($usersettings[$id]);
		  if (!$handle = @fopen($dp,"w")) {
		    die("Could not open users file for writing.");
		  }
		  fwrite($handle,serialize($usersettings));
		  fclose($handle);
		  
		}


		/* Renames a user (but maintains other settings)
		 *
		 * @author Ben Dodson
		 * @param newname the new username
		 * @param oldname the old name (defaults to this user's name)
		 */
		function changeName($newname, $oldname = false) {
			$dp = $this->data_dir . "/" . "users";
			$users = unserialize(file_get_contents($dp));
			// update users register and user settings.
			
			if (isset($users[$newname])) {
				return false;
			}
			if ($oldname === false) {
				$oldname = $this->name;
			}
			$users[$newname] = $users[$oldname];
			$id = $users[$newname]['id'];
			unset($users[$oldname]);
			
			
			if (!$handle = @fopen($dp,"w")) {
				die("Could not open users file for writing.");
			}
			fwrite($handle,serialize($users));
			fclose($handle);
			
			$dp = $this->data_dir . "/user_settings";
			
			$usersettings = unserialize(file_get_contents($dp));
			$usersettings[$id]['name'] = $newname;
			
			if (!$handle = @fopen($dp,"w")) {
				die("Could not open users file for writing.");
			}
			fwrite($handle,serialize($usersettings));
			fclose($handle);
			
			
			$this->name = $newname;
		}
		
		/* Changes the password
		 *
		 * @author Ben Dodson
		 *
		 */
		function changePassword($newpass, $name = false) {
			$dp = $this->data_dir . "/" . "users";
			$users = unserialize(file_get_contents($dp));
			
			if ($name === false) {
				$name = $this->name;
			}
			if (!isset($users[$name])) {
				return false;
			}
			$users[$name]['password'] = jz_password($newpass);
			
			if (!$handle = @fopen($dp,"w")) {
				die("Could not open users file for writing.");
			}
			fwrite($handle,serialize($users));
			fclose($handle);	
		}

		/* Returns all users in an array
		 * $arr['id'] = "username"
		 *
		 * @author Ben Dodson
		 * @since 2/19/05
		 * @version 2/19/05
		 **/
		function listUsers() {
		  $dp = $this->data_dir . "/" . "users";
		  
		  $arr = array();
		  $users = unserialize(file_get_contents($dp));

		  foreach ($users as $name=>$info) {
		    $arr[$info['id']] = $name;
		  }
		  asort($arr);
		  return $arr;
		}
		
		/* Logs in a user
		 *
		 * @author Ben Dodson
		 *
		 */
		function login($user, $password, $remember = false, $prehashed = false) {	
                        global $cms_mode,$cms_type;

                        if ($cms_mode != "false") {
                            $cms = true;
                        } else {
                            $cms = false;
                        }

			if (!$prehashed) {
			  $password = jz_password($password);
			}

			$dp = $this->data_dir . "/" . "users";
			$users = unserialize(file_get_contents($dp));
			
			// Clear their data cache.
                        if ($cms === false) {
			  /*
			  foreach ($_SESSION as $var=>$val) {
			    unset($_SESSION[$var]);
			  }
			  */
			  //Stupid PHP!!
			  $_SESSION = array();
                       }

			$this->initUser();
			
			if ($cms !== false) {
			  // The login is coming from CMS.
			  // This means we can assume they are authenticated;
			  // Just make sure they have an entry in our users file.
			  
			  if (!isset($users[$user])) {
			    // first timer:
			    $this->addUser($user,"cms-user");
                            // TODO: LOAD PERMISSIONS FOR CMS-DEFAULTS HERE!
			    // now just re-login.
			    return $this->login($user,$password,$remember,true);
			  }
			  else {
			    if ($users[$user]['password'] != jz_password("cms-user")) { // double user. bad move.
			      // Actually let's let this fly and see how it works out for CMS users.
			      // To disallow this again, be sure to edit install/step6.php so the 
			      // admin user is created w. password 'cms-user' during a CMS install.
			      $this->id = $users[$user]['id'];
			      $_SESSION['jzUserID'] = jz_cookie_encode($this->id);
			      $this->loadSettings();
			      writeLogData("access", "cms-user '" . $user . "' logged in successfully.");
			      return true;
			    } else {
			      $this->id = $users[$user]['id'];
			      $_SESSION['jzUserID'] = jz_cookie_encode($this->id);
			      $this->loadSettings();
			      writeLogData("access", "cms-user '" . $user . "' logged in successfully.");
			      return true;
			    }
			  }				
			  return false;
			}
			// NO CMS; standard way.
			// Passwords are hashes.
			if (isset($users[$user]) && 0== strcasecmp($users[$user]['password'],$password)) {
				$this->id = $users[$user]['id'];
				if ($remember) {
					setcookie('jzUserID',jz_cookie_encode($this->id),time()+60*60*24*30);
				}
				$_SESSION['jzUserID'] = jz_cookie_encode($this->id);
				$this->loadSettings();				
				writeLogData("access", "user '" . $user . "' logged in successfully.");
				return true;
			}
			else {
				unset($_SESSION['jzUserID']);
				writeLogData("access","failed login for user '" . $user . "'.");
				return false;
			}
		}
		
		/* Logs a user out
		 *
		 * @author Ben Dodson
		 *
		 */
		function logout() {
			unset($_SESSION['jzUserID']);
			setcookie('jzUserID',"",time() - 3600);
			unset($_COOKIE['jzUserID']);
			$this->id = $this->lookupUID(NOBODY);
			$this->name = NOBODY;
			$this->loadSettings();
		}
		
		/* Gets a certain setting for the user.
		 * Also see the user_default() function in backend/backend.php.
		 *
		 * @author Ben Dodson
		 * @param $setting the setting to retrieve.
		 */
		function getSetting($setting, $recursive = false) {
		  global $USER_SETTINGS_OVERRIDE;
		  // some overrides:
		  if ($setting == "theme" && isset($_SESSION['theme'])) {
		    return $_SESSION['theme'];
		  }

		  if (isset($USER_SETTINGS_OVERRIDE) && isset($USER_SETTINGS_OVERRIDE[$setting])) {
		    return $USER_SETTINGS_OVERRIDE[$setting];
		  }

		  if ($this->settings !== false) {
		    if (isCookieSetting($setting)) {
		      if (isset($_COOKIE[$setting])) {
			return $_COOKIE[$setting];
		      } else {
			return user_default($setting);
		      }
		    }
		    if (isset($this->settings[$setting])) return $this->settings[$setting];
		    else return user_default($setting);
		  } else if ($recursive == true) {
		    return user_default($setting);
		  } else {
		    $this->loadSettings();
		    return $this->getSetting($setting, true);
		  }
		}
		
		/* Loads the user's settings.
		 *
		 * @author Ben Dodson
		 *
		 */
		function loadSettings($my_id = false) {
			if ($my_id === false) $id = $this->id;
			else $id = $my_id;
			
			$dp = $this->data_dir . "/user_settings";
			$s = unserialize(file_get_contents($dp));
			if (!isset($s[$id])) {
				$s[$id] = array();
			}
			if ($my_id === false) {
				$mysettings = array();
				$settings = $s[$id];
				// Is the login invalid?		
				if (!is_array($settings) || $settings == array()) {
				  // fetch NOBODY's details
				  $nobody = $this->lookupUID(NOBODY);
				  if ($nobody != false) {
				    $settings = $s[$nobody];
				  }
				  if (!is_array($settings)) {
				    $settings = array();
				  }
				  $s[$id] = $settings;
				}

				if (isset($settings['template']) && $settings['template'] != "") {
				  $be = new jzBackend();
				  $classes = $be->loadData('userclasses');
				  $newsettings = $classes[$settings['template']];
				  $newsettings['name'] = $this->lookupName($id);
				  /*
				  if (isset($newsettings['edit_prefs']) && $newsettings['edit_prefs'] == true) {
				    foreach ($settings as $key=>$val) {
				      switch ($key) {
					// Is it a user preference?
				      case "email":
				      case "fullname":
				      case "frontend":
				      case "theme":
				      case "language":
				      case "playlist_type":
				      case "asuser":
				      case "aspass":
				      case "sort":
					$newsettings[$key] = $val;
					break;
				      }
				    }
				    */
				    foreach ($settings as $key=>$val) {
				    	$newsettings[$key] = $val;
				    }
				  $settings = $newsettings;
				}
				foreach ($settings as $key => $val) {
				  if ($val != "") {
				    if ($val == "true") {
				      $mysettings[$key] = true;
				    } else if ($val == "false") {
				      $mysettings[$key] = false;
				    }
				    else {
				      $mysettings[$key] = $val;	
				    }
				  }
				}
				$this->settings = $mysettings;
				$this->name = isset($this->settings['name']) ? $this->settings['name'] : word('Anonymous');
				
			}
			return $s[$id];
		}
		
		function setSetting($setting, $val, $id = false) {
			$this->setSettings(array($setting => $val), $id);
		}
		
		
		/* Sets a setting or several settings.
		 * All other settings remain the same.
		 *
		 * @author Ben Dodson
		 * @param $settingsArray an associative array of keys/values
		 */
		function setSettings($settingsArray, $my_id = false, $wipe = false) {
			if ($my_id === false) $id = $this->id;
			else $id = $my_id;
		
			if (!$wipe) {
				$oldsettings = $this->loadSettings($id);
			} else {
			  $temp = $this->loadSettings($id);
			  $oldsettings = array();
			  if (isset($temp['name'])) {
			    $oldsettings['name'] = $temp['name'];
			  }
			}
			$dp = $this->data_dir . "/user_settings";

			$usersettings = unserialize(file_get_contents($dp));
			
			/*
			if (!isNothing($settingsArray['home_dir'])) {
			  // make sure their home directory exists
			  $dir = $settingsArray['home_dir'];
			  $root = new jzMediaNode();
			  $dir = $root->getFilePath() . "/" . $dir;
			  if (!is_dir($dir)) {
			    @mkdir($dir);
			  }
			}
			*/

			foreach ($settingsArray as $key => $val) {
			  if ($val === true) {
			    $oldsettings[$key] = "true";
			  } else if ($val === false) {
			    $oldsettings[$key] = "false";
			  } else if ($val == "") {
			    unset($oldsettings[$key]);
			  } else {
			    $oldsettings[$key] = $val;
			  }
			  if (isset($this->settings) && $my_id === false) {
			    $this->settings[$key] = $oldsettings[$key];
			  }
			}

			$usersettings[$id] = $oldsettings;
			
			if (!$handle = @fopen($dp,"w")) {
				die("Could not open user_settings file for writing.");
			}

			fwrite($handle,serialize($usersettings));
			fclose($handle);
			//$this->settings = $oldsettings; // new settings now.
		}


		/*
		 * Loads the given playlist
		 * @author Ben Dodson
		 * @since 1/11/05
		 * @version 1/11/05
		 * @param $id the ID to load. False means use the 'current playlist'
		 *
		 **/
		function loadPlaylist($id = false) {
		  if ($id === false) {
		    // load the current playlist.
		    // this will likely be a playlist ID stored as a session variable 
		    // for now, just use the session playlist.
		    if (isset($_SESSION['jz_playlist'])) {
		      $id = $_SESSION['jz_playlist'];
		    } else {
		      $id = "session";
		    }
		  }

		  if ($id == 'session') {
		    $pl = unserialize($_SESSION['sessionPL']);
		    return $pl;
		  } else {
		    return $this->loadData($id);
		  }
		}

		/*
		 * Stores a playlist.
		 * A new name can be given, but
		 * if it is not the playlist's name is used.
		 *
		 * @author Ben Dodson
		 * @version 1/11/05
		 * @since 1/11/05
		 * @param $pl the playlist to store
		 * @param $name a new name to save the playlist as.
		 *
		 */
		function storePlaylist($pl, $name = false) {
		  if ($pl->getID() == 'session') {
		    $_SESSION['sessionPL'] = serialize($pl);
		  } else {
		    if ($name === false) {
		      $name = $pl->getName();
		    } else {
		      $pl->rename($name);
		    }
		    $this->storeData($pl->getID(),$pl);
		    $a = $this->loadData("playlists");
		    if (!is_array($a)) {
		      $a = array();
		    }
		    $a[$pl->getID()] = $name;
		    $this->storeData("playlists",$a);
		  }
		}

		/* Returns an array of all of the users playlists.
		 * Array is formatted as $id => $name.
		 * Use: foreach ($array as $id => $name) { ...
		 * Does not currently include the session playlist.
		 *
		 * @author Ben Dodson
		 * @version 1/28/04
		 * @since 1/28/04
		 *
		 **/
		function listPlaylists($type = "static") {
		  $a = $this->loadData("playlists");
		  if (!is_array($a)) {
		    return array();
		  } else {
		    if ($type == "all") {
		      return $a;
		    }
		    $b = array();
		    foreach ($a as $l=>$name) {
		      if (getListType($l) == $type) {
			$b[$l] = $name;
		      }
		    }
		    return array_reverse($b);
		  }
		}

		/*
		 * Removes a playlist.
		 *
		 * @author Ben Dodson
		 * @version 1/28/05
		 * @since 1/28/05
		 *
		 **/
		function removePlaylist($id) {
		  $a = $this->loadData("playlists");
		  unset($a[$id]);
		  $this->storeData("playlists",$a);

		  $this->removeData($id);
		}


		/* 
		 * Moves a playlist (renames it)
		 *
		 * @author Ben Dodson
		 * @since 2/22/05
		 * @version 2/22/05
		 *
		 **/
		function renamePlaylist($id = false, $newname) {
		  $pl = $this->loadPlaylist($id);
		  $this->storePlaylist($pl,$newname);
		}


		/*
		 * Stores arbitrary data for the user.
		 *
		 * @author Ben Dodson
		 * @version 1/12/05
		 * @since 1/12//05
		 * @param data_id an indentifier for the data.
		 * @param data the data to be stored.
		 */
		function storeData($data_id, $data) {
		  $dp = $this->data_dir . "/" . $this->getID() . "-" . $data_id;

		  if (!$handle = @fopen($dp,"w")) {
		    die("Could not open file " . $dp . " for writing.");
		  }
		  fwrite($handle,serialize($data));
		  fclose($handle);
		}

		/*
		 * Loads arbitrary data for the user.
		 *
		 * @author Ben Dodson
		 * @version 1/12/05
		 * @since 1/12//05
		 * @param data_id an indentifier for the data.
		 */
		function loadData($data_id) {
		  $dp = $this->data_dir . "/" . $this->getID() . "-" . $data_id;

		  if (!file_exists($dp)) {
		    return false;
		  }
		  
		  return unserialize(@file_get_contents($dp));
		}

		/*
		 * Removes a piece of data.
		 *
		 * @author Ben Dodson
		 * @since 1/28/05
		 * @version 1/28/05
		 *
		 **/
		function removeData($data_id) {
		  $dp = $this->data_dir . "/" . $this->getID() . "-" . $data_id;

		  @unlink($dp);
		}
		
		/*
		 * Returns the track object the user
		 * is currently playing.
		 * If no track is streaming, returns false.
		 * 
		 * @author Ben Dodson
		 * @since 12/21/06
		 */
		function getCurrentlyPlayingTrack($mysid = false) {
			if ($mysid === false) {
				if (isset($_SESSION['sid'])) {
					$mysid = $_SESSION['sid'];
				} else {
					return false;
				}
			}
			$be = new jzBackend();
			$tracks = $be->getPlaying();
	 		 foreach($tracks as $sid => $song){
	    		if ($mysid == $sid) {
	      			$track = new jzMediaTrack($song['path']);
	      			return $track;
	    		}
	 		 }
	 		 return false;
		}
	}
	
?>
