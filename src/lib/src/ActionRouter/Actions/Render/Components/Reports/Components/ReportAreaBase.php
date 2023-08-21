<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Reports\Components;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Reports\ReportVO;

class ReportAreaBase extends BaseRender {

	protected function report() :ReportVO {
		return ( new ReportVO() )->applyFromArray( $this->action_data[ 'report' ] );
	}
}