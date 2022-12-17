<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Reports\Builders;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions\Render\Components\Reports\ReportsCollatorBase;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Constants;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Lib\Reports\ReportVO;

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