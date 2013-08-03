<?php
/**
 * Weapon Limiter 
 * 
 * Configuration File
 * 
 * Console script for Battlefield Play4Free community.
 * Kicks players with forbidden weapons like shotguns on selected server.
 * 
 * @category BFP4F
 * @package  limiter
 * @author   piqus <ovirendo@gmail.com>
 * @license  MIT http://opensource.org/licenses/MIT
 * @version  0.1
 * @link     https://github.com/piqus/bfp4f-limiter
 */

/* Configuration
 ********************/

ini_set('max_execution_time', 20);
date_default_timezone_set('Europe/London');

// Load composer vendors 
define('VENDOR_DIR', __DIR__ . '/vendor');

$configs = array(
    'cacheThres' => 30, // 30 minutes
    'colLogs' => 'logs',
    'colCache' => 'cache',
    'ignored_members_enabled' => false,
    'ignored_members' => array(
            array('pid' => "2627733530", 'sid' => "609452444"),
            array('pid' => "2627733530", 'sid' => "611528041"),
        ),
    'prebuy_enabled' => false,
    'prebuy_restricted' => array(3000, 3008),
);

$srv['srv_rcon']['ip']   = "109.239.152.153";
$srv['srv_rcon']['port'] = " 27100";
$srv['srv_rcon']['pwd']  = "Yu652bgl7objk1";

$scr = array(
      "cstMessage" => "%player you are being autokicked for %weapon",
      "enabled"    => true,
      "ignVIP"     => true,
      "name"       => "Default Limiter",
      "restrGuns"  => array(
                            3000, 3024
                        ),
  );

/* Connect to DB 
 ********************/
define('DB_TYPE', 'mysql');

define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_USER', 'root');
define('DB_PASS', 'Uhaw24K6');
define('DB_NAME', 'limiter');

// if (DB_TYPE=="mongodb") {
    // require_once __DIR__ . '/DB-mongo.php';
// } else {
    require_once __DIR__ . '/DB-pdosql.php';
// }

$db = new DB();

/* Load Classes for COMPOSER
 ***************************/
require_once VENDOR_DIR.'/autoload.php';

//// or if you don't have composer
// require_once "src/T4G/BFP4F/Rcon/Base.php";
// require_once "src/T4G/BFP4F/Rcon/Players.php";
// require_once "src/T4G/BFP4F/Rcon/Chat.php";
// require_once "src/T4G/BFP4F/Rcon/Server.php";
// require_once "src/T4G/BFP4F/Rcon/Support.php";

?>