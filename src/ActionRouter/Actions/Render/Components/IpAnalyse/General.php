<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\IpAnalyse;

class General extends Base {

	public const SLUG = 'ipanalyse_general';
	public const TEMPLATE = '/wpadmin/components/ip_analyse/ip_general.twig';

	protected function getRenderData() :array {
		return ( new GeneralViewDataBuilder() )->build( $this->getAnalyseIP() );
	}
}
