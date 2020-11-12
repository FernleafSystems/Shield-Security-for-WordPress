<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

abstract class BaseReporter {

	use ModConsumer;

	/**
	 * @var ReportVO
	 */
	private $rep;

	/**
	 * @return array
	 */
	public function build() {
		return [];
	}

	/**
	 * @return ReportVO
	 */
	public function getReport() {
		return $this->rep;
	}

	/**
	 * @param ReportVO $rep
	 * @return $this
	 */
	public function setReport( ReportVO $rep ) {
		$this->rep = $rep;
		return $this;
	}
}