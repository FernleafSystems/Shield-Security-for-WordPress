<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Components\CompCons;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\RuntimeTestState;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\ShieldIntegrationTestCase;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\CurrentRequestFixture;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support\ProviderPluginFixture;

class IntegrationsConIntegrationTest extends ShieldIntegrationTestCase {

	use CurrentRequestFixture;
	use ProviderPluginFixture;

	private const OPTION_KEYS = [
		'enable_auto_integrations',
		'auto_integrations_track',
		'form_spam_providers',
		'user_form_providers',
	];

	private array $optionSnapshot = [];

	private array $requestSnapshot = [];

	public function set_up() {
		parent::set_up();

		$this->optionSnapshot = $this->snapshotSelectedOptions( self::OPTION_KEYS );
		$this->requestSnapshot = $this->snapshotCurrentRequestState();
		$this->snapshotProviderPluginFixtureState();
		$this->prepareAdminRequest();
	}

	public function tear_down() {
		if ( static::con() !== null ) {
			$this->restoreSelectedOptions( $this->optionSnapshot );
			$this->restoreProviderPluginFixtureState();
			$this->restoreCurrentRequestState( $this->requestSnapshot );
		}

		parent::tear_down();
	}

	public function test_build_integrations_states_exposes_installed_provider_contracts() :void {
		$this->enablePremiumCapabilities( [ 'thirdparty_scan_spam', 'thirdparty_scan_users' ] );
		$this->installProviderFixture( 'ninjaforms', 'ninja-forms.php', 'Ninja_Forms', 'Ninja Forms' );
		$this->installProviderFixture(
			'easy-digital-downloads',
			'easy-digital-downloads.php',
			'Easy_Digital_Downloads',
			'Easy Digital Downloads'
		);

		$con = $this->requireController();
		$con->opts
			->optSet( 'form_spam_providers', [ 'ninjaforms' ] )
			->optSet( 'user_form_providers', [ 'wordpress' ] )
			->store();
		$this->resetProviderCaches();

		$states = $con->comps->integrations->buildIntegrationsStates();

		$this->assertIntegrationState( $states, 'wordpress', 'enabled', 'WordPress', true );
		$this->assertIntegrationState( $states, 'ninjaforms', 'enabled', 'Ninja Forms', true );
		$this->assertIntegrationState( $states, 'easydigitaldownloads', 'available', 'Easy Digital Downloads', true );
	}

	public function test_build_integrations_states_reflects_capability_by_provider_family() :void {
		$this->enablePremiumCapabilities( [ 'thirdparty_scan_spam' ] );
		$this->installProviderFixture( 'ninjaforms', 'ninja-forms.php', 'Ninja_Forms', 'Ninja Forms' );
		$this->installProviderFixture(
			'easy-digital-downloads',
			'easy-digital-downloads.php',
			'Easy_Digital_Downloads',
			'Easy Digital Downloads'
		);

		$con = $this->requireController();
		$con->opts
			->optSet( 'form_spam_providers', [] )
			->optSet( 'user_form_providers', [ 'wordpress' ] )
			->store();
		$this->resetProviderCaches();

		$states = $con->comps->integrations->buildIntegrationsStates();

		$this->assertIntegrationState( $states, 'ninjaforms', 'available', 'Ninja Forms', true );
		$this->assertIntegrationState( $states, 'easydigitaldownloads', 'available', 'Easy Digital Downloads', false );
	}

	public function test_disabled_auto_integrations_does_not_mutate_track_or_providers() :void {
		$this->enablePremiumCapabilities( [ 'thirdparty_scan_spam', 'thirdparty_scan_users' ] );
		$this->installProviderFixture( 'ninjaforms', 'ninja-forms.php', 'Ninja_Forms', 'Ninja Forms' );
		$this->installProviderFixture(
			'easy-digital-downloads',
			'easy-digital-downloads.php',
			'Easy_Digital_Downloads',
			'Easy Digital Downloads'
		);

		$track = [
			'last_check_at' => 1234567890,
			'profile_hash'  => 'existing-profile',
		];
		$con = $this->requireController();
		$con->opts
			->optSet( 'enable_auto_integrations', 'N' )
			->optSet( 'auto_integrations_track', $track )
			->optSet( 'form_spam_providers', [] )
			->optSet( 'user_form_providers', [ 'wordpress' ] )
			->store();

		$con->comps->integrations->resetExecution()->execute();

		$this->assertSame( $track, $con->opts->optGet( 'auto_integrations_track' ) );
		$this->assertSame( [], $con->opts->optGet( 'form_spam_providers' ) );
		$this->assertSame( [ 'wordpress' ], $con->opts->optGet( 'user_form_providers' ) );
	}

