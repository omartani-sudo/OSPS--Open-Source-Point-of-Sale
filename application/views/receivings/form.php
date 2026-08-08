<div id="required_fields_message"><?php echo $this->lang->line('common_fields_required_message'); ?></div>

<ul id="error_message_box" class="error_message_box"></ul>

<?php echo form_open("receivings/save/".$receiving_info['receiving_id'], array('id'=>'receivings_edit_form', 'class'=>'form-horizontal')); ?>
	<fieldset id="receiving_basic_info">
		<div class="form-group form-group-sm">
			<?php echo form_label($this->lang->line('receivings_receipt_number'), 'supplier', array('class'=>'control-label col-xs-3')); ?>
			<?php echo anchor('receivings/receipt/'.$receiving_info['receiving_id'], 'RECV ' . $receiving_info['receiving_id'], array('target'=>'_blank', 'class'=>'control-label col-xs-8', "style"=>"text-align:left"));?>
		</div>
		
		<div class="form-group form-group-sm">
			<?php echo form_label($this->lang->line('receivings_date'), 'date', array('class'=>'control-label col-xs-3')); ?>
			<div class='col-xs-8'>
				<?php echo form_input(array('name'=>'date','value'=>date($this->config->item('dateformat') . ' ' . $this->config->item('timeformat'), strtotime($receiving_info['receiving_time'])), 'id'=>'datetime', 'class'=>'form-control input-sm'));?>
			</div>
		</div>
		
		<div class="form-group form-group-sm">
			<?php echo form_label($this->lang->line('receivings_supplier'), 'supplier', array('class'=>'control-label col-xs-3')); ?>
			<div class='col-xs-8'>
				<?php echo form_input(array('name' => 'supplier_name', 'value' => $selected_supplier_name, 'id' => 'supplier_name', 'class'=>'form-control input-sm'));?>
				<?php echo form_hidden('supplier_id', $selected_supplier_id);?>
			</div>
		</div>

		<div class="form-group form-group-sm">
			<?php echo form_label($this->lang->line('receivings_reference'), 'reference', array('class'=>'control-label col-xs-3')); ?>
			<div class='col-xs-8'>
				<?php echo form_input(array('name' => 'reference', 'value' => $receiving_info['reference'], 'id' => 'reference', 'class'=>'form-control input-sm'));?>
			</div>
		</div>
		
		<div class="form-group form-group-sm">
			<?php echo form_label($this->lang->line('receivings_employee'), 'employee', array('class'=>'control-label col-xs-3')); ?>
			<div class='col-xs-8'>
				<?php echo form_dropdown('employee_id', $employees, $receiving_info['employee_id'], 'id="employee_id" class="form-control"');?>
			</div>
		</div>
		
		<div class="form-group form-group-sm">
			<?php echo form_label($this->lang->line('receivings_comments'), 'comment', array('class'=>'control-label col-xs-3')); ?>
			<div class='col-xs-8'>
				<?php echo form_textarea(array('name'=>'comment','value'=>$receiving_info['comment'], 'id'=>'comment', 'class'=>'form-control input-sm'));?>
			</div>
		</div>
	</fieldset>
	
	<?php if(isset($items) && !empty($items)): ?>
	<fieldset id="receiving_items_info">
		<legend><?php echo $this->lang->line('receivings_items'); ?></legend>
		<table class="table table-striped table-bordered">
			<thead>
				<tr>
					<th><?php echo $this->lang->line('receivings_item_name'); ?></th>
					<th><?php echo $this->lang->line('receivings_cost'); ?></th>
					<th><?php echo $this->lang->line('receivings_quantity'); ?></th>
					<th><?php echo $this->lang->line('receivings_discount'); ?></th>
					<th><?php echo $this->lang->line('receivings_total'); ?></th>
					<?php if($items_module_allowed): ?>
					<th><?php echo $this->lang->line('common_edit'); ?></th>
					<?php endif; ?>
				</tr>
			</thead>
			<tbody>
				<?php
				foreach($items as $item)
				{
				?>
					<tr>
						<td><?php echo $item['description'] ? $item['description'] : $item['name']; ?></td>
						<td><?php echo to_currency($item['item_unit_price']); ?></td>
						<td><?php echo to_quantity_decimals($item['quantity_purchased']); ?></td>
						<td><?php echo to_currency_no_money($item['discount_percent']); ?>%</td>
						<td><?php echo to_currency(($item['item_unit_price'] * $item['quantity_purchased'] * $item['receiving_quantity']) - $item['discount_percent']); ?></td>
						<?php if($items_module_allowed): ?>
						<td>
							<?php echo form_open("receivings/edit_item_in_receiving/".$receiving_info['receiving_id'], array('class'=>'form-horizontal', 'id'=>'edit_item_'.$item['item_id'].'_'.$item['item_location'])); ?>
								<?php echo form_hidden('item_id', $item['item_id']); ?>
								<?php echo form_hidden('item_location', $item['item_location']); ?>
								<?php echo form_input(array('name'=>'price', 'class'=>'form-control input-sm', 'value'=>to_currency_no_money($item['item_unit_price'])));?>
								<?php echo form_input(array('name'=>'quantity', 'class'=>'form-control input-sm', 'value'=>to_quantity_decimals($item['quantity_purchased']))); ?>
								<?php echo form_input(array('name'=>'discount', 'class'=>'form-control input-sm', 'value'=>to_currency_no_money($item['discount_percent'])));?>
								<?php echo form_input(array('name'=>'description', 'class'=>'form-control input-sm', 'value'=>$item['description']));?>
								<?php echo form_input(array('name'=>'serialnumber', 'class'=>'form-control input-sm', 'value'=>$item['serialnumber']));?>
								<button type="submit" class="btn btn-primary btn-sm"><span class="glyphicon glyphicon-ok"></span></button>
							<?php echo form_close(); ?>
						</td>
						<?php endif; ?>
					</tr>
				<?php
				}
				?>
			</tbody>
		</table>
	</fieldset>
	<?php endif; ?>
 <?php echo form_close(); ?>
		
