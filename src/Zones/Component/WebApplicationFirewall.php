<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

use FernleafSystems\Wordpress\Plugin\Shield\Controller\Config\Modules\StringsOptions;
use FernleafSystems\Wordpress\Plugin\Shield\Zones\Common\EnumEnabledStatus;

class WebApplicationFirewall extends Base {

	public function title() :string {
		return __( 'Web Application Firewall (WAF)', 'wp-simple-firewall' );
	}

	public function subtitle() :string {
		return __( 'Block requests to the site that contain suspicious data.', 'wp-simple-firewall' );
	}

	protected function tooltip() :string {
		return __( 'Edit WAF settings', 'wp-simple-firewall' );
	}

	/**
	 * @inheritDoc
	 */
	protected function status() :array {
		$opts = self::con()->opts;
		$status = parent::status();

		$keys = [
			'block_dir_traversal',
			'block_sql_queries',
			'block_field_truncation',
			'block_php_code',
			'block_aggressive',
		];

		$rules = [];
		foreach ( $keys as $key ) {
			$rules[ $key ] = $opts->optIs( $key, 'Y' );
		}
		$enabled = \array_keys( \array_filter( $rules ) );

		if ( \count( $enabled ) > 3 ) {
			$status[ 'level' ] = EnumEnabledStatus::GOOD;
		}
		elseif ( \count( $enabled ) > 2 ) {
			$status[ 'level' ] = EnumEnabledStatus::OKAY;
		}
		else {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
		}

		$optStrings = new StringsOptions();
		foreach ( \array_diff( $keys, $enabled ) as $key ) {
			$status[ 'exp' ][] = sprintf( "Requests that trigger the firewall rule '%s' aren't intercepted.", $optStrings->getFor( $key )[ 'name' ] );
		}

		return $status;
	}

	public function postureSignals() :array {
		$optStrings = new StringsOptions();
		$signals = [];
		foreach ( [
			'block_dir_traversal'   => [ 'slug' => 'firewall_dir_traversal', 'weight' => 5 ],
			'block_sql_queries'     => [ 'slug' => 'firewall_sql_queries', 'weight' => 5 ],
			'block_field_truncation'=> [ 'slug' => 'firewall_field_truncation', 'weight' => 3 ],
			'block_php_code'        => [ 'slug' => 'firewall_php_code', 'weight' => 4 ],
			'block_aggressive'      => [ 'slug' => 'firewall_aggressive', 'weight' => 4 ],
		] as $optKey => $definition ) {
			$enabled = self::con()->opts->optIs( $optKey, 'Y' );
			$name = $optStrings->getFor( $optKey )[ 'name' ];
			$signals[] = $this->buildPostureSignal(
				$definition[ 'slug' ],
				sprintf( __( 'WAF Rule: %s', 'wp-simple-firewall' ), $name ),
				$definition[ 'weight' ],
				$enabled ? $definition[ 'weight' ] : 0,
				$enabled ? 'good' : 'critical',
				$enabled,
				[
					$enabled
						? sprintf( __( "Requests that trigger '%s' are intercepted.", 'wp-simple-firewall' ), $name )
						: sprintf( __( "Requests that trigger '%s' are not intercepted.", 'wp-simple-firewall' ), $name ),
				]
			);
		}
		return $signals;
	}
}
