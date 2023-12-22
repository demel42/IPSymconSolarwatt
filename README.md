[![IPS-Version](https://img.shields.io/badge/Symcon_Version-6.0+-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
![Code](https://img.shields.io/badge/Code-PHP-blue.svg)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)

## Dokumentation

**Inhaltsverzeichnis**

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Installation](#3-installation)
4. [Funktionsreferenz](#4-funktionsreferenz)
5. [Konfiguration](#5-konfiguration)
6. [Anhang](#6-anhang)
7. [Versions-Historie](#7-versions-historie)

## 1. Funktionsumfang

Auslesen der Daten eines Solarwatt Energymanager 

## 2. Voraussetzungen

- IP-Symcon ab Version 6.0

## 3. Installation

### a. Installation des Moduls

Im [Module Store](https://www.symcon.de/service/dokumentation/komponenten/verwaltungskonsole/module-store/) ist das Modul unter dem Suchbegriff *Solarwatt Energymanager* zu finden.<br>
Alternativ kann das Modul über [Module Control](https://www.symcon.de/service/dokumentation/modulreferenz/module-control/) unter Angabe der URL `https://github.com/demel42/Solarwatt.git` installiert werden.

### b. Einrichtung in IPS

Instanz *Solarwatt Energymanager* anlegen und parametrieren.

Das Passwort ist nur für die (noch nicht implementierte) Funktion des Energymanager-Reboots erforderlich.

Die Variablen für *Leistung* sollten sinnvollerweise auf Logging gestellt werden, die für *Energie* sind dann als *Zählervariablen* nutzbar.

Der Umfang der Variablen hängt von der verwendeten Hardware bzw eingebundenen Geräten ab, ich habe eine Konfiguration von PV-Modulen (_PVPlant_) mit Speicher (_BatteryFlex + _BatteryFlex ACS__); dazu gehört auch immer der _S0Counter_ (der die netzseitige Energiemessung durchführt). Der _Energymanager_ führt die Daten zusammen (auch die wesentlichen Daten des Wechselrichters) und legt die unter dem Begriff _Location_ (aka _Standort_) ab.
Die Menge an Variablen ist teilweise nicht so einfach zuzuordnen und in der Nomenklatur auch nicht immer eindeutig - die wichtisgten Variablen ­ die auch im Portal verwendet werden - sind eigens im Variablennamen gekennzeichnet.

Der Abruf erfolgt rein lokal.

## 4. Funktionsreferenz

`Solarwatt_UpdateStatus(int $InstanzID)`<br>
Abruf aller Daten vom Energymanager

## 5. Konfiguration

### Energymanager Device

#### Properties

| Eigenschaft               | Typ      | Standardwert | Beschreibung |
| :------------------------ | :------  | :----------- | :----------- |
| Instanz deaktivieren      | boolean  | false        | Instanz temporär deaktivieren |
|                           |          |              | |
| Host                      | string   |              | Hostname / IP-Adresse des Energymanagers im lokalen Netz |
| Password                  | string   |              | Passwort des Energymanagers, steht auf der Oberseite des Moduls |
|                           |          |              | |
| zusätzliche Variablen     | list     |              | Liste von optionalen Variablen |

#### Aktionen

| Bezeichnung                | Beschreibung |
| :------------------------- | :----------- |
| Status aktualisieren       |              |

### Variablenprofile

Es werden folgende Variablenprofile angelegt:
* Integer<br>
Solarwatt.Duration

* Float<br>
Solarwatt.GB,
Solarwatt.kW,
Solarwatt.kWh,
Solarwatt.Load,
Solarwatt.MB,
Solarwatt.Percent,
Solarwatt.Temperature,
Solarwatt.W,
Solarwatt.Wh

* String<br>
Solarwatt.PowermeterDirection

## 6. Anhang

### GUIDs
- Modul: `{CB7A9B0B-CCF1-9021-F31C-560F8F222F42}`
- Instanzen:
  - Energymanager: `{1F4D83D3-2A88-1CA5-B39C-CF7D616062FF}`
- Nachrichten:

### Quellen

## 7. Versions-Historie

- 1.0 @ 22.12.2023 11:31
  - Initiale Version
