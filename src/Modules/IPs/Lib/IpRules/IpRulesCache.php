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
		self::StoreCache( $group );
	}

	public static function ResetAll() :void {
		self::$ipCache = Arrays::SetAllValuesTo( self::GROUPS, [] );
		self::StoreCache();
	}

	public static function Add( string $key, $value, string $group, bool $store = true ) :void {
		self::LoadCache();
		self::$ipCache[ $group ][ $key ] = [
			'data' => $value,
			'_at'  => Services::Request()->ts(),
		];
		self::$ipCache[ $group ] = self::normalizeGroupCache( self::$ipCache[ $group ], $group );
		if ( $store ) {
			self::StoreCache( $group );
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
		self::StoreCache( $group );
	}

	public static function Has( string $key, string $group ) :bool {
		return isset( self::LoadCache()[ $group ][ $key ] );
	}

	private static function StoreCache( ?string $group = null ) :void {
		self::LoadCache();
		if ( $group === null ) {
			foreach ( \array_keys( self::GROUPS ) as $groupKey ) {
				self::StoreCache( $groupKey );
			}
		}
		elseif ( empty( self::$ipCache[ $group ] ) ) {
			\delete_transient( self::buildTransientKey( $group ) );
		}
		else {
			\set_transient(
				self::buildTransientKey( $group ),
				self::$ipCache[ $group ],
				self::GROUPS[ $group ][ 'lifetime' ]
			);
		}
	}

	private static function LoadCache() :array {
		if ( self::$ipCache === null ) {
			$cache = Arrays::SetAllValuesTo( self::GROUPS, [] );

			foreach ( self::GROUPS as $groupKey => $groupSettings ) {
				$group = \get_transient( self::buildTransientKey( $groupKey ) );
				$cache[ $groupKey ] = self::normalizeGroupCache( \is_array( $group ) ? $group : [], $groupKey );
			}

			self::$ipCache = $cache;
		}
		return self::$ipCache;
	}

	private static function buildTransientKey( string $group ) :string {
		return self::con()->prefix( 'ip_rules_cache_'.$group, '_' );
	}

	private static function normalizeGroupCache( array $group, string $groupKey ) :array {
		$groupSettings = self::GROUPS[ $groupKey ];
		$group = \array_filter(
			$group,
			fn( array $data ) => Services::Request()->ts() - ( $data[ '_at' ] ?? 0 ) < $groupSettings[ 'lifetime' ]
		);

		if ( \count( $group ) > 1 ) {
			\uasort( $group, fn( $a, $b ) => $a[ '_at' ] < $b[ '_at' ] ? 1 : ( $a[ '_at' ] > $b[ '_at' ] ? -1 : 0 ) );
		}

		return \array_slice( $group, 0, $groupSettings[ 'limit' ], true );
	}
}
