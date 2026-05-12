<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Login\TwoFactor\Import;

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
