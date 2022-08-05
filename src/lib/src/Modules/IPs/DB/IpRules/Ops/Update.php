<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\Ops;

use FernleafSystems\Wordpress\Plugin\Core\Databases\Base;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\IpRuleRecord;
use FernleafSystems\Wordpress\Services\Services;

class Update extends Base\Update {

	public function incrementTransgressions( Record $IP, int $increment = 1 ) :bool {
		return $this->updateTransgressions( $IP, $IP->offenses + $increment );
	}

	public function updateTransgressions( Record $IP, int $offenses ) :bool {
		return $this->updateRecord( $IP, [
			'offenses'       => max( 0, $offenses ),
			'last_access_at' => Services::Request()->ts()
		] );
	}

	public function updateLabel( Record $IP, string $label ) :bool {
		return $this->updateRecord( $IP, [ 'label' => trim( $label ) ] );
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