<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\ActionRouter\Render;

use Brain\Monkey\Functions;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Scans\Results\Plugins;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages\{
	ActionsQueueAssetMetadataResolver,
	ActionsQueueAssetFileStatusDetail,
	ActionsQueueDrillDownPresentationBuilder,
	ActionsQueueGroupContractBuilder,
	ActionsQueueGroupDefinitions
};
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\BaseUnitTest;

class ActionsQueueGroupContractBuilderTest extends BaseUnitTest {

	protected function setUp() :void {
		parent::setUp();
		Functions\when( '__' )->alias( static fn( string $text ) :string => $text );
		Functions\when( '_n' )->alias(
			static fn( string $single, string $plural, int $count, ...$unused ) :string => $count === 1 ? $single : $plural
		);
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
					'include_ignored' => false,
					'ignored_only'    => false,
				],
				'subject_type'            => 'plugin',
				'subject_id'              => 'example-plugin/example-plugin.php',
			],
			$pluginGroup[ 'render_action_data' ]
		);
		$this->assertSame( 'Example Plugin', $pluginGroup[ 'selection' ][ 'label' ] );
		$this->assertSame( 'Example Plugin', $pluginGroup[ 'selection' ][ 'header' ][ 'title' ] );
		$this->assertFalse( $pluginGroup[ 'is_interactive' ] );

		$this->assertSame( 'Example Theme', $themeGroup[ 'label' ] );
		$this->assertSame( 'direct_table', $themeGroup[ 'detail_shell' ] );
		$this->assertSame( 'actions_queue', $themeGroup[ 'render_action_data' ][ 'display_context' ] );
		$this->assertSame( 'theme', $themeGroup[ 'render_action_data' ][ 'subject_type' ] );
		$this->assertSame( 'example-theme', $themeGroup[ 'render_action_data' ][ 'subject_id' ] );
		$this->assertSame( 'Example Theme', $themeGroup[ 'selection' ][ 'header' ][ 'title' ] );
	}

	public function test_build_empty_group_uses_generic_base_group_when_scoped_asset_cannot_be_resolved() :void {
		$builder = $this->newBuilder();

		$group = $builder->buildEmptyGroup( 'plugins:missing-plugin/missing-plugin.php', 'Fix now' );

		$this->assertSame( 'Plugin Files', $group[ 'label' ] );
		$this->assertSame( 'asset_cards', $group[ 'detail_shell' ] );
		$this->assertSame( Plugins::class, $group[ 'render_action_class' ] );
		$this->assertSame( 'Plugin Files', $group[ 'selection' ][ 'label' ] );
		$this->assertSame( 'Plugin Files', $group[ 'selection' ][ 'header' ][ 'title' ] );
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
			$resolver
		);
	}
}
