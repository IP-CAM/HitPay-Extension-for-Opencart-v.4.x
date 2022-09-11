<?php
namespace Opencart\Catalog\Model\Extension\Hitpay\Payment;

class Hitpay extends \Opencart\System\Engine\Model {
	public function getMethod($address) {
		$this->load->language('extension/hitpay/payment/hitpay');

		$query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get('payment_hitpay_geo_zone_id') . "' AND country_id = '" . (int)$address['country_id'] . "' AND (zone_id = '" . (int)$address['zone_id'] . "' OR zone_id = '0')");
                
                $status = $this->config->get('payment_hitpay_status');
                if ($status) {
                    if (!$this->config->get('payment_hitpay_geo_zone_id')) {
                            $status = true;
                    } elseif ($query->num_rows) {
                            $status = true;
                    } else {
                            $status = false;
                    }
                }

		$method_data = array();
                
                $title = $this->config->get('payment_hitpay_title');
                $title = trim($title);
                if (empty($title)) {
                    $title = $this->language->get('text_title');
                }

		if ($status) {
  			$method_data = array(
				'code'       => 'hitpay',
				'title'      => $this->displayLogos($title, $this->config->get('payment_hitpay_logo')),
				'terms'      => '',
				'sort_order' => $this->config->get('payment_hitpay_sort_order')
			);
		}

		return $method_data;
	}
        
        public function displayLogos($title, $logos)
        {
            $customizedTitle = $title; 
            if (isset($_REQUEST['route']) && trim($_REQUEST['route']) == 'checkout/payment_method|getMethods') {
                $customizedTitle .= '<span>';
                foreach ($logos as $logo) {
                   $customizedTitle .= ' <img src="'. HTTP_SERVER .'extension/catalog/view/template/image/payment/hitpay/'.$logo.'.svg" alt="'.$logo.'" style="height:23px" />';
                }
                $customizedTitle .= '</span>';
            }
            
            return $customizedTitle;
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
        
        public function updateOrderData($order_id, $param, $value)
        {
            $this->db->query("UPDATE " . DB_PREFIX . "order SET {$param} = '" . $this->db->escape($value) . "' WHERE order_id = '" . (int)$order_id . "'");
        }
}