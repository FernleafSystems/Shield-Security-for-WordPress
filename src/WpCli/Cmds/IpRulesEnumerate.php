<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\WpCli\Cmds;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\LoadIpRules;

class IpRulesEnumerate extends IpRulesBase {

	protected function cmdParts() :array {
		return [ 'print' ];
	}

	protected function cmdShortDescription() :string {
		return 'Enumerate all IPs currently present on your lists.';
	}

	protected function cmdSynopsis() :array {
		$options = [ 'white', 'bypass', 'black', 'block', 'crowdsec' ];
		return [
			[
				'type'        => 'assoc',
				'name'        => 'list',
				'optional'    => false,
				'options'     => $options,
				'description' => sprintf( 'The IP list to enumerate (%s).', implode( ', ', $options ) ),
			],
		];
	}

	public function runCmd() :void {
		$dbh = self::con()->db_con->ip_rules;

		try {
			$this->checkList( $this->execCmdArgs[ 'list' ] );

			if ( \in_array( $this->execCmdArgs[ 'list' ], [ 'white', 'bypass' ] ) ) {
				$lists = [ $dbh::T_MANUAL_BYPASS ];
			}
			elseif ( \in_array( $this->execCmdArgs[ 'list' ], [ 'black', 'block' ] ) ) {
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

			\WP_CLI\Utils\format_items(
				'table',
				$IPs,
				[ 'IP' ]
			);
		}
		catch ( \Exception $e ) {
			\WP_CLI::error( $e->getMessage() );
		}
	}
}