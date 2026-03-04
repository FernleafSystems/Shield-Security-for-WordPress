<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

class UnitTestExecutionSelector {

	public const MODE_AUTO = 'auto';
	public const MODE_PARALLEL = 'parallel';
	public const MODE_SERIAL = 'serial';

	/**
	 * @return string[]
	 */
	public function validModes() :array {
		return [
			self::MODE_AUTO,
			self::MODE_PARALLEL,
			self::MODE_SERIAL,
		];
	}

	public function isValidMode( string $mode ) :bool {
		return \in_array( $mode, $this->validModes(), true );
	}

	public function assertValidMode( string $mode ) :void {
		if ( !$this->isValidMode( $mode ) ) {
			throw new \InvalidArgumentException(
				\sprintf(
					'Invalid unit test runner mode "%s". Expected one of: %s',
					$mode,
					\implode( ', ', $this->validModes() )
				)
			);
		}
	}

	/**
	 * @param string[] $args
	 */
	public function shouldUseSerialPhpUnit( array $args, string $mode = self::MODE_AUTO ) :bool {
		$this->assertValidMode( $mode );

		if ( $mode === self::MODE_SERIAL ) {
			return true;
		}
		if ( $mode === self::MODE_PARALLEL ) {
			return false;
		}

		foreach ( $args as $arg ) {
			if ( $arg === '--filter' || \strpos( $arg, '--filter=' ) === 0 ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @param string[] $args
	 * @return string[]
	 */
	public function buildCommand( array $args, string $mode = self::MODE_AUTO ) :array {
		if ( $this->shouldUseSerialPhpUnit( $args, $mode ) ) {
			return $this->buildSerialCommand( $args );
		}

		return $this->buildParallelCommand( $args );
	}

	/**
	 * @param string[] $args
	 * @return string[]
	 */
	private function buildSerialCommand( array $args ) :array {
		return \array_merge(
			[
				\PHP_BINARY,
				'./vendor/phpunit/phpunit/phpunit',
				'-c',
				'phpunit-unit.xml',
			],
			$args
		);
	}

	/**
	 * @param string[] $args
	 * @return string[]
	 */
	private function buildParallelCommand( array $args ) :array {
		return \array_merge(
			[
				\PHP_BINARY,
				'./vendor/brianium/paratest/bin/paratest',
				'-c',
				'phpunit-unit.xml',
				'--runner',
				'WrapperRunner',
				'--processes=auto',
				'--no-coverage',
			],
			$args
		);
	}
}
