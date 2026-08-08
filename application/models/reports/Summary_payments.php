<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once("Summary_report.php");

class Summary_payments extends Summary_report
{
	protected function _get_data_columns()
	{
		return array(
			array('payment_type' => $this->lang->line('reports_payment_type')),
			array('report_count' => $this->lang->line('reports_count')),
			array('amount_due' => $this->lang->line('sales_amount_due'), 'sorter' => 'number_sorter'));
	}

	protected function _where(array $inputs)
	{
		if(empty($this->config->item('date_or_time_format')))
		{
			$this->db->where('DATE(sales.sale_time) BETWEEN ' . $this->db->escape($inputs['start_date']) . ' AND ' . $this->db->escape($inputs['end_date']));
		}
		else
		{
			$this->db->where('sales.sale_time BETWEEN ' . $this->db->escape(rawurldecode($inputs['start_date'])) . ' AND ' . $this->db->escape(rawurldecode($inputs['end_date'])));
		}

		if($inputs['location_id'] != 'all')
		{
			$location_sub = $this->db->select('DISTINCT sale_id')
				->from('sales_items')
				->where('item_location', $inputs['location_id'])
				->get_compiled_select();
			$this->db->where("sales_payments.sale_id IN ($location_sub)");
		}

		if($inputs['sale_type'] == 'complete')
		{
			$this->db->where('sales.sale_status', COMPLETED);
			$this->db->group_start();
				$this->db->where('sales.sale_type', SALE_TYPE_POS);
				$this->db->or_where('sales.sale_type', SALE_TYPE_INVOICE);
				$this->db->or_where('sales.sale_type', SALE_TYPE_RETURN);
				$this->db->or_where('sales.sale_type', SALE_TYPE_CREDIT);
			$this->db->group_end();
		}
		elseif($inputs['sale_type'] == 'sales')
		{
			$this->db->where('sales.sale_status', COMPLETED);
			$this->db->group_start();
				$this->db->where('sales.sale_type', SALE_TYPE_POS);
				$this->db->or_where('sales.sale_type', SALE_TYPE_INVOICE);
				$this->db->or_where('sales.sale_type', SALE_TYPE_CREDIT);
			$this->db->group_end();
		}
		elseif($inputs['sale_type'] == 'quotes')
		{
			$this->db->where('sales.sale_status', SUSPENDED);
			$this->db->where('sales.sale_type', SALE_TYPE_QUOTE);
		}
		elseif($inputs['sale_type'] == 'work_orders')
		{
			$this->db->where('sales.sale_status', SUSPENDED);
			$this->db->where('sales.sale_type', SALE_TYPE_WORK_ORDER);
		}
		elseif($inputs['sale_type'] == 'canceled')
		{
			$this->db->where('sales.sale_status', CANCELED);
		}
		elseif($inputs['sale_type'] == 'returns')
		{
			$this->db->where('sales.sale_status', COMPLETED);
			$this->db->where('sales.sale_type', SALE_TYPE_RETURN);
		}
	}

	public function getData(array $inputs)
	{
		$this->db->select('sales_payments.payment_type, COUNT(DISTINCT sales_payments.sale_id) AS count, SUM(sales_payments.payment_amount) AS payment_amount');
		$this->db->from('sales_payments AS sales_payments');
		$this->db->join('sales AS sales', 'sales.sale_id = sales_payments.sale_id');

		$this->_where($inputs);

		$this->db->group_by("payment_type");

		$payments = $this->db->get()->result_array();

		$gift_card_count = 0;
		$gift_card_amount = 0;
		foreach($payments as $key => $payment)
		{
			if(strstr($payment['payment_type'], $this->lang->line('sales_giftcard')) !== FALSE)
			{
				$gift_card_count  += $payment['count'];
				$gift_card_amount += $payment['payment_amount'];

				unset($payments[$key]);
			}
		}

		if($gift_card_count > 0)
		{
			$payments[] = array('payment_type' => $this->lang->line('sales_giftcard'), 'count' => $gift_card_count, 'payment_amount' => $gift_card_amount);
		}

		return $payments;
	}
}
