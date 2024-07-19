<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\IpRules;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\{
	IpRulesIterator,
	Ops as IpRulesDB
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\SearchPanes\BuildDataForDays;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Options\Transient;
use IPLib\Factory;

class BuildSearchPanesData {

	use PluginControllerConsumer;

	public function build() :array {
		return [
			'options' => \array_map( '\array_values', [
				'day'        => $this->buildForDay(),
				'type'       => $this->buildForIpType(),
				//				'ip'         => $this->buildForIP(),
				//				'ip'         => $this->buildForIpWithoutIterator(),
				'is_blocked' => $this->buildForIsBlocked(),
			] )
		];
	}

	private function buildForDay() :array {
		/** @var ?IpRulesDB\Record $first */
		$first = self::con()
			->db_con
			->ip_rules
			->getQuerySelector()
			->setOrderBy( 'last_access_at', 'ASC' )
			->addWhereNewerThan( 0, 'last_access_at' )
			->first();
		return ( new BuildDataForDays() )->buildFromOldestToNewest(
			empty( $first ) ? Services::Request()->ts() : $first->last_access_at
		);
	}

	private function buildForIsBlocked() :array {
		return [
			[
				'label' => 'Blocked',
				'value' => 1,
			],
			[
				'label' => 'Not Blocked',
				'value' => 0,
			],
		];
	}

	private function buildForIpType() :array {
		$results = Services::WpDb()->selectCustom(
			sprintf( "SELECT DISTINCT `ir`.`type` FROM `%s` as `ir`;", self::con()->db_con->ip_rules->getTable() )
		);
		return \array_filter( \array_map(
			function ( $result ) {
				$type = null;
				if ( \is_array( $result ) && !empty( $result[ 'type' ] ) ) {
					$type = [
						'label' => IpRulesDB\Handler::GetTypeName( $result[ 'type' ] ),
						'value' => $result[ 'type' ],
					];
				}
				return $type;
			},
			\is_array( $results ) ? $results : []
		) );
	}

	private function buildForIP() :array {
		$ips = Transient::Get( 'apto-shield-iprulestable-ips', [] );
		if ( empty( $ips ) ) {
			$rulesIterator = new IpRulesIterator();
			$loader = $rulesIterator->getLoader();
			$loader->joined_table_select_fields = [ 'cidr', 'is_range' ];

			$ips = [];
			foreach ( $rulesIterator as $record ) {
				$ips[] = [
					'label' => $record->is_range ?
						Factory::parseRangeString( sprintf( '%s/%s', $record->ip, $record->cidr ) )->asSubnet()
							   ->toString()
						: $record->ip,
					'value' => $record->id,
				];
			}
			Transient::Set( 'apto-shield-iprulestable-ips', $ips, 10 );
		}
		return $ips;
	}
}