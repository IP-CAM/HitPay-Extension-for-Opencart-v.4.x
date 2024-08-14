<?php
namespace Opencart\Catalog\Controller\Extension\Hitpay\Payment;

require_once DIR_EXTENSION.'hitpay/system/library/hitpay-php-sdk/Request/CreatePayment.php';
require_once DIR_EXTENSION.'hitpay/system/library/hitpay-php-sdk/Request.php';
require_once DIR_EXTENSION.'hitpay/system/library/hitpay-php-sdk/Client.php';
require_once DIR_EXTENSION.'hitpay/system/library/hitpay-php-sdk/Response/CreatePayment.php';
require_once DIR_EXTENSION.'hitpay/system/library/hitpay-php-sdk/Response/PaymentStatus.php';
require_once DIR_EXTENSION.'hitpay/system/library/hitpay-php-sdk/Response/DeletePaymentRequest.php';

class Hitpay extends \Opencart\System\Engine\Controller {
        private $payment;
    
	public function index() {
            
            $this->load->language('extension/hitpay/payment/hitpay');
            $this->load->model('extension/hitpay/payment/hitpay');

            $this->payment = $this->model_extension_hitpay_payment_hitpay;

            $data['button_confirm'] = $this->language->get('button_confirm');

            $payUrl = $this->payment->getCompatibleRoute('extension/hitpay/payment/hitpay','send');

            $data['action'] = $this->url->link($payUrl, '', true);

            $checkout_type = $this->config->get('payment_hitpay_checkout_mode');
            
            if ($checkout_type != 'drop-in') {
                return $this->load->view('extension/hitpay/payment/hitpay', $data);
            } else {
                return $this->load->view('extension/hitpay/payment/dropin', $data);
            }
	}

	public function callback() {
            if ($this->config->get('payment_hitpay_logging')) {
                $logger = new \Opencart\System\Library\Log('hitpay.log');
                $logger->write('callback get');
                $logger->write($this->request->get);
            }

            if ($this->request->get['status'] == 'completed') {
                //$order_id = (int)($this->session->data['order_id']);
                //$this->load->model('checkout/order');
                //$this->model_checkout_order->addHistory((int)$order_id, $this->config->get('payment_hitpay_order_status_id'));
                /*$this->db->query("UPDATE `" . DB_PREFIX . "order` SET order_status_id  = '" . (int)$this->config->get('payment_hitpay_order_status_id') . "' WHERE order_id = '" . (int)$order_id . "'");*/
                $this->response->redirect($this->url->link('checkout/success', '', true));
            } else {
                $this->response->redirect($this->url->link('checkout/failure', '', true));
            }
	}

	public function webhook() {
            
            if ($this->config->get('payment_hitpay_logging')) {
                $logger = new \Opencart\System\Library\Log('hitpay.log');
                $logger->write('webhook post');
                $logger->write($this->request->post);
            }
            
            $this->load->model('checkout/order');
            $this->load->model('extension/hitpay/payment/hitpay');
            
            $order_id = (int)$this->request->post['reference_number'];
            if ($order_id > 0) {
                $metaData = $this->model_extension_hitpay_payment_hitpay->getPaymentData($order_id);
                if (!empty($metaData)) {
                    $metaData = json_decode($metaData, true);
                    if (isset($metaData['is_webhook_triggered']) && ($metaData['is_webhook_triggered'] == 1)) {
                        exit;
                    }
                }
            }

            $request = [];
            foreach ($this->request->post as $key=>$value) {
                if ($key != 'hmac'){
                    $request[$key] = $value;
                }
            }

            if ($this->config->get('payment_hitpay_mode') == 'live') {
                $hitPayClient = new \HitPay\Client($this->config->get('payment_hitpay_api_key'), true);
            } else {
                $hitPayClient = new \HitPay\Client($this->config->get('payment_hitpay_api_key'), false);
            }

            $hmac = $hitPayClient::generateSignatureArray($this->config->get('payment_hitpay_signature'), (array)$request);

            if ($hmac == $this->request->post['hmac']) {
                if ($order_id > 0) {
                    $metaData = $this->model_extension_hitpay_payment_hitpay->getPaymentData($order_id);
                    if (empty($metaData) || !$metaData) {
                        $paymentData = $this->request->post;
                        $paymentData = json_encode($paymentData);
                        $this->model_extension_hitpay_payment_hitpay->addPaymentData($order_id, $paymentData);
                    }
                }
                
                $payment_id = $this->request->post['payment_id'];
                
                $comment = 'HitPay payment is successful. Transaction ID: '. $payment_id;
                
                $order_status_id = (int)$this->config->get('payment_hitpay_order_status_id');
                
                $this->model_checkout_order->addHistory($order_id, $order_status_id, $comment, true);
                
                $this->model_extension_hitpay_payment_hitpay->updatePaymentData($order_id, 'is_webhook_triggered', 1);
            }
	}

