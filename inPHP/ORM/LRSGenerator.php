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
namespace inPHP\ORM;

class Column extends \inPHP\ChanableProperties { protected $type, $notNull, $autoIncrement, $fk; }
class ForeignKey extends \inPHP\ChanableProperties { protected $name, $target; }
/**
 * Generates LRS based on defined IModels. Generated code is as close to the SQL standard as possible
 * TODO implement drop tables option, including dropping all foreignKeys before dropping tables.
 */
class LRSGenerator implements \inPHP\Control\IRunnable {
	/** @param args, list of files to be included, or the --apply switch to give this script
	 * permission to actually execute all of the generated SQL scripts, and apply the LRS on
	 * your database. */
	function run($args) {
		\inPHP\includeAll(\inPHP\modelSpace());
		$tables = array(); // tablename => array(columnname => shorttype)
		$tablePKs = array(); $apply = false; $drop = false;
		foreach ($args as $fileName) if ($fileName=='--apply') $apply = true; 
			elseif ($fileName=='--dropTables') $drop = true; else include $fileName;
		foreach (get_declared_classes() as $class) {
			if (in_array('inPHP\ORM\IModel', class_implements($class)) && 
				($nr = new \ReflectionClass($class)) && !$nr->isAbstract()) {
				$r = ModelReflection::get($class);
				if (!array_key_exists($r->ancestor(), $tables)) { 
					$tables[$r->ancestor()]=array(); $tableFKs[$r->ancestor()] = array();
					$tableNNs[$r->ancestor()]=array(); $tableAIs[$r->ancestor()] = array();
				}
				$table =& $tables[$r->ancestor()];
				$tablePK =& $tablePKs[$r->ancestor()];
				foreach ($r->properties() as $property)
					if ($property->metaType() instanceof IModel ||
						!($property->metaType() instanceof IJoinable)) {
						$pk = $property->primaryKey();
						$nn = $property->annotation('inPHP\ORM\NotNull')!==false;
						$ai = $property->annotation('inPHP\ORM\AutoIncrement')!==false;
						$fk = $property->metaType() instanceof IModel ? new ForeignKey($property->name(), 
								get_class($property->metaType())) : null;
						array_map(function($p, $t) use (&$table, $nn, $ai, $fk, $pk, &$tablePK) { 
							$table[$p] = new Column($t, $nn, $ai, $fk); if($pk) $tablePK[] = $p;}, 
								$property->scalarNames(), $property->metaType()->shortTypes()); 
					}
				if (!$r->isFinal()) $table[DAO::CLASSFIELD] = new Column('string', true, false, null);
			}
		}
		print "Found tables and primary keys:\n";
		print_r($tablePKs);
		$typeConverterClass = \inPHP\Conf::get(__CLASS__.'.typeConverter', 'inPHP\ORM\MySQLTypeConverter');
		$typeConverter = new $typeConverterClass;
		$fkTables = array(); $creates = array();
		foreach ($tables as $name => $columns) {
			$shortName = strpos($name,'\\')===false?$name:substr($name,strrpos($name, '\\')+1);
			$create = "\n".'CREATE TABLE '.$shortName.' ('; $first = true;
			foreach ($columns as $columnName => $column) { if ($first)$first=0;else $create.=',';
				$create .= "\n\t".$columnName.' '.$typeConverter->convert($column->type())
				.($column->notNull()?' NOT NULL':'').($column->autoIncrement()?' AUTO_INCREMENT':'');
				if (($fk = $column->fk())!=null) { 
					if (!array_key_exists($shortName, $fkTables)) $fkTables[$shortName] = array();
					if (!array_key_exists($fk->name(), $fkTables[$shortName])) $fkTables[$shortName][$fk->name()]
						= array('target' => $fk->target(), 'columns' => array());
					$fkTables[$shortName][$fk->name()]['columns'][] = $columnName;
				}
			}
			$create .= ",\n\tPRIMARY KEY(".implode(', ', $tablePKs[$name]).")\n)";
			if (\inPHP\Conf::get(__CLASS__.'.innoDB', true)) $create .= " ENGINE=InnoDB;\n"; 
			else $create .= ";\n";
			$creates[$shortName] = $create;
		}
		$alters = array();
		foreach ($fkTables as $name => $fks) {			
			foreach ($fks as $relationName => $fk) {
				$shortName = strpos($fk['target'],'\\')===false ? $fk['target'] : 
								substr($fk['target'],strrpos($fk['target'], '\\')+1);
				$alter = "\n".'ALTER TABLE '.$name.'';
				$alter .= ' ADD CONSTRAINT '.$relationName.' FOREIGN KEY '.$relationName;
				$alter .= '('.implode(', ', $fk['columns']).')'."\n";
				$alter .= 'REFERENCES `'.$shortName.'`';
				$alter .= '('.implode(', ', $tablePKs[$fk['target']]).')';
				$alter .= "\nON DELETE RESTRICT\nON UPDATE CASCADE;\n";
				$alters[] = $alter;
			}			
		}
		$errors = 0;
		foreach ($creates as $table => $create) { print $create; if ($apply) try {
					$result = DB\ADB::get()->set($create); $result();
				} catch (ORMException $e) { 
					$errors++;
					print "\nError occurred trying to create table ".$table;
					print get_class($e)." with message ".$e->getMessage();
					print $e->getTraceAsString();
				} 
		}
		foreach ($alters as $alter) { print $alter; if ($apply) try {
					$result = DB\ADB::get()->set($alter); $result();
				} catch (ORMException $e) {
					$errors++;
					print "\nError occurred trying to alter table with following defintion:".$alter;
					print get_class($e)." with message ".$e->getMessage();
					print $e->getTraceAsString();
				} 
		}
		return $errors;
	}
}

interface IDBTypeConverter { function convert($type); }

class MySQLTypeConverter implements IDBTypeConverter {
	function convert($type) {
		if (is_array($type)) return 'enum(\''.implode('\', \'',$type).'\')';
		if ($type == 'string') return 'varchar(255)';
		return $type;
	}
}

?>