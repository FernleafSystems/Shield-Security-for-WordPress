<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\WpCli;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\WpCli\BaseWpCliCmd;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops;
use WP_CLI;

class AddRemove extends BaseWpCliCmd {

	/**
	 * @throws \Exception
	 */
	protected function addCmds() {
		WP_CLI::add_command(
			$this->buildCmd( [ 'ip', 'add' ] ),
			[ $this, 'cmdIpAdd' ], [
			'shortdesc' => 'Add an IP address to one of your lists, white or black.',
			'synopsis'  => array_merge(
				$this->getCommonSynopsis(),
				[
					'type'        => 'assoc',
					'name'        => 'label',
					'optional'    => true,
					'description' => 'The label to assign to this IP entry.',
				]
			),
		] );
		WP_CLI::add_command(
			$this->buildCmd( [ 'ip', 'remove' ] ),
			[ $this, 'cmdIpRemove' ], [
				'shortdesc' => 'Remove an IP address from one of your lists, white or black.',
				'synopsis'  => $this->getCommonSynopsis(),
			]
		);
	}

	/**
	 * @param array $null
	 * @param array $aA
	 * @throws WP_CLI\ExitException
	 */
	public function cmdIpAdd( array $null, array $aA ) {

		$sLabel = isset( $aA[ 'label' ] ) ? $aA[ 'label' ] : 'none';

		$oAdder = ( new Ops\AddIp() )
			->setMod( $this->getMod() )
			->setIP( $aA[ 'ip' ] );
		try {
			if ( $aA[ 'list' ] === 'white' ) {
				$oAdder->toManualWhitelist( $sLabel );
			}
			else {
				$oAdder->toManualBlacklist( $sLabel );
			}
		}
		catch ( \Exception $oE ) {
			WP_CLI::error( $oE->getMessage() );
		}
		WP_CLI::success( __( 'IP address added successfully.', 'wp-simple-firewall' ) );
	}

	/**
	 * @param array $null
	 * @param array $aA
	 * @throws WP_CLI\ExitException
	 */
	public function cmdIpRemove( array $null, array $aA ) {
		/** @var \ICWP_WPSF_FeatureHandler_Ips $oMod */
		$oMod = $this->getMod();

		$oDel = ( new Ops\DeleteIp() )
			->setDbHandler( $oMod->getDbHandler_IPs() )
			->setIP( $aA[ 'ip' ] );
		if ( $aA[ 'list' ] === 'white' ) {
			$bSuccess = $oDel->fromWhiteList();
		}
		else {
			$bSuccess = $oDel->fromBlacklist();
		}

		$bSuccess ?
			WP_CLI::success( __( 'IP address removed successfully.', 'wp-simple-firewall' ) )
			: WP_CLI::error( __( "IP address couldn't be removed. (It may not be on this list)", 'wp-simple-firewall' ) );
	}

	/**
	 * @return array[]
	 */
	private function getCommonSynopsis() {
		return [
			[
				'type'        => 'assoc',
				'name'        => 'ip',
				'optional'    => false,
				'description' => 'The IP address.',
			],
			[
				'type'        => 'assoc',
				'name'        => 'list',
				'optional'    => false,
				'options'     => [
					'white',
					'black',
				],
				'description' => 'The IP list to update.',
			],
		];
	}
}