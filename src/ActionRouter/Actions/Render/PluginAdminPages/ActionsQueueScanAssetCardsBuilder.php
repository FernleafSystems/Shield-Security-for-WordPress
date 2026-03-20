<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Investigation\InvestigationTableContract;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\{
	RetrieveBase,
	RetrieveItems
};
use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Core\VOs\Assets\{
	WpPluginVo,
	WpThemeVo
};
use FernleafSystems\Wordpress\Services\Services;

/**
 * @phpstan-type QueueAssetMetadata array{
 *   subject_type:string,
 *   subject_id:string,
 *   title:string,
 *   icon_class:string,
 *   has_update:bool
 * }
 */
class ActionsQueueScanAssetCardsBuilder {

	use PluginControllerConsumer;

	/**
	 * @return list<array<string,mixed>>
	 */
	public function buildIssueRecords( string $assetType, array $resultsDisplayOptions = [] ) :array {
		$options = $this->options()->normalize( $resultsDisplayOptions );
		$groupedBySlug = [];

		foreach ( $this->retrieveAssetResultItems( $assetType, $options ) as $item ) {
			$slug = (string)( $item->ptg_slug ?? '' );
			if ( $slug === '' ) {
				continue;
			}
			$groupedBySlug[ $slug ][] = $item;
		}

		$records = [];
		foreach ( $groupedBySlug as $slug => $items ) {
			$metadata = $this->resolveAssetMetadata( $assetType, $slug );
			if ( $metadata === null ) {
				continue;
			}

			$fileCount = \count( $items );
			$records[] = [
				'key'               => $slug,
				'panel_id'          => 'actions-queue-'.$assetType.'-card-'.\sanitize_key( $slug ),
				'panel_target'      => 'actions-queue-'.$assetType.'-'.\sanitize_key( $slug ),
				'expand_target'     => 'scan-files-'.$assetType.'-'.\sanitize_key( $slug ),
				'status'            => 'warning',
				'icon_class'        => $metadata[ 'icon_class' ],
				'title'             => $metadata[ 'title' ],
				'stat_text'         => $this->buildQueueAssetStatText( $fileCount, $options ),
				'meta_text'         => $metadata[ 'subject_id' ],
				'show_meta_in_tile' => true,
				'count_badge'       => $fileCount,
				'actions'           => $this->buildAssetActions( $metadata, $assetType ),
				'table'             => $this->buildFileStatusTable(
					$metadata[ 'subject_type' ],
					$metadata[ 'subject_id' ],
					$options
				),
				'render_action'     => [],
			];
		}

		\usort( $records, static function ( array $a, array $b ) :int {
			$countCmp = $b[ 'count_badge' ] <=> $a[ 'count_badge' ];
			return $countCmp !== 0
				? $countCmp
				: \strcmp( $a[ 'title' ], $b[ 'title' ] );
		} );

		return $records;
	}

	/**
	 * @param array{include_ignored:bool,ignored_only:bool} $resultsDisplayOptions
	 * @return list<object>
	 */
	protected function retrieveAssetResultItems( string $assetType, array $resultsDisplayOptions ) :array {
		return ( new RetrieveItems() )
			->setScanController( self::con()->comps->scans->AFS() )
			->addWheres( [
				\sprintf(
					"%s.`meta_key`='%s'",
					RetrieveBase::ABBR_RESULTITEMMETA,
					$assetType === 'plugin' ? 'is_in_plugin' : 'is_in_theme'
				),
			] )
			->retrieveForResultsTables( $resultsDisplayOptions )
			->getItems();
	}

	/**
	 * @return QueueAssetMetadata|null
	 */
	protected function resolveAssetMetadata( string $assetType, string $slug ) :?array {
		if ( $assetType === 'plugin' ) {
			$asset = Services::WpPlugins()->getPluginAsVo( $slug, true );
			if ( !$asset instanceof WpPluginVo ) {
				return null;
			}

			return [
				'subject_type' => InvestigationTableContract::SUBJECT_TYPE_PLUGIN,
				'subject_id'   => (string)$asset->file,
				'title'        => (string)$asset->Title,
				'icon_class'   => 'bi bi-plug-fill',
				'has_update'   => $asset->hasUpdate(),
			];
		}

		$asset = Services::WpThemes()->getThemeAsVo( $slug, true );
		if ( !$asset instanceof WpThemeVo ) {
			return null;
		}

		return [
			'subject_type' => InvestigationTableContract::SUBJECT_TYPE_THEME,
			'subject_id'   => (string)$asset->stylesheet,
			'title'        => (string)$asset->Name,
			'icon_class'   => 'bi bi-palette-fill',
			'has_update'   => $asset->hasUpdate(),
		];
	}

	/**
	 * @param QueueAssetMetadata $metadata
	 * @return list<array<string,mixed>>
	 */
	protected function buildAssetActions( array $metadata, string $assetType ) :array {
		$actions = [];
		if ( $metadata[ 'has_update' ] ) {
			$actions[] = [
				'type'       => 'update',
				'label'      => __( 'Update', 'wp-simple-firewall' ),
				'href'       => \admin_url( 'update-core.php' ),
				'icon'       => 'bi bi-arrow-up-circle-fill',
				'tooltip'    => __( 'Go to updates', 'wp-simple-firewall' ),
				'attributes' => [],
			];
		}
		if ( $assetType === 'plugin' ) {
			$actions[] = [
				'type'       => 'deactivate',
				'label'      => __( 'Deactivate', 'wp-simple-firewall' ),
				'href'       => \admin_url( 'plugins.php' ),
				'icon'       => 'bi bi-power',
				'tooltip'    => __( 'Go to plugins', 'wp-simple-firewall' ),
				'attributes' => [],
			];
		}
		return $actions;
	}

	/**
	 * @param array{include_ignored:bool,ignored_only:bool} $resultsDisplayOptions
	 * @return array<string,mixed>
	 */
	protected function buildFileStatusTable( string $subjectType, string $subjectId, array $resultsDisplayOptions ) :array {
		return ( new InvestigationFileStatusTableContractBuilder() )->build(
			$subjectType,
			$subjectId,
			$this->buildFullLogHref(),
			$this->options()->buildActionData( $resultsDisplayOptions )
		);
	}

	protected function buildFullLogHref() :string {
		return self::con()->plugin_urls->actionsQueueScans();
	}

	/**
	 * @param array{include_ignored:bool,ignored_only:bool} $resultsDisplayOptions
	 */
	protected function buildQueueAssetStatText( int $fileCount, array $resultsDisplayOptions ) :string {
		if ( $resultsDisplayOptions[ 'ignored_only' ] ) {
			return \sprintf(
				_n( '%s ignored file is available for review', '%s ignored files are available for review', $fileCount, 'wp-simple-firewall' ),
				$fileCount
			);
		}

		return \sprintf(
			_n( '%s file needs review', '%s files need review', $fileCount, 'wp-simple-firewall' ),
			$fileCount
		);
	}

	private function options() :ActionsQueueScanResultsOptions {
		return new ActionsQueueScanResultsOptions();
	}
}
