<?php // this file is automatically included by inPHP.php // intended as userspace	
#include('HotelExample.php'); // Hotel.php's content could have been here
	
// very safe, but very slow; delete these lines to use default local cache (APC)
// and default shared cache (Memcache backed by DB) if you have them installed.
\inPHP\Conf::set('Cache.shared', 'inPHP\Cache\NoSharedCache');
\inPHP\Conf::set('Cache.local', 'inPHP\Cache\NoLocalCache');
?>