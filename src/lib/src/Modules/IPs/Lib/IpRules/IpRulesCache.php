<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\IPs\Lib\IpRules;

use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Arrays;
use FernleafSystems\Wordpress\Services\Utilities\Options\Transient;

class IpRulesCache {

	public const GROUP_NO_RULES = 'no_rules';
	public const GROUP_COLLECTIONS = 'collections';
	public const COLLECTION_RANGES = 'ranges';
	private const GROUPS = [
		self::GROUP_NO_RULES    => [
			'lifetime' => 120,
			'limit'    => 30,
		],
		self::GROUP_COLLECTIONS => [
			'lifetime' => 120,
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

	public static function AddMultiple( array $values, string $group ) :void {
		foreach ( $values as $key => $value ) {
			self::Add( $key, $value, $group, false );
		}
		self::StoreCache();
	}

	/**
	 * @return mixed|null
	 * @throws \Exception
	 */
	public static function Get( string $key, string $group ) {
		return self::GetGroup( $group )[ $key ][ 'data' ] ?? null;
	}

	public static function Delete( string $key, string $group ) :void {
		self::LoadCache();
		unset( self::$ipCache[ $group ][ $key ] );
		self::StoreCache();
	}

	public static function GetGroup( string $group ) :array {
		return self::LoadCache()[ $group ];
	}

	public static function GetGroupValues( string $group ) :array {
		return \array_map(
			function ( array $groupItem ) {
				return $groupItem[ 'data' ];
			},
			self::LoadCache()[ $group ]
		);
	}

	public static function Has( string $key, string $group ) :bool {
		return isset( self::GetGroup( $group )[ $key ] );
	}

	public static function Remove( string $key, string $group ) :void {
		self::LoadCache();
		unset( self::$ipCache[ $group ][ $key ] );
		self::StoreCache();
	}

	private static function StoreCache() {
		Transient::Set( 'shield_ip_rules_cache', self::LoadCache(), DAY_IN_SECONDS );
	}

	private static function LoadCache() :array {
		if ( self::$ipCache === null ) {
			$cache = Transient::Get( 'shield_ip_rules_cache' );

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