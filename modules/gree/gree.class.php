<?php
/**
 * GREE: Gree and Cooper&Hunter air conditioners for MajorDoMo
 *
 * @package majordomo-gree
 * @version 1.0.1 (PHP 7.0 - 8.4)
 */
if (!function_exists('gree_json')) {
    /**
     * JSON for embedding into a template: safe inside <script> and free of the
     * "%object.property%" and "[#...#]" patterns the MajorDoMo template engine expands.
     */
    function gree_json($data)
    {
        $json = json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        if ($json === false) {
            return '{}';
        }
        // '%' and '#' only ever occur inside JSON strings, where \\uXXXX is equivalent
        return str_replace(array('%', '#'), array('\\u0025', '\\u0023'), $json);
    }
}

class gree extends module
{
    // module::__construct() only declares a part of the properties this module uses.
    // Declaring them here avoids "Creation of dynamic property" deprecations (PHP 8.2+).
    public $title;
    public $module_category;
    public $id;
    public $page;
    public $type;
    public $mac;
    public $keys;
    public $host;
    public $devtype;
    public $encryption;
    public $title_new;

    /** property name => setter method, per device family */
    private static $HANDLERS = array(
        'CH' => array(
            'temperature' => 'set_temp',
            'power' => 'set_power',
            'ac_mode' => 'set_ac_mode',
            'fan_speed' => 'set_fan_speed',
            'quiet' => 'set_quiet',
            'fan_direction' => 'set_fan_direction',
            'stepless_max' => 'set_stepless_max',
            'light' => 'set_light',
            'health' => 'set_health',
            'sleep' => 'set_sleep',
            'energy_save' => 'set_energy_save',
            'eco' => 'set_eco',
        ),
        'Gree' => array(
            'temperature' => 'set_temp',
            'power' => 'set_power',
            'ac_mode' => 'set_ac_mode',
            'fan_speed' => 'set_fan_speed',
            'quiet' => 'set_quiet',
            'fan_direction' => 'set_fan_direction',
            'fan_directionh' => 'set_fan_directionh',
            'light' => 'set_light',
            'health' => 'set_health',
            'sleep' => 'set_sleep',
            'energy_save' => 'set_energy_save',
            'turbo' => 'set_turbo',
            'air' => 'set_air',
            'blow' => 'set_blow',
            'stht' => 'set_stht',
        ),
    );

    /** polling interval code => seconds */
    public static $INTERVALS = array(
        'none' => 0, '1s' => 1, '5s' => 5, '20s' => 20, '1m' => 60, '10m' => 600, '1h' => 3600,
    );

