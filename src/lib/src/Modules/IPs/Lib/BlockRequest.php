<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModConsumer;

/**
 * @deprecated 18.6
 */
class BlockRequest {

	use ExecOnce;
	use ModConsumer;

	protected function canRun() :bool {
		return $this->isRequestBlocked();
	}

	protected function run() {
		do_action( 'shield/maybe_intercept_block_shield' );
		// This can still be stopped.
		if ( $this->isRequestBlocked() ) {
			self::con()->fireEvent( 'conn_kill' );
			self::con()->action_router->action( Actions\FullPageDisplay\DisplayBlockPage::class, [
				'render_slug' => Actions\Render\FullPage\Block\BlockIpAddressShield::SLUG
			] );
		}
	}

	private function isRequestBlocked() :bool {
		return (bool)apply_filters( 'shield/is_request_blocked', self::con()->this_req->is_ip_blocked_shield );
	}
}