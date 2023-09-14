<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Data;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\ReportVO;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\ModConsumer;

class BuildBase {

	use ModConsumer;

	protected $report;

	public function __construct( ReportVO $report ) {
		$this->report = $report;
	}
}