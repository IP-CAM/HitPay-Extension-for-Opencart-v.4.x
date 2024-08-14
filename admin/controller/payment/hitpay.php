<?php
namespace Opencart\Admin\Controller\Extension\Hitpay\Payment;

require_once DIR_EXTENSION.'hitpay/system/library/hitpay-php-sdk/Request.php';
require_once DIR_EXTENSION.'hitpay/system/library/hitpay-php-sdk/Client.php';
require_once DIR_EXTENSION.'hitpay/system/library/hitpay-php-sdk/Response/CreatePayment.php';
require_once DIR_EXTENSION.'hitpay/system/library/hitpay-php-sdk/Response/PaymentStatus.php';
require_once DIR_EXTENSION.'hitpay/system/library/hitpay-php-sdk/Response/Refund.php';

class Hitpay extends \Opencart\System\Engine\Controller {
    private $error = array();
    private $payment;

    public function index() {
        $this->load->language('extension/hitpay/payment/hitpay');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');
        
        $current_version = $this->getCurrentVersion();
        $version = $this->getVersion();
        if ($current_version != $version) {
            $data['upgrade_required'] = true;
            $upgrade_version = 'upgrade_'.$this->getVersionNumber($current_version).'_'.$this->getVersionNumber($version);
            $data['upgrade_link'] = $this->url->link('extension/payment/hitpay/'.$upgrade_version, 'user_token=' . $this->session->data['user_token'], true);
        } else {
            $data['upgrade_required'] = false;
        }

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            $this->model_setting_setting->editSetting('payment_hitpay', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $data['success'] = $this->session->data['success'];

            //$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
        }

        if (isset($this->error['warning'])) {
            $data['error_warning'] = $this->error['warning'];
        } else {
            $data['error_warning'] = '';
        }

        if (isset($this->error['api_key'])) {
            $data['error_api_key'] = $this->error['api_key'];
        } else {
            $data['error_api_key'] = '';
        }

        if (isset($this->error['signature'])) {
            $data['error_signature'] = $this->error['signature'];
        } else {
            $data['error_signature'] = '';
        }

        if (isset($this->error['type'])) {
            $data['error_type'] = $this->error['type'];
        } else {
            $data['error_type'] = '';
        }

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/hitpay/payment/hitpay', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['action'] = $this->url->link('extension/hitpay/payment/hitpay', 'user_token=' . $this->session->data['user_token'], true);

        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);

        if (isset($this->request->post['payment_hitpay_api_key'])) {
            $data['payment_hitpay_api_key'] = $this->request->post['payment_hitpay_api_key'];
        } else {
            $data['payment_hitpay_api_key'] = $this->config->get('payment_hitpay_api_key');
        }

        if (isset($this->request->post['payment_hitpay_signature'])) {
            $data['payment_hitpay_signature'] = $this->request->post['payment_hitpay_signature'];
        } else {
            $data['payment_hitpay_signature'] = $this->config->get('payment_hitpay_signature');
        }

        if (isset($this->request->post['payment_hitpay_mode'])) {
            $data['payment_hitpay_mode'] = $this->request->post['payment_hitpay_mode'];
        } else {
            $data['payment_hitpay_mode'] = $this->config->get('payment_hitpay_mode');
        }

        /*if (isset($this->request->post['payment_hitpay_total'])) {
            $data['payment_hitpay_total'] = $this->request->post['payment_hitpay_total'];
        } else {
            $data['payment_hitpay_total'] = $this->config->get('payment_hitpay_total');
        }*/

        if (isset($this->request->post['payment_hitpay_order_status_id'])) {
            $data['payment_hitpay_order_status_id'] = $this->request->post['payment_hitpay_order_status_id'];
        } else {
            $data['payment_hitpay_order_status_id'] = $this->config->get('payment_hitpay_order_status_id');
        }

        if (isset($this->request->post['payment_hitpay_logging'])) {
            $data['payment_hitpay_logging'] = $this->request->post['payment_hitpay_logging'];
        } else {
            $data['payment_hitpay_logging'] = $this->config->get('payment_hitpay_logging');
        }
        if (isset($this->request->post['payment_hitpay_title'])) {
            $data['payment_hitpay_title'] = $this->request->post['payment_hitpay_title'];
        } else {
            $data['payment_hitpay_title'] = $this->config->get('payment_hitpay_title');
        }
        
        if (isset($this->request->post['payment_hitpay_checkout_mode'])) {
            $data['payment_hitpay_checkout_mode'] = $this->request->post['payment_hitpay_checkout_mode'];
        } else {
            $data['payment_hitpay_checkout_mode'] = $this->config->get('payment_hitpay_checkout_mode');
        }

