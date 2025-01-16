<?php

/**
 * Model Class UVB Connector
 *
 * @author Balla Sándor <info@webelite.hu>
 *
 */

class ModelExtensionModuleUVBConnector extends Model {

    const LOG_FILENAME = "uvb_connector.log";
    const SANDBOX_BASE_URL = 'https://sandbox.utanvet-ellenor.hu/api/';
    const PRODUCTION_BASE_URL = 'https://utanvet-ellenor.hu/api/';
    const LOG_ERROR = 'ERROR';
    const LOG_INFO = 'INFO';

    private $requestUrl = '/request';
    private $signalUrl = '/signal';

    private $apiVersion = 'v2';

    /**
     * @var
     */
    private $response = array();

    /**
     * Check e-mail reputation
     *
     * @param array $data
     * @return array
     */
    public function get(array $data)
    {
        $this->_checkUVBService($data);

        return $this->response;
    }

    /**
     * Submit payload to UVB Signals API endpoint
     * @param array $order_data
     * @return array
     */
    public function post(array $order_data)
    {
        $this->_submitToUVBService($order_data);

        return $this->response;
    }

    /**
     * Send request to UVB API
     * @param array $data
     * @return void
     */
    private function _checkUVBService($payload)
    {
        $this->getHTTPResponse($this->getBaseUrl() . $this->requestUrl , $payload);
    }

    /**
     * Submit payload to UVB Signals API endpoint
     *
     * @param  $payload
     * @return void
     */
    private function _submitToUVBService($payload)
    {
        $this->getHTTPResponse($this->getBaseUrl() . $this->signalUrl , $payload);
    }

    /**
     * Get Base Url API
     * @return string
     */
    private function getBaseUrl() {
        return $this->config->get('module_uvb_connector_sandbox')
            ? self::SANDBOX_BASE_URL . $this->apiVersion
            : self::PRODUCTION_BASE_URL . $this->apiVersion;
    }

    /**
     * Set Response
     *
     * @param string $url
     * @param array $payload
     * @return void
     */
    private function getHTTPResponse($url,array $payload) {
        $publicApiKey = trim($this->config->get('module_uvb_connector_public_key'));
        $privateApiKey = trim($this->config->get('module_uvb_connector_private_key'));

        if ($publicApiKey && $privateApiKey){

            try {
                $ch = curl_init();
                curl_setopt($ch,CURLOPT_URL,$url);
                curl_setopt($ch,CURLOPT_POST, 1);
                curl_setopt($ch,CURLOPT_POSTFIELDS,$payload);
                curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch,CURLOPT_CONNECTTIMEOUT ,3);
                curl_setopt($ch,CURLOPT_TIMEOUT, 20);
                curl_setopt($ch, CURLOPT_HTTPHEADER,array(
                    'Authorization: Basic ' . base64_encode($publicApiKey .':'.$privateApiKey),
                    'Accept: application/json'
                ));

                $response = curl_exec($ch);
                curl_close ($ch);

                $logData = [
                    'url' => $url,
                    'payload' => $payload,
                ];

                if ($response) {
                    $logData['response'] = $response;
                    $this->response = json_decode($response,true);
                    $logType = self::LOG_INFO;
                }else{
                    $logData['response'] = 'No response';
                    $logType = self::LOG_ERROR;
                }

                $this->log($logType,$logData);

            } catch (Exception $e) {
                $this->log(self::LOG_ERROR,$e->getMessage());
            }
        }else{
            $this->log(self::LOG_ERROR,'Missing Private or Public Key.');
        }
    }

    /**
     * Logger
     *
     * @param string $type
     * @param mixed $data
     * @return void
     */
    private function log($type,$data)
    {
        if ($this->config->get('module_uvb_connector_log')){
            if (is_array($data)) {
                $message = json_encode($data);
            } else {
                $message = $data;
            }
            $log = new \Log(self::LOG_FILENAME);

            $log->write( $type . ' - ' . $message);

            unset($log);
        }
    }
}