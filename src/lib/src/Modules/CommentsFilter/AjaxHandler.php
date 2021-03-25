<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class AjaxHandler extends Shield\Modules\BaseShield\AjaxHandler {

	protected function processNonAuthAjaxAction( string $action ) :array {

		switch ( $action ) {
			case 'comment_token'.Services::IP()->getRequestIp():
				$response = $this->ajaxExec_GenCommentToken();
				break;

			default:
				$response = parent::processAjaxAction( $action );
		}

		return $response;
	}

	private function ajaxExec_GenCommentToken() :array {
		$req = Services::Request();
		return [
			'success' => true,
			'token'   => ( new Shield\Modules\CommentsFilter\Token\Create() )
				->setMod( $this->getMod() )
				->run( $req->post( 'ts' ), $req->post( 'post_id' ) ),
		];
	}
}