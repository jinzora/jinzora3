<?php if (!defined(JZ_SECURE_ACCESS)) die ('Security breach detected.');
	/* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * *    
	* JINZORA | Web-based Media Streamer   
	*
	* Jinzora is a Web-based media streamer, primarily desgined to stream MP3s 
	* (but can be used for any media file that can stream from HTTP). 
	* Jinzora can be integrated into a CMS site, run as a standalone application, 
	* or integrated into any PHP website.  It is released under the GNU GPL.
	* 
	* Jinzora Author:
	* Ross Carlson: ross@jasbone.com
	* http://www.jinzora.org
	* Documentation: http://www.jinzora.org/docs	
	* Support: http://www.jinzora.org/forum
	* Downloads: http://www.jinzora.org/downloads
	* License: GNU GPL <http://www.gnu.org/copyleft/gpl.html>
	* 
	* Contributors:
	* Please see http://www.jinzora.org/modules.php?op=modload&name=jz_whois&file=index
	*
	* Code Purpose: This page contains all the "general" related functions
	* Created: 9.24.03 by Ross Carlson
	*
	* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */
	
	
	
	/** 
	* Setup the Smarty template system
	*
	* @author Ross Carlson
	* @since 2.27.06
	*
	**/
	function smartySetup(){
      global $web_root, $root_dir, $include_path;
      
      $d = dirname(__FILE__);
      $root = substr($d, 0, strlen($d)-4) . "/";
      
      define('SMARTY_DIR', $root. 'lib/smarty/');
      define('SMARTY_ROOT', $root);
      require_once(SMARTY_DIR . 'Smarty.class.php');

      $smarty = new Smarty;
      $smarty->compile_dir = $root. 'temp/';
      $smarty->cache_dir = $root. 'temp/';

      return $smarty;
   }
	
	
