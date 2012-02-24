( function($) {
	$(function(){
		$('table.affiliates-overview').hide();
		$('table.affiliates-overview.hits').visualize({type: 'line', width: '320px', height: '200px' });
		$('table.affiliates-overview.visits').visualize({type: 'line', width: '320px', height: '200px' });
		$('table.affiliates-overview.referrals').visualize(
			{
				type: 'line',
				width: '320px',
				height: '200px',
				colors: ['#009900','#333333','#0000ff','#ff0000']
			}
		);
	});
} ) ( jQuery );
