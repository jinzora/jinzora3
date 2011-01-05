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
	* - This is the GD2 image resize service
	*
	* @since 04.05.05
	* @author Ben Dodson <ben@jinzora.org>
	* @author Ross Carlson <ross@jinzora.org>
	*/
	
	$jzSERVICE_INFO = array();
	$jzSERVICE_INFO['name'] = "GD2 Image Resizing";
	$jzSERVICE_INFO['url'] = "http://www.boutell.com/gd/";
	
	define('SERVICE_IMAGES_gd2','true');
	
	
	/**
	* Rotates an image
	* 
	* @author Ross Carlson
	* @version 4/16/05
	* @since 4/15/05
	* @param $image string the FULL path to the image to rotate
	*/
	function SERVICE_ROTATE_IMAGE_gd2($image, $node){
		global $allow_filesystem_modify, $include_path;
		
		// Let's make sure they have GD installed
		if (gd_version() < 2) { return false; }
		
		// Well since we can't rotate an ID3 image let's see if it's ID3
		if (stristr($image,"ID3:")){
			// Now let's make a file out of this data
			$jzSERVICES = new jzServices();
			$jzSERVICES->loadStandardServices();
			
			// Now let's fix the path
			$path = substr($image,4);
			$meta = $jzSERVICES->getTagData($path);
			
			// Now let's create a file
			$file = $include_path. 'data/images/'. str_replace("/","--",$path);
			$handle = fopen($file, "wb");
			fwrite($handle,$meta['pic_data']);				
			fclose($handle);
			
			// Now let's make this the image for the node
			$node->addMainArt($file);
			
			// Now let's update the name so we can rotate
			$image = $file;
		}
		
		// First we have to delete any resized versions of this
		$files = readDirInfo($include_path. 'data/images', "file");
		foreach ($files as $file){
			if (stristr($file,str_replace($include_path. 'data/images/',"",substr($image,0,-4)))){
				@unlink($file);
			}
		}
		
		// Now let's set our images
		$source = @imagecreatefromjpeg($image);
		$rotate = @imagerotate($source, 90, 0);
		@imagejpeg($rotate,$image);
		
	}
	
	/**
	* Creates resized images for a single image
	* 
	* @author Ross Carlson
	* @version 4/05/05
	* @since 4/05/05
	* @param $image string the FULL path to the image to resize
	* @param $dimensions string The size of the image to create
	* @return returns true or false if the image was resized properly (boolean)
	*/
	function SERVICE_CREATE_IMAGE_gd2($image, $dimensions, $text, $imageType = "audio", $force_create = "false") {
		global $include_path, $create_blank_art;
		
		// Let's make sure they have GD installed
		if (gd_version() < 2) { return false; }
		
		// Now should we just leave?
		if ($create_blank_art == "false" and $force_create == "false"){ return false; }
		
		// First let's figure out the name for the image
		$iArr = explode("/",$image);
		$imgName = $iArr[count($iArr)-1];
		unset($iArr[count($iArr)-1]);
		$name = substr(implode("--",$iArr),2);
		$path = $include_path. "data/images/". $name;
		$image = $path. md5($imgName. $dimensions). ".jpg";
		
		// Let's get the dimensions
		$dest_width = 200; 
		$dest_height = 200;
		
		// Let's setup some things for below
		$drop = 0;
		$shadow = 0;
		$font = 5;
		$maxwidth = 200;
		$truncate = 38;
		
		// Now let's see if we're too long...
		if (strlen($text) > $truncate + 3){$text = substr($text,0,$truncate). "...";}
		
		// Ok, we now have the path let's create it
		switch ($imageType){
			case "audio":
				$origImageName = "default.jpg";
			break;
			case "video":
				$origImageName = "video.jpg";
			break;			
		}
		$src_img = imagecreatefromjpeg($include_path. "style/images/". $origImageName);
		$dest_img = imageCreateTrueColor($dest_width, $dest_height);

		// decode color arguments and allocate colors
		$color = imagecolorallocate($dest_img, 255, 255, 255);
		$shadow = imagecolorallocate($dest_img, 0, 0, 0);

		// Let's get the width and height of the source image
		$src_width = imagesx($src_img); 
		$src_height = imagesy($src_img);

		/* Now let's copy the data from the old picture to the new one witht the new settings */
		imageCopyResampled($dest_img, $src_img, 0, 0, 0 ,0, $dest_width, $dest_height, $src_width, $src_height);
		
		// Now let's clean up our temp image
		imagedestroy($src_img);

		// Let's setup the font
		$fontwidth = ImageFontWidth(5);
		$fontheight = ImageFontHeight(5);
		// So that shadow is not off image on right align & bottom valign
		$margin = floor(5 + 0)/2; 
		if ($maxwidth != NULL) {
			$maxcharsperline = floor( ($dest_width - ($margin * 2)) / $fontwidth);
			$text = wordwrap($text, $maxcharsperline, "\n", 1);
		}		
		$lines = explode("\n", $text);
		
		// Now let's setup the alignment
		$y = ((imageSY($dest_img) - ($fontheight * sizeof($lines)))/2) + 50;
		while (list($numl, $line) = each($lines)) {
			ImageString($dest_img, $font, floor((imagesx($dest_img) - $fontwidth*strlen($line))/2)+$drop, ($y+$drop), $line, $shadow);
			ImageString($dest_img, $font, floor((imagesx($dest_img) - $fontwidth*strlen($line))/2), $y, $line, $color);
			$y += $fontheight;
		}

		// Now let's create our new image
		@touch($image);
		
		// Now let's make sure that new image is writable
		if (is_writable($image)){
			// Let's create it
			imagejpeg($dest_img, $image);		
			// Now let's clean up our temp image
			imagedestroy($dest_img);
			
			// Now we need to resize this
			return SERVICE_RESIZE_IMAGE_gd2($image,$dimensions);
		} else {
			return false;
		}
	}
	
	
	/**
	* Creates resized images for a single image
	* 
	* @author Ross Carlson
	* @version 4/05/05
	* @since 4/05/05
	* @param $image string the FULL path to the image to resize
	* @param $dimensions string The size of the image to create
	* @return the name of the resized image if resize succeeded, or the original filename if it failed
	*/
	function SERVICE_RESIZE_IMAGE_gd2($image, $dimensions, $dest = false, $imageType = "audio") {
		global $include_path, $keep_porportions, $resize_images;

		// Let's make sure they have GD installed
		if (gd_version() < 2) { return $image; }
		
		// Now let's make sure this is a JPG
		#if (!stristr($image,".jpg") and !stristr($image,"ID3:")){return $image;}
		if ( !(stristr($image,".jpg") || stristr($image, ".gif") )  and !stristr($image,"ID3:")){return $image;}

		// Should we do this at all?
		if ($resize_images == "false"){return $image;}
		
		// Ok, let's get on with it...
		// First let's create our filenames
		$iArr = explode("/",$image);
		$imgName = $iArr[count($iArr)-1];
		
		// Now let's see where this is gonna go
		unset($iArr[count($iArr)-1]);
		unset($iArr[0]);
		$name = implode("--",$iArr);
		$path = $name. "--";

		if ($dest){
			$destImage = $dest;
		} else {
			$destImage = $path. str_replace(".jpg","",$imgName);
			$destImage = "data/images/". md5($destImage). ".". $dimensions. ".jpg";
		}

		// Now let's create the images IF they don't exist
		if (!is_file($destImage)){
			return createImage($image,$destImage,$dimensions);
		} else {
			return $destImage;
		}
	}
	
	
	function createImage($source, $destination, $dimensions){
		global $keep_porportions, $include_path, $jzSERVICES;
		
		// Ok, let's make sure the dimensions aren't empty
		if (!$dimensions){return;}
		
		// Ok, first we need to see if this is an ID3 image and if so write it to a temp file
		if (strstr($source,"ID3:")){
			// Now let's get the data from the ID3 tag so we can write it out
			$path = substr($source,4);
			$meta = $jzSERVICES->getTagData($path);
			
			// Now let's create the new image file
			$source = $include_path. "temp/tempimage.jpg";
			$handle = fopen($source, "wb");
			fwrite($handle,$meta['pic_data']);				
			fclose($handle);
			if (sizeof($destination)>=5){
			   $destination = substr($destination,0,-4). ".". $dimensions. ".jpg";
			} else {
			   $destination = substr($destination,0). ".". $dimensions. ".jpg";
			}
			
		}
		
		// Let's grab the source image to work with it
		if (($src_img = @imagecreatefromjpeg($source)) == false){ 
			$gdInfo = gd_info();
			$gdGifSupport = $gdInfo["GIF Read Support"];
			if( !$gdGifSupport ) { return $source; }

			if (($src_img = @imagecreatefromgif($source)) == false){
				// We couldn't resize, so let's just return the original image
				return $source;
			}
		}

		if ($src_img <> ""){
			// Let's get the width and height of the source image
			$src_width = imagesx($src_img); 
			$src_height = imagesy($src_img);
			
			// Let's set the width and height of the new image we'll create
			$destArr = explode("x",$dimensions);
			$dest_width = $destArr[0]; 
			$dest_height = $destArr[1];
			
			// Now if the picture isn't a standard resolution (like 640x480) we
			// need to find out what the new image size will be by figuring
			// out which of the two numbers is higher and using that as the scale
			// First let's make sure they wanted to keep the porportions or not
			if ($keep_porportions == "true"){
				if ($src_width > $src_height){
					/* ok so the width is the bigger number so the width doesn't change
					   We need to figure out the percent of change by dividing the source width
					   by the dest width */
					$scale = $src_width / $dest_width;
					$dest_height = $src_height / $scale;
				} else {
					/* ok so the width is the bigger number so the width doesn't change
					   We need to figure out the percent of change by dividing the source width
					   by the dest width */
					$scale = $src_height / $dest_height;
					$dest_width = $src_width / $scale;
				}
			}
			
			// Now let's create our destination image with our new height/width
			$dest_img = imageCreateTrueColor($dest_width, $dest_height);
			
			// Now let's copy the data from the old picture to the new one witht the new settings
			imageCopyResampled($dest_img, $src_img, 0, 0, 0 ,0, $dest_width, $dest_height, $src_width, $src_height);
			
			// Now let's create our new image
			imagejpeg($dest_img, $include_path. $destination);
			
			// Now let's clean up all our temp images
			//imagedestroy($src_img);
			//imagedestroy($dest_img);
			return $destination;
		} else {
			return false;
		}
	}
?>
