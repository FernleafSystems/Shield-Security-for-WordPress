<?php
$sFeatureInclude = 'feature-default';
if ( !empty( $sFeatureInclude ) ) {
	$sFeatureInclude = $sFeatureInclude;
}
$sBaseDirName = dirname(__FILE__).ICWP_DS;
include_once( $sBaseDirName.'config_header.php' );
include_once( $sBaseDirName.$sFeatureInclude.'.php' );
include_once( $sBaseDirName.'config_footer.php' );
