<?php if ( $has_premium_support ) : ?>

<?php else : ?>
<style>
	#wpcontent {
		padding-left: 0;
	}

	.update-nag {
		display:none;
	}

	#ShieldCentralFrame {
		width: 99%;
		margin: 0.4%;
		overflow: hidden;
		border: 1px solid #aaaaaa;
	}
</style>
	<div class="row-fluid">
		<div class="span12 tcenter">
			<iframe src="<?php echo $aHrefs[ 'iframe_url' ]; ?>"
					id="ShieldCentralFrame"
					scrolling="no"
					frameborder="0"
					width="100%"
					height="4000px"
					style="overflow:hidden;"
			></iframe>
		</div>
	</div>

<?php endif;
