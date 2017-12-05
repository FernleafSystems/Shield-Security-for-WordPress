<h2><?php echo $strings['um_current_user_settings']; ?>
	<small>(<?php echo $time_now; ?>)</small>
</h2>
<?php if ( true ) : ?>
	<div class="icwpAjaxTableContainer"><?php echo $sUserSessionsTable; ?></div>
<?php else : ?>
	<?php echo $strings['um_need_to_enable_user_management']; ?>
<?php endif; ?>