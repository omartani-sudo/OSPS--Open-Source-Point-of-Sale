<?php if (!defined('BASEPATH')) exit('No direct script access allowed');

/**
 * Receiving class
 */

class Receiving extends CI_Model
{
	public function get_info($receiving_id)
	{
		$this->db->from('receivings');
		$this->db->join('people', 'people.person_id = receivings.supplier_id', 'LEFT');
		$this->db->join('suppliers', 'suppliers.person_id = receivings.supplier_id', 'LEFT');
		$this->db->where('receiving_id', $receiving_id);

		return $this->db->get();
	}

	public function get_receiving_by_reference($reference)
	{
		$this->db->from('receivings');
		$this->db->where('reference', $reference);

		return $this->db->get();
	}

	public function is_valid_receipt($receipt_receiving_id)
	{
		if(!empty($receipt_receiving_id))
		{
			//RECV #
			$pieces = explode(' ', $receipt_receiving_id);

			if(count($pieces) == 2 && preg_match('/(RECV|KIT)/', $pieces[0]))
			{
				return $this->exists($pieces[1]);
			}
			else
			{
				return $this->get_receiving_by_reference($receipt_receiving_id)->num_rows() > 0;
			}
		}

		return FALSE;
	}

	public function exists($receiving_id)
	{
		$this->db->from('receivings');
		$this->db->where('receiving_id', $receiving_id);

		return ($this->db->get()->num_rows() == 1);
	}

	public function update($receiving_data, $receiving_id)
	{
		$this->db->where('receiving_id', $receiving_id);

		return $this->db->update('receivings', $receiving_data);
	}

	public function save($items, $supplier_id, $employee_id, $comment, $reference, $payment_type, $receiving_id = FALSE)
	{
		if(count($items) == 0)
		{
			return -1;
		}

		$receivings_data = array(
			'receiving_time' => date('Y-m-d H:i:s'),
			'supplier_id' => $this->Supplier->exists($supplier_id) ? $supplier_id : NULL,
			'employee_id' => $employee_id,
			'payment_type' => $payment_type,
			'comment' => $comment,
			'reference' => $reference
		);

		//Run these queries as a transaction, we want to make sure we do all or nothing
		$this->db->trans_start();

		$this->db->insert('receivings', $receivings_data);
		$receiving_id = $this->db->insert_id();

		foreach($items as $line=>$item)
		{
			$cur_item_info = $this->Item->get_info($item['item_id']);

			$receivings_items_data = array(
				'receiving_id' => $receiving_id,
				'item_id' => $item['item_id'],
				'line' => $item['line'],
				'description' => $item['description'],
				'serialnumber' => $item['serialnumber'],
				'quantity_purchased' => $item['quantity'],
				'receiving_quantity' => $item['receiving_quantity'],
				'discount_percent' => $item['discount'],
				'item_cost_price' => $cur_item_info->cost_price,
				'item_unit_price' => $item['price'],
				'item_location' => $item['item_location']
			);

			$this->db->insert('receivings_items', $receivings_items_data);

			$items_received = $item['receiving_quantity'] != 0 ? $item['quantity'] * $item['receiving_quantity'] : $item['quantity'];

			// update cost price, if changed AND is set in config as wanted
			if($cur_item_info->cost_price != $item['price'] && $this->config->item('receiving_calculate_average_price') != FALSE)
			{
				$this->Item->change_cost_price($item['item_id'], $items_received, $item['price'], $cur_item_info->cost_price);
			}

			//Update stock quantity
			$item_quantity = $this->Item_quantity->get_item_quantity($item['item_id'], $item['item_location']);
			$this->Item_quantity->save(array('quantity' => $item_quantity->quantity + $items_received, 'item_id' => $item['item_id'],
											  'location_id' => $item['item_location']), $item['item_id'], $item['item_location']);

			$recv_remarks = 'RECV ' . $receiving_id;
			$inv_data = array(
				'trans_date' => date('Y-m-d H:i:s'),
				'trans_items' => $item['item_id'],
				'trans_user' => $employee_id,
				'trans_location' => $item['item_location'],
				'trans_comment' => $recv_remarks,
				'trans_inventory' => $items_received
			);

			$this->Inventory->insert($inv_data);

			$supplier = $this->Supplier->get_info($supplier_id);
		}

		$this->db->trans_complete();

		if($this->db->trans_status() === FALSE)
		{
			return -1;
		}

		return $receiving_id;
	}

	public function delete_list($receiving_ids, $employee_id, $update_inventory = TRUE)
	{
		$success = TRUE;

		// start a transaction to assure data integrity
		$this->db->trans_start();

		foreach($receiving_ids as $receiving_id)
		{
			$success &= $this->delete($receiving_id, $employee_id, $update_inventory);
		}

		// execute transaction
		$this->db->trans_complete();

		$success &= $this->db->trans_status();

		return $success;
	}

