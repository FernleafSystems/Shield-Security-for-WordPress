<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\WpCli;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\WpCli\BaseWpCliCmd;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops;
use WP_CLI;

class Enumerate extends BaseWpCliCmd {

	/**
	 * @throws \Exception
	 */
	protected function addCmds() {
		WP_CLI::add_command(
			$this->buildCmd( [ 'print' ] ),
			[ $this, 'cmdPrint' ], $this->mergeCommonCmdArgs( [
			'shortdesc' => 'Enumerate all IPs currently present on your lists.',
			'synopsis'  => [
				[
					'type'        => 'assoc',
					'name'        => 'list',
					'optional'    => false,
					'options'     => [
						'white',
						'black',
					],
					'description' => 'The IP list to enumerate.',
				],
			],
		] ) );
	}

	public function cmdPrint( $null, $aA ) {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();

		$oRtr = ( new Ops\RetrieveIpsForLists() )
			->setDbHandler( $oMod->getDbHandler_IPs() );
		$aIPs = $aA[ 'list' ] === 'white' ? $oRtr->white() : $oRtr->black();
		$aIPs = array_map(
			function ( $sIP ) {
				return [ 'IP' => $sIP, ];
			},
			$aIPs
		);

		WP_CLI\Utils\format_items(
			'table',
			$aIPs,
			[ 'IP' ]
		);
	}
}