<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules;

if ( !\function_exists( __NAMESPACE__.'\\shield_security_get_plugin' ) ) {
	function shield_security_get_plugin() {
		return \FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\PluginStore::$plugin;
	}
}

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\{
	MaintenanceItemIgnore,
	MaintenanceItemUnignore
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\MaintenanceIssueStateProvider;
use FernleafSystems\Wordpress\Plugin\Shield\Controller\Controller;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\{
	PluginControllerInstaller,
	ServicesState
};
use FernleafSystems\Wordpress\Services\Core\Users;

class MaintenanceItemActionsTest extends BaseUnitTest {

	private OptsStoreStub $opts;
	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( 'sanitize_key' )->alias(
			static fn( $text ) :string => \is_string( $text ) ? \strtolower( \trim( $text ) ) : ''
		);
		$this->opts = new OptsStoreStub();
		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::installItems( [
			'service_wpusers' => new class extends Users {
				public function isUserLoggedIn() :bool {
					return false;
				}
			},
		] );
		$this->installControllerStub();
	}

	protected function tearDown() :void {
		PluginControllerInstaller::reset();
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_ignore_requires_identifier_for_sub_item_checks() :void {
		$action = new MaintenanceItemIgnoreTestDouble(
			[
				'wp_plugins_updates' => [ 'plugin-one/plugin.php' ],
			],
			[
				'wp_plugins_updates' => true,
			],
			[
				'maintenance_key' => 'wp_plugins_updates',
			]
		);

		$action->process();

		$this->assertFalse( (bool)( $action->response()->payload()[ 'success' ] ?? true ) );
		$this->assertStringContainsString( 'identifier', (string)( $action->response()->payload()[ 'message' ] ?? '' ) );
		$this->assertSame( [], $this->opts->optGet( MaintenanceIssueStateProvider::OPT_KEY )[ 'wp_plugins_updates' ] ?? [] );
	}

	public function test_ignore_stores_requested_sub_item_identifier() :void {
		$action = new MaintenanceItemIgnoreTestDouble(
			[
				'wp_plugins_updates' => [ 'plugin-one/plugin.php', 'plugin-two/plugin.php' ],
			],
			[
				'wp_plugins_updates' => true,
			],
			[
				'maintenance_key' => 'wp_plugins_updates',
				'identifier'      => 'plugin-two/plugin.php',
			]
		);

		$action->process();

		$this->assertTrue( (bool)( $action->response()->payload()[ 'success' ] ?? false ) );
		$this->assertSame(
			[ 'plugin-two/plugin.php' ],
			$this->opts->optGet( MaintenanceIssueStateProvider::OPT_KEY )[ 'wp_plugins_updates' ] ?? []
		);
	}

	public function test_ignore_singleton_uses_self_token() :void {
		$action = new MaintenanceItemIgnoreTestDouble(
			[
				'system_php_version' => [ MaintenanceIssueStateProvider::SINGLETON_TOKEN ],
			],
			[],
			[
				'maintenance_key' => 'system_php_version',
			]
		);

		$action->process();

		$this->assertTrue( (bool)( $action->response()->payload()[ 'success' ] ?? false ) );
		$this->assertSame(
			[ MaintenanceIssueStateProvider::SINGLETON_TOKEN ],
			$this->opts->optGet( MaintenanceIssueStateProvider::OPT_KEY )[ 'system_php_version' ] ?? []
		);
	}

	public function test_unignore_removes_identifier_and_remains_idempotent() :void {
		$this->opts->optSet( MaintenanceIssueStateProvider::OPT_KEY, [
			'wp_plugins_updates' => [ 'plugin-one/plugin.php' ],
		] );

		$action = new MaintenanceItemUnignoreTestDouble(
			[
				'wp_plugins_updates' => [ 'plugin-one/plugin.php' ],
			],
			[
				'wp_plugins_updates' => true,
			],
			[
				'maintenance_key' => 'wp_plugins_updates',
				'identifier'      => 'plugin-one/plugin.php',
			]
		);

		$action->process();
		$action->process();

		$this->assertTrue( (bool)( $action->response()->payload()[ 'success' ] ?? false ) );
		$this->assertSame( [], $this->opts->optGet( MaintenanceIssueStateProvider::OPT_KEY )[ 'wp_plugins_updates' ] ?? [] );
	}

	private function installControllerStub() :void {
		/** @var Controller $controller */
		$controller = ( new \ReflectionClass( Controller::class ) )->newInstanceWithoutConstructor();
		$controller->opts = $this->opts;
		$controller->this_req = (object)[
			'request_bypasses_all_restrictions' => false,
			'is_ip_blocked'                     => false,
			'wp_is_ajax'                        => false,
			'is_security_admin'                 => false,
		];
		PluginControllerInstaller::install( $controller );
	}
}

class MaintenanceItemIgnoreTestDouble extends MaintenanceItemIgnore {

	private MaintenanceIssueStateProviderActionTestDouble $provider;

	public function __construct( array $currentIssueIdentifiersByKey, array $subItemSupportByKey, array $data ) {
		parent::__construct( $data );
		$this->provider = new MaintenanceIssueStateProviderActionTestDouble( $currentIssueIdentifiersByKey, $subItemSupportByKey );
	}

	protected function getMinimumUserAuthCapability() :string {
		return '';
	}

	protected function buildMaintenanceIssueStateProvider() :MaintenanceIssueStateProvider {
		return $this->provider;
	}
}

class MaintenanceItemUnignoreTestDouble extends MaintenanceItemUnignore {

	private MaintenanceIssueStateProviderActionTestDouble $provider;

	public function __construct( array $currentIssueIdentifiersByKey, array $subItemSupportByKey, array $data ) {
		parent::__construct( $data );
		$this->provider = new MaintenanceIssueStateProviderActionTestDouble( $currentIssueIdentifiersByKey, $subItemSupportByKey );
	}

	protected function getMinimumUserAuthCapability() :string {
		return '';
	}

	protected function buildMaintenanceIssueStateProvider() :MaintenanceIssueStateProvider {
		return $this->provider;
	}
}

class MaintenanceIssueStateProviderActionTestDouble extends MaintenanceIssueStateProvider {

	private array $currentIssueIdentifiersByKey;
	private array $subItemSupportByKey;

	public function __construct( array $currentIssueIdentifiersByKey, array $subItemSupportByKey ) {
		$this->currentIssueIdentifiersByKey = $currentIssueIdentifiersByKey;
		$this->subItemSupportByKey = $subItemSupportByKey;
	}

	public function currentIssueIdentifiersByKey() :array {
		return $this->normalizeKnownKeys(
			$this->currentIssueIdentifiersByKey,
			false
		);
	}

	public function isKnownMaintenanceKey( string $key ) :bool {
		return \array_key_exists( $key, $this->currentIssueIdentifiersByKey );
	}

	public function supportsSubItems( string $key ) :bool {
		return (bool)( $this->subItemSupportByKey[ $key ] ?? false );
	}

	public function normalizeIgnoredItems( array $ignoredItems, ?array $validIdentifiersByKey = null ) :array {
		$normalized = $this->normalizeKnownKeys( [], true );

		foreach ( \array_keys( $normalized ) as $key ) {
			$values = \is_array( $ignoredItems[ $key ] ?? null ) ? $ignoredItems[ $key ] : [];
			$values = \array_values( \array_unique( \array_filter( \array_map(
				static fn( $value ) :string => \is_scalar( $value ) ? \trim( (string)$value ) : '',
				$values
			) ) ) );

			if ( $validIdentifiersByKey !== null ) {
				$values = \array_values( \array_intersect( $values, $validIdentifiersByKey[ $key ] ?? [] ) );
			}

			$normalized[ $key ] = $values;
		}

		return $normalized;
	}

	/**
	 * @return array<string,list<string>>
	 */
	private function normalizeKnownKeys( array $values, bool $forceEmpty ) :array {
		$normalized = [];
		foreach ( \array_keys( $this->currentIssueIdentifiersByKey ) as $key ) {
			$normalized[ $key ] = $forceEmpty
				? []
				: \array_values( \array_map(
					static fn( $identifier ) :string => (string)$identifier,
					$values[ $key ] ?? []
				) );
		}
		return $normalized;
	}
}

class OptsStoreStub {

	private array $values = [
		MaintenanceIssueStateProvider::OPT_KEY => [],
	];

	public function optGet( string $key ) {
		return $this->values[ $key ] ?? null;
	}

	public function optSet( string $key, $value ) :self {
		$this->values[ $key ] = $value;
		return $this;
	}
}
