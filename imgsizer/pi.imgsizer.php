<?php

if ( ! defined('BASEPATH')) exit('No direct script access allowed');

$plugin_info = array(
	'pi_name'        => 'ImageSizer',
	'pi_version'     => '2.6.4',
	'pi_author'      => 'David Rencher',
	'pi_author_url'  => 'http://www.lumis.com/',
	'pi_description' => 'Image Resizer - resizes and caches images',
	'pi_usage'       => Imgsizer::usage()
  );


class Imgsizer
{

var $return_data = ""; 

  function Imgsizer()
  {
    $this->EE =& get_instance();
  }
  
  function size()
	{
		// --------------------------------------------------
		//  Determine base path
		// --------------------------------------------------
	
		// checks if server supports $ENV DOCUMENT_ROOT use $_SERVER otherwise
		if (array_key_exists('DOCUMENT_ROOT',$_ENV))
		{
			$base_path = $_ENV['DOCUMENT_ROOT']."/";
		}
		else
		{
			$base_path = $_SERVER['DOCUMENT_ROOT']."/";
		}
		
		$base_path = str_replace("\\", "/", $base_path);
		$base_path = $this->EE->functions->remove_double_slashes($base_path);
		
		
			
		// -------------------------------------
		// define some defaults
		// -------------------------------------	
		$img = array();
		$okmime = array("image/png","image/gif","image/jpeg");
		
		
		// -------------------------------------
		// collect passed vars from the tag
		// -------------------------------------
		$src = ( ! $this->EE->TMPL->fetch_param('image')) ? ( !$this->EE->TMPL->fetch_param('src') ? '' : $this->EE->TMPL->fetch_param('src') ) : $this->EE->TMPL->fetch_param('image');
		$src = str_replace(SLASH, "/", $src); // clean up passed src param 
			
		
		// -------------------------------------
		// Did User pass a full img tag..  shame shame.. lets fix that
		// -------------------------------------
		if (stristr($src, '<img'))
		{
			$src = str_replace("\\", "", $src);	
			$src = preg_match_all('/src="(.+?)"/is', $src, $this->url);
			$src = $this->url[1][0];
		}
	
	
			/*debug*/
			$this->EE->TMPL->log_item("imgsizer.user.src: ".$src);
		
			
		$base_path = ( ! $this->EE->TMPL->fetch_param('base_path')) ? $base_path : $this->EE->TMPL->fetch_param('base_path');
			$base_path = str_replace("\\", "/", $base_path);
			$base_path = $this->EE->functions->remove_double_slashes($base_path);
			$img['base_path']= $base_path;
			
		
			
		$remote = ( ! $this->EE->TMPL->fetch_param('remote')) ? '' : $this->EE->TMPL->fetch_param('remote');
		
		$cache = ( ! $this->EE->TMPL->fetch_param('cache')) ? '' : $this->EE->TMPL->fetch_param('cache');
		
		$refresh = ( ! $this->EE->TMPL->fetch_param('refresh')) ? '1440' : $this->EE->TMPL->fetch_param('refresh');

		
		// -------------------------------------
		// if no src thers not much we can do..
		// -------------------------------------
		if(!$src){
			 $this->EE->TMPL->log_item("imgsizer.Error: no src provided for imagesizer");
			 return $this->EE->TMPL->no_results();
		}
		
		

		// -------------------------------------
		// die if user trys to pass in a img tag
		// -------------------------------------
		/*
		if (stristr($src, '<img'))
		{
			$this->EE->TMPL->log_item("imgsizer.Error: you are trying to pass a img tag into imagesizer: no good");
			return $this->EE->TMPL->no_results();			
		}
		*/

	
		// -------------------------------------
		// extract necessities from full URL
		// -------------------------------------	
		if (stristr($src, 'http'))
		{
			$img['url_src'] = $src; // save the URL src for remote
			$urlarray = parse_url($img['url_src']);
			$img['url_host_cache_dir'] =  str_replace('.', '-', $urlarray['host']);			
			$src = '/'.$urlarray['path'];
		}
				
		$img['src'] = $this->EE->functions->remove_double_slashes("/".$src);
		$img['base_path'] = $this->EE->functions->remove_double_slashes($base_path);
		
		/*debug*/
		$this->EE->TMPL->log_item("imgsizer.img[src]: ".$img['src']);
		$this->EE->TMPL->log_item("imgsizer.img[base_path]: ".$img['base_path']);

		// -------------------------------------
		// do fetch remote img and reset the src
		// -------------------------------------		
		if($remote && $img['url_src']){
			$img['src'] = $this->do_remote($img);
		}
		
		
		$img_full_path = $this->EE->functions->remove_double_slashes($img['base_path'].$img['src']);
		
		/*debug*/
		$this->EE->TMPL->log_item("imgsizer.img_full_path: ".$img_full_path);
		
			
		// can we actually read this file?
		if(!is_readable($img_full_path)){	
		    $this->EE->TMPL->log_item("imgsizer.Error: ".$img_full_path." image is not readable or does not exist");
			return $this->EE->TMPL->no_results();
		}
		
		
		// -------------------------------------
		// get src img sizes and mime type
		// -------------------------------------
		$size = getimagesize($img_full_path);
			$img['src_width'] = $size[0];
			$img['src_height'] = $size[1];      
			$img['mime'] = $size['mime'];
			
			
		// -------------------------------------
		// get src relitive path
		// -------------------------------------	
		$src_pathinfo = pathinfo($img['src']);
		$img['base_rel_path'] = $this->EE->functions->remove_double_slashes($src_pathinfo['dirname']."/");	
		
		/*debug*/
		$this->EE->TMPL->log_item("imgsizer.img[base_rel_path]: ".$img['base_rel_path']);	

		// -------------------------------------
		// define all the file location pointers we will need 
		// -------------------------------------	
		$img_full_pathinfo = pathinfo($img_full_path);		
		$img['root_path'] = ( ! isset($img_full_pathinfo['dirname'])) ? '' : $this->EE->functions->remove_double_slashes($img_full_pathinfo['dirname']."/");
		$img['basename'] = ( ! isset($img_full_pathinfo['basename'])) ? '' : $img_full_pathinfo['basename'];
		$img['extension'] = ( ! isset($img_full_pathinfo['extension'])) ? '' : $img_full_pathinfo['extension'];
		$img['base_filename'] = str_replace(".".$img['extension'], "", $img_full_pathinfo['basename']);
		
		/*debug*/
		foreach($img_full_pathinfo as $key=>$value){
			$this->EE->TMPL->log_item("imgsizer.full_pathinfo ".$key.": ".$value);
		}


		// -------------------------------------
		// lets stop this if the image is not in the ok mime types 
		// -------------------------------------
		if (!in_array($img['mime'], $okmime))
			{
			$this->EE->TMPL->log_item("imgsizer.Error: image mime type does not match ok mime types");
			return $this->EE->TMPL->no_results();
			}
				
	
		// -------------------------------------
		// build cache location
		// -------------------------------------
		$base_cache = $this->EE->functions->remove_double_slashes($base_path."/images/sized/");
		$base_cache = ( ! $this->EE->TMPL->fetch_param('base_cache')) ? $base_cache : $this->EE->TMPL->fetch_param('base_cache');
		$base_cache = $this->EE->functions->remove_double_slashes($base_cache);
		
		$img['cache_path'] = $this->EE->functions->remove_double_slashes($base_cache.$img['base_rel_path']);
	
	         
	
			if(!is_dir($img['cache_path']))
			{
				// make the directory if we can 
				if (!mkdir($img['cache_path'],0777,true))
				{
				$this->EE->TMPL->log_item("imgsizer.Error: could not create cache directory! Please manually create the cache directory ".$img['cache_path']." with 777 permissions");
				return $this->EE->TMPL->no_results();
				}
			}
			
			
			// check if we can put files in the cache directory 
			if (!is_writable($img['cache_path']))
			{
				$this->EE->TMPL->log_item("imgsizer.Error: ".$img['cache_path']." is not writable please chmod 777");
				return $this->EE->TMPL->no_results();
			}
		

		// -------------------------------------
		// do the sizing math
		// -------------------------------------		
		$img = $this->get_some_sizes($img);

		// -------------------------------------
		// check the cache
		// -------------------------------------		
		$img = $this->check_some_cache($img);
		
		// -------------------------------------
		// do the sizing if needed 
		// -------------------------------------
		$img = $this->do_some_image($img);

		
		/*debug*/
		foreach($img as $key=>$value){
			$this->EE->TMPL->log_item("imgsizer.img[".$key."]: ".$value);
		}
  
		// -------------------------------------
		// do the output
		// ------------------------------------- 
		return $this->do_output($img);	
	}
	


// -------------------------------------
// This function calculates how the image should be resized / cropped etc.
// -------------------------------------

function get_some_sizes($img){

	
		// set some defaults
		$width = $img['src_width'];
		$height = $img['src_height'];
		$img['crop'] = "";
		$img['proportional'] = true;
		$color_space="";

		$auto = ( ! $this->EE->TMPL->fetch_param('auto')) ? '' : $this->EE->TMPL->fetch_param('auto');
		$max_width = ( ! $this->EE->TMPL->fetch_param('width')) ? '9000' : $this->EE->TMPL->fetch_param('width'); 
		$max_height = ( ! $this->EE->TMPL->fetch_param('height')) ? '9000' : $this->EE->TMPL->fetch_param('height');
		$greyscale = ( ! $this->EE->TMPL->fetch_param('greyscale')) ? '' : $this->EE->TMPL->fetch_param('greyscale');
		
		
		if($greyscale){
			$color_space = "-greyscale";		
		}		
		
			// -------------------------------------
			// get the ratio needed
			// -------------------------------------
			$x_ratio = $max_width / $width;
			$y_ratio = $max_height / $height;
			
			// -------------------------------------
			// if image already meets criteria, load current values in
			// if not, use ratios to load new size info
			// -------------------------------------
		
			if (($width <= $max_width) && ($height <= $max_height) )
			{
				$img['out_width'] = $width;
				$img['out_height'] = $height;
			} else if (($x_ratio * $height) < $max_height) {
				$img['out_height'] = ceil($x_ratio * $height);
				$img['out_width'] = $max_width;
			} else {
				$img['out_width'] = ceil($y_ratio * $width);
				$img['out_height'] = $max_height;
			}
			
			
			// -------------------------------------
			//  Set image sizing Ratio
			//  Auto size Added By Erin Dalzell 
			// -------------------------------------
			if ($auto)
			{
				if ($width > $height)
				{
					$img['out_width'] = $auto;
					$img['out_height'] = '0';
				}
				else
				{
					$img['out_height'] = $auto;
					$img['out_width'] = '0';
				}
			}		

			// -------------------------------------
			// Do we want to Crop the image?
			// -------------------------------------
			if($max_height != '9000' && $max_width != '9000' && $max_width != $max_height)
			{
				$img['crop'] = "yes";
				$img['proportional'] = false;
				$img['out_width'] = $max_width;
				$img['out_height'] = $max_height;
			}

			// -------------------------------------
			// Do we Need to crop?
			// -------------------------------------
			if($max_width == $max_height && $auto == ""){
				$img['crop'] = "yes";
				$img['proportional'] = false;
				$img['out_width'] = $max_width;
				$img['out_height'] = $max_height; 
			}
			

			// set outputs 
			$img['out_name'] = $img['base_filename'].$color_space.'-'.$img['out_width'].'x'.$img['out_height'].'.'.$img['extension'];
			$img['root_out_name'] = $img['cache_path'].$img['out_name'];
			$img['browser_out_path'] = $this->EE->functions->remove_double_slashes("/".str_replace($img['base_path'], '', $img['root_out_name']));
			
			
			return $img;
}	
	
	
// -------------------------------------
// checks cached images if they are present
// and if they are older than the src img
// -------------------------------------

function check_some_cache($img){

	$img['do_cache'] = "";
	
	$cache = ( ! $this->EE->TMPL->fetch_param('cache')) ? '' : $this->EE->TMPL->fetch_param('cache');
	
	$imageModified = @filemtime($img['base_path'].$img['src']);
	$thumbModified = @filemtime($img['cache_path'].$img['out_name']);

        // set a update flag 
		if ($imageModified > $thumbModified || $cache == "no")
		{
		$img['do_cache'] = "update";
		}	
	
	return $img;
}	
	
// -------------------------------------
// This function does the image resizing
// -------------------------------------
function do_some_image($img) {

	
	$file = $img['root_path'].$img['basename'];
	$width = $img['out_width'];
	$height = $img['out_height'];
	$crop = $img['crop'];	
	$proportional = $img['proportional'];
	$output = $img['cache_path'].$img['out_name'];
	
	
		$quality = ( ! $this->EE->TMPL->fetch_param('quality')) ? '100' : $this->EE->TMPL->fetch_param('quality');
		$quality = "100";
		$greyscale = ( ! $this->EE->TMPL->fetch_param('greyscale')) ? '' : $this->EE->TMPL->fetch_param('greyscale');
        
		
		if ( $height <= 0 && $width <= 0 ) {
            return false;
        }
       
		$info = getimagesize($file);
			$image = '';

       
		$final_width = 0;
        $final_height = 0;
        list($width_old, $height_old) = $info;

        if ($proportional) 
		{
            if ($width == 0) $factor = $height/$height_old;
            elseif ($height == 0) $factor = $width/$width_old;
            else $factor = min ( $width / $width_old, $height / $height_old);  
           
			$final_width = round ($width_old * $factor);
			$final_height = round ($height_old * $factor);

        }else {
			$final_width = ( $width <= 0 ) ? $width_old : $width;
			$final_height = ( $height <= 0 ) ? $height_old : $height;
        }
		
		if ($crop) {
		
	
			$int_width = 0;
			$int_height = 0;
			
			$adjusted_height = $final_height;
			$adjusted_width = $final_width;
			
				$wm = $width_old/$width;
				$hm = $height_old/$height;
				$h_height = $height/2;
				$w_height = $width/2;
				
				$ratio = $width/$height;
				$old_img_ratio = $width_old/$height_old;
				
					if ($old_img_ratio > $ratio) 
					{
						$adjusted_width = $width_old / $hm;
						$half_width = $adjusted_width / 2;
						$int_width = $half_width - $w_height;
					} 
					else if($old_img_ratio <= $ratio) 
					{
						$adjusted_height = $height_old / $wm;
						$half_height = $adjusted_height / 2;
						$int_height = $half_height - $h_height;
					}
		}

		if($img['do_cache'])
		{
		
			@ini_set("memory_limit","12M");
			@ini_set("memory_limit","16M");
			@ini_set("memory_limit","32M");
			@ini_set("memory_limit","64M");			
			
				switch (
					$info[2] ) {
						case IMAGETYPE_GIF:
							$image = imagecreatefromgif($file);
						break;
						case IMAGETYPE_JPEG:
							$image = imagecreatefromjpeg($file);
						break;
						case IMAGETYPE_PNG:
							$image = imagecreatefrompng($file);
						break;
						default:
							return false;
				}
			   
				$image_resized = imagecreatetruecolor( $final_width, $final_height );
					   
				if ( ($info[2] == IMAGETYPE_GIF) || ($info[2] == IMAGETYPE_PNG) ) {
					$trnprt_indx = imagecolortransparent($image);
		  
					// If we have a specific transparent color
					if ($trnprt_indx >= 0) {
		  
						// Get the original image's transparent color's RGB values
						$trnprt_color    = imagecolorsforindex($image, $trnprt_indx);
		  
						// Allocate the same color in the new image resource
						$trnprt_indx    = imagecolorallocate($image_resized, $trnprt_color['red'], $trnprt_color['green'], $trnprt_color['blue']);
		  
						// Completely fill the background of the new image with allocated color.
						imagefill($image_resized, 0, 0, $trnprt_indx);
		  
						// Set the background color for new image to transparent
						imagecolortransparent($image_resized, $trnprt_indx);
		  
				   
					}
					// Always make a transparent background color for PNGs that don't have one allocated already
					elseif ($info[2] == IMAGETYPE_PNG) {
		  
						// Turn off transparency blending (temporarily)
						imagealphablending($image_resized, false);
		  
						// Create a new transparent color for image
						$color = imagecolorallocatealpha($image_resized, 0, 0, 0, 127);
		  
						// Completely fill the background of the new image with allocated color.
						imagefill($image_resized, 0, 0, $color);
		  
						// Restore transparency blending
						imagesavealpha($image_resized, true);
					}
				}
		

	
				if ($crop) 
				{   
				 imagecopyresampled($image_resized, $image, -$int_width, -$int_height, 0, 0, $adjusted_width, $adjusted_height, $width_old, $height_old);    
				}else{
				 imagecopyresampled($image_resized, $image, 0, 0, 0, 0, $final_width, $final_height, $width_old, $height_old);
				}  

				if($greyscale){
							for ($c=0;$c<256;$c++)
							{
							$palette[$c] = imagecolorallocate($image_resized,$c,$c,$c);
							}
							
							for ($y=0;$y<$final_height;$y++)
							{
								for ($x=0;$x<$final_width;$x++)
								{
									$rgb = imagecolorat($image_resized,$x,$y);
									$r = ($rgb >> 16) & 0xFF;
									$g = ($rgb >> 8) & 0xFF;
									$b = $rgb & 0xFF;
									$gs = $this->yiq($r,$g,$b);
									imagesetpixel($image_resized,$x,$y,$palette[$gs]);
								}
							} 		
				}
		
		
				switch ($info[2] ) {
					case IMAGETYPE_GIF:
						imagegif($image_resized, $output);
					break;
					case IMAGETYPE_JPEG:
						imagejpeg($image_resized, $output, $quality);
						
					break;
					case IMAGETYPE_PNG:
						imagepng($image_resized, $output);
					break;
					default:
						return false;
				}
		}	
		
			$img['out_width'] =	$final_width;
			$img['out_height'] = $final_height;
			
	
	   return $img;

    }
	
	
function do_output($img){

		
		$alt = ( ! $this->EE->TMPL->fetch_param('alt')) ? '' : $this->EE->TMPL->fetch_param('alt');
		$style = ( ! $this->EE->TMPL->fetch_param('style')) ? '' : $this->EE->TMPL->fetch_param('style');
		$class = ( ! $this->EE->TMPL->fetch_param('class')) ? '' : $this->EE->TMPL->fetch_param('class');
		$title = ( ! $this->EE->TMPL->fetch_param('title')) ? '' : $this->EE->TMPL->fetch_param('title');
		$id = ( ! $this->EE->TMPL->fetch_param('id')) ? '' : $this->EE->TMPL->fetch_param('id');
		$justurl = ( ! $this->EE->TMPL->fetch_param('justurl')) ? '' : $this->EE->TMPL->fetch_param('justurl');
		$server_domain = ( ! $this->EE->TMPL->fetch_param('server_domain')) ? '' : $this->EE->TMPL->fetch_param('server_domain');
		
		$browser_out_path = str_replace(SLASH, "/", $server_domain).$img['browser_out_path'];
		$img['browser_out_path'] = $this->EE->functions->remove_double_slashes($browser_out_path);
	

		/** -------------------------------------
		/*  sometimes we may just want the path to the image e.g. RSS feeds
		/** -------------------------------------*/
		if ($justurl)
		{
			return $img['browser_out_path'];
		}		
		
		/** -------------------------------------
		/*  is the tag in a pair if so do that
		/** -------------------------------------*/

		$tagdata = $this->EE->TMPL->tagdata;
		$tagdata = $this->EE->functions->prep_conditionals($tagdata, $this->EE->TMPL->var_single);
		
			if ($tagdata)
			{
				foreach ($this->EE->TMPL->var_single as $key => $val)
				 {
				
				 
					
					if ($val == "url")
					{
						$tagdata = $this->EE->TMPL->swap_var_single($val, $img['browser_out_path'], $tagdata);
					}
					if ($val == "sized")
					{
						$tagdata = $this->EE->TMPL->swap_var_single($val, $img['browser_out_path'], $tagdata);
					}
					if ($val == "width")
					{
						$tagdata = $this->EE->TMPL->swap_var_single($val, $img['out_width'], $tagdata);
					}
					if ($val == "img_width")
					{
						$tagdata = $this->EE->TMPL->swap_var_single($val, $img['out_width'], $tagdata);
					}
					if ($val == "height")
					{
						$tagdata = $this->EE->TMPL->swap_var_single($val, $img['out_height'], $tagdata);
					}
					if ($val == "img_height")
					{
						$tagdata = $this->EE->TMPL->swap_var_single($val, $img['out_height'], $tagdata);
					}
				}
				return $tagdata;	
			}
			

			/** -------------------------------------
			/*  this is the default output just a simpe img tag 
			/** -------------------------------------*/
			$out_tag = "<img src=\"".$img['browser_out_path']."\" width=\"".$img['out_width']."\" height=\"".$img['out_height']."\" ";
			
			$out_tag .= ($id ? " id=\"$id\"" : "");
			
			$out_tag .= ($title ? " title=\"$title\"" : "");
			
			$out_tag .= ($alt ? " alt=\"$alt\"" : " alt=\"\"");
	
			$out_tag .= ($class ? " class=\"$class\"" : "");
			
			$out_tag .= ($style ? " style=\"$style\"" : "");
							
			return $out_tag." />";	

}


// -------------------------------------
// This function does remote image fetch
// -------------------------------------
function do_remote($img){

		
		$src = ( ! $this->EE->TMPL->fetch_param('image')) ? ( !$this->EE->TMPL->fetch_param('src') ? '' : $this->EE->TMPL->fetch_param('src') ) : $this->EE->TMPL->fetch_param('image');
			$src = str_replace(SLASH, "/", $src); // clean up passed src param 
		$refresh = ( ! $this->EE->TMPL->fetch_param('refresh')) ? '1440' : $this->EE->TMPL->fetch_param('refresh');
		
		$remote_user = ( ! $this->EE->TMPL->fetch_param('remote_user')) ? '' : $this->EE->TMPL->fetch_param('remote_user');
		$remote_pass = ( ! $this->EE->TMPL->fetch_param('remote_pass')) ? '' : $this->EE->TMPL->fetch_param('remote_pass');
		
		$url_filename = parse_url($img['src']);
		$url_filename = pathinfo($url_filename['path']);
		
		$base_cache = $this->EE->functions->remove_double_slashes($img['base_path']."/images/sized/");
		$base_cache = ( ! $this->EE->TMPL->fetch_param('base_cache')) ? $base_cache : $this->EE->TMPL->fetch_param('base_cache');
		$base_cache = $this->EE->functions->remove_double_slashes($base_cache);
		
		$save_mask = str_replace('/', '-', $url_filename['dirname']);
		$save_name = $img['url_host_cache_dir'].$save_mask."-".$url_filename['basename'];	
		
		$save_root_folder = $base_cache."remote";
		$save_root_path = $save_root_folder."/".$save_name;
		
		$save_rel_path = $base_cache."/remote/".$save_name;
		$save_rel_path = "/".str_replace($img['base_path'], '', $save_rel_path);
		$save_rel_path = $this->EE->functions->remove_double_slashes($save_rel_path);	
		
		$save_dir = $this->EE->functions->remove_double_slashes($base_cache."/remote/");

			if(!is_dir($save_dir))
			{
				// make the directory if we can 
				if (!mkdir($save_dir,0777,true))
				{
				$this->EE->TMPL->log_item("Error: could not create cache directory! Please manually create the cache directory ".$save_dir."/remote/ with 777 permissions");
				return $this->EE->TMPL->no_results();
				}
			}
			

	    // check the sorce and the cache file
		 //error_reporting(0);
		 
		 $remote_diff  = round((time() - filectime($save_root_path))/60)-9; 
		 //$this->EE->TMPL->log_item($remote_diff);
				
		if($remote_diff > $refresh){

				$ch = curl_init(); 
				curl_setopt($ch, CURLOPT_URL, $src);
					if($remote_pass)
					{
						curl_setopt($ch, CURLOPT_USERPWD, $remote_user.":".$remote_pass);
					}		
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_BINARYTRANSFER, 1); 
				curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);
				curl_setopt($ch, CURLOPT_TIMEOUT, 30);
				$file_contents = curl_exec($ch);
				$curl_info = curl_getinfo($ch);
		
