<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Options;

trait OptsConsumer {

	/**
	 * @var Options
	 */
	private $oOpts;

	/**
	 * @return Options
	 */
	public function getOpts() {
		return $this->oOpts;
	}

	/**
	 * @param Options $oOpts
	 * @return $this
	 */
	public function setOpts( $oOpts ) {
		$this->oOpts = $oOpts;
		return $this;
	}
}