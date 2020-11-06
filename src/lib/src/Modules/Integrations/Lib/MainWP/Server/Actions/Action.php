<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\Server\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\MainWP\{
	Common\Consumers\MWPSiteConsumer
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;
use MainWP\Dashboard\MainWP_Connect;

class Action {

	use ModConsumer;
	use MWPSiteConsumer;

	public function run( string $actionToExe ) {
		$info = MainWP_Connect::fetch_url_authed(
			$this->getMwpSite()->siteobj,
			'extra_execution',
			[
				$this->getCon()->prefix( 'mainwp-action' ) => $actionToExe
			]
		);
		// TODO: store/parse $info
	}
}
