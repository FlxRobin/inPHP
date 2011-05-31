<?php
namespace inPHP\Reflection;
/**
 * Generic reflection class with annotations.
 * Heavy weight constructor, instances should be cached when used in actual pages.
 * This class also captures properties and methods, but not parents and children.
 */
class ReflectioninPHPClass extends \inPHP\Reflection\Annotated {
	protected $name, $methods = array(), $properties = array(), $modifiers;
	public function __construct($reflectionClass) {
		parent::__construct($reflectionClass->getDocComment());
		$this->name = $reflectionClass->getName();
		$this->modifiers = $reflectionClass->getModifiers();
		foreach ($reflectionClass->getMethods() as $method)
			$this->methods[$method->getName()] = new ReflectioninPHPMethod($method);
		foreach ($reflectionClass->getProperties() as $property)
			$this->properties[$property->getName()] = new ReflectioninPHPProperty($property);
	}
	function name() { return $this->name; }
	function methods() { return $this->methods; } 
	function properties() { return $this->properties; }
	function method($n) { return isset($this->methods[$n])?$this->methods[$n]:false; }
	function property($n) { return isset($this->properties[$n])?$this->properties[$n]:false; }
	// captured by modifiers
	function isAbstract(){return($this->modifiers & ReflectionClass::IS_EXPLICIT_ABSTRACT)>0;}
	function isFinal(){return($this->modifiers & ReflectionClass::IS_FINAL)>0;}
	// not cached
	function interfaces() { return class_implements($this->name); }
	function implementorOf($interface) { return in_array($interface, $this->interfaces()); }
}
	
?>