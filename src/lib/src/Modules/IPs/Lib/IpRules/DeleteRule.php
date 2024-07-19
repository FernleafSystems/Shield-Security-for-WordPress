<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\IpRules\IpRuleRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Components\IpAddressConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;

class DeleteRule {

	use IpAddressConsumer;
	use PluginControllerConsumer;

	public function byRecords( array $records ) {
		foreach ( $records as $record ) {
			$this->byRecord( $record );
		}
	}

	public function byRecord( IpRuleRecord $record ) :bool {
		$dbh = self::con()->db_con->ip_rules;
		$deleted = $dbh->getQueryDeleter()->deleteById( $record->id );

		if ( $record->is_range ) {
			IpRulesCache::Delete( IpRulesCache::COLLECTION_RANGES, IpRulesCache::GROUP_COLLECTIONS );
		}
		if ( $record->type === $dbh::T_MANUAL_BYPASS ) {
			IpRulesCache::Delete( IpRulesCache::COLLECTION_BYPASS, IpRulesCache::GROUP_COLLECTIONS );
		}

		if ( $deleted ) {
			switch ( $record->type ) {

				case $dbh::T_AUTO_BLOCK:
				case $dbh::T_MANUAL_BLOCK:
				case $dbh::T_CROWDSEC:
					self::con()->fireEvent( 'ip_unblock', [
						'audit_params' => [
							'ip'   => $record->ipAsSubnetRange(),
							'type' => $dbh::GetTypeName( $record->type ),
						]
					] );
					break;

				case $dbh::T_MANUAL_BYPASS:
					self::con()->fireEvent( 'ip_bypass_remove', [
						'audit_params' => [
							'ip' => $record->ipAsSubnetRange()
						]
					] );
					break;
			}
		}

		return $deleted;
	}
}