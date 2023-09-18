<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Table;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\IpRulesIterator;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\Ops\Handler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\Ops\Record;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\Build\SearchPanes\BuildDataForDays;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Options\Transient;
use IPLib\Factory;

class BuildSearchPanesData {

	use ModConsumer;

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
		/** @var ?Record $first */
		$first = $this->mod()
					  ->getDbH_IPRules()
					  ->getQuerySelector()
					  ->setOrderBy( 'last_access_at', 'ASC' )
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
			sprintf( "SELECT DISTINCT `ir`.`type` FROM `%s` as `ir`;",
				$this->mod()->getDbH_IPRules()->getTableSchema()->table
			)
		);
		return \array_filter( \array_map(
			function ( $result ) {
				$type = null;
				if ( \is_array( $result ) && !empty( $result[ 'type' ] ) ) {
					$type = [
						'label' => Handler::GetTypeName( $result[ 'type' ] ),
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