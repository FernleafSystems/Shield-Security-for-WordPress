<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Investigation;

use FernleafSystems\Wordpress\Plugin\Shield\DBs\Common\IpAddressSql;
use FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve\RetrieveBase;

class InvestigationSubjectWheres {

	public static function impossible() :array {
		return [ '1=0' ];
	}

	public static function forUserColumn( string $column, int $uid ) :array {
		return $uid > 0 ? [ \sprintf( '%s=%d', $column, $uid ) ] : self::impossible();
	}

	public static function forIpColumn( string $column, string $ip ) :array {
		$ip = \trim( $ip );
		return empty( $ip ) ? self::impossible() : [ IpAddressSql::equality( $column, $ip ) ];
	}

	public static function forAssetSlug( string $slug, string $metaTableAbbr = RetrieveBase::ABBR_RESULTITEMMETA ) :array {
		$slug = \trim( $slug );
		if ( empty( $slug ) ) {
			return self::impossible();
		}

		return [
			\sprintf( "%s.`meta_key`='ptg_slug'", $metaTableAbbr ),
			\sprintf( "%s.`meta_value`='%s'", $metaTableAbbr, esc_sql( $slug ) ),
		];
	}

	public static function forCoreResults( string $metaTableAbbr = RetrieveBase::ABBR_RESULTITEMMETA ) :array {
		return [
			\sprintf( "%s.`meta_key`='is_in_core'", $metaTableAbbr ),
			\sprintf( "%s.`meta_value`=1", $metaTableAbbr ),
		];
	}
}
