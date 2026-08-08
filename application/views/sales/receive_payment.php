<?php $this->load->view("partial/header"); ?>

<script type="text/javascript">
$(document).ready(function()
{
	$('#payment_form').validate({
		submitHandler: function(form)
		{
			$(form).ajaxSubmit({
				success: function(response)
				{
					if(response.success)
					{
						$.notify(response.message, { type: 'success' });
						setTimeout(function()
						{
							window.location.reload();
						}, 1000);
					}
					else
					{
						alert(response.message);
					}
				},
				dataType: 'json'
			});
		}
	});

	$('#payment_amount').focus();
});
</script>

<div id="title_bar" class="print_hide">
	<h1><?php echo $this->lang->line('sales_receive_payment'); ?></h1>
</div>

<div id="content_area_wrapper">
	<div id="content_area">
		<?php echo form_open('sales/add_credit_payment/'.$sale_id, array('id'=>'payment_form')); ?>

		<?php if(isset($error)): ?>
			<div class="alert alert-dismissible alert-danger"><?php echo $error; ?></div>
		<?php else: ?>

			<fieldset id="sale_info">
				<legend><?php echo $this->lang->line('sales_sale_info'); ?></legend>

				<div class="field_row">
					<div class="field_item">
						<?php echo form_label($this->lang->line('common_id'), 'sale_id'); ?>
						<?php echo $sale_info['sale_id']; ?>
					</div>
					<div class="field_item">
						<?php echo form_label($this->lang->line('sales_sale_time'), 'sale_time'); ?>
						<?php echo date($this->config->item('dateformat') . ' ' . $this->config->item('timeformat'), strtotime($sale_info['sale_time'])); ?>
					</div>
				</div>

				<div class="field_row">
					<div class="field_item">
						<?php echo form_label($this->lang->line('customers_customer'), 'customer'); ?>
						<?php echo !empty($customer_info->first_name) ? $customer_info->first_name . ' ' . $customer_info->last_name : ''; ?>
					</div>
				</div>

				<div class="field_row">
					<div class="field_item">
						<?php echo form_label($this->lang->line('sales_amount_due'), 'amount_due'); ?>
						<?php echo to_currency($sale_info['amount_due']); ?>
					</div>
					<div class="field_item">
						<?php echo form_label($this->lang->line('sales_payment_total'), 'payment_total'); ?>
						<?php echo to_currency($payment_total); ?>
					</div>
					<div class="field_item">
						<?php echo form_label($this->lang->line('sales_amount_outstanding'), 'amount_outstanding'); ?>
						<strong><?php echo to_currency($amount_due); ?></strong>
					</div>
				</div>
			</fieldset>

			<fieldset id="payment_info">
				<legend><?php echo $this->lang->line('sales_payment_info'); ?></legend>

				<div class="field_row">
					<div class="field_item">
						<?php echo form_label($this->lang->line('sales_payment_type'), 'payment_type'); ?>
						<?php echo form_dropdown('payment_type', $payment_options, '', 'id="payment_type" class="form-control input-sm"'); ?>
					</div>
				</div>

				<div class="field_row">
					<div class="field_item">
						<?php echo form_label($this->lang->line('sales_payment_amount'), 'payment_amount'); ?>
						<?php echo form_input(array(
							'name'=>'payment_amount',
							'id'=>'payment_amount',
							'class'=>'form-control input-sm',
							'value'=>$amount_due,
							'maxlength'=>10
						)); ?>
					</div>
				</div>
			</fieldset>

			<div class="button_block">
				<?php echo form_submit('submit', $this->lang->line('common_submit'), array('class'=>'btn btn-primary btn-sm')); ?>
				<?php echo anchor('sales/outstanding_credit', $this->lang->line('common_cancel'), array('class'=>'btn btn-default btn-sm')); ?>
			</div>

		<?php endif; ?>

		<?php echo form_close(); ?>
	</div>
</div>

<?php $this->load->view("partial/footer"); ?>
