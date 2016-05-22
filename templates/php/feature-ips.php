<?php
include_once( $sBaseDirName . 'feature-default.php' );
if ( isset( $bFeatureEnabled ) && $bFeatureEnabled ) {
	include_once( $sBaseDirName . 'snippets'.DIRECTORY_SEPARATOR.'ip_lists.php' );
}