    /**
     * Human readable description of device properties, used by the UI.
     * kind: bool | enum | temp | num | ro
     */
    public static function propertyMeta()
    {
        $modes = array(0 => 'Авто', 1 => 'Охлаждение', 2 => 'Осушение', 3 => 'Вентиляция', 4 => 'Обогрев');
        $fan = array(0 => 'Авто', 1 => 'Низкая', 2 => 'Ниже средней', 3 => 'Средняя', 4 => 'Выше средней', 5 => 'Высокая');
        $swing_v = array(
            0 => 'По умолчанию', 1 => 'Качание, весь диапазон',
            2 => 'Фикс. вверху', 3 => 'Фикс. выше середины', 4 => 'Фикс. посередине',
            5 => 'Фикс. ниже середины', 6 => 'Фикс. внизу',
            7 => 'Качание внизу', 8 => 'Качание ниже середины', 9 => 'Качание посередине',
            10 => 'Качание выше середины', 11 => 'Качание вверху',
        );
        $swing_h = array(
            0 => 'По умолчанию', 1 => 'Качание', 2 => 'Фикс. слева', 3 => 'Фикс. левее центра',
            4 => 'Фикс. по центру', 5 => 'Фикс. правее центра', 6 => 'Фикс. справа',
        );
        return array(
            'power' => array('label' => 'Питание', 'kind' => 'bool'),
            'ac_mode' => array('label' => 'Режим работы', 'kind' => 'enum', 'options' => $modes),
            'temperature' => array('label' => 'Заданная температура, °C', 'kind' => 'temp'),
            'indoorTemperature' => array('label' => 'Температура в помещении, °C', 'kind' => 'ro'),
            'fan_speed' => array('label' => 'Скорость вентилятора', 'kind' => 'enum', 'options' => $fan),
            'fan_direction' => array('label' => 'Жалюзи вверх/вниз', 'kind' => 'enum', 'options' => $swing_v),
            'fan_directionh' => array('label' => 'Жалюзи влево/вправо', 'kind' => 'enum', 'options' => $swing_h),
            'quiet' => array('label' => 'Тихий режим', 'kind' => 'enum', 'options' => array(0 => 'Выкл', 1 => 'Авто', 2 => 'Вкл')),
            'turbo' => array('label' => 'Турбо', 'kind' => 'bool'),
            'light' => array('label' => 'Подсветка дисплея', 'kind' => 'bool'),
            'health' => array('label' => 'Ионизатор (Health)', 'kind' => 'bool'),
            'sleep' => array('label' => 'Режим сна', 'kind' => 'bool'),
            'blow' => array('label' => 'X-Fan, просушка теплообменника', 'kind' => 'bool'),
            'air' => array('label' => 'Приток свежего воздуха', 'kind' => 'bool'),
            'energy_save' => array('label' => 'Энергосбережение', 'kind' => 'bool'),
            'eco' => array('label' => 'ECO', 'kind' => 'bool'),
            'stht' => array('label' => 'Дежурный обогрев +8 °C', 'kind' => 'bool'),
            'stepless_max' => array('label' => 'Бесступенчатая скорость', 'kind' => 'num'),
            'temptype' => array('label' => 'Единицы температуры (0 °C, 1 °F)', 'kind' => 'ro'),
            'heatcooltype' => array('label' => 'Тип нагрев/охлаждение', 'kind' => 'ro'),
            'temrec' => array('label' => 'Бит округления °F', 'kind' => 'ro'),
            'dry' => array('label' => 'Осушение (флаг)', 'kind' => 'ro'),
            'timing' => array('label' => 'Таймер активен', 'kind' => 'ro'),
            'wdnumber_mode' => array('label' => 'Режим скоростей', 'kind' => 'ro'),
        );
    }

    /**
     * Module class constructor
     *
     * @access public
     */
    public function __construct()
    {
        parent::__construct();
        $this->name = "gree";
        $this->title = "GREE";
        $this->module_category = "<#LANG_SECTION_DEVICES#>";
        $this->checkInstalled();
    }

    /**
     * saveParams
     *
     * Saving module parameters
     *
     * @access public
     */
    function saveParams($data = 0)
    {
        $p = array();
        if (isset($this->id)) {
            $p["id"] = $this->id;
        }
        if (isset($this->view_mode)) {
            $p["view_mode"] = $this->view_mode;
        }
        if (isset($this->edit_mode)) {
            $p["edit_mode"] = $this->edit_mode;
        }
        if (isset($this->data_source)) {
            $p["data_source"] = $this->data_source;
        }
        if (isset($this->tab)) {
            $p["tab"] = $this->tab;
        }
        if (isset($this->page)) {
            $p["page"] = $this->page;
        }
        return parent::saveParams($p);
    }

    /**
     * getParams
     *
     * Getting module parameters from query string
     *
     * @access public
     */
    function getParams()
    {
        global $id;
        global $mode;
        global $mac;
        global $keys;
        global $host;
        global $title;
        global $devtype;
        global $encryption;
        global $type;
        global $view_mode;
        global $edit_mode;
        global $data_source;
        global $tab;
        global $title_new;

        if (isset($title)) {
            $this->title = $title;
        }
        if (isset($id)) {
            $this->id = $id;
        }
        if (isset($mode)) {
            $this->mode = $mode;
        }
        if (isset($mac)) {
            $this->mac = $mac;
        }
        if (isset($keys)) {
            $this->keys = $keys;
        }
        if (isset($host)) {
            $this->host = $host;
        }
        if (isset($devtype)) {
            $this->devtype = $devtype;
        }
        if (isset($encryption)) {
            $this->encryption = $encryption;
        }
        if (isset($type)) {
            $this->type = $type;
        }
        if (isset($view_mode)) {
            $this->view_mode = $view_mode;
        }
        if (isset($edit_mode)) {
            $this->edit_mode = $edit_mode;
        }
        if (isset($data_source)) {
            $this->data_source = $data_source;
        }
        if (isset($tab)) {
            $this->tab = $tab;
        }
        if (isset($title_new)) {
            $this->title_new = $title_new;
        }
    }