        /*$data['payment_logos'] = $this->get_payment_logos();
        if (isset($this->request->post['payment_hitpay_logo'])) {
            $data['payment_hitpay_logo'] = $this->request->post['payment_hitpay_logo'];
        } else {
            $payment_hitpay_logo = $this->config->get('payment_hitpay_logo');
            if (empty($payment_hitpay_logo)) {
                $payment_hitpay_logo = [];
            }
            $data['payment_hitpay_logo'] = $payment_hitpay_logo;
        }*/

        $data['payment_methods'] = ['paynow_online' , 'card', 'wechat', 'alipay'];

        $this->load->model('localisation/order_status');

        $data['order_statuses'] = $this->getNewOrderStatuses($this->model_localisation_order_status->getOrderStatuses());

        if (isset($this->request->post['payment_hitpay_geo_zone_id'])) {
            $data['payment_hitpay_geo_zone_id'] = $this->request->post['payment_hitpay_geo_zone_id'];
        } else {
            $data['payment_hitpay_geo_zone_id'] = $this->config->get('payment_hitpay_geo_zone_id');
        }

        $this->load->model('localisation/geo_zone');

        $data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

        if (isset($this->request->post['payment_hitpay_status'])) {
            $data['payment_hitpay_status'] = $this->request->post['payment_hitpay_status'];
        } else {
            $data['payment_hitpay_status'] = $this->config->get('payment_hitpay_status');
        }

        if (isset($this->request->post['payment_hitpay_sort_order'])) {
            $data['payment_hitpay_sort_order'] = $this->request->post['payment_hitpay_sort_order'];
        } else {
            $data['payment_hitpay_sort_order'] = $this->config->get('payment_hitpay_sort_order');
        }

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');


