<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\AutoUnblock\AutoUnblockCrowdsec;
use FernleafSystems\Wordpress\Services\Services;

class IpAutoUnblockCrowdsecVisitor extends IpAutoUnblockShieldVisitor {

	public const SLUG = 'ip_auto_unblock_crowdsec_visitor';
	public const PATTERN = self::SLUG.'-[a-f\d.:]+';

	protected function exec() {
		$unBlocker = ( new AutoUnblockCrowdsec() )->setMod( $this->primary_mod );
		if ( $unBlocker->canRunAutoUnblockProcess() && $unBlocker->processAutoUnblockRequest() ) {
			Services::Response()->redirectToHome();
		}
		$this->response()->success = false;
	}
}