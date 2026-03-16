<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

abstract class PageDrillDownLandingBase extends PageModeLandingBase {

	private const VALID_BADGE_STATUSES = [
		'critical',
		'warning',
		'good',
		'info',
		'neutral',
	];

	/**
	 * @return list<array{
	 *   key:string,
	 *   label?:string,
	 *   badge?:string,
	 *   badge_status?:string,
	 *   body?:string,
	 *   context?:array{path?:array<int|string,mixed>,focus?:mixed,next_step?:mixed}
	 * }>
	 */
	abstract protected function getLayers() :array;

	abstract protected function getActiveLayerIndex() :int;

	protected function getLandingVars() :array {
		$vars = parent::getLandingVars();
		$mode = $this->getLandingMode();
		$layers = $this->normalizeLayers( $this->getLayers() );
		$activeIndex = $this->clampActiveLayerIndex( $this->getActiveLayerIndex(), \count( $layers ) );
		$layers = $this->applyActiveLayer( $layers, $activeIndex );
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
	 * @param array<int,mixed> $layers
	 * @return list<array{
	 *   key:string,
	 *   label:string,
	 *   badge:string,
	 *   badge_status:string,
	 *   body:string,
	 *   context:array{path:list<string>,focus:string,next_step:string},
	 *   is_active:bool
	 * }>
	 */
	private function normalizeLayers( array $layers ) :array {
		$normalized = [];

		foreach ( $layers as $layer ) {
			if ( \count( $normalized ) >= 3 ) {
				break;
			}
			if ( !\is_array( $layer ) ) {
				continue;
			}

			$key = sanitize_key( (string)( $layer[ 'key' ] ?? '' ) );
			if ( empty( $key ) ) {
				continue;
			}

			$normalized[] = [
				'key'          => $key,
				'label'        => (string)( $layer[ 'label' ] ?? '' ),
				'badge'        => (string)( $layer[ 'badge' ] ?? '' ),
				'badge_status' => $this->sanitizeBadgeStatus( (string)( $layer[ 'badge_status' ] ?? '' ) ),
				'body'         => (string)( $layer[ 'body' ] ?? '' ),
				'context'      => $this->normalizeLayerContext( $layer[ 'context' ] ?? [] ),
				'is_active'    => false,
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
	 * @param mixed $context
	 * @return array{path:list<string>,focus:string,next_step:string}
	 */
	private function normalizeLayerContext( $context ) :array {
		if ( !\is_array( $context ) ) {
			return $this->emptyLayerContext();
		}

		$path = $context[ 'path' ] ?? [];
		if ( !\is_array( $path ) ) {
			$path = [];
		}

		return [
			'path'      => \array_values( \array_filter(
				\array_map(
					static fn( $segment ) :string => \trim( (string)$segment ),
					$path
				),
				static fn( string $segment ) :bool => $segment !== ''
			) ),
			'focus'     => \trim( (string)( $context[ 'focus' ] ?? '' ) ),
			'next_step' => \trim( (string)( $context[ 'next_step' ] ?? '' ) ),
		];
	}

	/**
	 * @param list<array{
	 *   key:string,
	 *   label:string,
	 *   badge:string,
	 *   badge_status:string,
	 *   body:string,
	 *   context:array{path:list<string>,focus:string,next_step:string},
	 *   is_active:bool
	 * }> $layers
	 * @return list<array{
	 *   key:string,
	 *   label:string,
	 *   badge:string,
	 *   badge_status:string,
	 *   body:string,
	 *   context:array{path:list<string>,focus:string,next_step:string},
	 *   is_active:bool
	 * }>
	 */
	private function applyActiveLayer( array $layers, int $activeIndex ) :array {
		if ( empty( $layers ) ) {
			return $layers;
		}

		$activeIndex = $this->clampActiveLayerIndex( $activeIndex, \count( $layers ) );

		foreach ( $layers as $index => &$layer ) {
			$layer[ 'is_active' ] = $index === $activeIndex;
		}
		unset( $layer );

		return $layers;
	}

	private function clampActiveLayerIndex( int $activeIndex, int $layerCount ) :int {
		if ( $layerCount < 1 || $activeIndex < 0 || $activeIndex >= $layerCount ) {
			return 0;
		}

		return $activeIndex;
	}

	/**
	 * @return array{path:list<string>,focus:string,next_step:string}
	 */
	private function emptyLayerContext() :array {
		return [
			'path'      => [],
			'focus'     => '',
			'next_step' => '',
		];
	}
}
