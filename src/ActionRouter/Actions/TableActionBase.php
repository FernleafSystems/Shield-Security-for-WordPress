<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\{
	MissingTableActionDataException,
	UnsupportedTableSubActionException
};
use FernleafSystems\Wordpress\Plugin\Shield\Tables\DataTables\LoadData\BaseBuildTableData;

abstract class TableActionBase extends BaseAction {

	protected const SUB_ACTION_RETRIEVE_TABLE_DATA = 'retrieve_table_data';

	protected function exec() {
		try {
			$subAction = (string)( $this->action_data[ 'sub_action' ] ?? '' );
			$handlers = $this->getSubActionHandlers();
			$handler = $handlers[ $subAction ] ?? null;

			if ( !\is_callable( $handler ) ) {
				throw new UnsupportedTableSubActionException( $this->getUnsupportedSubActionMessage( $subAction ) );
			}

			$this->assertRequiredActionDataKeys(
				$this->getSubActionRequiredDataKeysMap()[ $subAction ] ?? []
			);

			$response = $handler();
			if ( !\is_array( $response ) ) {
				throw new \Exception( 'Table-action handler did not return a response array.' );
			}
		}
		catch ( \Throwable $e ) {
			$response = $this->buildFailurePayload( $e );
		}

		$this->response()->action_response_data = $response;
	}

	/**
	 * @return array<string,callable>
	 */
	abstract protected function getSubActionHandlers() :array;

	protected function getSubActionRequiredDataKeysMap() :array {
		return [];
	}

	abstract protected function getUnsupportedSubActionMessage( string $subAction ) :string;

	/**
	 * @throws MissingTableActionDataException
	 */
	protected function assertRequiredActionDataKeys( array $requiredKeys ) :void {
		$missingKeys = \array_diff( $requiredKeys, \array_keys( $this->action_data ) );
		if ( !empty( $missingKeys ) ) {
			throw new MissingTableActionDataException(
				'Missing required action data keys: '.\implode( ', ', $missingKeys )
			);
		}
	}

	protected function buildUnsupportedSubActionMessage( string $tableLabel, string $subAction ) :string {
		// Backward-compatible lookup for previously action-specific msgids.
		$legacyTemplate = \sprintf( 'Not a supported %s table sub_action: %%s', $tableLabel );
		$legacyTranslation = __( $legacyTemplate, 'wp-simple-firewall' );
		if ( $legacyTranslation !== $legacyTemplate ) {
			return \sprintf( $legacyTranslation, $subAction );
		}

		return \sprintf(
			__( 'Not a supported %s table sub_action: %s', 'wp-simple-firewall' ),
			$this->translateUnsupportedSubActionTableLabel( $tableLabel ),
			$subAction
		);
	}

	private function translateUnsupportedSubActionTableLabel( string $tableLabel ) :string {
		switch ( $tableLabel ) {
			case 'Activity Log':
				return __( 'Activity Log', 'wp-simple-firewall' );
			case 'Traffic Log':
				return __( 'Traffic Log', 'wp-simple-firewall' );
			case 'Sessions':
				return __( 'Sessions', 'wp-simple-firewall' );
			case 'IP Rules':
				return __( 'IP Rules', 'wp-simple-firewall' );
			case 'Investigation':
				return __( 'Investigation', 'wp-simple-firewall' );
			default:
				return $tableLabel;
		}
	}

	protected function isPageReloadOnFailure() :bool {
		return true;
	}

	protected function buildFailurePayload( \Throwable $e ) :array {
		return [
			'success'     => false,
			'page_reload' => $this->isPageReloadOnFailure(),
			'message'     => $e->getMessage(),
		];
	}

	protected function buildDatatableDataResponse( BaseBuildTableData $builder, array $tableData ) :array {
		$builder->table_data = $tableData;
		return [
			'success'        => true,
			'datatable_data' => $builder->build(),
		];
	}

	protected function buildRetrieveTableDataResponse( BaseBuildTableData $builder, string $tableDataKey = 'table_data' ) :array {
		return $this->buildDatatableDataResponse(
			$builder,
			$this->getTableDataFromActionData( $tableDataKey )
		);
	}

	protected function getTableDataFromActionData( string $key = 'table_data' ) :array {
		$tableData = $this->action_data[ $key ] ?? [];
		return \is_array( $tableData ) ? $tableData : [];
	}
}
