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

		$metaWhere = \sprintf(
			'(%s OR %s)',
			self::existsActivityMetaEquals( $activityMetaTable, 'plugin', $subjectId, 'meta_plugin' ),
			self::existsActivityFileEditMetaLike(
				$activityMetaTable,
				'plugin_file_edited',
				self::buildPluginFileTokens( $subjectId ),
				'meta_plugin_file'
			)
		);

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

		$metaWhere = \sprintf(
			'(%s OR %s)',
			self::existsActivityMetaEquals( $activityMetaTable, 'theme', $subjectId, 'meta_theme' ),
			self::existsActivityFileEditMetaLike(
				$activityMetaTable,
				'theme_file_edited',
				self::buildThemeFileTokens( $subjectId ),
				'meta_theme_file'
			)
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

	private static function existsActivityFileEditMetaLike( string $activityMetaTable, string $eventSlug, array $searchTokens, string $abbr ) :string {
		$likeClauses = \array_values( \array_filter( \array_map(
			static fn( string $token ) :string => \trim( $token ) === ''
				? ''
				: \sprintf( "`%s`.`meta_value` LIKE '%%%s%%'", $abbr, esc_sql( self::escapeLikeToken( $token ) ) ),
			$searchTokens
		), '\strlen' ) );

		if ( empty( $likeClauses ) ) {
			return '0=1';
		}

		return \sprintf(
			"EXISTS (SELECT 1 FROM `%s` as `%s` WHERE `%s`.`log_ref`=`log`.`id` AND `log`.`event_slug`='%s' AND `%s`.`meta_key`='file' AND (%s))",
			$activityMetaTable,
			$abbr,
			$abbr,
			esc_sql( $eventSlug ),
			$abbr,
			\implode( ' OR ', $likeClauses )
		);
	}

	private static function buildPluginFileTokens( string $subjectId ) :array {
		$subjectId = \trim( \str_replace( '\\', '/', $subjectId ), '/' );
		if ( empty( $subjectId ) ) {
			return [];
		}

		$tokens = [ $subjectId ];
		$dir = \trim( \dirname( $subjectId ), './\\' );
		if ( !empty( $dir ) && $dir !== '.' ) {
			$tokens[] = $dir.'/';
			$tokens[] = '/'.$dir.'/';
		}

		return \array_values( \array_unique( \array_filter( $tokens, '\strlen' ) ) );
	}

	private static function buildThemeFileTokens( string $subjectId ) :array {
		$subjectId = \trim( \str_replace( '\\', '/', $subjectId ), '/' );
		if ( empty( $subjectId ) ) {
			return [];
		}

		$tokens = [];
		$dir = \trim( \dirname( $subjectId ), './\\' );
		if ( !empty( $dir ) && $dir !== '.' ) {
			$tokens[] = $dir.'/';
			$tokens[] = '/'.$dir.'/';
		}
		else {
			$tokens[] = $subjectId.'/';
			$tokens[] = '/'.$subjectId.'/';
		}

		return \array_values( \array_unique( \array_filter( $tokens, '\strlen' ) ) );
	}

	private static function escapeLikeToken( string $token ) :string {
		return \function_exists( 'esc_like' )
			? esc_like( $token )
			: \addcslashes( $token, '_%\\' );
	}
}
