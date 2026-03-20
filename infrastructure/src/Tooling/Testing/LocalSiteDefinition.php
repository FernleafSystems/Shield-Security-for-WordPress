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
		string $adminEmail = 'devnull@example.com'
	) {
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
}
