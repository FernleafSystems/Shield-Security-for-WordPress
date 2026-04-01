<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\IpAnalyse;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Investigation\InvestigationTableContract;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Investigation\ForTraffic as InvestigationTrafficTableBuilder;

class Traffic extends Base {

	public const SLUG = 'ipanalyse_traffic_log';
	public const TEMPLATE = '/wpadmin/components/investigate/table_container.twig';

	protected function getRenderData() :array {
		return $this->buildIpTableRenderData(
			__( 'Recent Request Logs', 'wp-simple-firewall' ),
			'warning',
			InvestigationTableContract::TABLE_TYPE_TRAFFIC,
			( new InvestigationTrafficTableBuilder() )
				->setSubject( InvestigationTableContract::SUBJECT_TYPE_IP, $this->getAnalyseIP() )
				->buildRaw()
		);
	}
}
