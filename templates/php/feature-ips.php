<?php
include_once( $sBaseDirName . 'feature-default.php' );
if ( isset( $bFeatureEnabled ) && $bFeatureEnabled ) {
	include_once( $sBaseDirName . 'snippets'.ICWP_DS.'ip_lists.php' );
}
