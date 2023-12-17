<?php

declare(strict_types=1);

require_once __DIR__ . '/../libs/common.php';
require_once __DIR__ . '/../libs/local.php';

class SolarwattEnergymanager extends IPSModule
{
    use Solarwatt\StubsCommonLib;
    use SolarwattLocalLib;

    public function __construct(string $InstanceID)
    {
        parent::__construct($InstanceID);

        $this->CommonContruct(__DIR__);
    }

    public function __destruct()
    {
        $this->CommonDestruct();
    }

    public function Create()
    {
        parent::Create();

        $this->RegisterPropertyBoolean('module_disable', false);

        $this->RegisterPropertyString('host', '');
        $this->RegisterPropertyString('password', '');

        $this->RegisterPropertyInteger('update_interval', 60);

        $this->RegisterAttributeString('UpdateInfo', json_encode([]));
        $this->RegisterAttributeString('ModuleStats', json_encode([]));

        $this->InstallVarProfiles(false);

        $this->RegisterTimer('UpdateStatus', 0, 'IPS_RequestAction(' . $this->InstanceID . ', "UpdateStatus", "");');

        $this->RegisterMessage(0, IPS_KERNELMESSAGE);
    }

    public function MessageSink($timestamp, $senderID, $message, $data)
    {
        parent::MessageSink($timestamp, $senderID, $message, $data);

        if ($message == IPS_KERNELMESSAGE && $data[0] == KR_READY) {
            $this->SetUpdateInterval();
        }
    }

    private function CheckModuleConfiguration()
    {
        $r = [];

        $host = $this->ReadPropertyString('host');
        if ($host == '') {
            $this->SendDebug(__FUNCTION__, '"host" is needed', 0);
            $r[] = $this->Translate('Host must be specified');
        }

        return $r;
    }

    private function CheckModuleUpdate(array $oldInfo, array $newInfo)
    {
        $r = [];

        return $r;
    }

    private function CompleteModuleUpdate(array $oldInfo, array $newInfo)
    {
        return '';
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        $this->MaintainReferences();

        if ($this->CheckPrerequisites() != false) {
            $this->MaintainTimer('UpdateStatus', 0);
            $this->MaintainStatus(self::$IS_INVALIDPREREQUISITES);
            return;
        }

        if ($this->CheckUpdate() != false) {
            $this->MaintainTimer('UpdateStatus', 0);
            $this->MaintainStatus(self::$IS_UPDATEUNCOMPLETED);
            return;
        }

        if ($this->CheckConfiguration() != false) {
            $this->MaintainTimer('UpdateStatus', 0);
            $this->MaintainStatus(self::$IS_INVALIDCONFIG);
            return;
        }

        $vpos = 1;

        $vpos = 100;
        $this->MaintainVariable('LastUpdate', $this->Translate('Last update'), VARIABLETYPE_INTEGER, '~UnixTimestamp', $vpos++, true);

        $module_disable = $this->ReadPropertyBoolean('module_disable');
        if ($module_disable) {
            $this->MaintainTimer('UpdateStatus', 0);
            $this->MaintainStatus(IS_INACTIVE);
            return;
        }

        $this->MaintainStatus(IS_ACTIVE);

        if (IPS_GetKernelRunlevel() == KR_READY) {
            $this->SetUpdateInterval();
        }
    }

    private function GetFormElements()
    {
        $formElements = $this->GetCommonFormElements('olarwatt Energymanager');

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            return $formElements;
        }

        $formElements[] = [
            'type'    => 'CheckBox',
            'name'    => 'module_disable',
            'caption' => 'Disable instance',
        ];

        $formElements[] = [
            'type'    => 'ValidationTextBox',
            'name'    => 'host',
            'caption' => 'Host',
        ];

        $formElements[] = [
            'type'    => 'PasswordTextBox',
            'name'    => 'password',
            'caption' => 'Energymanager password',
        ];

        $formElements[] = [
            'type'    => 'NumberSpinner',
            'name'    => 'update_interval',
            'suffix'  => 'Seconds',
            'minimum' => 0,
            'caption' => 'Update interval',
        ];

