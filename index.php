<?php define('JZ_SECURE_ACCESS','true');
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
	* @since 01.11.05
	* @author Ross Carlson <ross@jinzora.org>
	* @author Ben Dodson <ben@jinzora.org>
	*/
	
	// Let's set the error reporting level
	//@error_reporting(E_ERROR);
	
	// Right away lets set the time we started

	// Now we'll need to figure out the path stuff for our includes
	// This is critical for CMS modes
	$include_path = ""; $link_root = ""; $cms_type = "standalone"; $cms_mode = "false";
  $backend = ""; $jz_lang_file = ""; $skin = ""; $my_frontend = "";

	if (isset($_GET['op'])){
		// This has got to be postnuke...
		$include_path = "modules/". $_GET['name']. "/";
		$link_root = "modules.php?";
		$cms_type = "postnuke";
		$cms_mode = "true";
	} else if (isset($_GET['name']) and !isset($_GET['op'])){
		// This has got to be phpnuke
		$include_path = "modules/". $_GET['name']. "/";
		// Now we need to see if it's CPGNuke
		if (stristr($_SERVER['PHP_SELF'],"index.php")){
			$link_root = "index.php?";
			$cms_type = "cpgnuke";
			$cms_mode = "true";
		} else {
			$link_root = "modules.php?";
			$cms_type = "phpnuke";
			$cms_mode = "true";
		}
	} else if (isset($_GET['option'])){
		// This has got to be mambo
		$include_path = "components/". $_GET['option']. "/";
		$link_root = "index.php?";
		$cms_type = "mambo";
		$cms_mode = "true";
	} else if(@file_exists(dirname(__FILE__)."/../lib-common.php")) {
	  require_once(dirname(__FILE__)."/../lib-common.php");
	  $include_path = "";
	  $link_root = "index.php?";
	  $cms_type = "geeklog";
	  $cms_mode = "true";
	} else if (@file_exists(dirname(__FILE__)."/../../mainfile.php")) {
	  include(dirname(__FILE__)."/../../mainfile.php");
	  $include_path = "";
	  $link_root = "index.php?";
	  $cms_type = "xoops";
	  $cms_mode = "true";
	} else if (@file_exists(dirname(__FILE__)."/../../class2.php")) {
	  include(dirname(__FILE__)."/../../class2.php");
	  $include_path = "";
	  $link_root = "index.php?";
	  $cms_type = "e107";
	  $cms_mode = "true";
	}

if ($cms_type != "xoops") {
  session_name('jinzora-session');
  session_start();
}

