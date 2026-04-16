<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Login\TwoFactor\Import;

class ImportResult {

	public string $supplierSlug = '';

	public bool $checked = false;

	/**
	 * @var string[]
	 */
	public array $importedFactorSlugs = [];

	public function __construct( string $supplierSlug ) {
		$this->supplierSlug = $supplierSlug;
	}
}
