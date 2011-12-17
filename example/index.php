<?php
// Load class
require_once(dirname(__FILE__) . "/../pvpgn_plain_to_sql2.class.php");

// Options
$options = array(
	'convert_clans' => false,
	'convert_teams' => false,
	'pvpgn_absolute_path' => dirname(__FILE__) . "/server/"
);

// Constructor
$plain_to_sql2 = new pvpgn_plain_to_sql2($options);

// Generate SQL queries
echo $plain_to_sql2->convert();