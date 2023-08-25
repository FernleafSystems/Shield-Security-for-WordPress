<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\Components;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\ReportsCollatorBase;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\ReportVO;

abstract class BaseBuilder extends ReportsCollatorBase {

	public const TYPE = Constants::REPORT_TYPE_INFO;

	protected function getReport() :ReportVO {
		return ( new ReportVO() )->applyFromArray( $this->action_data[ 'report' ] );
	}

	protected function getRequiredDataKeys() :array {
		return [
			'report',
		];
	}
}