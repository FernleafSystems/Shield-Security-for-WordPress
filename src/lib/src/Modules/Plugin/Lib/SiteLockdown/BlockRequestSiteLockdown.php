<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\SiteLockdown;

use FernleafSystems\Utilities\Logic\ExecOnce;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\ModConsumer;

class BlockRequestSiteLockdown {

	use ExecOnce;
	use ModConsumer;

	protected function run() {
		self::con()->action_router->action( Actions\FullPageDisplay\DisplayBlockPage::class, [
			'render_slug' => Actions\Render\FullPage\Block\BlockPageSiteBlockdown::SLUG
		] );
	}
}