	public function delete($receiving_id, $employee_id, $update_inventory = TRUE)
	{
		// start a transaction to assure data integrity
		$this->db->trans_start();

		if($update_inventory)
		{
			// defect, not all item deletions will be undone??
			// get array with all the items involved in the sale to update the inventory tracking
			$items = $this->get_receiving_items($receiving_id)->result_array();
			foreach($items as $item)
			{
				// create query to update inventory tracking
				$inv_data = array(
					'trans_date' => date('Y-m-d H:i:s'),
					'trans_items' => $item['item_id'],
					'trans_user' => $employee_id,
					'trans_comment' => 'Deleting receiving ' . $receiving_id,
					'trans_location' => $item['item_location'],
					'trans_inventory' => $item['quantity_purchased'] * -1
				);
				// update inventory
				$this->Inventory->insert($inv_data);

				// update quantities
				$this->Item_quantity->change_quantity($item['item_id'], $item['item_location'], $item['quantity_purchased'] * -1);
			}
		}

		// delete all items
		$this->db->delete('receivings_items', array('receiving_id' => $receiving_id));
		// delete sale itself
		$this->db->delete('receivings', array('receiving_id' => $receiving_id));

		// execute transaction
		$this->db->trans_complete();
	
		return $this->db->trans_status();
	}

	public function get_receiving_items($receiving_id)
	{
		$this->db->from('receivings_items');
		$this->db->where('receiving_id', $receiving_id);

		return $this->db->get();
	}
	
	public function get_supplier($receiving_id)
	{
		$this->db->from('receivings');
		$this->db->where('receiving_id', $receiving_id);

		return $this->Supplier->get_info($this->db->get()->row()->supplier_id);
	}

	public function get_payment_options()
	{
		return array(
			$this->lang->line('sales_cash') => $this->lang->line('sales_cash'),
			$this->lang->line('sales_check') => $this->lang->line('sales_check'),
			$this->lang->line('sales_debit') => $this->lang->line('sales_debit'),
			$this->lang->line('sales_credit') => $this->lang->line('sales_credit')
		);
	}

