<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results\Plugins;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	ActionsQueueAssetMetadataResolver,
	ActionsQueueAssetFileStatusDetail,
	ActionsQueueContextActionsBuilder,
	ActionsQueueDrillDownPresentationBuilder,
	ActionsQueueGroupContractBuilder,
	ActionsQueueGroupDefinitions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support\ServicesState;
use FernleafSystems\Wordpress\Services\Core\{
	General,
	Request,
	Users
};

class ActionsQueueGroupContractBuilderTest extends BaseUnitTest {

	private array $servicesSnapshot = [];

	protected function setUp() :void {
		parent::setUp();
		if ( !\defined( 'HOUR_IN_SECONDS' ) ) {
			\define( 'HOUR_IN_SECONDS', 3600 );
		}
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( '_n' )->alias(
			static fn( string $single, string $plural, int $count, ...$unused ) :string => $count === 1 ? $single : $plural
		);
		Functions\when( 'wp_create_nonce' )->alias( static fn( string $action ) :string => 'nonce-'.$action );
		Functions\when( 'wp_hash' )->alias(
			static fn( string $data, string $scheme = 'auth' ) :string => 'hash-'.$scheme.'-'.$data
		);
		Functions\when( 'get_rest_url' )->alias(
			static fn( $blog = null, string $path = '' ) :string => '/wp-json/'.\ltrim( $path, '/' )
		);
		Functions\when( 'rawurlencode_deep' )->alias(
			static function ( $value ) {
				if ( \is_array( $value ) ) {
					return \array_map(
						static fn( $item ) :string => \rawurlencode( (string)$item ),
						$value
					);
				}
				return \rawurlencode( (string)$value );
			}
		);
		Functions\when( 'add_query_arg' )->alias(
			static function ( array $params, string $url ) :string {
				if ( empty( $params ) ) {
					return $url;
				}
				$pieces = [];
				foreach ( $params as $key => $value ) {
					$pieces[] = $key.'='.( \is_array( $value ) ? \rawurlencode( (string)\json_encode( $value ) ) : $value );
				}
				return $url.( \strpos( $url, '?' ) === false ? '?' : '&' ).\implode( '&', $pieces );
			}
		);

		$this->servicesSnapshot = ServicesState::snapshot();
		ServicesState::mergeItems( [
			'service_wpgeneral' => new class extends General {
				public function ajaxURL() :string {
					return '/admin-ajax.php';
				}
			},
			'service_request' => new class extends Request {
				public function ip() :string {
					return '127.0.0.1';
				}

				public function ts( bool $update = true ) :int {
					return 1700000000;
				}
			},
			'service_wpusers' => new class extends Users {
				public function getCurrentWpUserId() {
					return 0;
				}
			},
		] );
	}

	protected function tearDown() :void {
		ServicesState::restore( $this->servicesSnapshot );
		parent::tearDown();
	}

