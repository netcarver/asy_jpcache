<?php
    // This version of jpcache is hacked to become asy_jpcache. 
    // Don't use it for anything other than textpattern.
    // Get the original version of jpcache at www.jpcache.com

    /* Take a wild guess... */
    function jpcache_debug($s) 
    {
        static $jpcache_debugline;

        if ($GLOBALS["JPCACHE_DEBUG"]) 
        {
            $jpcache_debugline++;
            header("X-CacheDebug-$jpcache_debugline: $s");
        }
    }
    
    /* jpcache_key()
     *
     * Returns a hashvalue for the current. Maybe md5 is too heavy, 
     * so you can implement your own hashing-function. 
     */
    function jpcache_key()
    {
            if ($GLOBALS["JPCACHE_CLEANKEYS"])
            {
                $key = eregi_replace("[^A-Z,0-9,=]", "_", jpcache_scriptkey());
                $key .= ".".eregi_replace("[^A-Z,0-9,=]", "_", jpcache_varkey());
                if (strlen($key) > 255)
                {
                    // Too large, fallback to md5!
                    $key = md5(jpcache_scriptkey().jpcache_varkey());
                }
            }
            else
            {
                $key = md5(jpcache_scriptkey().jpcache_varkey());
            }
            jpcache_debug("Cachekey is set to $key");
            return $key;
    }

    /* jpcache_varkey()
     * 
     * Returns a serialized version of POST & GET vars
     * If you want to take cookies into account in the varkey too, 
     * add them inhere.
     */
    function jpcache_varkey() 
    {
        $varkey = "";
        if ($GLOBALS["JPCACHE_POST"])
        {
            $varkey = "POST=".serialize($_POST); 
        }
        $varkey .= "GET=".serialize($_GET);
		$txpcookie = NULL;
		foreach ($_COOKIE as $key => $value) {
			if (strpos($key,'txp_')!==false)
				$txpcookie[$key]=$value;
		}
        $varkey .= "COOKIE=".md5(serialize($txpcookie));
		if ($GLOBALS['JPCACHE_XHTML_XML']) $varkey.= $_SERVER['HTTP_ACCEPT'];
        jpcache_debug("Cache varkey is set to $varkey");
        return $varkey;
    }

    /* jpcache_scriptkey()
     *
     * Returns the script-identifier for the request
     */
    function jpcache_scriptkey()
    {
        // These should be available, unless running commandline
	// We are ignoring this for textpattern, and using the URI
        if ($GLOBALS["JPCACHE_IGNORE_DOMAIN"])
        {
	    $name=$_SERVER['REQUEST_URI']; 
//            $name=$_SERVER["PHP_SELF"];
        } 
        else 
        {
	    $name=$_SERVER['REQUEST_URI']; 
//            $name=$_SERVER["SCRIPT_URI"];
        }

        // Commandline mode will also fail this one, I'm afraid, as there is no
        // way to determine the scriptname
        if ($name=="")
        {
            $name="http://".$_SERVER["SERVER_NAME"].$_SERVER["SCRIPT_NAME"];
        }
        
        jpcache_debug("Cache scriptkey is set to $name");        
        return $name;
    }


    /* jpcache_check()
     *
     */
    function jpcache_check() 
    {
        if (!$GLOBALS["JPCACHE_ON"]) 
        {
            jpcache_debug("Cache has been disabled!");
            return false;
        }
        
        // We need to set this global, as ob_start only calls the given method
        // with no parameters.
        $GLOBALS["jpcache_key"] = jpcache_key();
        
        // Can we read the cached data for this key ?
        if (jpcache_restore())
        {
			$temp = new TxpLogging();
            jpcache_debug("Cachedata for ".$GLOBALS["jpcache_key"]." found, data restored");    
            return true;
        } 
        else 
        {
            // No cache data (yet) or unable to read
            jpcache_debug("No (valid) cachedata for ".$GLOBALS["jpcache_key"]);
            return false;
        }
    }
    
	// This is only a wrapper class to not pollute the namespace, no OO here
	class TxpLogging {
		function logit($r='')
		{
			global $siteurl;
			$mydomain = str_replace('www.','',preg_quote($siteurl,"/"));
			$out['uri'] = $_SERVER['REQUEST_URI'];//.'#cachehit';
			$out['ref'] = str_replace("http://","",(isset($_SERVER['HTTP_REFERER'])) ? $_SERVER['HTTP_REFERER'] : '');
			$out['ref'] = preg_replace("/\"|'|(?:\s.*$)/",'',$out['ref']);
			$host = $ip = $_SERVER['REMOTE_ADDR'];
	
			//Hardcoded dns to on, don't want to include admin_config.php
			if (true) {
				// A crude rDNS cache
				if ($h = safe_field('host', 'txp_log', "ip='".doSlash($ip)."' limit 1")) {
					$host = $h;
				}
				else {
					// Double-check the rDNS
					$host = @gethostbyaddr($_SERVER['REMOTE_ADDR']);
					if ($host != $ip and @gethostbyname($host) != $ip)
						$host = $ip;
				}
			}
			$out['ip'] = $ip;
			$out['host'] = $host;
			$out['status'] = 200; // FIXME
			$out['method'] = $_SERVER['REQUEST_METHOD'];
			if (preg_match("/^[^\.]*\.?$mydomain/i", $out['ref'])) $out['ref'] = "";
			
			if ($r=='refer') {
				if (trim($out['ref']) != "") { $this->insert_logit($out); }
			} else $this->insert_logit($out);
		}
	
		// -------------------------------------------------------------
		function insert_logit($in) 
		{	
			global $DB;
			$in = array_map('addslashes',$in);
			extract($in);
			safe_insert("txp_log", "`time`=now(),page='$uri',ip='$ip',host='$host',refer='$ref',status='$status',method='$method'");
		}

	    /* do_txp_logging()
	     *
	     * Log cache-hits to the DB for TXP
	     */
	    function do_txp_logging()
	    {
			global $txpcfg,$prefs,$siteurl, $DB;
			if ($txpcfg == '') {
				jpcache_debug('Txp-logging disabled. Include jpcache after config.php.');
				return;
			}
			include $txpcfg['txpath'].'/lib/txplib_db.php';
			$prefs = get_prefs();
			$siteurl = $prefs['siteurl'];
			if($prefs['logging'] == 'refer') { 
				$this->logit('refer'); 
			} elseif ($prefs['logging'] == 'all') {
				$this->logit();
			}
			jpcache_debug('Logged hit per txp-configuration.');
	    }

		// Constructor
		function TxpLogging() 
		{
			if (($GLOBALS['JPCACHE_TXPLOG_DO'] == 0) 
				|| preg_match('#(\?|\/)(rss|atom)(\=|\/)#',$_SERVER['REQUEST_URI'])) return;
			$this->do_txp_logging();
		}

	}	// End TXP-Logging

    /* jpcache_encoding()
     *
     * Are we capable of receiving gzipped data ?
     * Returns the encoding that is accepted. Maybe additional check for Mac ?
     */
    function jpcache_encoding()
    { 
        if (headers_sent() || connection_aborted())
        { 
            return false; 
        } 
        if (@strpos($_SERVER["HTTP_ACCEPT_ENCODING"],'x-gzip') !== false)
        {
            return "x-gzip";
        }
        if (@strpos($_SERVER["HTTP_ACCEPT_ENCODING"],'gzip') !== false)
        {
            return "gzip";
        }
        return false; 
    }

    /* jpcache_init()
     *
     * Checks some global variables and might decide to disable caching
     */
    function jpcache_init()
    {
        // Override default JPCACHE_TIME ?
        if (isset($GLOBALS["cachetimeout"]))
        {
            $GLOBALS["JPCACHE_TIME"]=$GLOBALS["cachetimeout"];
        }
        
        // Force gzip off if gzcompress does not exist
        if (!function_exists('gzcompress') || ini_get("zlib.output_compression")==1) 
        {
        	$GLOBALS["JPCACHE_USE_GZIP"]  = 0;
        }

        // Force cache off when POST occured when you don't want it cached
        if (!$GLOBALS["JPCACHE_POST"] && (count($_POST) > 0)) 
        {
            $GLOBALS["JPCACHE_ON"] = 0;
            $GLOBALS["JPCACHE_TIME"] = -1;
	    if (!isset($_POST['preview']) && isset($_POST['nonce']))
			jpcache_do_gc(true);
		}
        
        // A cachetimeout of -1 disables writing, only ETag and content encoding
        if ($GLOBALS["JPCACHE_TIME"] == -1)
        {
            $GLOBALS["JPCACHE_ON"] = 0;
        }

	if (strpos($_SERVER['REQUEST_URI'],'/file_download/') ||
	     (@$_REQUEST['s'] == 'file_download')) 
	{
            $GLOBALS["JPCACHE_ON"] = 0;
        }
        
	// Output header to recognize version
        header("X-Cache: asy_jpcache ".$GLOBALS["JPCACHE_VERSION"].
                " - ".$GLOBALS["JPCACHE_TYPE"]);
    }

    /* jpcache_gc()
     *
     * Checks if garbagecollection is needed.
     */
    function jpcache_gc()
    {
        // Should we garbage collect ?
        if ($GLOBALS["JPCACHE_GC"]>0) 
        {
            mt_srand(time(NULL));
            $precision=100000;
            // Garbagecollection probability
            if (((mt_rand()%$precision)/$precision) <=
                ($GLOBALS["JPCACHE_GC"]/100)) 
            {
                jpcache_debug("GarbageCollection hit!");
                jpcache_do_gc();
            }
        }
    }

    /* jpcache_start()
     *
     * Sets the handler for callback
     */
    function jpcache_start()
    {
        // Initialize cache
        jpcache_init();

        // Skip Caching Feeds 
		if (preg_match('#(\?|\/)(rss|atom)(\=|\/)#',$_SERVER['REQUEST_URI']))
		{
			if ($GLOBALS['JPCACHE_SKIP_FEEDS'] )
			{
				jpcache_debug("Not Caching Feeds.");
	            return;
			}
			if (is_callable('apache_request_headers')) {
				$headers = apache_request_headers();
				if (isset($headers["A-IM"])) {
					// Textpattern may respond with 
					jpcache_debug("Not Caching Feeds, partial Content.");
		            return;
				}
			}
			// Phew, Skip is set to false, and no partial content. We can cache this feed.
		}

        // Handle type-specific additional code if required
        jpcache_do_start();
  
        // Check cache
        if (jpcache_check())
        {
            // Cache is valid and restored: flush it!
            print jpcache_flush($GLOBALS["jpcachedata_gzdata"], 
                                $GLOBALS["jpcachedata_datasize"], 
                                $GLOBALS["jpcachedata_datacrc"]);
            // Handle type-specific additional code if required
            jpcache_do_end();
            exit;
        }
        else
        {
            // if we came here, cache is invalid: go generate page 
            // and wait for jpCacheEnd() which will be called automagically
            
            // Check garbagecollection
            jpcache_gc();
            
            // Go generate page and wait for callback
            ob_start("jpcache_end");
            ob_implicit_flush(0);
        }
    }

    /* jpcache_end()
     *
     * This one is called by the callback-funtion of the ob_start. 
     */
    function jpcache_end($contents)
    {
        jpcache_debug("Callback happened");

        // We do a binary comparison of the first two bytes, see
        // rfc1952, to check wether the content is gzipped.
        $is_gzipped = (strncmp($contents,"\x1F\x8B",2)===0);


        // If the connection was aborted, do not write the cache.
        // We don't know if the data we have is valid, as the user
        // has interupted the generation of the page.
        // Also check if jpcache is not disabled
		// Also skip if content is less then 50 bytes (uncompressed) or 17 Bytes (comrpessed)
        if ((connection_aborted() ) || 
            ($GLOBALS["JPCACHE_ON"] == 0) || 
            ($GLOBALS["JPCACHE_TIME"] <= 0) || 
			( (strlen($contents)/(1+($is_gzipped*2))) < 50 ) )
        {
  			jpcache_debug("Skipped writing Cachefile. Returned as is.");
            return $contents;
        }

        // If it's compressed in the script, decompress first.
        if ($is_gzipped) { $contents = gzinflate(substr($contents, 10)); }

        $contents_snippet = substr($contents,0,50);

        $gzdata = ($GLOBALS["JPCACHE_USE_GZIP"]) ? gzcompress($contents, $GLOBALS["JPCACHE_GZIP_LEVEL"]) : $contents;

        $datasize = strlen($contents);
        $datacrc = crc32($contents);

        jpcache_debug("Writing cached data to storage");
        // write the cache with the current data
        jpcache_write($gzdata, $datasize, $datacrc, $contents_snippet);
        
        // Handle type-specific additional code if required
        jpcache_do_end();

        // Return flushed data
        return jpcache_flush($gzdata, $datasize, $datacrc);
    }

    /* jpcache_flush()
     *
     * Responsible for final flushing everything.
     * Sets ETag-headers and returns "Not modified" when possible
     *
     * When ETag doesn't match (or is invalid), it is tried to send
     * the gzipped data. If that is also not possible, we sadly have to
     * uncompress (assuming JPCACHE_USE_GZIP is on)
     */
    function jpcache_flush($gzdata, $datasize, $datacrc)
    {       
        // First check if we can send last-modified
        $myETag = "\"jpd-$datacrc.$datasize\"";
        header("ETag: $myETag");
        $foundETag = isset($_SERVER["HTTP_IF_NONE_MATCH"]) ? stripslashes($_SERVER["HTTP_IF_NONE_MATCH"]) : "";
        $ret = NULL;
        
        if (strstr($foundETag, $myETag))
        {
            // Not modified!
            if(stristr($_SERVER["SERVER_SOFTWARE"], "microsoft"))
    	    {
    	        // IIS has already sent a HTTP/1.1 200 by this stage for
    	        // some strange reason
                header("Status: 304 Not Modified");  
            } 
            else 
            {
                header("HTTP/1.0 304");
            }
        }
        else
        {
            // Are we gzipping ?
            if ($GLOBALS["JPCACHE_USE_GZIP"]) 
            {
                $ENCODING = jpcache_encoding(); 
                if ($ENCODING) 
                { 
                    // compressed output: set header. Need to modify, as
                    // in some versions, the gzipped content is not what
                    // your browser expects.
                    header("Content-Encoding: $ENCODING");
                    $ret =  "\x1f\x8b\x08\x00\x00\x00\x00\x00";
                    $ret .= substr($gzdata, 0, strlen($gzdata) - 4);
                    $ret .= pack('V',$datacrc);
                    $ret .= pack('V',$datasize);
                } 
                else 
                {
                    // Darn, we need to uncompress :(
                    $ret = gzuncompress($gzdata);
                }
            } 
            else 
            {
                // So content isn't gzipped either
                $ret=$gzdata;
            }
        }
		if ($GLOBALS['jpcachedata_type']!='') 
		{
			header('Content-Type: '.$GLOBALS['jpcachedata_type']);
		}
		$vary = ($GLOBALS['JPCACHE_XHTML_XML']) ? ', Accept' : '';
		header('Vary: Cookie'.$vary);
        header("Content-Length: ".strlen($ret));
        return $ret;
    }
    
?>