    /**
     * Run
     *
     * @access public
     */
    function run()
    {
        global $session;
        $out = array();

        if ($this->action == 'admin') {
            $this->admin($out);
        } else {
            $this->usual($out);
        }

        if (isset($this->owner) && is_object($this->owner)) {
            if (isset($this->owner->action)) {
                $out['PARENT_ACTION'] = $this->owner->action;
            }
            if (isset($this->owner->name)) {
                $out['PARENT_NAME'] = $this->owner->name;
            }
        }

        $out['VIEW_MODE'] = isset($this->view_mode) ? $this->view_mode : '';
        $out['EDIT_MODE'] = isset($this->edit_mode) ? $this->edit_mode : '';
        $out['MODE'] = isset($this->mode) ? $this->mode : '';
        $out['ACTION'] = isset($this->action) ? $this->action : '';
        $out['DATA_SOURCE'] = isset($this->data_source) ? $this->data_source : '';
        $out['TAB'] = isset($this->tab) ? $this->tab : '';

        $this->data = $out;
        $p = new parser(DIR_TEMPLATES . $this->name . "/" . $this->name . ".html", $this->data, $this);
        $this->result = $p->result;
    }

    /**
     * BackEnd
     *
     * @access public
     */
    function admin(&$out)
    {
        $this->getConfig();

        // JSON endpoint for the control UI. It lives in admin() on purpose:
        // admin.php requires an authorised panel session.
        $op = gr('gree_op');
        if ($op != '' && $this->action == 'admin') {
            $this->ajax_api($op);
        }

        $view_mode = isset($this->view_mode) ? $this->view_mode : '';
        $data_source = isset($this->data_source) ? $this->data_source : '';

        if ($view_mode == 'update_settings') {
            $this->saveConfig();
            $this->redirect("?");
        }
        if (isset($this->mode) && $this->mode == 'check_params') {
            $this->check_params();
        }
        // isset() on $_GET/$_POST keys - a plain !$_GET['x'] raises a warning on PHP 8
        if ($data_source != '' && empty($_GET['data_source']) && empty($_POST['data_source'])) {
            $out['SET_DATASOURCE'] = 1;
        }

        if ($data_source == 'gree_devices' || $data_source == '') {
            if ($view_mode == '' || $view_mode == 'search_gree_devices') {
                $this->search_gree_devices($out);
            }
            if ($view_mode == 'edit_gree_devices') {
                $this->edit_gree_devices($out, (int)$this->id);
            }
            if ($view_mode == 'delete_gree_devices') {
                $this->delete_gree_devices((int)$this->id);
                $this->redirect("?data_source=gree_devices");
            }
            if ($view_mode == 'gree_devices_scan') {
                $this->gree_devices_scan($out);
            }
        }
    }

    /**
     * FrontEnd
     *
     * @access public
     */
    function usual(&$out)
    {
        $this->admin($out);
    }

    /**
     * gree_devices search
     * @access public
     */
    function search_gree_devices(&$out)
    {
        require(DIR_MODULES . $this->name . '/gree_devices_search.inc.php');
    }

    /**
     * gree_devices_scan
     * @access public
     */
    function gree_devices_scan(&$out)
    {
        require(DIR_MODULES . $this->name . '/gree_devices_scan.inc.php');
    }

    /**
     * gree_devices edit/add
     * @access public
     */
    function edit_gree_devices(&$out, $id)
    {
        require(DIR_MODULES . $this->name . '/gree_devices_edit.inc.php');
    }

