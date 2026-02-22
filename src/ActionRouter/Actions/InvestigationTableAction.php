<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Investigation\{
	InvestigationTableContract,
	InvestigationSubjectResolver,
	InvestigationTableRegistry
};
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\{
	InvalidInvestigationSubjectIdentifierException,
	MissingTableActionDataException,
	UnavailableInvestigationTableBuilderException,
	UnsupportedInvestigationSubjectTypeException,
	UnsupportedInvestigationTableTypeException,
	UnsupportedTableSubActionException
};
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\Investigation\BaseInvestigationData;

class InvestigationTableAction extends TableActionBase {

	public const SLUG = 'investigationtable_action';

	private const ERROR_UNSUPPORTED_SUB_ACTION = 'unsupported_sub_action';
	private const ERROR_MISSING_REQUIRED_ACTION_DATA = 'missing_required_action_data';
	private const ERROR_UNSUPPORTED_TABLE_TYPE = 'unsupported_table_type';
	private const ERROR_UNSUPPORTED_SUBJECT_TYPE = 'unsupported_subject_type';
	private const ERROR_INVALID_SUBJECT_IDENTIFIER = 'invalid_subject_identifier';
	private const ERROR_UNAVAILABLE_BUILDER = 'unavailable_builder';
	private const ERROR_UNEXPECTED = 'unexpected_error';

	protected function getRequiredDataKeys() :array {
		return [ InvestigationTableContract::REQ_KEY_SUB_ACTION ];
	}

	protected function getSubActionHandlers() :array {
		return [
			InvestigationTableContract::SUB_ACTION_RETRIEVE_TABLE_DATA => fn() => $this->retrieveTableData(),
		];
	}

	protected function getSubActionRequiredDataKeysMap() :array {
		return [
			InvestigationTableContract::SUB_ACTION_RETRIEVE_TABLE_DATA => [
				InvestigationTableContract::REQ_KEY_TABLE_TYPE,
				InvestigationTableContract::REQ_KEY_SUBJECT_TYPE,
				InvestigationTableContract::REQ_KEY_SUBJECT_ID,
			],
		];
	}

	protected function getUnsupportedSubActionMessage( string $subAction ) :string {
		return $this->buildUnsupportedSubActionMessage( 'Investigation', $subAction );
	}

	/**
	 * @throws InvalidInvestigationSubjectIdentifierException
	 * @throws UnavailableInvestigationTableBuilderException
	 * @throws UnsupportedInvestigationSubjectTypeException
	 * @throws UnsupportedInvestigationTableTypeException
	 */
	protected function retrieveTableData() :array {
		$subjectContext = $this->normalizeSubjectContext(
			(string)$this->action_data[ InvestigationTableContract::REQ_KEY_TABLE_TYPE ],
			(string)$this->action_data[ InvestigationTableContract::REQ_KEY_SUBJECT_TYPE ],
			$this->action_data[ InvestigationTableContract::REQ_KEY_SUBJECT_ID ]
		);

		$builder = $this->createBuilderForTableType( $subjectContext[ InvestigationTableContract::REQ_KEY_TABLE_TYPE ] );
		$builder->setSubject(
			$subjectContext[ InvestigationTableContract::REQ_KEY_SUBJECT_TYPE ],
			$subjectContext[ InvestigationTableContract::REQ_KEY_SUBJECT_ID ]
		);
		return $this->buildRetrieveTableDataResponse( $builder, InvestigationTableContract::REQ_KEY_TABLE_DATA );
	}

	/**
	 * @throws InvalidInvestigationSubjectIdentifierException
	 * @throws UnsupportedInvestigationSubjectTypeException
	 * @throws UnsupportedInvestigationTableTypeException
	 */
	protected function normalizeSubjectContext( string $tableType, string $subjectType, $subjectId ) :array {
		return $this->getSubjectResolver()->normalize( $tableType, $subjectType, $subjectId );
	}

	/**
	 * @throws UnavailableInvestigationTableBuilderException
	 */
	protected function createBuilderForTableType( string $tableType ) :BaseInvestigationData {
		$builderClass = InvestigationTableRegistry::getBuilderClass( $tableType );
		if ( empty( $builderClass ) || !\class_exists( $builderClass ) ) {
			throw new UnavailableInvestigationTableBuilderException(
				'Investigation table builder unavailable for table type: '.$tableType
			);
		}
		return new $builderClass();
	}

	protected function getSubjectResolver() :InvestigationSubjectResolver {
		return new InvestigationSubjectResolver();
	}

	protected function buildFailurePayload( \Throwable $e ) :array {
		return [
			'success'     => false,
			'page_reload' => true,
			'message'     => $e->getMessage(),
			'error_code'  => $this->determineErrorCode( $e ),
		];
	}

	private function determineErrorCode( \Throwable $e ) :string {
		if ( $e instanceof UnsupportedTableSubActionException ) {
			return self::ERROR_UNSUPPORTED_SUB_ACTION;
		}
		if ( $e instanceof MissingTableActionDataException ) {
			return self::ERROR_MISSING_REQUIRED_ACTION_DATA;
		}
		if ( $e instanceof UnsupportedInvestigationTableTypeException ) {
			return self::ERROR_UNSUPPORTED_TABLE_TYPE;
		}
		if ( $e instanceof UnsupportedInvestigationSubjectTypeException ) {
			return self::ERROR_UNSUPPORTED_SUBJECT_TYPE;
		}
		if ( $e instanceof InvalidInvestigationSubjectIdentifierException ) {
			return self::ERROR_INVALID_SUBJECT_IDENTIFIER;
		}
		if ( $e instanceof UnavailableInvestigationTableBuilderException ) {
			return self::ERROR_UNAVAILABLE_BUILDER;
		}
		return self::ERROR_UNEXPECTED;
	}
}
