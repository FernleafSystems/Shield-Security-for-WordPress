<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Components\CompCons\Login\TwoFactor\Import;

interface SupplierBridgeInterface {

	public function getSupplierSlug() :string;

	public function isApplicable() :bool;

	public function discoverForUser( \WP_User $user ) :SupplierFactorData;
}
