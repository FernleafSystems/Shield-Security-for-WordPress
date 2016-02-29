<?php if ( $has_premium_support ) : ?>

	<div class="row">
		<div class="span12">
			<h2>Shield Pro Support</h2>
			<p>
				You currently have full access to Shield Pro and support from the iControlWP team.
			</p>
			<p>
				<a href="<?php echo $aHrefs['support_centre_sso']; ?>" class="btn" target="_blank">Launch My Support Centre</a>
			</p>
		</div>
	</div>

<?php else : ?>

	<div class="row">
		<div class="span12">
			<h2>Shield Free</h2>
			<p>
				We provide Shield as-is without business-grade support.

				You have access to all the features in the plugin without restriction, but if you need support, or you have questions,
				we ask that you go Pro and support future advanced of your WordPress site security.
			</p>
			<p>
				<a href="<?php echo $aHrefs['shield_pro_url']; ?>" target="_blank">Go Pro!</a>
			</p>
		</div>
	</div>

<?php endif;
