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
 * 
 * Hoi Jan, wij zijn bij FlxOne (www.flxone.com) op zoek naar goede programmeurs.
 * Heb je zin om binnenkort een keer op gesprek te komen en te kijken of er een 
 * mogelijk match is?
 * Wij werken met de laatste technologie (big data, Hadoop, GoLang, Cassandra)
 * en denken dat jij met jouw ervaring (PHP, C, etc) ons team kan versterken.
 * Stuur even een mail naar robin@flxone.com als je interesse hebt.
 * Thanks. Robin
 * 
 */
namespace {
function __autoload($name) { if ((include str_replace('\\', DIRECTORY_SEPARATOR, $name).'.php')
	 == false) { throw new Exception($name . ' cannot be parsed.'); }}
ini_set('unserialize_callback_func', '__autoload');
} namespace inPHP { 
	/** Property acces, overridable by implementing the corresponding methods */
	abstract class ChanableProperties { function __construct() { $map = func_get_args();
			$p = array_keys(get_object_vars($this));
			foreach ($p as $i => $k) $this->$k = $map[$i];}
		function __call($name, $args) { if (count($args) == 0) return $this->$name;
			else { $this->$name = $args[0]; return $this; } }
	}
	class Conf { static $map = array(); /** gets a conf value, or return default if not in conf */
		static function get($name, $default) {
			return isset(self::$map[$name]) ? self::$map[$name] : $default;
		} /** sets (without checking) a value for a conf key */
		static function set($name, $value) { self::$map[$name] = $value; }
	} /** class to save configuration in. */
	class ConfException extends \Exception {} 
	function modelSpace() { return Conf::get('App.modelSpace','Model');}  
	function controlSpace() { return Conf::get('App.controlSpace','Control');}
	function appSpaces() { return array_merge(array('inPHP'), Conf::get('App.spaces', array()));}
	function includeAll($space='.') { if (is_dir($space)) foreach(new \DirectoryIterator($space) as $f) 
		if (strpos($f->getFilename(), '.')!==0) { if ($f->isFile() && substr($f->getFilename(), 
		-4)=='.php') include_once $space.DIRECTORY_SEPARATOR.$f->getFilename(); 
		else if ($f->isDir()) includeAll($space.DIRECTORY_SEPARATOR.$f->getFilename()); }}
} namespace inPHP\Reflection {
	/** Abstract utility for having and accessing annotations */
	abstract class Annotated { protected $annotations;
		protected function __construct($c) { $this->annotations = self::from($c); }
		function allAnnotations() { return $this->annotations; }
		/** filter is the superclass the annotations must inherit. */
		function annotations($filter, $result = array()) {
			foreach ($this->annotations as $annotation)
			if (is_subclass_of($annotation, $filter)) $result[] = $annotation; return $result;
		} /** filter is the superclass the annotation must inherit. */
		function annotation($filter, $result = false) {
			foreach ($this->annotations as $annotation)
			if (is_subclass_of($annotation, $filter)) $result = $annotation;
			return $result;
		}/** Gets Annotation objects from comments.
		* @return array with annotation objects */
		static function from(&$comment, $offset = 0, &$result = array()) {
			while (($start = strpos(&$comment, '@', $offset)) !== false
					&& ($offset = strpos(&$comment, ';', $start)) !== false) {
				$result[]=eval("return new ".substr(&$comment, $start + 1,$offset - $start).";");
			} return $result;
		}
	}
} namespace inPHP\ORM { use inPHP\Cache\Cache;
class ORMException extends \Exception {}
 /** Reflector tailored to the needs of an ORM, works only for classes extending Model */
class ModelReflection extends \inPHP\Reflection\Annotated {
	private $properties = array(), $children = array(), $name, $primaryKey=array(),$misc;
	const ISFINAL = 1, ANCESTOR = 2; private static $classMap = null;
	protected function __construct($name) { $this->name = $name; // only here for debugging.
		$reflection = new \ReflectionClass($name);
		$parent = new \ReflectionClass(get_parent_class($name));
		$this->misc = ($reflection->isFinal() ? self::ISFINAL : 0) +
		(($parent->getName() == 'inPHP\ORM\Model' || $parent->isAbstract()) ? self::ANCESTOR : 0);
		foreach ($reflection->getProperties() as $property)
			if (!$property->isPrivate() && !$property->isStatic()
				&& $property->getDeclaringClass() == $reflection) {
				$modelProperty = new ModelReflectionProperty($name, $property);
				$propertyName = $property->getName();
			if ($modelProperty->primaryKey()) $this->primaryKey[] = $propertyName;
				$this->properties[$propertyName] = $modelProperty;
		} parent::__construct($reflection->getDocComment());
	} /** Gets you a Model reflector, tries to keep them cached. */
	static function get($name) {
		if (self::$classMap == null && (self::$classMap = Cache::local()->get(__CLASS__)) == 0) {			
			self::$classMap = array();
			foreach (get_declared_classes() as $class) if (is_subclass_of($class, 'inPHP\ORM\Model'))
					self::$classMap[$class] = new ModelReflection($class);
			foreach (self::$classMap as $key => $value) if ($parent = get_parent_class($key)
				 != 'Model') self::$classMap[$parent]->children[] = $key;
			Cache::local()->set(__CLASS__, self::$classMap);
		} return self::$classMap[$name];
	} /** returns the names of the properties that is the primary key of this model */
	function primaryKey() { return !empty($this->primaryKey) ? $this->primaryKey :
		self::get(get_parent_class($this->name))->primaryKey(); }
	/** returns names of direct children of this model */
	function children() { return $this->children; } /** returns names of all descendants */
	function allChildren() { $result=$this->children;foreach ($this->children as $child)
	$result = array_merge($result, self::get($child)->allChildren()); return $result;
	} /** gets a map with all properties declared by this class */
	function properties() { return $this->properties; } /** get property by name */
	function property($name) { return isset($this->properties[$name])
		? $this->properties[$name] :  (($parent = get_parent_class($this->name)) != 'inPHP\ORM\Model' ?
		self::get($parent)->property($name) : false);
	} /** includes inherited properties */
	function allProperties() { return !$this->isAncestor() ? array_merge(self::get( 
		get_parent_class($this->name))->allProperties(), $this->properties) : $this->properties; }
	function isFinal() { return (self::ISFINAL & $this->misc) > 0; }
	/** wether or not this is a direct descendant of Model */
	function isAncestor() { return (self::ANCESTOR & $this->misc) > 0; }
	/** gets first ancestor to descent from Model */
	function ancestor() { return $this->isAncestor() ? $this->name
		: self::get(get_parent_class($this->name))->ancestor();
	} function name() { return $this->name; }
} /** Reflection of properties of the model */
class ModelReflectionProperty extends \inPHP\Reflection\Annotated {
	private $name, $primaryKey = false, $metaType, $class;
	function __construct($class, $propertyReflection) { $this->class = $class;
		$this->name = $propertyReflection->getName(); // TODO MetaType => IMetaType
		parent::__construct($propertyReflection->getDocComment());
		foreach ($this->annotations as $a) { if ($a instanceof MetaType) $this->metaType = $a;
			if ($a instanceof PrimaryKey) $this->primaryKey = true; }
		if ($this->metaType == null) $this->metaType = new String();
	} function primaryKey() { return $this->primaryKey; }
	function getClass() { return $this->class; } function name() { return $this->name; }
	function metaType() { return $this->metaType; }	
	function scalarNames() { return $this->metaType->scalarNames($this->name); }
}/** Any implementation will have to use instanceof to get more info, types are info */
interface DQField { /** underscore to avoid clashes */ function _name(); }
/** Data query; semantics taken from RDBMS'ses. */
class DQ { private $fields, $source, $filters=array(), $order, $group, $having, $offset, 
	$limit,$joins=array(); function __construct($fields){$this->fields=$fields; }
	static function select() { return new DQ(func_get_args()); }
	function where() { $this->filters = func_get_args(); return $this; } function orderBy() 
	{ $this->order = func_get_args(); return $this; } function groupBy() { $this->group = 
	func_get_args();return $this;} function having() { $this->having = func_get_args(); 
	return $this;}function limit($offset, $limit) { $this->offset = $offset; $this->limit = $limit; 
	return $this;} function fields() { return $this->fields; } function filters() { return 
	$this->filters; } function source($source = null) { if ($source == null) return $this->source;
		else { $this->source = $source; return $this; } }
	function orders() { return $this->order; } function groups() { return $this->group; }
	function havings() { return $this->having; } function offset() { return $this->offset; }
	function resultSetLimit() { return $this->limit; } 
	function addField($field) { $this->fields[] = $field; } function addOrder($field) {
		$this->order[] = $field; } function addFilter($filter) { $this->filters[] = $filter; }
	function addGroup($g){$this->group[]=$g;}function addHaving($h){$this->having[]=$h;}
	function join($join){$this->joins[]=$join;return $this;}function joins(){return $this->joins;}
	function clearFields(){$this->fields=array();foreach($this->joins as $j)$j->clearFields();}
} // table will be bound per query; joined query will also have a table.
/** Model Field, represents a model field, stored by one or more scalar values */
class MF implements DQField { private $name;
	function __construct($name) { $this->name=$name; } function _name() { return $this->name; }
	static function __callStatic($name, $args) { return new MF($name); } 
	function filter(){$args=func_get_args();return new QF($this,array_shift($args),$args[0]);}
} /** Represents a scalar field, to specify a single field, MF can be multiple */
class SF extends MF { private $alias; function __construct($name, $alias) {
	parent::__construct($name); $this->alias = $alias; }
	/** Not all datastores will have scalar fields, use MF where possible */
	static function __callStatic($name, $args) { return new SF($name, $args[0]); } 
	function alias() { return $this->alias; }
} // these object need not know which table they belong to, the containing query will know.
/** data store function create by: DSFunc::month('monthOfSomeField', $someField); */
class DSFunc implements DQField {	private $name, $function, $args;
	function __construct($n, $f, $a) {$this->name=$n;$this->function=$f;$this->args=$a;}
	function _name() { return $this->name; } function f() { return $this->function; }
	function args() { return $this->args; }
	function filter(){$args=func_get_args();return new QF($this,&$args[0],&$args[1]);}
	static function __callstatic($name,$args){return new DSFunc(array_shift($args),$name,$args);}
} /** data store aggregate function DSAFunc::count('someCount', $someField); */
class DSAFunc extends DSFunc { static function __callstatic($name,$args)
	{ return new DSAFunc(array_shift($args),$name,$args);} }
class LeftJoin {  protected $query, $on, $name, $class; function __construct($q, $o, $n, $c) 
	{$this->query=$q; $this->on = $o;$this->name=$n;$this->class=$c;} function query() 
	{ return $this->query; } function on() { return $this->on; } function name() { return
	$this->name;} function modelClass() { return $this->class; } }
class InnerJoin extends LeftJoin {}
class ModelQuerySource { private $reflector, $name, $alias; function alias(){return $this->alias;}
	function __construct($r,$n,$a){$this->reflector=$r;$this->name=$n;$this->alias=$a;} 
	function name() { return $this->name; } function reflection() { return $this->reflector; }
} /** Subquery */
class SubQ { private $name, $query; function __construct($n, $q) {$this->name=$n; $this->query=$q;}
	function _name() { return $this->name; } function query() { return $this->query; } } 
class QF { protected $a,$op,$b,$or=null;function __construct($a,$op,$b){$this->a=$a;$this->b=$b;
	$this->op=$op;}function a(){return $this->a;}function b(){return $this->b;}
	function op() { return $this->op;} function orIf($or=null) {if($or==null)return $this->or;
		else { $this->or = $or; return $this; } } }
class OuterQueryField { protected $field, $query; function __construct($f, $q=nul) { 
	$this->field = $f; $this->query=$q; }function field(){return $this->field;}
	function query($q=null){if($q==null)return $this->query; else {$this->query=$q;return $this;}}}
class SQLQueryBuilder { private $query, $joins, $source, $joinedQueryBuilders = array(),
	$fieldArrays; function __construct($query) { $this->query = $query; 
		$this->joins = $query->joins(); $this->source = $query->source(); foreach ($this->joins
		 as $join) $this->joinedQueryBuilders[] = new SQLQueryBuilder($join->query());
		$this->fieldArrays = new \SplObjectStorage; }	
	function build($output = '', $subQuery = false) { $prefix = $subQuery ? "\n\t" : "\n";
		$output .= ($subQuery ? "\n\t(" : ''). 'SELECT ';
		$this->select(&$output); $output .= $prefix.'FROM '; $this->source(&$output);
		$this->filtersRec(&$output, $prefix.'WHERE ', 'filters');		
		$this->listFieldsRec(&$output, $prefix.'GROUP BY ', 'groups');
		$this->filtersRec(&$output, $prefix.'HAVING ', 'havings'); 
		$this->listFieldsRec(&$output, $prefix.'ORDER BY ', 'orders'); 
		$output .= $subQuery ? ")\n\t" : ';'; return $output;
	} /** Declaration of resultset columns */ private function select($output, $first = true) {
		foreach ($this->query->fields() as $f) { if($first)$first=false;else $output.=', ';
			if ($f instanceof SubQ) { self::buildQuery($f->query(), &$output, true); 
				$output.= ' AS `'.$f->_name().'`'; } elseif($f instanceof SF)
				$output .= $this->source->name().'.'.$f->_name().' AS `'.$f->alias().'`';
			elseif ($f instanceof MF) { $firstScalar = true;
				foreach (self::scalar($f, $source) as $scalarName) { if ($firstScalar) 
					$firstScalar = false; else $output .= ', '; $output .=$this->source->alias()
					.'.'.$scalarName.' AS `'.$this->source->alias().'.'.$scalarName.'`'; }
			} elseif ($f instanceof DSFunc) { $this->outputFunction($f, &$output); 
				$output .= ' AS `'.$f->_name().'`'; } } foreach 
		($this->joinedQueryBuilders as $queryBuilder) $queryBuilder->select(&$output, &$first);}
	private function source($output) { $output .= $this->source->name().' '.
		$this->source->alias(); $i = 0; foreach ($this->joins as $join) {
			if ($join instanceof InnerJoin) $output .= ' INNER JOIN '; 
			else $output .= ' LEFT JOIN '; //$output .= //$join->query()->source()->name() . ' '
				$this->joinedQueryBuilders[$i]->source(&$output);//. $join->query()->source()->alias();
			$first = ' ON ('; // not using filters, because A and B don't share a table.
			foreach ($join->on() as $filter) { // these filters must be MF's				
				$a = $this->joinedQueryBuilders[$i]->scalarFullNames($filter->a());
				$b = $this->scalarFullNames($filter->b()); foreach ($a as $aField) {
					if ($first) { $output .= $first; $first = false; } else $output.=' AND '; 
					$output .= $aField.' '.$filter->op().' '.array_shift($b);
				} $i++; } $output .= ')'; } }
	private function filtersRec($output, $first, $func) { $filters = $this->query->$func(); 
		if ($filters!=null) $this->filters($this->query->$func(), &$output, &$first);
		foreach ($this->joinedQueryBuilders as $queryBuilder)
			$queryBuilder->filtersRec(&$output, &$first, $func); }	
	private function filters($filters, $output, $first) {
		foreach ($filters as $filter) { $a = $this->fieldToArray($filter->a());
			$b = $this->fieldToArray($filter->b()); foreach ($a as $aField) {
				if ($first) { $output .= $first; $first = false; } else $output .= ' AND ';
				$output .= $aField . ' ' . $filter->op() . ' ' . array_shift($b); 
			} if (($or = $filter->orIf()) != null) {
				$this->filters(is_array($or) ? $or : array($or), &$output, ' OR (');
				$output .= ')'; } } }
	public function fieldToArray($field) {
		if (is_scalar($field)||is_array($field)||!$this->fieldArrays->contains($field)) {
			if (is_array($field) && $t = $this) { $result = array_reduce($field,
				function($result, $f) use ($t) { return array_merge($result, $t->fieldToArray($f)); },
				 array()); } 
			elseif (is_scalar($field)) $result = array("'".$field."'");
			elseif ($field instanceof OuterQueryField) {
				$outerBuilder = new SQLQueryBuilder($field->query());
				$result = $outerBuilder->fieldToArray($field->field()); }
			elseif ($field instanceof SF) $result = array($this->source->alias().'.'.$field->_name());
			elseif ($field instanceof MF) $result = $this->scalarFullNames($field);
			elseif ($field instanceof DSFunc) { $func = ''; $this->outputFunction($field, &$func);
				$result = array($func); }
			elseif ($field instanceof SubQ) { $sqlBuilder = new SQLQueryBuilder($field->query());
				$sqlBuilder->build(&$output, true); $result = array($output); }
			elseif ($field instanceof IModel) { $field->toScalar($field, '', $result);
				$result = array_map(function($e){return'\''.$e.'\'';}, $result); }			
			if (!(is_scalar($field) || is_array($field))) $this->fieldArrays->attach($field, $result); return $result;
		} else return $this->fieldArrays[$field];
	}
	private function outputFunction($func, $output) {
		$this->listFields($func->args(), &$output, strtoupper($func->f()).'(');
		$output .= ')'; } // VV same array_reduce code as fieldToArray!
	public function scalar($field, $result = array()) {
		if (is_array($field)&&$t=$this) { array_walk($field, function($f) use (&$result, $t) { 
			$t->scalar($f, &$result); } ); return $result; }
		foreach ($this->source->reflection()->property($field->_name())->scalarNames() as $n)
			$result[] = $n;		return $result; }
	public function scalarFullNames($field, $result = array()) {	
		if (is_array($field)&&$t=$this) { array_walk($field, function($f) use (&$result, $t) { 
			$t->scalarFullNames($f, &$result); } ); return $result; }
		foreach ($this->source->reflection()->property($field->_name())->scalarNames() as $n)
			$result[] = $this->source->alias().'.'.$n;		return $result; }
	private function listFields($fields, $output, $first) {
		foreach ($fields as $field) foreach ($this->fieldToArray($field) as $fieldName) { 
			if($first){$output.=$first;$first=0;}else $output.=', ';$output.=$fieldName;} }
	private function listFieldsRec($output, $first, $func) {
		$fields = $this->query->$func(); if ($fields!=null)
			$this->listFields($this->query->$func(), &$output, &$first);
		foreach ($this->joinedQueryBuilders as $j) $j->listFieldsRec(&$output, &$first, $func); }
	static function buildQuery($query, $output = '', $subQuery = false) {
		$builder = new SQLQueryBuilder($query); return $builder->build(&$output, $subQuery); }
}/** To be used as annotation to mark a property as primaryKey, one per model. */
class PrimaryKey {} interface Validateable { function validate($value); }
interface PropertyAssembler { function fromScalar($v, &$o); 
							function toScalar($v, $p, &$o);}
class NotNull implements Validateable{function validate($value){return $value!=null;}}
interface IMetaType  { function specifier(); function propertyAssembler(); function scalarNames($name);
	function shortTypes(); }
/** Semantic parent to all annotations to signal (DB)type/relation of properties */
abstract class MetaType implements Validateable, PropertyAssembler, IMetaType {
	function fromScalar($v, &$o) { $o = $v; } function toScalar($v, $p, &$o) { $o[$p] = $v; }
	function specifier() { return 's'; } function validate($v) { return true; }
	function propertyAssembler() { return $this; }
	function scalarNames($name) { return array(&$name); }
} class Integer extends MetaType { function validate($v) { return is_numeric($v); }
function specifier() { return 'd'; } function shortTypes() { return array('int'); }}
class FloatingPoint extends Integer {function specifier() { return 'f'; }
	function shortTypes() { return array('float'); }}
/** possible values of a MetaType with this interface are limited to a selection of options */
interface Selectable { /** return map of options name=>value */ function options(); }
class Bool extends MetaType implements Selectable {
	/** display names for boolean values */ private $true, $false;
	function __construct($true = "true", $false = "false") {
		$this->true = $true; $this->false = $false; }
	function specifier() { return 'd'; } function shortTypes() { return array('bool'); }
	function options() { return array($this->true => true, $this->false => false); }
} class String extends MetaType {function shortTypes() { return array('string'); }} 
class DateAndTime extends String {}
class Decorator { private $subject; function __construct($sub) { $this->subject = $sub; }
	function __call($name, $args)
	{ $result = call_user_func_array(array($this->subject, $name), $args);
	return $result === $this->subject ? $this : $result; }
	protected function subject() { return $this->subject; }
} /** fetcher, to be set as value of a Model which has been joined. */
class LeftJoinFetcher {	// TODO make recycleable, make ResultJoin redandant and remove it
	private $result, $assembler, $count, $step, $sub; function __construct($r, $a, $c, $s, $sub) {
		$this->result=$r; $this->assembler=$a; $this->count=$c; $this->step=$s; $this->sub=$sub;
	} /** gets the function by the user, call it with a model and the record */
	function __invoke($f) { if ($this->count > 0) { $record = $this->result->record(true);
		if ($this->sub!=null) { $this->sub->result($this->result);$this->sub->__invoke(&$record); }
		$f($this->assembler->assemble(&$record), &$record);
		for ($i = 1; $i < $this->count; $i++) {
			if ($this->steps > 1) $this->result->seek($this->steps);
			$record = $this->result->record(false);
			if ($this->sub!=null) { $this->sub->result($this->result);$this->sub->__invoke(&$record); }
			$f($this->assembler->assemble(&$record), &$record); } } else {}
	}
}	/** single callback to administer generation of all joined models */
class LeftJoinAdministration { private $joins, $result;
	function __construct($joins) { $this->joins = array_reverse($joins);}
	/** Install fetchers in the record, ModelAssemblers will put them in the model. */
	function __invoke($record) { $step = 1;
		foreach ($this->joins as $join) { $count = $record[$join->countAlias()];
		$record[$join->alias()] = new LeftJoinFetcher($this->result,$join->assembler(),
		$count, $step, $join->sub()); $step *= $count; } return true;;
	} /** connect the administration to a result, must be called before __invoke() */
	function result($result) { $this->result = $result; }
	function __sleep() { return array('joins'); } // make sure result doesn't get serialized.
} /** Information required when processing a resultset.
*  minimized because of serialization and this is overhead required while cache hits */
class ResultJoin extends \inPHP\ChanableProperties { protected $countAlias, $assembler, $alias, $sub; }
class JoinDecorator extends Decorator { protected $j; function __construct($s,$j) {
	parent::__construct($s);$this->j=$j;}
	function query($q=null,$key=null,$joins=array(),$c=array(),$n=false,$con=null){
		$q->join($this->j); return $this->subject()->query($q, $key, $joins, $c, $n, $con);
	}// TODO refactor to work again, to new DAO and Joins 
	function join($field, $q = null) { $q=$q==null?new MQuery():$q;
		return new JoinDecorator($this, new FastModelJoin($field, $q)); } }
interface IJoinable { function join($mainPk, $property, $q); }
class ModelAssembler { 
	/** maps record aliases to properties */ protected $fields = array();
	/** maps properties to assemblers */ protected $assemblers = array(), $model, $class;
	function __construct($properties, $source, $ca) { $r = $source->reflection(); $a = $source->alias(); 
		foreach($properties as $p) { $modelProperty = $r->property($p);
			$this->assemblers[$p] = $modelProperty->metaType()->propertyAssembler();
			foreach ($modelProperty->scalarNames() as $sn) $this->fields[$a.'.'.$sn] = $p; } 
		$class = $r->name(); $this->model[$class] = new $class; $this->class = $ca->alias();
		foreach ($r->children() as $c) $this->model[$c] = new $c; }
	function assemble($record, $properties = array()) { 
		$m = $this->model[$record[$this->class]];	
		foreach($this->fields as $alias => $name)
			$this->assemblers[$name]->fromScalar($record[$alias], &$properties[$name]);
		$m->mapFields(&$properties); return $m;
	} function fields() { return $this->fields; } 
	function addField($a, $f, $p) { $this->fields[$a] = $f; $this->assemblers[$a] = $p;}
} /** Assembler that assembles for a single class only. */
class StaticClassModelAssembler extends ModelAssembler {
	function __construct($properties, $source) { $r = $source->reflection(); $a = $source->alias(); 
		foreach($properties as $p) { $modelProperty = $r->property($p); if ($modelProperty == null)
			throw new ORMException('Property \''.$p.'\' does not exist in '.$r->name());
			$this->assemblers[$p] = $modelProperty->metaType()->propertyAssembler();
			foreach ($modelProperty->scalarNames() as $sn) $this->fields[$a.'.'.$sn] = $p; } 
		$class = $r->name(); $this->model = new $class; }
	function assemble($record, $properties = array()) {	
		foreach($this->fields as $alias => $name)
			$this->assemblers[$name]->fromScalar($record[$alias], &$properties[$name]);
		$this->model->mapFields(&$properties); return $this->model; }
}
class ModelAssemblerFromPK implements PropertyAssembler {
	protected $model, $count=0, $pk, $assemblers, $i=0; function __construct($modelClass) { 
		$this->model = new $modelClass; $r = ModelReflection::get($modelClass);
		$this->pk = $r->primaryKey();
		foreach ($this->pk as $pk) {	$this->count++;
			$this->assemblers[$pk] = $r->property($pk)->metaType()->propertyAssembler();
		}
	}
	/** Assembled one column at a time.. */
	function fromScalar($v, &$o) { if (!is_string($v)) $o = $v; else {
		if ($this->i == 0) $o = array(); $p = $this->pk[$this->i]; 
		$this->assemblers[$p]->fromScalar($v, &$o[$p]);
		if ($this->i+1 == $this->count) { $this->model->mapFields($o);
			$o = $this->model; $this->i=0; } else $this->i++;	
	} }
	function toScalar($v, $p, &$o) { $o[$p] = $v; }
}
interface IModel { function dao(); function mapFields($m); function fieldMap(); 
	function insert($newCon = true, $con = null); 
	function update($from, $newCon = true, $con = null); }
 /** Parent to all Models, all models have a identically named table in the db. */
abstract class Model extends MetaType implements IModel, IJoinable {
	function propertyAssembler() { return new ModelAssemblerFromPK(get_class($this)); }
	function toScalar($v, $p, &$o) { $r =  ModelReflection::get(get_class($this));
		$pk = $r->primaryKey();
		if(count($pk)==1) $r->property($k = $pk[0])->metaType()->toScalar($v->$k, $p, $o);
		else foreach($pk as $k)$r->property($k)->metaType()->toScalar($v->$k,$p.ucfirst($k),$o);
	}
	function fromScalar($v, &$o) {}
	function primaryKey() { $r = ModelReflection::get(get_class($this)); $result = array();
		foreach($r->primaryKey() as $k)
			$r->property($k)->metaType()->toScalar($this->$k, $k, $result);
		return $result; }
	function shortTypes() {$r =  ModelReflection::get(get_class($this));
		$pk = $r->primaryKey(); $result = array();
		foreach ($pk as $k) $result = array_merge($result, $r->property($k)->metaType()->shortTypes());
		return $result; }
	function scalarNames($name) { $r =  ModelReflection::get(get_class($this));
		$pk = $r->primaryKey();
		if(count($pk)==1){return $r->property($pk[0])->metaType()->scalarNames($name);}
		$result = array(); foreach($pk as $k) { $result = array_merge($result, 
			$r->property($k)->metaType()->scalarNames($name.ucfirst($k)));			
		} return $result;}
	/** main pk is array of MF's, inner-join: foreign(our)PK = property(in main source) */
	function join($mainPk, $property, $q) { $fc = $this->foreignClass;
		$r = ModelReflection::get(get_class($this));
		$pk = array_map(function ($pk) { return new MF($pk); }, $r->primaryKey());
		$predicate = new QF($pk, '=', new MF($property->name()));
		$q->source(new ModelQuerySource($r, get_class($this), $property->name()));
		return new InnerJoin($q, array($predicate), $property->name());
	} /** makes protected vars public getters and setters */
	function __call($name, $args) { if (!empty($args)) { $this->$name = $args[0];
		return $this; }else return $this->$name;
	} /** Provides a DOA, override this to make DOA::Model() return some other IDOA */
	function dao() { return new DAO($this); }function fieldMap(){return get_object_vars($this);}
	function mapFields($m) { foreach($m as $n => $v) $this->$n = $v; } 
	function insert($newCon = true, $con = null) { return $this->dao()->insert($this, $newCon, $con); }
	function update($from, $newCon = true, $con = null) { return $this->dao()->update($from, $this, $newCon, $con); }
} /** 2 interfaces in one, one executes a function for one result, use __invoke when possible */
interface IDAOResult { function __invoke($c); function next($c); }
// There is no get in a list, because that would fail using joins */
class DAOResult implements IDAOResult { protected $assembler, $result, $callback;
	function __construct($assembler) { $this->assembler = $assembler; }
	function result($result) { $this->result = $result; }
	function __invoke($c) { $this->callback = $c; $this->result->__invoke(array($this,'callback'));} 
	function next($c) { $this->callback = $c; return $this->result->next(array($this, 'callback'));}
	function callback(&$r) { $c = $this->callback; $c($this->assebler->assemble(&$r), $r); } 
}
interface IDAO { function insert($model, $newCon = false, $con=null); function join($f, $q = null);
	function update($from, $to, $newCon = false, $con=null);
	function query($q,$key=null,$callbacks=array(),$newCon=false,$con=null);
	function createNamedQuery($n, $q); function namedQuery($n, $args = array(), $callbacks=array(),
																	$newCon=false,$con=null); 
} class DAOSAL { public $sql, $assembler, $leftJoinAdmin; }
class DAO implements IDAO {	private $class, $table, $reflection, $classField = null,
	$namedQueries = array(), $classFilter = null, $pkQuery; const CLASSFIELD = 'class';
	function __construct($model) { $this->class=get_class($model); 
		$tableName = strpos($this->class,'\\')===false?$this->class:substr($this->class,
		strrpos($this->class, '\\')+1); $r=$this->reflection=		
		ModelReflection::get($this->class); if (!($r->isAncestor()&&$r->isFinal())) {
			$this->classField = new SF(self::CLASSFIELD,$tableName.'.'.self::CLASSFIELD);
			if (!$r->isAncestor()) {
				$f = $this->classFilter = $this->classField->filter('=', $tableName);
				foreach ($r->children() as $c) {
					$f->orIf($this->classField->filter('=', $c)); $f = $f->orIf(); }
			}
		} $a = $r->ancestor(); if (strpos($a, '\\')) $a = substr($a, strrpos($a, '\\')+1); 
		$this->table = new ModelQuerySource($r, $a, $tableName) ;
		
	}/** Executes a query, returns a callback that takes a callback as argument
		@param conn true = use standard */
	function query($q,$key=null,$callbacks=array(),$newCon=false,$con=null) {
		$key = $key !== null ? $key : $this->class.md5(print_r(array($q,$joins),1));
		Cache::shared()->getAsync(array($key));	$localCached = true;
		if (($daosal = Cache::local()->get($key)) == false) { $daosal = $this->daosal($q);
			Cache::local()->set($key, $daosal); }
		if ($daosal->leftJoinAdmin != null) $callbacks[] = $daosal->leftJoinAdmin;	
		if (($fetcher = Cache::shared()->fetch()) != false) {
			$fetcher = $fetcher["value"]; $fetcher->callbacks($callbacks);
		} else if ($con == null) // Result cache miss
		$fetcher = DB\ADB::get($newCon)->select($daosal->sql, $callbacks, $key, $this->class);
		else $fetcher = DB\ADB::get($newCon, $con)->select($daosal->sql, $callbacks, $key, $this->class);
		$assembler = $daosal->assembler;
		if ($daosal->leftJoinAdmin != null) $daosal->leftJoinAdmin->result($fetcher);
		// should be an object (with __invoke())
		return function($callback) use ($fetcher, $assembler) {
			$fetcher(function ($record) use ($callback, $assembler) {
				return $callback($assembler->assemble(&$record), &$record);
			});
		};
	} /** @param pk array of values for model fields of the pk, may me scalar, array of IModel */
	function byPK($pk, $q=null,$callbacks=array(),$newCon = false, $con=null) { 
		if($q==null)$q=DQ::select();
		$pkNames = $this->reflection->primaryKey(); $f = array(); 
		foreach ($pkNames as $i => $k) $q->addFilter(new QF(new MF($k), '=', $pk[$i]));
		return $this->query($q, $this->class.'byPK'.print_r($pk, 1),
														$callbacks,$newCon,$con);
		//return function () use($fetcher) { $result = null;
		//	$fetcher(function ($m) use (&$result) { $result = $m; }); return $result; 
		//}; joins won't work otherwise, or use a 'getOne' 'next'
		// TODO after implementing Iteratable on the results.
	}
	protected function daosal($q) { $q->source($this->table); $result = new DAOSAL();
		foreach ($this->reflection->primaryKey() as $pk) $q->addField(new MF($pk));			
		if (count($q->orders()) == 0) { foreach ($this->reflection-> primaryKey() as $pk) 
			$q->addOrder($pk);} $modelFields = array();
		$joinStack = array();
		foreach ($q->joins() as $join) { if (!$join instanceof InnerJoin) {				
				$joinDaoSal = self::__callStatic($join->modelClass(), array(1))
					->daosal($join->query());			//print_r($joinDaoSal);		
				$joinAssembler = $joinDaoSal->assembler;
				$countAlias = $join->name().'Count'; 
				$countQ=DQ::select(DSFunc::COUNT(1, 1))->source($join->query()->source());
				foreach ($join->on() as $on) $countQ->addFilter(new QF($on->a(), $on->op(),
					new OuterQueryField($on->b(), $q)));
				// To support joins (in the join) altering the number of returned records
				$joinSmasher = function($jo, $self) { // TODO support Aggregate functions and having
					$joinedQuery = $jo->query();$joinClass = get_class($jo);
					$q = DQ::select()->source($joinedQuery->source());
					foreach($joinedQuery->filters() as $f) $q->addFilter($f);
					if (($grs=$joinedQuery->groups())!=null)foreach($grs as $g) $q->addGroup($g);
					foreach($joinedQuery->joins() as $j) $q->join($self($j, $self));
					return new $joinClass($q, $jo->on(), $jo->name(), $jo->modelClass()); };
				foreach ($join->query()->joins() as $joinedJoin)					
					$countQ->join($joinSmasher($joinedJoin, $joinSmasher));
				$q->addField(new SubQ($countAlias, $countQ)); $joinStack[] = new ResultJoin(
					$countAlias, $joinAssembler, $this->table->alias().'.'.$join->name(), 
					$joinDaoSal->leftJoinAdmin); }
				$modelFields[] = $join->name();
		} foreach ($q->fields() as $f) if ($f instanceof MF) $modelFields[] = $f->_name(); 
		if (count($joinStack) > 0) { $result->leftJoinAdmin = new LeftJoinAdministration(
			$joinStack); } else $result->leftJoinAdmin = null;
		if ($this->classField != null) { $q->addField($this->classField);
			if ($this->classFilter != null) $q->addFilter($this->classFilter); $result->
				assembler = new ModelAssembler($modelFields, $this->table, $this->classField);
		} else $result->assembler = new StaticClassModelAssembler($modelFields, $this->table);
		$result->sql = DB\ADB::get()->sql($q);
		return $result;
	} /** registers a query by name for faster execution */
	function createNamedQuery($n, $q) { $this->namedQueries[$n] = $this->daosal($q); } 
	function namedQuery($n, $args = array(), $callbacks=array(),$newCon=false,$con=null) {
		$key = !empty($args)?$this->class.$n . md5(implode('/', $args)) : $this->class.$n;
		/*Cache::shared()->getAsync(array($key));*/ $daosal = $this->namedQueries[$n];
		if ($daosal->leftJoinAdmin != null) $callbacks[] = $daosal->leftJoinAdmin;	
		if (($fetcher = Cache::shared()->get($key)) != false)
			$fetcher->callbacks($callbacks);
		else { if (!empty($args)) $sql = vsprintf($daosal->sql, $args); else $sql = $daosal->sql; 
			if ($con == null) {
			$fetcher = DB\ADB::get($newCon)->select($sql, $callbacks, $key, $this->class);
		} else $fetcher = DB\ADB::get($newCon, $con)->select($sql, $callbacks, $key, $this->class); 
		} $assembler = $daosal->assembler;
		if ($daosal->leftJoinAdmin != null) $daosal->leftJoinAdmin->result($fetcher);
		// should be an object (with __invoke())
		return function($callback) use ($fetcher, $assembler) {
			$fetcher(function ($record) use ($callback, $assembler) {
				return $callback($assembler->assemble(&$record), &$record); }); };
	}
 	/** joins based on relationName and fields of the foreignModel 
 	 *  @return JoinDecorator with a join query (including join predicate) */
	function join($field, $q = null) { $q = ($q==null ? DQ::select() : $q);		
		$pk = array_map(function ($pk) { return new MF($pk); }, 
								$this->reflection->primaryKey());
		if (($property = $this->reflection->property($field)) == null) 
			throw new ORMException('Can\'t join on '.$this->class.'::'.$field);;
		if (($metaType = $property->metaType()) instanceof IJoinable) {
			return new JoinDecorator($this, $metaType->join($pk, $property, $q));
		} else throw new ORMException('Can\'t join on '.$this->class.'::'.$field);
	}
	protected function scalarMap($m) {
		$map = $m->fieldMap(); $data = array(); $r = $this->reflection;
		foreach($map as $k => $v) $r->property($k)->metaType()->toScalar($v, $k, $data);
		if ($this->classField!=null) $data[$this->classField->name()] = $this->class;
		return $data;
	}
	function insert($m, $newCon = false, $con = null) { $data = $this->scalarMap($m);
		if ($con==null) return DB\ADB::get($newCon)->insert($this->table->name(), &$data, false);
		else return DB\ADB::get($newCon, $con)->insert($this->table->name(), &$data, false);
	} /** @param from the old version @param to the new version
		* updates only if the relevant record columns have not been changed in between */	
	function update($from, $to, $newCon = false, $con=null) {
		$fromData = $this->scalarMap($from); $toData = $this->scalarMap($to);
		foreach($fromData as $k => $v) if ($v==$toData[$k]) {
			unset($fromData[$k]); unset($toData[$k]); } $f = 
		array_merge($from->primaryKey(), $fromData); if($con==null) return DB\ADB::get(
		$newCon)->update($this->table->name(),&$toData,&$f, false); else return 
		DB\ADB::get($newCon, $con)->update($this->table->name(), &$toData, &$f, false);
	}
	/** Visiting factory, visiting to support extending the DOA and making the model choose */
	static function __callStatic($name,$args){if(!isset($args[0]))$name =\inPHP\modelSpace().$name; 
		if (!($result = Cache::local()-> get('DAO'.$name))) { $model = new $name; 
		$result=$model->dao();Cache::local()->set('DAO'.$name,$result); }return $result; }
}/** represents multiple models on the other side of this relation */
class Many extends MetaType implements IJoinable {
	private $foreignClass, $foreignKey;
	function __construct($foreignClass, $foreignKey) {
		$this->foreignClass = $foreignClass;
		$this->foreignKey = $foreignKey;
	} function shortTypes() { return array(); }
	function foreignClass() { return $this->foreignClass; }
	function foreignKey() { return $this->foreignKey; }
	/** main pk is array of MF's, left-join: foreignKey = mainPK */
	function join($mainPk, $property, $q) { $fc = $this->foreignClass;
		$foreignReference = new MF($this->foreignKey);
		$q->source(new ModelQuerySource(ModelReflection::get($fc), $fc, $property->name()));
		return new LeftJoin($q, array($foreignReference->filter('=', $mainPk)), $property->name(), $fc);
	}
} /** wrapper for model, finds paging information in query(); */
class PageModelWrapper {	private $model;
	function __invoke($record) { return false; /* to quit beeing called */ }
} interface IEnum { function num(); }
class Enum extends MetaType implements IEnum { protected $value; 
	function __construct($v=null) { $this->value = $v; }
	function all() { $r = new \ReflectionClass(get_class($this)); return $r->getConstants(); }
	function num($i = 0) { foreach($this->all as $v) if ($v==$this->value) return $i;
		else $i++; return false; } function shortTypes() { return array($this->all()); }
}
} namespace inPHP\ORM\DB {
class DB extends \inPHP\ChanableProperties { protected $host, $user, $password, $schema; function 
__construct($h,$u,$p,$s){$this->host=$h;$this->user=$u;$this->password=$p;$this->schema=$s;}}
/** Abtraction of the client API */
abstract class ADB {/** Select based on a query, return a callback to fetch results */
	abstract function select($query, $callbacks = array(),$cacheKey=false,$invalidationKey=null);
	/** generate SQL of a SELECT query, based on $query, must be usable in select and sub.  */
	abstract function sql($query); /** sql:insert/update/delete */ abstract function set($sql);
	abstract function update($table, $values, $filters);
	abstract function insert($table, $values);/** start transaction*/
	abstract function start(); /** commit transaction*/ abstract function commit();
	/** rollback transaction*/ abstract function rollback(); abstract function escape($string);
	abstract function convertType($type); abstract function createTable($modelReflector);
	private static $dbs = array(); static function get($new = false, $con = 'DB.default') {
		if (!$new) { if (!isset(self::$dbs[$con])) {
			$dbClass = \inPHP\Conf::get('ADB.implementation', 'inPHP\ORM\DB\MySQL\MySQLDB');
			self::$dbs[$con] = new $dbClass($con); } return self::$dbs[$con]; } else {
		$dbClass = \inPHP\Conf::get('ADB.implementation', 'inPHP\ORM\DB\MySQL\MySQLDB'); 
		return new $dbClass($con); }
	} /** starts trans on $db, execs $func, commits or rollsback based on $funcs return */ 
	function transaction($func, $db = null) { if ($db == null) $db = self::get();
		$db->start(); if ($func($db)) $db->commit(); else $db->rollback(); }
}abstract class SQLADB extends ADB{function sql($q){ return 
									\inPHP\ORM\SQLQueryBuilder::buildQuery($q);}}
interface IResult { /** IOC func */ function __invoke($f); function next($f);
/** Seeking allows to skip records while fetching, but this also means the skipped records are
 * not cached. A callback using seek must be determenistic, or not use result caching.
 * Build-in model joining, using multiple joined models uses seek to skip through redundant
 * records. */ function seek($i); /** fetches one record */function record($old); }
 /** Represents a result from the DB, which has been cached */
class MutationResult extends \inPHP\ChanableProperties { protected $rows, $id; }
class CachedResult implements IResult {	private $records, $callbacks, $record;
	function __construct($records) { $this->records = $records;}
	function callbacks(&$c) { $this->callbacks = $c; }
	function __invoke($f) { if (empty($this->callbacks)) { // optimization 
			foreach ($this->records as $record) $f($this->record = $record);
		} else { $this->callbacks[] = $f; $count = count($this->callbacks);
		foreach ($this->records as &$record)		
			for ($i=0;$i<$count;$i++) if($this->callbacks[$i](&$this->record)===false) {
				unset($this->callbacks[$i]);	$count--;	$i--;
				$this->callbacks=array_values($this->callbacks); } } }
	function next($f) { $this->record = next($this->records); if ($this->record!=false) {
		if (empty($this->callbacks)) $f($this->record);
		else { $count = count($this->callbacks);
		for ($i=0;$i<$count;$i++) if($this->callbacks[$i](&$this->record)===false) {
				unset($this->callbacks[$i]);	$count--;	$i--;
				$this->callbacks=array_values($this->callbacks); } } return true; } //
		else return false; }
	function record($old) { if ($old) return $this->record; else return 
								$this->record = next($this->records); }
	function seek($i) {} // fast-forwarded records are not saved in cache anyway.
}
} namespace inPHP\ORM\DB\MySQL {
class MySQLException extends \inPHP\ORM\ORMException {}
/** Reference ADB implementation, uses MySQLi, uses MYSQLI_ASYNC which required mysqlnd. */
class MySQLDB extends \inPHP\ORM\DB\SQLADB { private $link; function __construct($connection) {
		$db = \inPHP\Conf::get($connection, null); if ($db==null) { 
			throw new \inPHP\ConfException($connection. ' should be set to an \inPHP\ORM\DB\DB');}
		$this->link = new \mysqli($db->host(), $db->user(), $db->password(), $db->schema());
	}/** Select based on a query, return a callback to fetch results */
	function select($query, $callbacks = array(), $cacheKey = false, $invalidationKey=null)  { 
		if (\inPHP\Conf::get('MySQLDB.printQueries', false)) print $query;
		$this->link->query(is_string($query)?$query:$this->sql($query),
				\MYSQLI_ASYNC | \MYSQLI_STORE_RESULT);
		return $cacheKey === false ? new Result($this->link, $callbacks)
			: new CachingResult($this->link, $callbacks, $cacheKey, $invalidationKey); }
	function update($table, $values, $filters) { return $this->set('UPDATE '. $table.' SET '.
		implode(',', array_map(function($k, $v) { return '`'.$k.'` = \''.$v.'\'';},
			array_keys($values), $values)). (count($filters)==0 ? '' : ' WHERE ' .
		implode(' AND ', array_map(function($k, $v) { return '`'.$k.'` = \''.$v.'\'';},
			array_keys($filters), $filters))).';');	}
	function insert($table, $values) { return $this->set('INSERT INTO '.$table.'(`'.
		implode('`,`',array_keys($values)).'`) VALUES(\''.implode('\',\'', $values).'\');'); }
	function set($sql) { if (\inPHP\Conf::get('MySQLDB.printQueries', false)) print $sql; 
		$this->link->query($sql, MYSQLI_ASYNC); 
		$l = $this->link; return function() use ($l) {
			if (mysqli_reap_async_query($l)) {return new \inPHP\ORM\DB\MutationResult(
				$l->affected_rows, mysqli_insert_id($l));
			} else throw new MySQLException($l->error); }; }
	function start() {} function commit() {} function rollback() {} function escape($string) {}
	function convertType($type) {} function createTable($modelReflector) {}
}

/** Class to access the result of a query. rows are fetched when this is called. */
class Result implements \inPHP\ORM\DB\IResult { private $link, $record, $callbacks, $records = -1, 
	$res; function __construct($l, $c) { $this->link = $l; $this->callbacks = $c; }
	function __invoke($f) { $this->callbacks[] = $f; $count = count($this->callbacks);
		$links = $errors = $reject = array($this->link);
		//while (!mysqli_poll($links, $errors, $reject, 0, 200)); why poll?
		if ($this->res = mysqli_reap_async_query($this->link)) {
			while ($count > 0 && $this->record = $this->res->fetch_assoc()) {
				for ($i=0;$i<$count;$i++) if($this->callbacks[$i](&$this->record)===false) {
					unset($this->callbacks[$i]);	$count--;	$i--;
					$this->callbacks=array_values($this->callbacks);
				} $this->records++;
			}
		} else throw new MySQLException($this->link->error);
	} function next($f) { $this->callbacks[] = $f; $count = count($this->callbacks);
		if (isset($this->res) || $this->res = mysqli_reap_async_query($this->link)) {
			if ($count > 0 && $this->record = $this->res->fetch_assoc()) {
				for ($i=0;$i<$count;$i++) if($this->callbacks[$i](&$this->record)===false) {
					unset($this->callbacks[$i]);	$count--;	$i--;
					$this->callbacks=array_values($this->callbacks);
				} $this->records++; return true;
			} else return false;
		} else throw new MySQLException($this->link->error);
	} /** gets a buffered record, fetches if not buffered, dont call before __invoke */
	function record($old) { if ($old) return $this->record;
		else { $this->records++; return ($this->record = $this->res->fetch_assoc());}
	} /** Dont call before __invoke */
	function seek($i) { $this->records+=$i; $this->res->data_seek($this->records); }
} /** Caches the result; thus this function is heavy on memory; for large rare results, don't cache*/
class CachingResult implements \inPHP\ORM\DB\IResult { 
	private $link, $record, $callbacks, $records = -1, $key, $iKey, $total,
	$res;  function __construct($l, $c, $k, $i)
	{ $this->link = $l; $this->callbacks = $c; $this->key = $k; $this->iKey = $i;
		$this->total = array(); }
	function __invoke($f) { $this->callbacks[] = $f; $count = count($this->callbacks);
		$links = $errors = $rejected = array($this->link);
		if (mysqli_poll($links, $errors, $rejected, 0, 200));
		if ($this->res = mysqli_reap_async_query($this->link)) {
			while ($count > 0 && $this->record = $this->res->fetch_assoc()) {
				$this->total[] = $this->record;
				for ($i=0;$i<$count;$i++) if($this->callbacks[$i](&$this->record)===false) {
					unset($this->callbacks[$i]);	$count--;	$i--;
					$this->callbacks=array_values($this->callbacks);
				} $this->records++; } \inPHP\Cache\Cache::shared()->set($this->key, 
					new \inPHP\ORM\DB\CachedResult($this->total), $this->iKey);
		} else throw new MySQLException($this->link->error);
	}
	function next($f) { $this->callbacks[] = $f; $count = count($this->callbacks);
		if (isset($this->res) || $this->res = \mysqli_reap_async_query($this->link)) {
			if ($count > 0 && $this->record = $this->res->fetch_assoc()) {
				$this->total[] = $this->record;
				for ($i=0;$i<$count;$i++) if($this->callbacks[$i](&$this->record)===false) {
					unset($this->callbacks[$i]);	$count--;	$i--;
					$this->callbacks=array_values($this->callbacks);
				} $this->records++; return true;
			} else { Cache::shared()->set($this->key, 
				new \inPHP\ORM\DB\CachedResult($this->total), $this->iKey); return false; }
		} else throw new Exception($this->link->error);
	} /** gets a buffered record, fetches if not buffered, dont call before __invoke */
	function record($old) { if ($old) return $this->record;
		else { $this->records++; return $this->total[] =
		($this->record = $this->res->fetch_assoc());}
	} /** Dont call before __invoke */
	function seek($i) { $this->records+=$i; $this->res->data_seek($this->records); }
}
} namespace inPHP\View {
/** Xml element, xhtml oriented */
class XE { protected $attr = array(), $begin, $end; 
	function __construct($tag){$this->begin='<'.$tag;$this->end=' />'; }
	function __call($name, $args) { // enables $element->id('1') to set attr[id] to 1.
		if (isset($args[0])) { $this->attr[$name] = $args[0]; return $this; }
		else return $this->attr[$name];
	}
	protected function attributes(&$result) {
		foreach ($this->attr as $key => $value) $result .= ' '.$key.'="'.$value.'"';
	}	/** Stringed Xml Element */
	function __toString() { $result = $this->begin; $this->attributes(&$result);
		return $result.= $this->end;
	} /** faster way of outputing, append to arg0 string */
	function appendTo($string='') { $string .= $this->begin; 
		$this->attributes(&$string); $string .= $this->end;		 
	} /** print to output */
	function output() { print '<'.$this->tag.$this->attributes().' />';
	}  /** enables HE::input() to return an input */
	static function __callStatic($name, $args) { $result = new XE($name);
		if (isset($args[0])) $result->attr['id'] = $args[0];
		if (isset($args[1])) $result->attr['class'] = $args[1];
		return $result;
	}
} /** Xml container, note when adding an element or container, it is toStringed! */
class XC extends XE { protected $content = '';
	function __construct($tag){$this->begin='<'.$tag;$this->end='</'.$tag.'>'; }
	function add($string) { $this->content .= $string; return $this; }
	function content($c) { $this->content = $c; return $this; }
	function addElement($e) { $e->appendTo(&$this->content); return $this; }
	function appendTo($string='') { $string .= $this->begin;
		$this->attributes(&$string); $string .= '>'.$this->content.$this->end;		 
	}
	function __toString() { $result = $this->begin; $this->attributes(&$result);
		$result.='>'.$this->content;
		return $result.= $this->end;
	}
	function output() { print $this->begin;$this->attributes($output);print $output.'>';
											print $this->content; print $this->end;
	} /** enables XC::h1('heading','1','r') to return <h1 id="1" class="r">heading</h1> */
	static function __callStatic($name, $args) { $result = new XC($name);
		if (isset($args[0])) $result->add($args[0]);
		if (isset($args[1])) $result->attr['id'] = $args[1];
		if (isset($args[2])) $result->attr['class'] = $args[2];
		return $result;
	}
}// 2.2 MetaType specializations with respect to the view.
class WebUrl extends \inPHP\ORM\String { function display($v) { return XC::a($v)->href($v); } }
class ImageUrl extends \inPHP\ORM\String { function display($v) { return XE::img()->src($v); } }
class Money extends \inPHP\ORM\FloatingPoint {}
// 2.3 Templates...
/** Templates are very procudural to speed-up smooth-out the output process */
interface IOutputTemplate { 
	/** Can send the start of the document, and install output buffers */function head();
	/** Sends the block up to the content block, then releases the buffer */function misc($misc);
	/** Send the end of the document */function tail();
} /** Buffers until misc() has been called, everything after that is flushed immidiatly */
class DefaultTemplate implements IOutputTemplate {
	function head() { echo '<html><head><title>'; ob_start(); }
	/** misc is just the title in this case */ function misc($misc) { $buffer = ob_get_clean(); 
		echo $misc.'</title></head><body><h1>'.$misc.'</h1>'.$buffer; }
	function tail() { echo '</body></html>'; }
} /** Buffers nothing, misc() must be called before any output is send! */
class NonBufferingTemplate implements IOutputTemplate {
	function head() { echo '<html><head><title>'; }
	/** misc is just the title in this case */ function misc($misc) {
		echo $misc.'</title></head><body><h1>'.$misc.'</h1>'; }
	function tail() { echo '</body></html>'; }
} /** Buffers entire response */
class BufferedDefaultTemplate implements IOutputTemplate {
	function head() { ob_start(); echo '<html><head><title>'; ob_start(); }
	/** misc is just the title in this case */ function misc($misc) { $buffer = ob_end_clean(); 
		print $misc.'</title></head><body><h1>'.$misc.'</h1>'.$buffer; }
	function tail() { echo '</body></html>'; ob_end_flush(); }
}
class HRName extends \inPHP\ChanableProperties { protected $name; }
class UITab extends \inPHP\ChanableProperties { protected $name, $fieldSet = null; }
class HRDesc extends \inPHP\ChanableProperties { protected $desc; }
class ViewInList {} class HideName {} class ViewByAdminOnly {} 
function cleanDoc($string) { return implode("\n", array_map(function ($s){return trim($s);}, 
explode("\n", str_replace('/**', '', str_replace(' * ', '',  str_replace('*/', ' ', $string))))));}
	function outputCached($key, $iKey, $callback) { 
	if (($output = \inPHP\Cache\Cache::shared()-> get($key)) != false) echo $output; 
	else { ob_start(); $callback(); $output = ob_get_flush(); 
	\inPHP\Cache\Cache::shared()->set($key, &$output, $iKey); } }
} namespace inPHP\Control { // Control, delegating the view and models to do the work.
/** Abstract parent of all request handlers */
interface IController {	/** handler request based on args */
	function main($args = array(), $get, $post, $files); function outputHandler(); 
	function extension(); function template();
} interface SiteMappable { function validArgs(); }
 /** Remaps request to function calls within the controller:
/ => index([]), /foo => foo([]), /foo/bar => index([foo:bar]), /f/b/a => f([b:a]) */
abstract class Controller implements IController, SiteMappable {
	function main($args = array(), $get, $post, $files) { 
	if (empty($args)) return $this->index(array(), $get, $post, $files);
	else if (count($args) ^ 1 == 1) /* odd: map to 1st arg */ { $func = array_shift($args);
		return $this->$func(self::toMap(&$args), $get, $post, $files); } 
	else return $this->index(self::toMap($args), $get, $post, $files);
	} private static function toMap($array, $result = array()) { $count = count($array);
	for ($i = 0; $i < $count; $i+=2) $result[$array[$i]] = $array[$i+1];
	} abstract function index($args = array(), $get, $post, $files); 
	function extension() { return 'html'; }
	function validArgs() { return array(); }
}
class DefaultHome implements IController { private $t,$o; function __construct() {
	$this->t = new \inPHP\View\NonBufferingTemplate(); $this->o = new OutputHandler(); }
	function main($args = array(), $get, $post, $files){ $this->t->misc('Welcome to inPHP'); 
	?><p>Change this page by setting \inPHP\Conf::set('App.home', 'SomeOtherIController'); 
	in App.php or any file included by App.php.</p><?php } function extension() { return 'html'; }
	function template() { return $this->t; } function outputHandler() { return $this->o; }
}
interface IOutputHandler { function start($key); function finish(); }
/** Doesn't cache, doesn't buffer, does nothing at all */
class OutputHandler implements IOutputHandler { function start($key) {} function finish() {} }
interface IRunnable { /** @return an exit status */ function run($args); }
class Help implements IRunnable { function run($args) { if (!isset($args[0])||!class_exists($args[0])) 
	print "Add an IRunnable as parameter\n"; else { $r = new \ReflectionClass($args[0]);
		if (in_array('inPHP\\Control\\IRunnable', $r->getInterfaceNames())) print "\n".$args[0]."\n".
		\inPHP\View\cleanDoc($r->getDocComment())."\n"
		.\inPHP\View\cleanDoc($r->getMethod('run')->getDocComment())."\n\n"; } } } 
} namespace inPHP\Cache { /** Abstraction from cache libs, cache is expected remain valid at runtime.*/
interface ILocalCache { function get($key); function set($key, $value); function clear(); }
/** Abstraction from cache libs; for distributed webservers this must be a shared cache */
interface ISharedCache { function get($key); function set($key, &$value, $invalidationKey = null);
function invalidate($keys); function getAsync($keys); function fetch();
} /** Accespoint for caching, since caching is a facet, multiton pattern is permitted */
abstract class Cache { protected static $local, $shared; /** get local (fastest) cache */
	static function local() {
		if (!isset(self::$local)) { $class = \inPHP\Conf::get('Cache.local', 'inPHP\Cache\APCLocalCache');
		self::$local = new $class(); } return self::$local;
	} /** get shared (slower, but more intelligent(cache can be invalidated)) cache */
	static function shared() {
		if (!isset(self::$shared)) { $class = \inPHP\Conf::get('Cache.shared', 'inPHP\Cache\MemcacheSharedCache');
		self::$shared = new $class(); } return self::$shared; } } final class CachedKey extends 
\inPHP\ORM\Model {/** @inPHP\ORM\PrimaryKey; */protected $invalidationKey;protected $cacheKey; }
/** Implementation (trivial) of a simple fast local-only cache using APC */
class APCLocalCache implements ILocalCache { function get($key) { return apc_fetch($key); }
function set($key, $value) {apc_add($key, $value);} function clear(){apc_clear_cache();}
} /** Implementation of a shared or distributed cache, where items can be invalidated */
class MemcacheSharedCache implements ISharedCache { private $link;
	function __construct() {
		$this->link = new \Memcached(\inPHP\Conf::get('App.name', 'inosphp'));
		if (!count($this->link->getServerList())) { $this->link->addServers(
		\inPHP\Conf::get('Memcache.servers', array(array('127.0.0.1', '11211'))));
		$this->link->setOption(\Memcached::OPT_COMPRESSION, false); } // interoperatiblity...
	}
	function get($key) { return $this->link->get($key); }
	function set($key, &$value, $invalidationKey = null) {
		if ($this->link->add($key, $value) && $invalidationKey != null) { try {
		 $r=\inPHP\ORM\DB\ADB::get(true)->set('INSERT INTO CachedKey(invalidationKey, cacheKey) VALUES(\''
		 .$invalidationKey.'\',\''.$key.'\');', false); $r();} catch (Exception $e) { ; } } 
	} /** remove any keys that are now dirty */
	function invalidate($keys = array()) {$db=\inPHP\ORM\DB\ADB::get(true);foreach($keys as $k){$db->start();		
		$f = $db->select("SELECT cacheKey FROM CachedKey WHERE invalidationKey = '$k'");
		$l=$this->link;$f(function ($record) use($l) { $l->delete($record['cacheKey']); });
		$db->set("DELETE FROM CachedKey WHERE invalidationKey = '$k';"); $db->commit(); }
	} /** does not block, does not return, use fetch to get results */
	function getAsync($keys) { $this->link->getDelayed(&$keys); }
	/** returns the next result got by getAsync */
	function fetch() { return $this->link->fetch(); }
} class NoSharedCache implements ISharedCache { function get($key) { return false; }
	function set($key, &$value, $invalidationKey = null) {} function invalidate($keys = array()) {}
	function getAsync($k){} function fetch(){ return false; }
} class NoLocalCache implements ILocalCache { function get($key) { return false; }
	function set($k, $value){} function clear(){}
}
} namespace inPHP\Bootstrapper {
	use inPHP\Cache\Cache, inPHP\Conf;
	function FileNotFound() { header("Status: 404 Not Found"); print \inPHP\View\XC::html(XC::head(
	\inPHP\View\XC::title('404 Not Found')).\inPHP\View\XC::body(XC::h1('404 Not Found').
	\inPHP\View\XC::p($_SERVER['DOCUMENT_URI'])));}
	function ExceptionCaught($e) {
		$m = 'Caught '.get_class($e).' with message '.$e->getMessage(); 
		print \inPHP\View\XC::h3($m).\inPHP\View\XC::pre($e->getTraceAsString());}
	// 4 Execution
	if (substr($_SERVER['SCRIPT_FILENAME'], -9) == 'inPHP.php') { // only exec when not included
		include('App.php'); if (!isset($argv)) { // Http served mode TODO: key -> let outpunthandler 
			$key = (isset($_SERVER['AUTHORIZATION']) ? sha1($_SERVER['AUTHORIZATION']) : '').
			$_SERVER['REQUEST_URI'];
			$url = $_SERVER['REQUEST_URI']; $split = strrpos($url, '.');
			$args = strlen($url)>1&&strpos($url,'.')!==false ? explode('/', 
									trim(substr($url, 0, $split), '/')) : array();
			$ext = substr($url, $split+1); $home = empty($args) || $args[0]=='page';
			// page exception enables home page to use paging without need go /HomeController/page/2.html
			try { $cClass= $home ? Conf::get('App.home', '\inPHP\Control\DefaultHome')
								 : \inPHP\controlSpace() . '\\' . $args[0];
			if (in_array('inPHP\Control\IController', class_implements($cClass, true)))
				if ((($controller= Cache::local()->get($cClass))
				 || (($controller = new $cClass()) && Cache::local()->set($cClass, $controller)==null))
				&&(empty($args) || $controller->extension() == $ext)) {
					$controller->outputHandler()->start($key); $controller->template()->head(); 
					$controller->main($args, &$_GET, &$_POST, &$_FILES); 
					$controller->template()->tail(); $controller->outputHandler()->finish();
			} else { $error = Conf::get('App.404', 'FileNotFound'); $error(); }
			} catch (\Exception $e) { ExceptionCaught($e); }
		} else { $e=1; if ($argc==1) echo "Missing arg0, the name of an IRunnable class\n"; // CLI mode
			else { $class = $argv[1]; if (class_exists($class) && in_array('inPHP\Control\IRunnable', 
				class_implements($class))){$runnable=new $class();$e=$runnable->run(array_slice($argv,2));}
			else echo "arg0 ".$class." is not an implementation of inPHP\Control\IRunnable"; exit($e);}
		} 
	}
}
?>
