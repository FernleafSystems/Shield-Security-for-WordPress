<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Zones\Component;

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

	protected function configureStatus() :array {
		$ruleStates = $this->firewallRuleStates();
		$status = parent::status();

		if ( \count( \array_filter( \array_intersect_key(
			$ruleStates,
			\array_filter(
				$this->firewallRuleDefinitions(),
				static fn( array $definition ) :bool => !empty( $definition[ 'primary' ] )
			)
		) ) ) === 0 ) {
			$status[ 'level' ] = EnumEnabledStatus::BAD;
			$status[ 'exp' ][] = __( 'No primary exploit-blocking firewall rules are enabled.', 'wp-simple-firewall' );
		}

		foreach ( \array_keys( \array_filter( $ruleStates, static fn( bool $enabled ) :bool => !$enabled ) ) as $ruleKey ) {
			$status[ 'exp' ][] = sprintf(
				__( "Requests that trigger the firewall rule '%s' aren't intercepted.", 'wp-simple-firewall' ),
				$this->firewallRuleName( $ruleKey )
			);
		}

		if ( $status[ 'level' ] !== EnumEnabledStatus::BAD ) {
			$status[ 'level' ] = empty( $status[ 'exp' ] ) ? EnumEnabledStatus::GOOD : EnumEnabledStatus::OKAY;
		}

		return $status;
	}

	/**
	 * @inheritDoc
	 */
	protected function status() :array {
		$status = parent::status();
		$rules = $this->firewallRuleStates();
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

		foreach ( \array_diff( \array_keys( $rules ), $enabled ) as $key ) {
			$status[ 'exp' ][] = sprintf( "Requests that trigger the firewall rule '%s' aren't intercepted.", $this->firewallRuleName( $key ) );
		}

		return $status;
	}

	public function postureSignals() :array {
		$states = $this->firewallRuleStates();
		$signals = [];
		foreach ( $this->firewallRuleDefinitions() as $optKey => $definition ) {
			$enabled = !empty( $states[ $optKey ] );
			$name = $this->firewallRuleName( $optKey );
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

	/**
	 * @return array<string,bool>
	 */
	private function firewallRuleStates() :array {
		$states = [];
		foreach ( \array_keys( $this->firewallRuleDefinitions() ) as $ruleKey ) {
			$states[ $ruleKey ] = self::con()->opts->optIs( $ruleKey, 'Y' );
		}
		return $states;
	}

	/**
	 * @return array<string,array{name:string,slug:string,weight:int,primary:bool}>
	 */
	private function firewallRuleDefinitions() :array {
		return [
			'block_dir_traversal'    => [ 'name' => __( 'Directory Traversals', 'wp-simple-firewall' ), 'slug' => 'firewall_dir_traversal', 'weight' => 5, 'primary' => true ],
			'block_sql_queries'      => [ 'name' => __( 'SQL Queries', 'wp-simple-firewall' ), 'slug' => 'firewall_sql_queries', 'weight' => 5, 'primary' => true ],
			'block_field_truncation' => [ 'name' => __( 'Field Truncation', 'wp-simple-firewall' ), 'slug' => 'firewall_field_truncation', 'weight' => 3, 'primary' => false ],
			'block_php_code'         => [ 'name' => __( 'PHP Code', 'wp-simple-firewall' ), 'slug' => 'firewall_php_code', 'weight' => 4, 'primary' => true ],
			'block_aggressive'       => [ 'name' => __( 'Aggressive Scan', 'wp-simple-firewall' ), 'slug' => 'firewall_aggressive', 'weight' => 4, 'primary' => false ],
		];
	}

	private function firewallRuleName( string $ruleKey ) :string {
		$name = $this->firewallRuleDefinitions()[ $ruleKey ][ 'name' ] ?? '';
		return \is_string( $name ) && $name !== '' ? $name : $ruleKey;
	}
}
