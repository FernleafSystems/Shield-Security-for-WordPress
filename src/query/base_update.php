<?php

if ( class_exists( 'ICWP_WPSF_Query_BaseDelete', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base_query.php' );

abstract class ICWP_WPSF_Query_BaseUpdate extends ICWP_WPSF_Query_BaseQuery {

	/**
	 * @return string
	 */
	protected function getBaseQuery() {
		return "UPDATE `%s` WHERE %s %s";
	}

	/**
	 * Offset never applies to DELETE
	 * @return string
	 */
	protected function buildOffsetPhrase() {
		return '';
	}
}