<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\HackGuard\Scan\Results\Retrieve;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Investigation\InvestigationTableContract;
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Investigation\InvestigationSubjectWheres;

class ScanResultsScopeResolver {

	/**
	 * @return array{type:string,file:string}
	 */
	public function canonicalActionDataForSubject( string $subjectType, string $subjectId ) :array {
		$subjectType = \strtolower( \trim( $subjectType ) );
		$subjectId = \trim( $subjectId );

		switch ( $subjectType ) {
			case InvestigationTableContract::SUBJECT_TYPE_CORE:
				return [
					'type' => 'wordpress',
					'file' => 'wordpress',
				];
			case 'malware':
				return [
					'type' => 'malware',
					'file' => 'malware',
				];
			case InvestigationTableContract::SUBJECT_TYPE_PLUGIN:
			case InvestigationTableContract::SUBJECT_TYPE_THEME:
			default:
				return [
					'type' => $subjectType,
					'file' => $subjectId,
				];
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
			case 'plugin':
			case 'theme':
				return InvestigationSubjectWheres::forAssetSlug( $file, $metaTableAbbr );
			case 'malware':
				return InvestigationSubjectWheres::forMalwareResults( $metaTableAbbr );
			case 'wordpress':
			default:
				return InvestigationSubjectWheres::forCoreResults( $metaTableAbbr );
		}
	}
}
