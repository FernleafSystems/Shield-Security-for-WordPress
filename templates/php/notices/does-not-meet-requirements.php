<div class="error odp-admin-notice">
	<ul style="list-style: inside none disc;">
		<?php foreach ( $strings['requirements'] as $req ) : ?>
			<li><?php echo $req; ?></li>
		<?php endforeach; ?>
	</ul>
	<a href="<?php echo $hrefs['more_information']; ?>" target="_blank"><?php echo $strings['more_information']; ?></a>
</div>