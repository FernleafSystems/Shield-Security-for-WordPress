<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Databases\Comments;

use FernleafSystems\Wordpress\Plugin\Shield\Databases\Base;

class Select extends Base\Select {

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
}