<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Login\TwoFactor\Import;

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
