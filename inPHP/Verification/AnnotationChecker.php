<?php
namespace inPHP\Verification;
/* Introspects everything, to see if it ever fatals (spellerrors, not enough args..) */
// looks up all classes, properties and methods
/** @inPHP\Verification\AnnotationChecker; <-- for testing purposes 
 *  Can check if found annotations are well formed, so existing classes with the correct
 *  number of constructor arguments; these sort of checks can help when refactoring
 *  annotations. */
class AnnotationChecker implements \inPHP\Control\IRunnable {
	/** Takes no arguments, just introspects all defined classes and gets the annotations
	 * 	of those classes and their methods and properties.
	 *  Counts the errors that occur, and exits with that number as status (so 0 for no 
	 *  errors, any other number for errors), when errors occur, they should be easy to
	 *  spot in the output.
	 *  By default it outputs everything it parses, so you can see which classes are 
	 *  included.
	 */
	function run($args) {
		$errors = 0;		
		set_error_handler(array($this, "exception_error_handler"));
		foreach (get_declared_classes() as $class) {
			$reflectionClass = new \ReflectionClass($class);
			try {
				$reflectioninPHPClass = new \inPHP\Reflection\ReflectioninPHPClass($reflectionClass);
				print "\n\nclass ".$reflectioninPHPClass->name().":";
				print "\n\t".implode(', ', array_map(function ($annotation) { return get_class($annotation); },
					$reflectioninPHPClass->allAnnotations()));
				print "\n\n\tproperties:";
				foreach ($reflectioninPHPClass->properties() as $p) {
					print "\n\t".$class.'->'.$p->name();
					print "\n\t\t". implode(', ', array_map(function ($annotation) { return get_class($annotation); },
						$p->allAnnotations()));
				}
				print "\n\n\tmethods:";
				foreach ($reflectioninPHPClass->methods() as $m) {
					print "\n\t".$class.'->'.$m->name().'()';
					print "\n\t\t".implode(', ', array_map(function ($annotation) { return get_class($annotation); },
						$m->allAnnotations()));
				}
			} catch (\Exception $e) {
				$errors++;
				print "\nError occured while introspecting class: ".$class."\n";
				print get_class($e).' with message '.$e->getMessage();
				print $e->getTraceAsString();
			}
		}
		print "\n".$errors." errors occurred\n";
		exit($errors);
	}
	// http://php.net/manual/en/class.errorexception.php
	/** @inPHP\Verification\AnnotationChecker; <-- for testing purposes */
	function exception_error_handler($errno, $errstr, $errfile, $errline ) {
	    throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
	}
}

?>