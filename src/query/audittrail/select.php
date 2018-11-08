<?php

if ( class_exists( 'ICWP_WPSF_Query_AuditTrail_Select', false ) ) {
	return;
}

require_once( dirname( dirname( __FILE__ ) ).'/base/select.php' );

class ICWP_WPSF_Query_AuditTrail_Select extends ICWP_WPSF_Query_BaseSelect {

	/**
	 * @return string[]
	 */
	public function getUniqueUsernames() {
		return $this->getUnique( 'wp_username' );
	}

	/**
	 * @param string $sContext
	 * @return $this
	 */
	public function filterByContext( $sContext ) {
		if ( !empty( $sContext ) && strtolower( $sContext ) != 'all' ) {
			$this->addWhereEquals( 'context', $sContext );
		}
		return $this;
	}

	/**
	 * @param string $sIp
	 * @return $this
	 */
	public function filterByIp( $sIp ) {
		if ( $this->loadIpService()->isValidIp( $sIp ) ) {
			$this->addWhereEquals( 'ip', trim( $sIp ) );
		}
		return $this;
	}

	/**
	 * @param string $sIp
	 * @return $this
	 */
	public function filterByNotIp( $sIp ) {
		if ( $this->loadIpService()->isValidIp( $sIp ) ) {
			$this->addWhere( 'ip', trim( $sIp ), '!=' );
		}
		return $this;
	}

	/**
	 * @param bool $bIsLoggedIn - true is logged-in, false is not logged-in
	 * @return $this
	 */
	public function filterByIsLoggedIn( $bIsLoggedIn ) {
		return $this->addWhere( 'wp_username', '', $bIsLoggedIn ? '!=' : '=' );
	}

	/**
	 * @param int $sUsername
	 * @return $this
	 */
	public function filterByUsername( $sUsername ) {
		return $this->addWhereEquals( 'wp_username', trim( $sUsername ) );
	}

	/**
	 * @param string $sContext
	 * @return ICWP_WPSF_AuditTrailEntryVO[]|stdClass[]
	 */
	public function forContext( $sContext ) {
		return $this->reset()
					->filterByContext( $sContext )
					->query();
	}

	/**
	 * @return int|stdClass[]|ICWP_WPSF_AuditTrailEntryVO[]
	 */
	public function query() {
		return parent::query();
	}

	/**
	 * @return string
	 */
	protected function getVoName() {
		return 'ICWP_WPSF_AuditTrailEntryVO';
	}
}