<?php 
if (!defined(JZ_SECURE_ACCESS)) die ('Security breach detected.');
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
 * This page binds the backend to the frontend.
 *
 * @since 05.10.04
 * @author Ross Carlson <ross@jinzora.org>
 */

// // //
// This should be renamed to backend.lib.php
//
// First we have to include our files
include_once($include_path. 'system.php');
include_once($include_path. 'lib/general.lib.php');

// Define some constants.
define ("JZUNKNOWN","JZUNKNOWN");
define("ALL_MEDIA_GID",0);
define("NOBODY","NOBODY");
define("ALL_MEDIA_GROUP","ALL_MEDIA");


require_once(dirname(__FILE__) . "/backends/${backend}/header.php");


// Now we can build library functions.
//* * * * * * * * * * * * * * * * * *//
	
/**
 * Checks to see if a users streaming limit has been hit - returns true if it has
 * 
 * 
 * @author Ross Carlson, Ben Dodson
 * @version 7/04/2005
 * @since 7/04/2005
 * @param $track the track they will play (or array of tracks)
 * @param $willPlay whether or not they are about to stream the track.
 * For this to work properly, this function must be the last check
 * before the user can play back the track.
 */	
function checkStreamLimit($track, $willPlay = true, $user = false){
  global $jzUSER;

  if ($user === false){
    $user = $jzUSER;
  }

  $limit = $jzUSER->getSetting('cap_limit');
  if (isNothing($limit) || $limit <= 0) {
    return true;
  }
  
  $cap_duration = $jzUSER->getSetting('cap_duration');
  $cap_method = $jzUSER->getSetting('cap_method');

  // Now let's see how much they've streamed today
  $sArr = unserialize($user->loadData("streamed"));
  if (!is_array($sArr)) {
    $sArr = array();
  }
  $stArr = array();
  $streamed = 0;
  for ($i=0; $i < count($sArr); $i++) {
    // Was this within the last 24 hours?
    $age = time() - $sArr[$i][0];
    if ($age < $cap_duration*24*60*60){
      if ($cap_method == "size") {
	$streamed = $streamed + $sArr[$i][1];
      } else {
	$streamed++;
      }
      $stArr[] = $sArr[$i];
    }
  }
  // Now that we've cleaned up the history let's write it back out so it doesn't grow forever
  $user->storeData("streamed", serialize($stArr));
  
  // Let's make it look like an array no matter what:
  if (!is_array($track)) {
    $arr = array();
    $arr[] = $track;
  } else {
    $arr = $track;
  }

  $narr = array();
  $tsize = 0;
  foreach ($arr as $t) {
    $path = $t->getFilename('host');
    $tsize += round((filesize($path)/1048576-.49));
    $narr[] = array(time(),$tsize);
  }

  if ($cap_method == "size") {
    if (($tsize + $streamed) > $limit) {
      $ok = false;
    } else {
      $ok = true;
    }
  } else {
    if ((sizeof($narr) + $streamed) > $limit) {
      $ok = false;
    } else {
      $ok = true;
    }
  }
  
  if (!$ok) {
    return false;
  } else {
    if ($willPlay) {
      foreach ($narr as $e) {
	$streamArr[] = $e;
      }
      $user->storeData("streamed", serialize($streamArr));   
    }
    return true;
  }
}

/**
 * Checks whether or not a track
 * can be played right now.
 * This is based on media_lock_mode
 * and the user's permissions.
 *
 * @author Ben Dodson
 * @version 7/7/05
 * @since 7/7/05
 **/
function canPlay($el,$user) {
  global $media_lock_mode;

  // What to return:
  $permissions = false;
  $locked = false;
  $valid = true;

  // First, is the user allowed to play it in general?
  if (checkPermission($user,"stream",$el->getPath("String")) === false) {
    return $permissions;
  }

  // Fast case:
  if (isNothing($media_lock_mode) || $media_lock_mode == "off") {
    return $valid;
  }

  $be = new jzBackend();

  if ($media_lock_mode == "track") {
    $arr = $be->getPlaying();
    foreach ($arr as $key=>$more) {
      if ($more['fpath'] == $el->getFileName("host")) {
	return $locked;
      }
    }
    return $valid;
  }

  if ($media_lock_mode == "album") {
    $alb = $el->getAncestor("album");
    if ($alb === false) {
      return $valid;
    }
    $arr = $be->getPlaying();
    foreach ($arr as $key=>$more) {
      $t = new jzMediaTrack($more['path']);
      $ta = $t->getAncestor("album");
      if ($ta !== false) {
	if ($ta->getPath("String") == $alb->getPath("String")) {
	  return $locked;
	}
      }
    }
    return $valid;
  }


  if ($media_lock_mode == "artist") {
    $artist = $el->getAncestor("artist");
    if ($artist === false) {
      return $valid;
    }
    $arr = $be->getPlaying();
    foreach ($arr as $key=>$more) {
      $t = new jzMediaTrack($more['path']);
      $ta = $t->getAncestor("artist");
      if ($ta !== false) {
	if ($ta->getPath("String") == $artist->getPath("String")) {
	  return $locked;
	}
      }
    }
    return $valid;
  }


  if ($media_lock_mode == "genre") {
    $gen = $el->getAncestor("genre");
    if ($gen === false) {
      return $valid;
    }
    $arr = $be->getPlaying();
    foreach ($arr as $key=>$more) {
      $t = new jzMediaTrack($more['path']);
      $ta = $t->getAncestor("genre");
      if ($ta !== false) {
	if ($ta->getPath("String") == $gen->getPath("String")) {
	  return $locked;
	}
      }
    }
    return $valid;
  }

  return $valid;
}

/*
 * Builds a path from meta data
 * to fit the hierarchy.
 * 
 * @author Ben Dodson
 * @since 8/10/05
 * @version 8/10/05
 **/
function buildPath($meta) {
  global $hierarchy;
  
  // strip out weird characters.
  if (isset($meta['genre'])) {
    $genre = str_replace("/","-",$meta['genre']);
  } else {
    $genre = '';
  }

  if (isset($meta['artist'])) {
    $artist = str_replace("/","-",$meta['artist']);
  } else {
    $artist = '';
  }

  if (isset($meta['album'])) {
    $album = str_replace("/","-",$meta['album']);
  } else {
    $album = '';
  }


  if (isset($meta['filename'])) {
  	$filename = str_replace("/","-",$meta['filename']);	
  } else if (isset($meta['track'])) {
  	$filename = str_replace("/","-",$meta['track']);
  }
  
  
  if (isNothing($genre)) {
    $genre = word("Unknown");
  }
  if (isNothing($artist)) {
    $artist = word("Unknown");
  }
  if (isNothing($album)) {
    $album = word("Unknown");
  }
  
  // TODO: 1) in inject, do a case-insensitive comparison.
  //       2) guess id3 fields based on filesystem...
  
  $arr = array();
  $norm = array($genre,$artist,$album,$filename);
  for ($i = 0; $i < sizeof($hierarchy); $i++) {
    switch ($hierarchy[$i]) {
    case "genre":
      $arr[] = $genre;
      break;
    case "artist":
      $arr[] = $artist;
      break;
    case "album":
      $arr[] = $album;
      break;
    case "track":
      $arr[] = $filename;
      break;	
    default:
      $arr[] = $norm[$i];
    }
  }
  
  return $arr;
}

/** 
 * Returns which media path
 * an element is in
 *
 * @author Ben Dodson
 * @since 8/12/05
 * @version 8/12/05
 **/