$_SESSION['jz_load_time'] = microtime();

 	$web_path = $include_path;
	$install_complete = "no";

	// cyclic dependencies...
	@include($include_path. 'settings.php'); 
	@include_once($include_path. 'system.php');        
	@include($include_path. 'settings.php');
	
	include_once($include_path. "lib/general.lib.php");
	include_once($include_path. 'services/class.php');

	writeLogData("messages","Index: --------------- Beginning Jinzora session ---------------");
	
	// Load our external services:
	writeLogData("messages","Index: Loading default services");
	$jzSERVICES = new jzServices();
	$jzSERVICES->loadStandardServices();

	if ($cms_mode == "true") {
		writeLogData("messages","Index: Setting up CMS variables");
		$ar = $jzSERVICES->cmsGETVars();
		foreach ($ar as $id => $val) {
			$link_root .= $id . "=" . $val . "&";
		}
	}
						
	if (isset($_GET['install'])){
		// Now let's include the right file
		if ($_GET['install'] == "step7") {
			if (isset($_POST['submit_step6_more']))
				$_GET['install'] = 'step6';
		}
		if (strpos($_GET['install'], '..') !== false) {
			die();
		}
		
		include_once($include_path. 'install/'. $_GET['install']. ".php");
		exit();
	}
        $force_install = false;
        if ($install_complete == "no") {
	  $force_install = true;
	} else {
	  $cv = explode(".",$config_version);
	  $v = explode(".",$version);
	  if (!($cv[0] == $v[0] && $cv[1] == $v[1])) {
	    $force_install = true;
	  }
	}
             if ($force_install){
		// To make upgrades easy let's see if they have a settings file already
		// If they do we'll include the new one first so the new variable are already
		$root_dir = ""; $media_dirs = "";
		// populated for them 
		if (is_file('settings.php')){
			@include_once($include_path. 'settings.php');
			// Let's let them know we are upgrading 
			$upgrade = "Yes";
		}
		// Ok, it hasn't been installed so let's send them to the installer 
		include_once($include_path. 'install/step1.php');
		exit();
	}

	// Security...
	if ($cms_mode == "false" && $cms_type != "standalone") {
		die('Security breach detected.');
	}

	// let's fix all of our variables.
	// see "url.php" for details.
	writeLogData("messages","Index: Cleaning POST and GET variables");
	$_GET = unurlize($_GET);
	$_POST = unpostize($_POST); 

	writeLogData("messages","Index: Checking theme settings");
	
	// Now set up our backend; this is required for all Jinzora components.
	writeLogData("messages","Index: Including backend functions");
	include_once($include_path. 'backend/backend.php');
	include_once($include_path. 'frontend/display.php');


	// reset our this_page in case we need to add some temporary variables (frontend, etc.)
	$this_page = setThisPage();
	
	writeLogData("messages","Index: Checking for searching");
	if (isset($_GET['doSearch'])) {
		$_GET['action'] = "search";
		if (isset($_GET['song_title'])) {
			$_GET['search_query'] = $_GET['song_title'];	
		}
		if (!isset($_GET['search_type'])) {
			$_GET['search_type'] = "ALL";
		}
	}
	
	// copy the POST variables for a basic search to GET variables so we don't do it twice.
	// just do a powersearch with post variables.
	if (isset($_POST['doSearch'])) {
	  $_GET['action'] = "search";
	  $_GET['search_query'] = $_POST['search_query'];
	  $_GET['search_type'] = $_POST['search_type'];
	}

	if (isset($jz_path)) {
	  unset($jz_path);
	}

	// support setting the path via POST:
	if (isset($_POST['jz_path'])) {
	  $jz_path = $_POST['jz_path'];
	} else if (isset($_GET['jz_path'])) {
	  $jz_path = $_GET['jz_path'];
	} else if (!isset($jz_path)) {
	  $jz_path = "";
	}

	// maybe they set hierarchy as a string for some reason:
	if (is_string($hierarchy))
     $hierarchy = explode("/",$hierarchy);

 	// * * COMMAND LINE JINZORA * * //
	include($include_path.'frontend/cli.php');    
    
     
	// set up our user.
	writeLogData("messages","Index: Setting up the user object");
	$jzUSER = new jzUser(); // class handles _SESSION stuff.

	writeLogData("messages","Index: Loading user services");


     if (isset($_GET['user']) && isset($_GET['pass'])) {
       $store_cookie = true;
       $prehashed = (isset($_REQUEST['pw_hashed']) && $_REQUEST['pw_hashed']=='true');
       // Are they ok?
       if ($jzUSER->login($_GET['user'],$_GET['pass'],$store_cookie, $prehashed) === false) {
	 //echo "login failed";
	 //exit();
       }
     } else {
       $prehashed = true;
     }
	$jzSERVICES->loadUserServices();	
	if (!isset($_POST['action']) || $_POST['action'] != "login") {
	  handleUserInit();
	  writeLogData("messages","Index: Including the icons");
	  include_once($include_path. "frontend/icons.lib.php");
	  writeLogData("messages","Index: Creating a new frontend object");
	} else {
	  handleSetFrontend(false);
	}
	
	@include_once($include_path. "lang/${jz_language}-simple.php");
  @include_once($include_path. "lang/${jz_language}-extended.php");

	writeLogData("messages","Index: Testing the frontend file for security and including");
	@include_once($include_path.'frontend/frontends/'.$my_frontend.'/settings.php');
	
	// Now let's see what the user was doing?
	if (isset($_GET['action'])){
		if ($_GET['action'] == "logout"){
			writeLogData("messages","Index: Logging the user out");
			$jzUSER->logout();
		}
	}
	if ($jzUSER->getName() == ""){
		$jzUSER->logout();
	}

	// handle changing of settings:
	// These affect the session, NOT the user settings.
	// This is handled in general.lib.php: setThisPage().
	if (isset($_POST['action'])){
		if ($_POST['action'] == "popup") {
			$_GET['action'] = "popup";
			$_GET['ptype'] = $_POST['ptype'];
		}
	}

	if (checkPermission($jzUSER,"view") === false && (!isset($_POST['action']) || $_POST['action'] != "login" )) {
		// Now are we in CMS mode or what?
		if ($cms_type == "standalone" || $cms_type == "false" || $cms_type == ""){
			writeLogData("messages","Index: Sending the user to the login page");
			$fe->loginPage();
			exit();
		}
	}

	// Detect our current playlist:
	if (isset($_GET['jz_playlist'])) {
		$_SESSION['jz_playlist'] = $_GET['jz_playlist'];
	}
	
	if (isset($_POST['jz_playlist'])) {
		$_SESSION['jz_playlist'] = $_POST['jz_playlist'];
	}

	// Should we use AJAX?
	define('NO_AJAX_LINKS','true');
	
	if (defined('NO_AJAX')) {
		define ('NO_AJAX_LINKS','true');
		define ('NO_AJAX_JUKEBOX','true');
	}

	$_SESSION['jz_path'] = $jz_path;

	// having doSearch set and an action set is a security violation,
	// since it allows executing arbitrary, unscrambled actions.
	// First some security checking:
	if (isset($_POST['update_settings'])) {
		if (!(($_GET['action'] == "popup") && (($_GET['ptype'] == "usermanager") || ($_GET['ptype'] == "preferences")))) {
			die();
		}
	}
	if (isset($_GET['action'])) {
		switch ($_GET['action']) {
		
		case "login":
			writeLogData("messages","Index: Displaying the login page");
		  $fe->loginPage();
		  exit();
		  break;
		case "register":
		  writeLogData("messages","Index: Displaying registration page");
		  $fe->registrationPage();
		  exit();
		  break;
		case "search":
			writeLogData("messages","Index: Displaying the search page results");
			if (isset($_POST['powersearch'])) {
				// don't worry about params in a powersearch; handle
				// it on the results page.
				$fe->searchResults(false,false,true);
			} else if ($_GET['search_query'] == "") {
				$fe->powerSearch();
				// We cannot do SQL queries before we draw the header (for CMS)
				// So we have the searchPage handle the query itself.
				// Keywords are handled in the handleSearch() function
				// See backend/backend.php.
			} else {
			  $fe->searchResults($_GET['search_query'], $_GET['search_type']);
			}
			return;
			break;
		case "powersearch":
			writeLogData("messages","Index: Displaying the power search page");
			$fe->powerSearch();			
			exit();
			break;			

		case "playlist":
			// Now let's set the clip mode
			setGlobal('CLIP_MODE',$_GET['clips']);
		
			writeLogData("messages","Index: Generating playlists");
		  if ($jzUSER->getSetting('stream') === false && $jzUSER->getSetting('lofi') === false && $jzUSER->getSetting('jukebox_queue') === false) {
		    exit();
		  }
		  if (isset($_GET['type']) && $_GET['type'] == "playlist") {
			  // TODO: could pass the ID as a paramater and not automatically update the session variable.
			  $pid = false;
			  if (isset($_GET['jz_pl_id'])) {
			    $pid = $_GET['jz_pl_id'];
			  }
			  if (isset($_GET['plowner'])) {
			    $u= new jzUser(false,$_GET['plowner']);
			    $pl=$u->loadPlaylist($pid);
			  } else {
			    $pl = $jzUSER->loadPlaylist($pid);
			  }
			  if ($_GET['mode'] == "random") {
			    $pl = $pl->getSmartPlaylist();
			  }
			  $pl->play();
			}
			else if (isset($_GET['type']) && $_GET['type'] == "track") {
				// send file directly if method == direct.
				// otherwise send a playlist.
				// TODO: if method = direct, do things like update playcount.
				// and also validate user (pass username / md5(password) in URL)
				$el = &new jzMediaTrack($_GET['jz_path']);
				$pl = &new jzPlaylist();
				$pl->add($el);
                                if (isset($_GET['clip'])) {
                                  setGlobal("CLIP_MODE",true);
                                } 
				$pl->play();
			} else {
			  // Ok, was this a radio playlist or standard
				if (isset($_GET['mode']) && $_GET['mode'] == "radio"){
					// Let's set the limit
					$lim = (isset($_GET['limit'])) ? $_GET['limit'] : false;
					
					// Now let's get the tracks from the primary artist
					$el = &new jzMediaNode($_GET['jz_path']);
					$pl = new jzPlaylist();
					$pl->add($el);
					$pl = $pl->getSmartPlaylist($lim,"radio");
					$pl->play();
				} else {
					$el = &new jzMediaNode($_GET['jz_path']);
					$rand = (isset($_GET['mode']) && $_GET['mode'] == "random") ? true : false;
					$lim = (isset($_GET['limit'])) ? $_GET['limit'] : false;
                                        $tlist = $el->getSubNodes("tracks",-1,$rand,$lim);
                                        if ($rand === false) {
                                          sortElements($tlist,"number"); 
                                        }
					$pl = &new jzPlaylist($tlist);
					$pl->play();
				}
			}
			exit();
			break;
		case "setplayback":
		  // Stupid code. Should just use the id as the variable.
		  // but if it ain't broke...
		  $_SESSION['jb_playwhere'] = $_REQUEST['player'];
		  // Now let's figure out it's ID                                                                        
		  for ($i=0; $i < count($jbArr); $i++){
		    if ($jbArr[$i]['description'] == $_SESSION['jb_playwhere']){
		      $_SESSION['jb_id'] = $i;
		    }
		  }

		  exit();
		  break;
		case "jukebox":
			// Do we need to use the standard jukebox or not?
			// Now did they have a subcommand?
		  if ($jzUSER->getSetting('jukebox_admin') === false && $jzUSER->getSetting('jukebox_queue') === false) {
		    echo 'insufficient permissions.';
		    exit();
		  }
			if (isset($_GET['subaction']) or isset($_POST['subaction'])){
				// Now let's pass our command
				if (isset($_REQUEST['command'])){
					$command = $_REQUEST['command'];
				}
				
				// Let's include the Jukebox classes
				writeLogData("messages","Index: Passing command: ". $command. " to the jukebox");
				include_once($include_path. "jukebox/class.php");
				$jb = new jzJukebox();
				$jb->passCommand($command);
			}
			//flushdisplay();
			usleep(750000);
			if (isset($_GET['frame'])){
				include_once($include_path. "frontend/frontends/jukezora/topframe.php");
				exit();
			} else {
				include_once($include_path. "jukebox.php");
				exit();
			}
		break;

		case "generateRandom":
			writeLogData("messages","Index: Generating a random playlist");
		  if ($jzUSER->getSetting('stream') === false && $jzUSER->getSetting('lofi') === false) {
		    exit();
		  }
			$pl = &new jzPlaylist();
			// Let's time it.
			$timer = microtime_float();
			$pl->generate($_GET['random_play_type'],$_GET['random_play_number'],$_GET['random_play_genre']);
			$timer = round(microtime_float() - $timer,2);
			if ($_GET['random_play_genre'] != "") {
			  writeLogData('playback', "generated random playlist of ". $_GET['random_play_number'] . " tracks from genre '" . $_GET['random_play_genre'] . "' in $timer seconds.");
			} else {
			  writeLogData('playback', "generated random playlist of ". $_GET['random_play_number'] ." tracks in $timer seconds.");
			}
			$pl->play();
			exit();
			break;
		case "download":
			writeLogData("messages","Index: Beginning a file download for: ". $_GET['jz_path']);
			//while (@ob_end_flush());
			if ($_GET['type'] == "track" && $single_download_mode == "raw") {
			  $el = &new jzMediaTrack($_GET['jz_path']);
			  if (!checkStreamLimit($el)){
			    // TODO: AJAX this so we don't come to a page, but get a Javascript alert.
			    echo word('Sorry, you have reached your download limit.');
			    exit();
			  }
				// Are they downloading something resampled?
				if (stristr($_GET['jz_path'],"data/resample")){
					$name = $el->getPath();
					$name = $name[sizeof($name)-1];
					sendMedia($_GET['jz_path'], $name, $resample, true);
				} else {
					$el->increaseDownloadCount();
					$name = $el->getPath();
					$name = $name[sizeof($name)-1];
					sendMedia($el->getFileName("host"),$name, $resample, true);
				}
				exit();
			} else if ($_GET['type'] == "playlist") {
				$pl = $jzUSER->loadPlaylist($_GET['jz_pl_id']);
				$pl->download();
			} else {
				$pl = new jzPlaylist();
				if ($_GET['type'] == "track") {
					$el = &new jzMediaTrack($_GET['jz_path']);
				} else {
					$el = &new jzMediaNode($_GET['jz_path']);
				}
				if ($el->getLevel() == 0) { die(); }
				$pl->add($el);
				$pl->rename($el->getName());
				$pl->download();
			}
			exit();
		  break;


			/** Not yet...
		case "import":
			if (isset($_GET['query']))
				$node->mediaImport("jzLibrary","URL");
			else {
				echo "<form>URL: <input type=\"text\" name=\"query\">";
				echo '<input type="hidden" name="'.jz_encode("jz_path").'" value="'.jz_encode($_GET['jz_path']).'">';
				echo '<input type="hidden" name="'.jz_encode("action").'" value="'.jz_encode("import").'">';
				echo '<br><input type="submit" value="Import Media"></form>';
			}
			exit();
			break;
			
		case "export":
			$node->mediaExport("jzLibrary");
			exit();
			break;

			**/
		

		case "addToPlaylist":
		  if (!isset($_REQUEST['jz_path']) || !isset($_REQUEST['type'])) exit();
		  
		  if ($_REQUEST['type'] == 'track') {
		    $el = new jzMediaTrack($_REQUEST['jz_path']);
		  } else {
		    $el = new jzMediaNode($_REQUEST['jz_path']);
		  }
		  $pl = $jzUSER->loadPlaylist();
		  $pl->add($el);
		  $jzUSER->storePlaylist($pl);

		  if (!defined('NO_AJAX_JUKEBOX')) {
		    $blocks = new jzBlocks();
		    $blocks->playlistDisplay();
		  }
		  break;
		  
		} /* case */
	}
	/* * * * * * * * * */
	// // // // // // / / 
	/*******************/
	if (isset($_POST['action'])) {
		switch ($_POST['action']) {
		case "login":
		  if (isset($_POST['self_register'])) {	
			writeLogData("messages","Index: Showing the self registration page");
			// We are 'anonymous' for sure. Handle his variables now.
			handleUserInit();
			include_once($include_path. "frontend/class.php");
			$fe = new jzFrontend();
			$fe->registrationPage();
		    exit();
		  }
			$remember = (isset($_POST['remember'])) ? true : false;
			if ($_POST['field2'] == "cms-user") {
			  die("Security breach detected.");
			}
			if (($jzUSER->login($_POST['field1'],$_POST['field2'], $remember, $prehashed)) === false) {
				writeLogData("messages","Index: Displaying the login page");
				include_once($include_path. "frontend/class.php");
				$fe = new jzFrontendClass();
				$fe->loginPage(true);
				exit();
			}
			if ($jzUSER->getSetting('view') === false) {
			  include_once($include_path. "frontend/class.php");
			  $fe = new jzFrontendClass();
			  $fe->loginPage();
			  exit();
			}			
			$jzSERVICES->loadUserServices();
			handleUserInit();
			writeLogData("messages","Index: Including the icons");
			include_once($include_path. "frontend/icons.lib.php");
			break;
		case "mediaAction":
			writeLogData("messages","Index: Preforming a media action");
		  if (isset($_POST['randomize']) && $_POST['randomize'] == "true") {
		    unset($_POST['sendList']);
		    $_POST['sendListRandom'] = "true";
		  }

			$exit = true;
			if (isset($_POST['jz_path']) && (isset($_POST['sendPath']) || isset($_POST['sendPathRandom'])
				 || ((isset($_POST['sendList']) || isset($_POST['sendListRandom'])) && sizeof($_POST['jz_list']) == 0))) {
			  $guy = &new jzMediaNode($_POST['jz_path']);
				
				// should we do any filtering?
				if (isset($_POST['doquery']) && $_POST['query'] != "") {
					if ($_POST['how'] == "search") {
						$root = &new jzMediaNode();
						$pl = &new jzPlaylist($root->search(stripSlashes($_POST['query']),"tracks",-1));	
					}
					else  {
						$pl = &new jzPlaylist($guy->search(stripSlashes($_POST['query']),"tracks",-1));	
					}
				} else {
					$pl = &new jzPlaylist(array($guy));
				}
				
				if (isset($_POST['sendPathRandom']) || isset($_POST['sendListRandom'])) {
					$pl->flatten();
					$pl->shuffle();
					
					if (isset($_POST['limit'])) {
						$pl->truncate($_POST['limit']);
					}
				}
				else if (isset($_POST['limit'])) {
					$pl->flatten();
					$pl->truncate($_POST['limit']);
				}
				
				$pl->play();
			}
			else if (isset($_POST['info'])) {
				echo "get info for the given list.";
			}
			else if (isset($_POST['playplaylist'])) {			  
			  $pl = $jzUSER->loadPlaylist();
			  if ($_POST['playplaylist'] == 'random') {
			    $pl->shuffle();
			  }
			  $pl->play();
			} else if (isset($_POST['addList']) && sizeof($_POST['jz_list']) > 0) {
			  $exit = false;
				$pl = $jzUSER->loadPlaylist();
				if (!is_object($pl)) {
				  $pl = new jzPlaylist();
				}
				$pl->addFromForm();
				$jzUSER->storePlaylist($pl);

				if (!defined('NO_AJAX_JUKEBOX')) {
				  $blocks = new jzBlocks();
				  $blocks->playlistDisplay();
				  exit();
				}
			}
			else if ((isset($_POST['jz_path']) && isset($_POST['addPath']))
				 || (isset($_POST['addList']) && sizeof($_POST['jz_list']) == 0)) {
				 
				$exit = false;
				$guy = &new jzMediaNode($_POST['jz_path']);
				
				if (isset($_POST['doquery']) && $_POST['query'] != "") {
				  if ($_POST['how'] == "search") {
				    $root = &new jzMediaNode();
				    $list = $root->search(stripSlashes($_POST['query']),"tracks",-1);	
				  }
				  else  {
				    $list = $guy->search(stripSlashes($_POST['query']),"tracks",-1);	
				  }
				  
				  $pl = $jzUSER->loadPlaylist();
				  $pl->add($list);
				  $jzUSER->storePlaylist($pl);

				  if (!defined('NO_AJAX_JUKEBOX')) {
				    $blocks = new jzBlocks();
				    $blocks->playlistDisplay();
				    exit();
				  }

				} else {
				  $pl = $jzUSER->loadPlaylist();
				  $pl->add($guy);
				  $jzUSER->storePlaylist($pl);

				  if (!defined('NO_AJAX_JUKEBOX')) {
				    $blocks = new jzBlocks();
				    $blocks->playlistDisplay();
				    exit();
				  }
				}
			}
			else {
			  $pl = new jzPlaylist();
			  $pl->addFromForm();
			  $pl->flatten();

			  if (isset($_POST['limit'])) {
			    $pl->truncate($_POST['limit']);
			  }
			  
			  if (isset($_POST['sendListRandom'])) {
			    $pl->shuffle();
			  }

			  $pl->play();
			}
			if ($exit) {
				exit();
			}
			break;
		case "playlistAction":
			writeLogData("messages","Index: Preforming a playlist action");
			if ($jzUSER->getSetting('stream') === false && $jzUSER->getSetting('lofi') === false && $jzUSER->getSetting('download') === false) {
				exit();
			}
			$exit = true;
			$pl = $jzUSER->loadPlaylist();
			if (isset($_POST['downloadlist'])) {
				if ($jzUSER->getSetting('download') === false) {
					exit();
				}
				$pl->download();
			} else if (isset($_POST['createlist'])) {
				if (strlen($_POST['playlistname']) > 0) {
					$pl = new jzPlaylist();
					$jzUSER->storePlaylist($pl,$_POST['playlistname']);
					$_SESSION['jz_playlist'] = $pl->getID();
				}
				$exit = false;
			} else if (isset($_POST['noaction'])) {
				$exit = false;
			} else {
				if (isset($_POST['mode'])) {
					$pl = $pl->getSmartPlaylist();
				}
				if ($jzUSER->getSetting('stream') === false && $jzUSER->getSetting('lofi') === false) {
					exit();
				}
				if (isset($_POST['playplaylist']) && $_POST['playplaylist'] == "random") {
					$pl->shuffle();
				}
				$pl->play();
			}
			if ($exit) exit();
			break;
		case "generateRandom":
			writeLogData("messages","Index: Generating a random playlist");
		  if ($jzUSER->getSetting('stream') === false && $jzUSER->getSetting('lofi') === false) {
		    exit();
		  }
			$pl = &new jzPlaylist();
			// Let's time it.
			$timer = microtime_float();
			$pl->generate($_POST['random_play_type'],$_POST['random_play_number'],$_POST['random_play_genre']);
			$timer = round(microtime_float() - $timer,2);
			if ($_POST['random_play_genre'] != "") {
			  writeLogData('playback', "generated random playlist of ". $_POST['random_play_number'] . " tracks from genre '" . $_POST['random_play_genre'] . "' in $timer seconds.");
			} else {
			  writeLogData('playback', "generated random playlist of ". $_POST['random_play_number'] ." tracks in $timer seconds.");
			}
			$pl->play();
			exit();
			break;
			
		}
	}
	// Last thing: we want to draw a standard page, since we did not previously exit.
	// TODO: check for specialty pages (search,playlistmanager,etc)

	// Let's count how many of everything we have
	if (!isset($_SESSION['jz_num_genres'])){
		$root = &new jzMediaNode();
		$_SESSION['jz_num_genres'] = $root->getSubNodeCount("nodes",distanceTo("genre"));
		$_SESSION['jz_num_artists'] = $root->getSubNodeCount("nodes",distanceTo("artist"));
		$_SESSION['jz_num_albums'] = $root->getSubNodeCount("nodes",distanceTo("album"));
		$_SESSION['jz_num_tracks'] = $root->getSubNodeCount("nodes",distanceTo("track"));
	}

	// The header file defines our drawPage function.
	$maindiv = (isset($_GET['maindiv']) || isset($_POST['maindiv'])) ? true : false;
	
	// Let's check for security
	$blocks = new jzBlocks();
	if ($blocks->checkForSecure()){
		$smarty = smartySetup();
		$smarty->assign('path', getcwd());
			
			// Now let's include the template
		$smarty->display(SMARTY_ROOT. 'templates/slick/security-warning.tpl');
		exit();
	}
	
	if (isset($_SESSION['current_interface'])) {
		$_SESSION['ref_interface'] = $_SESSION['current_interface'];
	} else {
		$_SESSION['ref_interface'] = "";
	}
	$_SESSION['current_interface'] = $fe->name;
	
	$_SESSION['jz_purge_file_cache'] = "false";			
	$fe->standardPage($node,$maindiv);
?>