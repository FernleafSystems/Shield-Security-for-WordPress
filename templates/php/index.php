<?php
if ( empty( $sFeatureInclude ) ) {
	$sFeatureInclude = 'feature-default';
}

$sBaseDirName = dirname(__FILE__).ICWP_DS;
include_once( $sBaseDirName . 'index_header.php' );
include_once( $sBaseDirName.$sFeatureInclude.'.php' );
include_once( $sBaseDirName . 'index_footer.php' );
