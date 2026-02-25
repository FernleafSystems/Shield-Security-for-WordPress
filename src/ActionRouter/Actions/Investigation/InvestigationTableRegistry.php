<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Investigation;

use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Investigation\{
	BuildActivityLogData,
	BuildFileScanResultsData,
	BuildSessionsData,
	BuildTrafficData
};

class InvestigationTableRegistry {

	/**
	 * @var array<string,array{builder:class-string,subjects:string[]}>|null
	 */
	private static ?array $tableMap = null;

	/**
	 * @return array<string,array{builder:class-string,subjects:string[]}>
	 */
	public static function tableMap() :array {
		return self::$tableMap ??= [
			InvestigationTableContract::TABLE_TYPE_ACTIVITY          => [
				'builder'  => BuildActivityLogData::class,
				'subjects' => [
					InvestigationTableContract::SUBJECT_TYPE_USER,
					InvestigationTableContract::SUBJECT_TYPE_IP,
					InvestigationTableContract::SUBJECT_TYPE_PLUGIN,
					InvestigationTableContract::SUBJECT_TYPE_THEME,
					InvestigationTableContract::SUBJECT_TYPE_CORE,
				],
			],
			InvestigationTableContract::TABLE_TYPE_TRAFFIC           => [
				'builder'  => BuildTrafficData::class,
				'subjects' => [ InvestigationTableContract::SUBJECT_TYPE_USER, InvestigationTableContract::SUBJECT_TYPE_IP ],
			],
			InvestigationTableContract::TABLE_TYPE_SESSIONS          => [
				'builder'  => BuildSessionsData::class,
				'subjects' => [ InvestigationTableContract::SUBJECT_TYPE_USER ],
			],
			InvestigationTableContract::TABLE_TYPE_FILE_SCAN_RESULTS => [
				'builder'  => BuildFileScanResultsData::class,
				'subjects' => [
					InvestigationTableContract::SUBJECT_TYPE_PLUGIN,
					InvestigationTableContract::SUBJECT_TYPE_THEME,
					InvestigationTableContract::SUBJECT_TYPE_CORE,
				],
			],
		];
	}

	public static function hasTableType( string $tableType ) :bool {
		return isset( self::tableMap()[ $tableType ] );
	}

	public static function getBuilderClass( string $tableType ) :string {
		return (string)( self::tableMap()[ $tableType ][ 'builder' ] ?? '' );
	}

	public static function getAllowedSubjectTypes( string $tableType ) :array {
		$subjects = self::tableMap()[ $tableType ][ 'subjects' ] ?? [];
		return \is_array( $subjects ) ? \array_values( $subjects ) : [];
	}
}