function getMediaDir($element) {
  global $media_dirs;

  $path = $element->getFilePath();
  $mediapaths = explode("|",$media_dirs);
  for ($i = 0; $i < sizeof($mediapaths); $i++) {
    if (stristr($path,$mediapaths[$i])) {
      return $mediapaths[$i];
    }
  }
  return false;
}

function handleUserInit() {
  global $jzSERVICES,$jzUSER,$jz_language,$node,$skin,$include_path,
    $css,$image_dir,$my_frontend,$fe,$jz_path,$web_path,$USER_SETTINGS_OVERRIDE;
  writeLogData("messages","Index: Testing the language file for security and including");
  
  $USER_SETTINGS_OVERRIDE = array(); // for use by user agents

  checkUserAgent();
  handleSetLanguage();
  handleSetFrontend();
  writeLogData("messages","Index: Testing the theme file for security and including");
  handleSetTheme();
  handleJukeboxVars();

  if (!($node === false)) {
    if (!($dir = $jzUSER->getSetting('home_dir')) === false && $jzUSER->getSetting('home_read') === true) {
      if (strpos(strtolower($jz_path),strtolower($dir)) === false) {
	$jz_path = "";
      }
    }
    $node = new jzMediaNode($jz_path);
    if (isset($_GET['depth']))
      $node->setNaturalDepth($_GET['depth']);
    else
      doNaturalDepth($node);    
  }

  // Let's setup our stylesheet and icons
  // Should this be moved to display.php:preHeader()?
  if (stristr($skin,"/")){
    $css = $include_path . "$skin/default.php";
    $image_dir = $web_path . "$skin/";
  } else {
    $css = $include_path . "style/$skin/default.php";
    $image_dir = $web_path . "style/$skin/";
  }
}

/**
 * Changes Jinzora settings for specific devices.
 * 
 * @author Ben Dodson
 * @since 1/12/07
 * @version 1/12/07
 */
 function checkUserAgent() {
 	global $include_path;
 	// let's do it in a more accessible file
 	include($include_path.'frontend/useragent.php');
 }

/**
 * Sets up the frontend variable.
 * @author Ben Dodson
 * @since 4/29/05
 * @version 4/29/05
 **/
function handleSetFrontend($build_fe = true) {
	global $jzSERVICES,$my_frontend, $jzUSER, $jinzora_skin,$fe,$skin;
	
	// Now use them.
	if (isset($_GET['frontend'])) {
		$my_frontend = $_GET['frontend'];
	} else if (isset($_POST['frontend'])) {
		$my_frontend = $_POST['frontend'];
	} else if (defined("JZ_FRONTEND_OVERRIDE")) {
		$my_frontend = JZ_FRONTEND_OVERRIDE;
	}  else if (isset($_SESSION['frontend'])) {
		$my_frontend = $_SESSION['frontend'];
	} else if (isset($_COOKIE['frontend'])) {
		$my_frontend = $_COOKIE['frontend'];
	} else {
		$my_frontend = $jzUSER->getSetting('frontend');
	}
	if ($my_frontend == "andromeda") {
	  $my_frontend = "slick";
	  $skin = "slick";
	}
	if (!includeable_file($my_frontend, "frontend/frontends")) {
	  die("Invalid frontend ${my_frontend}.");
	}

	if ($build_fe) {
		global $include_path;
	  require($include_path. "frontend/frontends/${my_frontend}/header.php");
	  $fe = new jzFrontend();
	}

}

/**
 * Sets up the theme variable.
 * @author Ben Dodson
 * @since 6/1/05
 * @version 6/1/05
 **/
function handleSetTheme() {
  global $my_frontend, $jzUSER, $skin, $default_frontend_style, $cms_mode,$include_path;

	if (isset($_POST['set_theme'])){
	  $_GET['theme'] = $_POST['set_theme'];
	}
	if (isset($_GET['set_theme'])){
	  $_GET['theme'] = $_GET['set_theme'];
	}

	if (isset($_GET['theme'])) {
		$skin = $_GET['theme'];
	} else if (isset($_POST['theme'])) {
		$skin = $_POST['theme'];
	} else if (isset($default_frontend_style)){
		$skin = $default_frontend_style;
	} else if (defined("JZ_STYLE_OVERRIDE")) {
		$skin = JZ_STYLE_OVERRIDE;
	}  else if (isset($_SESSION['theme'])) {
		$skin = $_SESSION['theme'];
	} else if (isset($_COOKIE['theme'])) {
		$skin = $_COOKIE['theme'];
	} else {
		$skin = $jzUSER->getSetting('theme');
	}
	// Now are the in CMS mode?
	if ($cms_mode == "true"){
		$skin = "cms-theme";
	}
	
	if (!includeable_file($skin,"style")) {
		// is it in the frontend/frontends/*/style directory?
		$string = $include_path . "frontend/frontends/${my_frontend}/style";
		if (false === ($res = stristr($skin,$string)) || $res != 0) {
			die("Invalid style ${skin}.");
		} else {
			if (false !== stristr($skin, "://")){
				die("Invalid style ${skin}.");
			}
		}
	}
}

/**
 * Sets up the language variable.
 * @author Ben Dodson
 * @since 6/1/05
 * @version 6/1/05
 **/
function handleSetLanguage() {
	global $jzUSER, $jz_language, $default_frontend_style, $cms_mode;

	if (isset($_GET['language'])) {
		$jz_language = $_GET['language'];
	} else if (isset($_POST['language'])) {
		$jz_language = $_POST['language'];
	} else if (defined("JZ_LANGUAGE_OVERRIDE")) {
		$jz_language = JZ_LANGUAGE_OVERRIDE;
	}  else if (isset($_SESSION['language'])) {
		$jz_language = $_SESSION['language'];
	} else if (isset($_COOKIE['language'])) {
		$jz_language = $_COOKIE['language'];
	} else {
		$jz_language = $jzUSER->getSetting('language');
	}

	if (!includeable_file($jz_language . "-simple.php","lang") and !includeable_file($jz_language . ".php","lang")) {
	  die("Invalid language ${jz_language}.");
	}
}




/** 
 * Initializes the jukebox variables
 *
 * @author Ben Dodson
 * @since 4/29/05
 * @version 4/29/05
 **/
function handleJukeboxVars() {
  global $jukebox,$jukebox_default_addtype,$default_jukebox,$jzUSER,
    $home_jukebox_subnets,$home_jukebox_id;

  if (isset($_REQUEST['jz_player_type'])) {
    if ($_REQUEST['jz_player_type'] == 'stream') {
      $_SESSION['jb_playwhere'] = 'stream';
    } else if ($_REQUEST['jz_player_type'] == 'jukebox') {
      $_SESSION['jb_id'] = $_REQUEST['jz_player'];
      $_SESSION['jb_playwhere'] = 'jukebox';
    }
  }

  // easier call for api:
  if (isset($_REQUEST['jb_id'])) {
    if ($_REQUEST['jb_id'] == 'stream') {
      $_SESSION['jb_playwhere'] = 'stream';
    } else {
      $_SESSION['jb_playwhere'] = 'jukebox';
      $_SESSION['jb_id'] = $_REQUEST['jb_id'];
    }
  }

  //  if (checkPermission($jzUSER,"jukebox_queue")) {
    if (!isset($_SESSION['jb-addtype']) || isNothing($_SESSION['jb-addtype'])){ // set all the variables.
      if (!isNothing($jukebox_default_addtype)) {
	$_SESSION['jb-addtype'] = $jukebox_default_addtype;
      } else {
	$_SESSION['jb-addtype'] = "current";
      }
    }      
    if (!isset($_SESSION['jb_playwhere']) || isNothing($_SESSION['jb_playwhere'])) {
      if (isset($_GET['action']) && $_GET['action'] == 'playlist') {
	// hack.. stream these.
	$_SESSION['jb_playwhere'] = 'stream';
      }
      else if (preg_match("/^${home_jukebox_subnets}$/", $_SERVER['REMOTE_ADDR'])) {
	$_SESSION['jb_playwhere'] = $home_jukebox_id;
      }
      else if (!isNothing($default_jukebox)) {
	$_SESSION['jb_playwhere'] = $default_jukebox;
      } else {
	$_SESSION['jb_playwhere'] = "stream";
      }
    }

    if ($_SESSION['jb_playwhere'] == "stream" && !checkPermission($jzUSER,'stream')) {
      unset($_SESSION['jb_playwhere']);
      // We don't have $jbArr yet; handle in the block.
    }
    //  }
}

