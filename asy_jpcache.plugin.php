<?php

$plugin['name'] = 'asy_jpcache';

// Allow raw HTML help, as opposed to Textile.
// 0 = Plugin help is in Textile format, no raw HTML allowed (default).
// 1 = Plugin help is in raw HTML.  Not recommended.
# $plugin['allow_html_help'] = 1;

$plugin['version'] = '0.9.8';
$plugin['author'] = 'Sencer Yurdaguel';
$plugin['author_uri'] = 'http://www.sencer.de/';
$plugin['description'] = 'JPCache Integration. (This Admin-Plugin is only responsible for cleaning the cache. Click help, to see Installation Instructions.)';
$plugin['order'] = '5';
$plugin['type'] = '1';

if (!defined('PLUGIN_HAS_PREFS')) define('PLUGIN_HAS_PREFS', 0x0001); // This plugin wants to receive "plugin_prefs.{$plugin['name']}" events
if (!defined('PLUGIN_LIFECYCLE_NOTIFY')) define('PLUGIN_LIFECYCLE_NOTIFY', 0x0002); // This plugin wants to receive "plugin_lifecycle.{$plugin['name']}" events

$plugin['flags'] = '0';

if (!defined('txpinterface'))
        @include_once('zem_tpl.php');

# --- BEGIN PLUGIN CODE ---

// I stole plenty of code from zem. Don't tell him! ;)
	if (function_exists("register_callback")) {
		register_callback("asy_flush_event", "article", "edit");
		register_callback("asy_flush_event", "article", "create");
		register_callback("asy_flush_event", "link");
		register_callback("asy_flush_event", "page", "page_save");
		register_callback("asy_flush_event", "form", "form_save");
		register_callback("asy_flush_event", "list", "list_multi_edit");
		register_callback("asy_flush_event", "discuss");
		// We do not have a callback when comments are posted on the front_end
		// but that's ok, I hacked some magic into jpcache-main.php
	}

	// Add a new tab to the Content area.
	if (@txpinterface == 'admin') {
		register_tab("extensions", "asy_jpcache", "jpcache-cleaner");
		register_callback("asy_jpcachecleaner", "asy_jpcache");
	}

	// This is the callback-function when something in the Admin-Panel gets changed. (Wrapper)
	function asy_flush_event($event, $step) {
		if ( ($event==='article')
			 && (($step==='create') || ($step==='edit'))
       && ((count($_POST)==0) || (isset($_REQUEST['view']) && $_REQUEST['view']!='')) ) return;
		elseif (count($_POST)==0) return;
		$count = asy_flushdir(true);
	}

	// This is the Callback-Function for the Admin-CP
	function asy_jpcachecleaner($event, $step) {
		global $lastmod,$prefs,$path_to_site;
		// ps() returns the contents of POST vars, if any;
		if (ps("step") === "clean")
		{
			pagetop("JPCache Cleaner", ( (ps("asy_token") === (md5($lastmod)))
					? "Successful"
					: "Token expired. Please try again."));
			if (ps("asy_token") === (md5($lastmod)))
			{
				echo "<div align=\"center\" style=\"margin-top:3em\">";
				printf("Deleted %s files. Cache is clean.",''.asy_flushdir(true));
				echo "</div>";
			}
		} else {
			pagetop("JPCache Cleaner");
		}
		echo "<div align=\"center\" style=\"margin-top:3em\">";
		echo form(
			tag("JPCache-Cleaner", "h3").
			graf("Usually you don't need to do that. Cache is <b>automatically</b> cleared <br />1)
				  after a certain amount of time <br />2) when a comment is posted, edited or moderated
			      <br />3) after a page-template or form-tag is is modified.<br /><br />".
				fInput("hidden", "asy_token", md5($lastmod)).
				fInput("submit", "clean_cache", "Clean all cached Files", "smallerbox").
				eInput("asy_jpcache").sInput("clean")
			," style=\"text-align:center\"")
		);
		echo tag("Cache Statistics","h3");
		global $path_to_site;$count = array('size'=>0, 'num'=>0);
		$asy_cache_dir = $path_to_site .'/jpcache/cache';
		if (!empty($asy_cache_dir) and $fp = opendir($asy_cache_dir)) {
			while (false !== ($file = readdir($fp))) {
				if ($file{0} != ".") {
					$count['size'] += filesize("$asy_cache_dir/$file");
					++$count['num'];
				}
			}
			closedir($fp);
			printf("There are %d cache files with a total size of %d kb.", $count['num'], floor($count['size']/1000));
		} else { echo "Cache is empty.";}
		include $path_to_site .'/jpcache/jpcache-config.php';
