<?php

declare(strict_types=1);

class Tuya_DigitalLEDStrip extends IPSModule
{

    public function Create()
    {
        // Never delete this line!
        parent::Create();
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}'); // Connect with IP Symcom MQTT Server

        $this->RegisterPropertyString('MQTTTopic', '');
    }

    public function ApplyChanges()
    {
        // Never delete this line!
        parent::ApplyChanges();
        $this->ConnectParent('{C6D2AEB3-6E1F-4B2E-8E69-3A1A00246850}'); // Connect with IP Symcom MQTT Server
        // Set Filter for ReceiveData
        $MQTTTopic = $this->ReadPropertyString('MQTTTopic');
        $this->SetReceiveDataFilter('.*' . $MQTTTopic . '.*');

        $this->RegisterVariableBoolean('Tuya_State', 'State', '~Switch');
        $this->EnableAction('Tuya_State');
        $this->RegisterVariableInteger('Tuya_White_Brightness', 'White Brightness', '~Intensity.100');
        $this->EnableAction('Tuya_White_Brightness');
        $this->RegisterVariableInteger('Tuya_Color_Brightness', 'Color Brightness', '~Intensity.100');
        $this->EnableAction('Tuya_Color_Brightness');
        $this->RegisterVariableString('Tuya_HS', 'HS');
        $this->RegisterVariableString('Tuya_HSB', 'HSB');
        $this->RegisterVariableString('Tuya_Mode', 'Mode');
        $this->EnableAction('Tuya_Mode');   
        $this->RegisterVariableString('Tuya_Status', 'Status');

    }

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'Tuya_State':
                return $this->SwitchState($Value);
                break;
            case 'Tuya_White_Brightness':
                return $this->SwitchWhiteBrightness($Value);
                break;
            case 'Tuya_Color_Brightness':
                return $this->SwitchColorBrightness($Value);
                break;
            case 'Tuya_Mode':
                return $this->SwitchMode($Value);
                break;
        }
    }

    public function ReceiveData($JSONString)
    {
        $this->SendDebug('JSON', $JSONString, 0);
        if (!empty($this->ReadPropertyString('MQTTTopic'))) {
            $data = json_decode($JSONString);

            switch ($data->DataID) {
                case '{7F7632D9-FA40-4F38-8DEA-C83CD4325A32}': // MQTT Server
                    $Buffer = $data;
                    break;
                default:
                    $this->LogMessage('Invalid Parent', KL_ERROR);
                    return;
            }

            $this->SendDebug('MQTT Topic', $Buffer->Topic, 0);

            if (property_exists($Buffer, 'Topic')) {
                if (fnmatch('*/state', $Buffer->Topic)) {
                    $this->SendDebug('State Payload', $Buffer->Payload, 0);
                    switch ($Buffer->Payload) {
                        case 'OFF':
                            SetValue($this->GetIDForIdent('Tuya_State'), 0);
                            break;
                        case 'ON':
                            SetValue($this->GetIDForIdent('Tuya_State'), 1);
                            break;
                    }
                }
                if (fnmatch('*/white_brightness_state', $Buffer->Topic)) {
                    $this->SendDebug('White Brightness Payload', $Buffer->Payload, 0);
                    SetValue($this->GetIDForIdent('Tuya_White_Brightness'), $Buffer->Payload);
                }
                if (fnmatch('*/color_brightness_state', $Buffer->Topic)) {
                    $this->SendDebug('Color Brightness Payload', $Buffer->Payload, 0);
                    SetValue($this->GetIDForIdent('Tuya_Color_Brightness'), $Buffer->Payload);
                }
                if (fnmatch('*/hs_state', $Buffer->Topic)) {
                    $this->SendDebug('HS Payload', $Buffer->Payload, 0);
                    SetValue($this->GetIDForIdent('Tuya_HS'), $Buffer->Payload);
                }
                if (fnmatch('*/hsb_state', $Buffer->Topic)) {
                    $this->SendDebug('HSB Payload', $Buffer->Payload, 0);
                    SetValue($this->GetIDForIdent('Tuya_HSB'), $Buffer->Payload);
                }
                if (fnmatch('*/mode_state', $Buffer->Topic)) {
                    $this->SendDebug('Mode Payload', $Buffer->Payload, 0);
                    SetValue($this->GetIDForIdent('Tuya_Mode'), $Buffer->Payload);
                }
                if (fnmatch('*/status', $Buffer->Topic)) {
                    $this->SendDebug('Status Payload', $Buffer->Payload, 0);
                    switch ($Buffer->Payload) {
                        case 'online':
                            $this->SetValue('Tuya_Status', 'online');
                            break;
                        case 'offline':
                            $this->SetValue('Tuya_Status', 'offline');
                            break;
                    }
                }
            }
        }
    }

    private function SwitchState(bool $Value)
    {
        $Topic = $this->ReadPropertyString('MQTTTopic') . '/command';
        if ($Value) {
            $Payload = 'on';
        } else {
            $Payload = 'off';
        }
        return $this->sendMQTT($Topic, $Payload);
    }

    private function SwitchWhiteBrightness(int $Value)
    {
        $Topic = $this->ReadPropertyString('MQTTTopic') . '/white_brightness_command';
        return $this->sendMQTT($Topic, $Value);
    }

    private function SwitchColorBrightness(int $Value)
    {
        $Topic = $this->ReadPropertyString('MQTTTopic') . '/color_brightness_command';
        return $this->sendMQTT($Topic, $Value);
    }

    private function SwitchMode(int $Value)
    {
        $Topic = $this->ReadPropertyString('MQTTTopic') . '/color_brightness_command';
        switch($Value) {
            case 'white':
                $Payload = 'white';
                break;
            case 'colour':
                $Payload = 'colour';
                break;
        }
        return $this->sendMQTT($Topic, $Payload);
    }

    private function sendMQTT($Topic, $Payload)
    {
        $resultServer = true;
        $resultClient = true;
        //MQTT Server
        $Server['DataID'] = '{043EA491-0325-4ADD-8FC2-A30C8EEB4D3F}';
        $Server['PacketType'] = 3;
        $Server['QualityOfService'] = 0;
        $Server['Retain'] = false;
        $Server['Topic'] = $Topic;
        $Server['Payload'] = $Payload;
        $ServerJSON = json_encode($Server, JSON_UNESCAPED_SLASHES);
        $this->SendDebug(__FUNCTION__ . ' MQTT Server', $ServerJSON, 0);
        $resultServer = @$this->SendDataToParent($ServerJSON);

        if ($resultServer === false) {
            $last_error = error_get_last();
            echo $last_error['message'];
            return true;
        } else {
            return true;
        }
    }

}