				if (curl_errno($ch)) {
					return;
				} else {
					curl_close($ch);
				}
				
				define("FILE_PUT_CONTENTS_ATOMIC_TEMP", $save_dir);
				define("FILE_PUT_CONTENTS_ATOMIC_MODE", 0777);
				$this->file_put_contents_atomic($save_root_path, $file_contents);
				
				
				$this->EE->TMPL->log_item("imgsizer.remote.did_fetch: yes");
				foreach($curl_info as $key=>$value){
					$this->EE->TMPL->log_item("imgsizer.remote.curl.".$key.": ".$value);
				}
	
		}
	
		/*debug*/
		$this->EE->TMPL->log_item("imgsizer.remote.save_src_path: ".$save_rel_path);
		return $save_rel_path;

}


function file_put_contents_atomic($filename, $content) {
  
    $temp = tempnam(FILE_PUT_CONTENTS_ATOMIC_TEMP, 'temp');
    if (!($f = @fopen($temp, 'wb'))) {
        $temp = FILE_PUT_CONTENTS_ATOMIC_TEMP . DIRECTORY_SEPARATOR . uniqid('temp');
        if (!($f = @fopen($temp, 'wb'))) {
            trigger_error("file_put_contents_atomic() : error writing temporary file '$temp'", E_USER_WARNING);
            return false;
        }
    }
  
    fwrite($f, $content);
    fclose($f);
  
    if (!@rename($temp, $filename)) {
        @unlink($filename);
        @rename($temp, $filename);
    }
  
    @chmod($filename, FILE_PUT_CONTENTS_ATOMIC_MODE);
  
    return true;
  
} 

