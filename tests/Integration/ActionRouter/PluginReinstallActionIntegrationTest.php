<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\ExternalEntryRefusalAssertions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\{
	ActionData,
	ActionProcessor,
	Actions\PluginReinstall
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ServicesState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ActionRouter\Support\ActionRequestNonceFixture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Core\Plugins;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\WpPluginVo;

class PluginReinstallActionIntegrationTest extends ShieldIntegrationTestCase {

	use ActionRequestNonceFixture;
	use ExternalEntryRefusalAssertions;

	public function set_up() {
		parent::set_up();
		$this->loginAsAdministrator();
		$this->requireController()->this_req->wp_is_ajax = false;
	}

	public function test_reinstall_requires_valid_nonce_before_plugin_lookup() :void {
		$plugins = new PluginReinstallPoisonedPluginsService();
		$servicesSnapshot = ServicesState::snapshot();
		ServicesState::mergeItems( [
			'service_wpplugins' => $plugins,
		] );

		try {
			$this->assertAjaxRequestRefused( PluginReinstall::class, [
				'file' => 'akismet/akismet.php',
			] );
		}
		finally {
			$pluginLookupAttempted = $plugins->wasPluginLookupAttempted();
			ServicesState::restore( $servicesSnapshot );
		}
		$this->assertFalse( $pluginLookupAttempted );
	}
}

class PluginReinstallPoisonedPluginsService extends Plugins {

	private bool $pluginLookupAttempted = false;

	public function getPluginAsVo( string $file, bool $reload = false ) :?WpPluginVo {
		unset( $file, $reload );
		$this->pluginLookupAttempted = true;
		throw new \RuntimeException( 'Plugin reinstall nonce failure must not look up plugins.' );
	}

	public function wasPluginLookupAttempted() :bool {
		return $this->pluginLookupAttempted;
	}
}
