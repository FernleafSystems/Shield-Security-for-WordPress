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

	public function byRecord( IpRuleRecord $record ) :bool {
		$con = $this->getCon();
		/** @var IPs\ModCon $mod */
		$mod = $this->getMod();
		switch ( $record->type ) {

			case Handler::T_AUTO_BLACK:
			case Handler::T_MANUAL_BLACK:
				$con->fireEvent( 'ip_unblock', [ 'audit_params' => [ 'ip' => $record->ipAsSubnetRange() ] ] );
				break;

			case Handler::T_MANUAL_WHITE:
				$con->fireEvent( 'ip_bypass_remove', [ 'audit_params' => [ 'ip' => $record->ipAsSubnetRange() ] ] );
				break;
		}

		/** @var IpRulesDB\Delete $deleter */
		$deleter = $mod->getDbH_IPRules()->getQueryDeleter();
		return $deleter->deleteById( $record->id );
	}
}