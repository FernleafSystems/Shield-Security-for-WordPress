<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\LoadIpRules;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\Ops\Handler;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Utilities\Tool\IpListSort;

/**
 * @deprecated 16.0
 */
class RetrieveIpsForLists {

	use ModConsumer;

	/**
	 * @return string[]
	 */
	public function all() :array {
		return $this->forLists();
	}

	/**
	 * @return string[]
	 */
	public function white() :array {
		return $this->forLists( [ Handler::T_MANUAL_WHITE ] );
	}

	/**
	 * @return string[]
	 */
	public function black() :array {
		return $this->forLists( [ Handler::T_AUTO_BLACK, Handler::T_MANUAL_BLACK ] );
	}

	/**
	 * @return string[]
	 */
	public function blackAuto() :array {
		return $this->forLists( [ Handler::T_AUTO_BLACK ] );
	}

	/**
	 * @return string[]
	 */
	public function blackManual() :array {
		return $this->forLists( [ Handler::T_MANUAL_BLACK ] );
	}

	/**
	 * @return string[]
	 */
	private function forLists( array $lists = [] ) :array {

		$loader = ( new LoadIpRules() )->setMod( $this->getMod() );
		$loader->wheres = [
			sprintf( "`ir`.`type` IN ('%s')", implode( "','", $lists ) )
		];

		$ips = array_unique( array_map(
			function ( $record ) {
				return $record->ip;
			},
			$loader->select()
		) );

		return IpListSort::Sort( $ips );
	}
}
