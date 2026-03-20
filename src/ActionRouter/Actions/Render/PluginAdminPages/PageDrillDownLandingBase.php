<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

/**
 * @phpstan-type DrillLayerHeaderInput array{
 *   compact_back_label?:string,
 *   active_back_label?:string,
 *   breadcrumb_label?:string,
 *   title?:string,
 *   meta?:string,
 *   summary?:string,
 *   focus?:string,
 *   next_step?:string,
 *   icon_class?:string,
 *   badge?:string,
 *   badge_status?:string,
 *   color_key?:string
 * }
 * @phpstan-type RawDrillLayer array{
 *   key:non-empty-string,
 *   body:string,
 *   header?:DrillLayerHeaderInput
 * }
 * @phpstan-type DrillLayerHeader array{
 *   compact_back_label:string,
 *   active_back_label:string,
 *   breadcrumb_label:string,
 *   title:string,
 *   meta:string,
 *   summary:string,
 *   focus:string,
 *   next_step:string,
 *   icon_class:string,
 *   badge:string,
 *   badge_status:string,
 *   color_key:string
 * }
 * @phpstan-type DrillLayer array{
 *   key:non-empty-string,
 *   body:string,
 *   header:DrillLayerHeader,
 *   header_json:string
 * }
 * @phpstan-type DrillShell array{
 *   id:string,
 *   mode:string,
 *   active_index:int,
 *   layers:list<DrillLayer>
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

		$vars[ 'drill_shell' ] = [
			'id'           => sanitize_key( $mode.'_drill_shell' ),
			'mode'         => $mode,
			'active_index' => $activeIndex,
			'layers'       => $layers,
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

			$header = $this->normalizeLayerHeader( $layer[ 'header' ] ?? [] );
			$normalized[] = [
				'key'         => $key,
				'body'        => $layer[ 'body' ],
				'header'      => $header,
				'header_json' => $this->encodeJson( $header ),
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
	 * @param DrillLayerHeaderInput $header
	 * @return DrillLayerHeader
	 */
	private function normalizeLayerHeader( array $header ) :array {
		return \array_merge(
			[
			'compact_back_label' => \trim( $header[ 'compact_back_label' ] ?? '' ),
			'active_back_label'  => \trim( $header[ 'active_back_label' ] ?? '' ),
			'meta'               => \trim( $header[ 'meta' ] ?? '' ),
			],
			$this->normalizeOperatorChromeStep( [
				'breadcrumb_label' => $header[ 'breadcrumb_label' ] ?? '',
				'title'            => $header[ 'title' ] ?? '',
				'summary'          => $header[ 'summary' ] ?? '',
				'focus'            => $header[ 'focus' ] ?? '',
				'next_step'        => $header[ 'next_step' ] ?? '',
				'icon_class'       => $header[ 'icon_class' ] ?? '',
				'badge'            => $header[ 'badge' ] ?? '',
				'badge_status'     => $this->sanitizeBadgeStatus( $header[ 'badge_status' ] ?? '' ),
				'color_key'        => $header[ 'color_key' ] ?? ( $header[ 'badge_status' ] ?? '' ),
			] )
		);
	}

	private function clampActiveLayerIndex( int $activeIndex, int $layerCount ) :int {
		if ( $layerCount < 1 || $activeIndex < 0 || $activeIndex >= $layerCount ) {
			return 0;
		}

		return $activeIndex;
	}

}