/* 
 * Gets a global variable without
 * having to declare global.
 * Useful for settings.
 * 
 * @author Ben Dodson
 * @since 1/30/07
 */
  function conf($name) {
  	global $$name;
  	if (isset($$name)) {
	    return $$name;
  	} else {
    	return null;
  	}
  }
	
	
	/** 
	* Verifies that a directory exists and if not creates it
	*
	* @author Ross Carlson
	* @since 11/02/2005
	* @param $dir Name of the directory to create
	*
	**/
	function makedir($dir){
		$dArr = explode("/",$dir);
		$prevDir = "";
		foreach($dArr as $dir){
			$prevDir .= $dir. "/";
			if (!is_dir($prevDir)){
				mkdir($prevDir);
			}
		}
	}
	
	/** 
	* Cleans a potential filename of back characters
	*
	* @author Ross Carlson
	* @since 11/02/2005
	* @param $name Name of the track to clean
	* @return $ret Cleaned track name
	*
	**/
	function cleanFileName($name){
		$badArray = explode(";",';?;";/;\;|;*;<;>;:');	
		for ($e=0;$e<count($badArray);$e++){
			$name = str_replace($badArray[$e],"",$name);
		}
		return $name;
	}
	
	/** 
	* Grabs the data that was parsed from a Podcast
	*
	* @author Ross Carlson
	* @since 11/02/2005
	* @param $item An array with all the values to grab
	* @param $folder The subfolder to store the file in
	* @return boolean true|false
	*
	**/
	function getPodcastData($item, $folder){
		global $include_path, $podcast_folder;
		
		if ($item['file'] == ""){return false;}
		
		$be = new jzBackend();
		$display = new jzDisplay();
		
		// Let's clean the new folder name
		$folder = trim(cleanFileName($folder));
		
		// Let's grab the file and save it to disk
		$ext = substr($item['file'],strlen($item['file'])-3,3);
		$track = trim(cleanFileName($item['title']. ".". $ext));
		
		if (substr($podcast_folder,0,1) <> "/"){
			$dir = str_replace("\\","/",getcwd()). "/". $podcast_folder. "/". $folder;
		} else {
			$dir = $podcast_folder. "/". $folder;
		}
		$track = $dir. "/". $track;
		
		// Now let's create the directory we need
		makedir($dir);
		
		// Now let's see if the file already exists
		if (!is_file($track)){
			?>
			<script language="javascript">
				t.innerHTML = '<?php echo word("Downloading"). ": ". $display->returnShortName($item['title'],45); ?>';									
				-->
			</SCRIPT>
			<?php 							
			flushdisplay();
			// Now let's grab the file and write it out
			$fName = str_replace("&amp;","&",$item['file']);
			$data = file_get_contents($fName);
			$handle = fopen($track, "w");
			fwrite($handle,$data);
			fclose ($handle);
			?>
			<script language="javascript">
				t.innerHTML = '<?php echo word("Download Complete!"); ?>';									
				-->
			</SCRIPT>
			<?php 							
			flushdisplay();
		} else {
			?>
			<script language="javascript">
				t.innerHTML = '<?php echo word("Exists - moving to next track..."); ?>';									
				-->
			</SCRIPT>
			<?php 							
			flushdisplay();
		}
		
		return $track;
	}
	
	/** 
	* Parses a given Podcast URL to retrieve all the enclosures
	*
	* @author Ross Carlson
	* @since 11/02/2005
	* @param $url The URL to parse
	* @return array of the files and descriptions in the XML file
	*
	**/
	function parsePodcastXML($url){
		
		// Let's get the data from the URL
		$data = file_get_contents($url);
		$c=0;
		
		// Now let's parse out the basics about the feed
		$title = substr($data,strpos($data,"<title>")+strlen("<title>"));
		$title = substr($title,0,strpos($title,"</title>"));		
		$desc = substr($data,strpos($data,"<description>")+strlen("<description>"));
		$desc = substr($desc,0,strpos($desc,"</description>"));		
		$pubDate = substr($data,strpos($data,"<pubDate>")+strlen("<pubDate>"));
		$pubDate = substr($pubDate,0,strpos($pubDate,"</pubDate>"));
		$image = "";
		if (stristr($data,"<itunes:image")){
			$image = substr($data,strpos($data,"<itunes:image"));
			$image = substr($image,strpos($image,'href="')+6);
			$image = substr($image,0,strpos($image,'"'));
		}
		
		// Now let's set the array
		$retArray['title'] = $title;
		$retArray['desc'] = $desc;
		$retArray['pubDate'] = $pubDate;
		$retArray['image'] = $image;		
		$c++;
		
		// Now let's get the individual items
		$items = substr($data,strpos($data,"<item>"));
		$iArr = explode("</item>",$items);		
		// Now let's loop
		foreach ($iArr as $item){
			// Now let's parse that out
			$title = substr($item,strpos($item,"<title>")+strlen("<title>"));
			$title = substr($title,0,strpos($title,"</title>"));	
			$desc = substr($item,strpos($item,"<description>")+strlen("<description>"));
			$desc = substr($desc,0,strpos($desc,"</description>"));	
			$desc = str_replace("]]>","",str_replace("<![CDATA[","",$desc));
			$link = substr($item,strpos($item,"<link>")+strlen("<link>"));
			$link = substr($link,0,strpos($link,"</link>"));	
			$pubDate = substr($item,strpos($item,"<pubDate>")+strlen("<pubDate>"));
			$pubDate = substr($pubDate,0,strpos($pubDate,"</pubDate>"));	
			$file = substr($item,strpos($item,'url="')+strlen('url="'));
			$file = substr($file,0,strpos($file,'"'));
			$length = substr($item,strpos($item,'length="')+strlen('length="'));
			$length = substr($length,0,strpos($length,'"'));
			
			// Now let's set the return array
			$retArray[$c]['title'] = $title;
			$retArray[$c]['link'] = $link;
			$retArray[$c]['pubDate'] = $pubDate;
			$retArray[$c]['file'] = $file;
			$retArray[$c]['desc'] = $desc;
			$retArray[$c]['length'] = $length;
			$c++;
		}
		
		// Now let's return 
		return $retArray;
	}
	
	
	/*
	 * Fallback function for stripos
	 * 
	 * @author Ben Dodson, from PHP.net
	 * @since 8/31/06
	 */
	if (!function_exists("stripos")) {
  		function stripos($str,$needle,$offset=0) {
     		return strpos(strtolower($str),strtolower($needle),$offset);
  		}
	}
	
	
	
	/** 
	* Allows you to write 1 setting to a settings file
	*
	* @author Ross Carlson
	* @since 7/04/2005 - Happy 4th all you Americans!!!
	* @param $setting The setting to write
	* @param $val The value to write to the setting
	* @param $file The file to write too (the full path and filename)
	* @return true|false wether the action was successful
	*
	**/
	function writeSetting($setting, $val, $file){
	
		// Now let's open the file and read it in
		$fArr = file($file);
		
		// Let's start our new file
		$newFile = "";
		
		// Now let's loop through and find the setting
		foreach($fArr as $line){
			// Now does this line have the setting we want?
			if (stristr($line,$setting)){
				// Ok we found it, let's change it
				$newFile .=  substr($line,0,strpos($line," =")). ' = "'. $val. '";'. "\n";
			} else {
				// Nope, not the line
				$newFile .= $line;
			}
		}
		
		// Now we need to write the file back out
		if (is_writable($file)){
			$handle = fopen($file, "w");
			fwrite($handle,$newFile);
			fclose ($handle);
			return true;
		} else {
			return false;
		}		
	}

	/** 
	* Checks the site's general security.
	*
	* @author Ben Dodson
	* @since 6/30/05
	*
	**/
	 function checkSecurity() {
		 // TODO: Check the include file; make sure it is index.php
		 // or a CMS-specified file.
		 return;
	 }

	/** 
	* Makes sure a file is safe to include.
	*
	* @author Ben Dodson
	* @since 8/15/05
	*
	**/
	function includeable_file($fname, $dir = false) {
		global $include_path;
		
		if (stristr($fname,'/') !== false) {
			return false;
		}
		
		if (stristr($fname,"\\") !== false) {
			return false;
		}
		
		if ($dir !== false) {
			$dir = $include_path . $dir;
			// make sure it's in $dir.
			if (!is_dir($dir)) {
				die("$dir is not a valid directory.");
			}
			$d = dir($dir);
			while ($entry = $d->read()) {
				if ($entry == $fname) {
					return true;
				}
			}
			return false;
		}
		
		return true;
	}


	
	/**
	* 
	* Sets a global variable.
	*
	* @author Ben Dodson
	* @since 6/7/05
	* 
	**/
	function setGlobal($var,$val) {
			$GLOBALS[$var] = $val;
		} 

  /**
	* 
	* Gets a global variable.
	*
	* @author Ben Dodson
	* @since 6/7/05
	* 
	**/
	function getGlobal($var) {
		return isset($GLOBALS[$var]) ? $GLOBALS[$var] : false;
	}

	
	/**
	* 
	* Takes binary data and writes it to a file
	*
	* @author Ross Carlson
	* @since 3/31/05
	* @param $file the name of the file (the full path)
	* @param $data the binary data to write to the file
	* @param return returns true or false (bolean)
	* 
	**/
	function writeImage($file, $data){
		global $bad_chars;
		
		foreach ($bad_chars as $item){
			str_replace($item,"",$file);
		}
		
		if (is_file($file)){ unlink($file); }
		$handle = fopen($file, "w");
		if (fwrite($handle,$data)){
			fclose ($handle);
			return true;
		} else {
			fclose ($handle);
			return false;
		}
			
	}

	/**
	 * Makes a string safe for XML
	 * @author php.net
	 * @since 8/11/05
	 **/
	function htmlnumericentities($str){
		return preg_replace('/[^!-%\x27-;=?-~ ]/e', '"&#".ord("$0").chr(59)', $str);
	}

        function xmlentities($string) {
	  return str_replace ( array ( '&', '"', "'", '<', '>', '�' ), array ( '&amp;' , '&quot;', '&apos;' , '&lt;' , '&gt;', '&apos;' ), $string );
        }
	
	/**
	 * @author Ben Dodson
	 * 
	 **/
	function sendFileBundle($myfiles, $name) {
	  global $multiple_download_mode, $download_speed;

		$files = array();
		foreach ($myfiles as $file) {
			if ($file != '' && is_file($file)) {
				$files[] = $file;
			}
		}
	
	  if ($files == array()) {
		exit();
	  }

	  if ($multiple_download_mode == 'tar') {
			$reader = &new jzStreamTar($files);
			header ('Content-Type: application/x-tar');
			header ('Content-Disposition: attachment; filename="' . $name . '.tar"');
	  } else { // assume zip
			$reader = &new jzStreamZip($files);
			header ('Content-Type: application/zip');
			header ('Content-Disposition: attachment; filename="' . $name . '.zip"');
	  }
	  

	  
	  // content length header if supported
	  if (($size = $reader->FinalSize()) != 0) {
	  	$range = getContentRange($size);
		if ($range !== false) {
			$range_from = $range[0];
			$range_to = $range[1];
		} else {
			$range_from = 0;
			$range_to = $size-1;
		}
		
		// BJD 6/30/06:
		// HACK: range_to is not supported- only resuming and requesting entire
		// remains of file
		$range_to = $size-1;
		
		if ($range === false) {
		  // Content length has already been sent
		  header("Content-length: ".(string)$size);
		} else {
				header("HTTP/1.1 206 Partial Content");
				header("Accept-Range: bytes");
				header("Content-Length: " . ($size - $range_from));
				header("Content-Range: bytes $range_from" . "-" . ($range_to) . "/$size");
		}
	  } else {
	  	$range = false;
	  	$range_from = $range_to = 0;
	  }
	  
	  // caching headers
	  header("Cache-control: private");
	  header("Expires: " . gmdate("D, d M Y H:i:s", mktime(date("H") + 8, date("i"), date("s"), date("m"), date("d"), date("Y"))) . " GMT");
	  
	  //header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
	  $lastmod = 0;
	  foreach ($files as $f) {
	  	if (($t = filemtime($f)) > $lastmod) {
	  		$lastmod = $t;
	  	}
	  }
	  
	  header("Last-Modified: " . gmdate("D, d M Y H:i:s", $lastmod) . " GMT");
	  header("Pragma: no-cache");
	  
	  // Let's up the max_execution_time
	  //ini_set('max_execution_time','6000');
	  @set_time_limit(0);
	  // open reader
	  $reader->Open();
	 
	  // Are we resuming?
	  if ($range_from > 0) {
	  	$reader->Read($range_from);
	  }

	  // let's send, no speed limit
	  if ($download_speed == 0 || $download_speed == "") {
		while ( ($data=$reader->Read(4096))!='' and connection_status()==0 ) {
		  echo $data;
		  flush();
		}
	  } else {
		// let's send, looking at the speed
		$sent = 0;
		$begin_time = get_time();
		while ( ($data=$reader->Read(4096))!='' and connection_status()==0 ) {
		  echo $data;
		  flush();
		  $sent += 4;
		  // if current speed is too high, let's wait a bit
		  while ($sent/(get_time()-$begin_time) > $download_speed) {
		sleep(1);
		  }
		}
	  }
	  
	  // close reader
	  $reader->Close(); 
	
	  @set_time_limit(30);
	  //write the result in log
	  if (connection_status() == 0) {
		writeLogData('download','download of '.$name.' sucessful');
	  }
	  else {
		writeLogData('download','download of '.$name.' failed');
	  }
	
	}
	
		
	/**
	 * Displays the image in a browser.
	 * 
	 *  
	 * @author Ben Dodson
	 * @version 11/10/04
	 * @since 11/10/04
	 */	
	function showImage ($path) {
		global $include_path;
		
		// Now let's see if this is an ID3 image or not
		if (stristr($path,"ID3:")){
			// Now let's get the data we need
			include_once($include_path. 'services/class.php');
			$jzSERVICES = new jzServices();
			$jzSERVICES->loadStandardServices();
			
			// Now let's fix the path
			$path = substr($path,4);
			$meta = $jzSERVICES->getTagData($path);
			
			// Now let's set the header
			header("Content-Type: ". $meta['pic_mime']);
			sendID3Image($path, $meta['pic_name'],$meta['pic_data']);
		} else {
			$arr = explode("/",$path);
			if (sizeof($arr > 0))
				$name = $arr[sizeof($arr)-1];
			else
				$name = $path;	
			
			if (substr($path, -3) == "jpg" || substr($path, -4) == "jpeg" || substr($path, -3) == "jpe")
				header("Content-Type: image/jpeg");
			
			else if (substr($path, -3) == "gif")	
				header("Content-Type: image/gif");
			
			else if (substr($path, -3) == "png")
				header("Content-Type: image/png");
			
			else if (substr($path, -3) == "bmp")
				header("Content-Type: image/bmp");
		}
		
		// TODO: GD stuff; synchronize args with jzDisplay::image.
		// images are small, so don't worry about handling stream stuff.
		streamFile($path,$name);
	}


   /** 
	 * Sends the content-type header
	 *
	 * @author Ben Dodson
	 * @version 8/17/05
	 * @since 8/17/05
	 **/
	function sendContentType($ext) {
	  switch ($ext){
	  case "mp3":
	    header("Content-Type: audio/mpeg");
	    break;
	  case "wav":
	    header("Content-Type: audio/x-wav");
	    break;
	  case "mpc":
	    header("Content-Type: application/mpc");
	    break;
	  case "wv":
	    header("Content-Type: application/wv");
	    break;
	  case ".ra":
	  case ".rm":
	    header("Content-Type: audio/x-pn-realaudio");
	    break;
	  case "flac":
	    header("Content-Type: application/flac");
	    break;
	  case "ogg":
	    header("Content-Type: application/ogg");
	    break;
	  case "avi":
	    header("Content-Type: video/x-msvideo");
	    break;
	  case "mpg":
	  case "mpeg":
	    header("Content-Type: video/x-mpeg");		
	    break;
	  case "asf":
	  case "asx":
	  case "wma":
	  case "wmv":
	    header("Content-Type: video/x-ms-asf");
	    break;
	  case "mov":
	    header("Content-Type: application/x-quicktimeplayer");
	    break;
		case "flv":
	    header("Content-Type: video/x-flv");
	    break;
	  case "mid":
	  case "midi":
	    header("Content-Type: audio/midi");
	    break;
	  case "aac":
	  case "mp4":
	    header("Content-Type: application/x-quicktimeplayer");
	    break;
	  }
	}


	/**
	 * Sends a clip of a media file.
	 * 
	 * @author Ben Dodson
	 * @version 8/17/05
	 * @since 8/17/05
	 */	
   function sendClip($el) {
	   global $clip_length, $clip_start;

	   if (!$el->isLeaf()) {
	     return false;
	   }

	   $fname = $el->getFilename("host");
	   $ext = substr($fname,strrpos($fname,".")+1);

	   $clipfile = substr($fname,0,-4).".clip." . $ext;
	   if (is_file($clipfile)) {
	     sendMedia($clipfile, $el->getName() . " Clip." . $ext);
	     exit();
	   }

	   $meta = $el->getMeta();
	   $bitrate = $meta['bitrate'];
	   if (!is_numeric($bitrate)) {
	     // Let's assume 160.
	     $bitrate = 160;
	   }

	   $cstart = $clip_start * ($bitrate * 1024 / 8);
	   $clength = $clip_length * ($bitrate * 1024 / 8);

	   $contents = substr(file_get_contents($fname),$cstart,$clength);
	   sendContentType($ext);
	   header("Content-length: " . $clength);
	   header("Content-Disposition: inline; filename=\"".$el->getName() . " Clip." . $ext."\"");
	   header("Expires: ".gmdate("D, d M Y H:i:s", mktime(date("H")+2, date("i"), date("s"), date("m"), date("d"), date("Y")))." GMT");
	   header("Last-Modified: ".gmdate("D, d M Y H:i:s")." GMT");
	   header("Cache-Control: no-cache, must-revalidate");
	   header("Pragma: no-cache");

	   print $contents;

	   exit();
	 }


	
	/**
	 * Sends the actual media file
	 * and possibly resamples it.
	 * 
	 * 
	 * @author Ben Dodson
	 * @version 11/11/04
	 * @since 11/11/04
	 */	
	function sendMedia($path, $name, $resample=false, $download = false) {
		// Let's get the extension from the file
		$extArr = explode(".",$path);
		$ext = $extArr[count($extArr)-1];
		
		// Now let's fix up the name
		if (substr($name,0-strlen($ext)-1) != "." . $ext) {
		  $name .= "." . $ext;
		}

		if ($resample != "") {
		   $ext = false;
		}	

		// TODO: resample.
		// probably make a different streamFile (streamResampled)
		streamFile($path,$name,false,$resample,$download, true, $ext);
	}
	
