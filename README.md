ExpressionEngine ImageSizer - Improved
========

This ExpressionEngine plugin will resize any JPG GIF or PNG image to the desired size specified in your EE tag and cache the resized image to the cache folder. If you update the original image a new resized version will be created. If the image is not on the server the tag will not return anything. The general architecture of this plug-in is setup in a way that it only processes images when needed. 

Install
---
1. put plug-in in plug-ins location :)
2. create a cache folder in your images folder called "sized" /images/sized/
3. make sure you "sized" folder is writable (chmod 777)

Parameters:
---
**src=** (required) the path from "webroot" to the image or the URL to the image. /images/news/moped.jpg or http://www.lumis.com/images/news/moped.jpg 

**width=** the width you wish the image resized to. The height is resized proportionately.

or 

**height=** the height you wish the image resized to. The width is resized proportionately.

or

**auto=** the size of the longest side. If the image is landscape, then this sets the width, else it sets the height. 


**NOTE:**
if you use only width or only height the image will be scaled to match that width or height proportionately
if you use auto, The image will be scaled to the longest side proportionately.
if you use both width and height the image will be cropped from center to that width and height.
if "width" is = to "height" the image will be cropped from image center to make a square sized image.
when cropping the image is always scaled proportionately by the longest side
quality="90" (optional for JPG images) ranges from 0 (worst quality, smaller file) to 100 (best quality, biggest file). The default is the default value is (100). 

**greyscale="yes"** (optional) if set to yes imagesizer will convert color jpg images to greyscale 

**justurl=** (Single Tag only) if 'yes' the tag will only return the path to the image. 

**alt=** (Single Tag only) the alt tag of the image. 

**id=** (Single Tag only) the id of the image. 

**class=** (Single Tag only) the class of the image. 

**server_domain=** (optional) will prepend the value of the param to the output of the {sized} variable e.g. server_domain="http://www.lumis.com" cache="no" allows you to turn off image caching (not a good idea) setting this to "no" means your images will be reprocessed everytime the page is loaded (this param defaults to yes) 

**base_path=** (optional) by default the base_path is set by ExpressionEngine to your webroot you may override this by altering this value to something like "/web/htdocs/lumis.com/" 

**base_cache=** (optional) the base cache folder is where all your cache images are stored within sub directories of your base cache folder by default it is base_path+"/images/sized/" you can change this to anything you wish as long as it points to a folder structure in your sites document root example: base_cache="/web/htdocs/lumis.com/sized_images/" 

Experimental
---
**remote=** set this to "on" if you pass a remote URL through the src= 

**refresh=** (only used with remote) this sets how often to update the cache of the remote image in minutes. Default is 1 Day 

**remote_user=** (optional) use if the remote server requires a username and password.
**remote_pass=** (optional) use if the remote server requires a username and password. 
    
