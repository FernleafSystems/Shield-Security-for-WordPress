<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\CommentsFilter;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Services\Services;

class AjaxHandler extends Shield\Modules\BaseShield\AjaxHandler {

	protected function getAjaxActionCallbackMap( bool $isAuth ) :array {
		return array_merge( parent::getAjaxActionCallbackMap( $isAuth ), [
			'comment_token'.Services::IP()->getRequestIp() => [ $this, 'ajaxExec_GenCommentToken' ],
		] );
	}

	public function ajaxExec_GenCommentToken() :array {
		$req = Services::Request();
		return [
			'success' => true,
			'token'   => ( new Shield\Modules\CommentsFilter\Token\Create() )
				->setMod( $this->getMod() )
				->run( $req->post( 'ts' ), $req->post( 'post_id' ) ),
		];
	}
}