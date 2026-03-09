<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Config;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Ops\LoadConfig;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Services\Services;

class LoadConfigInvalidationIntegrationTest extends ShieldIntegrationTestCase {

	private string $storeKey = '';
	private string $pluginConfigPath = '';
	private $storedOptionSnapshot;

	public function set_up() {
		parent::set_up();

		$con = $this->requireController();
		$this->storeKey = 'aptoweb_controller_'.\substr( \hash( 'md5', \get_class( $con ) ), 0, 6 );
		$this->pluginConfigPath = $con->paths->forPluginItem( 'plugin.json' );
		$this->storedOptionSnapshot = Services::WpGeneral()->getOption( $this->storeKey );
	}

	public function tear_down() {
		if ( $this->storedOptionSnapshot === false ) {
			Services::WpGeneral()->deleteOption( $this->storeKey );
		}
		else {
			Services::WpGeneral()->updateOption( $this->storeKey, $this->storedOptionSnapshot );
		}

		parent::tear_down();
	}

	public function test_old_last_file_load_at_does_not_force_rebuild_when_signature_matches() :void {
		$stored = $this->requireController()->cfg->getRawData();
		$stored[ 'hash' ] = $this->buildCurrentSignature();
		$stored[ 'last_file_load_at' ] = Services::Request()->ts() - \DAY_IN_SECONDS;

		Services::WpGeneral()->updateOption( $this->storeKey, $stored );

		$cfg = $this->loadConfig();

		$this->assertFalse( $cfg->rebuilt );
		$this->assertFalse( $cfg->persist_required );
		$this->assertSame( $this->buildCurrentSignature(), $cfg->hash );
	}

	public function test_mismatched_signature_forces_rebuild() :void {
		$stored = $this->requireController()->cfg->getRawData();
		$stored[ 'hash' ] = 'stale-signature';
		$stored[ 'last_file_load_at' ] = Services::Request()->ts();

		Services::WpGeneral()->updateOption( $this->storeKey, $stored );

		$cfg = $this->loadConfig();

		$this->assertTrue( $cfg->rebuilt );
		$this->assertTrue( $cfg->persist_required );
		$this->assertSame( $this->buildCurrentSignature(), $cfg->hash );
	}

	public function test_mismatched_version_forces_rebuild() :void {
		$stored = $this->requireController()->cfg->getRawData();
		$stored[ 'hash' ] = $this->buildCurrentSignature();
		$stored[ 'properties' ][ 'version' ] = '0.0.0';

		Services::WpGeneral()->updateOption( $this->storeKey, $stored );

		$cfg = $this->loadConfig();

		$this->assertTrue( $cfg->rebuilt );
		$this->assertSame( $this->requireController()->cfg->properties[ 'version' ], $cfg->properties[ 'version' ] );
	}

	private function buildCurrentSignature() :string {
		$FS = Services::WpFs();
		return sprintf(
			'%d:%d',
			$FS->getModifiedTime( $this->pluginConfigPath ),
			(int)( $FS->getFileSize( $this->pluginConfigPath ) ?? 0 )
		);
	}

	private function loadConfig() {
		return ( new LoadConfig( $this->pluginConfigPath, $this->storeKey ) )->run();
	}
}
