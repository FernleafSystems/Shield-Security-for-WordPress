<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules;

use IPLib\Factory;

/**
 * NOT a true DB Model
 * @property string $ip
 */
class IpRuleRecord extends Ops\Record {

	public function ipAsSubnetRange() :string {
		return Factory::parseRangeString( $this->is_range ? $this->ip : sprintf( '%s/%s', $this->ip, $this->cidr ) )
					  ->asSubnet()
					  ->toString();
	}
}