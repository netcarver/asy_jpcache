<?php

    $JPCACHE_VERSION="v2";

/*
  jpcache
  Copyright 2001 - 2003 Jean-Pierre Deckers <jp@jpcache.com>

  This program is free software; you can redistribute it and/or
  modify it under the terms of the GNU General Public License
  as published by the Free Software Foundation; either version 2
  of the License, or (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
*/

/*
 Credits:

    Based upon and inspired by:
        phpCache        <nathan@0x00.org> (http://www.0x00.org/phpCache)
        gzdoc.php       <catoc@163.net> and <jlim@natsoft.com.my> 
        jr-cache.php    <jr-jrcache@quo.to>

    More info on http://www.jpcache.com/

 */
 
    // Set the includedir to the jpcache-directory
    $includedir = dirname(__FILE__);

    // Configuration file
    require "$includedir/jpcache-config.php";
    
    // Standard functions
    require "$includedir/jpcache-main.php";
       
    // Type specific implementations
    require "$includedir/$JPCACHE_TYPE.php";

    // Start caching
    jpcache_start();
?>