/**
 * Creates an array out of the given settings file.
 *
 * @author Ben Dodson
 * @since 2/2/05
 * @version 2/2/05
 *
 **/
function settingsToArray($filename) {
  $lines = file($filename); // each new line is an entry in the array.
  $arr = array();

  foreach ($lines as $line) {
    if (stristr($line,"=") === false) {
      continue;
    }
    $line = stripSlashes($line);
    $key = ""; 
    $val = "";
    $i = 0;
    while ($line[$i] != "=" && $i < strlen($line)) {
      if (!isBlankChar($line[$i]) && $line[$i] != "$") {
	$key .= $line[$i];
      }
      $i++;
    }
    if ($line[$i] == "=") {
      $i++;
      while (isBlankChar($line[$i])) {
	$i++;
      }
      if ($line[$i] == "\"") {
	$i++;
      }
      while ($i < strlen($line) && $line[$i] != ";") {
	$val .= $line[$i];
	$i++;
      }
      if ($val[strlen($val)-1] == "\"") {
	$val = substr($val,0,-1);
      }
      $arr[$key] = $val;
    }
  }
  return $arr;
}

/**
 * Creates a settings file out of an array.
 *
 * @author Ben Dodson
 * @since 2/2/05
 * @version 2/2/05
 *
 **/
function arrayToSettings($array,$filename) {
  $file = "<?php\n";
  foreach ($array as $key => $val) {
    $file .= '    $' . str_replace(" ","",str_replace("\t","",$key)) . ' = "' . addSlashes($val) . "\";\n";
  }
  $file .= "?";
  $file .= ">";
  if (($handle = fopen($filename, "w")) === false) {
    echo "Could not write to $filename.";
    die();
  }
  fwrite($handle,$file);	
  fclose ($handle);
}

 function isBlankChar($char) {
   if ($char == ' ' || $char == '\t') {
     return true;
   } else {
   return false;
   }
 }

/**
 * Checks a user permission
 * for permissions that require multiple
 * settings checks, IE admin, upload, and play.
 *
 * @author Ben Dodson
 * @since 3/1/05
 * @version 3/1/05
 **/
function checkPermission($user, $setting, $path = false) {
  global $embedded_player,$jukebox,$allow_download;

  switch ($setting) {
  case "admin":
    if ($user->getSetting('admin') === true ||
	($user->getSetting('home_admin') === true && stristr($path,$user->getSetting('home_dir')) !== false)) {
      return true;
    }
    else {
      return false;
    }
    break;
  case "upload":
    if ($user->getSetting('upload') === true ||
	($user->getSetting('home_upload') === true && stristr($path,$user->getSetting('home_dir')) !== false)) {
      return true;
    } else {
      return checkPermission($user,"admin",$path);
    }
    break;
  case "play":
    if ($user->getSetting('jukebox_queue') === true ||
	$user->getSetting('jukebox_admin') === true) 
      return true;
    // NO BREAK
  case "stream":
    if ($user->getSetting('stream') === true ||
	($user->getSetting('home_read') === true && stristr($path,$user->getSetting('home_dir')) !== false))
      return true;
    else return false;
    break;
  case "jukebox":
  case "jukebox_queue":
    if ($jukebox == "true" && 
	($user->getSetting('jukebox_queue') === true ||
	 $user->getSetting('jukebox_admin') === true))
      return true;
    else return false;
    break;
  case "embedded_player":
    if (defined ('JZ_FORCE_EMBEDDED_PLAYER'))
      return true;
    if ($user->getSetting('player') != "" || ($embedded_player != "" && $embedded_player != "false"))
      return true;
    else return false;
    break;
  case "view":
    if ($user->getSetting('view') === true ||
	($user->getSetting('home_read') === true))
	return true;
    else return false;
    break;
  case "download":
    if ($user->getSetting('download') === true) // no more allow_download var.
      return true;
    else return false;
    break;
  default:
    return $user->getSetting($setting);
  }
}

/**
 * Checks the current playback type.
 * Returns one of: stream|embedded|jukebox
 *
 * @author Ben Dodson
 * @since 5/26/05
 * @version 5/26/05
 * @param check_streammode True if you just want to choose between 'stream' and 'embedded'.
 **/
function checkPlayback($check_streammode = false) {
  global $embedded_player,$jukebox,$jzUSER;
  
  if (!$check_streammode &&
      $jukebox == "true" &&
      checkPermission($jzUSER,'jukebox') === true &&
      $_SESSION['jb_playwhere'] != "stream") {
    return "jukebox";
  }

  if (defined('JZ_FORCE_EMBEDDED_PLAYER')) {
  	return "embedded";
  }

  if (checkPermission($jzUSER,"embedded_player") || (!isNothing($embedded_player) && $embedded_player != "false")) {
    if (!isset($_REQUEST['target']) || $_REQUEST['target'] != 'raw') {
      return "embedded";
    }
  }

  return "stream";
}


/**
 * Returns the default of a setting.
 * 
 * @author Ben Dodson
 * @version 11/22/04
 * @since 11/22/04
 */
function user_default($setting) {
  global $frontend,$jz_lang_file,$jinzora_skin,$playlist_ext,$default_resample,$jzUSER;

  switch ($setting) {
  case "frontend":
    return $frontend;
    break;
  case "theme":
    return $jinzora_skin;
    break;
  case "localpath":
    return false;
    break;
  case "ratingweight":
    return 1;
    break;
  case "language":
    return $jz_lang_file;
    break;
  case "playlist_type":
    return $playlist_ext;
    break;
  case "home_dir":
    return false;
    break;
  case "edit_prefs":
    return ($jzUSER->lookupUID('NOBODY') != $jzUSER->getID());
    break;
  case "powersearch":
  case "view":
  case "download":
  case "stream":
    return true;
    break;
  case "resample_rate":
    return $default_resample;
    break;
  default: 
    return false;
    
  }
}

/** 
 * Handles all functions that should
 * be executed immediately before viewing a page
 *
 * @author Ben Dodson
 * @version 3/18/05
 * @since 3/18/05
 **/
function handlePageView($node) {

  $node->increaseViewCount();
  doUserBrowsing($node);
}


/** Encrypts a password
 *
 * @author Ben Dodson
 *
 */

function jz_password($pw) {
  return md5($pw);
}

/**
 * Pulls the keywords from a search string
 * and creates an array of them
 *
 * Returns an associative array:
 * $ret['search'] is the search string without keywords
 * $ret['keywords'] is an array of keywords
 * with values if needed.
 *
 * @author Ben Dodson
 * @version 1/17/05
 * @since 1/17/05
 *
 */
