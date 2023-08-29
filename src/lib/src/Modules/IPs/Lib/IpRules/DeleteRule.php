<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\{
	Components\IpAddressConsumer,
	ModConsumer
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\IpRuleRecord;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\Ops\Handler;

class DeleteRule {

	use ModConsumer;
	use IpAddressConsumer;

	public function byRecords( array $records ) {
		foreach ( $records as $record ) {
			$this->byRecord( $record );
		}
	}

	public function byRecord( IpRuleRecord $record ) :bool {
		$deleted = $this->mod()
						->getDbH_IPRules()
						->getQueryDeleter()
						->deleteById( $record->id );

		if ( $deleted ) {
			switch ( $record->type ) {

				case Handler::T_AUTO_BLOCK:
				case Handler::T_MANUAL_BLOCK:
				case Handler::T_CROWDSEC:
					self::con()->fireEvent( 'ip_unblock', [
						'audit_params' => [
							'ip'   => $record->ipAsSubnetRange(),
							'type' => Handler::GetTypeName( $record->type ),
						]
					] );
					break;

				case Handler::T_MANUAL_BYPASS:
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