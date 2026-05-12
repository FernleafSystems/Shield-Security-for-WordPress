<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\PluginAdminPages;

/**
 * @phpstan-import-type DrillLayerHeaderInput from OperatorChromeContract
 * @phpstan-import-type DrillLayerHeader from OperatorChromeContract
 * @phpstan-type RawDrillLayerHeader DrillLayerHeaderInput&array{title:non-empty-string}
 * @phpstan-type RawDrillLayer array{
 *   key:non-empty-string,
 *   body:string,
 *   header:RawDrillLayerHeader
 * }
 * @phpstan-type DrillLayer array{
 *   id:string,
 *   title_id:string,
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
		$shellID = sanitize_key( $mode.'_drill_shell' );
		$layers = $this->normalizeLayers( $this->getLayers(), $shellID );
		$activeIndex = $this->clampActiveLayerIndex( $this->getActiveLayerIndex(), \count( $layers ) );

		$vars[ 'drill_shell' ] = [
			'id'           => $shellID,
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
	private function normalizeLayers( array $layers, string $shellID ) :array {
		$normalized = [];

		foreach ( $layers as $layer ) {
			if ( \count( $normalized ) >= 3 ) {
				break;
			}

			$key = sanitize_key( $layer[ 'key' ] );
			if ( empty( $key ) ) {
				continue;
			}

			$rawHeader = $layer[ 'header' ] ?? null;
			if ( !\is_array( $rawHeader ) ) {
				continue;
			}

			$header = OperatorChromeContract::normalizeHeader( $rawHeader );
			if ( $header[ 'title' ] === '' ) {
				continue;
			}

			$layerID = $this->buildLayerID( $shellID, \count( $normalized ), $key );
			$normalized[] = [
				'id'          => $layerID,
				'title_id'    => $layerID.'_title',
				'key'         => $key,
				'body'        => $layer[ 'body' ],
				'header'      => $header,
				'header_json' => OperatorChromeContract::encodeJson( $header ),
			];
		}

		return $normalized;
	}

	private function buildLayerID( string $shellID, int $layerIndex, string $layerKey ) :string {
		return sanitize_key( \sprintf(
			'%s_layer_%d_%s',
			$shellID,
			$layerIndex,
			$layerKey
		) );
	}

	private function clampActiveLayerIndex( int $activeIndex, int $layerCount ) :int {
		if ( $layerCount < 1 || $activeIndex < 0 || $activeIndex >= $layerCount ) {
			return 0;
		}

		return $activeIndex;
	}

}
