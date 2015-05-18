<div class="updated icwp-admin-notice">
	<div id="IcwpTranslationsNotice">
		<form method="post" action="<?php echo $hrefs['form_action']; ?>">
			<input type="hidden" value="<?php echo $hrefs['redirect']; ?>" name="redirect_page" id="redirect_page">
			<h4 style="margin:10px 0 3px;">
				<?php echo $strings['like_to_help']; ?>
				<?php echo $strings['head_over_to']; ?> <a href="<?php echo $hrefs['translate']; ?>" target="_blank"><?php echo $strings['site_url']; ?></a>
			</h4>
			<input type="submit" value="<?php echo $strings['dismiss']; ?>" name="submit" class="button" style="float:left; margin-bottom:10px;">
			<div style="clear:both;"></div>
		</form>
	</div>
</div>