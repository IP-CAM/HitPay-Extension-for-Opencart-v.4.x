<?php
namespace Opencart\Admin\Model\Extension\Hitpay\Payment;

class Hitpay extends \Opencart\System\Engine\Model {

	public function install() {
		$this->db->query("
			CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "hitpay_order` (
			  `order_id` int(11) NOT NULL,
			  `response` TEXT
			) ENGINE=MyISAM DEFAULT COLLATE=utf8_general_ci;");
                $this->load->model('setting/event');
                
                $eventData = [
                    'code' => 'payment_hitpay',
                    'description' => 'To display HitPay payment information',
                    'trigger' => 'admin/view/sale/order_info/before',
                    'action' => 'extension/hitpay/payment/hitpay|order_info',
                    'status' => 1,
                    'sort_order' => 1
                ];
                $this->model_setting_event->addEvent($eventData);
	}

	public function uninstall() {
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "hitpay_order`;");
                $this->load->model('setting/event');
                $this->model_setting_event->deleteEventByCode('payment_hitpay');
	}

	public function getOrder($order_id) {
		$qry = $this->db->query("SELECT * FROM `" . DB_PREFIX . "hitpay_order` WHERE `order_id` = '" . (int)$order_id . "' LIMIT 1");

		if ($qry->num_rows) {
			$order = $qry->row;
			return $order;
		} else {
			return false;
		}
	}
        
        public function getPaymentData($order_id)
        {
            $qry = $this->db->query('select response FROM ' . DB_PREFIX.'hitpay_order WHERE order_id='.(int)($order_id));
            if ($qry->num_rows) {
                    $row = $qry->row;
                    return $row['response'];
            } else {
                    return false;
            }
        }

        public function addPaymentData($order_id, $response)
        {
            $this->db->query("INSERT INTO `" . DB_PREFIX . "hitpay_order` SET `order_id` = '" . (int)$order_id . "',  `response` = '" . $this->db->escape($response) . "'");
        }

        public function updatePaymentData($order_id, $param, $value)
        {
            $metaData = $this->getPaymentData($order_id);
            if (!empty($metaData)) {
                $metaData = json_decode($metaData, true);
                $metaData[$param] = $value;
                $paymentData = json_encode($metaData);
                $this->db->query("UPDATE " . DB_PREFIX . "hitpay_order SET response = '" . $this->db->escape($paymentData) . "' WHERE order_id = '" . (int)$order_id . "'");
            }
        }

        public function deletePaymentData($order_id, $param)
        {
            $metaData = $this->getPaymentData($order_id);
            if (!empty($metaData)) {
                $metaData = json_decode($metaData, true);
                if (isset($metaData[$param])) {
                    unset($metaData[$param]);
                }
                $paymentData = json_encode($metaData);

                $this->db->query("UPDATE " . DB_PREFIX . "hitpay_order SET response = '" . $this->db->escape($paymentData) . "' WHERE order_id = '" . (int)$order_id . "'");
            }
        }
}