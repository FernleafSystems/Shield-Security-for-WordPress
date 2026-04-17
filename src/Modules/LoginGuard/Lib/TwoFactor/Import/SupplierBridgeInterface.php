<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\LoginGuard\Lib\TwoFactor\Import;

interface SupplierBridgeInterface {

	public function getSupplierSlug() :string;

	/**
	 * @return string[]
	 */
	public function getSupportedFactorSlugs() :array;

	/**
	 * @param string[] $importableFactorSlugs
	 */
	public function discoverForUser( \WP_User $user, array $importableFactorSlugs = [] ) :SupplierFactorData;
}
