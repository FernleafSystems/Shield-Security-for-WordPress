<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Comments;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base\BaseSelect;

class Select extends BaseSelect {

	/**
	 * @param string $sToken
	 * @param string $nPostId
	 * @param string $sIp
	 * @return EntryVO|null
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
			/** @var EntryVO $oToken */
			$oToken = $this->first();
		}

		return $oToken;
	}

	/**
	 * @return EntryVO
	 */
	public function getVo() {
		return Handler::getVo();
	}
}