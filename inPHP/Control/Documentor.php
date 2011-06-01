<?php
namespace inPHP\Control;
use inPHP\Cache\Cache;
/** Controller to view live-self-documentation of the application running this controller. */
class Documentor implements IController {
	protected $template, $outputHandler;
	function __construct() {
		$this->template = new \inPHP\View\DefaultTemplate();
		$this->outputHandler = new \inPHP\Control\OutputHandler();
	}
	
	function main($args, $get, $post, $files) {
		$this->template->misc('Documentation');
		$namespaces = array();
		foreach (get_declared_classes() as $class) {
			$reflection = new \ReflectionClass($class);
			$namespace = $reflection->getNamespaceName();
			if (!array_key_exists($namespace, $namespaces))$namespaces[$namespace]=array();
			$namespaces[$namespace][$reflection->getName()] = $reflection;
		}
		$global = $namespaces['']; unset($namespaces['']);
		ksort($namespaces);
		foreach ($namespaces as $namespace => &$classes) { ksort($classes);
			print '<h2>'.$namespace.'</h2><div style="padding-left: 10px;">';
			foreach ($classes as $reflector) {
				print '<h3 style="margin: 10px 0 0 0;"><span style="color: #709;">class </span>'.
				$reflector->getShortName();
				if ($parent = get_parent_class($reflector->getName()))
					print '<span style="color: #666;"> : '.get_parent_class($reflector->getName()).
																						'</span>';				
				print '<span style="color: #999;"> '.implode(', ', 
												class_implements($reflector->getName())).'</span>';
				print '</h3>'.str_replace("\n", '<br />',
												\inPHP\View\cleanDoc($reflector->getDocComment()));
				$signature = array($this, 'signature');
				print '<p><small>'.implode(', ', array_map($signature, $reflector->getMethods()))
																				.'</small></p>';
			}
			print '</div>';
		}
	}
	
	function signature($m) {
		return '<strong>'.$m->getName().'</strong>('. implode(', ', array_map(function ($p) use ($m) {
			return ($p->isPassedByReference()?'&':'').'<strong style="color: #069">$'.$p->getName().
				'</strong>'.($p->isOptional()&&
			!$m->isInternal()?' = '.(($v = $p->getDefaultValue())==null?
				'<strong style="color: #709">null</strong>':$v):''); }, $m->getParameters())).')';			
	}
	function outputHandler() { return $this->outputHandler; } 
	function extension() { return 'html'; } 
	function template() {  return $this->template; }
	// implement the methods!
}
?>