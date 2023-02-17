<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules;

use FernleafSystems\Wordpress\Plugin\Shield;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\{
	IpRuleRecord,
	Ops as IpRulesDB
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\DB\IpRules\Ops\Handler;

class DeleteRule {

	use Shield\Modules\ModConsumer;
	use IPs\Components\IpAddressConsumer;

	public const MOD = IPs\ModCon::SLUG;

	public function byRecords( array $records ) {
		foreach ( $records as $record ) {
			$this->byRecord( $record );
		}
	}

	public function byRecord( IpRuleRecord $record ) :bool {
		$con = $this->getCon();
		/** @var IPs\ModCon $mod */
		$mod = $this->getMod();

		/** @var IpRulesDB\Delete $deleter */
		$deleter = $mod->getDbH_IPRules()->getQueryDeleter();
		$deleted = $deleter->deleteById( $record->id );

		if ( $deleted ) {
			switch ( $record->type ) {

				case Handler::T_AUTO_BLOCK:
				case Handler::T_MANUAL_BLOCK:
				case Handler::T_CROWDSEC:
					$con->fireEvent( 'ip_unblock', [
						'audit_params' => [
							'ip'   => $record->ipAsSubnetRange(),
							'type' => Handler::GetTypeName( $record->type ),
						]
					] );
					break;

				case Handler::T_MANUAL_BYPASS:
					$con->fireEvent( 'ip_bypass_remove', [ 'audit_params' => [ 'ip' => $record->ipAsSubnetRange() ] ] );
					break;
			}
		}

		return $deleted;
	}
}