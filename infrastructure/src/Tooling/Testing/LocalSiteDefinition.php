<?php declare( strict_types=1 );

namespace FernleafSystems\ShieldPlatform\Tooling\Testing;

class LocalSiteDefinition {

	private string $key;

	private string $label;

	private string $composeProjectName;

	private string $siteUrl;

	private string $siteHost;

	private int $sitePort;

	private string $dbName;

	private string $siteTitle;

	private string $adminUser;

	private string $adminPassword;

	private string $adminEmail;

	private string $composeFile;

	private string $dbHost;

	private bool $usesSharedDatabase;

	private string $sharedDatabaseComposeFile;

	private string $sharedDatabaseComposeProjectName;

	public function __construct(
		string $key,
		string $label,
		string $composeProjectName,
		string $siteUrl,
		string $siteHost,
		int $sitePort,
		string $dbName,
		string $siteTitle,
		string $adminUser = 'admin',
		string $adminPassword = 'password',
		string $adminEmail = 'devnull@example.com',
		string $composeFile = 'tests/docker/docker-compose.local-site.yml',
		string $dbHost = 'db:3306',
		bool $usesSharedDatabase = false,
		string $sharedDatabaseComposeFile = '',
		string $sharedDatabaseComposeProjectName = ''
	) {
		if ( $composeFile === '' ) {
			throw new \InvalidArgumentException( 'Local site compose file must not be empty.' );
		}
		if ( $dbHost === '' ) {
			throw new \InvalidArgumentException( 'Local site database host must not be empty.' );
		}
		if ( $usesSharedDatabase && ( $sharedDatabaseComposeFile === '' || $sharedDatabaseComposeProjectName === '' ) ) {
			throw new \InvalidArgumentException( 'Shared database sites must define shared database compose metadata.' );
		}

		$this->key = $key;
		$this->label = $label;
		$this->composeProjectName = $composeProjectName;
		$this->siteUrl = $siteUrl;
		$this->siteHost = $siteHost;
		$this->sitePort = $sitePort;
		$this->dbName = $dbName;
		$this->siteTitle = $siteTitle;
		$this->adminUser = $adminUser;
		$this->adminPassword = $adminPassword;
		$this->adminEmail = $adminEmail;
		$this->composeFile = $composeFile;
		$this->dbHost = $dbHost;
		$this->usesSharedDatabase = $usesSharedDatabase;
		$this->sharedDatabaseComposeFile = $sharedDatabaseComposeFile;
		$this->sharedDatabaseComposeProjectName = $sharedDatabaseComposeProjectName;
	}

	public function composeFile() :string {
		return $this->composeFile;
	}

	public function adminEmail() :string {
		return $this->adminEmail;
	}

	public function adminPassword() :string {
		return $this->adminPassword;
	}

	public function adminUser() :string {
		return $this->adminUser;
	}

	public function composeProjectName() :string {
		return $this->composeProjectName;
	}

	public function dbName() :string {
		return $this->dbName;
	}

	public function dbHost() :string {
		return $this->dbHost;
	}

	public function key() :string {
		return $this->key;
	}

	public function label() :string {
		return $this->label;
	}

	public function siteHost() :string {
		return $this->siteHost;
	}

	public function sitePort() :int {
		return $this->sitePort;
	}

	public function siteTitle() :string {
		return $this->siteTitle;
	}

	public function siteUrl() :string {
		return $this->siteUrl;
	}

	public function sharedDatabaseComposeFile() :string {
		return $this->sharedDatabaseComposeFile;
	}

	public function sharedDatabaseComposeProjectName() :string {
		return $this->sharedDatabaseComposeProjectName;
	}

	public function usesSharedDatabase() :bool {
		return $this->usesSharedDatabase;
	}
}
