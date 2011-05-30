<?php /** 
 * inPHP, declarative reflective performance oriented MVC framework with built-in ORM
 * Copyright (c) 2011 Jan Groothuijse, inLogic (inlogic.nl)
 * 
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * See <http://www.gnu.org/licenses/>.
 */ 
?><html><head><title>inPHP as glue sample</title></head>
<body><h1>inPHP as glue sample</h1><p>To show that the framework can be used as librairy of components</p>
<em>The source code behind this file is more usefull to view, the output is just to show it works.</em>
<h2>ORM</h2><p>The ORM is most likely the most usefull part of the framework when used as glue</p>
<?php include('inPHP.php'); // when included, the framework won't bite, just installs a __autoload();
use \inPHP\ORM\Model, \inPHP\ORM\DAO, \inPHP\ORM\DQ, \inPHP\ORM\MF;

// Some database settings the only 'required' configuration so far.
inPHP\Conf::set('DB.default', new inPHP\ORM\DB\DB('P:localhost', 'user', 'passwd', 'schema'));
// Not require, but usefull:
inPHP\Conf::set('MySQLDB.printQueries', true);
// very safe, but very slow; delete these lines to use default local cache (APC)
// and default shared cache (Memcache backed by DB) if you have them installed.
\inPHP\Conf::set('Cache.shared', 'inPHP\Cache\NoSharedCache');
\inPHP\Conf::set('Cache.local', 'inPHP\Cache\NoLocalCache');
/** A model definition */
final class Foo extends Model {	// final to stop auto adding a class attribute
	/** @inPHP\ORM\PrimaryKey; */
	protected $key;
	protected $value; // MetaTypes default to inPHP\ORM\String
}
	
try {	
	print '<table><caption><big><strong>Persisted&nbsp;Foo\'s</strong></big></caption>'.
												'<tr><th>Key</th><th>Value</th></tr>';
	// Some querying:	
	call_user_func(DAO::Foo()->query(DQ::select(MF::value())), function ($foo) { 
		print '<tr><td>'.$foo->key().'</td><td>'.$foo->value().'</td></tr>';
	});	
	
	print '</table>';
	// - DAO::Foo() returns a Data Acces Object to query persisted Foo objects
	// - query() returns a invokable resultset
	// - DQ::select() creates and returns a new Query object, using all supplied args as fields
	// - the invokable resultset of query() requires a function that takes the model as arg1 and 
	// 	 the entire record as arg2

	/** semanticly equivalent slightly more readable code: */	
	print '<table><caption><big><strong>Persisted&nbsp;Foo\'s</strong></big></caption>'.
												'<tr><th>Key</th><th>Value</th></tr>';
	$query = DQ::select(MF::value()); // Query to select the value attribute
	// pk (in this case `key`) is selected by default
	$dataAccesObject = DAO::Foo(); // A data acces object to query Foo's
	$fetcher = $dataAccesObject->query($query); // Queries database, returns a callback
	$fetcher(function ($foo) {print '<tr><td>'.$foo->key().'</td><td>'.$foo->value().'</td></tr>';});
	// execute the callback, which takes another callback as argument, which takes the $model as
	// argument
	print '</table>';
	
	if (isset($_GET['add'])) {
		// Insertion:	
		
		// Make a new Foo, asign it some values and insert it.
		$someFoo = new Foo(); $someFoo->key(uniqid())->value('somevalue');
		$insertResultCallback = $someFoo->insert();	
		
		// insert() returns a callback to get result of the query,
		
		// Updating someFoo:
		
		$updatedFoo = clone $someFoo; 
		$updateResultCallback = $updatedFoo->value('someOtherValue')->update($someFoo);
			
		// - update() takes the old version as an argument.
		// - the old version is used for its PK, so you can change pk values
		// - the old version is also used to detect which field are mutated, so those are SET
		// - the old version is also used to guard against lost updates, by using the old 
		//   values as WHERE clauses, it only updates if no else has update those fields in
		//   between
		// - To check if a record has been changed, use the rows() on the result.
			
		print "<p>Inserted ".$insertResultCallback()->rows()." row(s), updated ";
		print $updateResultCallback()->rows()." row(s) visible on next pageview</p>";	
		// you also can use it to get id();
		// see \inPHP\ORM\DB\MutationResult;
		
		// Query a single Model based on its id:
		$singleFetcher = DAO::Foo()->byPK(array($someFoo->key()), DQ::select(MF::value()));
		$singleFetcher(function ($foo) { print '<pre>'.print_r($foo, 1).'</pre>'; });	
		// - first arg is an array of all PK values, each item will be the expected value
		//   of one primary key field. 
		// - getByPK's 2nd arg is a query, of which only the fields and joins are important	
		// - getByPK returns a callback to get the results.
	}
	
	
} catch (Exception $e) {
	print '<h3>Caught '.get_class($e).' with message '.$e->getMessage().'</h3>';
	print '<pre>'.$e->getTraceAsString().'</pre>';	
	// When the DB reports an error, an inPHP\ORM\ORMException will be thrown.
	// when the configuration is incomplete, and ConfException will be thrown.
	// Normaly you should not use Exception, but a more specific type. 
}
?><a href="?add=random">Add a random Foo</a>
<h2>Reflection with annotations</h2><p>Annotations are used the ORM, but they could also be used
for logging, security, testing etc.</p><?php
	
