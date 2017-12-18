<?php
$sBaseDirName = dirname( __FILE__ ).DIRECTORY_SEPARATOR;
include_once( $sBaseDirName.'widgets'.DIRECTORY_SEPARATOR.'icwp_widgets.php' ); ?>
<div class="wrap">
	<div class="bootstrap-wpadmin <?php echo isset( $sFeatureSlug ) ? $sFeatureSlug : ''; ?> icwp-options-page">

		<div class="row">
			<div class="span11">
				<div class="page-header">
					<h2>
						<span class="feature-headline">
							<a id="pluginlogo_32" class="header-icon32" href="http://icwp.io/2k" target="_blank"></a>
							<?php echo $sPageTitle; ?>
							<?php if ( !empty( $sTagline ) ) : ?>
								<small class="feature-tagline">- <?php echo $sTagline; ?></small>
							<?php endif; ?>
							<?php if ( $help_video[ 'show' ] ) : ?>
								<a href="#"
								   class="btn btn-success"
								   data-featherlight="#<?php echo $help_video[ 'display_id' ]; ?>">Help Video</a>
							<?php endif; ?>
					</h2>
				</div>
				<?php
				if ( empty( $sFeatureInclude ) ) {
					$sFeatureInclude = 'feature-default';
				}
				include( $sBaseDirName.$sFeatureInclude );?>
			</div>
			<div class="span1">
				<?php if ( isset( $flags[ 'show_summary' ] ) && $flags[ 'show_summary' ] ) : ?>
					<?php include_once( $sBaseDirName.'snippets'.DIRECTORY_SEPARATOR.'state_summary.php' ); ?>
				<?php endif; ?>
			</div>
		</div>