<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Traits;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility\ParamsVO;

trait ParamsConsumer {

	/**
	 * @var ParamsVO
	 */
	public $p = null;

	protected function setParams( array $params ) {
		$this->p = ( new ParamsVO( $this->getParamsDef() ) )->applyFromArray( $params );
	}
}