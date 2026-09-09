<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

class DockerCleanupPolicy {

	public const SCOPE_BROWSER = 'browser';
	public const SCOPE_SOURCE = 'source';
	public const SCOPE_INTEGRATION_LOCAL = 'integration-local';
	public const SCOPE_CROSS_SITE = 'cross-site';
	public const SCOPE_DEV_SITE = 'dev-site';
	public const SCOPE_TEST_SITE = 'test-site';

	private string $scope;

	private string $harnessLabelValue;

	private string $envPrefix;

	private bool $browserLegacyCleanup;

	/** @var array<int,array{project:string,compose_file:string,description:string}> */
	private array $composeDowns;

	/**
	 * @param array<int,array{project:string,compose_file:string,description:string}> $composeDowns
	 */
	private function __construct(
		string $scope,
		string $harnessLabelValue,
		string $envPrefix,
		array $composeDowns,
		bool $browserLegacyCleanup = false
	) {
		$this->scope = $scope;
		$this->harnessLabelValue = $harnessLabelValue;
		$this->envPrefix = $envPrefix;
		$this->composeDowns = $composeDowns;
		$this->browserLegacyCleanup = $browserLegacyCleanup;
	}

	public static function browser( int $laneCount ) :self {
		$composeDowns = [];
		for ( $laneIndex = 1; $laneIndex <= $laneCount; $laneIndex++ ) {
			$composeDowns[] = [
				'project' => LocalSiteDefinitions::browserLane( $laneIndex )->composeProjectName(),
				'compose_file' => 'tests/docker/docker-compose.browser-lane.yml',
				'description' => 'compose down lane '.$laneIndex,
			];
		}
		$composeDowns[] = [
			'project' => LocalSiteDefinitions::browserSharedDatabaseComposeProjectName(),
			'compose_file' => 'tests/docker/docker-compose.browser-db.yml',
			'description' => 'compose down shared database',
		];

		return new self(
			self::SCOPE_BROWSER,
			LocalSiteDefinitions::BROWSER_HARNESS_LABEL_VALUE,
			'SHIELD_BROWSER',
			$composeDowns,
			true
		);
	}

	public static function source() :self {
		return new self(
			self::SCOPE_SOURCE,
			'shield-plugin-source',
			'SHIELD_DOCKER',
			[
				[
					'project' => 'shield-tests',
					'compose_file' => 'tests/docker/docker-compose.yml',
					'description' => 'compose down source runtime',
				],
			]
		);
	}

	public static function integrationLocal() :self {
		return new self(
			self::SCOPE_INTEGRATION_LOCAL,
			'shield-plugin-integration-local',
			'SHIELD_DOCKER',
			[
				[
					'project' => 'shield-local-db',
					'compose_file' => 'tests/docker/docker-compose.local-db.yml',
					'description' => 'compose down integration local database',
				],
			]
		);
	}

	public static function crossSite() :self {
		return new self(
			self::SCOPE_CROSS_SITE,
			'shield-plugin-cross-site',
			'SHIELD_DOCKER',
			[
				[
					'project' => 'shield-cross-site',
					'compose_file' => 'tests/docker/docker-compose.cross-site.yml',
					'description' => 'compose down cross-site pair',
				],
			]
		);
	}

	public static function devSite() :self {
		return new self(
			self::SCOPE_DEV_SITE,
			'shield-plugin-dev-site',
			'SHIELD_DOCKER',
			[
				[
					'project' => 'shield-local-site',
					'compose_file' => 'tests/docker/docker-compose.local-site.yml',
					'description' => 'compose down dev site',
				],
			]
		);
	}

	public static function testSite() :self {
		return new self(
			self::SCOPE_TEST_SITE,
			'shield-plugin-test-site',
			'SHIELD_DOCKER',
			[
				[
					'project' => 'shield-test-site',
					'compose_file' => 'tests/docker/docker-compose.local-site.yml',
					'description' => 'compose down test site',
				],
			]
		);
	}

	public static function forScope( string $scope, int $laneCount = 2 ) :self {
		switch ( $scope ) {
			case self::SCOPE_BROWSER:
				return self::browser( $laneCount );
			case self::SCOPE_SOURCE:
				return self::source();
			case self::SCOPE_INTEGRATION_LOCAL:
				return self::integrationLocal();
			case self::SCOPE_CROSS_SITE:
				return self::crossSite();
			case self::SCOPE_DEV_SITE:
				return self::devSite();
			case self::SCOPE_TEST_SITE:
				return self::testSite();
		}

		throw new \InvalidArgumentException( 'Unknown Docker cleanup scope: '.$scope );
	}

	/**
	 * @return string[]
	 */
	public static function scopes() :array {
		return [
			self::SCOPE_BROWSER,
			self::SCOPE_SOURCE,
			self::SCOPE_INTEGRATION_LOCAL,
			self::SCOPE_CROSS_SITE,
			self::SCOPE_DEV_SITE,
			self::SCOPE_TEST_SITE,
		];
	}

	public function scope() :string {
		return $this->scope;
	}

	public function harnessLabelValue() :string {
		return $this->harnessLabelValue;
	}

	public function browserLegacyCleanup() :bool {
		return $this->browserLegacyCleanup;
	}

	/**
	 * @return array<int,array{project:string,compose_file:string,description:string}>
	 */
	public function composeDowns() :array {
		return $this->composeDowns;
	}

	/**
	 * @return array<string,string>
	 */
	public function labelEnvironment(
		string $containerRunId,
		string $containerLifecycle,
		string $lane,
		string $containerExpiresAt,
		string $volumeRunId,
		string $volumeLifecycle,
		string $volumeExpiresAt
	) :array {
		if ( $this->envPrefix === 'SHIELD_BROWSER' ) {
			return [
				'SHIELD_BROWSER_LABEL_HARNESS' => $this->harnessLabelValue,
				'SHIELD_BROWSER_LABEL_LANE' => $lane,
				'SHIELD_BROWSER_CONTAINER_RUN_ID' => $containerRunId,
				'SHIELD_BROWSER_CONTAINER_LIFECYCLE' => $containerLifecycle,
				'SHIELD_BROWSER_CONTAINER_EXPIRES_AT' => $containerExpiresAt,
				'SHIELD_BROWSER_VOLUME_RUN_ID' => $volumeRunId,
				'SHIELD_BROWSER_VOLUME_LIFECYCLE' => $volumeLifecycle,
				'SHIELD_BROWSER_VOLUME_EXPIRES_AT' => $volumeExpiresAt,
			];
		}

		return [
			'SHIELD_DOCKER_LABEL_HARNESS' => $this->harnessLabelValue,
			'SHIELD_DOCKER_LABEL_LANE' => $lane,
			'SHIELD_DOCKER_CONTAINER_RUN_ID' => $containerRunId,
			'SHIELD_DOCKER_CONTAINER_LIFECYCLE' => $containerLifecycle,
			'SHIELD_DOCKER_CONTAINER_EXPIRES_AT' => $containerExpiresAt,
			'SHIELD_DOCKER_VOLUME_RUN_ID' => $volumeRunId,
			'SHIELD_DOCKER_VOLUME_LIFECYCLE' => $volumeLifecycle,
			'SHIELD_DOCKER_VOLUME_EXPIRES_AT' => $volumeExpiresAt,
		];
	}
}
