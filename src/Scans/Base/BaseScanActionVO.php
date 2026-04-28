<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Scans\Base;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;

/**
 * @property string   $scan
 * @property string   $scope_type
 * @property string   $scope_key
 * @property int      $created_at
 * @property int      $finished_at
 * @property int      $site_assets
 * @property string[] $items
 * @property array[]  $results
 * @property int      $usleep
 */
abstract class BaseScanActionVO extends DynPropertiesClass {

	public const DEFAULT_SLEEP_SECONDS = 0;

	public function getScanNamespace() :string {
		try {
			$namespace = ( new \ReflectionClass( $this ) )->getNamespaceName();
		}
		catch ( \Exception $e ) {
			$namespace = __NAMESPACE__;
		}
		return \rtrim( $namespace, '\\' ).'\\';
	}
}
