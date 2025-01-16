<?php

/**
 * Class UVB Connector
 *
 * @author Balla Sándor <info@webelite.hu>
 *
 */

class ControllerExtensionModuleUVBConnector extends Controller {
    const LOG_FILENAME = "uvb_connector.log";
    const UVB_MODULE_VERSION = '2.0';
    const EVENT_POST = 'uvb_connector_post';
    const EVENT_MENU = 'uvb_connector_admin_menu';
    const EVENT_MENU_ICON = 'uvb_admin_menu_icon';
    const EVENT_PAYMENT = 'uvb_connector_payment_';

    private $error = array();

    public function index() {

        $this->load->language('extension/module/uvb_connector');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {

            $this->deletePaymentMethodEvents();
            if (isset($this->request->post['module_uvb_connector_disabled_payment_methods'])) {
                foreach ($this->request->post['module_uvb_connector_disabled_payment_methods'] as $code) {
                    $this->addPaymentMethodEventByCode($code);
                }
            }

            $this->model_setting_setting->editSetting('module_uvb_connector', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('extension/module/uvb_connector', 'user_token=' . $this->session->data['user_token'] . '&type=module', true));
        }

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->error['threshold'])) {
            $data['error_threshold'] = $this->error['threshold'];
        } else {
            $data['error_threshold'] = '';
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
        $data['uvb_link'] = 'https://utanvet-ellenor.hu';
        $data['uvb_link_knowledge_base'] = 'https://utanvet-ellenor.hu/knowledge-base';
        $data['uvb_link_register'] = 'https://utanvet-ellenor.hu/register';
        $data['uvb_link_login'] = 'https://utanvet-ellenor.hu/login';
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

        if (isset($this->request->post['module_uvb_connector_stores'])) {
            $data['module_uvb_connector_stores'] = $this->request->post['module_uvb_connector_stores'];
        } else {
            $data['module_uvb_connector_stores'] = $this->config->get('module_uvb_connector_stores');
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

        if (!$this->request->post['module_uvb_connector_reputation_threshold']) {
            $this->error['threshold'] = $this->language->get('error_threshold');
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
     * Add UVB Admin Menu
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
                'icon'	   => 'fa-connectdevelop fa-uvb-icon',
                'name'	   => $this->language->get('heading_title') . ' ' .self::UVB_MODULE_VERSION,
                'href'     => '',
                'children' => $uvbConnectorChild
            );
        }
    }

    /**
     * Replace UVB Admin Menu Icon To Original UVB Icon
     * @param $route
     * @param $args
     * @param $output
     * @return void
     */
    // admin/view/common/column_left/after
    public function modifyAdminMenuIcon(&$route,&$args,&$output){
        $originalUVBIcon = '<svg width="20" height="20" style="margin-bottom: -4px;margin-right: 5px" viewBox="0 0 77 77" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M73.1066 38.4991L55.8026 8.65161H21.1946L3.89307 38.4991L21.1946 68.3482H55.8026L73.1066 38.4991ZM38.0544 25.2474C37.5341 24.4687 38.2027 23.3642 39.1474 23.4652H39.1661L49.395 24.5535C49.7064 24.5816 49.9438 24.8425 49.9412 25.1539V27.6673C49.9436 27.6979 49.9436 27.7311 49.9412 27.7611V30.0686C49.9436 30.1 49.9436 30.1316 49.9412 30.163V30.181C49.8833 32.7909 47.7401 34.8752 45.1187 34.8712C42.6872 34.8747 40.6345 33.072 40.3336 30.6691H38.8459C38.4554 30.6691 38.1232 30.4318 37.9791 30.1058C37.8356 29.7806 37.906 29.3159 38.1864 29.0367C38.2037 29.0239 38.2209 29.0132 38.2386 29.0022L38.243 28.9995L40.0324 27.5738L38.0923 25.2846C38.0784 25.2732 38.0664 25.2613 38.0544 25.2474ZM48.7355 25.6784L39.1474 24.6844L41.1817 27.0671H48.7355V25.6784ZM30.6513 27.0857C30.7268 27.0719 30.802 27.0671 30.8772 27.0671H30.878C31.4079 27.0648 31.9166 27.4141 32.0268 27.9676L34.0795 38.2295C34.1286 38.5476 33.9192 38.8481 33.6027 38.9132C33.2863 38.978 32.9742 38.7846 32.8931 38.4732L30.8586 28.2864L26.507 30.0686H29.0876C29.3843 30.0686 29.6903 30.1364 29.9544 30.3125C30.2113 30.4834 30.3831 30.786 30.444 31.1006H30.4632V31.1192L32.2717 40.1056C32.3414 40.4361 32.2584 40.7802 32.0455 41.0432C31.99 41.1072 31.9265 41.1641 31.8569 41.2124L32.6671 42.0379L37.9042 37.3663L37.9223 37.3477C38.8014 36.6181 40.1975 36.0719 41.3884 36.0719H48.3209C49.5255 36.0682 50.6676 36.6057 51.4296 37.535C51.5221 37.6012 51.5939 37.6926 51.6363 37.7978C51.6435 37.81 51.6483 37.8236 51.6555 37.835V37.8544C52.0933 38.5154 52.3169 39.2947 52.2958 40.0864V52.881C52.2958 53.2112 52.025 53.4814 51.6929 53.4814H39.5428C39.3758 53.5611 39.1824 53.5611 39.0154 53.4814H37.2255C36.8941 53.4814 36.6228 53.2112 36.6228 52.881V46.0331L34.3623 47.9843C33.8501 48.428 33.1951 48.674 32.5164 48.6783C31.7955 48.6783 31.0637 48.3757 30.5192 47.8342L30.5005 47.8148L24.4724 41.2863C24.4351 41.2467 24.4044 41.2023 24.3785 41.1556C24.2043 40.9845 24.0837 40.7515 24.0392 40.5174C24.0392 40.5159 24.0396 40.5138 24.0401 40.5116C24.0412 40.5065 24.0425 40.5005 24.0392 40.4988L22.2306 31.6811L22.2119 31.6625C22.1698 31.3647 22.1674 30.9991 22.3813 30.6497C22.5556 30.3659 22.8877 30.1526 23.2668 30.0867L30.5379 27.1049C30.5566 27.0974 30.5755 27.0911 30.5947 27.0857H30.6513ZM48.7355 28.2678H41.1064L41.0878 28.2864L39.5807 29.4684H40.7671C40.8479 29.452 40.9312 29.452 41.0117 29.4684H48.7355V28.2678ZM48.679 30.6691H41.5586C41.8388 32.3926 43.2941 33.6706 45.1187 33.6706C46.9435 33.6706 48.3986 32.3926 48.679 30.6691ZM29.0876 31.2693H23.5683L23.5309 31.2879C23.4952 31.3046 23.4554 31.316 23.4176 31.3259C23.4172 31.3281 23.4168 31.3303 23.4165 31.3326C23.4118 31.3607 23.4064 31.3931 23.4176 31.4757H23.3989L25.2072 40.2743H31.0093C31.0478 40.2743 31.0621 40.2748 31.0668 40.2751C31.0688 40.2753 31.069 40.2754 31.0686 40.2754M29.0876 31.2693C29.2125 31.2693 29.269 31.3022 29.2762 31.3073ZM29.2762 31.3073V31.3251ZM29.2762 31.3251L31.0661 40.2743ZM31.0661 40.2743C31.0657 40.2747 31.0662 40.275 31.0668 40.2751ZM46.6818 37.2725H41.3892C40.6542 37.2725 39.2883 37.7978 38.7139 38.2667C38.7127 38.2679 38.7086 38.2673 38.7045 38.2667C38.7005 38.2661 38.6964 38.2655 38.6952 38.2667L33.0438 43.2944C32.8064 43.5088 32.4419 43.5008 32.2144 43.2758L30.3879 41.4749H26.3189L31.3868 46.9901L31.4055 47.0092C31.7067 47.298 32.1613 47.4782 32.5169 47.4782C32.927 47.4782 33.2463 47.3605 33.5718 47.0844L36.8306 44.27C37.0088 44.1159 37.2607 44.0795 37.4757 44.1765C37.6902 44.2724 37.8289 44.486 37.8289 44.7203V48.2471L46.6818 37.2725ZM48.3215 37.2725H48.2268L37.8284 50.1611V52.2808H39.0154L50.3179 38.0608C49.8238 37.5728 49.1197 37.2725 48.3215 37.2725ZM51.0902 40.0679C51.0998 39.7466 51.0433 39.4488 50.9587 39.1672L40.5603 52.2808H51.0902V40.0679Z" fill="#b4cbdd"/>
                            </svg>';

        $icon = '/<i class="fa fa-connectdevelop fa-uvb-icon fw"><\/i>/';
        if (preg_match($icon,$output)){
            $output = preg_replace($icon,$originalUVBIcon,$output);
        }
    }

    public function install(){
        // Add Module Events
        $this->load->model('setting/event');

        $this->model_setting_event->addEvent(self::EVENT_POST, 'catalog/model/checkout/order/addOrderHistory/after', 'extension/module/uvb_connector/submitOrderOutcomeToUVBApi');
        $this->model_setting_event->addEvent(self::EVENT_MENU, 'admin/view/common/column_left/before', 'extension/module/uvb_connector/addUVBConnectorMenuToAdminMenu');
        $this->model_setting_event->addEvent(self::EVENT_MENU_ICON, 'admin/view/common/column_left/after', 'extension/module/uvb_connector/modifyAdminMenuIcon');
    }

    public function uninstall(){
        // Remove Module Events
        $this->load->model('setting/event');

        $this->model_setting_event->deleteEventByCode(self::EVENT_POST);
        $this->model_setting_event->deleteEventByCode(self::EVENT_MENU);
        $this->model_setting_event->deleteEventByCode(self::EVENT_MENU_ICON);

        $this->deletePaymentMethodEvents();
    }

    private function addPaymentMethodEventByCode($code) {
        $this->model_setting_event->addEvent(self::EVENT_PAYMENT . $code, 'catalog/model/extension/payment/'. $code . '/getMethod/after', 'extension/module/uvb_connector/handlePaymentMethod');
    }

    private function deletePaymentMethodEvents() {

        $files = glob(DIR_APPLICATION . 'controller/extension/payment/*.php');

        if ($files) {
            foreach ($files as $file) {
                $extension = basename($file, '.php');
                $this->model_setting_event->deleteEventByCode(self::EVENT_PAYMENT . $extension);
            }
        }
    }

}