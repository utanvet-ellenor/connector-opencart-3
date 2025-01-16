<?php

/**
 * Event Class UVBConnector
 *
 * @author Balla Sándor <info@webelite.hu>
 *
 */

class ControllerExtensionModuleUVBConnector extends Controller {
    /**
     * UVB Session LifeTime in minute
     * @var int
     */
    private $uvbLifeTime = 30; // second

    /**
     * @var bool
     */
    private $useSessionLifeTime = true;

    /**
     * Handle Payment Method
     *
     * @param $route
     * @param $data
     * @param $method_data
     * @return void
     */
    // catalog/model/extension/payment/cod/getMethod/after
    public function handlePaymentMethod(&$route,&$data,&$method_data) {
        if($this->cart->hasShipping() && $this->isUVBActive() && !$this->checkCustomerByUVBConnector()) {
            $method_data = array();
        }
    }

    public function checkCustomerByUVBConnector() {
        $status = true;

        $uvbConnectorGetData = $this->getPayloadDataForUVBCheck();

        if ($uvbConnectorGetData) {

            $this->load->model('extension/module/uvb_connector');

            $response = $this->hasActiveUVB($uvbConnectorGetData['email'])
                ? $this->session->data['uvb_connector']
                : $this->model_extension_module_uvb_connector->get($uvbConnectorGetData);

            if ($response) {

                if ($this->useSessionLifeTime) {
                    $response['email'] = $uvbConnectorGetData['email'];
                    $response['life_time'] = time() + ($this->uvbLifeTime);
                    $this->session->data['uvb_connector'] = $response;
                }

                if(isset($response['status']) && $response['status'] == 200
                    && $response['result']['reputation'] < (float)$this->config->get('module_uvb_connector_reputation_threshold')) {
                    $status = false;
                }
            }
        }

        return $status;
    }

    private function isUVBActive() {
        $status = $this->config->get('module_uvb_connector_status');
        $store_id = (int)$this->config->get('config_store_id');
        $disabledMethods = $this->config->get('module_uvb_connector_disabled_payment_methods');

        if ($this->config->get('module_uvb_connector_stores')) {
            $stores = $this->config->get('module_uvb_connector_stores');
        } else {
            $stores = array();
        }

        return $status
            && in_array($store_id,$stores)
            && (float)$this->config->get('module_uvb_connector_reputation_threshold') > 0
            && $disabledMethods;
    }

    private function getPayloadDataForUVBCheck() {
        $data = array(
            'threshold' => $this->config->get('module_uvb_connector_reputation_threshold'),
        );

        // Journal Checkout
        if ($this->isJournalQuickCheckout() && isset($this->request->post['order_data'])) {
            $sameAddress = $this->request->post['same_address'];

            $data['email'] = $this->request->post['order_data']['email'];
            $data['phoneNumber'] = $this->request->post['order_data']['telephone'];
            $data['countryCode'] = $sameAddress ? $this->request->post['order_data']['payment_iso_code_2'] : $this->request->post['order_data']['shipping_iso_code_2'];
            $data['postalCode'] = $sameAddress ? $this->request->post['order_data']['payment_postcode'] : $this->request->post['order_data']['shipping_postcode'];
            $data['addressLine'] = $sameAddress ? $this->request->post['order_data']['payment_address_1'] : $this->request->post['order_data']['shipping_address_1'];
        }


        // OpenCart Checkout
        if (!$this->isJournalQuickCheckout() && $this->request->get['route'] === 'checkout/payment_method') {
            if ($this->customer->isLogged()) {
                $this->load->model('account/customer');
                $customer_info = $this->model_account_customer->getCustomer($this->customer->getId());
                $data['email'] = $customer_info['email'];
                $data['phoneNumber'] = $customer_info['telephone'];
                $data['countryCode'] = $this->session->data['shipping_address']['iso_code_2'];
                $data['postalCode'] = $this->session->data['shipping_address']['postcode'];
                $data['addressLine'] = $this->session->data['shipping_address']['address_1'];
            } else {
                $sameAddress = $this->session->data['guest']['shipping_address'];

                $data['email'] = $this->session->data['guest']['email'];
                $data['phoneNumber'] = $this->session->data['guest']['telephone'];
                $data['countryCode'] = $sameAddress ? $this->session->data['payment_address']['iso_code_2'] : $this->session->data['shipping_address']['iso_code_2'];
                $data['postalCode'] = $sameAddress ? $this->session->data['payment_address']['postcode'] : $this->session->data['shipping_address']['postcode'];
                $data['addressLine'] = $sameAddress ? $this->session->data['payment_address']['address_1'] : $this->session->data['shipping_address']['address_1'];
            }
        }

        if (isset($data['email']) && $this->validatedEmail($data['email'])) {
            return $data;
        }
        return array();
    }

    private function isJournalQuickCheckout() {
        return $this->config->get('config_theme') === 'journal3'
            && $this->request->get['route'] === 'journal3/checkout/save'
            && $this->journal3->settings->get('activeCheckout') === 'journal';
    }

    // catalog/model/checkout/order/addOrderHistory/after
    public function submitOrderOutcomeToUVBApi(&$route, &$args){
        if($this->config->get('module_uvb_connector_status')){
            if (isset($args[0])) {
                $order_id = $args[0];
            } else {
                $order_id = 0;
            }

            if (isset($args[1])) {
                $order_status_id = $args[1];
            } else {
                $order_status_id = 0;
            }

            $outcome = 0;

            if($order_status_id == $this->config->get('module_uvb_connector_status_good')){
                $outcome = 1;
            }elseif ($order_status_id == $this->config->get('module_uvb_connector_status_bad')){
                $outcome = -1;
            }

            $order_info = $this->model_checkout_order->getOrder($order_id);

            if ($order_info && $outcome !== 0) {

                $this->load->model('extension/module/uvb_connector');

                $payload = [
                    'email' => $order_info['email'],
                    'outcome' => $outcome,
                    'orderId' => $order_info['order_id'],
                    'phoneNumber' => $order_info['telephone'],
                    'countryCode' => $order_info['shipping_iso_code_2'],
                    'postalCode' => $order_info['shipping_postcode'],
                    'addressLine' => $order_info['shipping_address_1'],
                ];

                // Send data to UVB Connector
                $this->model_extension_module_uvb_connector->post($payload);

            }
        }
    }

    /**
     *
     * Get Active UVB Response from session
     *
     * @param string $email
     * @return bool
     */
    private function hasActiveUVB($email)
    {
        if($email
            && isset($this->session->data['uvb_connector'])
            && $this->session->data['uvb_connector']['status'] == 200
            && $this->session->data['uvb_connector']['email'] == $email){
            if ((int)$this->session->data['uvb_connector']['life_time'] < time() ){
                unset($this->session->data['uvb_connector']);
                return false;
            }else{
                return true;
            }
        }
        return false;
    }

    /**
     * Email validator
     *
     * @param string $email
     * @return bool
     */
    private function validatedEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL);
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
            $log = new \Log('uv_connector_event_log.log');

            $log->write( $type . ' - ' . $message);

            unset($log);
        }
    }

}