        $this->response->setOutput($this->load->view('extension/hitpay/payment/hitpay', $data));
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/hitpay/payment/hitpay')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!$this->request->post['payment_hitpay_api_key']) {
            $this->error['api_key'] = $this->language->get('error_api_key');
        }

        if (!$this->request->post['payment_hitpay_signature']) {
            $this->error['signature'] = $this->language->get('error_signature');
        }

        return !$this->error;
    }
    
    public function get_payment_logos() {
        $list = array(
            array(
                'value' => 'visa',
                'label' => 'Visa'
            ),
            array(
                'value' => 'master',
                'label' => 'Mastercard'
            ),
            array(
                'value' => 'american_express',
                'label' => 'American Express'
            ),
            array(
                'value' => 'apple_pay',
                'label' => 'Apple Pay'
            ),
            array(
                'value' => 'google_pay',
                'label' => 'Google Pay'
            ),
            array(
                'value' => 'paynow',
                'label' => 'PayNow QR'
            ),
            array(
                'value' => 'grabpay',
                'label' => 'GrabPay'
            ),
            array(
                'value' => 'wechatpay',
                'label' => 'WeChatPay'
            ),
            array(
                'value' => 'alipay',
                'label' => 'AliPay'
            ),
            array(
                'value' => 'shopeepay',
                'label' => 'Shopee Pay'
            ),
            array(
                'value' => 'fpx',
                'label' => 'FPX'
            ),
            array(
                'value' => 'zip',
                'label' => 'Zip'
            )
        );
        return $list;
    }
    
    public function getNewOrderStatuses($statuses) {
        $result = array();
        $skipStatuses = array(
            'Canceled',
            'Canceled Reversal',
            'Chargeback',
            'Denied',
            'Expired',
            'Failed',
            'Refunded',
            'Reversed',
            'Voided'
        );
        foreach ($statuses as $key => $status) {
            if (!in_array($status['name'], $skipStatuses)) {
                $result[] = $status;
            }
        }
        return $result;
    }
    
    public function install() {
        $this->load->model('extension/hitpay/payment/hitpay');
        $this->model_extension_hitpay_payment_hitpay->install();
    }

    public function uninstall() {
            $this->load->model('extension/hitpay/payment/hitpay');
            $this->model_extension_hitpay_payment_hitpay->uninstall();
    }
        
    public function order_info(&$route, &$data, &$output) {
        $order_id = $this->request->get['order_id'];
        
        $this->load->model('sale/order');
        $this->load->language('extension/hitpay/payment/hitpay');
        $this->load->model('extension/hitpay/payment/hitpay');
        
        $this->payment = $this->model_extension_hitpay_payment_hitpay;
        
        $is_hitpay_order = false;
        $tab_key = -1;
        if ($this->payment->isVersion402()) {
            $data['tabs'][] = array('code' => 'hitpay', 'content' => '', 'title' => $this->language->get('heading_title'));
        }

        if (isset($data['tabs'])) {
            foreach ($data['tabs'] as $key => $tabCol) {
                if ($tabCol['code'] == 'hitpay') {
                    if ($order_id > 0) {
                        $order_info = $this->model_sale_order->getOrder($order_id);
                        if ($order_info && isset($order_info['order_status_id'])) {
                            $current_order_status_id = $order_info['order_status_id'];

                            $allowed_order_statuses = [];
                            $order_statuses = $this->getNewOrderStatuses($this->model_localisation_order_status->getOrderStatuses());
                            foreach ($order_statuses as $status) {
                                $allowed_order_statuses[] = $status['order_status_id'];
                            }

                            if (in_array($current_order_status_id, $allowed_order_statuses)){
                                $tab_key = $key;
                                $is_hitpay_order = true;
                                break;
                            } else {
                                unset($data['tabs'][$key]);
                            }
                        }
                    }
                }
            }
        }

        if ($order_id > 0 && $is_hitpay_order) {
            $order = $this->payment->getOrder($order_id);
 
            if ($order && isset($order['response']) && ($metaData = $order['response']) && !empty($metaData)) {
                $metaData = json_decode($metaData, true);

                if(isset($metaData['payment_id']) && !empty($metaData['payment_id'])) {
                    $params = $metaData;
                    
                    /* The below block to add hitpay refund tab to the order page */
                    $tab['title'] = 'HitPay Refund';
                    $tab['code'] = 'hitpay_refund';
                    if(isset($metaData['is_refunded']) && $metaData['is_refunded'] == 1) {
                        /*$params['amount_refunded'] = $this->currency->format($metaData['refundData']['amount_refunded'], $order_info['currency_code'], $order_info['currency_value']);
                        $params['total_amount'] = $this->currency->format($metaData['refundData']['total_amount'], $order_info['currency_code'], $order_info['currency_value']);*/
                        $params['amount_refunded'] = $this->currency->format($metaData['refundData']['amount_refunded'], $order_info['currency_code'], 1);
                        $params['total_amount'] = $this->currency->format($metaData['refundData']['total_amount'], $order_info['currency_code'], 1);
                    } else {
                        $params['is_refunded'] = 0;
                        $params['amount'] = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value']);
                    }

                    $params['user_token'] = $this->session->data['user_token'];
                    $params['order_id'] = $order_id;
                    
                    $refundUrl = $this->payment->getCompatibleRoute('extension/hitpay/payment/hitpay','refund');
                    $params['refund_action'] = $refundUrl;

                    $content = $this->load->view('extension/hitpay/payment/hitpay_refund', $params);
                    
                    $data['tabs'][$tab_key]['content'] .= $content;
                    
                    /* The below block to display hitpay payment details to order totals */
                    $payment_method = '';
                    $fees = '';
                    $fees_currency = '';
                    $payment_request_id = $metaData['payment_request_id'];
                    if (!empty($payment_request_id)) {
                        $payment_method = isset($metaData['payment_type']) ? $metaData['payment_type'] : '';
                        $fees = isset($metaData['fees']) ? $metaData['fees'] : '';
                        $fees_currency = isset($metaData['fees_currency']) ? $metaData['fees_currency'] : '';
                        if (empty($payment_method) || empty($fees) || empty($fees_currency)) {
                            
                            try {
                                if ($this->config->get('payment_hitpay_mode') == 'live') {
                                    $hitPayClient = new \HitPay\Client($this->config->get('payment_hitpay_api_key'), true);
                                } else {
                                    $hitPayClient = new \HitPay\Client($this->config->get('payment_hitpay_api_key'), false);
                                }
 
                                $paymentStatus = $hitPayClient->getPaymentStatus($payment_request_id);
                                if ($paymentStatus) {
                                    $payments = $paymentStatus->payments;
                                    if (isset($payments[0])) {
                                        $payment = $payments[0];
                                        $payment_method = $payment->payment_type;
                                        $fees = $payment->fees;
                                        $fees_currency = $payment->fees_currency;
                                        $this->model_extension_hitpay_payment_hitpay->updatePaymentData($order_id, 'payment_type', $payment_method);
                                        $this->model_extension_hitpay_payment_hitpay->updatePaymentData($order_id, 'fees', $fees);
                                        $this->model_extension_hitpay_payment_hitpay->updatePaymentData($order_id, 'fees_currency', $fees_currency);
                                    }
                                }
                            } catch (\Exception $e) {
                                $payment_method = $e->getMessage();
                            }
                        }
                        
                        if (!empty($payment_method)) {
                            $data['order_totals'][] = array('title' => 'HitPay Payment Type', 'text' => ucwords(str_replace("_", " ", $payment_method)));
                            $data['order_totals'][] = array('title' => 'HitPay Fee', 'text' => $fees .' '.strtoupper($fees_currency));
                        }
                    }
                }
            }
        }
    }

    public function refund() {
        $response = array();
        $status = 0;

        try {
            if (isset($this->request->post['order_id'])) {
                $order_id = $this->request->post['order_id'];
            } else {
                $order_id = 0;
            }

            if (isset($this->request->post['hitpay_amount'])) {
                $hitpay_amount = $this->request->post['hitpay_amount'];
            } else {
                $hitpay_amount = 0;
            }

            if (isset($this->request->post['payment_id'])) {
                $transaction_id = $this->request->post['payment_id'];
            } else {
                $transaction_id = 0;
            }

            $this->load->model('sale/order');
            $order_info = $this->model_sale_order->getOrder($order_id);

            $order_total_paid = $order_info['total'];
            $order_total_paid = (float)$this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false);
            $amount = $hitpay_amount;

            if ($amount <= 0) {
                throw new \Exception('Refund amount shoule be greater than 0');
            }

            if ($amount > $order_total_paid) {
                throw new \Exception('Refund amount shoule be less than or equal to order paid total ('.$order_total_paid.')');
            }

            if ($this->config->get('payment_hitpay_mode') == 'live') {
                $hitPayClient = new \HitPay\Client($this->config->get('payment_hitpay_api_key'), true);
            } else {
                $hitPayClient = new \HitPay\Client($this->config->get('payment_hitpay_api_key'), false);
            }

            $result = $hitPayClient->refund($transaction_id, $amount);

            $this->load->model('extension/hitpay/payment/hitpay');
            $this->model_extension_hitpay_payment_hitpay->updatePaymentData($order_id, 'refundData', array(
                'order_id' => (int) $order_id,
                'refund_id' =>  $result->getId(),
                'payment_id' => $result->getPaymentId(),
                'status' => $result->getStatus(),
                'amount_refunded' => $result->getAmountRefunded(),
                'total_amount' => $result->getTotalAmount(),
                'currency' => $result->getCurrency(),
                'payment_method' => $result->getPaymentMethod(),
                'created_at' => $result->getCreatedAt()
            ));
            $order = $this->model_extension_hitpay_payment_hitpay->updatePaymentData($order_id, 'is_refunded', 1);

            $message = 'Refund successful. Refund Reference Id: '.$result->getId().', '
                    . 'Payment Id: '.$transaction_id.', Amount Refunded: '.$result->getAmountRefunded().', '
                    . 'Payment Method: '.$result->getPaymentMethod().', Created At: '.$result->getCreatedAt();

            $total_refunded = $result->getAmountRefunded();
            if ($total_refunded >= $order_total_paid) {
                //$message .= ' Order status changed, please reload the page';
            }
            $status = 1;
        } catch (\Exception $e) {
            $message = 'Refund Payment Failed: '.$e->getMessage();
        }

        $response['status'] = $status;
        $response['message'] = $message;

        echo json_encode($response);
        exit;
    }
    
    public function upgrade_100_120() {
        $current_version = $this->getCurrentVersion();
        $version = $this->getVersion();
        if ($current_version == $version) {
            echo 'Already upgraded to => '.$current_version;
        } else {
            $this->load->model('extension/hitpay/payment/hitpay');
            $this->model_extension_hitpay_payment_hitpay->upgrade_100_120();
            echo 'Upgraded Successfully.';
        }
        die;
    }
    
    public function getVersion()
    {
        $this->load->model('extension/hitpay/payment/hitpay');
        return $this->model_extension_hitpay_payment_hitpay->getVersion();
    }
    
    public function getCurrentVersion()
    {
        $this->load->model('extension/hitpay/payment/hitpay');
        $current_version = $this->model_extension_hitpay_payment_hitpay->getSettingValue('payment_hitpay_current_version');
        if (!$current_version || empty($current_version)) {
            $current_version = '1.2.0';
        }
        return $current_version;
    }
    
    public function getVersionNumber($version)
    {
        return str_replace('.', '', $version);
    }
}