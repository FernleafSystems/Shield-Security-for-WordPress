<?php if ( $has_premium_support ) : ?>

	<div class="row">
		<div class="span12">
			<h2>Shield Pro Support</h2>
			<p>
				You currently have full access to Shield Pro and support from the iControlWP team.
			</p>
			<p>
				Thank you for supporting us!
			</p>
			<p>
				<a href="<?php echo $aHrefs['support_centre_sso']; ?>" class="btn" target="_blank">Launch My Support Centre</a>
			</p>
		</div>
	</div>

<?php else : ?>

	<div class="row">
		<div class="span6">
			<h2>Shield Free</h2>
			<p>
				We offer Shield as-is without business-grade support.
			</p>
			<p>
				<span style="font-style: italic">You have access to all the features in the plugin without any restriction.</span>
			</p>
			<p>
				But if you manage many websites, you need support, or you just want to give back,
				we ask that you go Pro and support future development of your WordPress site security.
			</p>
			<p>
				We currently provide Shield Pro to all iControlWP clients.  This means that you not only benefit from
				centralized WordPress management and daily, automated offsite backups, you also get the gain from the huge
				advantages of central WordPress security.
			</p>
			<p>
				With your ongoing support, we plan to make WordPress security centralized and easier than ever - no more
				individual on-site plugin management. You'll be able to finally control website security from a central control panel.
			</p>
			<p>
				It's coming... you can be a part of the journey and be more secure than ever.
			</p>
			<p style="font-weight: bolder;">
				Dare to believe in better. We do! :D
			</p>
			<p>
				<a href="<?php echo $aHrefs['shield_pro_url']; ?>" class="btn btn-success btn-large" target="_blank">Go Pro!</a>
				<a href="<?php echo $aHrefs['shield_pro_more_info_url']; ?>" class="btn btn-large" target="_blank">Learn More</a>
			</p>
		</div>
	</div>

<?php endif;
