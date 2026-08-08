<?php $this->load->view("partial/header"); ?>

<script type="text/javascript">
$(document).ready(function()
{
	<?php $this->load->view('partial/bootstrap_tables_locale'); ?>

	table_support.init({
		resource: '<?php echo site_url($controller_name);?>',
		search_url: '<?php echo site_url($controller_name."/search_outstanding_credit");?>',
		headers: <?php echo $table_headers; ?>,
		pageSize: <?php echo $this->config->item('lines_per_page'); ?>,
		uniqueId: 'sale_id',
		queryParams: function() {
			return $.extend(arguments[0], {
				search: $("#search").val()
			});
		}
	});

	$("#search").keypress(function(event) {
		if (event.which == 13) {
			table_support.refresh();
		}
	});
});
</script>

<div id="title_bar" class="print_hide btn-toolbar">
	<button onclick="javascript:printdoc()" class='btn btn-info btn-sm pull-right'>
		<span class="glyphicon glyphicon-print">&nbsp</span><?php echo $this->lang->line('common_print'); ?>
	</button>
	<?php echo anchor("sales", '<span class="glyphicon glyphicon-shopping-cart">&nbsp</span>' . $this->lang->line('sales_register'), array('class'=>'btn btn-info btn-sm pull-right', 'id'=>'show_sales_button')); ?>
	<?php echo anchor("sales/manage", '<span class="glyphicon glyphicon-list-alt">&nbsp</span>' . $this->lang->line('sales_takings'), array('class'=>'btn btn-info btn-sm pull-right', 'id'=>'show_takings_button')); ?>
</div>

<div id="content_area_wrapper">
	<div id="content_area">
		<div id="toolbar">
			<div class="pull-left form-inline" role="toolbar">
				<?php echo form_input(array('name'=>'search', 'class'=>'form-control input-sm', 'id'=>'search', 'placeholder'=>$this->lang->line('common_search'))); ?>
				<button id="search_button" class="btn btn-default btn-sm">
					<span class="glyphicon glyphicon-search">&nbsp</span><?php echo $this->lang->line('common_search'); ?>
				</button>
			</div>
		</div>

		<div id="table_holder">
			<table id="table"></table>
		</div>
	</div>
</div>

<?php $this->load->view("partial/footer"); ?>
