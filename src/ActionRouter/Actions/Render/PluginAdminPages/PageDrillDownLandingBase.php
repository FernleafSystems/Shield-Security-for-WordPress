<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

/**
 * @phpstan-type DrillLayerContextInput array{
 *   path?:list<string>,
 *   focus?:string,
 *   next_step?:string
 * }
 * @phpstan-type RawDrillLayer array{
 *   key:non-empty-string,
 *   label:string,
 *   body:string,
 *   badge?:string,
 *   badge_status?:string,
 *   context?:DrillLayerContextInput
 * }
 * @phpstan-type DrillLayerContext array{
 *   path:list<string>,
 *   focus:string,
 *   next_step:string
 * }
 * @phpstan-type DrillLayer array{
 *   key:non-empty-string,
 *   label:string,
 *   badge:string,
 *   badge_status:string,
 *   body:string,
 *   context:DrillLayerContext
 * }
 * @phpstan-type DrillShell array{
 *   id:string,
 *   mode:string,
 *   active_index:int,
 *   layers:list<DrillLayer>
 * }
 * @phpstan-type DrillContextCard array{
 *   shell_id:string,
 *   mode:string,
 *   initial_context:DrillLayerContext
 * }
 */
abstract class PageDrillDownLandingBase extends PageModeLandingBase {

	private const VALID_BADGE_STATUSES = [
		'critical',
		'warning',
		'good',
		'info',
		'neutral',
	];

	/**
	 * @return list<RawDrillLayer>
	 */
	abstract protected function getLayers() :array;

	abstract protected function getActiveLayerIndex() :int;

	protected function getLandingVars() :array {
		$vars = parent::getLandingVars();
		$mode = $this->getLandingMode();
		$layers = $this->normalizeLayers( $this->getLayers() );
		$activeIndex = $this->clampActiveLayerIndex( $this->getActiveLayerIndex(), \count( $layers ) );
		$shellId = sanitize_key( $mode.'_drill_shell' );

		$vars[ 'drill_shell' ] = [
			'id'           => $shellId,
			'mode'         => $mode,
			'active_index' => $activeIndex,
			'layers'       => $layers,
		];
		$vars[ 'drill_context_card' ] = [
			'shell_id'        => $shellId,
			'mode'            => $mode,
			'initial_context' => !empty( $layers )
				? $layers[ $activeIndex ][ 'context' ]
				: $this->emptyLayerContext(),
		];

		return $vars;
	}

	/**
	 * @param list<RawDrillLayer> $layers
	 * @return list<DrillLayer>
	 */
	private function normalizeLayers( array $layers ) :array {
		$normalized = [];

		foreach ( $layers as $layer ) {
			if ( \count( $normalized ) >= 3 ) {
				break;
			}

			$key = sanitize_key( $layer[ 'key' ] );
			if ( empty( $key ) ) {
				continue;
			}

			$normalized[] = [
				'key'          => $key,
				'label'        => $layer[ 'label' ],
				'badge'        => $layer[ 'badge' ] ?? '',
				'badge_status' => $this->sanitizeBadgeStatus( $layer[ 'badge_status' ] ?? '' ),
				'body'         => $layer[ 'body' ],
				'context'      => $this->normalizeLayerContext( $layer[ 'context' ] ?? [] ),
			];
		}

		return $normalized;
	}

	private function sanitizeBadgeStatus( string $status ) :string {
		$status = sanitize_key( $status );
		if ( !\in_array( $status, self::VALID_BADGE_STATUSES, true ) ) {
			$status = 'neutral';
		}
		return $status;
	}

	/**
	 * @param DrillLayerContextInput $context
	 * @return DrillLayerContext
	 */
	private function normalizeLayerContext( array $context ) :array {
		return [
			'path'      => \array_values( \array_filter(
				\array_map(
					static fn( string $segment ) :string => \trim( $segment ),
					$context[ 'path' ] ?? []
				),
				static fn( string $segment ) :bool => $segment !== ''
			) ),
			'focus'     => \trim( $context[ 'focus' ] ?? '' ),
			'next_step' => \trim( $context[ 'next_step' ] ?? '' ),
		];
	}

	private function clampActiveLayerIndex( int $activeIndex, int $layerCount ) :int {
		if ( $layerCount < 1 || $activeIndex < 0 || $activeIndex >= $layerCount ) {
			return 0;
		}

		return $activeIndex;
	}

	/**
	 * @return DrillLayerContext
	 */
	private function emptyLayerContext() :array {
		return [
			'path'      => [],
			'focus'     => '',
			'next_step' => '',
		];
	}
}
