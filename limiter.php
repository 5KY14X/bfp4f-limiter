#!/usr/bin/env php
<?php
/**
 * Weapon Limiter
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

// composer vendor directory
define('VENDOR_DIR', __DIR__ . '/vendor');


$configs = array(
    'cacheThres' => 30, // 30 minutes
    'dsn' => 'mongodb://USER:PASSWORD@HOST:PORT/DATABASE',
    'db' => 'DATABASE',
    'colLogs' => 'logs',
    'colCache' => 'cache',
    'colScripts' => 'scripts',
    'colServer' => 'servers',
    'srvName' => 'default',
    'ignored_members_enabled' => false,
    'ignored_members' => array(
            array('pid' => "2627733530", 'sid' => "609452444"),
            array('pid' => "2627733530", 'sid' => "611528041"),
        ),
    'prebuy_enabled' => false,
    'prebuy_restricted' => array(3000, 3008),
);

/* Connect to DB 
 ********************/
$m = new MongoClient($configs['dsn'], array());
$mdb = $m->selectDB($configs['db']);

$c = $mdb->selectCollection($configs['colServer']);
$srv = $c->findOne(array('srv_key' => $configs['srvName']));

$c = $mdb->selectCollection($configs['colScripts']);
$scr = $c->findOne(array('type' => 'limiter', 'key' => 'default'));

/* Is limiter off? 
 ********************/
if ($scr['enabled'] === false) {
    echo('Limiter is switched off');
    exit(1);
}

/* Load Classes for COMPOSER
 ***************************/
require_once VENDOR_DIR.'/autoload.php';

use T4G\BFP4F\Rcon as rcon;
$rc = new rcon\Base();

/* Connect to server 
 ********************/
$rc->ip = $srv['srv_rcon']['ip'];
$rc->port = (int) $srv['srv_rcon']['port'];
$rc->pwd = $srv['srv_rcon']['pwd'];

$rc->connect($cn, $cs);

if ($cn !== 0) {
    $err = "E: Game server {$srv['srv_name']} is not responding;".PHP_EOL.
           "E: Invalid credentials or server is down;".PHP_EOL.
           "E: $cs ($cn)" . PHP_EOL;
    error_log($err);
    echo($err);
    exit(1);
}

$rc->init();

/* Retrieve data from game server 
 ********************************/

// Create Player Object
$rcp = new rcon\Players();

// Get Players Info
$players = $rcp->fetch();

// Toolbox
$sup = new rcon\Support();

foreach ($players as $player) {

    /* Skip player which has loading screen
     **************************************/
    if ($player->connected != '1') {
        continue;
    }

    /* Skip players with VIP status
     ******************************/
    if ($scr['ignVIP'] === true) {
        if ($player->vip == 1) {
            continue;
        }
    }

    /* Devlare tmp variable
     **********************/
    $decision = array(
        'kick' => false,
        'weapon_id' => '0',
        'reason' => "autokick"
    );

    /* Test - Ignore Selected soldiers (Not VIPs, but also not managed to leave)
     ***************************************************************************/
    if ($configs['ignored_members_enabled'] === true) {
        foreach ($configs['ignored_members'] as $ignored) {
            if ($ignored['pid'] == $player->nucleusId && $ignored['sid'] == $player->cdKeyHash) {
                continue;
            }
        }
    }

    /* Retrieve Loadout from cache collection or website.
     ***************************************************/
    $c = $mdb->selectCollection($configs['colCache']);
    $cache = $c->findOne(array('pid' => (string) $player->nucleusId, 'sid' => $player->cdKeyHash));

    if (empty($cache)) {
        $playerLoadout = new rcon\Stats((string) $player->nucleusId, $player->cdKeyHash);
        $loadout = $playerLoadout->retrieveLoadout();

        // Did I received a valid JSON?
        if ($playerLoadout->isValid($loadout) === false) {
            continue;
        }

        // Prepare loadout for storage into DB.
        foreach ($loadout['data']['equipment'] as $key => $value) {
            $loadout['storage'][$key] = $value['id'];
        }

        // Insert into cache collection
        $c->insert(
            array(
            'pid' => (string) $player->nucleusId,
            'sid' => $player->cdKeyHash,
            'date' => date("Y-m-d H:i:s"),
            'loadout' => $loadout['storage'],
            )
        );

    } else {
        // Time Difference
        $start_date = new DateTime('now');
        $since_start = $start_date->diff(new DateTime($cache['date']));
        $minutes = $since_start->days * 24 * 60;
        $minutes += $since_start->h * 60;
        $minutes += $since_start->i;

        // May I update data in DB?
        if ($minutes >= $configs['cacheThres']) {
            $playerLoadout = new rcon\Stats((string) $player->nucleusId, $player->cdKeyHash);
            $loadout = $playerLoadout->retrieveLoadout();

            // Prepare loadout for storage into DB.
            foreach ($loadout['data']['equipment'] as $key => $value) {
                $loadout['storage'][$key] = $value['id'];
            }

            $c->update(
                array(
                    'pid' => (string) $player->nucleusId,
                    'sid' => $player->cdKeyHash
                ),
                array(
                    'pid' => (string) $player->nucleusId,
                    'sid' => $player->cdKeyHash,
                    'date' => date("Y-m-d H:i:s"),
                    'loadout' => $loadout['storage'],
                )
            );            
        } else {
            // Cached - Just load it from DB
            $loadout['storage'] = $cache['loadout'];
        }
    }

    /* Look ma! I am *Valid* 
     ***********************/
    foreach ($loadout['storage'] as $weapon) {

        /* I haz too much monies?
         ************************/
        if ($configs['prebuy_enabled']===true) {
            if (($sup->weaponGetReqLvl($weapon) < $player->level) && in_array($weapon, $configs['prebuy_restricted']) ) {
                $decision['kick'] = true;
                $decision['weapon_id'] = $weapon;
                $decision['reason'] => "Prebought gun: ".$sup->weaponGetName($weapon).". Already on ".$player->level." lvl";
            }
        }

        /* I haz too big gun?
         ********************/
        if (in_array($weapon, $scr['restrGuns'])) {
            $decision['kick'] = true;
            $decision['weapon_id'] = $weapon;
            $decision['reason'] => "Disallowed gun: ".$sup->weaponGetName($weapon);
        }
    }

    /* For sure. Bai
     ***************/
    if ($decision['kick'] === true) {
        $reason = preg_replace('/%player/', $player->name, $scr['cstMessage']);
        $reason = preg_replace('/%weapon/', $sup->weaponGetName($decision['wid']), $reason);
        $rcp->kick($player->name, $reason);

        $c = $mdb->selectCollection($configs['colLogs']);
        $c->insert(
            array(
                'pid' => (string) $player->nucleusId,
                'sid' => $player->cdKeyHash,
                'soldier' => $player->name,
                'date' => date("Y-m-d H:i:s"),
                'server' => $configs['srvName'],
                'action' => 'autokick',
                'reason' => $decision['reason'],
                'script' => array(
                    'script' => 'weapon-limiter',
                    'setup' => 'default',
                )
            )
        );

    }
}

// Notice to stdout
echo "Completed. " . date("Y-m-d H:i:s") . PHP_EOL;