/** 
 * Sends the ID3 image
 *
 * @author Ben Dodson
 * @version 7/20/05
 * @since 7/20/05
 **/
function sendID3Image($path,$name,$id3) {
  header("Content-length: ".(string)(strlen($id3)));
  header("Content-Disposition: inline; filename=\"".$name."\"");
  header("Expires: ".gmdate("D, d M Y H:i:s", mktime(date("H")+2, date("i"), date("s"), date("m"), date("d"), date("Y")))." GMT");
  header("Last-Modified: ".gmdate("D, d M Y H:i:s", filemtime($path))." GMT");
  header("Cache-Control: no-cache, must-revalidate");
  header("Pragma: no-cache");
  print($id3);
  return true;
  
}



	/**
	 * Actually sends the data in the specified file.
	 * 
	 * 
	 * @author Ben Dodson, PHP.net
	 * @version 11/11/04
	 * @since 11/11/04
	 */	
	function streamFile($path,$name,$limit=false,$resample="",$download = false, $contentTypeFor = false) {
		global $always_resample, $allow_resample, $always_resample_rate, $jzUSER;
		

		// Let's ignore if they abort, that way we'll know when the track stops...
		ignore_user_abort(TRUE);	
		
		$jzSERVICES = new jzServices();
		$jzSERVICES->loadStandardServices();

		$status = false;
		
		if ($limit === false)
			$speed_limit = 1*1024; // from system.php?
		else
			$speed_limit = $limit;
		// limit is passed as a param because we may want to limit it for downloads
		// but not for streaming / image viewing.
		// Also, we may want to write a different function for resampling,
		// but I don't know yet.
		
		// IF NO SPEED LIMIT:
		// the 'speed_limit' from above is the amount
		// of buffer used while sending the file.
		// but with no speed limit, there is no 'sleep' issued.
		// this makes seeking in a file much faster.
		
		// Let's get the extension of the real file
		$extArr = explode(".",$path);
		$ext = $extArr[count($extArr)-1];
		
		if (!is_file($path) || connection_status() != 0) return (false); 
		
		$meta = $jzSERVICES->getTagData($path);
		$do_resample = false;
		

		if (!isNothing($resample)) {
		  $do_resample = true;
		}

		if (($allow_resample == "true") && stristr($always_resample,$ext)) {
		  $do_resample = true;
		}
		


		if ($meta['type'] == "mp3") {
		  if (!isNothing($resample) && $resample >= $meta['bitrate']) {
		    $do_resample = false;
		  }
		}
		if ($download) {
		  $do_resample = false;
		}
		// Are they resampling or transcoding?
		if ($do_resample){
			// Ok, if resampling isn't set let's go with the default
			if ($resample == ""){
				$resample = $always_resample_rate;
			}
			
			// Now let's load up the resampling service
			$jzSERVICES = new jzServices();
			$jzSERVICES->loadService("resample","resample");
			$jzSERVICES->resampleFile($path,$name,$resample);
			
			// Now let's unset what they are playing
			$be = new jzBackend();
			$be->unsetPlaying($_GET['jz_user'],$_GET['sid']);
			
			return;
		}
		// Now we need to know if this is an ID3 image or not
		// First let's get their limit
		$limit = "7";

		
		$size = filesize($path);
		
		$range = getContentRange($size);
		if ($range !== false) {
			$range_from = $range[0];
			$range_to = $range[1];
		} else {
			$range_from = 0;
			$range_to = $size-1;
		}

		$ps3 = false;
		$allheaders = getallheaders();
		if (isset($allheaders['User-Agent']) && $allheaders['User-Agent'] == "PLAYSTATION 3") {
		  $ps3 = true;
		}

		if ($ps3) {
  		  // ps3 is both picky and buggy.
		  if ($range_from > $size) {
		    // Yeah, this happens, and sending a
		    // 416 Requested Range Not Satisfiable fails.
		    // This is probably causing our high-latency delays
		    // but I don't know a better workaround.
		    header("HTTP/1.1 200 OK");
		    $range_from = 0;
		    $range_to = $size - 1;
		  } else if ($range_from == 0 && $size == $range_to + 1) {
		    header("HTTP/1.1 200 OK");
		  } else {
		    fwrite($f, "PARTIAL $range_from $range_to $size\n");
  		    header("HTTP/1.1 206 Partial Content");
		    header("CONTENT-RANGE: bytes ${range_from}-${range_to}/${size}");
		  }

		  header("transferMode.dlna.org: Streaming");
		  header("contentFeatures.dlna.org: DLNA.ORG_OP=01;DLNA.ORG_CI=0;DLNA.ORG_FLAGS=017000 00000000000000000000000000");
		  header("Accept-Ranges: bytes");
		  header("Connection: keep-alive");
		  header("Content-Length: " . $size - $range_from);
		  
		} else if ($range === false) {
		  // Content length has already been sent
		  header("Content-Length: ".(string)$size);
		} else {
			header("HTTP/1.1 206 Partial Content");
			header("Accept-Range: bytes");
			header("Content-Length: " . ($size - $range_from));
			header("Content-Range: bytes $range_from" . "-" . ($range_to) . "/$size");
		}

		if ($contentTypeFor !== false) {
		  sendContentType($contentTypeFor);
		}

		if (!$ps3) {
		  header("Content-Disposition: inline; filename=\"".$name."\"");
		  header("Expires: ".gmdate("D, d M Y H:i:s", mktime(date("H")+2, date("i"), date("s"), date("m"), date("d"), date("Y")))." GMT");
		  header("Last-Modified: ".gmdate("D, d M Y H:i:s", filemtime($path))." GMT");
		  header("Cache-Control: no-cache, must-revalidate");
		  header("Pragma: no-cache");
		}

		if ($file = fopen($path, 'rb')) {
		  @set_time_limit(0);
		  fseek($file, $range_from);				
		  while(!feof($file) and (connection_status()==0) and ($cur_pos = ftell($file)) < $range_to+1) {
		  	print(fread($file, min(1024*$speed_limit, $range_to + 1 - $cur_pos)));
		    flush();
		    if ($limit !== false) {
		    	sleep(1);
		    }
		  }
		 
		  $status = (connection_status()==0);
		  fclose($file);
		  @set_time_limit(30);
		}
		
		// Now let's unset what they are playing
		$be = new jzBackend();
		$be->unsetPlaying($_GET['jz_user'],$_GET['sid']);
		
		return($status);
	}

	
	/**
	 * Converts an associative array to a link.
	 * Function can take 2 arrays and treats them both the same.
	 * Useful if you have a set of variables that you use a lot
	 * and another set you use only once or twice.
	 * 
	 * This function also remembers things like GET-based frontend/theme settings.
	 *
	 * @author Ben Dodson
	 * @version 11/6/04
	 * @since 11/5/04
	 */
	function urlize($arr1 = array(), $arr2 = array()) {
	  global $this_page, $root_dir, $link_root, $include_path, $ssl_stream, $secure_urls,$jzUSER;
	  
	  $this_page = setThisPage();
	  $arr = $arr1 + $arr2;
	  
	  if (!isset($arr['action'])) { 
	    $action="";
	  } else {
	    $action = $arr['action'];
	  }
	   
	  if (isset($arr['jz_path']) && $arr['jz_path'] == "") {
	    unset($arr['jz_path']);
	  }
 
	  if ($action != "play" && $action != "playlist") {
		  if (isset($_GET['frontend']) && !isset($arr['frontend'])) {
		    $arr['frontend'] = $_GET['frontend'];
		  } else if (isset($_POST['frontend']) && !isset($arr['frontend'])) {
		    $arr['frontend'] = $_POST['frontend'];
		  }
		  
		  if (isset($_GET['theme']) && $_GET['theme'] != '' && !isset($arr['theme'])) {
		    $arr['theme'] = $_GET['theme'];
		  } else if (isset($_POST['theme']) && $_POST['theme'] != '' && !isset($arr['theme'])) {
		    $arr['theme'] = $_POST['theme'];
		  }
	  }
	  
	  switch ($action) {
	  case "play":
	    $link = "";
	    if ($ssl_stream == "true") {
	      $link .= "https://";
	    } else {
	      $link .= "http://";
	    }
	    $link .=  $_SERVER['HTTP_HOST'];
	    if ($_SERVER['SERVER_PORT'] != 80 && $_SERVER['SERVER_PORT'] != 443 && (strpos($link,":") === false)) {
	      $link .= ":" . $_SERVER['SERVER_PORT'];
	    }
	    $link = str_replace(":443","",$link);
	    $link .= $root_dir . "/mediabroadcast.php?";
	    $ENC_FUNC = "jz_track_encode";
	    // fairly gross hack for non-mp3 files:
	    if (isset($arr['ext'])) {
	      $extension = $arr['ext'];
	      unset($arr['ext']);
	    } else {
	      $extension = false;
	    }
	    break;
	  case "image":
	    //case "download":
	    $link = "";
	    if ($_SERVER['SERVER_PORT'] == 443) {
	      $link .= "https://";
	    } else {
	      $link .= "http://";
	    }
	    $link .=  $_SERVER['HTTP_HOST'];
	    if ($_SERVER['SERVER_PORT'] != 80 && $_SERVER['SERVER_PORT'] != 443 && (strpos($link,":") === false)) {
	      $link .= ":" . $_SERVER['SERVER_PORT'];
	    }
	    $link = str_replace(":443","",$link);
	    $link .= $root_dir . "/mediabroadcast.php?image=tr&";
	    $ENC_FUNC = "jz_encode";
	    // fairly gross hack for non-mp3 files:
	    if (isset($arr['ext'])) {
	      $extension = $arr['ext'];
	      unset($arr['ext']);
	    } else {
	      $extension = false;
	    }
	    break;
	  case "popup":
	    $link = $root_dir . "/popup.php?";
	    $ENC_FUNC = "jz_encode";
	    $extension = false;
	    break;
	  default:
	    $link = $this_page; // setThisPage handles CMS stuff and adds & or ?.
	    $ENC_FUNC = "jz_encode";
	    $extension = false;
	  }
	  if ($action == "playlist") {
	  	$extension = $jzUSER->getSetting('playlist_type');
	  }
	  
	  //$jz_secure = "true"; // for now.
	  $vars="";
	  if ($secure_urls == "true" && !url_full_exception($arr)) {
			foreach ($arr as $key => $val) {
		  	// if jz_secure, jz_encode our keys and vars.
			  if (!url_key_exception($key))
					$vars .= "&" . urlencode($ENC_FUNC($key)) . "=" . urlencode($ENC_FUNC($val));
			  else
					$vars .= "&" . urlencode($key) . "=" . urlencode($val);
			}
	  } else {
			foreach ($arr as $key => $val) {
		  	$vars .= "&" . urlencode($key) . "=" . urlencode($val);
			}
	  }
	
	  $link = $link . substr($vars,1);
	
	  if (!isNothing($extension)) {
			$link .= "&ext." . $extension;
	  } else {
	  	$link .= "&ext.html";
	  }

	  return $link;
	}
	
	/**
	 * Gets the URL root from a URLized string.
	 * 
	 * @author Ben Dodson
	 * @since 7/16/06
	 * @version 7/16/06
	 */
	 function getURLRoot($url) {
	 	return substr($url, 0, strpos($url,'?'));
	 }
	
	/**
	 * Gets the URL root from a URLized string.
	 * 
	 * @author Ben Dodson
	 * @since 7/16/06
	 * @version 7/16/06
	 */
	function getURLVars($url) {
		$url = substr($url, strpos($url,'?')+1);
	
		$url = explode("&",$url);
	    $url2 = array();
	    foreach ($url as $var){
	    	$url2[substr($var,0,strpos($var,'='))] =
	        substr($var,strpos($var,'=')+1);
	    }
	
	
		return $url2;
	}

	
	/**
	 * Inverse of the above.
	 *
	 * @author Ben Dodson
	 * @version 11/11/04
	 * @since 11/11/04
	 */
	function unurlize($arr) {
		global $secure_urls;
		
		$GET = array();
	
		if (false !== stristr($_SERVER['PHP_SELF'],"mediabroadcast.php")) {
		  if (isset($arr['image'])) {
				$DEC_FUNC = "jz_decode";
		  } else {
		    $DEC_FUNC = "jz_track_decode";
		  }
		} else {
		  $DEC_FUNC = "jz_decode";
		}
		//$jz_secure = "true";
		if ($secure_urls == "true" && !url_full_exception($arr)) {
			foreach ($arr as $key => $val) {
				// if jz_secure, jz_encode our keys and vars.
				if (!url_key_exception($key) && !is_array($key)) {
					$GET[$DEC_FUNC($key)] = $DEC_FUNC(stripSlashes($val));
				} else if (!is_array($key)) {
					$GET[$key] = stripSlashes($val);
				} else {
				  $GET[$key] = $val;
				}
			}
		} else {
			foreach ($arr as $key => $val) {
			  if (!is_array($key)) {
				$GET[$key] = stripSlashes($val);
			  } else {
				$GET[$key] = $val;
			  }
			}
		}
		return $GET;
	}
	/**
	 * Same as above for POST variables.
	 *
	 * @author Ben Dodson
	 * @version 12/16/04
	 * @ since 12/16/04
	 */
	function unpostize($arr) {
	  $POST = array();
	  if (url_full_exception($arr)) {
		foreach($arr as $key => $val) {
		  $POST[$key] = stripSlashes($val);
		}
	  }
	  else {
		foreach ($arr as $key => $val) {
		  if (is_array($val)) {
		$arr1 = array();
		for ($i = 0; $i < sizeof($val); $i++) {
		  $arr1[] = stripSlashes(jz_decode($val[$i]));
		}
		$POST[$key] = $arr1;
		  } else if (post_key_exception($key)) {
		$POST[$key] = stripSlashes($val);
		  } else {
		$POST[jz_decode($key)] = jz_decode(stripSlashes($val));
		  }
		}
	  }
	
	  return $POST;
	}
	
	
	/**
	 * Checks to see if a key indicates our entire string should not be scrambled.
	 * 
	 * @author Ben Dodson
	 * @version 11/11/04
	 * @since 11/11/04
	 */
	function url_full_exception($arr) {

	  foreach ($arr as $key => $val) {
	    switch ($key) {
	      // if we see doSearch, don't unscramble/scramble since we
	      // came from searchbar
	    case "doSearch":
	      if (isset($_REQUEST['action']) && $_REQUEST['action'] != '' ) {
		if ($_REQUEST['action'] != "search") {
		  die("Not allowed.");
		}
	      }
	      return true;
	      break;
	    case "update_settings":
	      /* This will break if you try to update_settings
               * outside of a popup. 
	       */
	      if (isset($_REQUEST['action'])) {
		if ($_REQUEST['action'] != "popup") {
		  die("Not allowed.");
		}
	      }
	      /* Just so you know.
	       */
	      return true;
	      break;
	    }
	  }
	  
	  return false;
	}
	
	/**
	 * Checks to see if our key/val string should be scrambled.
	 * Things like text inputs are not scrambled.
	 * 
	 * @author Ben Dodson
	 * @version 11/11/04
	 * @since 11/11/04
	 */
	function url_key_exception($key) {
          global $jzSERVICES;

          $a = $jzSERVICES->cmsGETVars();
          if (isset($a[$key])) return true;

	  switch ($key) {
	  case "query":
	  case "search_query":
	  case "Artist": // decoy
	  case "Track": // decoy
	  case "Title": // decoy
	  case "op":
	  case "name":
	  case "frame":
	  case "jza":
	  case "view":
	  case "style":
	  case "user":
	  case "pass":
	  case "playlistname":
		//case "file":
		return true;
	  }
	  return false;
	}
	
	/** Same as above, but for POST variables.
	 * 
	 * @author Ben Dodson
	 * @version 12/16/04
	 * @since 12/16/04
	 */
	function post_key_exception($key) {
	
		// Let's account for some partial matches
		if (stristr($key,"plTrackPos-") 
			or stristr($key,"plTrackDel-")
			or stristr($key,"edit_")
			){
			return true;
		}
	
		switch ($key) {
				case "update_postsettings":
			case "jz_list":
			case "query":
			case "search_query":
			case "field1":
			case "field2":
			case "field3":
			case "field4":
			case "field5":
			case "remember":
			case "siteNewsData":
			case "jbvol":
			case "jbplaywhere":
			case "jbjumpto":
			case "addplat":
			case "jz_playlist":
			case "playlistname":
			case "updateTags":
			case "reGenre":
			case "reArtist":
			case "reAlbum":
			case "reTrack":
			case "reNumber":
			case "plType":
			case "shareWithUser":
			case "updatePlaylist":
			case "deletePlaylist":
			case "plToEdit":
		        case "randomize":
				return true;
			default:
				return false;
		}
	}
	
	
	/**
	 * Scrambles a string
	 * 
	 * @author Ben Dodson
	 * @version 11/6/04
	 * @since 11/6/04
	 */
	function jz_encode($string, $key = false) {
		global $secure_urls,$security_key;
		
		if ($secure_urls == "false"){
			return $string;
		}
	  // Complex scheme.
	  if ($key === false) {
	    $key = $security_key;
	    if (strlen($key) < 10) {
	      $key = "secrets are fun to keep (so let's keep them)";
	    }
	  }
	  $result = '';
	  for($i=1; $i<=strlen($string); $i++) {
			$char = substr($string, $i-1, 1);
			$keychar = substr($key, ($i % strlen($key))-1, 1);
			$char = chr(ord($char)+ord($keychar));
			$result .= $char;
	  }
	  return str_replace("+","JZPLUS",base64_encode($result)); 
	}
	
	/**
	 * Unscrambles a string.
	 *
	 * @author Ben Dodson
	 * @version 11/6/04
	 * @since 11/5/04
	 */
	function jz_decode($string, $key = false) {
		global $secure_urls,$security_key;
		
		if ($secure_urls == "false"){
			return $string;
		}
		
	  // Complex scheme.
	  $string = base64_decode(str_replace("JZPLUS","+",$string));
	  if ($key === false) {
	    $key = $security_key;
	    if (strlen($key) < 10) {
	      $key = "secrets are fun to keep (so let's keep them)";
	    }
	  }
	  $result = '';
	  for ($i=1; $i <= strlen($string); $i++) {
			$char = substr($string,$i-1,1);
			$keychar = substr($key,($i % strlen($key))-1,1);
			$char = chr(ord($char)-ord($keychar));
			$result .= $char;
	  }
		
	  return $result;
	}
	
	
	
	
	/*
	 * Encodes the URL for a track (since players break easily)
	 * 
	 * @author Ben Dodson
	 * @since 2/2/05
	 * @version 2/2/05
	 *
	 **/
	function jz_track_encode($string) {
	  $ret = "";
	  return strrev($string);
	  for ($i = 0; $i < strlen($string); $i++) {
		$ret .= chr(ord($string[$i])+4);
	  }
	
	  return strrev($ret);
	}
	
	/*
	 * Decodes the URL for a track (since players break easily)
	 * 
	 * @author Ben Dodson
	 * @since 2/2/05
	 * @version 2/2/05
	 *
	 **/
	function jz_track_decode($string) {
	  $ret = "";
	  return strrev($string);
	  $string = strrev($string);
	  for ($i = 0; $i < strlen($string); $i++) {
		$ret .= chr(ord($string[$i])-4);
	  }
	  return $ret;
	}

        function getTrackIdFromURL($url) {
	  global $secure_urls;

	  if ($secure_urls == "true") {
	    $decoded_label = jz_track_encode('jz_path');
	  } else {
	    $decoded_label = 'jz_path';
	  }

	  $args = substr($url,strpos($url,'?')+1);
	  $args = explode('&',$args);
	  foreach ($args as $arg) {
	    $parts = explode('=',$arg);
	    if ($parts[0] == $decoded_label) {
	      if ($secure_urls == "true") {
		return jz_track_decode($parts[1]);
	      } else {
		return $parts[1];
	      }
	    }
	  }
	  return false;
	}
	
	/**
	 * Scrambles a string for the cookie
	 * 
	 * @author Ben Dodson
	 * @version 1/16/05
	 * @since 11/23/04
	 */
	function jz_cookie_encode($string) {
	  return jz_encode($string, "this is a secret key that will be moved and improved.");
	}
	
	/**
	 * Unscrambles a stringf or the cookie
	 *
	 * @author Ben Dodson
	 * @version 1/16/05
	 * @since 11/23/04
	 */
	function jz_cookie_decode($string) {
	  return jz_decode($string, "this is a secret key that will be moved and improved.");
	}
	 
	function setThisPage() {
	  global $link_root, $cms_type, $cms_mode, $fe;	

	  if (defined('JZ_URL_OVERRIDE')) {
	    $link = JZ_URL_OVERRIDE . '?';
	  } else if ($cms_mode === false || $cms_mode == "false" || $link_root == "") {
            //$a = explode("/",$_SERVER['PHP_SELF']);
            //$link = $a[sizeof($a)-1] . '?';
	    $link = $_SERVER['PHP_SELF'];

	    $link = substr($link,0,strrpos($link,'/')).'/index.php?';
	    $link = str_replace("popup.php","index.php",$link);
	  }
	  else {
	  //  
		$link = $link_root;
	  }
	  
	  // check for things that need to be added:
	  $this_page = $link;

	  // Add additional settings to our URL:
	  if (isset($_GET['set_frontend'])) {
	    $this_page .= urlencode(jz_encode("frontend")) . "=" . urlencode(jz_encode($_GET['set_frontend'])) . "&";
	    $_GET['frontend'] = $_GET['set_frontend'];
	  } else if (isset($_POST['set_frontend'])) {
	    $this_page .= urlencode(jz_encode("frontend")) . "=" . urlencode(jz_encode($_POST['set_frontend'])) . "&";
	    $_GET['frontend'] = $_POST['set_frontend'];
	  } else if (isset($_GET['view'])) {
	    $this_page .= urlencode(jz_encode("frontend")) . "=" . urlencode(jz_encode($_GET['view'])) . "&";
	    $_GET['frontend'] = $_GET['view'];
	  } else if (isset($_GET['frontend'])) {
	    $this_page .= urlencode(jz_encode("frontend")) . "=" . urlencode(jz_encode($_GET['frontend'])) . "&";
	  }

          if (isset($_GET['set_theme'])) {
	    $this_page .= urlencode(jz_encode("theme")) . "=" . urlencode(jz_encode($_GET['set_theme'])) . "&";
	    $_GET['theme'] = $_GET['set_theme'];
	  } else if (isset($_POST['set_theme'])) {
	    $this_page .= urlencode(jz_encode("theme")) . "=" . urlencode(jz_encode($_POST['set_theme'])) . "&";
	    $_GET['theme'] = $_POST['set_theme'];
	  } else if (isset($_GET['style'])) {
	    $this_page .= urlencode(jz_encode("theme")) . "=" . urlencode(jz_encode($_GET['style'])) . "&";
	    $_GET['theme'] = $_GET['style'];
	  } else if (isset($_GET['theme'])) {
	    $this_page .= urlencode(jz_encode("theme")) . "=" . urlencode(jz_encode($_GET['theme'])) . "&";
	  }

	  if (isset($_GET['set_language'])) {
	    $this_page .= urlencode(jz_encode("language")) . "=" . urlencode(jz_encode($_GET['set_language'])) . "&";
	    $_GET['language'] = $_GET['set_language'];
	  } else if (isset($_POST['set_language'])) {
	    $this_page .= urlencode(jz_encode("language")) . "=" . urlencode(jz_encode($_POST['set_language'])) . "&";
	    $_GET['language'] = $_POST['set_language'];
	  } else if (isset($_GET['language'])) {
	    $this_page .= urlencode(jz_encode("language")) . "=" . urlencode(jz_encode($_GET['language'])) . "&";
	  }

	  
	  return $this_page;
	}

	
	/**
	 * Replace ob_flush()
	 *
	 * @category    PHP
	 * @package     PHP_Compat
	 * @link        http://php.net/function.ob_flush
	 * @author      Aidan Lister <aidan@php.net>
	 * @author      Thiemo M�ttig (http://maettig.com/)
	 * @version     $Revision$
	 * @since       PHP 4.2.0
	 * @require     PHP 4.0.1 (trigger_error)
	
	 */
	if (!function_exists('ob_flush'))
	{
		function ob_flush()
		{
			if (@ob_end_flush()) {
				return ob_start();
			}
	
			trigger_error("ob_flush() Failed to flush buffer. No buffer to flush.", E_USER_NOTICE);
	
			return false;
		}
	}
	
	/**
	 * Replace file_get_contents()
	 *
	 * @category    PHP
	 * @package     PHP_Compat
	 * @link        http://php.net/function.file_get_contents
	 * @author      Aidan Lister <aidan@php.net>
	 * @version     $Revision$
	 * @internal    resource_context is not supported
	 * @since       PHP 5
	 * @require     PHP 4.0.1 (trigger_error)
	 */
	if (!function_exists('file_get_contents'))
	{
		function file_get_contents($filename, $incpath = false, $resource_context = null)
		{
			if (false === $fh = fopen($filename, 'rb', $incpath)) {
				trigger_error('file_get_contents() failed to open stream: No such file or directory', E_USER_WARNING);
				return false;
			}
	
			clearstatcache();
			if ($fsize = filesize($filename)) {
				$data = fread($fh, $fsize);
			} else {
				while (!feof($fh)) {
					$data .= fread($fh, 8192);
				}
			}
	
			fclose($fh);
			return $data;
		}
	}
	
	// microtime for PHP 4
	function microtime_float()
	{
	  list($usec, $sec) = explode(" ", microtime());
	  return ((float)$usec + (float)$sec);
	} 
	
