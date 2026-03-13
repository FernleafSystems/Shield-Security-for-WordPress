<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Widgets\MaintenanceIssueStateProvider;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class MaintenanceIssueStateProviderTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( '_n' )->alias(
			static fn( string $single, string $plural, int $count, ...$unused ) :string => $count === 1 ? $single : $plural
		);
	}

	public function test_normalize_ignored_items_enforces_known_buckets_and_valid_identifiers() :void {
		$provider = new MaintenanceIssueStateProviderTestDouble(
			[
				[ 'key' => 'wp_plugins_updates', 'zone' => 'maintenance', 'component_class' => 'plugin-updates', 'availability_strategy' => 'always' ],
				[ 'key' => 'system_php_version', 'zone' => 'maintenance', 'component_class' => 'php-version', 'availability_strategy' => 'always' ],
			],
			[],
			[],
			[]
		);

		$normalized = $provider->normalizeIgnoredItems(
			[
				'wp_plugins_updates' => [ 'plugin-two/plugin.php', 'plugin-one/plugin.php', 'plugin-one/plugin.php', '' ],
				'system_php_version' => [ MaintenanceIssueStateProvider::SINGLETON_TOKEN, 'bad-value' ],
			],
			[
				'wp_plugins_updates' => [ 'plugin-one/plugin.php', 'plugin-two/plugin.php' ],
				'system_php_version' => [ MaintenanceIssueStateProvider::SINGLETON_TOKEN ],
			]
		);

		$this->assertSame(
			[
				'wp_plugins_updates',
				'system_php_version',
			],
			\array_keys( $normalized )
		);
		$this->assertSame(
			[ 'plugin-one/plugin.php', 'plugin-two/plugin.php' ],
			$normalized['wp_plugins_updates']
		);
		$this->assertSame(
			[ MaintenanceIssueStateProvider::SINGLETON_TOKEN ],
			$normalized['system_php_version']
		);
	}

	public function test_build_states_applies_partial_and_full_ignore_rules() :void {
		$provider = new MaintenanceIssueStateProviderTestDouble(
			[
				[ 'key' => 'wp_plugins_updates', 'zone' => 'maintenance', 'component_class' => 'plugin-updates', 'availability_strategy' => 'always' ],
				[ 'key' => 'system_php_version', 'zone' => 'maintenance', 'component_class' => 'php-version', 'availability_strategy' => 'always' ],
			],
			[
				'plugin-updates' => [
					'title'                  => 'Plugins With Updates',
					'desc_protected'         => 'All available plugin updates have been applied.',
					'desc_unprotected'       => 'Plugins need updates.',
					'is_protected'           => false,
					'is_critical'            => false,
					'is_applicable'          => true,
					'href_full'              => '/wp-admin/plugins.php',
					'href_full_target_blank' => false,
					'fix'                    => 'Update',
				],
				'php-version' => [
					'title'                  => 'PHP Version',
					'desc_protected'         => 'PHP looks healthy.',
					'desc_unprotected'       => 'PHP is old.',
					'is_protected'           => false,
					'is_critical'            => false,
					'is_applicable'          => true,
					'href_full'              => 'https://example.com/php',
					'href_full_target_blank' => true,
					'fix'                    => 'Review',
				],
			],
			[
				'wp_plugins_updates' => [ 'plugin-one/plugin.php', 'plugin-two/plugin.php', 'plugin-three/plugin.php' ],
				'system_php_version' => [ MaintenanceIssueStateProvider::SINGLETON_TOKEN ],
			],
			[
				'wp_plugins_updates' => [ 'plugin-two/plugin.php', 'stale/plugin.php' ],
				'system_php_version' => [ MaintenanceIssueStateProvider::SINGLETON_TOKEN ],
			]
		);

		$states = $provider->buildStates();

		$this->assertSame( 2, $states['wp_plugins_updates']['count'] );
		$this->assertSame( 1, $states['wp_plugins_updates']['ignored_count'] );
		$this->assertSame( 'warning', $states['wp_plugins_updates']['severity'] );
		$this->assertStringContainsString( '2 plugin updates', $states['wp_plugins_updates']['description'] );
		$this->assertStringContainsString( 'ignored', $states['wp_plugins_updates']['description'] );
		$this->assertSame( 0, $states['system_php_version']['count'] );
		$this->assertSame( 1, $states['system_php_version']['ignored_count'] );
		$this->assertSame( 'good', $states['system_php_version']['severity'] );
		$this->assertStringContainsString( 'ignored', $states['system_php_version']['description'] );
	}
}

class MaintenanceIssueStateProviderTestDouble extends MaintenanceIssueStateProvider {

	private array $definitions;
	private array $components;
	private array $issueIdentifiersByKey;
	private array $ignoredItems;

	public function __construct(
		array $definitions,
		array $components,
		array $issueIdentifiersByKey,
		array $ignoredItems
	) {
		$this->definitions = $definitions;
		$this->components = $components;
		$this->issueIdentifiersByKey = $issueIdentifiersByKey;
		$this->ignoredItems = $ignoredItems;
	}

	protected function getDefinitions() :array {
		return $this->definitions;
	}

	protected function buildComponent( string $componentClass ) :array {
		return $this->components[ $componentClass ];
	}

	protected function getStoredIgnoredItems() :array {
		return $this->ignoredItems;
	}

	protected function issueIdentifiersForKey( string $key, array $component ) :array {
		return $this->issueIdentifiersByKey[ $key ];
	}
}
