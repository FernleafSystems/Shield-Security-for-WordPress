<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Rest\Request;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

abstract class Process extends \FernleafSystems\Wordpress\Plugin\Core\Rest\Request\Process {

	use ModConsumer;

	/**
	 * @return RequestVO|mixed
	 */
	protected function getRequestVO() {
		/** @var RequestVO $req */
		$req = parent::getRequestVO();
		return $req->setMod( $this->mod() );
	}

	/**
	 * @return RequestVO
	 */
	protected function newReqVO() {
		return new RequestVO();
	}
}
