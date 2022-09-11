<?php
namespace Opencart\Catalog\Controller\Extension\Hitpay\Payment;

require_once DIR_EXTENSION.'hitpay/system/library/hitpay-php-sdk/Request/CreatePayment.php';
require_once DIR_EXTENSION.'hitpay/system/library/hitpay-php-sdk/Request.php';
require_once DIR_EXTENSION.'hitpay/system/library/hitpay-php-sdk/Client.php';
require_once DIR_EXTENSION.'hitpay/system/library/hitpay-php-sdk/Response/CreatePayment.php';
require_once DIR_EXTENSION.'hitpay/system/library/hitpay-php-sdk/Response/PaymentStatus.php';
require_once DIR_EXTENSION.'hitpay/system/library/hitpay-php-sdk/Response/DeletePaymentRequest.php';

class Hitpay extends \Opencart\System\Engine\Controller {
	public function index() {

		$data['button_confirm'] = $this->language->get('button_confirm');

		$data['action'] = $this->url->link('extension/hitpay/payment/hitpay|send', '', true);

		return $this->load->view('extension/hitpay/payment/hitpay', $data);
	}

	public function callback() {
            if ($this->config->get('payment_hitpay_logging')) {
                $logger = new \Opencart\System\Library\Log('hitpay.log');
                $logger->write('callback get');
                $logger->write($this->request->get);
            }

            if ($this->request->get['status'] == 'completed') {
                $order_id = (int)($this->session->data['order_id']);
                $this->load->model('checkout/order');
                $this->model_checkout_order->addHistory((int)$order_id, $this->config->get('payment_hitpay_order_status_id'));
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

                $this->load->model('checkout/order');
                //$this->model_checkout_order->addOrderHistory((int)$this->request->post['reference_number'], $this->config->get('payment_hitpay_order_status_id'), '', false);
                $this->model_extension_hitpay_payment_hitpay->updatePaymentData($order_id, 'is_webhook_triggered', 1);
            }
	}

	public function send() {
            
            if ($this->config->get('payment_hitpay_mode') == 'live') {
                $hitPayClient = new \HitPay\Client($this->config->get('payment_hitpay_api_key'), true);
            } else {
                $hitPayClient = new \HitPay\Client($this->config->get('payment_hitpay_api_key'), false);
            }

            $this->load->model('checkout/order');
            $this->load->model('extension/hitpay/payment/hitpay');

            $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
            if ($order_info) {

                try {
                    $payment_method = $this->config->get('payment_hitpay_title');
                    $this->model_extension_hitpay_payment_hitpay->updateOrderData($order_info['order_id'], 'payment_method', $payment_method);

                    $request = new \HitPay\Request\CreatePayment();

                    $request
                        ->setAmount((float)$this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false))
                        ->setCurrency(strtoupper($order_info['currency_code']))
                        ->setEmail($order_info['email'])
                        ->setPurpose('Order #' . $order_info['order_id'])
                        ->setName(trim($order_info['firstname']) . ' ' . trim($order_info['lastname']))
                        ->setReferenceNumber($order_info['order_id'])
                        ->setRedirectUrl($this->url->link('extension%2Fhitpay%2Fpayment%2Fhitpay%7Ccallback', '', true))
                        ->setWebhook($this->url->link('extension%2Fhitpay%2Fpayment%2Fhitpay%7Cwebhook', '', true))
                        ->setChannel('api_opencart')
                        ;
                    $request->setChannel('api_opencart');
                    $result = $hitPayClient->createPayment($request);
                    header('Location: ' . $result->url);

                } catch (\Exception $e) {
                    print_r($e->getMessage());
                }
            }
        }
}