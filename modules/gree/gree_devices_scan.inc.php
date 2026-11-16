<?php
/*
* Network scan for Gree and Cooper&Hunter air conditioners
* @version 1.0.2
*/
include_once(DIR_MODULES . 'gree/gree_device.class.php');

global $session;

if (isset($this->owner) && is_object($this->owner) && isset($this->owner->name) && $this->owner->name == 'panel') {
    $out['CONTROLPANEL'] = 1;
}

// This file is pulled in from inside a method, so the helper has to be guarded
// against a second declaration ("Cannot redeclare ...").
if (!function_exists('gree_scan')) {
    /**
     * @return array
     */
    function gree_scan()
    {
        $result = array();

        foreach (GreeDevice::Discover() as $device) {
            $result[] = array(
                'DEVTYPE' => $device->devtype(),
                'NAME' => $device->name(),
                'MAC' => $device->mac(),
                'HOST' => $device->host(),
                'TYPE' => $device->devmodel(),
                'KEYS' => '',
                'KEYS64' => '',
                'ENCRYPTION' => '',
            );
        }

        foreach (GreeDevice::DiscoverGREE() as $device) {
            $result[] = array(
                'DEVTYPE' => $device->devtype(),
                'NAME' => $device->name(),
                'MAC' => $device->mac(),
                'HOST' => $device->host(),
                'TYPE' => $device->devmodel(),
                'KEYS' => $device->keys(),
                // a Gree key is arbitrary printable ASCII and may contain & + # % =
                // so it has to be encoded before it can travel through a query string
                'KEYS64' => rtrim(strtr(base64_encode($device->keys()), '+/', '-_'), '='),
                'ENCRYPTION' => $device->encryption(),
            );
        }

        return $result;
    }
}

$res = gree_scan();

// mark devices that are already registered
$known = array();
$registered = SQLSelect("SELECT ID, MAC FROM gree_devices");
if (is_array($registered)) {
    foreach ($registered as $row) {
        $known[strtolower(str_replace(':', '', $row['MAC']))] = (int)$row['ID'];
    }
}

$total = count($res);
for ($i = 0; $i < $total; $i++) {
    $key = strtolower(str_replace(':', '', $res[$i]['MAC']));
    $res[$i]['KNOWN_ID'] = isset($known[$key]) ? $known[$key] : 0;
    $res[$i]['NAME_URL'] = urlencode($res[$i]['NAME']);
    $res[$i]['NAME'] = htmlspecialchars($res[$i]['NAME']);
}

$out['RESULT'] = $res;
