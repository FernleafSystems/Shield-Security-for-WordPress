<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

/**
 * @phpstan-import-type DrillLayerHeaderInput from OperatorChromeContract
 * @phpstan-import-type DrillLayerHeader from OperatorChromeContract
 * @phpstan-type RawDrillLayer array{
 *   key:non-empty-string,
 *   body:string,
 *   header?:DrillLayerHeaderInput
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

			$header = OperatorChromeContract::normalizeHeader( $layer[ 'header' ] ?? [] );
			$normalized[] = [
				'key'         => $key,
				'body'        => $layer[ 'body' ],
				'header'      => $header,
				'header_json' => OperatorChromeContract::encodeJson( $header ),
			];
		}

		return $normalized;
	}

	private function clampActiveLayerIndex( int $activeIndex, int $layerCount ) :int {
		if ( $layerCount < 1 || $activeIndex < 0 || $activeIndex >= $layerCount ) {
			return 0;
		}

		return $activeIndex;
	}

}
