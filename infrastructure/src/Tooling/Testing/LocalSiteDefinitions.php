<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

class LocalSiteDefinitions {

	private const BROWSER_LANE_COMPOSE_FILE = 'tests/docker/docker-compose.browser-lane.yml';
	private const BROWSER_DB_COMPOSE_FILE = 'tests/docker/docker-compose.browser-db.yml';
	private const BROWSER_DB_PROJECT_NAME = 'shield-browser-db';
	private const BROWSER_DB_HOST = 'shield-browser-db:3306';

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

	public static function testFromEnvironment() :LocalSiteDefinition {
		$laneIndex = \getenv( 'SHIELD_BROWSER_LANE_INDEX' );
		if ( $laneIndex === false || $laneIndex === '' ) {
			return self::test();
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
			'shield-test-site-lane-'.$laneIndex,
			'http://127.0.0.1:'.( 8889 + $laneIndex ),
			'127.0.0.1',
			8889 + $laneIndex,
			'shield_test_site_lane_'.$laneIndex,
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
}
