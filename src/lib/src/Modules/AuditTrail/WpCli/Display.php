<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\AuditTrail\WpCli;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Tables;
use WP_CLI;

class Display extends Base\WpCli\BaseWpCliCmd {

	/**
	 * @throws \Exception
	 */
	protected function addCmds() {
		WP_CLI::add_command(
			$this->buildCmd( [ 'display' ] ),
			[ $this, 'cmdDisplay' ], [
			'shortdesc' => 'Import configuration from another WP site running Shield',
			'synopsis'  => [
//				[
//					'type'        => 'assoc',
//					'name'        => 'source',
//					'optional'    => false,
//					'description' => 'The URL of the source site or absolute path to import file.',
//				],
//				[
//					'type'        => 'assoc',
//					'name'        => 'site-secret',
//					'optional'    => true,
//					'default'     => null,
//					'description' => 'The secret key on the source site. Not required if this site is already registered on the source site.',
//				],
//				[
//					'type'        => 'assoc',
//					'name'        => 'slave',
//					'optional'    => true,
//					'default'     => null,
//					'options'     => [
//						'add',
//						'remove',
//					],
//					'description' => 'Add or remove this site as a registered slave (in the whitelist) on the source site. Secret is required to `add`.',
//				],
//				[
//					'type'        => 'flag',
//					'name'        => 'quiet',
//					'optional'    => true,
//					'description' => 'By-pass confirmation prompt.',
//				],
//				[
//					'type'        => 'flag',
//					'name'        => 'delete-file',
//					'optional'    => true,
//					'description' => 'Delete file after configurations have been imported.',
//				],
			],
		] );
	}

	/**
	 * @param array $null
	 * @param array $aA
	 * @throws WP_CLI\ExitException
	 */
	public function cmdDisplay( array $null, array $aA ) {
		/** @var \ICWP_WPSF_FeatureHandler_AuditTrail $oMod */
		$oMod = $this->getMod();
		$oTableBuilder = ( new Tables\Build\AuditTrail() )
			->setMod( $oMod )
			->setDbHandler( $oMod->getDbHandler_AuditTrail() );
		( new Tables\Render\WpCliTable\AuditTrail() )
			->setDataBuilder( $oTableBuilder )
			->render();
	}
}