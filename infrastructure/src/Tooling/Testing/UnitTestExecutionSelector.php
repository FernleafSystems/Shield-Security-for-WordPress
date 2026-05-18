<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

class UnitTestExecutionSelector {

	public const MODE_AUTO = 'auto';
	public const MODE_PARALLEL = 'parallel';
	public const MODE_SERIAL = 'serial';

	public const STRATEGY_SERIAL_PHPUNIT = 'serial_phpunit';
	public const STRATEGY_PARATEST_WRAPPER = 'paratest_wrapper';
	public const STRATEGY_PARATEST_FUNCTIONAL = 'paratest_functional';

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
	public function selectStrategy( array $args, string $mode = self::MODE_AUTO ) :string {
		$this->assertValidMode( $mode );

		if ( $mode === self::MODE_SERIAL ) {
			return self::STRATEGY_SERIAL_PHPUNIT;
		}

		$filter = $this->filterValue( $args );
		if ( $filter !== null && $filter !== '' ) {
			if ( $this->isPhpUnitDatasetShortcutFilter( $filter ) ) {
				if ( $mode === self::MODE_PARALLEL ) {
					throw new \InvalidArgumentException(
						'Native PHPUnit dataset shortcut filters require auto/serial mode, or a long-form regex filter when forcing --runner-mode=parallel.'
					);
				}

				return self::STRATEGY_SERIAL_PHPUNIT;
			}

			return self::STRATEGY_PARATEST_FUNCTIONAL;
		}

		return self::STRATEGY_PARATEST_WRAPPER;
	}

	public function isParatestStrategy( string $strategy ) :bool {
		return \in_array(
			$strategy,
			[
				self::STRATEGY_PARATEST_WRAPPER,
				self::STRATEGY_PARATEST_FUNCTIONAL,
			],
			true
		);
	}

	/**
	 * @param string[] $args
	 * @return string[]
	 */
	public function buildCommand( array $args, string $mode = self::MODE_AUTO ) :array {
		switch ( $this->selectStrategy( $args, $mode ) ) {
			case self::STRATEGY_SERIAL_PHPUNIT:
				return $this->buildSerialCommand( $args );

			case self::STRATEGY_PARATEST_FUNCTIONAL:
				return $this->buildFunctionalParatestCommand( $args );

			case self::STRATEGY_PARATEST_WRAPPER:
				return $this->buildWrapperParatestCommand( $args );
		}

		throw new \LogicException( 'Unknown unit test execution strategy.' );
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
	private function buildWrapperParatestCommand( array $args ) :array {
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

	/**
	 * @param string[] $args
	 * @return string[]
	 */
	private function buildFunctionalParatestCommand( array $args ) :array {
		return \array_merge(
			[
				\PHP_BINARY,
				'./vendor/brianium/paratest/bin/paratest',
				'-c',
				'phpunit-unit.xml',
				'--runner',
				'Runner',
				'--processes=auto',
				'--no-coverage',
				'-f',
			],
			$args
		);
	}

	/**
	 * @param string[] $args
	 */
	private function filterValue( array $args ) :?string {
		for ( $index = 0; $index < \count( $args ); $index++ ) {
			$arg = $args[ $index ];
			if ( $arg === '--filter' ) {
				$nextIndex = $index + 1;
				return isset( $args[ $nextIndex ] ) ? (string)$args[ $nextIndex ] : '';
			}
			if ( \strpos( $arg, '--filter=' ) === 0 ) {
				return (string)\substr( $arg, 9 );
			}
		}

		return null;
	}

	private function isPhpUnitDatasetShortcutFilter( string $filter ) :bool {
		if ( $this->isDelimitedRegex( $filter ) ) {
			return false;
		}

		return \strpos( $filter, '#' ) !== false || \strpos( $filter, '@' ) !== false;
	}

	private function isDelimitedRegex( string $filter ) :bool {
		if ( $filter === '' ) {
			return false;
		}

		$delimiter = $filter[ 0 ];
		if ( \ctype_alnum( $delimiter ) || $delimiter === '\\' || \ctype_space( $delimiter ) ) {
			return false;
		}

		$lastDelimiterPosition = \strrpos( $filter, $delimiter );
		return \is_int( $lastDelimiterPosition ) && $lastDelimiterPosition > 0;
	}
}
