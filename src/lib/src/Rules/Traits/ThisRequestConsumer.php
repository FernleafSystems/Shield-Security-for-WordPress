<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Traits;

use FernleafSystems\Wordpress\Plugin\Shield\Request\ThisRequest;

trait ThisRequestConsumer {

	/**
	 * @var ThisRequest
	 */
	protected $req;

	/**
	 * @return $this
	 */
	public function setThisRequest( ThisRequest $request ) {
		$this->req = $request;
		return $this;
	}
}