	public function test_build_empty_group_keeps_scoped_asset_groups_on_direct_table_contract() :void {
		$builder = $this->newBuilder( [
			'plugin:example-plugin/example-plugin.php' => [
				'subject_type' => 'plugin',
				'subject_id'   => 'example-plugin/example-plugin.php',
				'title'        => 'Example Plugin',
				'icon_class'   => 'bi bi-plug-fill',
				'has_update'   => false,
			],
			'theme:example-theme'                     => [
				'subject_type' => 'theme',
				'subject_id'   => 'example-theme',
				'title'        => 'Example Theme',
				'icon_class'   => 'bi bi-palette-fill',
				'has_update'   => false,
			],
		] );

		$pluginGroup = $builder->buildEmptyGroup( 'plugins:example-plugin/example-plugin.php', 'Fix now' );
		$themeGroup = $builder->buildEmptyGroup( 'themes:example-theme', 'Fix now' );

		$this->assertSame( 'Example Plugin', $pluginGroup[ 'label' ] );
		$this->assertSame( 'direct_table', $pluginGroup[ 'detail_shell' ] );
		$this->assertSame( 'expandable', $pluginGroup[ 'card_type' ] );
		$this->assertSame( ActionsQueueAssetFileStatusDetail::class, $pluginGroup[ 'render_action_class' ] );
		$this->assertSame(
			[
				'display_context'         => 'actions_queue',
				'results_display_options' => [
					'include_ignored'  => false,
					'include_repaired' => false,
					'include_deleted'  => false,
					'ignored_only'     => false,
				],
				'subject_type'            => 'plugin',
				'subject_id'              => 'example-plugin/example-plugin.php',
			],
			$pluginGroup[ 'render_action_data' ]
		);
		$this->assertSame( 'Example Plugin', $pluginGroup[ 'selection' ][ 'label' ] );
		$this->assertSame( 'Example Plugin', $pluginGroup[ 'selection' ][ 'header' ][ 'title' ] );
		$this->assertSame( [], $pluginGroup[ 'selection' ][ 'header' ][ 'actions' ] ?? null );
		$this->assertFalse( $pluginGroup[ 'is_interactive' ] );

		$this->assertSame( 'Example Theme', $themeGroup[ 'label' ] );
		$this->assertSame( 'direct_table', $themeGroup[ 'detail_shell' ] );
		$this->assertSame( 'actions_queue', $themeGroup[ 'render_action_data' ][ 'display_context' ] );
		$this->assertSame(
			[
				'include_ignored'  => false,
				'include_repaired' => false,
				'include_deleted'  => false,
				'ignored_only'     => false,
			],
			$themeGroup[ 'render_action_data' ][ 'results_display_options' ] ?? []
		);
		$this->assertSame( 'theme', $themeGroup[ 'render_action_data' ][ 'subject_type' ] );
		$this->assertSame( 'example-theme', $themeGroup[ 'render_action_data' ][ 'subject_id' ] );
		$this->assertSame( 'Example Theme', $themeGroup[ 'selection' ][ 'header' ][ 'title' ] );
		$this->assertSame( [], $themeGroup[ 'selection' ][ 'header' ][ 'actions' ] ?? null );
	}

	public function test_build_empty_group_uses_generic_base_group_when_scoped_asset_cannot_be_resolved() :void {
		$builder = $this->newBuilder();

		$group = $builder->buildEmptyGroup( 'plugins:missing-plugin/missing-plugin.php', 'Fix now' );

		$this->assertSame( 'asset_cards', $group[ 'detail_shell' ] );
		$this->assertSame( Plugins::class, $group[ 'render_action_class' ] );
		$this->assertSame( $group[ 'label' ], $group[ 'selection' ][ 'label' ] );
		$this->assertSame( $group[ 'label' ], $group[ 'selection' ][ 'header' ][ 'title' ] );
	}

	/**
	 * @param array<string,array<string,mixed>> $metadataByAsset
	 */
	private function newBuilder( array $metadataByAsset = [] ) :ActionsQueueGroupContractBuilder {
		$resolver = new class( $metadataByAsset ) extends ActionsQueueAssetMetadataResolver {

			private array $metadataByAsset;

			public function __construct( array $metadataByAsset ) {
				$this->metadataByAsset = $metadataByAsset;
			}

			public function resolve( string $assetType, string $assetKey ) :?array {
				return $this->metadataByAsset[ $assetType.':'.$assetKey ] ?? null;
			}
		};

		return new ActionsQueueGroupContractBuilder(
			new ActionsQueueGroupDefinitions(),
			new ActionsQueueDrillDownPresentationBuilder(),
			$resolver,
			null,
			new class extends ActionsQueueContextActionsBuilder {
				public function buildForGroup(
					string $definitionKey,
					string $label,
					string $detailShell,
					int $itemCount,
					array $renderActionData
				) :array {
					return [];
				}
			}
		);
	}
}
