<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\WpCli;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use WP_CLI;

class Enumerate extends Base {

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

	public function cmdPrint( array $null, array $args ) {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		try {
			$this->checkList( $args[ 'list' ] );

			$retriever = ( new Ops\RetrieveIpsForLists() )
				->setDbHandler( $mod->getDbHandler_IPs() );

			$IPs = array_map(
				function ( $ip ) {
					return [ 'IP' => $ip, ];
				},
				in_array( $args[ 'list' ], [ 'white', 'bypass' ] ) ? $retriever->white() : $retriever->black()
			);

			WP_CLI\Utils\format_items(
				'table',
				$IPs,
				[ 'IP' ]
			);
		}
		catch ( \Exception $e ) {
			WP_CLI::error( $e->getMessage() );
		}
	}
}