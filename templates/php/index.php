<?php
if ( empty( $sFeatureInclude ) ) {
	$sFeatureInclude = 'feature-default';
}

$sBaseDirName = dirname(__FILE__).DIRECTORY_SEPARATOR;
include_once( $sBaseDirName . 'index_header.php' );
include_once( $sBaseDirName.$sFeatureInclude );
include_once( $sBaseDirName . 'index_footer.php' );

if ( $help_video[ 'show' ] ) {
	include_once( $sBaseDirName . 'snippets'.DIRECTORY_SEPARATOR.'help_video_player.php' );
}
