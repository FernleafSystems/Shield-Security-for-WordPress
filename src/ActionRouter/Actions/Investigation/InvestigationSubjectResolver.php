<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Investigation;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\{
	InvalidInvestigationSubjectIdentifierException,
	UnsupportedInvestigationSubjectTypeException,
	UnsupportedInvestigationTableTypeException
};
use FernleafSystems\Wordpress\Services\Services;

class InvestigationSubjectResolver {

	/**
	 * @throws InvalidInvestigationSubjectIdentifierException
	 * @throws UnsupportedInvestigationSubjectTypeException
	 * @throws UnsupportedInvestigationTableTypeException
	 */
	public function normalize( string $tableType, string $subjectType, $subjectId ) :array {
		$tableType = \strtolower( \trim( $tableType ) );
		$subjectType = \strtolower( \trim( $subjectType ) );

		if ( !InvestigationTableRegistry::hasTableType( $tableType ) ) {
			throw new UnsupportedInvestigationTableTypeException( 'Unsupported investigation table type: '.$tableType );
		}

		if ( !\in_array( $subjectType, InvestigationTableRegistry::getAllowedSubjectTypes( $tableType ), true ) ) {
			throw new UnsupportedInvestigationSubjectTypeException( 'Unsupported subject type for table type.' );
		}

		return [
			InvestigationTableContract::REQ_KEY_TABLE_TYPE   => $tableType,
			InvestigationTableContract::REQ_KEY_SUBJECT_TYPE => $subjectType,
			InvestigationTableContract::REQ_KEY_SUBJECT_ID   => $this->normalizeSubjectId( $subjectType, $subjectId ),
		];
	}

	/**
	 * @throws InvalidInvestigationSubjectIdentifierException
	 * @throws UnsupportedInvestigationSubjectTypeException
	 */
	private function normalizeSubjectId( string $subjectType, $subjectId ) {
		switch ( $subjectType ) {
			case InvestigationTableContract::SUBJECT_TYPE_USER:
				$normalizedSubjectId = \is_numeric( $subjectId ) ? (int)$subjectId : 0;
				if ( $normalizedSubjectId <= 0 ) {
					throw new InvalidInvestigationSubjectIdentifierException( 'Invalid user subject identifier.' );
				}
				break;
			case InvestigationTableContract::SUBJECT_TYPE_IP:
				$normalizedSubjectId = \trim( (string)$subjectId );
				if ( empty( $normalizedSubjectId ) || !Services::IP()->isValidIp( $normalizedSubjectId ) ) {
					throw new InvalidInvestigationSubjectIdentifierException( 'Invalid IP subject identifier.' );
				}
				break;
			case InvestigationTableContract::SUBJECT_TYPE_PLUGIN:
			case InvestigationTableContract::SUBJECT_TYPE_THEME:
				$normalizedSubjectId = $this->normalizeAssetSubjectIdentifier( $subjectType, $subjectId );
				break;
			case InvestigationTableContract::SUBJECT_TYPE_CORE:
				$normalizedSubjectId = InvestigationTableContract::SUBJECT_TYPE_CORE;
				break;
			default:
				throw new UnsupportedInvestigationSubjectTypeException( 'Invalid subject type.' );
		}

		return $normalizedSubjectId;
	}

	/**
	 * @throws InvalidInvestigationSubjectIdentifierException
	 */
	protected function normalizeAssetSubjectIdentifier( string $subjectType, $subjectId ) :string {
		$subjectId = \trim( (string)$subjectId );
		if ( empty( $subjectId ) ) {
			throw new InvalidInvestigationSubjectIdentifierException( 'Invalid asset subject identifier.' );
		}

		$installed = $subjectType === InvestigationTableContract::SUBJECT_TYPE_PLUGIN
			? $this->getInstalledPluginSubjectIdentifiers()
			: $this->getInstalledThemeSubjectIdentifiers();

		$matched = $this->findMatchingAssetSubjectIdentifier( $subjectId, $installed );
		if ( empty( $matched ) ) {
			throw new InvalidInvestigationSubjectIdentifierException( 'Invalid asset subject identifier.' );
		}

		return $matched;
	}

	protected function getInstalledPluginSubjectIdentifiers() :array {
		return Services::WpPlugins()->getInstalledPluginFiles();
	}

	protected function getInstalledThemeSubjectIdentifiers() :array {
		return Services::WpThemes()->getInstalledStylesheets();
	}

	protected function findMatchingAssetSubjectIdentifier( string $subjectId, array $installedSubjects ) :string {
		$installedSubjects = \array_values( \array_filter( \array_map(
			fn( $item ) => \trim( (string)$item ),
			$installedSubjects
		), '\strlen' ) );

		if ( \in_array( $subjectId, $installedSubjects, true ) ) {
			return $subjectId;
		}

		$lookup = [];
		foreach ( $installedSubjects as $installedSubject ) {
			$lookup[ \strtolower( $installedSubject ) ] = $installedSubject;
		}

		$candidates = [ $subjectId ];
		$decoded = \rawurldecode( $subjectId );
		if ( $decoded !== $subjectId ) {
			$candidates[] = $decoded;
		}

		foreach ( $candidates as $candidate ) {
			$matched = $lookup[ \strtolower( \trim( $candidate ) ) ] ?? '';
			if ( !empty( $matched ) ) {
				return $matched;
			}
		}

		return '';
	}
}
