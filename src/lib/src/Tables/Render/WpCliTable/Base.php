<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\Render\WpCliTable;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\Build\BaseBuild;

class Base {

	/**
	 * @var BaseBuild
	 */
	private $oDataBuilder;

	/**
	 * @return BaseBuild|mixed
	 */
	public function getDataBuilder() {
		return $this->oDataBuilder;
	}

	/**
	 * @param BaseBuild $oDataBuilder
	 * @return $this
	 */
	public function setDataBuilder( $oDataBuilder ) {
		$this->oDataBuilder = $oDataBuilder;
		return $this;
	}
}