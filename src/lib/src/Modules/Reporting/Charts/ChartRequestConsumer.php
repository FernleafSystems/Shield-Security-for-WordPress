<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Reporting\Charts;

trait ChartRequestConsumer {

	/**
	 * @var ChartRequestVO
	 */
	protected $chartReq;

	/**
	 * @return ChartRequestVO
	 */
	public function getChartRequest() {
		return $this->chartReq;
	}

	/**
	 * @param ChartRequestVO $req
	 * @return $this
	 */
	public function setChartRequest( $req ) {
		$this->chartReq = $req;
		return $this;
	}
}