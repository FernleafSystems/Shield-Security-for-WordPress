<?php if ( $flags[ 'show_ads' ] ) : ?>
<div id="FooterWizardBanner" class="container-fluid">
	<div id="WizardBanner" class="row">
		<div class="col-lg-6 offset-lg-1 col-7">
			<h6 class="text-left">Exclusive Security Extras when you go Pro:</h6>
			   <p>Vulnerability Scanner; Plugins Hack Guard; More Frequent Scans; Email Support +much more.</p>
		</div>
		<div class="col-lg-4 col-5">
			<a href="<?php echo $hrefs['goprofooter'];?>" target="_blank" class="btn btn-success">
				Get Even More Protection With Pro &rarr;</a>
		</div>
	</div>
</div>
<?php endif; ?>
<script type="text/javascript">
	jQuery( document ).ready( function () {
		jQuery( 'a.btn-icwp-wizard' ).tooltip( {
			placement: 'bottom',
			trigger: 'hover focus'
		} );
		jQuery( 'a.module .dashicons' ).tooltip( {
			placement: 'right',
			trigger: 'hover focus'
		} );
	} );
</script>