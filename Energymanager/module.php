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

        $this->RegisterPropertyString('use_fields', json_encode([]));

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
        $use_fields = json_decode($this->ReadPropertyString('use_fields'), true);
        $classes = $this->GetClasses();
        $mapping = $this->GetMapping();
        foreach ($classes as $class) {
            foreach ($mapping[$class] as $ent) {
                $ident = $this->GetArrayElem($ent, 'ident', '');
                $desc = $this->GetArrayElem($ent, 'desc', '');
                $vartype = $this->GetArrayElem($ent, 'type', '');
                $varprof = $this->GetArrayElem($ent, 'prof', '');
                $alias = $this->GetArrayElem($ent, 'alias', '');

                $use = (bool) $this->GetArrayElem($ent, 'mandatory', false);
                if ($use == false) {
                    foreach ($use_fields as $field) {
                        if ($ident == $this->GetArrayElem($field, 'ident', '')) {
                            $use = (bool) $this->GetArrayElem($field, 'use', false);
                            break;
                        }
                    }
                }

                $name = $this->Translate($desc);
                if ($alias != '') {
                    $name = $this->Translate($alias) . ' - ' . $name;
                }

                $this->SendDebug(__FUNCTION__, 'register variable: ident=' . $ident . ', vartype=' . $vartype . ', varprof=' . $varprof . ', use=' . $this->bool2str($use), 0);
                $this->MaintainVariable($ident, $name, $vartype, $varprof, $vpos++, $use);
            }
        }

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
        $formElements = $this->GetCommonFormElements('Solarwatt Energymanager');

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

        $values = [];
        $use_fields = json_decode($this->ReadPropertyString('use_fields'), true);
        $classes = $this->GetClasses();
        $mapping = $this->GetMapping();
        foreach ($classes as $class) {
            foreach ($mapping[$class] as $ent) {
                $elem = $this->GetArrayElem($ent, 'elem', '');
                $fld = $class . '/' . $elem;
                $ident = $this->GetArrayElem($ent, 'ident', '');
                $desc = $this->GetArrayElem($ent, 'desc', '');
                $alias = $this->GetArrayElem($ent, 'alias', '');
                $mandatory = (bool) $this->GetArrayElem($ent, 'mandatory', false);
                if ($mandatory) {
                    continue;
                }

                $use = false;
                foreach ($use_fields as $field) {
                    if ($ident == $this->GetArrayElem($field, 'ident', '')) {
                        $use = (bool) $this->GetArrayElem($field, 'use', false);
                        break;
                    }
                }

                $name = $this->Translate($desc);
                if ($alias != '') {
                    $name = $this->Translate($alias) . ' - ' . $name;
                }

                @$varID = $this->GetIDForIdent($ident);
                $varID = $varID !== false ? '#' . $varID : '';

                $values[] = [
                    'ident' => $ident,
                    'desc'  => $name,
                    'fld'   => $fld,
                    'use'   => $use,
                    'varID' => $varID,
                ];
            }
        }
        $this->SendDebug(__FUNCTION__, 'values=' . print_r($values, true), 0);

        $formElements[] = [
            'type'     => 'ExpansionPanel',
            'items'    => [
                [
                    'type'     => 'List',
                    'name'     => 'use_fields',
                    'caption'  => 'Available variables',
                    'add'      => false,
                    'delete'   => false,
                    'columns'  => [
                        [
                            'caption' => 'Ident',
                            'name'    => 'ident',
                            'width'   => '300px',
                            'save'    => true
                        ],
                        [
                            'caption' => 'Description',
                            'name'    => 'desc',
                            'width'   => 'auto',
                            'save'    => false,
                        ],
                        [
                            'caption' => 'Field',
                            'name'    => 'fld',
                            'width'   => '300px',
                            'save'    => false,
                        ],
                        [
                            'caption' => 'use',
                            'name'    => 'use',
                            'width'   => '100px',
                            'edit'    => [
                                'type' => 'CheckBox'
                            ],
                        ],
                        [
                            'caption' => 'ID',
                            'name'    => 'varID',
                            'width'   => '100px',
                            'save'    => false,
                        ],
                    ],
                    'values'                      => $values,
                    'rowCount'                    => count($values),
                    'loadValuesFromConfiguration' => false,
                ],
            ],
            'caption'  => 'Additional variables',
            'expanded' => false,
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

        $now = time();

        $data = '';
        $statuscode = $this->do_HttpRequest('/rest/kiwigrid/wizard/devices', '', '', 'GET', $data);
        if ($statuscode == 0) {
            $use_idents = [];

            $use_fields = json_decode($this->ReadPropertyString('use_fields'), true);
            $classes = $this->GetClasses();
            $mapping = $this->GetMapping();
            foreach ($classes as $class) {
                foreach ($mapping[$class] as $ent) {
                    $ident = $this->GetArrayElem($ent, 'ident', '');
                    $use = (bool) $this->GetArrayElem($ent, 'mandatory', false);
                    if ($use == false) {
                        foreach ($use_fields as $field) {
                            if ($ident == $this->GetArrayElem($field, 'ident', '')) {
                                $use = (bool) $this->GetArrayElem($field, 'use', false);
                                break;
                            }
                        }
                    }
                    if ($use && $ident != '') {
                        $use_idents[] = $ident;
                    }
                }
            }

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

            $idx = 0;
            $fnd = false;
            $classes = $this->GetClasses();
            $mapping = $this->GetMapping();
            foreach ($classes as $class) {
                foreach ($mapping[$class] as $ent) {
                    $ident = $this->GetArrayElem($ent, 'ident', '');
                    if (in_array($ident, $use_idents) == false) {
                        continue;
                    }
                    $desc = $this->GetArrayElem($ent, 'desc', '');
                    $vartype = $this->GetArrayElem($ent, 'type', '');
                    $varprof = $this->GetArrayElem($ent, 'prof', '');
                    $factor = (float) $this->GetArrayElem($ent, 'factor', 1);
                    $elem = $this->GetArrayElem($ent, 'elem', '');
                    $fld = $class . '.devices.' . $idx . '.vars.' . $elem;
                    $raw = $this->GetArrayElem($components, $fld, '', $fnd);
                    if ($fnd == false) {
                        $this->SendDebug(__FUNCTION__, 'set ' . $fld . ' is missing -> ignored', 0);
                        continue;
                    }

                    switch ($vartype) {
                        case VARIABLETYPE_BOOLEAN:
                            $val = boolval($raw);
                            break;
                        case VARIABLETYPE_INTEGER:
                            $val = (int) (intval($raw) * $factor);
                            break;
                        case VARIABLETYPE_FLOAT:
                            $val = floatval($raw) * $factor;
                            break;
                        case VARIABLETYPE_STRING:
                            $val = $raw;
                            if ($varprof != '' && $this->CheckVarProfile4Value($varprof, $val) == false) {
                                $this->LogMessage(__FUNCTION__ . ': unknown value "' . $raw . '" for variable "' . $ident . '"', KL_WARNING);
                            }
                            if ($ident == 'Energymanager_Uptime_Pretty') {
                                $val = $this->FormatDuration((int) (intval($raw) * $factor));
                            }
                            break;
                    }

                    $this->SetValue($ident, $val);

                    $fmt = $this->GetValueFormatted($ident);
                    $this->SendDebug(__FUNCTION__, 'set ' . $fld . '="' . $raw . '" to ' . $ident . ' => ' . $fmt, 0);
                }
            }

            $powerAll = [];
            $energyAll = [];
            foreach ($classes as $class) {
                $powerList = [];
                $energyList = [];
                foreach ($mapping[$class] as $ent) {
                    $elem = $this->GetArrayElem($ent, 'elem', '');
                    $ident = $this->GetArrayElem($ent, 'ident', '');
                    $fld = $class . '.devices.' . $idx . '.vars.' . $elem;
                    $raw = $this->GetArrayElem($components, $fld, '', $fnd);
                    if ($fnd == false) {
                        continue;
                    }
                    if (preg_match('/^Power/', $elem)) {
                        $powerList[$elem] = $this->format_float(floatval($raw), 2) . ' W / ' . $this->format_float(floatval($raw) / 1000, 1) . ' kW';
                    }
                    if (preg_match('/^Work/', $elem)) {
                        $energyList[$elem] = $this->format_float(floatval($raw), 2) . ' Wh / ' . $this->format_float(floatval($raw) / 1000, 1) . ' kWh';
                    }
                }
                if ($powerList != []) {
                    $powerAll[$class] = $powerList;
                }
                if ($energyList != []) {
                    $energyAll[$class] = $energyList;
                }
            }
            $this->SendDebug(__FUNCTION__, 'power=' . print_r($powerAll, true), 0);
            $this->SendDebug(__FUNCTION__, 'energy=' . print_r($energyAll, true), 0);

            $this->SetValue('LastUpdate', $now);
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
            'Location' => [
                [
                    'desc'      => 'State of location',
                    'ident'     => 'Location_State',
                    'elem'      => 'StateDevice',
                    'type'      => VARIABLETYPE_STRING,
                    'mandatory' => true,
                ],
                [
                    'desc'  => 'Power stored in the storage',
                    'alias' => 'Stored',
                    'ident' => 'PowerToStorage',
                    'elem'  => 'PowerBuffered',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'Solarwatt.W',
                ],
                [
                    'desc'  => 'Power from grid stored in the storage',
                    'ident' => 'PowerToStorageFromGrid',
                    'elem'  => 'PowerBufferedFromGrid',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'Solarwatt.W',
                ],
                [
                    'desc'  => 'Power from PV stored in the storage',
                    'ident' => 'PowerToStorageFromPV',
                    'elem'  => 'PowerBufferedFromProducers',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'Solarwatt.W',
                ],
                [
                    'desc'      => 'Total power consumed',
                    'alias'     => 'Consumption',
                    'ident'     => 'PowerConsumed',
                    'elem'      => 'PowerConsumed',
                    'type'      => VARIABLETYPE_FLOAT,
                    'prof'      => 'Solarwatt.W',
                    'mandatory' => true,
                ],
                [
                    'desc'  => 'Power consumed from the grid',
                    'ident' => 'PowerConsumedFromGrid',
                    'elem'  => 'PowerConsumedFromGrid',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'Solarwatt.W',
                ],
                [
                    'desc'      => 'Power consumed from the PV',
                    'alias'     => 'Direct consumption',
                    'ident'     => 'PowerConsumedFromPV',
                    'elem'      => 'PowerConsumedFromProducers',
                    'type'      => VARIABLETYPE_FLOAT,
                    'prof'      => 'Solarwatt.W',
                    'mandatory' => true,
                ],
                [
                    'desc'  => 'Power consumed from the storage',
                    'alias' => 'Used',
                    'ident' => 'PowerConsumedFromStorage',
                    'elem'  => 'PowerConsumedFromStorage',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'Solarwatt.W',
                ],
                [
                    'desc'      => 'Power fed from the grid',
                    'alias'     => 'Purchased',
                    'ident'     => 'PowerFromGrid',
                    'elem'      => 'PowerIn',
                    'type'      => VARIABLETYPE_FLOAT,
                    'prof'      => 'Solarwatt.W',
                    'mandatory' => true,
                ],
                [
                    'desc'      => 'Power delivered to the grid',
                    'alias'     => 'Feed-in',
                    'ident'     => 'PowerToGrid',
                    'elem'      => 'PowerOut',
                    'type'      => VARIABLETYPE_FLOAT,
                    'prof'      => 'Solarwatt.W',
                    'mandatory' => true,
                ],
                [
                    'desc'  => 'Power delivered to the grid direct from the PV',
                    'ident' => 'PowerToGridFromPV',
                    'elem'  => 'PowerOutFromProducers',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'Solarwatt.W',
                ],
                [
                    'desc'  => 'Power delivered to the grid direct from the storage',
                    'ident' => 'PowerToGridFromStorage',
                    'elem'  => 'PowerOutFromStorage',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'Solarwatt.W',
                ],
                [
                    'desc'      => 'Total power produced',
                    'alias'     => 'Production',
                    'ident'     => 'PowerProduced',
                    'elem'      => 'PowerProduced',
                    'type'      => VARIABLETYPE_FLOAT,
                    'prof'      => 'Solarwatt.W',
                    'mandatory' => true,
                ],
                [
                    'desc'   => 'Power consumed direct from the PV plus energy stored',
                    'ident'  => 'PowerSelfConsumed',
                    'elem'   => 'PowerSelfConsumed',
                    'type'   => VARIABLETYPE_FLOAT,
                    'prof'   => 'Solarwatt.kW',
                    'factor' => 1 / 1000,
                ],
                [
                    'desc'   => 'Power consumed direct from the PV and from the storage',
                    'ident'  => 'PowerSelfSupplied',
                    'elem'   => 'PowerSelfSupplied',
                    'type'   => VARIABLETYPE_FLOAT,
                    'prof'   => 'Solarwatt.kW',
                    'factor' => 1 / 1000,
                ],
                [
                    'desc'  => 'Energy stored in the storage',
                    'alias' => 'Stored',
                    'ident' => 'EnergyToStorage',
                    'elem'  => 'WorkBuffered',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'Solarwatt.Wh',
                ],
                [
                    'desc'  => 'Energy from grid stored in the storage',
                    'ident' => 'EnergyToStorageFromGrid',
                    'elem'  => 'WorkBufferedFromGrid',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'Solarwatt.Wh',
                ],
                [
                    'desc'  => 'Energy from PV stored in the storage',
                    'ident' => 'EnergyToStorageFromPV',
                    'elem'  => 'WorkBufferedFromProducers',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'Solarwatt.Wh',
                ],
                [
                    'desc'      => 'Total energy consumed',
                    'alias'     => 'Consumption',
                    'ident'     => 'EnergyConsumed',
                    'elem'      => 'WorkConsumed',
                    'type'      => VARIABLETYPE_FLOAT,
                    'prof'      => 'Solarwatt.kWh',
                    'factor'    => 1 / 1000,
                    'mandatory' => true,
                ],
                [
                    'desc'   => 'Energy consumed from the grid',
                    'ident'  => 'EnergyConsumedFromGrid',
                    'elem'   => 'WorkConsumedFromGrid',
                    'type'   => VARIABLETYPE_FLOAT,
                    'prof'   => 'Solarwatt.kWh',
                    'factor' => 1 / 1000,
                ],
                [
                    'desc'      => 'Energy consumed from the PV',
                    'alias'     => 'Direct consumption',
                    'ident'     => 'EnergyConsumedFromPV',
                    'elem'      => 'WorkConsumedFromProducers',
                    'type'      => VARIABLETYPE_FLOAT,
                    'prof'      => 'Solarwatt.kWh',
                    'factor'    => 1 / 1000,
                    'mandatory' => true,
                ],
                [
                    'desc'   => 'Energy consumed from the storage',
                    'alias'  => 'Used',
                    'ident'  => 'EnergyConsumedFromStorage',
                    'elem'   => 'WorkConsumedFromStorage',
                    'type'   => VARIABLETYPE_FLOAT,
                    'prof'   => 'Solarwatt.kWh',
                    'factor' => 1 / 1000,
                ],
                [
                    'desc'      => 'Energy fed from the grid',
                    'alias'     => 'Purchased',
                    'ident'     => 'EnergyFromGrid',
                    'elem'      => 'WorkIn',
                    'type'      => VARIABLETYPE_FLOAT,
                    'prof'      => 'Solarwatt.kWh',
                    'factor'    => 1 / 1000,
                    'mandatory' => true,
                ],
                [
                    'desc'      => 'Energy delivered to the grid',
                    'alias'     => 'Feed-in',
                    'ident'     => 'EnergyToGrid',
                    'elem'      => 'WorkOut',
                    'type'      => VARIABLETYPE_FLOAT,
                    'prof'      => 'Solarwatt.kWh',
                    'factor'    => 1 / 1000,
                    'mandatory' => true,
                ],
                [
                    'desc'   => 'Energy delivered to the grid direct from the PV',
                    'ident'  => 'EnergyToGridFromPV',
                    'elem'   => 'WorkOutFromProducers',
                    'type'   => VARIABLETYPE_FLOAT,
                    'prof'   => 'Solarwatt.kWh',
                    'factor' => 1 / 1000,
                ],
                [
                    'desc'   => 'Energy delivered to the grid direct from the storage',
                    'ident'  => 'EnergyToGridFromStorage',
                    'elem'   => 'WorkOutFromStorage',
                    'type'   => VARIABLETYPE_FLOAT,
                    'prof'   => 'Solarwatt.kWh',
                    'factor' => 1 / 1000,
                ],
                [
                    'desc'      => 'Total energy produced',
                    'alias'     => 'Production',
                    'ident'     => 'EnergyProduced',
                    'elem'      => 'WorkProduced',
                    'type'      => VARIABLETYPE_FLOAT,
                    'prof'      => 'Solarwatt.kWh',
                    'factor'    => 1 / 1000,
                    'mandatory' => true,
                ],
                [
                    'desc'   => 'Energy consumed direct from the PV plus energy stored',
                    'ident'  => 'EnergySelfConsumed',
                    'elem'   => 'WorkSelfConsumed',
                    'type'   => VARIABLETYPE_FLOAT,
                    'prof'   => 'Solarwatt.kWh',
                    'factor' => 1 / 1000,
                ],
                [
                    'desc'   => 'Energy consumed direct from the PV and from the storage',
                    'ident'  => 'EnergySelfSupplied',
                    'elem'   => 'WorkSelfSupplied',
                    'type'   => VARIABLETYPE_FLOAT,
                    'prof'   => 'Solarwatt.kWh',
                    'factor' => 1 / 1000,
                ],
            ],
            'EnergyManager' => [
                [
                    'desc'      => 'State of the energymanager',
                    'ident'     => 'Energymanager_State',
                    'elem'      => 'StateDevice',
                    'type'      => VARIABLETYPE_STRING,
                    'mandatory' => true,
                ],
                [
                    'desc'   => 'Uptime of the energymanager',
                    'ident'  => 'Energymanager_Uptime',
                    'elem'   => 'TimeSinceStart',
                    'type'   => VARIABLETYPE_INTEGER,
                    'prof'   => 'Solarwatt.Duration',
                    'factor' => 1 / 1000,
                ],
                [
                    'desc'   => 'Uptime of the energymanager',
                    'ident'  => 'Energymanager_Uptime_Pretty',
                    'elem'   => 'TimeSinceStart',
                    'type'   => VARIABLETYPE_STRING,
                    'factor' => 1 / 1000,
                ],
                [
                    'desc'   => 'Last contact of the energymanager to the cloud',
                    'ident'  => 'Energymanager_LastContact',
                    'elem'   => 'DateCloudLastSeen',
                    'type'   => VARIABLETYPE_INTEGER,
                    'prof'   => '~UnixTimestamp',
                    'factor' => 1 / 1000,
                ],
                [
                    'desc'  => 'Total load on the energymanager',
                    'ident' => 'Energymanager_LoadTotal',
                    'elem'  => 'FractionCPULoadTotal',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'Solarwatt.Load',
                ],
                [
                    'desc'  => 'System load on the energymanager',
                    'ident' => 'Energymanager_LoadSys',
                    'elem'  => 'FractionCPULoadKernel',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'Solarwatt.Load',
                ],
                [
                    'desc'  => 'User load on the energymanager',
                    'ident' => 'Energymanager_LoadUsr',
                    'elem'  => 'FractionCPULoadUser',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'Solarwatt.Load',
                ],
                [
                    'desc'  => 'Load of last 1m on the energymanager',
                    'ident' => 'Energymanager_Load1m',
                    'elem'  => 'FractionCPULoadAverageLastMinute',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'Solarwatt.Load',
                ],
                [
                    'desc'  => 'Load of last 5m on the energymanager',
                    'ident' => 'Energymanager_Load5m',
                    'elem'  => 'FractionCPULoadAverageLastFiveMinutes',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'Solarwatt.Load',
                ],
                [
                    'desc'  => 'Load of last 15m on the energymanager',
                    'ident' => 'Energymanager_Load15m',
                    'elem'  => 'FractionCPULoadAverageLastFifteenMinutes',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'Solarwatt.Load',
                ],
                [
                    'desc'   => 'Total memory of the energymanager',
                    'ident'  => 'Energymanager_MemTotal',
                    'elem'   => 'StatusMonitoringMap.memory_total',
                    'type'   => VARIABLETYPE_FLOAT,
                    'prof'   => 'Solarwatt.MB',
                    'factor' => 1 / (1024 * 1024),
                ],
                [
                    'desc'   => 'Avail memory of the energymanager',
                    'ident'  => 'Energymanager_MemAvail',
                    'elem'   => 'StatusMonitoringMap.memory_available',
                    'type'   => VARIABLETYPE_FLOAT,
                    'prof'   => 'Solarwatt.MB',
                    'factor' => 1 / (1024 * 1024),
                ],
                [
                    'desc'   => 'Disk size of the energymanager',
                    'ident'  => 'Energymanager_DiskSize',
                    'elem'   => 'StatusMonitoringMap.disk_size',
                    'type'   => VARIABLETYPE_FLOAT,
                    'prof'   => 'Solarwatt.MB',
                    'factor' => 1 / (1024 * 1024),
                ],
                [
                    'desc'   => 'Available disk space of the energymanager',
                    'ident'  => 'Energymanager_DiskFree',
                    'elem'   => 'StatusMonitoringMap.disk_free',
                    'type'   => VARIABLETYPE_FLOAT,
                    'prof'   => 'Solarwatt.MB',
                    'factor' => 1 / (1024 * 1024),
                ],
            ],
            'S0Counter' => [
                [
                    'desc'  => 'State of the powermeter',
                    'ident' => 'S0Counter_State',
                    'elem'  => 'StateDevice',
                    'type'  => VARIABLETYPE_STRING,
                ],
            ],
            'PVPlant' => [
                [
                    'desc'  => 'State of the PV',
                    'ident' => 'PV_State',
                    'elem'  => 'StateDevice',
                    'type'  => VARIABLETYPE_STRING,
                ],
                [
                    'desc'  => 'Power produced by the PV',
                    'ident' => 'PV_PowerProduced',
                    'elem'  => 'PowerACOut',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'Solarwatt.W',
                ],
                [
                    'desc'  => 'Energy produced by the PV',
                    'ident' => 'PV_EnergyProduced',
                    'elem'  => 'WorkACOut',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'Solarwatt.Wh',
                ],
            ],
            'BatteryFlex' => [
                [
                    'desc'  => 'Operation mode of the battery',
                    'ident' => 'Battery_ModeConverter',
                    'elem'  => 'ModeConverter',
                    'type'  => VARIABLETYPE_STRING,
                    'prof'  => 'Solarwatt.BatteryConverterMode',
                ],
                [
                    'desc'  => 'State of the battery',
                    'ident' => 'Battery_State',
                    'elem'  => 'StateDevice',
                    'type'  => VARIABLETYPE_STRING,
                ],
                [
                    'desc'  => 'State of charge of the battery',
                    'ident' => 'Battery_StateOfCharge',
                    'elem'  => 'StateOfCharge',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'Solarwatt.Percent',
                ],
                [
                    'desc'  => 'State of health of the battery',
                    'ident' => 'Battery_StateOfHealth',
                    'elem'  => 'StateOfHealth',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'Solarwatt.Percent',
                ],
                [
                    'desc'  => 'Temperature of the battery',
                    'ident' => 'Battery_Temperature',
                    'elem'  => 'TemperatureBattery',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'Solarwatt.Temperature',
                ],
                [
                    'desc'  => 'Minimum temperature of the battery',
                    'ident' => 'Battery_TemperatureMin',
                    'elem'  => 'TemperatureBatteryMin',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'Solarwatt.Temperature',
                ],
                [
                    'desc'  => 'Maximum temperature of the battery',
                    'ident' => 'Battery_TemperatureMax',
                    'elem'  => 'TemperatureBatteryMax',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'Solarwatt.Temperature',
                ],
                [
                    'desc'  => 'Mean temperature of the battery',
                    'ident' => 'Battery_TemperatureMean',
                    'elem'  => 'TemperatureBatteryMean',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'Solarwatt.Temperature',
                ],
                [
                    'desc'   => 'Energy fed into the battery',
                    'ident'  => 'Battery_WorkACIn',
                    'elem'   => 'WorkACIn',
                    'type'   => VARIABLETYPE_FLOAT,
                    'prof'   => 'Solarwatt.kWh',
                    'factor' => 1 / 1000,
                ],
                [
                    'desc'   => 'Energy supplied from the battery',
                    'ident'  => 'Battery_WorkACOut',
                    'elem'   => 'WorkACOut',
                    'type'   => VARIABLETYPE_FLOAT,
                    'prof'   => 'Solarwatt.kWh',
                    'factor' => 1 / 1000,
                ],
                [
                    'desc'   => 'Energy capacity of the battery',
                    'ident'  => 'Battery_EnergyCapacity',
                    'elem'   => 'WorkCapacity',
                    'type'   => VARIABLETYPE_FLOAT,
                    'prof'   => 'Solarwatt.kWh',
                    'factor' => 1 / 1000,
                ],
                [
                    'desc'   => 'Energy capacity charged into the battery',
                    'ident'  => 'Battery_EnergyCharged',
                    'elem'   => 'WorkCharged',
                    'type'   => VARIABLETYPE_FLOAT,
                    'prof'   => 'Solarwatt.Wh',
                ],
                [
                    'desc'   => 'Energy discharged out of the battery',
                    'ident'  => 'Battery_EnergyDischarged',
                    'elem'   => 'WorkDischarged',
                    'type'   => VARIABLETYPE_FLOAT,
                    'prof'   => 'Solarwatt.Wh',
                ],
            ],
            'BatteryFlexPowermeter' => [
                [
                    'desc'  => 'State of the battery powermeter',
                    'ident' => 'BatteryPowermeter_State',
                    'elem'  => 'StateDevice',
                    'type'  => VARIABLETYPE_STRING,
                ],
                [
                    'desc'  => 'Metering direction of the battery powermeter',
                    'ident' => 'BatteryPowermeter_DirectionMetering',
                    'elem'  => 'DirectionMetering',
                    'type'  => VARIABLETYPE_STRING,
                    'prof'  => 'Solarwatt.PowermeterDirection',
                ],
                [
                    'desc'  => 'Energy fed into the battery',
                    'ident' => 'BatteryPowermeter_WorkIn',
                    'elem'  => 'WorkIn',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'Solarwatt.Wh',
                ],
                [
                    'desc'  => 'Energy supplied from the battery',
                    'ident' => 'BatteryPowermeter_WorkOut',
                    'elem'  => 'WorkOut',
                    'type'  => VARIABLETYPE_FLOAT,
                    'prof'  => 'Solarwatt.Wh',
                ],
            ],
        ];

        return $mapping;
    }

    private function GetClasses()
    {
        $classes = [
            'Location',
            'EnergyManager',
            'S0Counter',
            'PVPlant',
            'BatteryFlex',
            'BatteryFlexPowermeter',
        ];

        return $classes;
    }

    private function FormatDuration(int $sec)
    {
        $ret = '';

        if ($sec > 86400) {
            $day = floor($sec / 86400);
            $sec = $sec % 86400;
            $ret .= sprintf('%dd', $day);
        }
        if ($sec > 3600) {
            $hour = floor($sec / 3600);
            $sec = $sec % 3600;
            $ret .= sprintf('%dh', $hour);
        }
        if ($sec > 60) {
            $min = floor($sec / 60);
            $sec = $sec % 60;
            $ret .= sprintf('%dm', $min);
        }
        if ($sec > 0) {
            $ret .= sprintf('%ds', $sec);
        }

        return $ret;
    }
}
/*
[PowerStringDCIn] =>
[PowerYieldSum] =>
[ResistanceBatteryMax] => 0.002
[ResistanceBatteryMean] => 0.002
[ResistanceBatteryMin] => 0.002
[ResistanceBatteryString] =>
[TemperatureBatteryCellMax] => 22
[TemperatureBatteryCellMin] => 19
 */
