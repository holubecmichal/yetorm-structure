<?php

/**
 * Vytvoření repozitářů a entit z tabulek databáze.
 *
 * @filesource	yetorm-build-auto.php
 * @author		© Web Data Studio, www.web-data.cz
 * @contributor Michal Holubec
 * @version		1.0.1
 */

require_once("inflector.php");
echo "\r\n";

/** Databázový ovladač */
define('DB_DRIVER', 'mysql');

/** Adresa SQL serveru */
define('DB_HOST', 'localhost');

/** Název databáze */
define('DB_NAME', 'smartersurfaces_dev');

/** Přihlašovací jméno */
define('DB_USER', 'root');

/** Přihlašovací heslo */
define('DB_PASSWORD', 'root');

/** Namespace tříd */
define('NS', 'App\\');

/** Cesta k app */
define('APP_PATH', __DIR__ . '/app');

/** Cesta k model */
define('MODEL_PATH', APP_PATH . '/model');
define('ENTITY_PATH', MODEL_PATH . '/entity/');
define('REPOSITORY_PATH', MODEL_PATH . '/repository/');
define('PROTOTYPE_PATH', __DIR__ . '/prototypes');

// Přípojení do databáze,
try {
	$db = new PDO(DB_DRIVER . ':host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASSWORD, array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES UTF8'));
} catch (PDOException $e) {
	echo $e->getMessage();
	echo "\r\n";
	echo "\r\n";
	die;
}

if(!file_exists(APP_PATH)) {
	echo "Nebyla nalezena slozka app";
	echo "\r\n";
	echo "\r\n";
	die;
}

checkPaths();
checkBases();

// Načtení názvu tabulek,
$tables = array();

foreach ($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {

	$name = implode('', array_map(function($word) {
				return ucfirst($word);
			}, explode('_', $table)));


	$cols = array();

	foreach ($db->query("DESCRIBE `$table`") as $col) {
		$cols[$col['Field']] = '@property' . (strtolower($col['Extra']) == 'auto_increment' ? '-read' : NULL)
				. ' ' . getColType(strtolower($col['Type'])) . (strtolower($col['Null']) == 'yes' ? '|NULL' : NULL)
				. ' $' . $col['Field'] . "\n";
	}

	$tables[$name] = array(
		$table => $cols,
	);
}

// Generování tříd,
foreach ($tables as $className => $table) {

	foreach ($table as $name => $cols) {

		if(!file_exists(REPOSITORY_PATH . $className . '.php')) {
			//$entity_name = readline($className . ": ");
			$entity_name = readline_predefined($className . ": ", Inflector::singularize($className));
			newEntity($cols, $entity_name);
		} else {
			$repository = file_get_contents(REPOSITORY_PATH . $className . '.php');
			preg_match('/(?<=@entity \\\\).*/', $repository, $matches);

			if(!$matches) {
				continue;
			}

			$matches = explode("\\", $matches[0]);
			$entity_name = end($matches);

			if(file_exists(ENTITY_PATH . $entity_name . '.php')) {
				editEntity($cols, $entity_name);
			} else {
				newEntity($cols, $entity_name);
			}
		}
	}

	if(!file_exists(REPOSITORY_PATH . $className . '.php')) {
		// Repozitář
		$properties = " * @table $name\n * @entity \\" . NS . 'Model\Entity\\' . $entity_name;
		$buffer = "namespace " . NS . "Model\\Repository;\n\n/**\n$properties\n */\nfinal class $className extends Base\n{\n\n}";
		file_put_contents(REPOSITORY_PATH . $className . '.php', "<?php\n\n$buffer\n");
	}
}
echo "\r\n";
echo "\r\n";

/**
 * Vytvori novou entitu
 * @param $className
 * @param $cols
 * @param $entity_name
 */
function newEntity($cols, $entity_name) {
	$properties = ' * ' . implode(' * ', $cols);
	$buffer = "namespace " . NS . "Model\\Entity;\n\n/**\n$properties */\nfinal class $entity_name extends Base\n{\n\n}";

	file_put_contents(ENTITY_PATH . $entity_name . '.php', "<?php\n\n$buffer\n");
}

/**
 * Zedituje properties dle aktualniho stavu tabulky a sloupcu
 * @param $cols
 * @param $entity_name
 */
function editEntity($cols, $entity_name) {
	$content = file_get_contents(ENTITY_PATH . $entity_name . '.php');
	preg_match("/\\/\\*\\*.*?\\*\\//s", $content, $matches);

	if(!$matches) {
		return;
	}

	$old_properties = $matches[0];

	$new_properties = ' * ' . implode(' * ', $cols);
	$new_properties = "/**\n$new_properties */\n";

	$content = str_replace($old_properties, $new_properties, $content);
	file_put_contents(ENTITY_PATH . $entity_name . '.php', $content);
}

/**
 * Detekce typu sloupce
 * @param string $type typ sloupce
 * @param array $outputArray
 * @return string
 */
function getColType($type, $outputArray = NULL)
{
	preg_match("/\w+/", $type, $outputArray);

	$types = [
		'string' => ['varchar', 'text'],
		'\Nette\Utils\DateTime' => ['datetime'],
	];

	foreach ($types as $_type => $array) {

		if (in_array(strtolower(trim($outputArray[0])), $array)) {
			$outputArray[0] = $_type;
			break;
		}
	}

	return $outputArray[0];
}

/**
 * Zkontroluje existence zakladnich adresaru
 * pripadne se postara o jejich vytvoreni
 */
function checkPaths() {
	if(!file_exists(MODEL_PATH)) {
		mkdir(MODEL_PATH);
	}
	if(!file_exists(ENTITY_PATH)) {
		mkdir(ENTITY_PATH);
	}
	if(!file_exists(REPOSITORY_PATH)) {
		mkdir(REPOSITORY_PATH);
	}
}

/**
 * Zkontroluje existence zakladnich trid
 * pripadne se postara o jejich vytvoreni
 */
function checkBases() {
	if(!file_exists(ENTITY_PATH . 'Base.php')) {
		$prototype = file_get_contents(PROTOTYPE_PATH . '/BaseEntity.php');
		$prototype = str_replace("BaseEntity", "Base", $prototype);
		file_put_contents(ENTITY_PATH . 'Base.php', $prototype);
	}
	if(!file_exists(REPOSITORY_PATH . 'Base.php')) {
		$prototype = file_get_contents(PROTOTYPE_PATH . '/BaseRepository.php');
		$prototype = str_replace("BaseRepository", "Base", $prototype);
		file_put_contents(REPOSITORY_PATH . 'Base.php', $prototype);
	}
}

// ------------- READLINE S PREDEFINED HODNOTOU

$readline = FALSE;
$prompt_finished = FALSE; 

function readline_callback($ret) { 
	global $readline, $prompt_finished; 
	$readline = $ret; 
	$prompt_finished = TRUE; 
	readline_callback_handler_remove(); 
} 

function readline_predefined($prompt, $predefined_text = "") {
	global $readline, $prompt_finished; 

	readline_callback_handler_install($prompt, 'readline_callback'); 
	for ($i = 0; $i < strlen($predefined_text); $i++) { 
		readline_info('pending_input', substr($predefined_text, $i, 1)); 
		readline_callback_read_char(); 
	} 
	$readline = FALSE; 
	$prompt_finished = FALSE; 
	while (!$prompt_finished) {
		readline_callback_read_char(); 
	}

	return $readline;
}
// ------------- READLINE S PREDEFINED HODNOTOU