    /**
     * gree_devices delete record
     * @access public
     */
    function delete_gree_devices($id)
    {
        $id = (int)$id;
        if (!$id) {
            return;
        }

        $properties = SQLSelect("SELECT * FROM gree_properties WHERE DEVICE_ID=" . $id);
        if (is_array($properties)) {
            foreach ($properties as $property) {
                if (!empty($property['LINKED_OBJECT']) && !empty($property['LINKED_PROPERTY'])) {
                    removeLinkedProperty($property['LINKED_OBJECT'], $property['LINKED_PROPERTY'], $this->name);
                }
            }
        }

        SQLExec("DELETE FROM gree_devices WHERE ID=" . $id);
        SQLExec("DELETE FROM gree_properties WHERE DEVICE_ID=" . $id);
    }

    /**
     * Called by the core when a linked object property is changed.
     *
     * @return bool
     */
    function propertySetHandle($object, $property, $value)
    {
        $this->getConfig();
        include_once(DIR_MODULES . $this->name . '/gree_device.class.php');

        $table = 'gree_properties';
        $properties = SQLSelect(
            "SELECT * FROM $table WHERE LINKED_OBJECT='" . DBSafe($object) . "'"
            . " AND LINKED_PROPERTY='" . DBSafe($property) . "'"
        );

        if (!is_array($properties) || !count($properties)) {
            return false;
        }

        $handled = false;

        foreach ($properties as $prop) {
            $device_id = (int)$prop['DEVICE_ID'];
            if (!$device_id) {
                continue;
            }

            $rec = SQLSelectOne("SELECT * FROM gree_devices WHERE ID=" . $device_id);
            if (!is_array($rec) || !isset($rec['ID'])) {
                continue;
            }

            $rm = GreeDevice::CreateDevice(
                $rec['IP'],
                $rec['MAC'],
                $rec['DEVTYPE'],
                isset($rec['KEYS']) ? $rec['KEYS'] : '',
                isset($rec['ENCRYPTION']) ? $rec['ENCRYPTION'] : 'ECB'
            );

            if ($rm === null) {
                DebMes('gree: unsupported device type for ' . $rec['TITLE'], 'error');
                continue;
            }

            $family = isset($rec['TYPE']) ? $rec['TYPE'] : '';
            if (!isset(self::$HANDLERS[$family])) {
                continue;
            }

            $title = isset($prop['TITLE']) ? $prop['TITLE'] : '';
            if (!isset(self::$HANDLERS[$family][$title])) {
                continue;
            }

            $method = self::$HANDLERS[$family][$title];
            if (!$rm->$method($value)) {
                DebMes('gree: ' . $rec['TITLE'] . ' ' . $title . '=' . $value . ' failed (' . $rm->lastError() . ')', 'error');
            }

            $prop['VALUE'] = $value;
            SQLUpdate($table, $prop);
            $handled = true;
        }

        return $handled;
    }

    /**
     * Poll devices and publish their state.
     *
     * @param string $chtime ''    - every device (manual refresh from the UI)
     *                       'all' - every device that is not disabled
     *                       other - only devices with this polling interval
     */
    function check_params($chtime = '')
    {
        $this->getConfig();

        $chtime = (string)$chtime;

        if ($chtime === '') {
            $db_rec = SQLSelect("SELECT * FROM gree_devices");
        } elseif ($chtime === 'all') {
            $db_rec = SQLSelect("SELECT * FROM gree_devices WHERE CHTIME<>'none'");
        } else {
            $db_rec = SQLSelect("SELECT * FROM gree_devices WHERE CHTIME='" . DBSafe($chtime) . "'");
        }

        if (!is_array($db_rec) || !count($db_rec)) {
            return;
        }

        foreach ($db_rec as $rec) {
            $this->poll_device($rec);
        }
    }

    /**
     * @param array $rec gree_devices row
     * @return GreeDevice|null
     */
    function device_object($rec)
    {
        include_once(DIR_MODULES . $this->name . '/gree_device.class.php');
        if (!is_array($rec) || !isset($rec['ID'])) {
            return null;
        }
        return GreeDevice::CreateDevice(
            $rec['IP'],
            $rec['MAC'],
            $rec['DEVTYPE'],
            isset($rec['KEYS']) ? $rec['KEYS'] : '',
            isset($rec['ENCRYPTION']) ? $rec['ENCRYPTION'] : 'ECB'
        );
    }

