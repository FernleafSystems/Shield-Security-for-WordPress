<form method="post" action="<?php echo $sAction; ?>">
	<input type="hidden" value="<?php echo $sRedirectPage; ?>" name="redirect_page" id="redirect_page">
	<p><?php echo $sMessage; ?></p>
	<input type="submit" value="<?php echo $sButtonText; ?>" name="submit" class="button" style="float:left; margin-bottom:10px;">
	<div style="clear:both;"></div>
</form>