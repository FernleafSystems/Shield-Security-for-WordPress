<?php

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\WpCli;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\LoadIpRules;
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
					'options'     => [ 'white', 'bypass', 'black', 'block', 'crowdsec' ],
					'description' => 'The IP list to enumerate.',
				],
			],
		] ) );
	}

	public function cmdPrint( array $null, array $args ) {
		$dbh = self::con()->db_con->dbhIPRules();

		try {
			$this->checkList( $args[ 'list' ] );

			if ( \in_array( $args[ 'list' ], [ 'white', 'bypass' ] ) ) {
				$lists = [ $dbh::T_MANUAL_BYPASS ];
			}
			elseif ( \in_array( $args[ 'list' ], [ 'black', 'block' ] ) ) {
				$lists = [ $dbh::T_AUTO_BLOCK, $dbh::T_MANUAL_BLOCK ];
			}
			else {
				$lists = [ $dbh::T_CROWDSEC ];
			}

			$loader = new LoadIpRules();
			$loader->wheres = [
				sprintf( "`ir`.`type` IN ('%s')", \implode( "','", $lists ) )
			];

			$IPs = \array_map(
				function ( $record ) {
					return [ 'IP' => $record->ip, ];
				},
				$loader->select()
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