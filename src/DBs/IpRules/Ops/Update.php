<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\IpRuleRecord;
use FernleafSystems\Wordpress\Services\Services;

class Update extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Update {

	/**
	 * @param Record|IpRuleRecord $IP
	 */
	public function incrementTransgressions( $IP, int $increment = 1 ) :bool {
		return $this->updateTransgressions( $IP, $IP->offenses + $increment );
	}

	/**
	 * @param Record|IpRuleRecord $IP
	 */
	public function updateTransgressions( $IP, int $offenses, bool $updateLastAccess = true ) :bool {
		$data = [
			'offenses' => \max( 0, $offenses ),
		];
		if ( $updateLastAccess ) {
			$data[ 'last_access_at' ] = Services::Request()->ts();
		}
		return $this->updateRecord( $IP, $data );
	}

	public function updateLabel( Record $IP, string $label ) :bool {
		return $this->updateRecord( $IP, [ 'label' => \trim( $label ) ] );
	}

	/**
	 * @param Record|IpRuleRecord $record
	 */
	public function updateLastAccessAt( $record ) :bool {
		return $this->updateById( $record->id, [ 'last_access_at' => Services::Request()->ts() ] );
	}

	public function setBlocked( Record $IP ) :bool {
		return $this->updateRecord( $IP, [
			'blocked_at'     => Services::Request()->ts(),
			'last_access_at' => Services::Request()->ts()
		] );
	}
}