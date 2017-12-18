<div>
	<div class="notice-icon">
		<span class="dashicons dashicons-shield"></span>&nbsp;
	</div>

	<div class="notice-content">
		<h3><?php echo $strings['title'];?>!</h3>
		<?php if ( $flags['can_wizard'] ) : ?>
			<p><strong><a href="<?php echo $hrefs['wizard'];?>" target="_blank">Launch Setup Wizard</a></strong>
			- <?php echo $strings['setup'];?></p>
		<?php else : ?>
			<p><?php echo $strings['no_setup'];?></p>
		<?php endif; ?>
	</div>
</div>

<style type="text/css">
	.notice-icon {
		float: left;
		height: 43px;
		line-height: 176px;
		min-width: 64px;
		padding: 10px 0;
	}
	.notice-icon-right {
		float: right;
		width: 74px;
	}
	.notice-content {
	}
	.notice-content h3 {
		margin-top: 5px;
	}
	.notice-content h3,
	.notice-content p {
		margin-left: 62px;
	}
	.notice-icon .dashicons {
		display: inline-block;
		font-size: 48px;
	}
	.notice-content p a {
		text-shadow: none;
		font-weight: bold;
	}
</style>