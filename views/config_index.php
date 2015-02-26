<?php
$sFeatureInclude = 'feature-default';
if ( !empty( $icwp_sFeatureInclude ) ) {
	$sFeatureInclude = $icwp_sFeatureInclude;
}
$sBaseDirName = dirname(__FILE__).ICWP_DS;
include_once( $sBaseDirName.'config_header.php' );
include_once( $sBaseDirName.$sFeatureInclude.'.php' );
include_once( $sBaseDirName.'config_footer.php' );
