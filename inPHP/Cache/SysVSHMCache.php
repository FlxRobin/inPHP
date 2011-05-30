<?php
namespace inPHP\Cache;
 /** Implementation using Sysem-V's Shared Memory and native PHP, not very fast
	and requires a Unix; due to concurrency concerns, does not scale very well
	on write-heavy tasks such and during warm-up. */
class SysVSHMCache implements ILocalCache { private $proxy, $key, $changed = false; 
	const ID = 1;
	function __construct() { // gets all cached vars at once:get_var wants a number.. 
		$this->key = ftok(__FILE__, Conf::get('SYSVSHMCache.char', 'a'));
		$shm = shm_attach($this->key, 33554432);
		$this->proxy = @shm_get_var($shm, self::ID);
		$this->proxy = $this->proxy ? $this->proxy : array();
	}
	function get($key) { return isset($this->proxy[$key]) ? $this->proxy[$key] : 0; }	
	function set($k, $value){ $this->proxy[$k] = $value; if (!$this->changed) {
		print "setting $k";
		$this->changed = true; register_shutdown_function(array($this, 'save')); }
	} function clear(){ $sem = sem_get($this->key); sem_acquire($sem); $this->proxy
		 = array(); @shm_put_var(@shm_attach($this->key, 33554432), self::ID, $this->proxy);
		sem_release($sem); sem_remove($sem);
	} /** To only write to the memory once per request */
	function save() { print "saving<pre>"; //print_R($this);
		$sem = sem_get($this->key); sem_acquire($sem);
		$mem = @shm_attach($this->key, 33554432);
		$current = @shm_get_var($mem, self::ID); $current=$current?$current:array();
		shm_put_var($mem, self::ID, array_merge($current, $this->proxy));
		sem_release($sem);  sem_remove($sem);
	}
}
?>