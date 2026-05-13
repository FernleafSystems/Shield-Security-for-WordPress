<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Config;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;

class OptionChangeAuditRedactionIntegrationTest extends ShieldIntegrationTestCase {

	private array $originalOptions = [];

	public function set_up() {
		parent::set_up();
		$this->originalOptions = $this->snapshotSelectedOptions( [
			'admin_access_key',
			'admin_access_timeout',
			'api_namespace_exclusions',
		] );
	}

	public function tear_down() {
		$this->restoreSelectedOptions( $this->originalOptions );
		parent::tear_down();
	}

	public function test_sensitive_option_change_audit_value_is_redacted() :void {
		$con = $this->requireController();
		$rawHash = 'audit-test-sensitive-hash';
		if ( (string)$con->opts->optGet( 'admin_access_key' ) === $rawHash ) {
			$rawHash .= '-updated';
		}

		$timeout = (int)$con->opts->optGet( 'admin_access_timeout' ) + 1;

		$this->captureShieldEvents();
		$con->opts
			->optSet( 'admin_access_key', $rawHash )
			->optSet( 'admin_access_timeout', $timeout )
			->store();

		$sensitiveEvent = $this->optionChangedEventFor( 'admin_access_key' );
		$this->assertSame( 'redacted', $sensitiveEvent[ 'meta' ][ 'audit_params' ][ 'value' ] ?? '' );
		$this->assertStringNotContainsString(
			$rawHash,
			\json_encode( $sensitiveEvent[ 'meta' ][ 'audit_params' ] ?? [] ) ?: ''
		);

		$normalEvent = $this->optionChangedEventFor( 'admin_access_timeout' );
		$this->assertSame( (string)$timeout, $normalEvent[ 'meta' ][ 'audit_params' ][ 'value' ] ?? '' );
	}

	public function test_newly_sensitive_array_option_change_audit_value_is_redacted() :void {
		$con = $this->requireController();
		$rawNamespace = 'audit-sensitive-namespace-'.\wp_generate_password( 8, false );

		$this->captureShieldEvents();
		$con->opts
			->optSet( 'api_namespace_exclusions', [ $rawNamespace ] )
			->store();

		$sensitiveEvent = $this->optionChangedEventFor( 'api_namespace_exclusions' );
		$auditParams = $sensitiveEvent[ 'meta' ][ 'audit_params' ] ?? [];

		$this->assertSame( 'api_namespace_exclusions', $auditParams[ 'key' ] ?? '' );
		$this->assertSame( 'redacted', $auditParams[ 'value' ] ?? '' );
		$this->assertStringNotContainsString(
			$rawNamespace,
			\json_encode( $auditParams ) ?: ''
		);
	}

	private function optionChangedEventFor( string $optionKey ) :array {
		foreach ( $this->getCapturedEventsByKey( 'plugin_option_changed' ) as $event ) {
			if ( ( $event[ 'meta' ][ 'audit_params' ][ 'key' ] ?? '' ) === $optionKey ) {
				return $event;
			}
		}

		$this->fail( sprintf( 'Expected plugin_option_changed event for option "%s".', $optionKey ) );
		return [];
	}
}
