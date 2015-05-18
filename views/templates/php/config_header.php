<?php
$sBaseDirName = dirname(__FILE__).ICWP_DS;
include_once( $sBaseDirName . 'icwp_options_helper.php' );
include_once( $sBaseDirName.'widgets'.ICWP_DS.'icwp_widgets.php' );
?>

<div class="wrap">
	<div class="bootstrap-wpadmin <?php echo isset($sFeatureSlug) ? $sFeatureSlug : ''; ?> icwp-options-page">
		<div class="row">
			<div class="span12">
				<?php include_once( $sBaseDirName.'snippets'.ICWP_DS.'state_summary.php' ); ?>
			</div>
		</div>
<?php
printOptionsPageHeader( $sFeatureName );
