<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Lib\Scan\Checks;

class Standard extends Base {

	private $params;

	/**
	 * @param array  $params
	 * @return false|\WP_Error
	 */
	public function run( array $params ) {
		$checkResult = false;

		$this->params = $params;

		if ( !empty( $this->getFirewallPatterns() ) ) {
			$checkResult = $this->testSimplePatterns();
			if ( !is_wp_error( $checkResult ) ) {
				$checkResult = $this->testRegexPatterns();
			}
		}

		return $checkResult;
	}

	/**
	 * @return false|\WP_Error
	 */
	protected function testRegexPatterns() {
		$found = false;
		foreach ( $this->getFirewallPatterns_Regex() as $term ) {
			foreach ( $this->params as $param => $value ) {
				if ( preg_match( $term, $value ) ) {
					$found = new \WP_Error( 'shield-firewall', '', [
						'param' => $param,
						'value' => $value,
						'check' => $this->check,
						'type'  => 'regex',
					] );
					break( 2 );
				}
			}
		}
		return $found;
	}

	/**
	 * @return false|\WP_Error
	 */
	protected function testSimplePatterns() {
		$found = false;
		foreach ( $this->getFirewallPatterns_Simple() as $term ) {
			foreach ( $this->params as $param => $value ) {
				if ( stripos( $value, $term ) !== false ) {
					$found = new \WP_Error( 'shield-firewall', '', [
						'param' => $param,
						'value' => $value,
						'check' => $this->check,
						'type'  => 'simple',
					] );
					break( 2 );
				}
			}
		}
		return $found;
	}
}