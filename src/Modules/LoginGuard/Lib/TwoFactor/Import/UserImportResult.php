<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Import;

class UserImportResult {

	public bool $hasSourceState = false;

	/**
	 * @var string[]
	 */
	public array $importedFactorSlugs = [];

	/**
	 * @var array<string, string>
	 */
	public array $skippedFactorReasons = [];
}
