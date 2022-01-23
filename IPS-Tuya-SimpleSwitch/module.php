<?php

declare(strict_types=1);

class Tuya_SimpleSwitch extends IPSModule
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

        $this->SendDebug(__FUNCTION__ . ' Device Type: ', ' Switch', 0);
        $this->RegisterVariableBoolean('Tuya_State', 'State', '~Switch');
        $this->EnableAction('Tuya_State');
        $this->RegisterVariableString('Tuya_Status', 'Status');
    }

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'Tuya_State':
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

    private function SwitchMode(bool $Value)
    {
        $Topic = $this->ReadPropertyString('MQTTTopic') . '/command';
        if ($Value) {
            $Payload = 'on';
        } else {
            $Payload = 'off';
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
        $this->SendDebug(__FUNCTION__ . 'MQTT Server', $ServerJSON, 0);
        $resultServer = @$this->SendDataToParent($ServerJSON);

        if ($resultServer === false) {
            $last_error = error_get_last();
            echo $last_error['message'];
            return false;
        } else {
            return true;
        }
    }

}