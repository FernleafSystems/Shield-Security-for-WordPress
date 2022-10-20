<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Base\Common\ExecOnceModConsumer;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\Insights\ActionRouter\Actions;

class BlockRequest extends ExecOnceModConsumer {

	protected function canRun() :bool {
		return (bool)apply_filters( 'shield/is_request_blocked', $this->getCon()->this_req->is_ip_blocked_shield );
	}

	protected function run() {
		do_action( 'shield/maybe_intercept_block_shield' );

		$this->getCon()->fireEvent( 'conn_kill' );

		$this->getCon()
			 ->getModule_Insights()
			 ->getActionRouter()
			 ->action( Actions\FullPageDisplay\DisplayBlockPage::SLUG, [
				 'render_slug' => Actions\Render\FullPage\Block\BlockIpAddressShield::SLUG
			 ] );
	}
}