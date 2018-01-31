<?php if ( $flags['can_wizard'] ) : ?>
	<p><strong><a href="<?php echo $hrefs['wizard'];?>" target="_blank">Launch Setup Wizard</a></strong>
	- <?php echo $strings['setup'];?></p>
<?php else : ?>
	<p><?php echo $strings['no_setup'];?>  :(</p>
<?php endif; ?>