<script type="text/javascript">
$(document).ready(function()
{
	<?php $this->load->view('partial/datepicker_locale'); ?>
	
	$('#datetime').datetimepicker(
	{
		format: "<?php echo dateformat_bootstrap($this->config->item("dateformat")) . ' ' . dateformat_bootstrap($this->config->item("timeformat"));?>",
		startDate: "<?php echo date($this->config->item('dateformat') . ' ' . $this->config->item('timeformat'), mktime(0, 0, 0, 1, 1, 2010));?>",
		<?php
		$t = $this->config->item('timeformat');
		$m = $t[strlen($t)-1];
		if( strpos($this->config->item('timeformat'), 'a') !== false || strpos($this->config->item('timeformat'), 'A') !== false )
		{ 
		?>
			showMeridian: true,
		<?php 
		}
		else
		{
		?>
			showMeridian: false,
		<?php 
		}
		?>
		minuteStep: 1,
		autoclose: true,
		todayBtn: true,
		todayHighlight: true,
		bootcssVer: 3,
		language: "<?php echo current_language_code(); ?>"
	});

	var fill_value = function(event, ui)
	{
		event.preventDefault();
		$("input[name='supplier_id']").val(ui.item.value);
		$("input[name='supplier_name']").val(ui.item.label);
	};

	var autocompleter = $("#supplier_name").autocomplete(
	{
		source: '<?php echo site_url("suppliers/suggest"); ?>',
		minChars: 0,
		delay: 15, 
		cacheLength: 1,
		appendTo: '.modal-content',
		select: fill_value,
		focus: fill_value
    });

	$('button#delete').click(function()
	{
		dialog_support.hide();
		table_support.do_delete('<?php echo site_url('receivings'); ?>', <?php echo $receiving_info['receiving_id']; ?>);
	});

	// declare submitHandler as an object.. will be reused
	var submit_form = function()
	{
		$(this).ajaxSubmit(
		{
			success:function(response)
			{
				dialog_support.hide();
				table_support.handle_submit('<?php echo site_url('receivings'); ?>', response);
			},
			dataType:'json'
		});
	};

	$('#receivings_edit_form').validate($.extend(
	{
		submitHandler : function(form)
		{
			submit_form.call(form);
		}
	}, form_support.error));

	$('form[id^="edit_item_"]').validate({
		submitHandler: function(form)
		{
			$(form).ajaxSubmit(
			{
				success: function(response)
				{
					$.notify(response.message, { type: response.success ? 'success' : 'danger'});
					if(response.success)
					{
						table_support.handle_submit('<?php echo site_url('receivings'); ?>', response);
					}
				},
				dataType: 'json'
			});
		},
		rules:
		{
			price: 'required',
			quantity: 'required',
			discount: 'required'
		}
	});

});
</script>
