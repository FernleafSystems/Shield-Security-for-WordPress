<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class IsPhpCli extends Base {

	use Traits\TypePhp;

	protected function execConditionCheck() :bool {
		$sapi = \defined( 'PHP_SAPI' ) ? \PHP_SAPI : ( \function_exists( '\php_sapi_name' ) ? \php_sapi_name() : null );
		return $sapi === 'cli';
	}

	public function getDescription() :string {
		return __( 'Is the request triggered by PHP CLI.', 'wp-simple-firewall' );
	}
}