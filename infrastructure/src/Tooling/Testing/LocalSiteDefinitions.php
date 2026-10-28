<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

use Symfony\Component\Filesystem\Path;

class LocalSiteDefinitions {

	public const BROWSER_HARNESS_LABEL_VALUE = 'shield-plugin-browser';
	public const BROWSER_NETWORK_NAME = 'shield-browser-lanes';
	public const BROWSER_DB_CONTAINER_NAME = 'shield-browser-lanes-db';
	public const LEGACY_BROWSER_NETWORK_NAME = 'shield-browser-tests';
	public const LEGACY_BROWSER_DB_CONTAINER_NAME = 'shield-browser-db';
	public const LEGACY_BROWSER_DB_VOLUME_NAME = 'shield-browser-db_browser-db';

	private const BROWSER_LANE_BASE_PORT = 8900;
	private const WORKTREE_TEST_SITE_BASE_PORT = 8870;
	private const BROWSER_LANE_COMPOSE_FILE = 'tests/docker/docker-compose.browser-lane.yml';
	private const BROWSER_DB_COMPOSE_FILE = 'tests/docker/docker-compose.browser-db.yml';
	private const BROWSER_DB_PROJECT_NAME = 'shield-browser-lanes-db';
	private const BROWSER_DB_HOST = 'shield-browser-lanes-db:3306';

	public static function dev() :LocalSiteDefinition {
		return new LocalSiteDefinition(
			'dev',
			'Local dev site',
			'shield-local-site',
			'http://127.0.0.1:8888',
			'127.0.0.1',
			8888,
			'shield_local_site',
			'Shield Local Dev Site'
		);
	}

	public static function test() :LocalSiteDefinition {
		return new LocalSiteDefinition(
			'test',
			'Local test site',
			'shield-test-site',
			'http://127.0.0.1:8889',
			'127.0.0.1',
			8889,
			'shield_test_site',
			'Shield Local Test Site'
		);
	}

	/**
	 * The main checkout keeps the 8889 test site; each linked Git worktree gets its own slot so one
	 * worktree cannot rebuild another's site.
	 */
	public static function testForCheckout( string $rootDir, ?LocalTestSiteSlots $slots = null ) :LocalSiteDefinition {
		if ( !\is_file( Path::join( $rootDir, '.git' ) ) ) {
			return self::test();
		}

		$slot = ( $slots ?? new LocalTestSiteSlots() )->slotFor( $rootDir );
		$port = self::WORKTREE_TEST_SITE_BASE_PORT + $slot;
		return new LocalSiteDefinition(
			'test',
			'Local test site (worktree slot '.$slot.')',
			'shield-test-site-wt'.$slot,
			'http://127.0.0.1:'.$port,
			'127.0.0.1',
			$port,
			'shield_test_site_wt'.$slot,
			'Shield Local Test Site '.$slot
		);
	}

	public static function testFromEnvironment( ?string $rootDir = null ) :LocalSiteDefinition {
		$laneIndex = \getenv( 'SHIELD_BROWSER_LANE_INDEX' );
		if ( $laneIndex === false || $laneIndex === '' ) {
			return $rootDir === null ? self::test() : self::testForCheckout( $rootDir );
		}
		if ( !\ctype_digit( $laneIndex ) || (int)$laneIndex < 1 ) {
			throw new \InvalidArgumentException( 'SHIELD_BROWSER_LANE_INDEX must be a positive integer.' );
		}

		return self::browserLane( (int)$laneIndex );
	}

	public static function browserLane( int $laneIndex ) :LocalSiteDefinition {
		if ( $laneIndex < 1 ) {
			throw new \InvalidArgumentException( 'Browser lane index must be greater than zero.' );
		}

		return new LocalSiteDefinition(
			'browser-lane-'.$laneIndex,
			'Browser test lane '.$laneIndex,
			'shield-browser-lane-'.$laneIndex,
			'http://127.0.0.1:'.( self::BROWSER_LANE_BASE_PORT + $laneIndex ),
			'127.0.0.1',
			self::BROWSER_LANE_BASE_PORT + $laneIndex,
			'shield_browser_lane_'.$laneIndex,
			'Shield Browser Test Lane '.$laneIndex,
			'admin',
			'password',
			'devnull@example.com',
			self::BROWSER_LANE_COMPOSE_FILE,
			self::BROWSER_DB_HOST,
			true,
			self::BROWSER_DB_COMPOSE_FILE,
			self::BROWSER_DB_PROJECT_NAME
		);
	}

	public static function browserSharedDatabaseComposeProjectName() :string {
		return self::BROWSER_DB_PROJECT_NAME;
	}
}
