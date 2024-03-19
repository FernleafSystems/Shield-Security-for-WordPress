<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\WpCli\Cmds;

use FernleafSystems\Wordpress\Plugin\Shield\Tables;
use WP_CLI;

abstract class ActivityLogBase extends BaseCmd {

	protected function getCmdBase() :array {
		return \array_merge( parent::getCmdBase(), [
			'activity-log'
		] );
	}

	/**
	 * @throws \Exception
	 */
	protected function addCmds() {
		WP_CLI::add_command(
			$this->buildCmd( [ 'display' ] ),
			[ $this, 'cmdDisplay' ], $this->mergeCommonCmdArgs( [
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
//					'name'        => 'force',
//					'optional'    => true,
//					'description' => 'Bypass confirmation prompt.',
//				],
//				[
//					'type'        => 'flag',
//					'name'        => 'delete-file',
//					'optional'    => true,
//					'description' => 'Delete file after configurations have been imported.',
//				],
			],
		] ) );
	}

	/**
	 * @throws WP_CLI\ExitException
	 */
	public function cmdDisplay( array $null, array $a ) {
		( new Tables\Render\WpCliTable\ActivityLog() )->render();
	}
}