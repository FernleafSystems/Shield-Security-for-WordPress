<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\Reporting\Charts;

trait ChartRequestConsumer {

	protected ChartRequestVO $chartReq;

	public function getChartRequest() :ChartRequestVO {
		return $this->chartReq;
	}

	public function setChartRequest( ChartRequestVO $req ) :self {
		$this->chartReq = $req;
		return $this;
	}
}