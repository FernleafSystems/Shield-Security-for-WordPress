<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

class UnitTestExecutionSelector {

	/**
	 * @param string[] $args
	 */
	public function shouldUseSerialPhpUnit( array $args ) :bool {
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
	public function buildCommand( array $args ) :array {
		if ( $this->shouldUseSerialPhpUnit( $args ) ) {
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
