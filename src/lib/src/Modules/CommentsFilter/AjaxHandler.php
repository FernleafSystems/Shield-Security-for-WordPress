<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class AjaxHandler extends Shield\Modules\Base\AjaxHandlerShield {

	/**
	 * @param string $sAction
	 * @return array
	 */
	protected function processAjaxAction( $sAction ) {

		switch ( $sAction ) {
			case 'comment_token'.Services::IP()->getRequestIp():
				$aResponse = $this->ajaxExec_GenCommentToken();
				break;

			default:
				$aResponse = parent::processAjaxAction( $sAction );
		}

		return $aResponse;
	}

	/**
	 * @return array
	 */
	private function ajaxExec_GenCommentToken() {
		$oReq = Services::Request();
		$sToken = ( new Shield\Modules\CommentsFilter\Token\Create() )
			->setMod( $this->getMod() )
			->run( $oReq->post( 'ts' ), $oReq->post( 'post_id' ) );

		return [
			'success' => true,
			'token'   => $sToken,
		];
	}
}