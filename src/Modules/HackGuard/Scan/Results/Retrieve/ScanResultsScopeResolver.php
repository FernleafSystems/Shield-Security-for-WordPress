<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Investigation\InvestigationTableContract;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Investigation\InvestigationSubjectWheres;

class ScanResultsScopeResolver {

	public const SCOPE_TYPE_WORDPRESS = 'wordpress';
	public const SCOPE_FILE_WORDPRESS = 'wordpress';
	public const SCOPE_TYPE_PLUGIN = InvestigationTableContract::SUBJECT_TYPE_PLUGIN;
	public const SCOPE_TYPE_THEME = InvestigationTableContract::SUBJECT_TYPE_THEME;
	public const SCOPE_TYPE_MALWARE = InvestigationTableContract::SUBJECT_TYPE_MALWARE;

	/**
	 * @return array{type:string,file:string}
	 * @throws \InvalidArgumentException
	 */
	public function canonicalActionDataForSubject( string $subjectType, string $subjectId ) :array {
		$subjectType = \strtolower( \trim( $subjectType ) );
		$subjectId = \trim( $subjectId );

		switch ( $subjectType ) {
			case InvestigationTableContract::SUBJECT_TYPE_CORE:
			case self::SCOPE_TYPE_WORDPRESS:
				return [
					'type' => self::SCOPE_TYPE_WORDPRESS,
					'file' => self::SCOPE_FILE_WORDPRESS,
				];
			case self::SCOPE_TYPE_MALWARE:
				return [
					'type' => self::SCOPE_TYPE_MALWARE,
					'file' => self::SCOPE_TYPE_MALWARE,
				];
			case self::SCOPE_TYPE_PLUGIN:
			case self::SCOPE_TYPE_THEME:
				if ( $subjectId === '' ) {
					throw new \InvalidArgumentException( 'No scan result asset scope was provided.' );
				}
				return [
					'type' => $subjectType,
					'file' => $subjectId,
				];
			default:
				throw new \InvalidArgumentException( \sprintf( 'Unsupported scan result scope type "%s".', $subjectType ) );
		}
	}

	/**
	 * @return array{type:string,file:string}
	 */
	public function normalizeActionScope( string $type, string $file ) :array {
		return $this->canonicalActionDataForSubject( $type, $file );
	}

	/**
	 * @return list<string>
	 */
	public function wheresForActionScope(
		string $type,
		string $file,
		string $metaTableAbbr = RetrieveBase::ABBR_RESULTITEMMETA
	) :array {
		[ 'type' => $type, 'file' => $file ] = $this->normalizeActionScope( $type, $file );

		switch ( $type ) {
			case self::SCOPE_TYPE_PLUGIN:
			case self::SCOPE_TYPE_THEME:
				return InvestigationSubjectWheres::forAssetSlug( $file, $metaTableAbbr );
			case self::SCOPE_TYPE_MALWARE:
				return InvestigationSubjectWheres::forMalwareResults( $metaTableAbbr );
			case self::SCOPE_TYPE_WORDPRESS:
				return InvestigationSubjectWheres::forCoreResults( $metaTableAbbr );
			default:
				throw new \InvalidArgumentException( \sprintf( 'Unsupported scan result scope type "%s".', $type ) );
		}
	}
}
