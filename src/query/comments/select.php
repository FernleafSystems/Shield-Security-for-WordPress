<?php

if ( class_exists( 'ICWP_WPSF_Query_Comments_Select', false ) ) {
	return;
}

require_once( dirname( dirname( __FILE__ ) ).'/base/select.php' );

class ICWP_WPSF_Query_Comments_Select extends ICWP_WPSF_Query_BaseSelect {

	protected function customInit() {
		require_once( dirname( __FILE__ ).'/ICWP_WPSF_CommentsEntryVO.php' );
	}

	/**
	 * @param string $sList
	 * @return ICWP_WPSF_IpsEntryVO[]
	 */
	public function allFromList( $sList ) {
		return $this->reset()
					->addWhereEquals( 'list', $sList )
					->query();
	}

	/**
	 * @param string $sToken
	 * @param string $nPostId
	 * @param string $sIp
	 * @return ICWP_WPSF_CommentsEntryVO|null
	 */
	public function getTokenForPost( $sToken, $nPostId, $sIp = null ) {
		$oToken = null;

		if ( !empty( $sToken ) && !empty( $nPostId ) ) {
			$this->reset()
				 ->addWhereEquals( 'unique_token', $sToken )
				 ->addWhereEquals( 'post_id', (int)$nPostId );
			if ( !empty( $sIp ) ) {
				$this->addWhereEquals( 'ip', $sIp );
			}
			/** @var ICWP_WPSF_CommentsEntryVO $oToken */
			$oToken = $this->first();
		}

		return $oToken;
	}

	/**
	 * @return ICWP_WPSF_CommentsEntryVO[]|stdClass[]
	 */
	public function query() {

		$aData = parent::query();

		if ( $this->isResultsAsVo() ) {
			foreach ( $aData as $nKey => $oAudit ) {
				$aData[ $nKey ] = new ICWP_WPSF_CommentsEntryVO( $oAudit );
			}
		}

		return $aData;
	}
}