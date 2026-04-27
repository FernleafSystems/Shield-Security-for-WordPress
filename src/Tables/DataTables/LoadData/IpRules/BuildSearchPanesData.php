<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\IpRules;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\BaseBuildSearchPanesData;
use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\Ops as IpRulesDB;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\SearchPanes\BuildDataForDays;
use FernleafSystems\Wordpress\Services\Services;

class BuildSearchPanesData extends BaseBuildSearchPanesData {

	public function build() :array {
		return [
			'options' => \array_map( '\array_values', [
				'day'        => $this->buildForDay(),
				'type'       => $this->buildForIpType(),
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
}