/*		if (@$JPCACHE_TXPLOG_DO == 1 && $prefs['logging']=='all'){
			echo tag("Read-Write-Ratio<sup>1</sup>","h3");;
			$cachehits = safe_field('COUNT( id ) as hit', 'txp_log', "page LIKE '%#cachehit'");
			$totalhits = getThing("SELECT MIN(time) FROM ".PFX."txp_log WHERE page LIKE '%#cachehit'");
			$totalhits = getThing("SELECT COUNT(id) FROM ".PFX."txp_log WHERE time > '". $totalhits."'");
			printf("There were <b>%d</b> cache-reads recorded and <b>%d</b> possible cache-writes. <br />Average number of reads per write: <b>%01.2f</b>",$cachehits, $totalhits-$cachehits, (($totalhits-$cachehits) > 0) ? ($cachehits/($totalhits-$cachehits)) : '0');
			echo "<br /><br /><sup>1</sup>This is a (low) Approximation. Initially wait a week before numbers become meaningful.";
		}
*/		echo "</div>";
	}

	// This function clears the Cache directory. Make sure jpcache is installed in the right directory.
	function asy_flushdir($force_clean = false) {
		global $path_to_site, $lastmod;

		$count = 0;
		$asy_cache_dir = $path_to_site .'/jpcache/cache';

		if (!empty($asy_cache_dir) and $fp = opendir($asy_cache_dir)) {
			$last = strtotime($lastmod);
			while (false !== ($file = readdir($fp))) {
				if ($file{0} != "." AND
					 ((filemtime("$asy_cache_dir/$file") < $last) OR $force_clean)){
					@unlink("$asy_cache_dir/$file");
					++$count;
				}
			}

			closedir($fp);
		}

		return $count;
	}


