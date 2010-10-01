<?php
    // asy_jpcache configuration file  //Hacked for Textpattern1.0 r300 and up
 
    /**
     * General configuration options. 
     */
    $JPCACHE_SKIP_FEEDS   =   0;   // Set to 1, so jpcache skips your feeds 
    $JPCACHE_XHTML_XML    =   0;   // See help of admin-side plugin
    $JPCACHE_DEFAULT_MIMETYPE 
      = 'text/html; charset=utf-8';// only ever used for PHP4.x, 
								   // see help of admin-side plugin.

    $JPCACHE_TIME         =   900; // Default number of seconds to cache a page
    $JPCACHE_DEBUG        =   0;   // Turn debugging on/off
    $JPCACHE_ON           =   1;   // Turn caching on/off
    $JPCACHE_USE_GZIP     =   1;   // Whether or not to use GZIP
    $JPCACHE_GZIP_LEVEL   =   6;   // GZIPcompressionlevel to use (1=low,9=high)
    $JPCACHE_CLEANKEYS    =   0;   // Set to 1 to avoid hashing the storage-key:
                                   // then you can easily see cachefile-origin.

    $JPCACHE_TXPLOG_DO    =   0;   // Set to 1, if you want to enable txp-logging.
								   // Disabled by default for performance reason. 
								   // Make sure your index.php loks like this!
								   // 	include './textpattern/config.php'; 
								   // 	include './jpcache/jpcache.php';
								   // Include jpcache after config.php


    /**
     * Please DO NOT change these variables
     */
    $JPCACHE_IGNORE_DOMAIN=   0;   // Ignore domain name in request(single site)
    $JPCACHE_POST         =   0;   // Should POST's be cached (default OFF)
    $JPCACHE_GC           =   0;   // Probability % of garbage collection
    $JPCACHE_TYPE 		  = "file";

    /**
     * File based caching setting.
     */
    $JPCACHE_DIR          = dirname(dirname(__FILE__))."/jpcache/cache"; 
    				   // Directory where jpcache must store 
                                   // generated files. Please use a dedicated
                                   // directory, and make it writable
    $JPCACHE_FILEPREFIX   = "jpc_";// Prefix used in the filename. This enables
                                   // us to (more accuratly) recognize jpcache-
                                   // files.
?>
