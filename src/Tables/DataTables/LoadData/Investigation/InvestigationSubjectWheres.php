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

	public static function forActivitySubject( string $subjectType, string $subjectId, string $activityMetaTable ) :array {
		$subjectType = \strtolower( \trim( $subjectType ) );
		switch ( $subjectType ) {
			case 'plugin':
				$wheres = self::forPluginActivitySubject( $subjectId, $activityMetaTable );
				break;
			case 'theme':
				$wheres = self::forThemeActivitySubject( $subjectId, $activityMetaTable );
				break;
			case 'core':
				$wheres = self::forCoreActivitySubject();
				break;
			default:
				$wheres = self::impossible();
				break;
		}
		return $wheres;
	}

	public static function forPluginActivitySubject( string $subjectId, string $activityMetaTable ) :array {
		$subjectId = \trim( $subjectId );
		if ( empty( $subjectId ) || empty( $activityMetaTable ) ) {
			return self::impossible();
		}

		$fallbackToken = \trim( \basename( \str_replace( '\\', '/', $subjectId ) ) );
		$metaWhere = self::existsActivityMetaEquals( $activityMetaTable, 'plugin', $subjectId, 'meta_plugin' );
		if ( !empty( $fallbackToken ) ) {
			$metaWhere = \sprintf(
				'(%s OR %s)',
				$metaWhere,
				self::existsActivityMetaLike( $activityMetaTable, $fallbackToken, 'meta_plugin_fallback' )
			);
		}

		return [
			"`log`.`event_slug` LIKE 'plugin_%'",
			$metaWhere,
		];
	}

	public static function forThemeActivitySubject( string $subjectId, string $activityMetaTable ) :array {
		$subjectId = \trim( $subjectId );
		if ( empty( $subjectId ) || empty( $activityMetaTable ) ) {
			return self::impossible();
		}

		$metaWhere = self::existsActivityMetaEquals( $activityMetaTable, 'theme', $subjectId, 'meta_theme' );
		$metaWhere = \sprintf(
			'(%s OR %s)',
			$metaWhere,
			self::existsActivityMetaLike( $activityMetaTable, $subjectId, 'meta_theme_fallback' )
		);

		return [
			"`log`.`event_slug` LIKE 'theme_%'",
			$metaWhere,
		];
	}

	public static function forCoreActivitySubject() :array {
		return [
			"(`log`.`event_slug` LIKE 'core_%' OR `log`.`event_slug`='permalinks_structure' OR `log`.`event_slug` LIKE 'wp_option_%')",
		];
	}

	private static function existsActivityMetaEquals( string $activityMetaTable, string $metaKey, string $metaValue, string $abbr ) :string {
		return \sprintf(
			"EXISTS (SELECT 1 FROM `%s` as `%s` WHERE `%s`.`log_ref`=`log`.`id` AND `%s`.`meta_key`='%s' AND `%s`.`meta_value`='%s')",
			$activityMetaTable,
			$abbr,
			$abbr,
			$abbr,
			esc_sql( $metaKey ),
			$abbr,
			esc_sql( $metaValue )
		);
	}

	private static function existsActivityMetaLike( string $activityMetaTable, string $search, string $abbr ) :string {
		return \sprintf(
			"EXISTS (SELECT 1 FROM `%s` as `%s` WHERE `%s`.`log_ref`=`log`.`id` AND `%s`.`meta_key` NOT IN ('uid','audit_count') AND `%s`.`meta_value` LIKE '%%%s%%')",
			$activityMetaTable,
			$abbr,
			$abbr,
			$abbr,
			$abbr,
			esc_sql( $search )
		);
	}
}
