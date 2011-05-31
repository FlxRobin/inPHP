<?php
namespace inPHP\Reflection;
/**
 * Generic reflection method with annotations.
 * Heavy weight constructor, instances should be cached when used in actual pages.
 */
class ReflectioninPHPMethod extends \inPHP\Reflection\Annotated {
	protected $name, $modifiers, $class;
	/** The class of which this methods override the similarly named method */
	protected $prototypeClass = null;
	function __construct($reflectionMethod) {
		parent::__construct($reflectionMethod->getDocComment());
		$this->name = $reflectionMethod->getName();
		$this->class = $reflectionMethod->getDeclaringClass()->getName();
		$this->modifiers = $reflectionMethod->getModifiers();			
		try { $this->prototypeClass = $reflectionMethod->getPrototype()->getDeclaringClass()->
			getName(); } catch (\ReflectionException $e) {/* Method does not override. */}
	}		
	function name() { return $this->name; }
	/** The declaring class is the last class to (re)define the method */
	function declaringClass() { return $this->class; }
	/** Gets the class off which this method overrides, or null if this does not override */
	function prototypeClass() { return $this->prototypeClass; }
	// information in modifier
	function isAbstract() { return ($this->modifiers & \ReflectionMethod::IS_ABSTRACT) > 0; }
	function isFinal() { return ($this->modifiers & \ReflectionMethod::IS_FINAL) > 0; }
	function isStatic() { return ($this->modifiers & \ReflectionMethod::IS_STATIC) > 0; }
	function isPrivate() { return ($this->modifiers & \ReflectionMethod::IS_PRIVATE) > 0; }
	function isProtected() { return ($this->modifiers & \ReflectionMethod::IS_PROTECTED) > 0; }
	function isPublic() { return ($this->modifiers & \ReflectionMethod::IS_PUBLIC) > 0; }
}

?>