function splitKeywords($string) {
  global $jzUSER, $keyword_genre, $keyword_artist, $keyword_album, $keyword_track,
    $keyword_play, $keyword_random, $keyword_radio, $keyword_lyrics, $keyword_limit,
    $keyword_id;

  $limit_default = 50;
  $ret = array();
  $keywords = array();

  if (isset($keyword_id) && (false !== stristr($string, "$keyword_id"))) {
    $keywords['id'] = true;
    $string = str_replace("  "," ",str_replace("$keyword_id","",$string));
  }

  if (isset($keyword_genre) && (false !== stristr($string, "$keyword_genre"))) {
    $keywords['genres'] = true;
    $string = str_replace("  "," ",str_replace("$keyword_genre","",$string));
  }

  if (isset($keyword_artist) && (false !== stristr($string, "$keyword_artist"))) {
    $keywords['artists'] = true;
    $string = str_replace("  "," ",str_replace("$keyword_artist","",$string));
  }

  if (isset($keyword_album) && (false !== stristr($string, "$keyword_album"))) {
    $keywords['albums'] = true;
    $string = str_replace("  "," ",str_replace("$keyword_album","",$string));
  }

  if (isset($keyword_track) && (false !== stristr($string, "$keyword_track"))) {
    $keywords['tracks'] = true;
    $string = str_replace("  "," ",str_replace("$keyword_track","",$string));
  }

  if (isset($keyword_lyrics) && (false !== stristr($string, "$keyword_lyrics"))) {
    $keywords['lyrics'] = true;
    $string = str_replace("  "," ",str_replace("$keyword_lyrics","",$string));
  }


  if (isset($keyword_play) && (false !== stristr($string, "$keyword_play") && checkPermission($jzUSER,'play') === true)) {
    $keywords['play'] = true;
    $string = str_replace("  "," ",str_replace("$keyword_play","",$string));
  }

  if (isset($keyword_radio) && (false !== stristr($string, "$keyword_radio") && checkPermission($jzUSER,'play') === true)) {
    $keywords['radio'] = true;
    $keywords['limit'] = $limit_default;
    $string = str_replace("  "," ",str_replace("$keyword_radio","",$string));
  }

  if (isset($keyword_limit) && (false !== stristr($string, "$keyword_random") && checkPermission($jzUSER,'play') === true)) {
    $keywords['random'] = true;
    $keywords['play']  = true;
    $keywords['limit'] = $limit_default;
    $string = str_replace("  "," ",str_replace("$keyword_random","",$string));
  }


  if (isset($keyword_limit) && (false !== stristr($string, "$keyword_limit"))) {
    $explode = explode(" ",$string);
    $str_array = array();
    for ($i = 0; $i < sizeof($explode)-1; $i++) {
      if (false !== stristr($explode[$i],"$keyword_limit")) {
	if (is_numeric($explode[$i+1])) {
	  $keywords['limit'] = $explode[$i+1];
	  $i++;
	}
	else {
	  $keywords['limit'] = $limit_default;
	}
      } else {
	$str_array[] = $explode[$i];
      }
    }
      $string = implode(" ",$str_array);
  }

  while ($string[0] == " ") {
    $string = substr($string,1);
  }
  while ($string[strlen($string)] == " ") {
    $string = substr($string,0,-1);
  }
  $ret['keywords'] = $keywords;
  $ret['search'] = $string;
  return $ret;
}

/**
 * Determines whether or not the selected keywords
 * should have the output muted.
 *
 * @author Ben Dodson
 * @version 1/18/05
 * @since 1/18/05
 */
function muteOutput($keywords) {
  if (isset($keywords['play'])) {
    return true;
  }
  if (isset($keywords['radio'])) {
    return true;
  }

  return false;
}

/**
 * Compares jzMediaElements by filepath for sorting.
 * 
 * @author Ben Dodson
 * @version 6/9/04
 * @since 6/9/04
 */
function compareFilename($a, $b) {
   global $compare_ignores_the;

$an = $a->getFilePath();
$bn = $b->getFilePath();

 return strnatcasecmp($an,$bn);

}


/**
 * Compares jzMediaElements for sorting.
 * 
 * @author Ben Dodson
 * @version 6/9/04
 * @since 6/9/04
 */
function compareNodes($a, $b) {
   global $compare_ignores_the;

$an = $a->getName();
$bn = $b->getName();
  if ($compare_ignores_the != "false" && strtolower(substr($an,0,4)) == "the ") {
    $an = substr($an,4);
  }

  if ($compare_ignores_the != "false" && strtolower(substr($bn,0,4)) == "the ") {
    $bn = substr($bn,4);
  }
  return strnatcasecmp($an,$bn);
}

/**
 * Compares elements by year
 *
 * @author Ben Dodson
 * @version 2/20/05
 * @since 2/20/05
 *
 **/
function compareYear($a, $b) {
  $ay = $a->getYear();
  $by = $b->getYear();
  
  if ($ay == "-") {
    $ay = false;
  }

  if ($by == "-") {
    $by = false;
  }

  if ($ay === false && $by !== false) {
    return 1;
  }

  if ($by === false && $ay !== false) {
    return -1;
  }

  if ($ay == $by) {
    return compareNodes($a,$b);
  }
  
  return ($ay < $by) ? 1 : -1;
}

/**
 * Compares elements by track number
 *
 * @author Ben Dodson
 * @version 5/27/05
 * @since 5/27/05
 **/
function compareNumber($a,$b) {
  if ($a->isLeaf() === false && $b->isLeaf() === false) {
    return compareNodes($a,$b);
  }

	$ad = $a->getAncestor("album");
	$bd = $b->getAncestor("album");
	$adArtist = $a->getAncestor("artist");
	$bdArtist = $b->getAncestor("artist");

	if(   
		( $ad !== false && $bd !== false && strtoupper($ad->getName()) != strtoupper($bd->getName()))
		 ||
	    ( $adArtist !== false && $bdArtist !== false && strtoupper($adArtist->getName()) != strtoupper($bdArtist->getName())) 
	   ) {
    if ($ad === false || $bd === false) {
      return compareNodes($a,$b);
    } 
    return compareNodes($ad,$bd);
  } 

  if (($ad = $a->getAncestor("disk")) != ($bd = $b->getAncestor("disk"))) {
    if ($ad !== false && $bd !== false) { // both from disks.
      return compareNodes($ad,$bd);
    }

    if ($ad === false) { // $a NOT from a disk, but b is.
      return 1;
    }

    return -1;
  } 

  if ($a->isLeaf() === false) {
    return 1;
  }

  if ($b->isLeaf() === false) {
    return -1;
  }

  $am = $a->getMeta();
  $bm = $b->getMeta();

  if ((isNothing($am['number']) && isNothing($bm['number']))
      || ($am['number'] == $bm['number'])) {
    return compareNodes($a,$b);
  }

  if (isNothing($am['number'])) {
    return 1;
  }

  if (isNothing($bm['number'])) {
    return -1;
  }

  return ($am['number'] < $bm['number']) ? -1 : 1;
}

/**
 * Sorts a list of elements by a given parameter.
 *
 * @author Ben Dodson
 * @version 2/20/05
 * @since 2/20/05
 *
 **/
