<?php
$sBaseDirName = dirname(__FILE__).ICWP_DS;
include_once( $sBaseDirName . 'icwp_options_helper.php' );
include_once( $sBaseDirName.'widgets'.ICWP_DS.'icwp_widgets.php' );
?>

<div class="wrap">
	<div class="bootstrap-wpadmin <?php echo isset($sFeatureSlug) ? $sFeatureSlug : ''; ?> icwp-options-page">
		<div class="page-header">
			<h2>
				<a id="pluginlogo_32" class="header-icon32" href="http://icwp.io/2k" target="_blank"></a>
				<?php echo $sPageTitle; ?>
			</h2>
		</div>
		<div class="row">
			<div class="span12">
				<?php include_once( $sBaseDirName.'snippets'.ICWP_DS.'state_summary.php' ); ?>
			</div>
		</div>
