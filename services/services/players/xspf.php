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
	* Code Purpose: Processes data for the jlGui embedded Java Player
	* Created: 03.03.05 by Ross Carlson
	*
	* * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * * */

	define('SERVICE_PLAYERS_xspf','true');

	/**
	* Returns the player width
	* 
	* @author Ben Dodson
	* @version 8/23/05
	* @since 8/23/05
	*/
	function SERVICE_RETURN_PLAYER_WIDTH_xspf(){
	  return 440;
	}

	/**
	* Returns the players height.
	* 
	* @author Ben Dodson
	* @version 8/23/05
	* @since 8/23/05
	*/
	function SERVICE_RETURN_PLAYER_HEIGHT_xspf(){
	  return 205;
	}

	
	/**
	* Returns the data for the form posts for the player
	* 
	* @author Ross Carlson
	* @version 06/05/05
	* @since 06/05/05
	* @param $formname The name of the form that is being created
	*/
	function SERVICE_RETURN_PLAYER_FORM_LINK_xspf($formname){
		return "document.". $formname. ".target='embeddedPlayer'; openMediaPlayer('', 440, 205);";
	}
	
	
	/**
	* Returns the data for the href's to open the popup player
	* 
	* @author Ross Carlson
	* @version 06/05/05
	* @since 06/05/05
	*/
	function SERVICE_RETURN_PLAYER_HREF_xspf(){
		return ' target="embeddedPlayer" onclick="openMediaPlayer(this.href, 440, 205); return false;"';
	}
	

	/**
	* Actually displays this embedded player
	* 
	* @author Ross Carlson
	* @version 3/03/05
	* @since 3/03/05
	* @param $list an array containing the tracks to be played
	*/
	function SERVICE_DISPLAY_PLAYER_xspf($width, $height){
		global $root_dir, $this_site, $css;
		
		?>

		 <?php
		     if (!isset($_SERVER['HTTP_REFERER']) || 
			 (false === strpos($_SERVER['HTTP_REFERER'],$_SERVER['SERVER_NAME'])) &&
			 (false === strpos($_SERVER['HTTP_REFERER'],$_SERVER['SERVER_ADDR']))
			 ) {
		       // the popup is not resizable.		       
		       $d = new jzDisplay();
		       $d->displayJavascript();
		       ?>
		       <script type="text/javascript">
			 win=openMediaPlayer(window.location, <?php echo $width; ?>, <?php echo $height; ?>);
		       if (win) {
			 //self.close();

		       } else {
			 // popup fail
			 this.href=window.location;
			 document.write('<a href="#" <?php echo SERVICE_RETURN_PLAYER_HREF_xspf(); ?>>Click here to open media player.</a>');
		       }
			 </script>
		       <?php
			     exit();
			     
		     }
		?>
		<SCRIPT LANGUAGE=JAVASCRIPT TYPE="TEXT/JAVASCRIPT"><!--\
		   if (window.name == 'embeddedPlayer' && (window.innerWidth != <?php echo $width ?> || window.innerHeight != <?php echo $height ?>) ) {
		  window.resizeTo(<?php echo $width; ?>,<?php echo $height; ?>)
		}
		
		-->
		</SCRIPT>




		<?php	
		
		// Let's setup the page
		echo '<title>Jinzora XSPF Media Player</title>';
		echo '<body leftmargin="0" topmargin="0" rightmargin="0" bottommargin="0" bgcolor="#000000">';

		$playlist = $this_site. $root_dir. "/temp/playlist.xspf?". time();
		$height = $height - 45;
		?>
		<object 
			classid="clsid:d27cdb6e-ae6d-11cf-96b8-444553540000" 
			codebase="http://fpdownload.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=7,0,0,0" 
			width="440" 
			height="<?php echo $height; ?>" 
			id="xspf_player" 
			align="middle">
			<param name="allowScriptAccess" value="sameDomain" />
			<param name="movie" value="<?php echo $this_site. $root_dir; ?>/services/services/players/xspf_player.swf?autoplay=true&autoload=true&playlist_url=<?php echo $this_site. $root_dir; ?>/temp/playlist.xspf" />
			<param name="quality" value="high" />
			<param name="bgcolor" value="#e6e6e6" />
			<embed src="<?php echo $this_site. $root_dir; ?>/services/services/players/xspf_player.swf?autoplay=true&autoload=true&playlist_url=<?php echo $this_site. $root_dir; ?>/temp/playlist.xspf" 
				quality="high" 
				bgcolor="#e6e6e6" 
				width="440" 
				height="<?php echo $height; ?>" 
				name="xspf_player" 
				align="middle" 
				allowScriptAccess="sameDomain" 
				type="application/x-shockwave-flash" 
				pluginspage="http://www.macromedia.com/go/getflashplayer" />
		</object>

		<?php
		exit();
	}
	
	/**
	* Processes data for the jlGui embedded player
	* 
	* @author Ross Carlson
	* @version 3/03/05
	* @since 3/03/05
	* @param $list an array containing the tracks to be played
	*/
	function SERVICE_OPEN_PLAYER_xspf($list){
		global $include_path, $root_dir, $this_site;
	
		$display = new jzDisplay();

		// Let's set the name of this player for later
		$player_type = "xspf";		
				
		// Now let's loop through each file
		$list->flatten();

		$output_content = '<?xml version="1.0" encoding="UTF-8"?>'. "\n";
		$output_content .= '<playlist version="1" xmlns = "http://www.jinzora.org">'. "\n";
		$output_content .= '  <trackList>'. "\n";
		
		// Now let's loop throught the items to create the list
		foreach ($list->getList() as $track) {
			// Should we play this?
			if ((stristr($track->getPath("String"),".lofi.") 
				or stristr($track->getPath("String"),".clip."))
				and $_SESSION['jz_play_all_tracks'] <> true){continue;}
				
			// Let's get the meta
			$meta = $track->getMeta();
			
			// Let's get the art
			$parent = $track->getParent();
			if (($art = $parent->getMainArt("150x150")) !== false) {
			  $image = jzCreateLink($art,"image");
			} else {
			  $image = $this_site. $root_dir. "/style/images/default.jpg";
			}
			
			$output_content .= '    <track>'. "\n";
			$output_content .= '      <location>'. $track->getFileName("user"). '</location>'. "\n";
			$output_content .= '      <image>'. $image. '</image>'. "\n";
			$output_content .= '      <annotation>'. $meta['artist']. " - ". $meta['title']. '</annotation>'. "\n";
			$output_content .= '    </track>'. "\n";
		}

		// Now let's finish up the content
		$output_content .= '  </trackList>'. "\n";
		$output_content .= '</playlist>';
		
		// Now that we've got the playlist, let's write it out to the disk
		$plFile = $include_path. "temp/playlist.xspf";
		@unlink($plFile);
		$handle = fopen ($plFile, "w");
		fwrite($handle,$output_content);				
		fclose($handle);
			
		// Now let's display
		$width = "445";
		$height = "250";
		SERVICE_DISPLAY_PLAYER_xspf($width, $height);
	}	
?>