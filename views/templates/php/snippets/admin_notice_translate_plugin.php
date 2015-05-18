<div id="IcwpTranslationsNotice">
	<form method="post" action="<?php echo $sAction; ?>">
		<input type="hidden" value="<?php echo $sRedirectPage; ?>" name="redirect_page" id="redirect_page">
		<h4 style="margin:10px 0 3px;">
			<?php _wpsf_e( 'Would you like to help translate the WordPress Simple Firewall into your language?' ); ?>
			<?php printf( _wpsf__( 'Head over to: %s' ), '<a href="http://translate.icontrolwp.com" target="_blank">translate.icontrolwp.com</a>' ); ?>
		</h4>
		<input type="submit" value="<?php _wpsf_e( 'Dismiss this notice' ); ?>" name="submit" class="button" style="float:left; margin-bottom:10px;">
		<div style="clear:both;"></div>
	</form>
</div>