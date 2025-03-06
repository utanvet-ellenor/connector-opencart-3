<?php

/**
 * Event Class UVBConnector
 *
 * @author Balla Sándor <info@webelite.hu>
 *
 */

class ControllerExtensionModuleUVBConnector extends Controller {

    /**
     * Log file
     */
    const LOG_FILENAME = 'uv_connector_event_log.log';

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
     * Handle OpenCart Payment Method
     *
     * @param $route
     * @param $data
     * @param $method_data
     * @return void
     */
    //  catalog/model/extension/payment/cod/getMethod/after
    public function handlePaymentMethod(&$route,&$data,&$method_data) {
        if($this->cart->hasShipping() && $this->isUVBActive() && !$this->checkCustomerByUVBConnector()) {
            $method_data = array();
        }
    }

    /**
     * Handle XPayment Methods
     *
     * @param $route
     * @param $data
     * @param $method_data
     * @return void
     */
    // catalog/model/extension/payment/xpayment/getMethod/after
    public function handleXPaymentMethod(&$route,&$data,&$method_data) {
        // Route: extension/payment/xpayment/getMethod
        if($this->cart->hasShipping() && $this->isUVBActive() && !$this->checkCustomerByUVBConnector()) {
            $disabledMethods = $this->config->get('module_uvb_connector_disabled_payment_methods');
            foreach ($disabledMethods as $code) {
                $this->config->set('payment_' . $code . '_status', false);
            }
        }
    }

    public function checkCustomerByUVBConnector() {
        $uvbConnectorGetData = $this->getPayloadDataForUVBCheck();
        if (!$uvbConnectorGetData) {
            return true;
        }

        $this->load->model('extension/module/uvb_connector');

        $response = $this->hasActiveUVB($uvbConnectorGetData['email'])
            ? $this->session->data['uvb_connector']
            : $this->model_extension_module_uvb_connector->get($uvbConnectorGetData);

        if (!$response) {
            return true;
        }

        if ($this->useSessionLifeTime) {
            $response['email'] = $uvbConnectorGetData['email'];
            $response['life_time'] = time() + ($this->uvbLifeTime);
            $this->session->data['uvb_connector'] = $response;
        }

        $blocked = $response['result']['blocked'] ?? false;

        return !$blocked;
    }

    private function isUVBActive() {
        $status = $this->config->get('module_uvb_connector_status');
        if (!$status) {
            return false;
        }

        $disabledMethods = $this->config->get('module_uvb_connector_disabled_payment_methods');
        if (!$disabledMethods) {
            return false;
        }

        $store_id = (int) $this->config->get('config_store_id');
        $stores = $this->config->get('module_uvb_connector_stores');
        if (!$stores) {
            return false;
        }

        return in_array($store_id, $stores);
    }

