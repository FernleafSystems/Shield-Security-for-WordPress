<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules;

use IPLib\Factory;

/**
 * NOT a true DB Model
 * @property string $ip
 */
class IpRuleRecord extends Ops\Record {

	public function ipAsSubnetRange( bool $includeCidrForSingles = false ) :string {
		return ( $includeCidrForSingles || $this->is_range ) ?
			Factory::parseRangeString( $this->is_range ? sprintf( '%s/%s', $this->ip, $this->cidr ) : $this->ip )
				   ->asSubnet()
				   ->toString()
			: $this->ip;
	}

	public function isBlocked() :bool {
		return $this->blocked_at > $this->unblocked_at;
	}
}