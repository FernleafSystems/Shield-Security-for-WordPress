<?php
$sBaseDirName = dirname(__FILE__).DIRECTORY_SEPARATOR;
include_once( $sBaseDirName.'widgets'.DIRECTORY_SEPARATOR.'icwp_widgets.php' ); ?>

<div class="wrap">
	<div class="bootstrap-wpadmin <?php echo isset($sFeatureSlug) ? $sFeatureSlug : ''; ?> icwp-options-page">
		<div class="page-header">
			<h2>
				<span class="feature-headline">
					<a id="pluginlogo_32" class="header-icon32" href="http://icwp.io/2k" target="_blank"></a>
                    <?php echo $sPageTitle; ?>
				<?php if ( !empty( $sTagline ) ) : ?>
					<small class="feature-tagline">- <?php echo $sTagline; ?></small>
				<?php endif; ?>
				<?php if ( $help_video[ 'show' ] ) : ?>
                    <?php add_thickbox(); ?>
                    <a href="<?php echo $help_video['href']; ?>"
                       title="Help Video" class="thickbox btn btn-success">Help Video</a>
				<?php endif; ?>
			</h2>
		</div>
		<?php if ( isset( $bShowStateSummary ) && $bShowStateSummary ) : ?>
			<?php include_once( $sBaseDirName.'snippets'.DIRECTORY_SEPARATOR.'state_summary.php' ); ?>
		<?php endif; ?>
