<?php

if ( class_exists( 'ICWP_WPSF_Query_AuditTrail_Find', false ) ) {
	return;
}

require_once( dirname( __FILE__ ).'/base.php' );

class ICWP_WPSF_Query_AuditTrail_Find extends ICWP_WPSF_Query_Base {

	/**
	 * @var string
	 */
	protected $sTerm;

	/**
	 * @var bool
	 */
	protected $bResultsAsVo;

	public function __construct() {
		$this->init();
	}

	/**
	 * @return ICWP_WPSF_AuditTrailEntryVO[]
	 */
	public function all() {
		return $this->query_Search( $this->getTerm() );
	}

	/**
	 * @param string $sTerm
	 * @return ICWP_WPSF_AuditTrailEntryVO[]|array[]
	 */
	protected function query_Search( $sTerm ) {

		$sTerm = str_replace( '"', '', esc_sql( trim( $sTerm ) ) );

		$sQuery = "
			SELECT *
			FROM `%s`
			WHERE
				`wp_username` LIKE \"%%%s%%\"
				OR `message` LIKE \"%%%s%%\"
		";
		$sQuery = sprintf(
			$sQuery,
			$this->getTable(),
			$sTerm,
			$sTerm
		);

		$aData = $this->loadDbProcessor()
					  ->selectCustom( $sQuery, OBJECT_K );
		if ( $this->isResultsAsVo() ) {
			foreach ( $aData as $nKey => $oAudit ) {
				$aData[ $nKey ] = new ICWP_WPSF_AuditTrailEntryVO( $oAudit );
			}
		}
		return $aData;
	}

	protected function init() {
		require_once( dirname( __FILE__ ).'/ICWP_WPSF_AuditTrailEntryVO.php' );
	}

	/**
	 * @return string
	 */
	public function getTerm() {
		return $this->sTerm;
	}

	/**
	 * @return bool
	 */
	public function isResultsAsVo() {
		return $this->bResultsAsVo;
	}

	/**
	 * @param bool $bResultsAsVo
	 * @return $this
	 */
	public function setResultsAsVo( $bResultsAsVo ) {
		$this->bResultsAsVo = $bResultsAsVo;
		return $this;
	}

	/**
	 * @param string $sTerm
	 * @return $this
	 */
	public function setTerm( $sTerm ) {
		$this->sTerm = $sTerm;
		return $this;
	}
}