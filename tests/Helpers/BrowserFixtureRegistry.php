<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers;

use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter\ActionsQueueFixtureBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter\ImportExportFileFixtureBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter\IpAnalysisActivityMetaFixtureBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter\IpRulesTableFixtureBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter\MainwpSitesFixtureBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter\MerlinWelcomeFixtureBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter\MfaProfileFixtureBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter\NotBotAltchaFixtureBuilder;
use FernleafSystems\Wordpress\Plugin\Shield\Tests\Helpers\ActionRouter\PublicBlockRecoveryFixtureBuilder;

class BrowserFixtureRegistry {

	private const OPTION_PREFIX = 'shield_browser_fixture_';

	/**
	 * @param list<string> $args
	 * @return array<string,mixed>
	 */
	public static function run( string $fixture, string $action, array $args = [] ) :array {
		switch ( $fixture ) {
			case '__all__':
				return self::runAllFixtures( $action );
			case 'actions-queue':
				return self::runActionsQueueFixture( $action, $args );
			case 'import-export-file':
				return self::runImportExportFileFixture( $action );
			case 'ip-analysis-activity-meta':
				return self::runIpAnalysisActivityMetaFixture( $action );
			case 'ip-rules-table':
				return self::runIpRulesTableFixture( $action );
			case 'mainwp-sites':
				return self::runMainwpSitesFixture( $action );
			case 'merlin-welcome':
				return self::runMerlinWelcomeFixture( $action );
			case 'mfa-profile':
				return self::runMfaProfileFixture( $action );
			case 'notbot-altcha':
				return self::runNotBotAltchaFixture( $action, $args );
			case 'public-block-recovery':
				return self::runPublicBlockRecoveryFixture( $action, $args );
			default:
				throw new \RuntimeException( 'Unknown browser fixture: '.$fixture );
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function runAllFixtures( string $action ) :array {
		if ( $action !== 'cleanup' ) {
			throw new \RuntimeException( 'Unknown browser fixture action: '.$action );
		}

		self::runActionsQueueFixture( 'cleanup', [] );
		self::runImportExportFileFixture( 'cleanup' );
		self::runIpAnalysisActivityMetaFixture( 'cleanup' );
		self::runIpRulesTableFixture( 'cleanup' );
		self::runMainwpSitesFixture( 'cleanup' );
		self::runMerlinWelcomeFixture( 'cleanup' );
		self::runMfaProfileFixture( 'cleanup' );
		self::runNotBotAltchaFixture( 'cleanup', [] );
		self::runPublicBlockRecoveryFixture( 'cleanup', [] );
		return [ 'cleaned' => true ];
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
	 * @return array<string,mixed>
	 */
	private static function runImportExportFileFixture( string $action ) :array {
		$builder = new ImportExportFileFixtureBuilder();
		$optionKey = self::fixtureOptionKey( 'import-export-file' );
		$state = \get_option( $optionKey, [] );
		$state = \is_array( $state ) ? $state : [];

		switch ( $action ) {
			case 'cleanup':
				$builder->cleanup( $state );
				\delete_option( $optionKey );
				return [ 'cleaned' => true ];

			case 'seed':
				if ( $state !== [] ) {
					$builder->cleanup( $state );
					\delete_option( $optionKey );
				}

				$result = $builder->seed();
				\update_option( $optionKey, $result[ 'state' ], false );
				return $result[ 'contract' ];

			default:
				throw new \RuntimeException( 'Unknown browser fixture action: '.$action );
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function runIpAnalysisActivityMetaFixture( string $action ) :array {
		$builder = new IpAnalysisActivityMetaFixtureBuilder();
		$optionKey = self::fixtureOptionKey( 'ip-analysis-activity-meta' );
		$state = \get_option( $optionKey, [] );
		$state = \is_array( $state ) ? $state : [];

		switch ( $action ) {
			case 'cleanup':
				$builder->cleanup( $state );
				\delete_option( $optionKey );
				return [ 'cleaned' => true ];

			case 'seed':
				if ( $state !== [] ) {
					$builder->cleanup( $state );
					\delete_option( $optionKey );
				}

				$result = $builder->seed();
				\update_option( $optionKey, $result[ 'state' ], false );
				return $result[ 'contract' ];

			default:
				throw new \RuntimeException( 'Unknown browser fixture action: '.$action );
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function runIpRulesTableFixture( string $action ) :array {
		$builder = new IpRulesTableFixtureBuilder();
		$optionKey = self::fixtureOptionKey( 'ip-rules-table' );
		$state = \get_option( $optionKey, [] );
		$state = \is_array( $state ) ? $state : [];

		switch ( $action ) {
			case 'cleanup':
				$builder->cleanup( $state );
				\delete_option( $optionKey );
				return [ 'cleaned' => true ];

			case 'seed':
				if ( $state !== [] ) {
					$builder->cleanup( $state );
					\delete_option( $optionKey );
				}

				$result = $builder->seed();
				\update_option( $optionKey, $result[ 'state' ], false );
				return $result[ 'contract' ];

			default:
				throw new \RuntimeException( 'Unknown browser fixture action: '.$action );
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function runMainwpSitesFixture( string $action ) :array {
		$builder = new MainwpSitesFixtureBuilder();
		$optionKey = self::fixtureOptionKey( 'mainwp-sites' );
		$state = \get_option( $optionKey, [] );
		$state = \is_array( $state ) ? $state : [];

		switch ( $action ) {
			case 'cleanup':
				$builder->cleanup( $state );
				\delete_option( $optionKey );
				return [ 'cleaned' => true ];

			case 'seed':
				if ( $state !== [] ) {
					$builder->cleanup( $state );
					\delete_option( $optionKey );
				}

				$result = $builder->seed();
				\update_option( $optionKey, $result[ 'state' ], false );
				return $result[ 'contract' ];

			default:
				throw new \RuntimeException( 'Unknown browser fixture action: '.$action );
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function runMerlinWelcomeFixture( string $action ) :array {
		$builder = new MerlinWelcomeFixtureBuilder();
		$optionKey = self::fixtureOptionKey( 'merlin-welcome' );
		$state = \get_option( $optionKey, [] );
		$state = \is_array( $state ) ? $state : [];

		switch ( $action ) {
			case 'cleanup':
				$builder->cleanup( $state );
				\delete_option( $optionKey );
				return [ 'cleaned' => true ];

			case 'seed':
				if ( $state !== [] ) {
					$builder->cleanup( $state );
					\delete_option( $optionKey );
				}

				$result = $builder->seed();
				\update_option( $optionKey, $result[ 'state' ], false );
				return $result[ 'contract' ];

			default:
				throw new \RuntimeException( 'Unknown browser fixture action: '.$action );
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function runMfaProfileFixture( string $action ) :array {
		$builder = new MfaProfileFixtureBuilder();
		$optionKey = self::fixtureOptionKey( 'mfa-profile' );
		$state = \get_option( $optionKey, [] );
		$state = \is_array( $state ) ? $state : [];

		switch ( $action ) {
			case 'cleanup':
				$builder->cleanup( $state );
				\delete_option( $optionKey );
				return [ 'cleaned' => true ];

			case 'seed':
				if ( $state !== [] ) {
					$builder->cleanup( $state );
					\delete_option( $optionKey );
				}

				$result = $builder->seed();
				\update_option( $optionKey, $result[ 'state' ], false );
				return $result[ 'contract' ];

			default:
				throw new \RuntimeException( 'Unknown browser fixture action: '.$action );
		}
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function runNotBotAltchaFixture( string $action, array $args ) :array {
		$builder = new NotBotAltchaFixtureBuilder();
		$optionKey = self::fixtureOptionKey( 'notbot-altcha' );
		$state = \get_option( $optionKey, [] );
		$state = \is_array( $state ) ? $state : [];

		switch ( $action ) {
			case 'cleanup':
				$builder->cleanup( $state );
				\delete_option( $optionKey );
				return [ 'cleaned' => true ];

			case 'inspect':
				return $builder->inspect( $state );

			case 'seed':
				if ( $state !== [] ) {
					$builder->cleanup( $state );
					\delete_option( $optionKey );
				}

				$result = $builder->seed( \trim( $args[ 0 ] ?? '' ) );
				\update_option( $optionKey, $result[ 'state' ], false );
				return $result[ 'contract' ];

			default:
				throw new \RuntimeException( 'Unknown browser fixture action: '.$action );
		}
	}

	/**
	 * @param list<string> $args
	 * @return array<string,mixed>
	 */
	private static function runPublicBlockRecoveryFixture( string $action, array $args ) :array {
		$builder = new PublicBlockRecoveryFixtureBuilder();
		$optionKey = self::fixtureOptionKey( 'public-block-recovery' );
		$state = \get_option( $optionKey, [] );
		$state = \is_array( $state ) ? $state : [];

		switch ( $action ) {
			case 'cleanup':
				$builder->cleanup( $state );
				\delete_option( $optionKey );
				return [ 'cleaned' => true ];

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
