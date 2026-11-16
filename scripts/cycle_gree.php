<?php
/**
 * gree polling cycle
 * @version 1.0.2
 */

chdir(dirname(__FILE__) . '/../');

include_once("./config.php");
include_once("./lib/loader.php");
include_once("./lib/threads.php");
set_time_limit(0);

// connecting to database
$db = new mysql(DB_HOST, '', DB_USER, DB_PASSWORD, DB_NAME);

include_once("./load_settings.php");
include_once(DIR_MODULES . "control_modules/control_modules.class.php");
$ctl = new control_modules();
include_once(DIR_MODULES . 'gree/gree.class.php');
$br = new gree();
$br->getConfig();

$tmp = SQLSelectOne("SELECT ID FROM gree_devices LIMIT 1");
if (!is_array($tmp) || empty($tmp['ID'])) {
    exit; // no devices added -- no need to run this cycle
}

echo date("H:i:s") . " running " . basename(__FILE__) . PHP_EOL;

$cycle_name = str_replace('.php', '', basename(__FILE__));

// interval driven scheduling: robust to polls that take longer than a second
$intervals = array(
    '1s' => 1,
    '5s' => 5,
    '20s' => 20,
    '1m' => 60,
    '10m' => 600,
    '1h' => 3600,
);

$next_run = array();
$now = time();
foreach ($intervals as $key => $seconds) {
    $next_run[$key] = $now;
}

$onetime = (isset($argv) && is_array($argv) && in_array('onetime', $argv, true))
    || isset($_GET['onetime']);

// polling intervals actually used by devices, re-read every 30 s so that
// unused intervals cost no database query at all
$used = array();
$used_checked = 0;

while (1) {
    setGlobal($cycle_name . 'Run', time(), 1);

    $now = time();
    if ($now - $used_checked >= 30) {
        $used = array();
        $rows = SQLSelect("SELECT DISTINCT CHTIME FROM gree_devices");
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $used[$row['CHTIME']] = 1;
            }
        }
        $used_checked = $now;
    }

    foreach ($intervals as $key => $seconds) {
        if (!isset($used[$key])) {
            continue;
        }
        if ($now >= $next_run[$key]) {
            // skip the periods missed while a slow poll was running
            $next_run[$key] = $now + $seconds;
            try {
                $br->check_params($key);
            } catch (Exception $e) {
                DebMes('gree cycle error (' . $key . '): ' . $e->getMessage(), 'error');
            } catch (Error $e) {
                DebMes('gree cycle error (' . $key . '): ' . $e->getMessage(), 'error');
            }
        }
    }

    if (file_exists('./reboot') || $onetime) {
        $db->Disconnect();
        exit;
    }

    sleep(1);
}
