<?php
namespace Opencart\Admin\Model\Extension\Hitpay\Payment;

class Hitpay extends \Opencart\System\Engine\Model {
        private $version = '1.2.0';
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
                    'action' => $this->getCompatibleRoute('extension/hitpay/payment/hitpay', 'order_info'),
                    'status' => 1,
                    'sort_order' => 1
                ];
                $this->model_setting_event->addEvent($eventData);
                
                $eventData = [
                    'code' => 'payment_hitpay_catalog_checkout_script',
                    'description' => 'To add javascript into the page',
                    'trigger' => 'catalog/view/checkout/checkout/after',
                    'action' => $this->getCompatibleRoute('extension/hitpay/payment/hitpay', 'checkout_after'),
                    'status' => 1,
                    'sort_order' => 5
                ];
                $this->model_setting_event->addEvent($eventData);
                
                $this->upgrade_100_120();
	}

	public function uninstall() {
		$this->db->query("DROP TABLE IF EXISTS `" . DB_PREFIX . "hitpay_order`;");
                $this->load->model('setting/event');
                $this->model_setting_event->deleteEventByCode('payment_hitpay');
                $this->model_setting_event->deleteEventByCode('payment_hitpay_catalog_checkout_script');
	}
        
        public function upgrade_100_120() {}

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
        
        public function isVersion402()
        {
            $status = true;

            if (VERSION == '4.0.0.0' || VERSION == '4.0.1.0' || VERSION == '4.0.1.1') {
                $status = false;
            }

            return $status;
        }

        public function getCompatibleRoute($route, $method)
        {
            if ($this->isVersion402()) {
                return $route.'.'.$method;
            } else {
                return $route.'|'.$method;
            }
        }
        
        public function getVersion()
        {
            return $this->version;
        }
        
        public function getSettingValue($key, $store_id = 0)
        {
            $query = $this->db->query("SELECT value FROM " . DB_PREFIX . "setting WHERE store_id = '" . (int)$store_id . "' AND `key` = '" . $this->db->escape($key) . "'");

            if ($query->num_rows) {
                    return $query->row['value'];
            } else {
                    return null;	
            }
	}
	
	public function editSettingValue($key = '', $value = '', $store_id = 0)
        {
            $exist = $this->getSettingValue($key);
            if ($exist) {
                    $this->db->query("UPDATE " . DB_PREFIX . "setting SET `value` = '" . $this->db->escape($value) . "' WHERE `key` = '" . $this->db->escape($key) . "' AND store_id = '" . (int)$store_id . "'");
            } else {
                    $this->db->query("INSERT INTO " . DB_PREFIX . "setting SET store_id = '" . (int)$store_id . "', `code` = 'payment_hitpay_outside', `key` = '" . $this->db->escape($key) . "', `value` = '" . $this->db->escape($value) . "'");
            }
	}
}