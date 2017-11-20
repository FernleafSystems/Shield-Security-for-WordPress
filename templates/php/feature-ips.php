<?php
include( $sBaseDirName.'feature-default.php' );
if ( isset( $bFeatureEnabled ) && $bFeatureEnabled ) {
	include( $sBaseDirName.'snippets'.DIRECTORY_SEPARATOR.'ip_lists.php' );
}