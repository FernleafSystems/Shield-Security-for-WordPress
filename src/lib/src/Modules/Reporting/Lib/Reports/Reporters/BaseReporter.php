<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports\Reporters;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Traits\AuthNotRequired;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports\ReportVO;

abstract class BaseReporter {

	use AuthNotRequired;
	use PluginControllerConsumer;

	public const TYPE = Constants::REPORT_TYPE_INFO;

	/**
	 * @var ReportVO
	 */
	private $rep;

	/**
	 * @return string[]
	 */
	public function build() :array {
		return [];
	}

	public function getReport() :ReportVO {
		return $this->rep;
	}

	/**
	 * @return $this
	 */
	public function setReport( ReportVO $rep ) {
		$this->rep = $rep;
		return $this;
	}
}