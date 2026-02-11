<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\BotSignal;

class BotSignalRecord {

	public bool $modified = false;

	/**
	 * @var array<string,mixed>
	 */
	private array $data = [];

	/**
	 * @param array<string,mixed> $raw
	 */
	public function applyFromArray( array $raw ) :self {
		foreach ( $raw as $key => $value ) {
			$this->data[ (string)$key ] = $value;
		}
		$this->modified = false;
		return $this;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function getRawData() :array {
		return $this->data;
	}

	public function __get( string $key ) {
		return $this->data[ $key ] ?? null;
	}

	public function __set( string $key, $value ) :void {
		if ( \serialize( $value ) !== \serialize( $this->data[ $key ] ?? null ) ) {
			$this->modified = true;
		}
		$this->data[ $key ] = $value;
	}
}