//Creates yiq function
function yiq($r,$g,$b)
	{
		return (($r*0.299)+($g*0.587)+($b*0.114));
	}	
	

	// --------------------------------------------------------------------

	/**
	 * Usage
	 *
	 * This function describes how the plugin is used.
	 *
	 * @access	public
	 * @return	string
	 */
	
  //  Make sure and use output buffering

  function usage()
  {
  ob_start(); 
  ?>

The ImageSizer plugin will resize any JPG, GIF or PNG image to the size specified
        and cache the resized image to the cache folder. 
        
        If you update the original image a new resized version will be created. 

		=============================
		The Tag
		=============================

        {exp:imgsizer:size src="/images/news/moped.jpg" width="100"}
               <img src="{sized}" width="{width}" height="{height}" />
               <div style="background-image:url({sized}); width:{width}px; height:{height}px;"></div>
        {/exp:imgsizer:size}


		==============
		TAG PARAMETERS
		==============

        src=
            [REQUIRED]
            the relitive path to the image or the URL to the image.
            /images/news/moped.jpg  or  http://www.lumis.com/images/news/moped.jpg
          -------------------


        width=
        	the width you wish the image resized to. The height is resized proportionately.
        	[OR]
        height=
         	the height you wish the image resized to. The width is resized proportionately.
        	[OR]
        auto=
         	the size of the longest side. If the image is landscape, then this sets the width, else it sets the height.

            NOTE:
            - if you use only width or only height the image will be scaled to match that width or height proportionately. 
            - if you use auto, image will be scaled to the longest side proportionately. 
            - if you use both width and height the image will be cropped from center to that width and height.
            - if "width" is = to "height" the image will be cropped from image center to make a square sized image.
         ------------------- 
            

        quality= 
            [OPTIONAL]
            only used if image is JPG
            ranges from 0 (worst quality, smaller file) to 100 (best quality, biggest file). The default is the default value is (100).
            ------------------- 

        greyscale=
            [OPTIONAL]
            if set to yes imagesizer will convert color images to greyscale
            ------------------- 


        base_path=
            [OPTIONAL]
             by default the base_path is set by ExpressionEngine to your webroot you may override this by altering this value to something like "/web/htdocs/lumis.com/" 
             ------------------- 
        
        base_cache= 
            [OPTIONAL]
            the base cache folder is where all your cache images are stored within sub directories of your base cache folder by default it is "/web/htdocs/lumis.com/images/sized/" you can change this to anything you wish as long as it points to a folder structure in your sites document root 
            ------------------- 
        
        cache = 
            [for testing]
            allows you to turn off image caching (not a good idea) setting this to "no" means your images will be reprocessed everytime the page is loaded (caching is on by default)
			------------------- 
		
		------------------
		TROUBLESHOOTING:
		------------------
		
		All error messages are logged in the Template Parsing Log.  If you have no output, or unexpected output, enable the Template Parsing Log in your Output and Debugging Preferences.
		
		Even though this tag has its own caching mechanism, do not forget that you can further increase performance by using Tag Caching: http://expressionengine.com/docs/general/caching.html#tag_caching
		



  <?php
  $buffer = ob_get_contents();
	
  ob_end_clean(); 

  return $buffer;
  }
  // END

}
/* End of file pi.imgsizer.php */ 
/* Location: ./system/expressionengine/third_party/imgsizer/pi.imgsizer.php */ 