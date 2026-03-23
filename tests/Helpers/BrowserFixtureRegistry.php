<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter\ActionsQueueFixtureBuilder;

class BrowserFixtureRegistry {

	private const OPTION_PREFIX = 'shield_browser_fixture_';

	/**
	 * @param list<string> $args
	 * @return array<string,mixed>
	 */
	public static function run( string $fixture, string $action, array $args = [] ) :array {
		switch ( $fixture ) {
			case 'actions-queue':
				return self::runActionsQueueFixture( $action, $args );
			default:
				throw new \RuntimeException( 'Unknown browser fixture: '.$fixture );
		}
	}

	/**
	 * @param list<string> $args
	 * @return array<string,mixed>
	 */
	private static function runActionsQueueFixture( string $action, array $args ) :array {
		$builder = new ActionsQueueFixtureBuilder();
		$optionKey = self::fixtureOptionKey( 'actions-queue' );
		$state = \get_option( $optionKey, [] );
		$state = \is_array( $state ) ? $state : [];

		switch ( $action ) {
			case 'cleanup':
				$builder->cleanup( $state );
				\delete_option( $optionKey );
				return [ 'cleaned' => true ];

			case 'inspect':
				$scenario = self::requireScenario( $args );
				return $builder->inspect( $scenario );

			case 'seed':
				$scenario = self::requireScenario( $args );
				if ( $state !== [] ) {
					$builder->cleanup( $state );
					\delete_option( $optionKey );
				}

				$result = $builder->seed( $scenario );
				\update_option( $optionKey, $result[ 'state' ], false );
				return $result[ 'contract' ];

			default:
				throw new \RuntimeException( 'Unknown browser fixture action: '.$action );
		}
	}

	/**
	 * @param list<string> $args
	 */
	private static function requireScenario( array $args ) :string {
		$scenario = \trim( $args[ 0 ] ?? '' );
		if ( $scenario === '' ) {
			throw new \RuntimeException( 'Missing fixture scenario.' );
		}

		return $scenario;
	}

	private static function fixtureOptionKey( string $fixture ) :string {
		$key = \preg_replace( '/[^a-z0-9_]/', '_', \strtolower( $fixture ) );
		return self::OPTION_PREFIX.( \is_string( $key ) ? $key : 'fixture' );
	}
}
