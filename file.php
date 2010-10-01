<?php

    // Template for other types of cache 
    // You'll need to implement these 5 functions, add
    // additional functions inhere. 
    //
    // Add variables you use to jpcache-config.php
    //
    // When you've implemented a new storage-system, and think that the world
    // could/should use it too, please submit it to me (jp@jpcache.com),
    // and I'll include it in a next release (with full credits, ofcourse).
    
    // NOTE: This file was hacked for asy_jpcache and Textpattern.
    //       Do not use jpcache-templates, if you don't understand what the 
    //       differences are.
 
    /* jpcache_restore()
     *
     * Will (try to) restore the cachedata.
     */
    function jpcache_restore()
    {
        // Construct filename
        $filename = $GLOBALS["JPCACHE_DIR"]."/".$GLOBALS["JPCACHE_FILEPREFIX"].$GLOBALS["jpcache_key"];
        
        // read file and unserialize the data
        $cachedata=@unserialize(jpcache_fileread($filename));
        if (is_array($cachedata))
        {
            // Only read cachefiles of my version
            if ($cachedata["jpcache_version"] == $GLOBALS["JPCACHE_VERSION"])
            {
                if (($cachedata["jpcache_expire"] == "0") || 
                    ($cachedata["jpcache_expire"] >= time()))
                {
                    //Restore data
                    $GLOBALS["jpcachedata_gzdata"]   = $cachedata["jpcachedata_gzdata"];
                    $GLOBALS["jpcachedata_datasize"] = $cachedata["jpcachedata_datasize"];
                    $GLOBALS["jpcachedata_datacrc"]  = $cachedata["jpcachedata_datacrc"];
                    $GLOBALS["jpcachedata_type"]  = $cachedata["jpcachedata_type"];
                    return TRUE;
                }
                else
                {
                    jpcache_debug("Data in cachefile $filename has expired");
                }
            }
            else
            {
                // Invalid version of cache-file
                jpcache_debug("Invalid version of cache-file $filename");
            }
        }
        else
        {
            // Invalid cache-file
            jpcache_debug("Invalid content of cache-file $filename");
        }
        
        return FALSE;
    }

    /* jpcache_write()
     *
     * Will (try to) write out the cachedata to the db
     */
    function jpcache_write($gzdata, $datasize, $datacrc, $contents_snippet) 
    {
        // Construct filename
        $filename = $GLOBALS["JPCACHE_DIR"]."/".$GLOBALS["JPCACHE_FILEPREFIX"].$GLOBALS["jpcache_key"];
        
        // Create and fill cachedata-array
        $cachedata = array();
        $cachedata["jpcache_version"] = $GLOBALS["JPCACHE_VERSION"];
        $cachedata["jpcache_expire"] = ($GLOBALS["JPCACHE_TIME"] > 0) ? 
                                            time() + $GLOBALS["JPCACHE_TIME"] :
                                            0;
        $cachedata["jpcachedata_gzdata"] = $gzdata;
        $cachedata["jpcachedata_datasize"] = $datasize;
        $cachedata["jpcachedata_datacrc"] = $datacrc;
        if (function_exists('headers_list')) 
		{
           $headers = headers_list();
           foreach ($headers as $item)
          {
             if (stristr($item, 'Content-Type') !== false)
	    	   $cachedata["jpcachedata_type"] = substr($item, strpos($item, ': ')+2);
           }
        } 
		// Buggy PHP versions just return blank arrays on headers_list()
        if (!function_exists('headers_list') || !isset($cachedata["jpcachedata_type"]))
		{
			//Content-Sniffing to set correct Content-Type, because headers were not available
			if (strpos($contents_snippet,'<rss version="0.92">')===0)
				$cachedata["jpcachedata_type"] = 'application/rss+xml; charset=utf-8';
			elseif (strpos($contents_snippet,chr(60).'?xml version="1.0" encoding="UTF-8"?'.chr(62)."\n<feed")===0)
				$cachedata["jpcachedata_type"] = 'application/atom+xml; charset=utf-8';
			else
				$cachedata["jpcachedata_type"] = $GLOBALS['JPCACHE_DEFAULT_MIMETYPE'];
		}
        // And write the data
        if (jpcache_filewrite($filename, serialize($cachedata)))
        {
            jpcache_debug("Successfully wrote cachefile $filename");
        }
        else
        {
            jpcache_debug("Unable to write cachefile $filename");
        }
        
    }
    
    /* jpcache_do_gc()
     *
     * Performs the actual garbagecollection
     */
    function jpcache_do_gc($force = false)
    {
        $dp=opendir($GLOBALS["JPCACHE_DIR"]);
        
        // Can we access directory ?
        if (!$dp) 
        {
            jpcache_debug("Error opening ". $GLOBALS["JPCACHE_DIR"] ." for garbage-collection");
        }
        
        while (!(($de=readdir($dp))===FALSE)) 
        {
            // To get around strange php-strpos, add additional char
            // Only read jpcache-files.
            if (strpos("x$de", $GLOBALS["JPCACHE_FILEPREFIX"])==1)
            {
                $filename=$GLOBALS["JPCACHE_DIR"] . "/" . $de;
                // read file and unserializes the data
                $cachedata=unserialize(jpcache_fileread($filename));
                
                // Check data in array.
                if (is_array($cachedata)) 
                {
                    if (($cachedata["jpcache_expire"]!="0" && $cachedata["jpcache_expire"]<=time()) OR ($force))
                    {
                        // Unlink file, we do not need to get a lock
                        $deleted = @unlink($filename);
                        if ($deleted)
                        {
                            jpcache_debug("Successfully unlinked $filename");
                        }
                        else
                        {
                            jpcache_debug("Failed to unlink $filename");
                        }
                    } 
                } 
            }
        }

    }


    /* jpcache_do_start()
     *
     * Additional code that is executed before real jpcache-code kicks in
     */
    function jpcache_do_start()
    {  
        // Add additional code you might require
    }

    /* jpcache_do_end()
     *
     * Additional code that is executed after caching has been performed,
     * but just before output is returned. No new output can be added!
     */
    function jpcache_do_end()
    {
        // Add additional code you might require
    }

    /* This internal function reads in the cache-file */
    function jpcache_fileread($filename)
    {
        // php.net suggested I should use rb to make it work under Windows
        $fp=@fopen($filename, "rb");
        if (!$fp) 
        {
            jpcache_debug("Failed to open for read of $filename");
            return NULL;
        }
        
        // Get a shared lock
        flock($fp, LOCK_SH);
        
        $buff="";
        // Be gentle, so read in 4k blocks
        while (($tmp=fread($fp, 4096))) 
        {
            $buff.=$tmp;
        }
        
        // Release lock
        flock($fp, LOCK_UN);
        fclose($fp);
        // Return
        return $buff;
    }

    /* This internal function writes the cache-file */
    function jpcache_filewrite($filename, $data) 
    {
        $return = FALSE;
        // Lock file, ignore warnings as we might be creating this file
        $fpt = @fopen($filename, "rb");
        @flock($fpt, LOCK_EX);
        
        // php.net suggested I should use wb to make it work under Windows
        $fp=@fopen($filename, "wb+");
        if (!$fp) 
        {
            // Strange! We are not able to write the file!
            jpcache_debug("Failed to open for write of $filename");
        }
        else
        {
            fwrite($fp, $data, strlen($data));
            fclose($fp);
            $return = TRUE;
        }
        
        // Release lock
        @flock($fpt, LOCK_UN);
        @fclose($fpt);
        // Return
        return $return;
    }

    // Make sure no additional lines/characters are after the closing-tag!
?>
