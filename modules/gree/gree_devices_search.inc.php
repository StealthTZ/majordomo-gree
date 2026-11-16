<?php
/*
* @version 1.0.2
*/
global $session;

if (isset($this->owner) && is_object($this->owner) && isset($this->owner->name) && $this->owner->name == 'panel') {
    $out['CONTROLPANEL'] = 1;
}

$out['CYCLERUN'] = ((time() - (int)gg('cycle_greeRun')) < 15) ? 1 : 0;

$sortby_gree_devices = "ID DESC";
$out['SORTBY'] = $sortby_gree_devices;

// SEARCH RESULTS
$res = SQLSelect("SELECT * FROM gree_devices ORDER BY " . $sortby_gree_devices);

if (is_array($res) && isset($res[0]['ID'])) {
    $total = count($res);
    for ($i = 0; $i < $total; $i++) {
        foreach (array('TITLE', 'IP', 'MAC', 'TYPE', 'CHTIME') as $field) {
            $res[$i][$field] = htmlspecialchars(isset($res[$i][$field]) ? (string)$res[$i][$field] : '');
        }
        $updated = isset($res[$i]['UPDATED']) ? (string)$res[$i]['UPDATED'] : '';
        if ($updated === '' || strpos($updated, '0000-00-00') === 0) {
            $res[$i]['UPDATED'] = '';
            continue;
        }
        $tmp = explode(' ', $updated);
        $res[$i]['UPDATED'] = fromDBDate($tmp[0]) . (isset($tmp[1]) ? ' ' . $tmp[1] : '');
    }
    $out['RESULT'] = $res;
}

$out['TOTAL'] = is_array($res) ? count($res) : 0;
$out['STATE_JSON'] = gree_json(array('devices' => $this->devices_state(0), 'meta' => gree::propertyMeta()));
