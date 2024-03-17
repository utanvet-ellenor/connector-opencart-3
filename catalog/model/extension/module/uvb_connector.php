<?php

/**
 * Model Class UVB Connector
 *
 * @author Balla Sándor <info@webelite.hu>
 *
 */

class ModelExtensionModuleUVBConnector extends Model {
    const LOG_FILENAME = "uvb_connector.log";
    const SANDBOX_BASE_URL = 'https://sandbox.utanvet-ellenor.hu/api/v1/signals/';
    const PRODUCTION_BASE_URL = 'https://utanvet-ellenor.hu/api/v1/signals/';
    const LOG_ERROR = 'Error';
    const LOG_INFO = 'Info';

    /**
     * @var
     */
    private $response = [];

    /**
     * Check e-mail reputation
     *
     * @param array $data
     * @return array
     */
    public function get(array $data): array
    {

        $this->_checkUVBService($data);

        return $this->response;
    }

    /**
     * Submit payload to UVB Signals API endpoint
     * @param array $order_data
     * @return array
     */
    public function post(array $order_data): array
    {
        $this->_submitToUVBService($order_data);

        return $this->response;
    }

    /**
     * Send request to UVB API
     * @param array $data
     * @return void
     */
    private function _checkUVBService(array $data) : void
    {
        if($this->validateEmail($data['email'])){
            $emailHash = $this->getEmailHash($data['email']);
            $payload = array();

            $payload['threshold'] = $this->config->get('module_uvb_connector_reputation_threshold');

            // It will be in next UVB Connector version
            //$payload['phoneNumber'] = $data['phoneNumber'];
            //$payload['countryCode'] = $data['countryCode'];
            //$payload['postalCode'] = $data['postalCode'];
            //$payload['addressLine'] = $data['addressLine'];

            $this->getHTTPResponse($this->getBaseUrl() . $emailHash,$payload);
        }else{
            $this->log(self::LOG_ERROR,'Incorrect email address: ' . $data['email']);
        }
    }

    /**
     * Submit payload to UVB Signals API endpoint
     *
     * @param  $data
     * @return void
     */
    private function _submitToUVBService($data) : void
    {
        if($this->validateEmail($data['email'])){
            $payload = array(
                'emailHash' => $this->getEmailHash($data['email']),
                'outcome' => $data['outcome'],
                'orderId' => $data['orderId'],
                'phoneNumber' => $data['phoneNumber'],
                'countryCode' => $data['countryCode'],
                'postalCode' => $data['postalCode'],
                'addressLine' => $data['addressLine'],
            );

            $this->getHTTPResponse($this->getBaseUrl(),$payload);
        }else{
            $this->log(self::LOG_ERROR,'Incorrect email: ' . $data['email'] . ' - order_id' . $data['order_id']);
        }
    }

    /**
     * Get Base Url API
     * @return string
     */
    private function getBaseUrl(): string
    {
        return $this->config->get('module_uvb_connector_sandbox') ? self::SANDBOX_BASE_URL : self::PRODUCTION_BASE_URL;
    }

    /**
     * The hash produced by sha256 hashing the e-mail.
     *
     * @param string $email
     * @return string
     */
    private function getEmailHash(string $email): string
    {
        $email = preg_replace('/(.+)\+.*(@.+)/', '$1$2', $email);

        // Lowercase e-mail address
        $email = strtolower($email);

        // Hash the string with sha256sum
        return hash('sha256', $email);
    }

    /**
     * Email validator
     *
     * @param string $email
     * @return bool
     */
    private function validateEmail(string $email): bool{
        return filter_var($email, FILTER_VALIDATE_EMAIL);
    }

    /**
     * Set Response
     *
     * @param string $url
     * @param array $payload
     * @return void
     */
    private function getHTTPResponse(string $url,array $payload): void
    {
        $publicApiKey = trim($this->config->get('module_uvb_connector_public_key'));
        $privateApiKey = trim($this->config->get('module_uvb_connector_private_key'));

        if ($publicApiKey && $privateApiKey){

            try {
                $ch = curl_init();
                curl_setopt($ch,CURLOPT_URL,$url);
                curl_setopt($ch,CURLOPT_POST, 1);
                curl_setopt($ch,CURLOPT_POSTFIELDS,http_build_query($payload));
                curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch,CURLOPT_CONNECTTIMEOUT ,3);
                curl_setopt($ch,CURLOPT_TIMEOUT, 20);
                curl_setopt($ch, CURLOPT_HTTPHEADER,array(
                    //'Content-Type: application/json', // Errort ad vissza
                    'Authorization: Basic ' . base64_encode($publicApiKey .':'.$privateApiKey)
                ));

                $response = curl_exec($ch);
                curl_close ($ch);

                $this->response = json_decode($response,true);

                $logData = [
                    'url' => $url,
                    'response' => $this->response,
                    'payload' => $payload,
                ];
                $this->log(self::LOG_INFO,$logData);

            } catch (Exception $e) {
                $this->log(self::LOG_INFO,$e->getMessage());
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
    private function log(string $type,$data): void
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