function sortElements(&$list, $param = "name") {
  switch ($param) {
  case "year":
    $func = "compareYear";
    break;
  case "name":
    $func = "compareNodes";
    break;
  case "number":
    $func = "compareNumber";
    break;
  case "filename":
    $func = "compareFilename";
    break;
  }

  usort($list,$func);
}




	/**
	 * Displays the status information on screen
	 * during an update.
	 * 
	 * @author Ben Dodson
	 * @version 11/13/04
	 * @since 11/13/04
	 */
	function showStatus($path = false) {
		global $word_importing;
		
		// Let's set our display items
		$media_display = str_replace("'","",$path);
		// Now let's truncate the media_display
		if (strlen($media_display) > 60){
			$media_display = substr($media_display,0,60). "...";
		}
		
		switch($_SESSION['jz_import_progress']){
			case "30":
				$val = ".&nbsp;";
				$_SESSION['jz_import_progress'] = 0;
			break;
			default:
				$i=0;$val="";
				while ($i < $_SESSION['jz_import_progress']){
					$val .= ".&nbsp;";
					$i++;
				}
			break;
		}	  
		$_SESSION['jz_import_progress']++;
		if ($media_display <> ""){
			?>
			<script language="javascript">
				d.innerHTML = '<nobr><b><?php echo word("Directory") ?>:</b> <?php echo $media_display; ?><nobr>';
			-->
			</SCRIPT>
			<?php
		}
		
		$status_str = word("Processing files"). ": ". $_SESSION['jz_import_full_progress'];
		// Now let's figure out what's left
		if ($_SESSION['jz_import_full_ammount'] <> 0){
			$left = round((($_SESSION['jz_import_full_progress'] / $_SESSION['jz_import_full_ammount']) * 100));
			if ($left > 0){$left = $left -1; }
			// Ok, now let's figure out the time
			// First how much time has elapsed
			$elapsed = time() - $_SESSION['jz_import_start_time'];
			if ($elapsed == 0){$elapsed = 1;}
			// Ok, now how many files did we do in that time?
			$perTime = round($_SESSION['jz_import_full_progress'] / $elapsed);
			if ($perTime == 0){$perTime = 1;}
			// Now how much is left
			$ammountLeft = $_SESSION['jz_import_full_ammount'] - $_SESSION['jz_import_full_progress'];
			// Now time left?
			$timeLeft = convertSecMins(round($ammountLeft / $perTime));
			$status_str .= " (". $left. "% - ". $timeLeft. ") &nbsp; ". $val;
		}
		?>
		<script language="javascript">
			p.innerHTML = '<b><?php echo $status_str; ?></b>';
		-->
		</SCRIPT>
		<?php
		flushdisplay();
	}

/**
 * Turns a string with potentially weird characters into a valid path.
 * 
 * @author Ben Dodson
 * @version 6/9/04
 * @since 6/9/04
 */
function pathize($str, $char = '_') {
  $str = preg_replace("/[^a-z|A-Z|0-9| |,|'|\"|(|)|.|-|_|+|=]/",$char,$str);

  if ($str == "" || $str == "-") {
    $str = word("Unknown");
  }
  return $str;
}

/**
 * Returns a useable URL.
 * Type is one of: image|track
 * 'arr' lets you add extra variables to our URL.
 * 
 * @author Ben Dodson
 * @version 6/9/04
 * @since 6/9/04
 * @param string $text the data to parse
 * return returns a string with the URL's fixed
 */
function fixAMGUrls($text){
  $text = str_replace('href="/cg/amg.dll?','target="_blank" href="http://www.allmusic.com/cg/amg.dll?',$text);
	$text = str_replace("\n", "<p>", $text );
	return $text;
}

/**
 * Returns a useable URL.
 * Type is one of: image|track
 * 'arr' lets you add extra variables to our URL.
 * 
 * @author Ben Dodson
 * @version 6/9/04
 * @since 6/9/04
 */
function jzCreateLink($path, $type, $arr = array()) {
  global $media_dirs,$web_dirs,$include_path,$this_site, $root_dir,$jzUSER;
  
  if ($type == "image" && !($path[0] == '/' || stristr($path,":\\") || stristr($path,":/"))) {
    // the link is relative; return it.
    return $this_site. $root_dir. "/". str_replace("%2F","/",rawurlencode($path));
  }

  $media_paths = explode("|",$media_dirs);
  $web_paths = @explode("|",$web_dirs);

  if (strlen($web_dirs) > 0) {
    for ($i = 0; $i < sizeof($media_paths); $i++) {
      if (($pos = stristr($path,$media_paths[$i])) !== false) {
				if ($pos == 0 && $web_paths[$i] != "") {
	  			$path = str_replace($media_paths[$i],$web_paths[$i],$path);
				if (substr($path,0,4) == "http") {
				  return $path;
				}
					if ($type == "image" && $_SERVER['SERVER_PORT'] == 443) {
						$link = "https://";
					} else {
						$link = "http://";
					}
	  			$link .=  $_SERVER['HTTP_HOST'];
					if ($_SERVER['SERVER_PORT'] != 80 && $_SERVER['SERVER_PORT'] != 443 && (strpos($link,":") === false)) {
						$link .= ":" . $_SERVER['SERVER_PORT'];
					}
					$link = str_replace(":443","",$link);

					$ar = explode("/",$path);
					for ($i = 0; $i < sizeof($ar); $i++) {
						$ar[$i] = rawurlencode($ar[$i]);
					}
					$path = implode("/",$ar);
					$link .= '/' . $path;
					return $link;
				}
      }
    }
  }

  switch ($type) {
  case "image":
    $arr['action'] = "image";
    $arr['jz_path'] = $path;
    $arr['jz_user'] = $jzUSER->getId();
    return urlize($arr);
    break;
  case "track":
		if ($web_dirs <> ""){
			return $path;
		} else {
			$ssid = session_id();
			writeLogData("as_debug","jzCreateLink: ssid = ". $ssid);
			
			$arr['jz_path'] = $path;
			$arr['action'] = "play";
			$arr['type'] = "track";
			$arr['jza'] = $ssid;

			return urlize($arr);
		}
    break;
  }
}			

/**
 * Returns a blank cache;
 *
 *  NODE:
 * [0]  path
 * [1]  art
 * [2]  updated
 * [3]  playcount
 * [4]  rating
 * [5]  ratingcount
 * [6]  dateadded
 * [7]  nodes array
 * [8]  tracks array
 * [9]  short_desc
 * [10] desc
 * [11] year
 * [12] dlcount
 * [13] filepath
 * [14] links
 * [15] ptype
 * [16-21] unused
 * [22] hidden
 * [23] ID
 * [24] direct play count
 * [25] view count
 *   TRACK:
 *      0      1      2       3         4         5          6        7        8           9       10    11      12     13    14      15      16      17      18       19       20        21        22  23 24     25     26
 * [filepath][art][fname][playcount][rating][ratingcount][dateadded][name][frequency][short_desc][desc][year][dlcount][size][length][genre][artist][album][extension][lyrics][bitrate][tracknum][hidden][ID][dp_count][view_count][sheet_music]
 *
 * @author Ben Dodson
 * @version 6/9/04
 * @since 6/9/04
 */
function blankCache($type = "node") {
  $cache = array();
  
  if ($type == "node") {
    for ($i = 0; $i < 23; $i++) {
      $cache[] = "-";
    }
    $cache[3] = $cache[4] = $cache[5] = $cache[12] = $cache[24] = $cache[25] = 0;
    $cache[7] = array();
    $cache[8] = array();
    $cache[14] = array();
    $cache[22] = 'false';
    $cache[23] = uniqid("T");

    return $cache;
  }
  else if ($type == "track") {
    for ($i = 0; $i < 23; $i++) {
      $cache[] = "-";
    }
    $cache[3] = $cache[4] = $cache[5] = $cache[12] = $cache[24] = $cache[25] = 0;
    $cache[22] = 'false';
    $cache[23] = uniqid("T");
    $cache[26] = 0;
    return $cache;		
  }
  else {
    return $cache;
  }
}

/**
 * Gets the desired information (genre,artist,album) from a jzMediaElement.
 * This does not use meta data even for tracks-- only the $hierarchy.
 * 
 * @author Ben Dodson
 * @version 6/10/04
 * @since 6/10/04
 */
