<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Data;

use FernleafSystems\Wordpress\Plugin\Shield\Components\Worpdrive\Database\Operators\SqlIdentifier;

class ExportMap {

	private array $dumpStatus;

	private ?array $allowedTables;

	/**
	 * @throws \Exception
	 */
	public function __construct( array $dumpStatus = [], ?array $allowedTables = null ) {
		$this->allowedTables = $allowedTables === null ? null : \array_values( $allowedTables );
		$this->dumpStatus = $this->normaliseDumpStatus( $dumpStatus );
	}

	public function status() :array {
		return $this->dumpStatus;
	}

	public function updateStatus( string $table, array $status ) :void {
		$this->dumpStatus[ $table ] = $this->normaliseTableStatus( $table, $status );
	}

	private function normaliseDumpStatus( array $dumpStatus ) :array {
		$normalised = [];
		foreach ( $dumpStatus as $table => $status ) {
			if ( !\is_string( $table ) || !\is_array( $status ) ) {
				throw new \InvalidArgumentException( 'Worpdrive export map must be keyed by table name with array status values.' );
			}
			$normalised[ $table ] = $this->normaliseTableStatus( $table, $status );
		}
		return $normalised;
	}

	private function normaliseTableStatus( string $table, array $status ) :array {
		SqlIdentifier::assertAllowedTable( $table, $this->allowedTables );

		return [
			'offset'        => $this->readNonNegativeInt( $status, 'offset' ),
			'page'          => $this->readNonNegativeInt( $status, 'page' ),
			'completed_at'  => $this->readNonNegativeInt( $status, 'completed_at' ),
			'exported_rows' => $this->readNonNegativeInt( $status, 'exported_rows' ),
			'max_page_rows' => $this->readPositiveInt( $status, 'max_page_rows', 1000 ),
			'chunk_size'    => $this->readPositiveInt( $status, 'chunk_size' ),
		];
	}

	private function readNonNegativeInt( array $status, string $key, ?int $default = null ) :int {
		$value = $this->readInt( $status, $key, $default );
		if ( $value < 0 ) {
			throw new \InvalidArgumentException( \sprintf( 'Worpdrive export map value must be zero or greater: %s', $key ) );
		}
		return $value;
	}

	private function readPositiveInt( array $status, string $key, ?int $default = null ) :int {
		$value = $this->readInt( $status, $key, $default );
		if ( $value < 1 ) {
			throw new \InvalidArgumentException( \sprintf( 'Worpdrive export map value must be one or greater: %s', $key ) );
		}
		return $value;
	}

	private function readInt( array $status, string $key, ?int $default = null ) :int {
		if ( !\array_key_exists( $key, $status ) ) {
			if ( $default !== null ) {
				return $default;
			}
			throw new \InvalidArgumentException( \sprintf( 'Worpdrive export map is missing required value: %s', $key ) );
		}

		$value = $status[ $key ];
		if ( \is_bool( $value ) || !( \is_int( $value ) || ( \is_string( $value ) && \ctype_digit( $value ) ) ) ) {
			throw new \InvalidArgumentException( \sprintf( 'Worpdrive export map value must be an integer: %s', $key ) );
		}
		return (int)$value;
	}
}
