<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\AutoUnblock\AutoUnblockVisitor;
use FernleafSystems\Wordpress\Services\Services;

class IpAutoUnblockShieldVisitor extends IpsBase {

	const SLUG = 'ip_auto_unblock_shield_visitor';
	const PATTERN = self::SLUG.'-[a-f\d.:]+';

	/**
	 * @inheritDoc
	 */
	protected function exec() {
		$unBlocker = ( new AutoUnblockVisitor() )->setMod( $this->primary_mod );
		if ( $unBlocker->canRunAutoUnblockProcess() && $unBlocker->processAutoUnblockRequest() ) {
			Services::Response()->redirectToHome();
		}
		$this->response()->success = false;
	}
}