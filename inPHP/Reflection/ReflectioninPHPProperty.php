<?php
namespace inPHP\Reflection;
/**
 * Generic reflection property with annotations.
 * Heavy weight constructor, instances should be cached when used in actual pages.
 */
class ReflectioninPHPProperty extends \inPHP\Reflection\Annotated {
	protected $name, $modifiers, $class;
	function __construct($reflectionProperty) {
		parent::__construct($reflectionProperty->getDocComment());
		$this->name = $reflectionProperty->getName();
		$this->class = $reflectionProperty->getDeclaringClass()->getName();
		$this->modifiers = $reflectionProperty->getModifiers();
	}
	function name() { return $this->name; }
	/** The declaring class is the last class to (re)define the method */
	function declaringClass() { return $this->class; }
	/** Gets the class off which this method overrides, or null if this does not override */
	function prototypeClass() { return $this->prototypeClass; }
	// information in modifier
	function isStatic() { return ($this->modifiers & \ReflectionProperty::IS_STATIC) > 0; }
	function isPrivate() { return ($this->modifiers & \ReflectionProperty::IS_PRIVATE) > 0; }
	function isProtected() { return ($this->modifiers & \ReflectionProperty::IS_PROTECTED) > 0; }
	function isPublic() { return ($this->modifiers & \ReflectionProperty::IS_PUBLIC) > 0; }
}
?>