    /**
     * Read one device and publish its state.
     *
     * @param array $rec gree_devices row
     * @return string empty on success, error text otherwise
     */
    function poll_device($rec)
    {
        $rm = $this->device_object($rec);
        if ($rm === null) {
            DebMes('gree: device ' . $rec['TITLE'] . ' has an unsupported type ' . $rec['DEVTYPE'], 'error');
            return 'неподдерживаемый тип устройства';
        }

        $response = $rm->get_status();

        if (!is_array($response) || !count($response)) {
            DebMes('gree: device ' . $rec['TITLE'] . ' is not available (' . $rm->lastError() . ')', 'error');
            return 'устройство не отвечает (' . $rm->lastError() . ')';
        }

        $this->publish_state((int)$rec['ID'], $response);

        SQLExec("UPDATE gree_devices SET UPDATED='" . date('Y-m-d H:i:s') . "' WHERE ID=" . (int)$rec['ID']);
        return '';
    }

    /**
     * Send a value to the device from the UI and publish it.
     *
     * @return string empty on success, error text otherwise
     */
    function set_device_value($device_id, $prop, $value)
    {
        $rec = SQLSelectOne("SELECT * FROM gree_devices WHERE ID=" . (int)$device_id);
        if (!is_array($rec) || !isset($rec['ID'])) {
            return 'устройство не найдено';
        }
        $family = isset($rec['TYPE']) ? $rec['TYPE'] : '';
        if (!isset(self::$HANDLERS[$family][$prop])) {
            return 'свойство ' . $prop . ' только для чтения';
        }
        $rm = $this->device_object($rec);
        if ($rm === null) {
            return 'неподдерживаемый тип устройства';
        }
        $method = self::$HANDLERS[$family][$prop];
        if (!$rm->$method($value)) {
            $err = $rm->lastError();
            DebMes('gree: ' . $rec['TITLE'] . ' ' . $prop . '=' . $value . ' failed (' . $err . ')', 'error');
            return 'команда не выполнена' . ($err != '' ? ' (' . $err . ')' : '');
        }
        // publish to the linked object right away, without waiting for the next poll
        $this->table_data_set($prop, (int)$rec['ID'], $value);
        return '';
    }

    function isWritable($family, $prop)
    {
        return isset(self::$HANDLERS[$family][$prop]);
    }

