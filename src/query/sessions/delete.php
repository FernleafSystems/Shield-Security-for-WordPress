<?php

if ( class_exists( 'ICWP_WPSF_Query_Sessions_Delete', false ) ) {
	return;
}

require_once( dirname( dirname( __FILE__ ) ).'/base_delete.php' );

class ICWP_WPSF_Query_Sessions_Delete extends ICWP_WPSF_Query_BaseDelete {

	/**
	 * @param int $bOlderThan
	 * @return bool
	 */
	public function forExpiredLoginAt( $bOlderThan ) {
		return $this->reset()
					->addWhereOlderThan( $bOlderThan, 'logged_in_at' )
					->query();
	}

	/**
	 * @param int $bOlderThan
	 * @return bool
	 */
	public function forExpiredLoginIdle( $bOlderThan ) {
		return $this->reset()
					->addWhereOlderThan( $bOlderThan, 'last_activity_at' )
					->query();
	}

	/**
	 * @param string $sWpUsername
	 * @return false|int
	 */
	public function forUsername( $sWpUsername ) {
		return $this->reset()
					->addWhereEquals( 'wp_username', $sWpUsername )
					->query();
	}

	/**
	 * @return ICWP_WPSF_Query_Sessions_Count
	 */
	protected function getCounter() {
		require_once( dirname( __FILE__ ).'/count.php' );
		$oCounter = new ICWP_WPSF_Query_Sessions_Count();
		return $oCounter->setTable( $this->getTable() );
	}
}