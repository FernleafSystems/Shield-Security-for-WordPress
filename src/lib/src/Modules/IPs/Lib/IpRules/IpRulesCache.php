<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\PluginControllerConsumer;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Arrays;

class IpRulesCache {

	use PluginControllerConsumer;

	public const GROUP_NO_RULES = 'no_rules';
	public const GROUP_COLLECTIONS = 'collections';
	public const COLLECTION_RANGES = 'ranges';
	public const COLLECTION_BYPASS = 'white';
	private const GROUPS = [
		self::GROUP_NO_RULES    => [
			'lifetime' => 60,
			'limit'    => 30,
		],
		self::GROUP_COLLECTIONS => [
			'lifetime' => 600,
			'limit'    => 30,
		],
	];

	/**
	 * @var array[]
	 */
	private static $ipCache = null;

	public static function ResetGroup( string $group ) :void {
		self::LoadCache();
		self::$ipCache[ $group ] = [];
		self::StoreCache();
	}

	public static function ResetAll() :void {
		self::$ipCache = [];
		self::StoreCache();
	}

	public static function Add( string $key, $value, string $group, bool $store = true ) :void {
		self::LoadCache();
		self::$ipCache[ $group ][ $key ] = [
			'data' => $value,
			'_at'  => Services::Request()->ts(),
		];
		if ( $store ) {
			self::StoreCache();
		}
	}

	/**
	 * @return mixed|null
	 */
	public static function Get( string $key, string $group ) {
		return self::LoadCache()[ $group ][ $key ][ 'data' ] ?? null;
	}

	public static function Delete( string $key, string $group ) :void {
		self::LoadCache();
		unset( self::$ipCache[ $group ][ $key ] );
		self::StoreCache();
	}

	public static function Has( string $key, string $group ) :bool {
		return isset( self::LoadCache()[ $group ][ $key ] );
	}

	private static function StoreCache() {
		Services::WpGeneral()->updateOption( self::con()->prefix( 'ip_rules_cache', '_' ), self::LoadCache() );
	}

	private static function LoadCache() :array {
		if ( self::$ipCache === null ) {
			$cache = Services::WpGeneral()->getOption( self::con()->prefix( 'ip_rules_cache', '_' ) );

			$cache = \array_intersect_key(
				\array_merge(
					Arrays::SetAllValuesTo( self::GROUPS, [] ),
					\is_array( $cache ) ? $cache : []
				),
				self::GROUPS
			);

			foreach ( self::GROUPS as $groupKey => $groupSettings ) {

				$groupData = \array_filter(
					$cache[ $groupKey ],
					function ( array $data ) use ( $groupSettings ) {
						return Services::Request()->ts() - $data[ '_at' ] < $groupSettings[ 'lifetime' ];
					}
				);

				// We want the newest item ordered earlier so that the array_slice() removes older items
				if ( \count( $groupData ) > 1 ) {
					\uasort( $groupData, function ( $a, $b ) {
						$atA = $a[ '_at' ];
						$atB = $b[ '_at' ];
						return $atA < $atB ? 1 : ( $atA > $atB ? -1 : 0 );
					} );
				}

				$cache[ $groupKey ] = \array_slice( $groupData, 0, $groupSettings[ 'limit' ] );
			}

			self::$ipCache = $cache;
		}
		return self::$ipCache;
	}
}