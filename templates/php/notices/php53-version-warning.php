<div id="IcwpNoticePhpWarning">
	<form method="post" action="<?php echo $hrefs['form_action']; ?>">
		<input type="hidden" value="<?php echo $hrefs['redirect']; ?>" name="redirect_page" id="redirect_page">
		<h4 style="margin:10px 0 3px;">
			<?php echo $strings['your_php_version']; ?>
			<?php echo $strings['head_over_to']; ?>
		</h4>
		<p>
			<?php echo $strings['future_versions_not_supported']; ?>
			<br /><?php echo $strings['ask_host_to_upgrade']; ?>
			<br /><?php echo $strings['any_questions']; ?>
		</p>
		<p>
			<input type="submit" value="<?php echo $strings['dismiss']; ?>" name="submit" class="button">
			<a href="<?php echo $hrefs['forums']; ?>" target="_blank"><?php echo $strings['forums']; ?></a>
		</p>
		<div style="clear:both;"></div>
	</form>
</div>