<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Request;

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