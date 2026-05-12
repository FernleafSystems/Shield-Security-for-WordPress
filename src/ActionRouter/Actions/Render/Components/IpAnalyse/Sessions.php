<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\IpAnalyse;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Investigation\InvestigationTableContract;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\CommonDisplayStrings;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\Investigation\ForSessions as InvestigationSessionsTableBuilder;

class Sessions extends Base {

	public const SLUG = 'ipanalyse_sessions';
	public const TEMPLATE = '/wpadmin/components/investigate/table_container.twig';

	protected function getRenderData() :array {
		return $this->buildIpTableRenderData(
			CommonDisplayStrings::get( 'user_sessions_label' ),
			'good',
			InvestigationTableContract::TABLE_TYPE_SESSIONS,
			( new InvestigationSessionsTableBuilder() )
				->setSubject( InvestigationTableContract::SUBJECT_TYPE_IP, $this->getAnalyseIP() )
				->buildRaw()
		);
	}
}
