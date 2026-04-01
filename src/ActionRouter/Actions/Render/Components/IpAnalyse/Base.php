<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\IpAnalyse;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	Actions\Investigation\InvestigationTableContract,
	Actions\InvestigationTableAction
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\InvestigateRenderContracts;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;
use FernleafSystems\Wordpress\Services\Services;

class Base extends Render\BaseRender {

	use InvestigateRenderContracts;

	protected function getRequiredDataKeys() :array {
		return [ 'ip' ];
	}

	/**
	 * @throws ActionException
	 */
	protected function getAnalyseIP() :string {
		if ( !Services::IP()->isValidIp( $this->action_data[ 'ip' ] ) ) {
			throw new ActionException( __( "A valid IP address wasn't provided.", 'wp-simple-firewall' ) );
		}
		return $this->action_data[ 'ip' ];
	}

	protected function buildIpTableRenderData( string $title, string $status, string $tableType, array $datatableInit ) :array {
		$table = $this->buildTableContainerContract(
			$title,
			$status,
			$tableType,
			InvestigationTableContract::SUBJECT_TYPE_IP,
			$this->getAnalyseIP(),
			$datatableInit,
			ActionData::Build( InvestigationTableAction::class )
		);
		$table[ 'is_flat' ] = true;

		return [
			'table' => $table,
		];
	}
}