function getInformation(&$element,$type = "album") {
  global $hierarchy;
  
  $path = $element->getPath();
  if (is_string($hierarchy))
    $hierarchy = explode("/",$hierarchy);
  
  for ($i = 0; $i < sizeof($hierarchy); $i++) {
    if ($hierarchy[$i] == $type) {
      return ($i < sizeof($path)) ? str_replace("_"," ",$path[$i]) : false;
    }
  }
  return false;
}

/**
 * Figures the ptype of a node from the hierarchy.
 * 
 * @author Ben Dodson
 * @version 11/3/04
 * @since 11/3/04
 */
function findPType(&$node) {
  global $hierarchy;
  
  // Obvious cases:
  if ($node->isLeaf()) {
    return "track";
  }
  
  if ($node->getLevel() == 0) {
    return "root";
  }
  
  // Otherwise, we need to use the $hierarchy.
  if (is_string($hierarchy))
    $hier = explode("/",$hierarchy);
  else
    $hier = $hierarchy;
  
  
  
  if ($node->getLevel() > sizeof($hier)) {
    return "generic";
  }
  
  $guess = $hier[$node->getLevel()-1];
  if ($guess == "track")
    return "disk"; // isn't really a track;
  // must be a disk.
  else if ($guess == "loose") {
    return "generic";
  }
  else {
    return $guess;
  }
}

/**
 * Sets the 'natural depth' for this node based on the hierarchy.
 * 
 * @author Ben Dodson
 * @version 11/3/04
 * @since 11/3/04
 */
function doNaturalDepth(&$node) {
  global $hierarchy;
  
  if (is_string($hierarchy))
    $hierarchy = explode("/",$hierarchy);
  
  $level = $oldlevel = $node->getLevel();
  while ($level < sizeof($hierarchy) && $hierarchy[$level] == "hidden") {
    $level++;
  }
  $node->setNaturalDepth(1 - $oldlevel + $level);
}

/**
 * Finds the distance from the node to the level type (genre,artist,album,track)
 * If it is only called with 1 param, it returns the distance from the root to that type.
 * 
 * @author Ben Dodson
 * @version 6/10/04
 * @since 6/10/04
 */
function distanceTo($type = "album", $node = false) {
  global $hierarchy;
  
  if (is_string($hierarchy))
    $hierarchy = explode("/",$hierarchy);
  
  if ($node === false) {
    $node = &new jzMediaNode();
  }
  
  if ($type == "any" || $type == "track")
    return -1;
  
  $level = $node->getLevel();
  for ($i = 0; $i < sizeof($hierarchy); $i++) {
    if ($hierarchy[$i] == $type) {
      if ($i - $level >= 0) {
	return ($i - $level + 1);
      }
    }
  }
  return false;
}

/**
 * Essentially the inverse of distanceTo.
 * 
 * @author Ben Dodson
 * @version 5/1/05
 * @since 5/1/05
 */
function toLevel($distance, $node = false) {
  global $hierarchy;
  
  if (is_string($hierarchy))
    $hierarchy = explode("/",$hierarchy);
  
  if ($node === false) {
    $node = &new jzMediaNode();
  }
  
  if ($distance < 0) {
    return false;
  }
  
  $level = $node->getLevel();
  $distance = $distance + $level;

  if ($distance == 0) {
    return "root";
  }

  if (0 <= $distance-1 && $distance-1 < sizeof($hierarchy)) {
    return $hierarchy[$distance-1];
  } else {
    return false;
  }
}

/**
 * validates a key in our hierarchy.
 * 
 * 
 * @author Ben Dodson
 * @version 11/11/04
 * @since 11/11/04
 */
function validateLevel(&$lvl) {
  $lvl = strtolower($lvl);
  switch ($lvl) {
  case "genre":
  case "artist":
  case "album":
  case "track":
  case "hidden":
  case "generic":
  case "root":
  case "disk":
  case "user":
  case "subgenre":
    return true;
    break;	
  case "genres":
  case "subgenres":
  case "artists":
  case "albums":
  case "tracks":
  case "disks":
    $lvl = substr($lvl,0,-1);
    return true;
  }
  return false;
}
	

/**
 * Translates a path to a URL-valid one.
 * 
 * 
 * @author PHP online resource
 * @version 
 * @since
 */
function translate_uri($uri) {
  $parts = explode('/', $uri);
  for ($i = 0; $i < count($parts); $i++) {
    $parts[$i] = rawurlencode($parts[$i]);
  }
  return implode('/', $parts);
}



/**
 * Converts seconds to a string
 * 
 * 
 * @author Ben Dodson
 * @version 11/17/04
 * @since 11/17/04
 */
function stringize_time($sec) {
  $str = "";
  
  if ($sec > 60*60*24) {
    // days
    $days = intval($sec / (60*60*24));
    $sec -= $days*(60*60*24);
    $str .= $days . " days ";
    if ($sec > 60*60) { // hours
      $hours = intval($sec / (60*60));
      $sec -= $hours*(60*60);
      $str .= $hours . " hours ";
    }
    if ($sec > 60) {
      $mins = intval($sec / 60);
      $sec -= ($mins*60);
      $str .= $mins . " minutes ";
    }
    $str .= $sec . " seconds";
  } else {
    // $len -> string
    if ($sec > 60*60) {
      $h = intval($sec / (60*60));
      $sec -= $h*60*60;
      $str .= $h . ":";
    }
    if ($sec > 60) {
      $m = intval($sec / 60);
      $sec -= $m*60;
      if ($str != "" && $m < 10) {
	$str .= "0";	
      }
      $str .= $m . ":";
    } else {
      $str .= "0:";
    }
    if ($sec < 10) {
      $str .= "0";
    }
    $str .= $sec;
  }	
  return $str;
}

/**
 * Stringizes size given in megs.
 * 
 * 
 * @author Ben Dodson
 * @version 11/17/04
 * @since 11/17/04
 */
function stringize_size($size) {
  if ($size > 734000) { // 70%+ of a TB
    return round($size / 1024*1024,2) . " TB";
  }
  if ($size > 715) { // 70%+ of a GB
    return round($size / 1024,2) . " GB";
  }
  return $size . " MB";
}
/** Updates a node's cache nonrecursively.
 *
 * @author Ben Dodson
 */
