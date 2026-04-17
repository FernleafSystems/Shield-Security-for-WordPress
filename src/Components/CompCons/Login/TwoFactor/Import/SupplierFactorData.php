<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Login\TwoFactor\Import;

class SupplierFactorData {

	public bool $hasSourceState = false;

	/**
	 * @var string[]
	 */
	public array $sourceFactorSlugs = [];

	/**
	 * @var array<string, string>
	 */
	public array $skippedFactorReasons = [];

	public ?string $gaSecret = null;

	public bool $emailEnabled = false;

	/**
	 * @var string[]
	 */
	public array $backupCodeHashes = [];
}
