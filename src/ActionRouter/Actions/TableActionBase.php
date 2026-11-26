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
			$subAction = $this->fields()->string( 'sub_action', '' );
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

		$payloadSuccess = (bool)( $response[ 'success' ] ?? false );
		unset( $response[ 'success' ] );

		$this->response()
			->setPayload( $response )
			->setPayloadSuccess( $payloadSuccess );
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
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Plain-text message of developer-defined key names: exec() returns it as the AJAX response message.
			throw new MissingTableActionDataException(
				'Missing required action data keys: '.\implode( ', ', $missingKeys )
			);
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}

	/**
	 * @param string $tableName the table's translated name
	 */
	protected function buildUnsupportedSubActionMessage( string $tableName, string $subAction ) :string {
		return \sprintf(
			/* translators: %1$s: table name, %2$s: action name */
			__( 'Not a supported %1$s table sub_action: %2$s', 'wp-simple-firewall' ),
			$tableName,
			$subAction
		);
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