function updateNodeCache($node, $recursive = false, $showStatus = false, $force = false, $readTags = true, $root_path = false) {
  global $media_dirs,$live_update,$jzSERVICES,$hierarchy,$backend,$default_importer,$jukebox,$include_path;

	$flags = array();
	$flags['showstatus'] = $showStatus;
	$flags['force'] = $force;
	$flags['readtags'] = $readTags;
	$flags['recursive'] = $recursive;

	$importer = $default_importer;
	
	// TODO: more dynamic choice of importer.
	if (false !== stristr($importer,"id3tags")) {
		// id3tag importer doesn't care about your hierarchy.
		// TODO: seperate hierarchy for display / import.	
	} else {
		// TODO: Remove this stuff once we have a propper way
		// of getting the path from the node. Make
		// the function recursive with respect to the node.
		$mypath = array();
		
		if (false !== ($val = getInformation($node,"genre"))) {
			$mypath['genre'] = $val;
		}
		if (false !== ($val = getInformation($node,"subgenre"))) {
			$mypath['subgenre'] = $val;
		}
		if (false !== ($val = getInformation($node,"artist"))) {
			$mypath['artist'] = $val;
		}
		if (false !== ($val = getInformation($node,"album"))) {
			$mypath['album'] = $val;
		}
		if (false !== ($val = getInformation($node,"disk"))) {
			$mypath['disk'] = $val;
		}
		
		$flags['path'] = $mypath;
		$flags['hierarchy'] = array_slice($hierarchy,sizeof($mypath),sizeof($hierarchy)-sizeof($mypath));		
	}

	$jzSERVICES->loadService("importing",$importer);
	// TODO: Move flags array into parameters of this function.
	
	
	/*if ($flags['recursive']) {
		@ini_set("max_execution_time","0");
		@ini_set("memory_limit","64");
	}*/

  if ($node->getLevel() == 0 && $root_path === false) {
    $mediapaths = explode("|",$media_dirs);
    for ($i = 0; $i < sizeof($mediapaths); $i++) {
      if (is_dir($mediapaths[$i]) && $mediapaths[$i] != "/" && $mediapaths[$i] != "") {
				//$node->updateCache($recursive,$mediapaths[$i], $showStatus,$force,$readTags);
				$jzSERVICES->importMedia($node, $mediapaths[$i], $flags);
      }
    }
  } else {
    //$node->updateCache($recursive,$root_path,$showStatus,$force, $readTags);
    $jzSERVICES->importMedia($node, $root_path, $flags);
    if ($recursive === false && $node->getSubNodeCount('tracks',-1) == 0) {
      //$node->updateCache(true,$root_path,$showStatus,$force,$readTags);
      $flags['recursive'] = true;
      $jzSERVICES->importMedia($node, $root_path, $flags);
    }
  }

  if ($showStatus == true) {
    showStatus();
  }
  
  if ($jukebox == "true") {
  	include_once($include_path. "jukebox/class.php");
  	$jb = new jzJukebox();
	$jb->updateDB($node, $recursive, $root_path);
  }
}

	/*
	 * Sets the user's browsing history, this is then used to track the user and show previous items
	 *
	 * @author Ben Dodson, Ross Carlson
	 * @since 2/2/05
	 * @version 2/2/05
	 *
	 **/
	function doUserBrowsing($node) {
	  global $jzUSER;
	  
	  $jzBackend = new jzBackend();

	  $oldHist = $jzUSER->loadData('history');
	  $jzUSER->storeData('history',$node->getPType(). "|". $node->getName(). "|". $node->getPath("String"). "|". time(). "\n". substr($oldHist,0,5000));
	  
	  $oldHist = $jzBackend->loadData('history');
	  // Now let's find the history for this user
	  $dArr = explode("\n",$oldHist);
	  for ($i=0; $i < count($dArr); $i++){
	  	$vArr = explode("|",$dArr[$i]);
			if ($vArr[6] == $_SESSION['sid']){
				unset($dArr[$i]);
			}
	  }
	  $oldHist = implode("\n",$dArr);
	  $jzBackend->storeData('history',$node->getPType(). "|". $node->getName(). "|". $node->getPath("String"). "|". time(). "|". $jzUSER->getName(). "|". $jzUSER->getSetting('fullname'). "|". $_SESSION['sid']. "|". $_SERVER['REMOTE_ADDR']. "\n". substr($oldHist,0,50000));
	}



/**
 * Sees if the setting is a cookie-stored setting.
 *
 * @author Ben Dodson
 * @version 1/21/05
 * @since 1/21/05
 */
function isCookieSetting($setting) {
  switch ($setting) {
  case "localpath":
    return true;
  default:
    return false;
  }
}


/**
 * Handle a search query from the GET/POST variables.
 * @author Ben Dodson
 */
function handleSearch($search_string = false, $search_type = false) {
  global $jzUSER;

  $root = &new jzMediaNode();
  $timer = microtime_float();

  if ($search_string === false) {
    $search_string = $_GET['search_query'];
  }
  if ($search_type === false) {
    $search_type = $_GET['search_type'];
  }


  $string_array = splitKeywords($search_string);
  $keywords = $string_array['keywords'];
  $string = $string_array['search'];

  if (isset($keywords['genres'])) {
    $locations[sizeof($locations)] = 'genre';
    $search_type = "genres";
  }

  if (isset($keywords['artists'])) {
    $locations[sizeof($locations)] = 'artist';
    $search_type = "artists";
  }

  if (isset($keywords['albums'])) {
    $locations[sizeof($locations)] = 'album';
    $search_type = "albums";
  }

  if (isset($keywords['tracks'])) {
    $locations[sizeof($locations)] = 'track';
    $search_type = "tracks";
  }

  if (isset($keywords['lyrics'])) {
    $search_type = "lyrics";
  }

  if (isset($keywords['radio'])) {
    $search_type = "artists";
    $max_res = 1;
  } else if (isset($keywords['limit'])) {
    $max_res = $keywords['limit'];
  } else {
    $max_res = 100;
  }

  switch (strtolower($search_type)) {
  case "all":
    $stype = "both";
    $distance = -1;
    break;
  case "genres":
  case "genre":
    $stype = "nodes";
    $distance = distanceTo("genre");
    break;
  case "artists":
  case "artist":
    $stype = "nodes";
    $distance = distanceTo("artist");
    break;
  case "albums":
  case "album":
    $stype = "nodes";
    $distance = distanceTo("album");
    break;
  case "tracks":
  case "track":
    $stype = "tracks";
    $distance = -1;
    break;
  case "lyrics":
  case "lyric":
    $stype = "lyrics";
    $distance = -1;
    break;
  case "best":
  default:
    $stype = "both";
    $distance = -1;
    $keywords['best'] = true;
  }
  if ($distance === false) {
    die("Could not search for ${search_type}.");
  }

  // Are they searching by ID explicitly?
  if (isset($keywords['id'])) {
    $stype = "id";
    // We handle this differently than above in
    // case they set @genre and @id (or whatever).
  }
  
  /* if we have 2 locations,
     the closest to the root is our anchor
     and the further is our return type.
  */

  if (sizeof($locations) > 1) {
    if ($locations[1] == 'track') {
      $r = 'tracks';
    } else {
      $r = 'nodes';
    }
    $limit = 1;
    if (isset($keywords['limit'])) {
      $limit = $keywords['limit'];
    }

    $results = $root->search($string,"nodes",distanceTo($locations[0]),1,'exact');
    if (sizeof($results) > 0) {
      $results = $results[0]->getSubNodes($r,distanceTo($locations[1],$results[0]),true,$limit);
    } else {
      $results = $root->search($string,"nodes",distanceTo($locations[0]),1);
      if (sizeof($results) > 0) {
	$results = $results[0]->getSubNodes($r,distanceTo($locations[1],$results[0]),true,$limit);
      }
    }
  }

  // Exact matches if using keywords:
  else if (isset($keywords['play']) || isset($keywords['radio'])) {
    $results = $root->search($string,$stype,$distance,1,'exact');
    if (sizeof($results) == 0) {
      $results = $root->search($string,$stype,$distance,1); // better to limit 1 or $max_res?
    }
  }
  else if (isset($keywords['best'])) {
    $results = $root->search($string,$stype,$distance,-1,'exact');
    if (sizeof($results) == 0) {
      $results = $root->search($string,$stype,$distance,$max_res); // better to limit 1 or $max_res?
    }
  }
  else {
    $results = $root->search($string, $stype, $distance, $max_res);
  }
  if (sizeof($results) == 0) {
    // Maybe a search by ID will work...
    $results = $root->search($string, "id", $distance, $max_res);
    if (sizeof($results) == 0) {
      return $results;
    }
  }
  $timer = round(microtime_float() - $timer, 2);
  writeLogData('search',"searched '${search_type}' for '${string}' in $timer seconds.");


  // What about keywords?
  if (isset($keywords['play']) && sizeof($results) > 0) {
    $pl = new jzPlaylist;
    $pl->add($results);
        //    if (isset($keywords['limit'])) {
    //  $pl->flatten();
    //}

    if (isset($keywords['random'])) {
      $pl = $pl->getSmartPlaylist($max_res);
    } else if (isset($keywords['limit'])) {
      $pl->flatten();
      $pl->truncate($max_res);
    }
    $pl->play();
    exit();
  } else if (isset($keywords['radio'])) {
    $pl = new jzPlaylist;
    $pl->add($results);
    $pl = $pl->getSmartPlaylist(50, "radio");
    $pl->play();
    exit();
  }


  return $results;
}

