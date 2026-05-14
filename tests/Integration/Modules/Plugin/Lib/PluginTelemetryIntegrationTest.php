<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Modules\Plugin\Lib;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Lib\PluginTelemetry;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class PluginTelemetryIntegrationTest extends ShieldIntegrationTestCase {

	private array $originalOptions = [];

	public function set_up() {
		parent::set_up();

		$this->requireDb( 'events' );
		$this->originalOptions = $this->snapshotSelectedOptions( [
			'importexport_masterurl',
			'api_namespace_exclusions',
			'blockdown_cfg',
			'admin_access_timeout',
		] );
	}

	public function tear_down() {
		$this->restoreSelectedOptions( $this->originalOptions );
		parent::tear_down();
	}

	public function test_sensitive_options_are_excluded_from_telemetry_options_payload() :void {
		$con = $this->requireController();
		$masterUrl = 'https://audit-sensitive-master.example.com';
		$apiNamespace = 'audit-sensitive-api-namespace';
		$blockdownSlug = 'audit-sensitive-blockdown';
		$timeout = 37;

		$con->opts
			->optSet( 'importexport_masterurl', $masterUrl )
			->optSet( 'api_namespace_exclusions', [ $apiNamespace ] )
			->optSet( 'blockdown_cfg', [ 'slug' => $blockdownSlug ] )
			->optSet( 'admin_access_timeout', $timeout )
			->store();

		$options = ( new PluginTelemetry() )->collectTrackingData()[ 'options' ] ?? [];

		$this->assertArrayNotHasKey( 'importexport_masterurl', $options );
		$this->assertArrayNotHasKey( 'api_namespace_exclusions', $options );
		$this->assertArrayNotHasKey( 'blockdown_cfg', $options );
		$this->assertStringNotContainsString( $masterUrl, \json_encode( $options ) ?: '' );
		$this->assertStringNotContainsString( $apiNamespace, \json_encode( $options ) ?: '' );
		$this->assertStringNotContainsString( $blockdownSlug, \json_encode( $options ) ?: '' );

		$this->assertArrayHasKey( 'admin_access_timeout', $options );
		$this->assertSame( $timeout, (int)$options[ 'admin_access_timeout' ] );
	}
}
