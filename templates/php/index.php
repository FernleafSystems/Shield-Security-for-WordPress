<?php
if ( empty( $sFeatureInclude ) ) {
	$sFeatureInclude = 'feature-default';
}

$sBaseDirName = dirname(__FILE__).DIRECTORY_SEPARATOR;
include_once( $sBaseDirName . 'index_header.php' );
include_once( $sBaseDirName.$sFeatureInclude );
include_once( $sBaseDirName . 'index_footer.php' );