	public function send() {
            
            $json = array();
            $dropInAjax = 0;
            
            if (isset($this->request->post['drop_in_ajax'])) {
                $dropInAjax = 1;
            }
            
            if ($this->config->get('payment_hitpay_mode') == 'live') {
                $hitPayClient = new \HitPay\Client($this->config->get('payment_hitpay_api_key'), true);
            } else {
                $hitPayClient = new \HitPay\Client($this->config->get('payment_hitpay_api_key'), false);
            }

            $this->load->model('checkout/order');
            $this->load->model('extension/hitpay/payment/hitpay');
            
            $this->payment = $this->model_extension_hitpay_payment_hitpay;

            $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
            if ($order_info) {

                try {
                    $payment_method = $this->config->get('payment_hitpay_title');
                    $this->model_extension_hitpay_payment_hitpay->updateOrderData($order_info['order_id'], 'payment_method', $payment_method);
                    
                    $redirectUrl = $this->payment->getCompatibleRoute('extension/hitpay/payment/hitpay','callback');
                    $webhookUrl = $this->payment->getCompatibleRoute('extension/hitpay/payment/hitpay','webhook');
                    
                    $amount = (float)$this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false);
                    //$amount = $order_info['total'];
                   // $amount = round($amount, 2);

                    $request = new \HitPay\Request\CreatePayment();

                    $request
                        ->setAmount($amount)
                        ->setCurrency(strtoupper($order_info['currency_code']))
                        ->setEmail($order_info['email'])
                        ->setPurpose('Order #' . $order_info['order_id'])
                        ->setName(trim($order_info['firstname']) . ' ' . trim($order_info['lastname']))
                        ->setReferenceNumber($order_info['order_id'])
                        ->setRedirectUrl($this->url->link($redirectUrl, '', true))
                        ->setWebhook($this->url->link($webhookUrl, '', true))
                        ->setChannel('api_opencart')
                        ;
                    $request->setChannel('api_opencart');
                    
                    if ($this->config->get('payment_hitpay_logging')) {
                        $logger = new \Opencart\System\Library\Log('hitpay.log');
                        $logger->write('create payment request');
                        $logger->write((array)$request);
                    }
                    
                    
                    $result = $hitPayClient->createPayment($request);
                    
                    if ($this->config->get('payment_hitpay_logging')) {
                        $logger = new \Opencart\System\Library\Log('hitpay.log');
                        $logger->write('create payment response');
                        $logger->write((array)$result);
                    }
                    
                    if ($dropInAjax == 0) {
                        header('Location: ' . $result->url);
                    } else {
                        $json['redirect_url'] = $this->url->link($redirectUrl, 'reference=##reference##&status=##status##', true);
                        $json['payment_request_id'] = $result->getId();
                        $json['payment_url'] = $result->getUrl();
                        
                        $domain = 'sandbox.hit-pay.com';
                        if ($this->config->get('payment_hitpay_mode') == 'live') {
                            $domain = 'hit-pay.com';
                        }
                        $json['domain'] = $domain;
                        $json['apiDomain'] = $domain;
                    }
                } catch (\Exception $e) {
                    if ($dropInAjax == 0) {
                        print_r($e->getMessage());
                    } else {
                        $errorMessage = $e->getMessage();
                        $message = 'Payment Failed: '.$errorMessage;
                        $json['error'] = $message;
                    }
                }
            }
            
            if ($dropInAjax == 1) {
                $this->response->addHeader('Content-Type: application/json');
                $this->response->setOutput(json_encode($json));	
            }
        }
        
        /**
        * Add script to the checkout page
        *
        * @param mixed $route
        * @param mixed $data
        * @param mixed $output
        * 
        * @return void
        */
        public function checkout_after(&$route, &$data, &$output)
        {
           // In case the extension is disabled, do nothing
            if (!$this->config->get('payment_hitpay_status')) {
                return;
            }
            
            $checkout_type = $this->config->get('payment_hitpay_checkout_mode');
            
            if ($checkout_type != 'drop-in') {
                return;
            }
            
            $testMode = 1;
            if ($this->config->get('payment_hitpay_mode') == 'live') {
                $testMode = 0;
            }
            
            $params['test_mode'] = $testMode;
            $content = $this->load->view('extension/hitpay/payment/addscript', $params);

            $hook = '<head';
            $js = '<head>'.$content;
            $output = str_replace($hook, $js, $output);
        }
}