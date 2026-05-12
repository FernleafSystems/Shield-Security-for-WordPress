<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Integration\Support;

trait TracksOptionWrites {

	private array $trackedOptionNames = [];

	private array $trackedOptionWrites = [];

	private $optionAddedHook = null;

	private $optionUpdatedHook = null;

	protected function startTrackingOptionWrites( array $optionNames ) :void {
		$this->trackedOptionNames = \array_fill_keys( $optionNames, true );
		$this->trackedOptionWrites = [];

		$this->optionAddedHook = function ( string $option, $value ) :void {
			if ( isset( $this->trackedOptionNames[ $option ] ) ) {
				$this->trackedOptionWrites[] = [
					'hook'   => 'added_option',
					'option' => $option,
					'value'  => $value,
				];
			}
		};
		$this->optionUpdatedHook = function ( string $option, $oldValue, $value ) :void {
			if ( isset( $this->trackedOptionNames[ $option ] ) ) {
				$this->trackedOptionWrites[] = [
					'hook'      => 'updated_option',
					'option'    => $option,
					'old_value' => $oldValue,
					'value'     => $value,
				];
			}
		};

		\add_action( 'added_option', $this->optionAddedHook, 10, 2 );
		\add_action( 'updated_option', $this->optionUpdatedHook, 10, 3 );
	}

	protected function stopTrackingOptionWrites() :void {
		if ( $this->optionAddedHook !== null ) {
			\remove_action( 'added_option', $this->optionAddedHook, 10 );
			$this->optionAddedHook = null;
		}
		if ( $this->optionUpdatedHook !== null ) {
			\remove_action( 'updated_option', $this->optionUpdatedHook, 10 );
			$this->optionUpdatedHook = null;
		}
		$this->trackedOptionNames = [];
	}

	protected function assertOptionWasNotWritten( string $optionName ) :void {
		$writes = \array_values( \array_filter(
			$this->trackedOptionWrites,
			fn( array $write ) => ( $write[ 'option' ] ?? '' ) === $optionName
		) );
		$this->assertCount( 0, $writes, "Unexpected option write for {$optionName}" );
	}

	protected function getTrackedOptionWrites() :array {
		return $this->trackedOptionWrites;
	}
}