# --- END PLUGIN CODE ---
if (0) {
?>
<!--
# --- BEGIN PLUGIN HELP ---
<h1><strong>asy_jpcache: JPCache Integration</strong></h1>

	<p>asy_jpcache is based on <a href="http://forum.textpattern.com/viewtopic.php?id=3195">zem_cache</a>, <a href="http://www.jpcache.com">jpcache</a> and some ideas from <a href="http://mnm.uib.es/gallir/wp-cache-2/">WP-Cache</a>.</p>

	<p>asy_jpcache will only cache <em>complete</em> pages (including your feeds). If you want to cache partial pages to retain some dynamic parts take a look at <a href="http://forum.textpattern.com/viewtopic.php?id=3195">zem_cache</a> which is perfect for that kind of thing.</p>

	<h2><strong>Installation</strong></h2>

	<ol>
	<li>Copy the directory <strong>jpcache</strong> and its contents into your main directory. It should be right next to your <em>images</em> and your <em>textpattern</em> directory (on a default install).</li>
		<li>Make sure that the <strong>jpcache/cache</strong> directory can be written to. Usually <em>chmod 777 jpcache/cache/</em> will do the trick.</li>
		<li>Edit your <strong>index.php</strong> in the main directory and BEFORE
  <pre>include $txpcfg['txpath'].'/publish.php';</pre>

insert the following line:

  <pre>include './jpcache/jpcache.php';</pre>

  (IMPORTANT: do <strong>NOT</strong> edit textpattern/index.php )
		<li>Don&#8217;t forget to activate this Admin-plugin.</li>
		<li>Optional: Look inside jpcache/jpcache-config.php to change a few settings, like enabling Debugging, permanently turning off gzip-encoding, changing timout, skipping feeds, re-enabling logging etc.</li>
	</ol>

	<p>If you are experiencing problems (or want to deactivate jpcache), comment out the line in your index.php that you added:<br />
</p>
  <pre>//include './jpcache/jpcache.php';</pre>


	<h3><strong>Details about how JPCache works</strong></h3>

	<p>Note: The files you received are a modified version of the original jpcache available at <a href="http://www.jpcache.com">jpcache.com</a>. Do <strong>NOT</strong> use this modified version for other stuff, please use the original jpcache version, because this here was hacked to serve textpattern.</p>

	<p>After a user requests a page, JPCache will save the result-page in compressed format in the cache-directory. For subsequent requests, JPCache will</p>
	<ul>
	<li>not serve a cached page, if the latest cached file is older than X seconds (default 900)</li>
		<li>send a 304 header it ETag-headers are set</li>
		<li>otherwise send the compressed page if gzip is supported</li>
		<li>or sent the uncompressed page if gzip is not supported</li>
		<li>delete a cached page, if a comment is posted at that URL</li>
	</ul>

	<p>asy_jpcache will also <strong>automatically clean the cache</strong> when</p>
	<ul>
	<li>comments are edited, deleted, moderated (for this and the following activate this plugin)</li>
		<li>articles are edited, posted, deleted</li>
		<li>forms or pages are edited.</li>
	</ul>

	<p>asy_jpcache will not cache requests made via POST (like comment previews).<br />
asy_jpcache will not cache file-downloads.</p>

	<h3><strong>FAQ</strong></h3>

	<p><strong>I have Problems with my Feeds</strong><br />
There have been reports that some people have occasionally problems with the RSS/Atom-Feeds. I was not able to reliable reproduce them to find a fix. I have implemented a few things in 0.9 that should solve those problems. If you do however have problems with Feeds, please report so in the Forum. In the meanwhile you can easily turn of caching of the Feeds in jpcache-config.php by setting $JPCACHE_SKIP_FEEDS to 1.</p>

	<p><strong>My Textpatterns access logs keep getting shorter</strong><br />
That&#8217;s a good thing, because when cached files are served it is usually not recorded in the db. However if you wish to keep recording hits in the db set $JPCACHE_TXPLOG_DO to 1. (Default is off, because cache-reads require no access to the db. Enabling this will lower performance a bit.)</p>

	<p><strong>I am using the XRT-Plugin</strong><br />
If you are using the XRT-Plugin (XML-RPC for Textpattern) by pixelmeadow, you are basically posting content to your blog without going through the Admin-Interface. Therefore the cache will not be cleaned right away. Due to the fact, that cache is periodically regenerated (default time 900 seconds), your changes may appear up to 900 seconds later on the actual site. You can shorten the default caching time in jpcache-config.php.</p>

	<p><strong>What happens with CSS-Requests</strong><br />
The stylesheets are not affected by jpcache, because they are served from a different file (not index.php). Since stylesheets usually do not change, my advice would be to put them in a regular (static) file and let the Webserver handle it (it&#8217;s more efficient).</p>

	<p><strong>Content-Type on PHP4</strong><br />
If you are using PHP4, asy_jpcache cannot get and cache the proper Content-Type of the generated page. The current workaround to this, is to take a look at the first 50 characters of the content and decide wether it&#8217;s a rss-feed, xml-feed, or text/html. This is fine for most Textpattern users. If for some reason you want to change those hardcoded headers or add more, take a look at $JPCACHE_DEFAULT_MIMETYPE in jpcache/jpcache-config.php and maybe the code in jpcache/file.php below the Line saying:<br />
//Content-Sniffing to set correct Content-Type, because headers were not available</p>

	<p><strong>I am serving application/xhtml+xml sometimes</strong><br />
(This assumes you already have a plugin that handles this like <a href="http://forum.textpattern.com/viewtopic.php?id=4634">phw_DTD</a> )<br />
If performance is <em>really</em>, <em>really</em> important, then don&#8217;t, because it complicates caching and lessens its benefits (at least the way it is implemented here). Otherwise:<br />
If you are using PHP 5 simply open jpcache/jpcache-config.php and set $JPCACHE_XHTML_XML to 1. This will add the Accept-Header to the caching-key, meaning that for each URI one cache-file for every different Accept-Header will be written. Done.<br />
If you are using PHP 4 <strong>in addition to the above</strong> you will also have to change the code that decides which Content-Type Header to send. There must be a line that is similar to header(&#8220;Content-Type: application/xhtml+xml; charset=utf-8&#8221;);. Add another line right there that sets the variable<br />
$GLOBALS[&#8216;JPCACHE_DEFAULT_MIMETYPE&#8217;] to something like &#8220;application/xhtml+xml; charset=utf-8&#8221;. That way, JPCache will know which Content-Type to associate with the request.</p>

	<p>Do you have further questions? Please ask in the <a href="http://forum.textpattern.com/viewtopic.php?id=8352">Textpattern Forum-Thread for this plugin</a>.</p>
# --- END PLUGIN HELP ---
-->
<?php
}
?>