/**
 * Returns a list of available languages
 *
 * @author Ben Dodson, Ross Carlson
 * @since 11/5/05
 *
 */
function getLanguageList() {
  global $include_path;
  $lang_dir = $include_path. "lang";
  $retArray = readDirInfo($lang_dir,"file");
  sort($retArray);
  $languages = array();
  for ($c=0; $c < count($retArray); $c++){	
    $entry = $retArray[$c];
    // Let's make sure this isn't the local directory we're looking at 
    if ($entry == "." || $entry == ".." || $entry == "master.php") { continue;}
    if (!stristr($entry,"-setup") and !stristr($entry,".html")){
      if (strrpos($entry,'-') !== false) {
	$languages[substr($entry,0,strrpos($entry,'-'))] = true;
      } else {
	$languages[$entry] = true;
      }
    }
  }
  return array_keys($languages);
}

	/**
	* Translates a word into a native language
	* 
	* 
	* @author Ross Carlson
	* @author Ben Dodson
	* @version 3.22.05
	* @since 3.22.05
	* @param string $word The word to translate
	* @param $term1, $term2, ....: replace the key %s in $word. 
	*/
	function word($word){
		global $words, $jz_lang_file, $include_path;
		
		// Let's hash that word
		$hash = md5($word);
		
		// Now let's make sure there is a translation for this
		if (isset($words[$hash])){
			$ret = $words[$hash];	
		} else {
			// Ok, it didn't so let's log it and return what they put it
			$fileName = $include_path. "temp/jinzora-words-". $jz_lang_file. ".log";		
			if (!is_file($fileName)){touch($fileName);}
			$data = file_get_contents($fileName);
			if (!stristr($data,$word)){
				$handle = @fopen($fileName, "a");
				$data = "$". "words['". $hash. "'] = ". '"'. $word. '";'. "\n";
				@fwrite($handle,$data);				
				@fclose($handle);
			}
			
			// Now let's set up for returning:
			$ret = $word;
		}
		// Replace %s as needed:
		$i = 1;
		while (($index = strpos($ret,'%s')) !== false) {
		  $ret = substr($ret,0,$index) . func_get_arg($i++) . substr($ret,$index+2);
		}
		return $ret;
	}
	
	
	
	// This function will see if there is a thumbnail for the corresponding item
	// Added 3.23.04 by Ross Carlson
	// It returns false or the name of the file
	function searchThumbnail($searchFile){
		global $ext_graphic, $web_root;
		
		$typeArray = explode("|",$ext_graphic);		
		$thumb_file = "";			
		$fileExt = returnFileExt($searchFile);		
		for ($e=0; $e < count($typeArray); $e++){
			$thumbFileName = str_replace(".".$fileExt,".thumb.". $typeArray[$e],$searchFile);
			if (is_file($thumbFileName)){
				$thumb_file =  str_replace("%2F","/",rawurlencode(str_replace($web_root,"",$thumbFileName)));
			}
		}
		
		// Now let's return it
		if ($thumb_file <> ""){
			return $thumb_file;
		} else {
			return false;
		}
	}
	
	/**
	* Display the opening tag for a table
	* 
	* @author Ross Carlson
	* @version 04/28/04
	* @since 04/28/04
	* @param integer $width Width of the table in percent
	* @param integer $cellpadding cellpadding for the table
	* @param string $class the style sheet class for the table
	*/
	function jzTableOpen($width = "100", $cellpadding = "5", $class = "jz_track_table", $widthType = "%"){
		echo '<table class="'. $class. '" width="'. $width. $widthType. '" cellpadding="'. $cellpadding. '" cellspacing="0" border="0">'. "\n";
	}
	
	/**
	* Display the closing tag for a table
	* 
	* @author Ross Carlson
	* @version 04/28/04
	* @since 04/28/04
	*/
	function jzTableClose(){
		echo '</table>'. "\n";
	}
	
	/**
	* open a Table Row
	* 
	* @author Ross Carlson
	* @version 04/28/04
	* @since 04/28/04
	* @param string $class the style sheet class for the table
	*/
	function jzTROpen($class = ""){
		echo '  <tr class="'. $class. '">'. "\n";
	}
	
	/**
	* close a Table Row
	* 
	* @author Ross Carlson
	* @version 04/28/04
	* @since 04/28/04
	*/
	function jzTRClose(){
		echo '  </tr>'. "\n";
	}
	
	/**
	* Display the opening tag for a table detail
	* 
	* @author Ross Carlson
	* @version 04/28/04
	* @since 04/28/04
	* @param integer $width Width of the table in percent
	* @param string $align alignment for the cell
	* @param string $valign verticle alignment for the cell
	* @param string $class the style sheet class for the table
	* @param integer $colspan how many colums should this cell span
	*/
	function jzTDOpen($width = "100", $align = "left", $valign = "top", $class = "", $colspan = "", $widthType = "%"){
		echo '    <td width="'. $width. $widthType. '" align="'. $align. '" valign="'. $valign. '" class="'. $class. '" ';
		if ($colspan <> "0"){
			echo 'colspan="'. $colspan. '"';
		}
		echo '>'. "\n";
	}
	
	/**
	* Display the opening tag for a table detail
	* 
	* @author Ross Carlson
	* @version 04/28/04
	* @since 04/28/04
	*/
	function jzTDClose(){
		echo '    </td>'. "\n";
	}
	
	/**
	* Display an A HREF tag
	* 
	* @author Ross Carlson
	* @version 04/28/04
	* @since 04/28/04
	*/
	function jzHREF($href, $target = "", $class = "", $onclick = "", $item, $title = ""){
		$retVar = '<a ';
		if ($title <> ""){ $retVar .= 'title="'. $title. '" '; }
		if ($class <> ""){ $retVar .= 'class="'. $class. '" '; }
		if ($target <> ""){ $retVar .= 'target="'. $target. '" '; }
		if ($onclick <> ""){ $retVar .= 'onclick="'. $onclick. '" '; }
		$retVar .= 'href="'. $href. '" >'. $item. '</a>';
		
		// Now let's display the link
		echo $retVar;
	}
	
	
	/**
	* returns the truncated name of an item
	* 
	* @author Ross Carlson
	* @version 05/03/04
	* @since 05/03/04
	* @param string $item the item to truncate
	* @param string $length how long should it be?
	*/
	function returnItemShortName($item,$length){
		if (strlen($item) > $length + 3){
			return substr($item,0,$length). "...";
		} else {
			return $item;
		}
	}	
	
	/**
	 * Hightlights (bolds) part of a string to emphasis it
	 * 
	 * @author Ross Carlson (from www.php.net)
	 * @version 01/14/05
	 * @since 01/14/05
	 * @param string $x The Haystack
	 * @param string $var The needle
	 * @return string guessed mime type for thie filename
	 */
	function highlight($x,$var) {//$x is the string, $var is the text to be highlighted
	   if ($var != "") {
		   $xtemp = "";
		   $i=0;
		   while($i<strlen($x)){
			   if((($i + strlen($var)) <= strlen($x)) && (strcasecmp($var, substr($x, $i, strlen($var))) == 0)) {
						//this version bolds the text. you can replace the html tags with whatever you like.
					   $xtemp .= "<b>" . substr($x, $i , strlen($var)) . "</b>";
					   $i += strlen($var);
			   }
			   else {
				   $xtemp .= $x{$i};
				   $i++;
			   }
		   }
		   $x = $xtemp;
	   }
	   return $x;
	} 
	
	/**
	 * for php < 4.3.0
	 * 
	 * @author Laurent Perrin 
	 * @version 02/06/04
	 * @since 02/06/04
	 * @param string $filename file name
	 * @return string guessed mime type for thie filename
	 */
	if (!function_exists("mime_content_type")) {
		function mime_content_type($filename) {
			switch(strrchr($filename,'.')){
				case '.mp3':
				case '.mp2':
				case '.mp1': 
					return 'audio/mpeg';
				case '.wma':
					return 'audio/wma';
				case '.wav':
					return 'audio/x-wav';
				case '.avi':
					return 'video/x-msvideo';
				case '.qt':
				case '.mov':
					return 'video/quicktime';
				case '.mpe':
				case '.mpg':
				case '.mpeg': 
					return 'video/mpeg';;
			} // switch()
			return '';
		}
	}
	
	/**
	 * for php < 4.3.0
	 * 
	 * @author Laurent Perrin 
	 * @version 02/15/04
	 * @since 02/06/04
	 * @param string $filename filename
	 * @param integer $use_include_path use include path
	 * @return string file contents
	 */
	if (!function_exists("file_get_contents")) {
		function file_get_contents($filename, $use_include_path = 0) {
			$data = ''; // just to be safe. Dunno, if this is really needed
			$file = @fopen($filename, "rb", $use_include_path);
			if ($file) {
				$data = fread($file, filesize($filename));
				fclose($file);
			}
			return $data;
		}
	}
	
	/**
	* Updates the track counter for something when it's played
	* 
	* @author Ross Carlson
	* @version 07/26/04
	* @version 07/26/04
	* @param string $data the file name to process
	* @return string the file extension (without the .)
	*/
	function returnFileExt($data){
		$fileInfo = pathinfo($data);
		if (isset($fileInfo["extension"])){
			return $fileInfo["extension"];
		} else {
			return "";
		}
	}
	
	/**
	* Handles array sorting for us
	* 
	* @author Ross Carlson
	*/
	function track_cmp($a, $b){
	   if ($a == $b) {
		   return 0;
	   }
	   return ($a > $b) ? -1 : 1;
	}
	
	
	/**
	* Returns the difference between 2 microtime stamps
	* 
	* @author Ross Carlson
	* @version 06/17/04
	* @since 06/17/04
	* @param string $value What needs to be added to the list
	*/
	function microtime_diff($a, $b) {
	   list($a_dec, $a_sec) = explode(" ", $a);
	   list($b_dec, $b_sec) = explode(" ", $b);
	   return $b_sec - $a_sec + $b_dec - $a_dec;
	}
	
	
	
	/**
	* Sets a session variable with the URL of the current page
	* 
	* @author Ross Carlson
	* @version 05/04/04
	* @since 05/04/04
	* @returns Session variable $_SESSION['prev_page']
	*/
	function setPreviousPage(){		
		// Now let's set the session variable for later
		$_SESSION['prev_page'] = @$_SERVER['REQUEST_URI'];
		// Let's make sure it got set right and if not fix it
		if ($_SESSION['prev_page'] == ""){
			$_SESSION['prev_page'] = $_SERVER["URL"]. "?". $_SERVER["QUERY_STRING"];
		}
	}
		
	/**
	* Returns how wide the columns on the genre/artist page should be
	* 
	* @author Ross Carlson
	* @version 05/03/04
	* @since 05/03/04
	* @param integer $items Number of items that are to be displayed
	* @returns integer returns width 
	*/
	function returnColWidth($items){		
		global $cols_in_genre;
		
		// Let's find out how wide the colums will be in percent 
		$col_width = 100 / $cols_in_genre;

		// Now let's make sure we don't divide by zero (Thanks flavio!)
		if ($items < 1){ $items = 1; }
		
		// Let's make sure we have enough items to fill the number of colums that we wanted (say we wanted 3 cols but we only have 1 item) 
		if ($items < $cols_in_genre){
			// Ok, let's make this a better number 
			$col_width = 100 / $items;
		}
		
		return $col_width;
	}
	
	/**
	* Convert MB to KB
	* 
	* @author Ross Carlson
	* @version 06/28/04
	* @since 06/28/04
	* @param integer $size Size of the item in MB (example 12.27)
	* @returns returns the size in kb (example 12564.48)
	*/
	function convertMBtoKB($size){
		return $size * 1024;
	}
	
	/**
	* Convert MB to KB
	* 
	* @author Ross Carlson
	* @version 06/28/04
	* @since 06/28/04
	* @param integer $size Size of the item in MB (example 12.27)
	* @returns returns the size in kb (example 12564.48)
	*/
	function convertKBtoMB($size){
		return round($size / 1024,2);
	}
	
	/**
	* Converts a long numeric value to time, 69 = 1:09:00
	* 
	* @author Ross Carlson
	* @version 04/29/04
	* @since 04/29/04
	* @param integer $time Amount of time in minutes
	* @returns returns the time in days:hours:minutes:seconds
	*/
	function formatTime($time){
		
		// Let's get the days
		$days=0;
		while($time > (24*60*60)){
			$time = $time - (24*60*60);
			$days++;
		}
		// Now let's get hours
		$hours=0;
		while($time > (60*60)){
			$time = $time - (60*60);
			$hours++;
		}
		// Now let's get minutes
		$mins=0;
		while($time > (60)){
			$time = $time - (60);
			$mins++;
		}
		if ($time < 10){$time = "0". $time;}
		
		return $days. ":". $hours. ":". $mins. ":". $time;
	}
		
	/**
	* Convert minutes to seconds
	* 
	* @author Ross Carlson
	* @version 04/29/04
	* @since 04/29/04
	* @param integer $seconds Number of seconds to convert to minutes
	* @returns returns the time in minutes:seconds
	*/
	function convertMinsSecs($minutes){
		// Let's make sure it was mins:sec
		if (!stristr($minutes,":")){ $minutes = $minutes. ":"; }
		
		// Now let's split it by the :
		$minArray = explode(":",$minutes);
		
		// Now let's create the time
		return ($minArray[0] * 60) + $minArray[1];
	}
	
	/**
	* Convert seconds to minutes
	* 
	* @author Ross Carlson
	* @version 04/29/04
	* @since 04/29/04
	* @param integer $seconds Number of seconds to convert to minutes
	* @returns returns the time in minutes:seconds
	*/
	function convertSecMins($seconds){
		// First let's round it off
		$seconds = round($seconds);
		
		// Now let's loop through subtracking 60 each time
		$ctr=0;
		while ($seconds >= 60){
			$seconds = $seconds - 60;
			$ctr++;
		}
		if ($seconds < 10){
			$seconds = "0". $seconds;
		}
		
		return $ctr. ":". $seconds;
	}
	
	
	// This function will take a filename and return the formated name of the XML (or data) file
	// Added 4.6.04 by Ross Carlson
	// Returns the name of the file, formated, without the leading path (so just as it was sent)
	function returnFormatedFilename($fileName){
	
		//Ok, let's set it
		$fileName = str_replace("/","---",$fileName);
		
		// Now let's make sure we don't have any files beginning with ---
		while (substr($fileName,0,3) == "---"){
			$fileName = substr($fileName,3,strlen($fileName));
		}

		return $fileName;
	}
		
	// This function forces the browser to display output
	function flushDisplay() {
		global $cms_type;
		
		if ($cms_type <> "mambo" && $cms_type <> "xoops"){
			@ob_flush(); 
			@flush();
		}
		print str_repeat(" ", 4096);	// force a flush
	}
	
	
	// This function just returns the directories for us
	function readJustDirs($dirName, &$readCtr, &$mainArray){
		global $web_root, $root_dir, $media_dir;
		// Let's up the max_execution_time
		ini_set('max_execution_time','600');

		// Let's look at the directory we are in		
		if (is_dir($dirName)){
			$d = dir($dirName);
			while($entry = $d->read()) {
				// Let's make sure we are seeing real directories
				if ($entry == "." || $entry == "..") { continue;}
				// Now let's read this IF it's just a folder
				if (is_dir($dirName. "/". $entry)){
					$mainArray[$readCtr] = str_replace($web_root. $root_dir. $media_dir. "/", "",$dirName. "/". $entry);
					$readCtr++;	
					readJustDirs($dirName. "/". $entry, $readCtr, $mainArray);
				}
			}
			// Now let's close the directory
			$d->close();
			
			// Now let's sort that array
			@sort($mainArray);
		}		
		// Ok, let's return the data
		return $mainArray;
		
	}
		
	// This function takes a directory and get's all sub directories and files and puts them in an array
	function readAllDirs($dirName, &$readCtr, &$mainArray, $searchExt = "false", $displayProgress = "false"){
		global $audio_types, $video_types;
		
		// Let's up the max_execution_time
		ini_set('max_execution_time','600');
		
		// Let's look at the directory we are in		
		if (is_dir($dirName)){
			$d = dir($dirName);
			while($entry = $d->read()) {
				// Let's make sure we are seeing real directories
				if ($entry == "." || $entry == "..") { continue;}
				
				// Now let's see if we are looking at a directory or not
				if (filetype($dirName. "/". $entry) <> "file"){
					// Ok, that was a dir, so let's move to the next directory down
					readAllDirs($dirName. "/". $entry, $readCtr, $mainArray, $searchExt, $displayProgress);
				} else {
					// Let's see if they wanted status
					if ($displayProgress == "true"){
						if ($readCtr % 50 == 0){ echo '.'; flushDisplay();}
					}
					// Let's see if we want to search a specfic extension or not
					if ($searchExt == "false"){
						// Ok, we found files, let's make sure they are audio or video files
						if (preg_match("/\.($audio_types)$/i", $entry) or preg_match("/\.($video_types)$/i", $entry) ) {
							$mainArray[$readCtr] = $dirName. "/". $entry;
							$readCtr++;
						}
					} else {
						if (stristr($entry, $searchExt) or $searchExt == "true"){
							$mainArray[$readCtr] = $dirName. "/". $entry;
							$readCtr++;
						}
					}
				}			
			}
			// Now let's close the directory
			$d->close();
			
			// Now let's sort that array
			@sort($mainArray);
		}		
		// Ok, let's return the data
		return $mainArray;
	}

	function readAllDirs2($dirName, &$readCtr){
		global $audio_types, $video_types;
		
		// Let's up the max_execution_time
		ini_set('max_execution_time','6000');
		// Let's look at the directory we are in		
		if (@is_dir($dirName)){
			$d = @dir($dirName);
			if (@is_object($d)){
				while($entry = $d->read()) {
					// Let's make sure we are seeing real directories
					if ($entry == "." || $entry == "..") { continue;}
					if ($readCtr % 100 == 0){ 
						?>
						<script language="javascript">
							fc.innerHTML = '<b><?php echo word("%s files analyzed.",$readCtr); ?></b>';									
							-->
						</SCRIPT>
						<?php 
						@flush(); @ob_flush();
					}
					// Now let's see if we are looking at a directory or not
					if (filetype($dirName. "/". $entry) <> "file"){
						// Ok, that was a dir, so let's move to the next directory down
						readAllDirs2($dirName. "/". $entry, $readCtr);
					} else {
						if (preg_match("/\.($audio_types|$video_types)$/i", $entry)){
							$readCtr++;
							$_SESSION['jz_full_counter']++;
						}							
					}			
				}
				// Now let's close the directory
				$d->close();
			}
		}		
	}

	
	// This function makes sure that the variable is TOTALLY clean of slashes
	function jzstripslashes($variable){
		// Lets loop through until the variable is clean
		while (stristr($variable,"\\") <> ""){
			$variable = stripslashes($variable);
		}
		
		while (stristr($variable,"//") <> ""){
			$variable = str_replace("//","/",($variable));
		}
		// Now let's send the clean variable back
		return $variable;
	}

	// This function reads the directory specifed and returns the results into a sorted array */
	function readDirInfo($dirName, $type){
	
		// Let's up the max_execution_time
		ini_set('max_execution_time','600');
		
		$retArray = array();
		// First let's make sure this is really a dir...
		if (is_dir($dirName)){
			$d = dir($dirName);
			while($entry = $d->read()) {
				// Let's make sure this isn't the local directory we're looking at
				if ($entry == "." || $entry == ".." || $entry == "CVS") { continue;}
				
				// Let's see if they wanted to look for a directory or a file and add that to the array
				if ($type == "dir" and (filetype($dirName. "/". $entry) == "dir" or filetype($dirName. "/". $entry) == "link")){
					$retArray[] = $entry;
				}
				if ($type == "file" and stristr($entry,".") <> "" and $entry <> ""){
					$retArray[] = $entry;
				}
			}
			$d->close();
			
			// Let's make sure we found something, and if we didn't let's not sort an empty array
			if (count($retArray) <> 0){
				sort($retArray);
			}
		}
		
		/* Now let's return the array to them */
		return $retArray;
	}
	
	
	function userAuthenticate($username){
		global $this_site, $web_root, $root_dir, $media_dir, $cms_user_access, $default_access, $include_path, $jzUSER;
		
		// Now let's authenticate this user
		$jzUSER = new jzUser();
		if ($username == "anonymous") {
			$username = NOBODY;
		}

		return $jzUSER->login($username, "cms-user", false);
	}
	
	function check_for_numerics($str) {
		for ($i = 0; $i < strlen($str); $i++) {
			if (is_numeric($str[$i]))
			{
			return (boolean) TRUE;
			}
		}
	}
	
	// This function will write out error messages to the log files...
	function writeLogData($logName, $data){
		global $include_path, $enable_logging, $log_max_size_kb, $jzUSER;
		
		if ($enable_logging == "false"){return;}

		// Let's see what file they wanted to open and open it up! 
		$fileName = $include_path. "temp/" . $logName . ".log";		
		if (!is_file($fileName)){
			@touch($fileName);
		}
		
		// First let's make sure we're not over the max size
		/*
		if (filesize($fileName) > $log_max_size_kb){
			// Ok, we need to truncate it
			$data = file($fileName);
			$truncate = (count($data) * .25);
			for ($i=0; $i<count($data); $i++) {
				if ($i > $truncate){
					$nData[] = substr($data[$i],0,strlen($data[$i])-1);
				}
			}
			$data = implode("\n",$nData);
			
			//$handle = @fopen($fileName, "w");
			//@fwrite($handle,$data);				
			//@fclose($handle);		
		}
		*/
		
		if (isset($jzUSER)){
			$user = $jzUSER->getName();
		} else {
			$user = "anon";
		}
		
		// Let's get the microseconds
		$lTime = explode(" ",microtime());
		$msec = substr($lTime[0],2,2);
		
		$handle = @fopen($fileName, "a");
		$data = date("n/j/y g:i:s",time()). ".". $msec. ", user:". $user. ", ". $data. "\n";
		@fwrite($handle,$data);				
		@fclose($handle);			
      
	}
	
	// This function returns the installed GD version
	function gd_version() {
		static $gd_version_number = null;
		if ($gd_version_number === null) {
			ob_start();
			phpinfo(INFO_MODULES);
			$module_info = ob_get_contents();
			ob_end_clean();
			if (preg_match("/\bgd\s+version\b[^\d\n\r]+?([\d\.]+)/i",$module_info,$matches)) {
				$gd_version_number = $matches[1];
			} else {
				$gd_version_number = 0;
			}
		}
		return $gd_version_number;
	} 
	
	/* This function will resize the JPEG's we pass into the site for us and produce PNG's */
	function resizeImage($source_image, $destination_image, $destination_width, $destination_height){
		global $keep_porportions;
		
		// First we need to see if GD is installed or not...
		if (gd_version() == 0){
			// Ok, no GD, let's write that to the log...
			writeLogData('error','Sorry, GD Libraries not found while trying to resize an image...');
			return false;
		}
		
		/* get the picture and set the output picture */
		$image = $source_image;
		$new_image = $destination_image;
		
		/* Let's grab the source image that was uploaded to work with it */
		$src_img = @imagecreatefromjpeg($image);
		
		if ($src_img <> ""){
			/* Let's get the width and height of the source image */
			$src_width = imagesx($src_img); $src_height = imagesy($src_img);
			
			/* Let's set the width and height of the new image we'll create */
			$dest_width = $destination_width; $dest_height = $destination_height;
			
			/* Now if the picture isn't a standard resolution (like 640x480) we
			   need to find out what the new image size will be by figuring
			   out which of the two numbers is higher and using that as the scale */
			// First let's make sure they wanted to keep the porportions or not
			if ($keep_porportions == "true"){
				if ($src_width > $src_height){
					/* ok so the width is the bigger number so the width doesn't change
					   We need to figure out the percent of change by dividing the source width
					   by the dest width */
					$scale = $src_width / $destination_width;
					$dest_height = $src_height / $scale;
				} else {
					/* ok so the width is the bigger number so the width doesn't change
					   We need to figure out the percent of change by dividing the source width
					   by the dest width */
					$scale = $src_height / $destination_height;
					$dest_width = $src_width / $scale;
				}
			} else {
				$dest_height = $destination_height;
				$dest_width = $destination_width;
			}
			
			/* Now let's create our destination image with our new height/width */
			if (gd_version() >= 2) {
				$dest_img = imageCreateTrueColor($dest_width, $dest_height);
			} else {
				$dest_img = imageCreate($dest_width, $dest_height);
			}
			
			/* Now let's copy the data from the old picture to the new one witht the new settings */
			if (gd_version() >= 2) {
				imageCopyResampled($dest_img, $src_img, 0, 0, 0 ,0, $dest_width, $dest_height, $src_width, $src_height);
			} else {
				imageCopyResized($dest_img, $src_img, 0, 0, 0 ,0, $dest_width, $dest_height, $src_width, $src_height);
			}
			
			/* Now let's create our new image */
			@imagejpeg($dest_img, $new_image);
			
			/* Now let's clean up all our temp images */
			imagedestroy($src_img);
			imagedestroy($dest_img);
			
			return true;
		} else {
			return false;
		}
	}	

	
	function createBlankImage($image, $font, $text, $color, $shadow, $drop, $maxwidth, $alignment, $valign, $padding="5"){
		global $web_root, $root_dir;
		
		// First we need to see if GD is installed or not...
		if (gd_version() == 0){
			// Ok, no GD, let's write that to the log...
			writeLogData('error','Sorry, GD Libraries not found!');
			return false;
		}
		
		/* Now let's create our destination image with our new height/width */		
		$src_img = imagecreatefromjpeg($image);
		if (gd_version() >= 2) {
			$dest_img = imageCreateTrueColor($maxwidth, $maxwidth);
		} else {
			$dest_img = imageCreate($maxwidth, $maxwidth);
		}

		// decode color arguments and allocate colors
		$color_args = explode (' ',$color);
		$color = imagecolorallocate($dest_img, $color_args[0], $color_args[1], $color_args[2]);
		$shadow_args = explode (' ',$shadow);
		$shadow = imagecolorallocate($dest_img, $shadow_args[0], $shadow_args[1], $shadow_args[2]);

		/* Let's get the width and height of the source image */
		$src_width = imagesx($src_img); $src_height = imagesy($src_img);

		/* Now let's copy the data from the old picture to the new one witht the new settings */
		if (gd_version() >= 2) {
			imageCopyResampled($dest_img, $src_img, 0, 0, 0 ,0, $maxwidth, $maxwidth, $src_width, $src_height);
		} else {
			imageCopyResized($dest_img, $src_img, 0, 0, 0 ,0, $maxwidth, $maxwidth, $src_width, $src_height);
		}

		/* Now let's clean up our temp image */
		imagedestroy($src_img);

		$fontwidth = ImageFontWidth($font);
		$fontheight = ImageFontHeight($font);

		$margin = floor($padding + $drop)/2; // So that shadow is not off image on right align & bottom valign

		if ($maxwidth != NULL) {
			$maxcharsperline = floor( ($maxwidth - ($margin * 2)) / $fontwidth);
			$text = wordwrap($text, $maxcharsperline, "\n", 1);
		}
		
		$lines = explode("\n", $text);
		
		switch($valign){
		
		 case "center":
		  $y = (imageSY($dest_img) - ($fontheight * sizeof($lines)))/2;
		  break;
		
		 case "bottom":
		  $y = imageSY($dest_img) - (($fontheight * sizeof($lines)) + $margin);
		  break;
		
		 default:
		  $y = $margin;
		  break;
		}
		
		switch($alignment){
			 case "right":
			   while (list($numl, $line) = each($lines)) {
					 ImageString($dest_img, $font, (imagesx($dest_img) - $fontwidth*strlen($line))-$margin+$drop, ($y+$drop), $line, $shadow);
					 ImageString($dest_img, $font, (imagesx($dest_img) - $fontwidth*strlen($line))-$margin, $y, $line, $color);
					 $y += $fontheight;
			   }
			 break;
			
			 case "center":
			   while (list($numl, $line) = each($lines)) {
					 ImageString($dest_img, $font, floor((imagesx($dest_img) - $fontwidth*strlen($line))/2)+$drop, ($y+$drop), $line, $shadow);
					 ImageString($dest_img, $font, floor((imagesx($dest_img) - $fontwidth*strlen($line))/2), $y, $line, $color);
					 $y += $fontheight;
			   }
			 break;
			
			 default:
			   while (list($numl, $line) = each($lines)) {
				ImageString($dest_img, $font, $margin+$drop, ($y+$drop), $line, $shadow);
				ImageString($dest_img, $font, $margin, $y, $line, $color);
				$y += $fontheight;
			   }
			 break;
			}
		
		/* Now let's create our new image */
		$new_image = $web_root. $root_dir. "/temp/temp-image.jpg";
		@touch($new_image);
		
		// Now let's make sure that new image is writable
		if (is_writable($new_image)){
			imagejpeg($dest_img, $new_image);
		
			/* Now let's clean up our temp image */
			imagedestroy($dest_img);
			
			return true;
		} else {
			echo "Sorry, I couldn't open the temporary image file for writing.<br>".
				 "looks like something is wrong with the permissions on your temp directory at:<br><br>".
				 $web_root. $root_dir. "/temp<br><br>".
				 "Sorry about that, but this is a fatal error!<br><br>".
				 "You could turn off auto art searching in settings.php by changing<br><br>".
				 '$search_album_art = "false";';
			exit();
			return false;
		}
		
	}
	
	
	// This function will resize the album images
	function jzResizeAlbum($dirToSearch, $artistImage){
		global $album_img_width, $album_img_height;
			
		// Now let's see if the artist image needs to be resized
		if ($album_img_width <> "0" and $album_img_height <> "0"){
			// Now let's get the image dimensions and see if it needs resizing
			$imgFile = $dirToSearch. "/". $artistImage;
			$imgDst = $dirToSearch. "/". $artistImage. ".new";
			$imgDim = getimagesize($imgFile);
			$imgWidth = $imgDim[0];
			$imgHeight = $imgDim[1];
			
			// Now let's see if either is bigger or smaller than what we want
			if ($imgHeight <> $album_img_height && $imgWidth <> $album_img_width){
				// Ok, we need to change the height of the image
				if (resizeImage($imgFile, $imgDst, $album_img_width, $album_img_height) == true){
					// Now let's make sure there's not another old file, because we don't want to replace it
					if (!is_file($imgFile. ".old")){
						// Now let's backup the old file
						@rename ($imgFile, $imgFile. ".old");
					}
					// Now let's put the new file in place
					@rename ($imgDst, $imgFile);						
				}
			}
		}
	}
	
	// This function will resize the artist images
	function jzResizeArtist($dirToSearch, $Image){
		global $artist_img_width, $artist_img_height;
			
		// Now let's see if the artist image needs to be resized
		if ($artist_img_width <> "0" and $artist_img_height <> "0"){
			// Now let's get the image dimensions and see if it needs resizing
			$imgFile = $dirToSearch. "/". $Image;
			$imgDst = $dirToSearch. "/". $Image. ".new";
			$imgDim = getimagesize($imgFile);
			$imgWidth = $imgDim[0];
			$imgHeight = $imgDim[1];	
			
			// Now let's see if either is bigger or smaller than what we want
			if ($imgHeight <> $artist_img_height && $imgWidth <> $artist_img_width){
				// Ok, we need to change the height of the image
				if (resizeImage($imgFile, $imgDst, $artist_img_width, $artist_img_height) == true){
					// Now let's make sure there's not another old file, because we don't want to replace it
					if (!is_file($imgFile. ".old")){
						// Now let's backup the old file
						@rename ($imgFile, $imgFile. ".old");
					}
						// Now let's put the new file in place
						@rename ($imgDst, $imgFile);
				}						
			}
		}
	}

	function deldir($dir){
		$current_dir = opendir($dir);
		while($entryname = readdir($current_dir)){
			if(is_dir("$dir/$entryname") and ($entryname != "." and $entryname!="..")){
				deldir("${dir}/${entryname}");
			}elseif($entryname != "." and $entryname!=".."){
				unlink("${dir}/${entryname}");
			}
		}
		closedir($current_dir);
		rmdir($dir);
	}
	
	

	// simple function that can help, if you want to know if a string could be UTF-8 or not
	function seems_utf8($Str) {
		for ($i=0; $i<strlen($Str); $i++) {
			if (ord($Str[$i]) < 0x80) continue; # 0bbbbbbb
			elseif ((ord($Str[$i]) & 0xE0) == 0xC0) $n=1; # 110bbbbb
			elseif ((ord($Str[$i]) & 0xF0) == 0xE0) $n=2; # 1110bbbb
			elseif ((ord($Str[$i]) & 0xF8) == 0xF0) $n=3; # 11110bbb
			elseif ((ord($Str[$i]) & 0xFC) == 0xF8) $n=4; # 111110bb
			elseif ((ord($Str[$i]) & 0xFE) == 0xFC) $n=5; # 1111110b
			else return false; # Does not match any model
			for ($j=0; $j<$n; $j++) { # n bytes matching 10bbbbbb follow ?
				if ((++$i == strlen($Str)) || ((ord($Str[$i]) & 0xC0) != 0x80))
					return false;
			}
		}
		return true;
	}

