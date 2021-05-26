<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Rest;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Lib\Rest\Request\RequestVO;

trait RequestVoConsumer {

	/**
	 * @var RequestVO
	 */
	private $requestVO;

	/**
	 * @return RequestVO|mixed
	 */
	public function getRequestVO() {
		return $this->requestVO;
	}

	/**
	 * @param RequestVO|mixed $reqVO
	 * @return $this
	 */
	public function setRequestVO( $reqVO ) {
		$this->requestVO = $reqVO;
		return $this;
	}
}