/** Thin wrapper around PHP's reflection classes to give them annotations */ 
class AnnotatedReflection extends inPHP\Reflection\Annotated { 
	protected $nativeReflector;		
	function __construct($nativeReflector) {
		$this->nativeReflector = $nativeReflector;
		parent::__construct($nativeReflector->getDocComment());
	}
	function __call($name, $args) { 
		return call_user_func_array(array($this->nativeReflector, $name), $args); 
	}
}

// Some classes to reflect on:	
class LogToFile { private $file; function __construct($f) { $this->file = $f; } }

/** @Foo; */
class AuthorizationRequired { /** @LogToFile('/tmp/breach/'); */ function get() {}}

/** @LogToFile('/tmp/1'); Just don't use apetails and semicolons in your comments @Foo; */
class Bar {
	/** @AuthorizationRequired; @Foo; */
	function doSomethingDangerous() {}
	/** @AuthorizationRequired; */
	function doSomethingEvenMoreDangerous() {}
	function doSomethingHarmless() {}
}

// Show annotations using reflection:
print '<table>';
foreach (array('Bar', 'AnnotatedReflection', 'AuthorizationRequired') as $class)
{
	$reflector = new AnnotatedReflection(new ReflectionClass($class));
	print '<tr><td colspan="2"><h3><br />'.$class.' reflection</h3></th></tr>';
	print '<tr><th>Class annotations</th><td><ul>';
	foreach ($reflector->allAnnotations() as $annotation)
		print '<li>'.print_r($annotation, 1).'</li>';	 
	print '</ul></td></tr><tr><th>Method name</th><th>Method annotations</th></tr>';
	foreach (array_map(function ($methodReflector) { return new AnnotatedReflection($methodReflector); }, 
														$reflector->getMethods()) as $methodReflector) {
		print '<tr><td>'.$methodReflector->getName().'</td><td><ul>';
		foreach ($methodReflector->allAnnotations() as $annotation)
			print '<li>'.print_r($annotation, 1).'</li>';	
		print '</ul></td></tr>';
	}		
}
print '</table>';	
// - allAnnotations() is a function in Annotated that just returns all found annotations
// 	 when getting a specific type of annotation use the annotation($type) function instead
// - the array_map call rewraps the MethodReflectors from getMethods() with the AnnotatedReflection

?><h2>Dynamic xml wrapper</h2><p>Usefull when writing html, but not that fast.</p><?php
use \inPHP\View\XC;
print XC::table(XC::tr(XC::th('1').XC::th('2')))->border(1);
print XC::p('Because you don\'t have to write the close tag, it won\'t go foobar.')
															->style('color: blue;');
// just a testiment to the dynamicness of PHP
// - XC is Xml Container, calling a static function will give you an object representing
//   a container of with the tag you called the function with, the first arg will be considered
//	 its initial content, second will be the id, third the class.
// - To set html attributes one only needs to call a method with the corresponding name and 
//	 the wanted value of the attribute as arg.
?><h2>Seperated wrapped caching</h2><p>Since I don't believe in a caching with 'time to live', I have 
seperated caching in two for this framework. On one side, we have things that never expire unless we
change the code, like reflection and all products of reflection, or a really complex constructors. This
framework uses the fastest of caches for that, a local cache (APC, XCache) and no ttl's.
On the other hand we have perstisted data, we wan't to cache this to reduce load on the datastore, but we
never want stale data, and data will go stale when something changes in the datastore. For this we use
cache that can be shared among webservers, so when we want to delete something from the cache, it is gone 
for all servers.</p><p>Local cache and shared cache have interfaces, the Cache class load implementations
based on the configuration<?php

print '<h3>Localcache implementation: '.get_class(\inPHP\Cache\Cache::local()).'</h3>';
if ($one = \inPHP\Cache\Cache::local()->get('one'))
	print 'Local cache hit, '.$one;
else {
	print 'Local cache miss...caching \'one\' => '.$one=1;
	\inPHP\Cache\Cache::local()->set('one', $one);
}
$in = 'TwoValidness';
print '<h3>Sharedcache implementation: '.get_class(\inPHP\Cache\Cache::shared()).'</h3>';
if ($two = \inPHP\Cache\Cache::shared()->get('two')) {
	print 'Shared cache hit, '.$two.' deleting it by its invalidation key \''.$in.'\'';
	\inPHP\Cache\Cache::shared()->invalidate(array($in));
} else {
	print 'Shared cache miss...caching \'two\' => '.$two=2;
	\inPHP\Cache\Cache::shared()->set('two', $two, $in);
}	


?><p><small>2011 inLogic</small></p></body></html>