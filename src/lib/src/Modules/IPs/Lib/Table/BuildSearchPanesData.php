<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Table;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\IpRulesIterator;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\LoadIpRules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\Ops\Handler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModCon;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Options\Transient;
use IPLib\Factory;

class BuildSearchPanesData {

	use ModConsumer;

	public function build() :array {
		return [
			'options' => [
				'type'       => $this->buildForIpType(),
				//				'ip'         => $this->buildForIP(),
				//				'ip'         => $this->buildForIpWithoutIterator(),
				'is_blocked' => $this->buildForIsBlocked(),
			]
		];
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
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$results = Services::WpDb()->selectCustom(
			sprintf( "SELECT DISTINCT `ir`.`type` FROM `%s` as `ir`;", $mod->getDbH_IPRules()->getTableSchema()->table )
		);
		return array_filter( array_map(
			function ( $result ) {
				$type = null;
				if ( is_array( $result ) && !empty( $result[ 'type' ] ) ) {
					$type = [
						'label' => Handler::GetTypeName( $result[ 'type' ] ),
						'value' => $result[ 'type' ],
					];
				}
				return $type;
			},
			is_array( $results ) ? $results : []
		) );
	}

	private function buildForIP() :array {
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$ips = Transient::Get( 'apto-shield-iprulestable-ips', [] );
		if ( empty( $ips ) ) {
			$rulesIterator = new IpRulesIterator();
			$loader = $rulesIterator->setMod( $mod )->getLoader();
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

	private function buildForIpWithoutIterator() :array {
		error_log( __FUNCTION__.var_export( time(), true ) );
		/** @var ModCon $mod */
		$mod = $this->getMod();

		$results = ( new LoadIpRules() )->setMod( $mod );
		$results->joined_table_select_fields = [ 'cidr' ];
		return array_values( array_filter( array_map(
			function ( $result ) {
				$range = Factory::parseRangeString( sprintf( '%s/%s', $result->ip, $result->cidr ) );
				if ( empty( $range ) ) {
					$IP = null;
				}
				else {
					$IP = [
						'label' => $range->getSize() === 1 ? $result->ip : $range->asSubnet()->toString(),
						'value' => $result->id,
					];
				}

				return $IP;
			},
			$results->select()
		) ) );
	}
}