<?php

declare(strict_types=1);

trait SolarwattLocalLib
{
    public static $IS_FORBIDDEN = IS_EBASE + 10;
    public static $IS_SERVERERROR = IS_EBASE + 11;
    public static $IS_HTTPERROR = IS_EBASE + 12;
    public static $IS_INVALIDDATA = IS_EBASE + 13;

    private function GetFormStatus()
    {
        $formStatus = $this->GetCommonFormStatus();

        $formStatus[] = ['code' => self::$IS_FORBIDDEN, 'icon' => 'error', 'caption' => 'Instance is inactive (access forbidden)'];
        $formStatus[] = ['code' => self::$IS_SERVERERROR, 'icon' => 'error', 'caption' => 'Instance is inactive (server error)'];
        $formStatus[] = ['code' => self::$IS_HTTPERROR, 'icon' => 'error', 'caption' => 'Instance is inactive (http error)'];
        $formStatus[] = ['code' => self::$IS_INVALIDDATA, 'icon' => 'error', 'caption' => 'Instance is inactive (invalid data)'];

        return $formStatus;
    }

    public static $STATUS_INVALID = 0;
    public static $STATUS_VALID = 1;
    public static $STATUS_RETRYABLE = 2;

    private function CheckStatus()
    {
        switch ($this->GetStatus()) {
            case IS_ACTIVE:
                $class = self::$STATUS_VALID;
                break;
            case self::$IS_SERVERERROR:
            case self::$IS_HTTPERROR:
            case self::$IS_INVALIDDATA:
                $class = self::$STATUS_RETRYABLE;
                break;
            default:
                $class = self::$STATUS_INVALID;
                break;
        }

        return $class;
    }

    private function InstallVarProfiles(bool $reInstall = false)
    {
        if ($reInstall) {
            $this->SendDebug(__FUNCTION__, 'reInstall=' . $this->bool2str($reInstall), 0);
        }

        $this->CreateVarProfile('Solarwatt.Duration', VARIABLETYPE_INTEGER, ' s', 0, 0, 0, 0, 'Clock', [], $reInstall);

        $this->CreateVarProfile('Solarwatt.MB', VARIABLETYPE_FLOAT, ' MB', 0, 0, 0, 0, '', [], $reInstall);
        $this->CreateVarProfile('Solarwatt.GB', VARIABLETYPE_FLOAT, ' GB', 0, 0, 0, 0, '', [], $reInstall);
        $this->CreateVarProfile('Solarwatt.Load', VARIABLETYPE_FLOAT, '', 0, 0, 0, 2, '', [], $reInstall);
        $this->CreateVarProfile('Solarwatt.Percent', VARIABLETYPE_FLOAT, ' %', 0, 100, 0, 0, '', [], $reInstall);
        $this->CreateVarProfile('Solarwatt.Temperature', VARIABLETYPE_FLOAT, ' °C', 0, 0, 0, 0, '', [], $reInstall);
        $this->CreateVarProfile('Solarwatt.Wh', VARIABLETYPE_FLOAT, ' Wh', 0, 0, 0, 0, '', [], $reInstall);
        $this->CreateVarProfile('Solarwatt.kWh', VARIABLETYPE_FLOAT, ' kWh', 0, 0, 0, 1, '', [], $reInstall);
        $this->CreateVarProfile('Solarwatt.W', VARIABLETYPE_FLOAT, ' W', 0, 0, 0, 0, '', [], $reInstall);
        $this->CreateVarProfile('Solarwatt.kW', VARIABLETYPE_FLOAT, ' kW', 0, 0, 0, 1, '', [], $reInstall);

        $associations = [
            ['Wert' => 'IN', 'Name' => $this->Translate('Purchase'), 'Farbe' => -1],
            ['Wert' => 'OUT', 'Name' => $this->Translate('Feed-in'), 'Farbe' => -1],
            ['Wert' => 'BIDIRECTIONAL', 'Name' => $this->Translate('In both directions'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('Solarwatt.PowermeterDirection', VARIABLETYPE_STRING, '', 0, 0, 0, 0, '', $associations, $reInstall);

        $associations = [
            ['Wert' => 'OFF', 'Name' => $this->Translate('Off'), 'Farbe' => -1],
            ['Wert' => 'CHARGING', 'Name' => $this->Translate('Charging'), 'Farbe' => -1],
            ['Wert' => 'DISCHARGING', 'Name' => $this->Translate('Discharging'), 'Farbe' => -1],
        ];
        $this->CreateVarProfile('Solarwatt.BatteryConverterMode', VARIABLETYPE_STRING, '', 0, 0, 0, 0, '', $associations, $reInstall);
    }
}
