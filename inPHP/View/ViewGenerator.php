<?php
namespace inPHP\View;
use inPHP\Cache\Cache;
/**
 * Generates view based on model class.
 * TODO see if inheritance fucks up Model::fieldMap() order of fields 
 */
class ViewGenerator {
	protected $listHeader;
	/** Blocks of precompiled html; with %0$s's */
	protected $layoutView, $layoutEdit, $blockView, $blockEdit, $listView, $listEdit, $suppressed;
	
	/** Heavy weight constructor, cache this thing! */
	function __construct($modelClass) {
		$this->suppressed = array();
		$reflection = \inPHP\ORM\ModelReflection::get($modelClass);
		$modelHrName = ($hr = $reflection->annotation('inPHP\View\HRName')) != false ? $hr->name : 
						self::camelToHuman($modelClass);	$this->listHeader .= '<tr>';
		$this->blockEdit = $this->blockView = '<div class="Block">'; $i = 0;
		$layoutView = array(); $layoutEdit = array();	
		foreach ($reflection->allProperties() as $name => $property) { $i++;			
			$metaType = $property->metaType(); $id = $modelClass.$name; 
			if ($metaType instanceof \inPHP\ORM\IJoinable) $this->suppressed[] = $name; else {
				$c = 'ModelProperty '.$modelClass.' '.$name.' '.get_class($metaType);
				$hrName = ($hrName = $property->annotation('inPHP\View\HRName')) != false ? $hrName->name() :
																		self::camelToHuman($name);
				// TODO viewPrecision specifier etc, viewContainer etc...
				$placeHolder = '%'.$i.'$'.$metaType->specifier(); 
						
				// TODO check if its editable...Add class for NotNull...
				// TODO add Selectable path
				$input = XC::input($placeHolder, $id)->__toString(); 
				$span = XC::span($placeHolder, $id)->__toString();
				// TODO make PrimaryKeys appear in H2's in blocks; not to mention at the top..
				$label = XC::label($hrName)->for($id)->__toString();
				$div = XC::div()->class($c);			
				$tab = $property->annotation('inPHP\View\UITab');
				$this->placeInLayout(&$layoutView,$div->content($label.$span)->__toString(),$tab, 
																					$modelHrName);
				$this->placeInLayout(&$layoutEdit,$div->content($label.$input)->__toString(),$tab, 
																					$modelHrName);
				// TODO make the PK free in lists
				if ($property->annotation('inPHP\View\ViewInList') != false
					 || $property->annotation('inPHP\ORM\PrimaryKey') != false) {				
					$this->listHeader .= XC::th($hrName)->class($c);
					$this->listView .= XC::td()->class($c)->content($placeHolder);
					$this->listEdit .= XC::td()->class($c)->content($input);
					$this->blockView .= $span; $this->blockEdit .= $label.$input;
				}
			}
		}
		$this->layoutView = $this->generateLayout($layoutView);
		$this->layoutEdit = $this->generateLayout($layoutEdit);
	}
	
	function display($model,$mutators = array()) {
		//print_R($model); /*
		$vars = $model->fieldMap();		
		foreach ($mutators as $name => $func) $func(&$vars[$name]);
		foreach ($this->suppressed as $s) if (!array_key_exists($s, $mutators)) $vars[$s] = null;
		try {
		vprintf($this->layoutView, $vars);
		} catch (Exception $e) { debug_print_backtrace(); }//*/		
	}
	
	function displayEdit($model) {$result = array();		
		foreach ($this->properties as $name => $p) $this->placeInLayout($result,
				$p->div()->content($p->label().$p->editContainer()
				->content($model->$name()))->__toString(), $p->tab()); 
		$this->displayLayout($result);
	}
	
	function displayBlock($model, $linkedProperties = array(), $afterBlock = null ) { 
		echo '<div class="Block">'; foreach ($this->properties as $name => $p) 
			if (isset($linkedProperties[$name]))  $p->viewContainer()
				->content($linkedProperties[$name]($model->$name()));
			else $p->viewContainer()->content($p->metaType()->display(
													$model->$name()))->output();
		if ($afterBlock!=null) $afterBlock($model);
		echo '</div>';
	}
	/** returns < td >'s callbacks transform the obj_vars */
	function displayListLine($model, $mutators = array(), $lineEnd = null) {
		$vars = $model->varMap();		
		foreach ($mutators as $name => $func) $func(&$vars[$name]);  
		printf($this->line, $vars);
		if ($lineEnd != null) $lineEnd($model);
	}	
	/** header, so a tr with th's, no < table > in there anywhere */
	function displayListHeader() { return $this->listHeader; }	
	/** Displays form content only, does not contain < form >, you have to encapsule. */
	function displayCreate() { $this->displayEdit(new $this->modelClass); }
	/** displays inputs in td's in a tr, the inputs are viewInList properties */
	function displayCreateInListLine() {
		
	}
	/** displays inputs in a div, the inputs are viewInList properties */
	function displayCreateInBlock() {
		
	}
	/** result is by reference for speed, no copies of it shall be made */
	protected function placeInLayout(&$result, $html, $tab, $modelHrName) {
		$tabName = !$tab ? $modelHrName : $tab->name();
		$fieldSet = !$tab ? 0 : (($f = $tab->fieldSet()) != null ? $f : 0); 
		if (!isset($result[$tabName])) $result[$tabName] = array();
		$t =& $result[$tabName]; if (!isset($t[$fieldSet])) $t[$fieldSet] = $html;
		else $t[$fieldSet] .= $html;
	}
	/** Used to place htmlpieces indexed by tabName and fieldset into a tabbed layout */
	protected function generateLayout(&$layout, $lis = '', $divs = '') {
		$result = '<div class="UITabs"><ul>'; foreach ($layout as $tabName => $tab) { $tabHtml = ''; 
			$id = str_replace(' ', '_', $tabName); foreach ($tab as $fieldName => $fieldSet) {
				if ($fieldName == 0) $tabHtml.=$fieldSet; else $tabHtml.='<fieldset class="'
				.$fieldName.'"><legend>'.$fieldName.'</legend>'.$fieldSet.'</fieldset>';				
			} $divs .= '<div id="'.$id.'">'.$tabHtml.'</div>';
			$result .= '<li><a href="#'.$id.'">'.$tabName.'</a></li>';
		} $result .=  '</ul>'.$divs.'</div>'; return $result;
	}
	
	/** replaces all capitals by lower-case version prefixed with space, and upcases first char */
	static function camelToHuman($camel) { if (($last = strrpos($camel, '\\')) !== false) 
		$camel = substr($camel, $last+1); $len = strlen($camel);
		for ($i = $len-1; $i > 1; $i--) { //regex-less, for more speed
			$ord = ord($camel[$i]); if ($ord >= 65 && $ord <= 90)
				$camel = substr_replace($camel, ' '.lcfirst($camel[$i]), $i, 1);
		} return ucfirst($camel);
	}
	/** Caching factory, ViewGenerator::Model() returns a ViewGenerator, 
	 * ViewGenerator::Model('ExtendedViewGenerator') returns an ExtendedViewGenerator. */
	static function __callStatic($name, $args) { 
		$name = \inPHP\Conf::get('App.modelSpace','').$name;
		$class = isset($args[0]) ? $args[0] : 'inPHP\View\ViewGenerator';
		if (!($result = Cache::local()->get($class.':'.$name)))
			Cache::local()->set($class.':'.$name, ($result = new $class($name))); 
		return $result;
	}
}
?>