	public function test_auto_integrations_adds_capable_installed_providers_and_persists_them() :void {
		$this->enablePremiumCapabilities( [ 'thirdparty_scan_spam', 'thirdparty_scan_users' ] );
		$this->installProviderFixture( 'ninjaforms', 'ninja-forms.php', 'Ninja_Forms', 'Ninja Forms' );
		$this->installProviderFixture(
			'easy-digital-downloads',
			'easy-digital-downloads.php',
			'Easy_Digital_Downloads',
			'Easy Digital Downloads'
		);

		$con = $this->requireController();
		$con->opts
			->optSet( 'enable_auto_integrations', 'Y' )
			->optSet( 'auto_integrations_track', [] )
			->optSet( 'form_spam_providers', [] )
			->optSet( 'user_form_providers', [ 'wordpress' ] )
			->store();
		$this->resetProviderCaches();

		$con->comps->integrations->resetExecution()->execute();

		$track = $con->opts->optGet( 'auto_integrations_track' );
		$this->assertIsArray( $track );
		$this->assertArrayHasKey( 'last_check_at', $track );
		$this->assertArrayHasKey( 'profile_hash', $track );
		$this->assertGreaterThan( 0, (int)$track[ 'last_check_at' ] );
		$this->assertNotSame( '', (string)$track[ 'profile_hash' ] );
		$this->assertSame( [ 'ninjaforms' ], $con->opts->optGet( 'form_spam_providers' ) );
		$this->assertSame( [ 'wordpress', 'easydigitaldownloads' ], $con->opts->optGet( 'user_form_providers' ) );

		RuntimeTestState::resetOptionsRuntimeCache();
		$this->assertSame( [ 'ninjaforms' ], $con->opts->optGet( 'form_spam_providers' ) );
		$this->assertSame( [ 'wordpress', 'easydigitaldownloads' ], $con->opts->optGet( 'user_form_providers' ) );

		$con->comps->integrations->resetExecution()->execute();
		$this->assertSame( $track, $con->opts->optGet( 'auto_integrations_track' ) );
		$this->assertSame( [ 'ninjaforms' ], $con->opts->optGet( 'form_spam_providers' ) );
		$this->assertSame( [ 'wordpress', 'easydigitaldownloads' ], $con->opts->optGet( 'user_form_providers' ) );
	}

	public function test_auto_integrations_respects_capabilities_and_normalizes_duplicates() :void {
		$this->enablePremiumCapabilities( [ 'thirdparty_scan_spam' ] );
		$this->installProviderFixture( 'ninjaforms', 'ninja-forms.php', 'Ninja_Forms', 'Ninja Forms' );
		$this->installProviderFixture(
			'easy-digital-downloads',
			'easy-digital-downloads.php',
			'Easy_Digital_Downloads',
			'Easy Digital Downloads'
		);

		$con = $this->requireController();
		$con->opts
			->optSet( 'enable_auto_integrations', 'Y' )
			->optSet( 'auto_integrations_track', [] )
			->optSet( 'form_spam_providers', [ 'ninjaforms', 'ninjaforms' ] )
			->optSet( 'user_form_providers', [ 'wordpress' ] )
			->store();
		$this->resetProviderCaches();

		$con->comps->integrations->resetExecution()->execute();

		$this->assertSame( [ 'ninjaforms' ], $con->opts->optGet( 'form_spam_providers' ) );
		$this->assertSame( [ 'wordpress' ], $con->opts->optGet( 'user_form_providers' ) );
	}

	private function prepareAdminRequest() :void {
		$this->applyCurrentRequestState(
			[
				'REQUEST_METHOD'  => 'GET',
				'REQUEST_URI'     => '/wp-admin/admin.php?page=shield',
				'SCRIPT_NAME'     => '/wp-admin/admin.php',
				'SCRIPT_FILENAME' => '/wp-admin/admin.php',
				'PHP_SELF'        => '/wp-admin/admin.php',
			],
			[],
			[],
			[
				'path'        => '/wp-admin/admin.php',
				'script_name' => 'admin.php',
				'wp_is_admin' => true,
				'wp_is_ajax'  => false,
				'wp_is_cron'  => false,
			]
		);
	}

	private function assertIntegrationState(
		array $states,
		string $slug,
		string $state,
		string $name,
		bool $hasCap
	) :void {
		$this->assertArrayHasKey( $slug, $states );
		$integration = $states[ $slug ];

		$this->assertSame( $slug, $integration[ 'slug' ] ?? null );
		$this->assertSame( $state, $integration[ 'state' ] ?? null );
		$this->assertSame( $name, $integration[ 'name' ] ?? null );
		$this->assertSame( $hasCap, $integration[ 'has_cap' ] ?? null );
		$keys = \array_keys( $integration );
		\sort( $keys );
		$this->assertSame( [ 'has_cap', 'name', 'slug', 'state' ], $keys );
	}
}
