<?php

if ( class_exists( 'ICWP_WPSF_Query_Comments_Select', false ) ) {
	return;
}

require_once( dirname( dirname( __FILE__ ) ).'/base/select.php' );

class ICWP_WPSF_Query_Comments_Select extends ICWP_WPSF_Query_BaseSelect {

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
	 * @return int|stdClass[]|ICWP_WPSF_CommentsEntryVO[]
	 */
	public function query() {
		return parent::query();
	}

	/**
	 * @return string
	 */
	protected function getVoName() {
		return 'ICWP_WPSF_CommentsEntryVO';
	}
}