/*
 * Returns the content range (in bytes) of the request.
 * If the range header is not set, false is returned.
 * @author Ben Dodson
 * @since 6/28/06
 * @version 6/28/06
 * @return array(from,to) or false, if the RANGE header is not set. 
 */
	function getContentRange($size) {
		$from = 0; $to = $size-1;
		
		if (isset($_SERVER['HTTP_RANGE'])) {
			$split = explode("=",$_SERVER['HTTP_RANGE']);
			if (trim($split[0]) == "bytes") {
				if ($split[1][0] == '-') {
					if ($size !== false) {
						$val = trim(substr($split[1], 1));
						$from = $size - $val - 1; // TODO: VERIFY THE -1 HERE
					}
				}
				if (strpos($split[1], '-') !== false) {
					$split2 = explode("-",$split[1]);
					if (isset($split2[1]) && !isNothing($split2[1])) {
						$to = trim($split2[1]);
					}
					
					$from = trim($split2[0]);
				} else {
					$from = trim($split[1]);
				}
				       			
       			if(empty($to)) {
           			$to = $size - 1;  // -1  because end byte is included
                     		          //(From HTTP protocol:
									 // 	'The last-byte-pos value gives the byte-offset of the 
									// last byte in the range; that is, the byte positions specified are inclusive')
       			}
       			
       			return array($from,$to);
			}
		}
		return false;
	}
	
?>