    private function getPayloadDataForUVBCheck() {
        $data = array(
            'threshold' => $this->config->get('module_uvb_connector_reputation_threshold'),
        );

        // Journal Checkout
        if ($this->isJournalQuickCheckout()) {
            $sameAddress = $this->request->post['same_address'];

            $data['email'] = $this->request->post['order_data']['email'];
            $data['phoneNumber'] = $this->request->post['order_data']['telephone'];
            $data['countryCode'] = $sameAddress ? $this->request->post['order_data']['payment_iso_code_2'] : $this->request->post['order_data']['shipping_iso_code_2'];
            $data['postalCode'] = $sameAddress ? $this->request->post['order_data']['payment_postcode'] : $this->request->post['order_data']['shipping_postcode'];
            $data['addressLine'] = $sameAddress ? $this->request->post['order_data']['payment_address_1'] : $this->request->post['order_data']['shipping_address_1'];

            if ($this->validatedEmail($data['email'])) {
                return $data;
            }
        }

        // OpenCart Checkout
        if ($this->isOpenCartCheckout()) {
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

            if ($this->validatedEmail($data['email'])) {
                return $data;
            }
        }

        // xtensions Best Checkout
        if ($this->isXtensionCheckout()) {
            if ($this->customer->isLogged()) {
                $this->load->model('account/customer');
                $customer_info = $this->model_account_customer->getCustomer($this->customer->getId());
                $data['email'] = $customer_info['email'];
                $data['phoneNumber'] = $customer_info['telephone'];
                $data['countryCode'] = $this->session->data['shipping_address']['iso_code_2'];
                $data['postalCode'] = $this->session->data['shipping_address']['postcode'];
                $data['addressLine'] = $this->session->data['shipping_address']['address_1'];
            } else {
                $sameAddress = $this->session->data['shipping_same_guest'] ?? false;

                $data['email'] = $this->session->data['guest']['email'];
                $data['phoneNumber'] = $this->session->data['guest']['telephone'];
                $data['countryCode'] = $sameAddress ? $this->session->data['payment_address']['iso_code_2'] : $this->session->data['shipping_address']['iso_code_2'];
                $data['postalCode'] = $sameAddress ? $this->session->data['payment_address']['postcode'] : $this->session->data['shipping_address']['postcode'];
                $data['addressLine'] = $sameAddress ? $this->session->data['payment_address']['address_1'] : $this->session->data['shipping_address']['address_1'];
            }

            if ($this->validatedEmail($data['email'])) {
                return $data;
            }
        }

        return [];

    }

    private function isXtensionCheckout() {
        if ($this->request->get['route'] === 'extension/module/xtensions/checkout/xpayment_method') {
            return true;
        }

        return false;
    }

    private function isOpenCartCheckout() {
        if ($this->isJournalQuickCheckout()) {
            return false;
        }

        if ($this->request->get['route'] !== 'checkout/payment_method') {
            return false;
        }

        return true;
    }

    private function isJournalQuickCheckout() {
        if ($this->config->get('config_theme') !== 'journal3') {
            return false;
        }

        if ($this->request->get['route'] !== 'journal3/checkout/save') {
            return false;
        }

        if (!isset($this->request->post['order_data'])) {
            return false;
        }

        return $this->journal3->settings->get('activeCheckout') === 'journal';
    }

    // catalog/model/checkout/order/addOrderHistory/after
    public function submitOrderOutcomeToUVBApi(&$route, &$args){
        if (!$this->config->get('module_uvb_connector_status')) {
            return;
        }

        $order_id = $args[0] ?? null;
        if (!$order_id) {
            return;
        }

        $order_status_id = $args[1] ?? null;
        if (!$order_status_id) {
            return;
        }

        $goodStatusId = $this->config->get('module_uvb_connector_status_good');
        $badStatusId = $this->config->get('module_uvb_connector_status_bad');

        if (!in_array($order_status_id, [$goodStatusId, $badStatusId])) {
            return;
        }

        switch ($order_status_id) {
            case $goodStatusId:
                $outcome = 1;
                break;
            case $badStatusId:
                $outcome = -1;
                break;
            default:
                $outcome = null;
                break;
        }
        if (!$outcome) {
            return;
        }

        $order_info = $this->model_checkout_order->getOrder($order_id);
        if (!$order_info) {
            return;
        }

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

    /**
     *
     * Get Active UVB Response from session
     *
     * @param string $email
     * @return bool
     */
    private function hasActiveUVB($email)
    {
        if (!$email) {
            return false;
        }

        if (!isset($this->session->data['uvb_connector'])) {
            return false;
        }

        if ($this->session->data['uvb_connector']['email'] != $email) {
            return false;
        }

        $lifetime = (int)$this->session->data['uvb_connector']['life_time'];
        if ($lifetime <= time()) {
            unset($this->session->data['uvb_connector']);

            return false;
        }

        return true;
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
        if (!$this->config->get('module_uvb_connector_log')) {
            return;
        }

        $message = is_array($data) ? json_encode($data) : $data;

        $log = new \Log(self::LOG_FILENAME);
        $log->write( $type . ' - ' . $message);

        unset($log);
    }
}
