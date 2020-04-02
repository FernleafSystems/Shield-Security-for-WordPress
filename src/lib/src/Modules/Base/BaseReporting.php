<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports\ReportVO;

abstract class BaseReporting {

	use ModConsumer;

	/**
	 * @var int|null
	 */
	protected $nFromTS;

	/**
	 * @var int|null
	 */
	protected $nUntilTS;

	/**
	 * @return array
	 */
	public function buildAlerts() {
		return [];
	}

	/**
	 * @param ReportVO $oRep
	 * @return array
	 */
	public function buildInfo( ReportVO $oRep ) {
		return [];
	}

	/**
	 * @return int|null
	 */
	public function getFromTS() {
		return is_int( $this->nFromTS ) ? (int)max( 0, $this->nFromTS ) : null;
	}

	/**
	 * @return int|null
	 */
	public function getUntilTS() {
		return is_int( $this->nUntilTS ) ? (int)max( 0, $this->nUntilTS ) : null;
	}

	/**
	 * @param int|null $nUntilTS
	 * @return $this
	 */
	public function setUntilTS( $nUntilTS ) {
		$this->nUntilTS = $nUntilTS;
		return $this;
	}

	/**
	 * @param int|null $nFromTS
	 * @return $this
	 */
	public function setFromTS( $nFromTS ) {
		$this->nFromTS = $nFromTS;
		return $this;
	}
}