        return $formElements;
    }

    private function GetFormActions()
    {
        $formActions = [];

        if ($this->GetStatus() == self::$IS_UPDATEUNCOMPLETED) {
            $formActions[] = $this->GetCompleteUpdateFormAction();

            $formActions[] = $this->GetInformationFormAction();
            $formActions[] = $this->GetReferencesFormAction();

            return $formActions;
        }

        $formActions[] = [
            'type'    => 'Button',
            'caption' => 'Update status',
            'onClick' => 'IPS_RequestAction($id, "UpdateStatus", "");',
        ];

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Expert area',
            'expanded'  => false,
            'items'     => [
                $this->GetInstallVarProfilesFormItem(),
            ],
        ];

        $formActions[] = [
            'type'      => 'ExpansionPanel',
            'caption'   => 'Test area',
            'expanded'  => false,
            'items'     => [
                [
                    'type'    => 'TestCenter',
                ],
            ]
        ];

        $formActions[] = $this->GetInformationFormAction();
        $formActions[] = $this->GetReferencesFormAction();

        return $formActions;
    }

    private function SetUpdateInterval(int $sec = null)
    {
        if (is_null($sec)) {
            $sec = $this->ReadPropertyInteger('update_interval');
        }
        $this->MaintainTimer('UpdateStatus', $sec * 1000);
    }

    private function do_HttpRequest($cmd, $args, $postdata, $mode, &$result)
    {
        $host = $this->ReadPropertyString('host');

        $url = 'http://' . $host . $cmd;

        $header = [];

        $this->SendDebug(__FUNCTION__, 'http: url=' . $url . ', mode=' . $mode, 0);
        $this->SendDebug(__FUNCTION__, '  header=' . print_r($header, true), 0);
        if ($postdata != '') {
            $this->SendDebug(__FUNCTION__, '  postdata=' . $postdata, 0);
        }

        $time_start = microtime(true);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        switch ($mode) {
            case 'GET':
                break;
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $mode);
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $mode);
                break;
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $cdata = curl_exec($ch);
        $cerrno = curl_errno($ch);
        $cerror = $cerrno ? curl_error($ch) : '';
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $duration = round(microtime(true) - $time_start, 2);
        $this->SendDebug(__FUNCTION__, ' => errno=' . $cerrno . ', httpcode=' . $httpcode . ', duration=' . $duration . 's', 0);
        $this->SendDebug(__FUNCTION__, '    cdata=' . $cdata, 0);

        $statuscode = 0;

        $statuscode = 0;
        $err = '';
        $result = '';
        if ($cerrno) {
            $statuscode = self::$IS_SERVERERROR;
            $err = 'got curl-errno ' . $cerrno . ' (' . $cerror . ')';
        } elseif ($httpcode != 200) {
            if ($httpcode == 403) {
                $statuscode = self::$IS_FORBIDDEN;
                $err = 'got http-code ' . $httpcode . ' (forbidden)';
            } elseif ($httpcode >= 500 && $httpcode <= 599) {
                $statuscode = self::$IS_SERVERERROR;
                $err = 'got http-code ' . $httpcode . ' (server error)';
            } else {
                $statuscode = self::$IS_HTTPERROR;
                $err = "got http-code $httpcode";
            }
        } else {
            $result = @json_decode($cdata, true);
            if ($result == '') {
                $statuscode = self::$IS_INVALIDDATA;
                $err = 'malformed response';
            }
        }

        if ($statuscode) {
            $this->SendDebug(__FUNCTION__, ' => statuscode=' . $statuscode . ', err=' . $err, 0);
        }

        return $statuscode;
    }

    private function UpdateStatus()
    {
        if ($this->CheckStatus() == self::$STATUS_INVALID) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $data = '';
        $statuscode = $this->do_HttpRequest('/rest/kiwigrid/wizard/devices', '', '', 'GET', $data);

        if ($statuscode == 0) {
            $components = [];
            if (isset($data['result']['items'])) {
                $items = $data['result']['items'];
                foreach ($items as $item) {
                    $tagValues = $item['tagValues'];
                    if (isset($tagValues['IdName'])) {
                        $name = $tagValues['IdName']['value'];
                    } else {
                        $name = '';
                    }
                    $vars = [];
                    foreach ($tagValues as $tagValue) {
                        if ($tagValue['tagName'] == 'IdName') {
                            continue;
                        }
                        $vars[$tagValue['tagName']] = $tagValue['value'];
                    }
                    ksort($vars);
                    $guid = $item['guid'];
                    $models = $item['deviceModel'];
                    $class = count($item['deviceModel']) ? $item['deviceModel'][count($item['deviceModel']) - 1]['deviceClass'] : '';
                    $r = preg_split('/\./', $class);
                    $type = count($r) ? $r[count($r) - 1] : '';

                    $devices = [];
                    foreach ($components as $c) {
                        if ($c['class'] == $class) {
                            $devices = $component['devices'];
                            break;
                        }
                    }
                    $devices[] = [
                        'name'   => $name,
                        'guid'   => $guid,
                        'vars'   => $vars,
                    ];
                    $component = [
                        'type'    => $type,
                        'class'   => $class,
                        'devices' => $devices,
                    ];
                    $components[$type] = $component;
                }
            }
            ksort($components);
            $this->SendDebug(__FUNCTION__, 'components=' . print_r($components, true), 0);

            $mapping = $this->GetMapping();

            $idx = 0;
            $fnd = false;
            foreach ($mapping as $class => $map) {
                foreach ($map as $ent) {
                    $fld = $class . '.devices.' . $idx . '.vars.' . $ent['elem'];
                    $org = $this->GetArrayElem($components, $fld, '', $fnd);
                    $unit = '';
                    $fmt = '';
                    switch ($ent['type']) {
                        case VARIABLETYPE_BOOLEAN:
                            $val = boolval($org);
                            break;
                        case VARIABLETYPE_INTEGER:
                            switch ($ent['prof']) {
                                case '~UnixTimestamp':
                                    $val = (int) floor(intval($org) / 1000);
                                    $fmt = ' (' . date('d.m.Y H:i', $val) . ')';
                                    break;
                                default:
                                    $val = intval($org);
                                    break;
                            }
                            break;
                        case VARIABLETYPE_FLOAT:
                            switch ($ent['elem']) {
                                case 'StateOfCharge':
                                case 'StateOfHealth':
                                    $val = floatval($org) / 100;
                                    break;
                                default:
                                    $val = floatval($org);
                                    break;
                            }
                            switch ($ent['prof']) {
                                case 'kW':
                                case 'kWh':
                                    $val /= 1000;
                                    $fmt = ' (' . $this->format_float($val, 1) . ')';
                                    $unit = $ent['prof'];
                                    break;
                                case 'W':
                                case 'Wh':
                                    $fmt = ' (' . $this->format_float($val, 1) . ')';
                                    $unit = $ent['prof'];
                                    break;
                                case '%':
                                    $unit = $ent['prof'];
                                    $fmt = ' (' . $this->format_float($val * 100, 0) . ')';
                                    break;
                                case 'MB':
                                    $val = $val / 1024 / 1024;
                                    $unit = $ent['prof'];
                                    $fmt = ' (' . $this->format_float($val, 0) . ')';
                                    break;
                                case 'MB':
                                    $val = $val / 1024 / 1024 / 1024;
                                    $unit = $ent['prof'];
                                    $fmt = ' (' . $this->format_float($val, 2) . ')';
                                    break;
                                default:
                                    $fmt = ' (' . $this->format_float($val, 1) . ')';
                                    break;
                            }
                            break;
                        case VARIABLETYPE_STRING:
                            $val = $org;
                            break;
                    }
                    $this->SendDebug(__FUNCTION__, 'field=' . $fld . ': org="' . $org . '", val=' . $val . $fmt . ' ' . $unit, 0);
                }
            }
        }

        $this->SendDebug(__FUNCTION__, $this->PrintTimer('UpdateStatus'), 0);
    }

    private function LocalRequestAction($ident, $value)
    {
        $r = true;
        switch ($ident) {
            case 'UpdateStatus':
                $this->UpdateStatus();
                break;
            default:
                $r = false;
                break;
        }
        return $r;
    }

    public function RequestAction($ident, $value)
    {
        if ($this->LocalRequestAction($ident, $value)) {
            return;
        }
        if ($this->CommonRequestAction($ident, $value)) {
            return;
        }

        if ($this->GetStatus() == IS_INACTIVE) {
            $this->SendDebug(__FUNCTION__, $this->GetStatusText() . ' => skip', 0);
            return;
        }

        $this->SendDebug(__FUNCTION__, 'ident=' . $ident . ', value=' . $value, 0);

        $r = false;
        switch ($ident) {
            default:
                $this->SendDebug(__FUNCTION__, 'invalid ident ' . $ident, 0);
                break;
        }
        if ($r) {
            $this->SetValue($ident, $value);
        }
    }

    private function GetMapping()
    {
        $mapping = [
            'BatteryFlex' => [
                /*
                [
                    'elem'  => 'ACPower',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'kW',
                ],
                    [
                        'elem' => 'BatteryError',
                        'type'  => VARIABLETYPE_STRING,
                        'prof'  => '',
                    ],
                 */
                [
                    'name'  => 'Mode of the battery',
                    'ident' => 'Battery_ModeConverter',
                    'elem'  => 'ModeConverter',
                    'type'  => VARIABLETYPE_STRING,
                    'prof'  => '',
                ],
                [
                    'name'  => 'State of the battery',
                    'ident' => 'Battery_State',
                    'elem'  => 'StateDevice',
                    'type'  => VARIABLETYPE_STRING,
                    'prof'  => '',
                ],
                [
                    'name'  => 'State of charge of the battery',
                    'ident' => 'Battery_StateOfCharge',
                    'elem'  => 'StateOfCharge',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => '%',
                ],
                [
                    'name'  => 'State of health of the battery',
                    'ident' => 'Battery_StateOfHealth',
                    'elem'  => 'StateOfHealth',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => '%',
                ],
                /*
                    [
                        'elem' => 'SysErr',
                        'type'  => VARIABLETYPE_STRING,
                        'prof'  => '',
                    ],
                    [
                        'elem' => 'SysStat',
                        'type'  => VARIABLETYPE_STRING,
                        'prof'  => '',
                    ],
                 */
                [
                    'name'  => 'Temperature of the battery',
                    'ident' => 'Battery_Temperature',
                    'elem'  => 'TemperatureBattery',
                    'type'  => VARIABLETYPE_INTEGER,
                    'prof'  => '°C',
                ],
                [
                    'name'  => 'Uptime of the battery',
                    'ident' => 'Battery_Uptime',
                    'elem'  => 'UpTimePDG',
                    'type'  => VARIABLETYPE_STRING,
                    'prof'  => 'Duration',
                ],
                [
                    'name'  => 'Energy fed into the battery',
                    'ident' => 'Battery_WorkACIn',
                    'elem'  => 'WorkACIn',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'kWh',
                ],
                [
                    'name'  => 'Energy supplied from the battery',
                    'ident' => 'Battery_WorkACOut',
                    'elem'  => 'WorkACOut',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'kWh',
                ],
                [
                    'name'  => 'Energy capacity of the battery',
                    'ident' => 'Battery_EnergyCapacity',
                    'elem'  => 'WorkCapacity',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'kWh',
                ],
                [
                    'name'  => 'Energy capacity charged into the battery',
                    'ident' => 'Battery_EnergyCharged',
                    'elem'  => 'WorkCharged',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'kWh',
                ],
                [
                    'name'  => 'Energy discharged out of the battery',
                    'ident' => 'Battery_EnergyDischarged',
                    'elem'  => 'WorkDischarged',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'kWh',
                ],
            ],
            'BatteryFlexPowermeter' => [
                [
                    'name'  => 'State of the battery powermeter',
                    'ident' => 'BatteryPowermeter_State',
                    'elem'  => 'StateDevice',
                    'type'  => VARIABLETYPE_STRING,
                    'prof'  => '',
                ],
                [
                    'name'  => 'metering direction of the battery powermeter',
                    'ident' => 'BatteryPowermeter_DirectionMetering',
                    'elem'  => 'DirectionMetering',
                    'type'  => VARIABLETYPE_STRING,
                    'prof'  => '',
                ],
                [
                    'name'  => 'Energy fed into the battery',
                    'ident' => 'BatteryPowermeter_WorkIn',
                    'elem'  => 'WorkIn',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'Wh',
                ],
                [
                    'name'  => 'Energy supplied from the battery',
                    'ident' => 'BatteryPowermeter_WorkOut',
                    'elem'  => 'WorkOut',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'Wh',
                ],
            ],
            'EnergyManager' => [
                [
                    'name'  => 'Last contact of the energymanager to the cloud',
                    'ident' => 'Energymanager_LastContact',
                    'elem'  => 'DateCloudLastSeen',
                    'type'  => VARIABLETYPE_INTEGER,
                    'prof'  => '~UnixTimestamp',
                ],
                [
                    'name'  => 'Total load of the energymanager',
                    'ident' => 'Energymanager_LoadTotal',
                    'elem'  => 'FractionCPULoadTotal',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => '%',
                ],
                [
                    'name'  => 'System load of the energymanager',
                    'ident' => 'Energymanager_LoadSys',
                    'elem'  => 'FractionCPULoadKernel',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => '%',
                ],
                [
                    'name'  => 'User load of the energymanager',
                    'ident' => 'Energymanager_LoadUsr',
                    'elem'  => 'FractionCPULoadUser',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => '%',
                ],
                [
                    'name'  => 'Average load of last 1m of the energymanager',
                    'ident' => 'Energymanager_Load1m',
                    'elem'  => 'FractionCPULoadAverageLastMinute',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => '%',
                ],
                [
                    'name'  => 'Average load of last 5m of the energymanager',
                    'ident' => 'Energymanager_Load5m',
                    'elem'  => 'FractionCPULoadAverageLastFiveMinutes',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => '%',
                ],
                [
                    'name'  => 'Average load of last 15m of the energymanager',
                    'ident' => 'Energymanager_Load15m',
                    'elem'  => 'FractionCPULoadAverageLastFifteenMinutes',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => '%',
                ],
                [
                    'name'  => 'Total memory of the energymanager',
                    'ident' => 'Energymanager_MemTotal',
                    'elem'  => 'StatusMonitoringMap.memory_total',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'MB',
                ],
                [
                    'name'  => 'Avail memory of the energymanager',
                    'ident' => 'Energymanager_MemAvail',
                    'elem'  => 'StatusMonitoringMap.memory_available',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'MB',
                ],
                [
                    'name'  => 'Disk size of the energymanager',
                    'ident' => 'Energymanager_DiskSize',
                    'elem'  => 'StatusMonitoringMap.disk_size',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'GB',
                ],
                [
                    'name'  => 'Free disk size of the energymanager',
                    'ident' => 'Energymanager_DiskFree',
                    'elem'  => 'StatusMonitoringMap.disk_free',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'GB',
                ],
                [
                    'name'  => 'State of the energymanager',
                    'ident' => 'Energymanager_State',
                    'elem'  => 'StateDevice',
                    'type'  => VARIABLETYPE_STRING,
                    'prof'  => '',
                ],
                [
                    'name'  => 'Uptime of the energymanager',
                    'ident' => 'Energymanager_Uptime',
                    'elem'  => 'TimeSinceStart',
                    'type'  => VARIABLETYPE_STRING,
                    'prof'  => 'Duration',
                ],
            ],
            'Location' => [
                [
                    'name'  => 'Power stored in the battery',
                    'ident' => 'PowerToBattery',
                    'elem'  => 'PowerBuffered',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'kW',
                ],
                [
                    'name'  => 'Power from grid stored in the battery',
                    'ident' => 'PowerToBatteryFromGrid',
                    'elem'  => 'PowerBufferedFromGrid',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'kW',
                ],
                [
                    'name'  => 'Power from PV stored in the battery',
                    'ident' => 'PowerToBatteryFromPV',
                    'elem'  => 'PowerBufferedFromProducers',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'kW',
                ],
                [
                    'name'  => 'Total power consumed by all consumers',
                    'ident' => 'PowerConsumed',
                    'elem'  => 'PowerConsumed',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'kW',
                ],
                [
                    'name'  => 'Power consumed from the grid',
                    'ident' => 'PowerConsumedFromGrid',
                    'elem'  => 'PowerConsumedFromGrid',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'kW',
                ],
                [
                    'name'  => 'Power direct consumed from the PV',
                    'ident' => 'PowerConsumedFromPV',
                    'elem'  => 'PowerConsumedFromProducers',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'kW',
                ],
                [
                    'name'  => 'Power consumed from the battery',
                    'ident' => 'PowerConsumedFromBattery',
                    'elem'  => 'PowerConsumedFromStorage',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'kW',
                ],
                [
                    'name'  => 'Power fed into the grid',
                    'ident' => 'PowerFromGrid',
                    'elem'  => 'PowerIn',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'kW',
                ],
                [
                    'name'  => 'Power delivered to the grid',
                    'ident' => 'PowerToGrid',
                    'elem'  => 'PowerOut',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'kW',
                ],
                [
                    'name'  => 'Power delivered to the grid direct from the PV',
                    'ident' => 'PowerToGridFromPV',
                    'elem'  => 'PowerOutFromProducers',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'kW',
                ],
                [
                    'name'  => 'Power delivered to the grid direct from the battery',
                    'ident' => 'PowerToGridFromBattery',
                    'elem'  => 'PowerOutFromStorage',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'kW',
                ],
                [
                    'name'  => 'Power produced by the PV',
                    'ident' => 'PowerProduced',
                    'elem'  => 'PowerProduced',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'kW',
                ],
                /*
                    [
                        'elem' => 'PowerReleased',
                        'type'  => VARIABLETYPE_FLOAT,
                        'prof'  => 'kW',
                    ],
                 */
                [
                    'name'  => 'Power consumed direct from the PV plus energy stored',
                    'ident' => 'PowerSelfConsumed',
                    'elem'  => 'PowerSelfConsumed',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'kW',
                ],
                [
                    'name'  => 'Power consumed direct from the PV plus energy consumed from the battery',
                    'ident' => 'PowerSelfSupplied',
                    'elem'  => 'PowerSelfSupplied',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'kW',
                ],
                [
                    'name'  => 'State of location',
                    'ident' => 'Location_State',
                    'elem'  => 'StateDevice',
                    'type'  => VARIABLETYPE_STRING,
                    'prof'  => '',
                ],
                [
                    'name'  => 'Energy stored in the battery',
                    'ident' => 'EnergyToBattery',
                    'elem'  => 'WorkBuffered',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'kWh',
                ],
                [
                    'name'  => 'Energy from grid stored in the battery',
                    'ident' => 'EnergyToBatteryFromGrid',
                    'elem'  => 'WorkBufferedFromGrid',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'kWh',
                ],
                [
                    'name'  => 'Energy from PV stored in the battery',
                    'ident' => 'EnergyToBatteryFromPV',
                    'elem'  => 'WorkBufferedFromProducers',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'kWh',
                ],
                [
                    'name'  => 'Total energy consumed by all consumers',
                    'ident' => 'EnergyConsumed',
                    'elem'  => 'WorkConsumed',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'kWh',
                ],
                [
                    'name'  => 'Energy consumed from the grid',
                    'ident' => 'EnergyConsumedFromGrid',
                    'elem'  => 'WorkConsumedFromGrid',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'kWh',
                ],
                [
                    'name'  => 'Energy direct consumed from the PV',
                    'ident' => 'EnergyConsumedFromPV',
                    'elem'  => 'WorkConsumedFromProducers',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'kWh',
                ],
                [
                    'name'  => 'Energy consumed from the battery',
                    'ident' => 'EnergyConsumedFromBattery',
                    'elem'  => 'WorkConsumedFromStorage',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'kWh',
                ],
                [
                    'name'  => 'Energy fed into the grid',
                    'ident' => 'EnergyFromGrid',
                    'elem'  => 'WorkIn',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'kWh',
                ],
                [
                    'name'  => 'Energy delivered to the grid',
                    'ident' => 'EnergyToGrid',
                    'elem'  => 'WorkOut',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'kWh',
                ],
                [
                    'name'  => 'Energy delivered to the grid direct from the PV',
                    'ident' => 'EnergyToGridFromPV',
                    'elem'  => 'WorkOutFromProducers',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'kWh',
                ],
                [
                    'name'  => 'Energy delivered to the grid direct from the battery',
                    'ident' => 'EnergyToGridFromBattery',
                    'elem'  => 'WorkOutFromStorage',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'kWh',
                ],
                [
                    'name'  => 'Energy produced by the PV',
                    'ident' => 'EnergyProduced',
                    'elem'  => 'WorkProduced',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'kWh',
                ],
                /*
                    [
                        'elem' => 'WorkReleased',
                        'type'  => VARIABLETYPE_FLOAT,
                        'prof'  => 'kWh',
                    ],
                 */
                [
                    'name'  => 'Energy consumed direct from the PV plus energy stored',
                    'ident' => 'EnergySelfConsumed',
                    'elem'  => 'WorkSelfConsumed',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'kWh',
                ],
                [
                    'name'  => 'Energy consumed direct from the PV plus energy consumed from the battery',
                    'ident' => 'EnergySelfSupplied',
                    'elem'  => 'WorkSelfSupplied',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'kWh',
                ],
            ],
            'PVPlant' => [
                [
                    'name'  => 'Power produced by the PV',
                    'ident' => 'PV_PowerProduced',
                    'elem'  => 'PowerACOut',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'W',
                ],
                [
                    'name'  => 'State of the PV',
                    'ident' => 'PV_State',
                    'elem'  => 'StateDevice',
                    'type'  => VARIABLETYPE_STRING,
                    'prof'  => '',
                ],
                [
                    'name'  => 'Energy produced by the PV',
                    'ident' => 'PV_EnergyProduced',
                    'elem'  => 'WorkACOut',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'Wh',
                ],
            ],
            'S0Counter' => [
                /*
                    [
                        'elem' => 'InjectionEnergyNet',
                        'type'  => VARIABLETYPE_FLOAT,
                        'prof'  => 'kWh',
                    ],
                    [
                        'elem' => 'InjectionEnergySum',
                        'type'  => VARIABLETYPE_FLOAT,
                        'prof'  => 'kWh',
                    ],
                    [
                        'elem' => 'PowerIn',
                        'type'  => VARIABLETYPE_FLOAT,
                        'prof'  => 'kW',
                    ],
                    [
                        'elem' => 'PowerOut',
                        'type'  => VARIABLETYPE_FLOAT,
                        'prof'  => 'kW',
                    ],
                 */
                [
                    'name'  => 'State of the powermeter',
                    'ident' => 'S0Counter_State',
                    'elem'  => 'StateDevice',
                    'type'  => VARIABLETYPE_STRING,
                    'prof'  => '',
                ],
                /*
                    [
                        'elem' => 'WorkIn',
                        'type'  => VARIABLETYPE_FLOAT,
                        'prof'  => 'kWh',
                    ],
                    [
                        'elem' => 'WorkOut',
                        'type'  => VARIABLETYPE_FLOAT,
                        'prof'  => 'kWh',
                    ],
                 */
            ],
        ];

        return $mapping;
    }
}