	public function search($search, $filters, $rows = 0, $limit_from = 0, $sort = 'receiving_time', $order = 'desc', $count_only = FALSE)
	{
		if($count_only == TRUE)
		{
			$this->db->select('COUNT(DISTINCT receivings.receiving_id) as count');
		}
		else
		{
			$this->db->select('
				receivings.receiving_id,
				MAX(receivings.receiving_time) AS receiving_time,
				MAX(receivings.payment_type) AS payment_type,
				MAX(suppliers.company_name) AS company_name,
				MAX(people.first_name) AS first_name,
				MAX(people.last_name) AS last_name
			');
		}

		$this->db->from('receivings AS receivings');
		$this->db->join('people', 'people.person_id = receivings.supplier_id', 'LEFT');
		$this->db->join('suppliers', 'suppliers.person_id = receivings.supplier_id', 'LEFT');

		$this->db->group_start();
			$this->db->or_like('people.first_name', $search);
			$this->db->or_like('people.last_name', $search);
			$this->db->or_like('suppliers.company_name', $search);
			$this->db->or_like('receivings.reference', $search);
			$this->db->or_like('receivings.payment_type', $search);
		$this->db->group_end();

		if(empty($this->config->item('date_or_time_format')))
		{
			$this->db->where('DATE_FORMAT(receivings.receiving_time, "%Y-%m-%d") BETWEEN ' . $this->db->escape($filters['start_date']) . ' AND ' . $this->db->escape($filters['end_date']));
		}
		else
		{
			$this->db->where('receivings.receiving_time BETWEEN ' . $this->db->escape(rawurldecode($filters['start_date'])) . ' AND ' . $this->db->escape(rawurldecode($filters['end_date'])));
		}

		if($count_only == TRUE)
		{
			return $this->db->get()->row()->count;
		}

		$this->db->group_by('receivings.receiving_id');

		$this->db->order_by($sort, $order);

		if($rows > 0)
		{
			$this->db->limit($rows, $limit_from);
		}

		return $this->db->get();
	}

	public function get_found_rows($search, $filters)
	{
		return $this->search($search, $filters, 0, 0, 'receiving_time', 'desc', TRUE);
	}

	public function get_payments_summary($search, $filters)
	{
		$this->db->select('payment_type, COUNT(receiving_id) AS count, SUM(receiving_id) AS amount');
		$this->db->from('receivings');

		if(empty($this->config->item('date_or_time_format')))
		{
			$this->db->where('DATE_FORMAT(receiving_time, "%Y-%m-%d") BETWEEN ' . $this->db->escape($filters['start_date']) . ' AND ' . $this->db->escape($filters['end_date']));
		}
		else
		{
			$this->db->where('receiving_time BETWEEN ' . $this->db->escape(rawurldecode($filters['start_date'])) . ' AND ' . $this->db->escape(rawurldecode($filters['end_date'])));
		}

		$this->db->group_by('payment_type');

		return $this->db->get()->result_array();
	}

	public function update_items($receiving_id, $items, $employee_id)
	{
		$this->db->trans_start();

		$old_items = $this->get_receiving_items($receiving_id)->result_array();

		foreach($old_items as $old_item)
		{
			$found = FALSE;
			foreach($items as $new_item)
			{
				if($new_item['item_id'] == $old_item['item_id'] && $new_item['item_location'] == $old_item['item_location'])
				{
					$found = TRUE;
					$quantity_diff = $new_item['quantity'] - $old_item['quantity_purchased'];
					$items_received = $new_item['receiving_quantity'] != 0 ? $quantity_diff * $new_item['receiving_quantity'] : $quantity_diff;

					if($items_received != 0)
					{
						$item_quantity = $this->Item_quantity->get_item_quantity($new_item['item_id'], $new_item['item_location']);
						$this->Item_quantity->save(array('quantity' => $item_quantity->quantity + $items_received, 'item_id' => $new_item['item_id'],
							'location_id' => $new_item['item_location']), $new_item['item_id'], $new_item['item_location']);

						$inv_data = array(
							'trans_date' => date('Y-m-d H:i:s'),
							'trans_items' => $new_item['item_id'],
							'trans_user' => $employee_id,
							'trans_location' => $new_item['item_location'],
							'trans_comment' => 'RECV ' . $receiving_id,
							'trans_inventory' => $items_received
						);
						$this->Inventory->insert($inv_data);
					}

					$cur_item_info = $this->Item->get_info($new_item['item_id']);
					if($cur_item_info->cost_price != $new_item['price'] && $this->config->item('receiving_calculate_average_price') != FALSE)
					{
						$this->Item->change_cost_price($new_item['item_id'], $new_item['quantity'], $new_item['price'], $cur_item_info->cost_price);
					}

					$receivings_items_data = array(
						'description' => $new_item['description'],
						'serialnumber' => $new_item['serialnumber'],
						'quantity_purchased' => $new_item['quantity'],
						'discount_percent' => $new_item['discount'],
						'item_unit_price' => $new_item['price']
					);
					$this->db->where('receiving_id', $receiving_id);
					$this->db->where('item_id', $new_item['item_id']);
					$this->db->where('item_location', $new_item['item_location']);
					$this->db->update('receivings_items', $receivings_items_data);
					break;
				}
			}

			if(!$found)
			{
				$items_removed = $old_item['receiving_quantity'] != 0 ? $old_item['quantity_purchased'] * $old_item['receiving_quantity'] : $old_item['quantity_purchased'];

				$item_quantity = $this->Item_quantity->get_item_quantity($old_item['item_id'], $old_item['item_location']);
				$this->Item_quantity->save(array('quantity' => $item_quantity->quantity - $items_removed, 'item_id' => $old_item['item_id'],
					'location_id' => $old_item['item_location']), $old_item['item_id'], $old_item['item_location']);

				$inv_data = array(
					'trans_date' => date('Y-m-d H:i:s'),
					'trans_items' => $old_item['item_id'],
					'trans_user' => $employee_id,
					'trans_location' => $old_item['item_location'],
					'trans_comment' => 'RECV ' . $receiving_id,
					'trans_inventory' => $items_removed * -1
				);
				$this->Inventory->insert($inv_data);

				$this->db->delete('receivings_items', array('receiving_id' => $receiving_id, 'item_id' => $old_item['item_id'], 'item_location' => $old_item['item_location']));
			}
		}

		foreach($items as $new_item)
		{
			$found = FALSE;
			foreach($old_items as $old_item)
			{
				if($new_item['item_id'] == $old_item['item_id'] && $new_item['item_location'] == $old_item['item_location'])
				{
					$found = TRUE;
					break;
				}
			}

			if(!$found)
			{
				$cur_item_info = $this->Item->get_info($new_item['item_id']);

				$receivings_items_data = array(
					'receiving_id' => $receiving_id,
					'item_id' => $new_item['item_id'],
					'line' => $new_item['line'],
					'description' => $new_item['description'],
					'serialnumber' => $new_item['serialnumber'],
					'quantity_purchased' => $new_item['quantity'],
					'receiving_quantity' => $new_item['receiving_quantity'],
					'discount_percent' => $new_item['discount'],
					'item_cost_price' => $cur_item_info->cost_price,
					'item_unit_price' => $new_item['price'],
					'item_location' => $new_item['item_location']
				);
				$this->db->insert('receivings_items', $receivings_items_data);

				$items_received = $new_item['receiving_quantity'] != 0 ? $new_item['quantity'] * $new_item['receiving_quantity'] : $new_item['quantity'];

				if($cur_item_info->cost_price != $new_item['price'] && $this->config->item('receiving_calculate_average_price') != FALSE)
				{
					$this->Item->change_cost_price($new_item['item_id'], $items_received, $new_item['price'], $cur_item_info->cost_price);
				}

				$item_quantity = $this->Item_quantity->get_item_quantity($new_item['item_id'], $new_item['item_location']);
				$this->Item_quantity->save(array('quantity' => $item_quantity->quantity + $items_received, 'item_id' => $new_item['item_id'],
					'location_id' => $new_item['item_location']), $new_item['item_id'], $new_item['item_location']);

				$inv_data = array(
					'trans_date' => date('Y-m-d H:i:s'),
					'trans_items' => $new_item['item_id'],
					'trans_user' => $employee_id,
					'trans_location' => $new_item['item_location'],
					'trans_comment' => 'RECV ' . $receiving_id,
					'trans_inventory' => $items_received
				);
				$this->Inventory->insert($inv_data);
			}
		}

		$this->db->trans_complete();

		return $this->db->trans_status();
	}

	/*
	We create a temp table that allows us to do easy report/receiving queries
	*/
	public function create_temp_table(array $inputs)
	{
		if(empty($inputs['receiving_id']))
		{
			if(empty($this->config->item('date_or_time_format')))
			{
				$where = 'WHERE DATE(receiving_time) BETWEEN ' . $this->db->escape($inputs['start_date']) . ' AND ' . $this->db->escape($inputs['end_date']);
			}
			else
			{
				$where = 'WHERE receiving_time BETWEEN ' . $this->db->escape(rawurldecode($inputs['start_date'])) . ' AND ' . $this->db->escape(rawurldecode($inputs['end_date']));
			}
		}
		else
		{
			$where = 'WHERE receivings_items.receiving_id = ' . $this->db->escape($inputs['receiving_id']);
		}

		$this->db->query('CREATE TEMPORARY TABLE IF NOT EXISTS ' . $this->db->dbprefix('receivings_items_temp') .
			' (INDEX(receiving_date), INDEX(receiving_time), INDEX(receiving_id))
			(
				SELECT 
					MAX(DATE(receiving_time)) AS receiving_date,
					MAX(receiving_time) AS receiving_time,
					receivings_items.receiving_id,
					MAX(comment) AS comment,
					MAX(item_location) AS item_location,
					MAX(reference) AS reference,
					MAX(payment_type) AS payment_type,
					MAX(employee_id) AS employee_id, 
					items.item_id,
					MAX(receivings.supplier_id) AS supplier_id,
					MAX(quantity_purchased) AS quantity_purchased,
					MAX(receivings_items.receiving_quantity) AS receiving_quantity,
					MAX(item_cost_price) AS item_cost_price,
					MAX(item_unit_price) AS item_unit_price,
					MAX(discount_percent) AS discount_percent,
					receivings_items.line,
					MAX(serialnumber) AS serialnumber,
					MAX(receivings_items.description) AS description,
					MAX(item_unit_price * quantity_purchased * receivings_items.receiving_quantity - item_unit_price * quantity_purchased * receivings_items.receiving_quantity * discount_percent / 100) AS subtotal,
					MAX(item_unit_price * quantity_purchased * receivings_items.receiving_quantity - item_unit_price * quantity_purchased * receivings_items.receiving_quantity * discount_percent / 100) AS total,
					MAX((item_unit_price * quantity_purchased * receivings_items.receiving_quantity - item_unit_price * quantity_purchased * receivings_items.receiving_quantity * discount_percent / 100) - (item_cost_price * quantity_purchased)) AS profit,
					MAX(item_cost_price * quantity_purchased * receivings_items.receiving_quantity ) AS cost
				FROM ' . $this->db->dbprefix('receivings_items') . ' AS receivings_items
				INNER JOIN ' . $this->db->dbprefix('receivings') . ' AS receivings
					ON receivings_items.receiving_id = receivings.receiving_id
				INNER JOIN ' . $this->db->dbprefix('items') . ' AS items
					ON receivings_items.item_id = items.item_id
				' . "
				$where
				" . '
				GROUP BY receivings_items.receiving_id, items.item_id, receivings_items.line
			)'
		);
	}
}
?>
