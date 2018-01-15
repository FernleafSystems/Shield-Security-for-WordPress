<?php
if ( isset( $slug ) ) {
	include_once( dirname( __FILE__ ).sprintf( '/module-help-%s.php', $slug ) );
}