/**
 * Checks to see if tracks or nodes are
 * returned from a powersearch.
 *
 * @author Ben Dodson
 * @since 4/6/05
 * @version 4/6/05
 *
 **/
function powerSearchType() {
  if (isset($_POST['song_title']) && $_POST['song_title'] != "")
    return "tracks";

  if (isset($_POST['length']) && $_POST['length'] != "")
    return "tracks";

  if (isset($_POST['number']) && $_POST['number'] != "")
    return "tracks";
  
  if (isset($_POST['year']) && $_POST['year'] != "")
    return "tracks";
  
  if (isset($_POST['bitrate']) && $_POST['bitrate'] != "")
    return "tracks";
  
  if (isset($_POST['frequency']) && $_POST['frequency'] != "")
    return "tracks";
  
  if (isset($_POST['size']) && $_POST['size'] != "")
    return "tracks";
  
  if (isset($_POST['type']) && $_POST['type'] != "")
    return "tracks";
  
  if (isset($_POST['comment']) && $_POST['comment'] != "")
    return "tracks";
  
  if (isset($_POST['lyrics']) && $_POST['lyrics'] != "")
    return "tracks";
  
  return "nodes";
}

/**
 * Prepares the meta search array
 * from a powersearch.
 *
 * @author Ben Dodson
 * @since 4/6/05
 * @version 4/6/05
 **/
function getSearchMeta() {
  $array = array();

  if (isset($_POST['length']) && $_POST['length'] != "") {
    $array['length_operator'] = $_POST['length_operator'];
    if ($_POST['length_type'] == "minutes") {
      $array['length'] = 60*$_POST['length'];
    } else {
      $array['length'] = $_POST['length'];
    }
  }
  
  if (isset($_POST['number']) && $_POST['number'] != "") {
    $array['number'] = $_POST['number'];
    $array['number_operator'] = $_POST['number_operator'];
  }
  
  if (isset($_POST['year']) && $_POST['year'] != "") {
    $array['year'] = $_POST['year'];
    $array['year_operator'] = $_POST['year_operator'];
  }
  
  if (isset($_POST['bitrate']) && $_POST['bitrate'] != "") {
    $array['bitrate'] = $_POST['bitrate'];
    $array['bitrate_operator'] = $_POST['bitrate_operator'];
  }
  
  if (isset($_POST['frequency']) && $_POST['frequency'] != "") {
    $array['frequency'] = $_POST['frequency'];
    $array['frequency_operator'] = $_POST['frequency_operator'];
  }
  
  if (isset($_POST['size']) && $_POST['size'] != "") {
    $array['size'] = $_POST['size'];
    $array['size_operator'] = $_POST['size_operator'];
  }
  
  if (isset($_POST['type']) && $_POST['type'] != "") {
    $array['type'] = $_POST['type'];
  }
  
  if (isset($_POST['comment']) && $_POST['comment'] != "")
    $array['comment'] = $_POST['comment'];
  
  if (isset($_POST['lyrics']) && $_POST['lyrics'] != "")
    $array['lyrics'] = $_POST['lyrics'];

  return $array;
}

/**
 * Filters out the searches
 * that don't match the meta information.
 *
 * @author Ben Dodson
 * @version 4/6/05
 * @since 4/6/05
 *
 **/
function filterSearchResults($results,$meta) {
  $ret = array();

  // NOTE: Right now the operators are forgiving
  // in that a < is treated like <=.
  foreach ($results as $r) {
    $remove = false;
    $m = $r->getMeta();
    if (removeResultNumber("bitrate",$m,$meta) ||
	removeResultNumber("length",$m,$meta) ||
	removeResultNumber("size",$m,$meta) ||
	removeResultNumber("year",$m,$meta) ||
	removeResultNumber("number",$m,$meta) ||
	removeResultNumber("frequency",$m,$meta)
	) {
      $remove = true;
    }

    if (removeResultString("lyrics",$m,$meta) ||
	removeResultString("comment",$m,$meta)
	) {
      $remove = true;
    }

    if (!$remove) {
      $ret[] = $r;
    }
  }
  return $ret;
}	

/**
 * Tests to see if a result should be
 * removed based on its metadata
 * for a certain key.
 *
 * @author Ben Dodson
 * @version 4/6/05
 * @since 4/6/05
 **/
function removeResultNumber($type,$m,$meta) {
  $operator = $type . "_operator";

  $remove = false;
  if (isset($meta[$type])) {
    switch ($meta[$operator]) {
    case "=":
      if ($m[$type] != $meta[$type])
	$remove = true;
      break;
    case "<":
      if ($m[$type] > $meta[$type])
	$remove = true;
      break;
    case ">":
      if ($m[$type] < $meta[$type])
	$remove = true;
      break;
    }
  }
  return $remove;
}

/**
 * Checks to see if a result should be
 * removed because of a lack of string-matching
 *
 * @author Ben Dodson
 * @since 4/6/05
 * @version 4/6/05
 **/
function removeResultString($type,$m,$meta) {
  if (!isset($meta[$type])) {
    return false;
  }

  if (!isset($m[$type])) {
    return true;
  }

  $words = explode(" ",$meta[$type]);
  foreach($words as $word) {
    if (false === stristr($m[$type],$word)) {
      return true;
    }
  }
  return false;
}


/**
 * Estimates the rating given a float.
 * Return value is between 0 and 5, incremented by .5.
 * 
 * @author Ben Dodson
 * @version 6/13/04
 * @since 6/11/04
 */
function estimateRating($val) {
  $whole = floor($val);
  $fraction = $val - $whole;
  
  if ($fraction < .25) {
    return $whole;
  } else if ($fraction < .75) {
    return $whole + .5;
  } else {
    return $whole + 1;
  }
}

/**
 * Gets the filemtime for a file
 * But if it's in the future,
 * fix it up to make it compatible.
 *
 * @author Ben Dodson
 * @since 4/11/05
 * @version 4/11/05
 **/
function jz_filemtime($file) {
  $mdate = filemtime($file);
  if ($mdate > ($curtime = time())) {
    if (@touch($file) === false) {
      $mdate = $curtime - ($mdate - $curtime);
    } else {
      $mdate = filemtime($file);
    }
  }
  return $mdate;
}

/**
 * Checks to see if the string represents
 * a different way of being empty.
 * Standards are good, but isNothing functions
 * are cool too!
 *
 * @author Ben Dodson
 * @since 4/12/05
 * @version 4/12/05
 *
 **/
function isNothing($string) {
  if (!isset($string) ||
      $string == "" || 
      $string == false ||
      $string == "-")
    return true;
  
  return false;
}

/**
 * Removes an entry from an array and reindexes.
 *
 * @author Ben Dodson
 * @since 8/1/05
 * @since 8/1/05
 **/
function removeFromArray(&$arr,$el) {
  $removed = false;
  foreach ($arr as $id=>$a) {
    if ($a == $el) {
      $removed = true;
      unset($arr[$id]);
    }
  }
  if ($removed) {
    $arr = array_values($arr);
  }
}
  
?>