    /**
     * State of the devices for the UI.
     *
     * @param int $id 0 - every device
     * @return array
     */
    function devices_state($id = 0)
    {
        $where = $id ? ' WHERE ID=' . (int)$id : '';
        $devices = SQLSelect("SELECT * FROM gree_devices" . $where . " ORDER BY TITLE");
        if (!is_array($devices)) {
            return array();
        }
        // properties of every listed device in one query
        $ids = array();
        foreach ($devices as $rec) {
            $ids[] = (int)$rec['ID'];
        }
        $props_by_device = array();
        $links_by_device = array();
        if (count($ids)) {
            $rows = SQLSelect("SELECT DEVICE_ID, TITLE, VALUE, LINKED_OBJECT, LINKED_PROPERTY FROM gree_properties"
                . " WHERE DEVICE_ID IN (" . implode(',', $ids) . ")");
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    $dev = (int)$row['DEVICE_ID'];
                    $props_by_device[$dev][$row['TITLE']] = $row['VALUE'];
                    if ($row['LINKED_OBJECT'] != '') {
                        $links_by_device[$dev][$row['TITLE']] = $row['LINKED_OBJECT'] . '.' . $row['LINKED_PROPERTY'];
                    }
                }
            }
        }

        $result = array();
        foreach ($devices as $rec) {
            $props = isset($props_by_device[(int)$rec['ID']]) ? $props_by_device[(int)$rec['ID']] : array();
            $links = isset($links_by_device[(int)$rec['ID']]) ? $links_by_device[(int)$rec['ID']] : array();
            $updated = (isset($rec['UPDATED']) && $rec['UPDATED'] != '' && strpos($rec['UPDATED'], '0000') !== 0)
                ? strtotime($rec['UPDATED']) : 0;
            $interval = isset(self::$INTERVALS[$rec['CHTIME']]) ? self::$INTERVALS[$rec['CHTIME']] : 60;
            $family = isset($rec['TYPE']) ? $rec['TYPE'] : '';
            $result[] = array(
                'id' => (int)$rec['ID'],
                'title' => $rec['TITLE'],
                'type' => $family,
                'ip' => $rec['IP'],
                'mac' => $rec['MAC'],
                'encryption' => isset($rec['ENCRYPTION']) ? $rec['ENCRYPTION'] : 'ECB',
                'chtime' => $rec['CHTIME'],
                'bound' => ($family != 'Gree' || (isset($rec['KEYS']) && $rec['KEYS'] != '')),
                'updated' => $updated,
                'age' => $updated ? max(0, time() - $updated) : null,
                // online: answered within three polling periods (at least three minutes)
                'online' => $updated ? ((time() - $updated) <= max(180, $interval * 3)) : false,
                'writable' => isset(self::$HANDLERS[$family]) ? array_keys(self::$HANDLERS[$family]) : array(),
                'props' => $props,
                'links' => $links,
            );
        }
        return $result;
    }

    /**
     * JSON endpoint: ?gree_op=state|poll|set|pair
     */
    function ajax_api($op)
    {
        $res = array('ok' => true, 'error' => '');
        $id = (int)gr('id');

        if ($op == 'poll') {
            $rec = SQLSelectOne("SELECT * FROM gree_devices WHERE ID=" . $id);
            if (is_array($rec) && isset($rec['ID'])) {
                $res['error'] = $this->poll_device($rec);
            } else {
                $res['error'] = 'устройство не найдено';
            }
        } elseif ($op == 'cycle_restart') {
            // picked up by cycle.php within ~30 seconds
            setGlobal('cycle_greeControl', 'restart');
        } elseif ($op == 'poll_all') {
            $this->check_params('all');
        } elseif ($op == 'set') {
            $res['error'] = $this->set_device_value($id, (string)gr('prop'), gr('value'));
        } elseif ($op == 'pair') {
            include_once(DIR_MODULES . $this->name . '/gree_device.class.php');
            $ip = trim((string)gr('ip'));
            $mac = strtolower(preg_replace('/[^0-9a-f]/i', '', (string)gr('mac')));
            $encryption = strtoupper((string)gr('encryption')) == 'GCM' ? 'GCM' : 'ECB';
            if ($ip == '' || strlen($mac) != 12) {
                $res['error'] = 'укажите IP и MAC устройства';
            } else {
                $key = GreeDevice::pair($mac, $ip, $encryption);
                if ($key === '') {
                    $res['error'] = 'кондиционер не ответил на запрос привязки';
                } else {
                    $res['key'] = $key;
                    $res['encryption'] = $encryption;
                    if ($id) {
                        SQLExec("UPDATE gree_devices SET `KEYS`='" . DBSafe($key) . "', ENCRYPTION='" . $encryption . "' WHERE ID=" . $id);
                    }
                }
            }
        } elseif ($op != 'state') {
            $res['error'] = 'unknown operation';
        }

        if ($res['error'] != '') {
            $res['ok'] = false;
        }
        $res['cycle'] = ((time() - (int)gg('cycle_greeRun')) < 15);
        $res['devices'] = $this->devices_state($op == 'poll_all' ? 0 : $id);

        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($res);
        exit;
    }

    /**
     * Store a whole poll result: one SELECT for the device, writes only for
     * properties whose value changed.
     *
     * @param int   $dev_id
     * @param array $values property => value
     */
    function publish_state($dev_id, $values)
    {
        $dev_id = (int)$dev_id;
        if (!$dev_id || !is_array($values) || !count($values)) {
            return;
        }

        $existing = array();
        $rows = SQLSelect("SELECT * FROM gree_properties WHERE DEVICE_ID=" . $dev_id);
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (!isset($existing[$row['TITLE']])) {
                    $existing[$row['TITLE']] = $row;
                }
            }
        }

        foreach ($values as $prop => $val) {
            if (!isset($existing[$prop])) {
                $new = array(
                    'TITLE' => $prop,
                    'DEVICE_ID' => $dev_id,
                    'VALUE' => $val,
                    'LINKED_OBJECT' => '',
                    'LINKED_PROPERTY' => '',
                    'LINKED_METHOD' => '',
                );
                SQLInsert('gree_properties', $new); // takes the record by reference
                continue;
            }
            $record = $existing[$prop];
            if ((string)$val === (string)$record['VALUE']) {
                continue;
            }
            $record['VALUE'] = $val;
            SQLUpdate('gree_properties', $record);
            if ($record['LINKED_OBJECT'] != '' && $record['LINKED_PROPERTY'] != '') {
                // exclude this module from the linked modules chain, otherwise a value
                // read from the device is immediately written back to it
                sg($record['LINKED_OBJECT'] . '.' . $record['LINKED_PROPERTY'], $val, array($this->name => 1));
            }
        }
    }

    /**
     * Store a single device property and push it to the linked object.
     */
    function table_data_set($prop, $dev_id, $val, $sg_val = null)
    {
        $table = 'gree_properties';
        $dev_id = (int)$dev_id;

        $record = SQLSelectOne(
            "SELECT * FROM $table WHERE TITLE='" . DBSafe($prop) . "' AND DEVICE_ID=" . $dev_id
        );

        // SQLSelectOne() returns NULL when nothing matched; count(NULL) is a
        // TypeError since PHP 8.0
        if (is_array($record) && isset($record['ID'])) {
            if ((string)$val === (string)$record['VALUE']) {
                return;
            }

            $record['VALUE'] = $val;
            SQLUpdate($table, $record);

            if (!empty($record['LINKED_OBJECT']) && !empty($record['LINKED_PROPERTY'])) {
                // exclude this module from the linked modules chain, otherwise a value
                // read from the device is immediately written back to it
                sg(
                    $record['LINKED_OBJECT'] . '.' . $record['LINKED_PROPERTY'],
                    is_null($sg_val) ? $val : $sg_val,
                    array($this->name => 1)
                );
            }
        } else {
            $record = array(
                'TITLE' => $prop,
                'DEVICE_ID' => $dev_id,
                'VALUE' => $val,
                'LINKED_OBJECT' => '',
                'LINKED_PROPERTY' => '',
                'LINKED_METHOD' => '',
            );
            SQLInsert($table, $record);
        }
    }

    /**
     * Install
     * @access private
     */
    function install($data = '')
    {
        parent::install();
    }

    /**
     * Uninstall
     * @access public
     */
    function uninstall()
    {
        SQLExec('DROP TABLE IF EXISTS gree_devices');
        SQLExec('DROP TABLE IF EXISTS gree_properties');
        parent::uninstall();
    }

    /**
     * dbInstall
     * @access private
     */
    function dbInstall($data = '')
    {
        /*
        gree_devices  - registered devices
        gree_properties - device properties and their links to objects
        */
        $data = <<<EOD
 gree_devices: ID int(10) unsigned NOT NULL auto_increment
 gree_devices: TYPE varchar(10) NOT NULL DEFAULT ''
 gree_devices: TITLE varchar(100) NOT NULL DEFAULT ''
 gree_devices: DEVTYPE varchar(10) NOT NULL DEFAULT ''
 gree_devices: IP varchar(45) NOT NULL DEFAULT ''
 gree_devices: MAC varchar(20) NOT NULL DEFAULT ''
 gree_devices: CHTIME varchar(10) NOT NULL DEFAULT ''
 gree_devices: KEYS varchar(128) NOT NULL DEFAULT ''
 gree_devices: ENCRYPTION varchar(10) NOT NULL DEFAULT 'ECB'
 gree_devices: UPDATED datetime
 gree_properties: ID int(10) unsigned NOT NULL auto_increment
 gree_properties: TITLE varchar(100) NOT NULL DEFAULT ''
 gree_properties: VALUE text
 gree_properties: DEVICE_ID int(10) NOT NULL DEFAULT '0'
 gree_properties: LINKED_OBJECT varchar(100) NOT NULL DEFAULT ''
 gree_properties: LINKED_PROPERTY varchar(100) NOT NULL DEFAULT ''
 gree_properties: LINKED_METHOD varchar(100) NOT NULL DEFAULT ''
 gree_properties: INDEX (DEVICE_ID)
 gree_properties: INDEX (LINKED_OBJECT)
EOD;
        parent::dbInstall($data);
    }
}
