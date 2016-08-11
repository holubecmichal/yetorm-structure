<?php

/**
 * Vytvoření repozitářů a entit z tabulek databáze & GUI
 *
 * @filesource	 yetorm-build-simple.php
 * @author 		 Michal Holubec, Martin Pecha
 * @contributor	 © Web Data Studio, www.web-data.cz
 * @version		 2.0.0
 */

require_once("inflector.php");

/** Databázový ovladač */
define('DB_DRIVER', 'mysql');

/** Adresa SQL serveru */
define('DB_HOST', 'localhost');

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
	$db = new PDO(DB_DRIVER . ':host=' . DB_HOST, DB_USER, DB_PASSWORD, array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES UTF8'));
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

// GUI DB
$database = drawSelectDatabase($db);

$next = true;
while($next) {

	// GUI TABLE
	$table = drawSelectTable($db, $database);

	// Functions
	$cols = getTableCols($db, $table);
	$className = getClassName($table);

	// Work
	$entity = generateEntity($className, $cols);
	generateRepository($table, $className, $entity);

	$next = waitForYesNo("Do you want generate another table?");
}

drawEmptyLine();
drawRow("Thanks for using.");
drawEmptyLine();
drawEmptyLine();


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

function drawTableLine($data_len=0) {
	echo "  " . str_repeat("-", $data_len + 10) . "\r\n";
}

function drawEmptyTableLine($data_len=0) {
	echo "  " . "|    " . str_repeat(" ", $data_len) .  "    |" . "\r\n";
}

function drawTableRow($string="", $data_len=0) {
	$string_len = strlen($string);
	echo "  " . "|    " . $string . str_repeat(" ", $data_len-$string_len) .  "    |" . "\r\n";
}

function drawEmptyLine() {
	echo "\r\n";
}

function drawRow($text) {
	echo "  {$text}\r\n";
}

function clearScreen() {
	system('clear');

	drawEmptyLine();
	drawTableLine(50);
	drawTableRow("YetORM entity & repository generator", 50);
	drawTableRow("v2.0.0", 50);
	drawTableRow("[GUI]  (c) 2016 Martin Pecha", 50);
	drawTableRow("[CORE] (c) 2016 Michal Holubec", 50);
	drawTableRow("[CORE] (c) 20xx Web Data Studio, www.web-data.cz", 50);
	drawTableLine(50);
	drawEmptyLine();
}

function waitForYesNo($task) {
	$line = drawQuestion($task . " [y/n]");
	if($line != 'y' && $line != 'n'){
		return $this->waitForYesNo($task);
	}
	return ($line == 'y' ? true : false);
}

function drawSelectDatabase($db) {
	$databases = [];
	$length = 0;
	$num_len = 0;
	$num = 1;

	foreach ($db->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN) as $database) {

		if(strlen($database) > $length) {
			$length = strlen($database);
		}
		if(strlen($num) > $num_len) {
			$num_len = strlen($num);
		}

		$databases[$num] = $database;
		$num++;
	}

	$total_len = $length + $num_len + 4; // num:   string

	clearScreen();
	drawEmptyLine();
	drawTableLine($total_len);
	drawEmptyTableLine($total_len);
	foreach($databases as $num => $database) {
		drawTableRow($num . ":" .str_repeat(" ", $num_len - strlen($num)+3) . $database, $total_len);
	}
	drawEmptyTableLine($total_len);
	drawTableLine($total_len);

	drawEmptyLine();
	drawEmptyLine();

	$database_num = 0;
	while(!isset($databases[$database_num])) {
		$database_num = drawQuestion("Select database");
	}

	$database = $databases[$database_num];

	drawEmptyLine();

	return $database;
}

function drawSelectTable($db, $database) {
	$tables = [];
	$length = 0;
	$num_len = 0;
	$num = 1;

	$db->query('USE ' . $database);
	foreach ($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {

		if(strlen($table) > $length) {
			$length = strlen($table);
		}
		if(strlen($num) > $num_len) {
			$num_len = strlen($num);
		}

		$tables[$num] = $table;
		$num++;
	}

	$total_len = $length + $num_len + 4; // num:   string

	clearScreen();
	drawEmptyLine();
	drawRow("DATABASE: " . $database);
	drawTableLine($total_len);
	drawEmptyTableLine($total_len);
	foreach($tables as $num => $table) {
		drawTableRow($num . ":" .str_repeat(" ", $num_len - strlen($num)+3) . $table, $total_len);
	}
	drawEmptyTableLine($total_len);
	drawTableLine($total_len);

	drawEmptyLine();
	drawEmptyLine();

	$table_num = 0;
	while(!isset($tables[$table_num])) {
		$table_num = drawQuestion("Select table");
	}

	$table = $tables[$table_num];

	drawEmptyLine();

	return $table;
}

function drawQuestion($question) {
	echo "  {$question}: ";
	$handle = fopen ("php://stdin","r");
	$line = fgets($handle);
	return trim($line);
}

function getTableCols($db, $table) {
	$cols = array();

	foreach ($db->query("DESCRIBE `$table`") as $col) {
		$cols[$col['Field']] = '@property' . (strtolower($col['Extra']) == 'auto_increment' ? '-read' : NULL)
			. ' ' . getColType(strtolower($col['Type'])) . (strtolower($col['Null']) == 'yes' ? '|NULL' : NULL)
			. ' $' . $col['Field'] . "\n";
	}

	return $cols;
}


function getClassName($table) {
	return implode('', array_map(function($word) {
		return ucfirst($word);
	}, explode('_', $table)));
}

function generateEntity($className, $cols) {

	if(!file_exists(REPOSITORY_PATH . $className . '.php')) {
		$entity_name = readline_predefined("  Entity name for repo '" . $className . "': ", Inflector::singularize($className));
		newEntity($cols, $entity_name);

		drawRow("Entity '{$entity_name}' generated");
	} else {
		$repository = file_get_contents(REPOSITORY_PATH . $className . '.php');
		preg_match('/(?<=@entity \\\\).*/', $repository, $matches);

		if(!$matches) {
			return null;
		}

		$matches = explode("\\", $matches[0]);
		$entity_name = end($matches);

		if(file_exists(ENTITY_PATH . $entity_name . '.php')) {
			editEntity($cols, $entity_name);
			drawRow("Entity '{$entity_name}' updated");
		} else {
			newEntity($cols, $entity_name);
			drawRow("Entity '{$entity_name}' generated");
		}
	}
	return $entity_name;

}

function generateRepository($table, $className, $entity_name) {

	if($entity_name === null) {
		return;
	}

	if(!file_exists(REPOSITORY_PATH . $className . '.php')) {
		// Repozitář
		$properties = " * @table $table\n * @entity \\" . NS . 'Model\Entity\\' . $entity_name;
		$buffer = "namespace " . NS . "Model\\Repository;\n\n/**\n$properties\n */\nfinal class $className extends Base\n{\n\n}";
		file_put_contents(REPOSITORY_PATH . $className . '.php', "<?php\n\n$buffer\n");
		drawRow("Repository '{$className}' generated");
	} else {
		drawRow("Repository '{$className}' exist");
	}

	return;

}


