<?php

/**
 * Class UVB Connector
 *
 * @author Balla Sándor <info@webelite.hu>
 *
 */

class ControllerExtensionModuleUVBConnector extends Controller {
    const LOG_FILENAME = "uvb_connector.log";
    const UVB_MODULE_VERSION = '1.0';
    const EVENT_GET = 'uvb_connector_get';
    const EVENT_POST = 'uvb_connector_post';
    const EVENT_MENU = 'uvb_connector_admin_menu';

    private $error = array();

    public function index() {

        $this->load->language('extension/module/uvb_connector');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('module_uvb_connector', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');

            //$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
            $this->response->redirect($this->url->link('extension/module/uvb_connector', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
        }

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/uvb_connector', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['action'] = $this->url->link('extension/module/uvb_connector', 'user_token=' . $this->session->data['user_token'], true);

        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);

        // Links
        $data['uvb_link'] = 'https://utanvet-ellenor.hu/?utm_source=bs_module&utm_medium=opencart&utm_campaign=';
        $data['uvb_link_knowledge_base'] = 'https://utanvet-ellenor.hu/knowledge-base/?utm_source=bs_module&utm_medium=opencart&utm_campaign=';
        $data['uvb_link_register'] = 'https://utanvet-ellenor.hu/register/?utm_source=bs_module&utm_medium=opencart&utm_campaign=';
        $data['uvb_link_login'] = 'https://utanvet-ellenor.hu/login/?utm_source=bs_module&utm_medium=opencart&utm_campaign=';
        $data['uvb_link_support'] = 'mailto:info@webelite.hu?subject=Help:UVB Connector Module for OpenCart';

        // Order Statuses
        $this->load->model('localisation/order_status');

        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        // Stores
        $this->load->model('setting/store');

        $data['stores'] = array();

        $data['stores'][] = array(
            'store_id' => 0,
            'name'     => $this->config->get('config_name') . $this->language->get('text_default'),
        );

        $results = $this->model_setting_store->getStores();

        foreach ($results as $result) {
            $data['stores'][] = array(
                'store_id' => $result['store_id'],
                'name'     => $result['name'],
            );
        }

        // Payment Methods
        $data['payment_methods'] = array();

        $files = glob(DIR_APPLICATION . 'controller/extension/payment/*.php');

        if ($files) {
            foreach ($files as $file) {
                $extension = basename($file, '.php');

                $this->load->language('extension/payment/' . $extension, 'extension');

                if($this->config->get('payment_' . $extension . '_status')){
                    $data['payment_methods'][] = array(
                        'name'       => $this->language->get('extension')->get('heading_title'),
                        'code'       => $extension,
                    );
                }
            }
        }

        // Log
        $data['download'] = $this->url->link('extension/module/uvb_connector/download', 'user_token=' . $this->session->data['user_token'], true);
        $data['clear'] = $this->url->link('extension/module/uvb_connector/clear', 'user_token=' . $this->session->data['user_token'], true);

        $data['log'] = '';
        $data['error_log_warning'] = '';

        $file = DIR_LOGS . self::LOG_FILENAME;
        if (file_exists($file)) {
            $size = filesize($file);

            if ($size >= 5242880) {
                $suffix = array(
                    'B',
                    'KB',
                    'MB',
                    'GB',
                    'TB',
                    'PB',
                    'EB',
                    'ZB',
                    'YB'
                );

                $i = 0;

                while (($size / 1024) > 1) {
                    $size = $size / 1024;
                    $i++;
                }
                $data['error_log_warning'] = sprintf($this->language->get('error_log_warning'), basename($file), round(substr($size, 0, strpos($size, '.') + 4), 2) . $suffix[$i]);
            } else {
                $data['log'] = file_get_contents($file, FILE_USE_INCLUDE_PATH, null);
            }
        }

        if (isset($this->request->post['module_uvb_connector_public_key'])) {
            $data['module_uvb_connector_public_key'] = $this->request->post['module_uvb_connector_public_key'];
        } else {
            $data['module_uvb_connector_public_key'] = $this->config->get('module_uvb_connector_public_key');
        }

        if (isset($this->request->post['module_uvb_connector_private_key'])) {
            $data['module_uvb_connector_private_key'] = $this->request->post['module_uvb_connector_private_key'];
        } else {
            $data['module_uvb_connector_private_key'] = $this->config->get('module_uvb_connector_private_key');
        }

        if (isset($this->request->post['module_uvb_connector_sandbox'])) {
            $data['module_uvb_connector_sandbox'] = $this->request->post['module_uvb_connector_sandbox'];
        } else {
            $data['module_uvb_connector_sandbox'] = $this->config->get('module_uvb_connector_sandbox');
        }

        if (isset($this->request->post['module_uvb_connector_test_email'])) {
            $data['module_uvb_connector_test_email'] = $this->request->post['module_uvb_connector_test_email'];
        } else {
            $data['module_uvb_connector_test_email'] = $this->config->get('module_uvb_connector_test_email');
        }

        if (isset($this->request->post['module_uvb_connector_log'])) {
            $data['module_uvb_connector_log'] = $this->request->post['module_uvb_connector_log'];
        } else {
            $data['module_uvb_connector_log'] = $this->config->get('module_uvb_connector_log');
        }

        if (isset($this->request->post['module_uvb_connector_reputation_threshold'])) {
            $data['module_uvb_connector_reputation_threshold'] = $this->request->post['module_uvb_connector_reputation_threshold'];
        } else {
            $data['module_uvb_connector_reputation_threshold'] = $this->config->get('module_uvb_connector_reputation_threshold');
        }

        if (isset($this->request->post['module_uvb_connector_status_good'])) {
            $data['module_uvb_connector_status_good'] = $this->request->post['module_uvb_connector_status_good'];
        } else {
            $data['module_uvb_connector_status_good'] = $this->config->get('module_uvb_connector_status_good');
        }

        if (isset($this->request->post['module_uvb_connector_status_bad'])) {
            $data['module_uvb_connector_status_bad'] = $this->request->post['module_uvb_connector_status_bad'];
        } else {
            $data['module_uvb_connector_status_bad'] = $this->config->get('module_uvb_connector_status_bad');
        }

        if (isset($this->request->post['module_uvb_connector_disabled_payment_methods'])) {
            $data['module_uvb_connector_disabled_payment_methods'] = $this->request->post['module_uvb_connector_disabled_payment_methods'];
        } else {
            $data['module_uvb_connector_disabled_payment_methods'] = $this->config->get('module_uvb_connector_disabled_payment_methods');
        }

        if (isset($this->request->post['module_uvb_connector_enabled_shipping_methods'])) {
            $data['module_uvb_connector_enabled_shipping_methods'] = $this->request->post['module_uvb_connector_enabled_shipping_methods'];
        } else {
            $data['module_uvb_connector_enabled_shipping_methods'] = $this->config->get('module_uvb_connector_enabled_shipping_methods');
        }

        if (isset($this->request->post['module_uvb_connector_stores'])) {
            $data['module_uvb_connector_stores'] = $this->request->post['module_uvb_connector_stores'];
        } else {
            $data['module_uvb_connector_stores'] = $this->config->get('module_uvb_connector_stores');
        }

        if (isset($this->request->post['module_uvb_connector_status'])) {
            $data['module_uvb_connector_status'] = $this->request->post['module_uvb_connector_status'];
        } else {
            $data['module_uvb_connector_status'] = $this->config->get('module_uvb_connector_status');
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/module/uvb_connector', $data));
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/module/uvb_connector')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }

    public function download() {
        $this->load->language('extension/module/uvb_connector');

        $file = DIR_LOGS . self::LOG_FILENAME;

        if (file_exists($file) && filesize($file) > 0) {
            $this->response->addheader('Pragma: public');
            $this->response->addheader('Expires: 0');
            $this->response->addheader('Content-Description: File Transfer');
            $this->response->addheader('Content-Type: application/octet-stream');
            $this->response->addheader('Content-Disposition: attachment; filename="' . $this->config->get('config_name') . '_' . date('Y-m-d_H-i-s', time()) . '_'. self::LOG_FILENAME .'"');
            $this->response->addheader('Content-Transfer-Encoding: binary');

            $this->response->setOutput(file_get_contents($file, FILE_USE_INCLUDE_PATH, null));
        } else {
            $this->session->data['error'] = sprintf($this->language->get('error_warning'), basename($file), '0B');

            $this->response->redirect($this->url->link('extension/module/uvb_connector', 'user_token=' . $this->session->data['user_token'], true));
        }
    }

    public function clear() {
        $this->load->language('extension/module/uvb_connector');

        if (!$this->user->hasPermission('modify', 'extension/module/uvb_connector')) {
            $this->session->data['error'] = $this->language->get('error_permission');
        } else {
            $file = DIR_LOGS . self::LOG_FILENAME;

            $handle = fopen($file, 'w+');

            fclose($handle);

            $this->session->data['success'] = $this->language->get('text_clear_success');
        }

        $this->response->redirect($this->url->link('extension/module/uvb_connector', 'user_token=' . $this->session->data['user_token'], true));
    }

    /**
     * Add UVB Menu
     * @param $route
     * @param $data
     * @return void
     */
    // admin/view/common/column_left/before
    public function addUVBConnectorMenuToAdminMenu(&$route,&$data){
        if ($this->user->hasPermission('access', 'extension/module/uvb_connector')) {
            $this->load->language('extension/module/uvb_connector');
            $uvbConnectorChild[] = array(
                'name'	   => $this->language->get('text_settings'),
                'href'     => $this->url->link('extension/module/uvb_connector', 'user_token=' . $this->session->data['user_token'], true),
            );

            $data['menus'][] = array(
                'id'       => 'menu-uvb-connector',
                'icon'	   => 'fa-connectdevelop',
                'name'	   => $this->language->get('heading_title') . ' ' .self::UVB_MODULE_VERSION,
                'href'     => '',
                'children' => $uvbConnectorChild
            );
        }
    }

    public function install(){
        $this->load->model('setting/event');
        $this->model_setting_event->addEvent(self::EVENT_GET, 'catalog/view/checkout/payment_method/before', 'extension/module/uvb_connector/checkCustomerByUVBConnector');
        $this->model_setting_event->addEvent(self::EVENT_POST, 'catalog/model/checkout/order/addOrderHistory/after', 'extension/module/uvb_connector/submitOrderOutcomeToUVBApi');
        $this->model_setting_event->addEvent(self::EVENT_MENU, 'admin/view/common/column_left/before', 'extension/module/uvb_connector/addUVBConnectorMenuToAdminMenu');
    }

    public function uninstall(){
        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode(self::EVENT_GET);
        $this->model_setting_event->deleteEventByCode(self::EVENT_POST);
        $this->model_setting_event->deleteEventByCode(self::EVENT_MENU);
    }

}