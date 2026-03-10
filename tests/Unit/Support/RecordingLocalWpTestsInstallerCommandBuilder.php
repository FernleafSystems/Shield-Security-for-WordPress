<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Tests\Unit\Support;

use FernleafSystems\ShieldPlatform\Tooling\Testing\LocalWpTestsInstallerCommandBuilder;

class RecordingLocalWpTestsInstallerCommandBuilder extends LocalWpTestsInstallerCommandBuilder {

	/** @var array<int,array{db_name:string,db_user:string,db_pass:string,db_host:string,wp_version:string,skip_db_create:bool}> */
	public array $calls = [];

	/** @var string[] */
	private array $command;

	/**
	 * @param string[] $command
	 */
	public function __construct( array $command ) {
		parent::__construct();
		$this->command = $command;
	}

	public function build(
		string $dbName,
		string $dbUser,
		string $dbPass,
		string $dbHost,
		string $wpVersion,
		bool $skipDbCreate
	) :array {
		$this->calls[] = [
			'db_name' => $dbName,
			'db_user' => $dbUser,
			'db_pass' => $dbPass,
			'db_host' => $dbHost,
			'wp_version' => $wpVersion,
			'skip_db_create' => $skipDbCreate,
		];

		return $this->command;
	}
}
