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
    private $uvbLifeTime = 1;

    /**
     * @var bool
     */
    private $useSessionLifeTime = true;

    // catalog/view/checkout/payment_method/before
    public function checkCustomerByUVBConnector(&$route, &$data)
    {
        if($this->config->get('module_uvb_connector_status') && in_array((int)$this->config->get('config_store_id'),$this->config->get('module_uvb_connector_stores') ?? array()) && $this->config->get('module_uvb_connector_disabled_payment_methods')){

            // Set Email
            $email = '';
            if ($this->customer->isLogged()){
                $this->load->model('account/customer');
                $customer_info = $this->model_account_customer->getCustomer($this->customer->getId());
                $email = $customer_info['email'];
            }else {
                if (isset($this->session->data['guest'])){
                    $email = $this->session->data['guest']['email'];
                }
            }

            if ($this->config->get('module_uvb_connector_sandbox') &&
                isset($this->session->data['user_id']) &&
                isset($this->session->data['user_token']) &&
                $this->config->get('module_uvb_connector_test_email')){
                $email = $this->config->get('module_uvb_connector_test_email');
            }

            $this->load->model('extension/module/uvb_connector');

            $uvbConnectorGetData = array(
                'email' => $email,
                'phoneNumber' => '',
                'countryCode' => '',
                'postalCode' => '',
                'addressLine' => '',
            );

            if($this->hasActiveUVB($email) && $this->session->data['uvb_connector']['status'] == 200) {
                $response = $this->session->data['uvb_connector'];
            }else{
                $response = $response = $this->model_extension_module_uvb_connector->get($uvbConnectorGetData);
            }

            if ($response){

                if ($this->useSessionLifeTime){
                    $response['email'] = $email;
                    $response['life_time'] = time() + ($this->uvbLifeTime * 60);
                    $this->session->data['uvb_connector'] = $response;
                }

                if($response['message']['totalRate'] < (float)$this->config->get('module_uvb_connector_reputation_threshold')){
                    // Remove Payment Methods
                    foreach ($this->config->get('module_uvb_connector_disabled_payment_methods') as $code){
                        unset($data['payment_methods'][$code]);
                        unset($this->session->data['payment_methods'][$code]);
                    }
                    if (empty($this->session->data['payment_methods'])) {
                        $data['error_warning'] = sprintf($this->language->get('error_no_payment'), $this->url->link('information/contact'));
                    } else {
                        $data['error_warning'] = '';
                    }
                }
            }
        }
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

                $order_data = [
                    'email' => $order_info['email'],
                    'outcome' => $outcome,
                    'orderId' => $order_info['order_id'],
                    'phoneNumber' => $order_info['telephone'],
                    'countryCode' => $order_info['shipping_iso_code_2'],
                    'postalCode' => $order_info['shipping_postcode'],
                    'addressLine' => $order_info['shipping_address_1'],
                ];

                $this->load->model('extension/module/uvb_connector');

                // Send data to UVB Connector
                $this->model_extension_module_uvb_connector->post($order_data);

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
    private function hasActiveUVB(string $email): bool{
        if(isset($this->session->data['uvb_connector']) && $this->session->data['uvb_connector']['email'] == $email){
            if ((int)$this->session->data['uvb_connector']['life_time'] < time() ){
                unset($this->session->data['uvb_connector']);
                return false;
            }else{
                return true;
            }
        }
        return false;
    }

}