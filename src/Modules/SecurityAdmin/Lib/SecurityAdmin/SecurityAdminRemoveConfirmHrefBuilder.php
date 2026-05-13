<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\SecurityAdmin\Lib\SecurityAdmin;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\SecurityAdminRemove;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;

class SecurityAdminRemoveConfirmHrefBuilder {

	use PluginControllerConsumer;

	public function build() :string {
		return self::con()->plugin_urls->noncedPluginAction(
			SecurityAdminRemove::class,
			Services::WpGeneral()->getAdminUrl()
		);
	}
}
