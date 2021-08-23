<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Lib\Scan\Handlers;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

abstract class Base {

	use ModConsumer;

	const SLUG = '';
	const TYPE = '';

	public function runCheck() :\WP_Error {
		$checkResult = new \WP_Error();

		if ( !empty( $this->getFirewallPatterns() ) ) {
			$checkResult = $this->testSimplePatterns();
			if ( !is_wp_error( $checkResult ) ) {
				$checkResult = $this->testRegexPatterns();
			}
		}

		return $checkResult;
	}

	protected function getFirewallPatterns() :array {
		return $this->getOptions()->getDef( 'firewall_patterns' )[ static::SLUG ] ?? [];
	}

	protected function getFirewallPatterns_Regex() :array {
		return array_map(
			function ( $regex ) {
				return '/'.$regex.'/i';
			},
			$this->getFirewallPatterns()[ 'regex' ] ?? []
		);
	}

	protected function getFirewallPatterns_Simple() :array {
		return $this->getFirewallPatterns()[ 'simple' ] ?? [];
	}

	protected function getItemsToScan() :array {
		return [];
	}

	protected function getScanName() :string {
		return '';
	}

	protected function testRegexPatterns() :\WP_Error {
		$found = new \WP_Error;
		foreach ( $this->getFirewallPatterns_Regex() as $term ) {
			foreach ( $this->getItemsToScan() as $param => $value ) {
				if ( preg_match( $term, $value ) ) {
					$found = new \WP_Error( 'shield-firewall', '', [
						'name'  => $this->getScanName(),
						'term'  => $term,
						'param' => $param,
						'value' => $value,
						'scan'  => static::SLUG,
						'type'  => static::TYPE,
					] );
					break 2;
				}
			}
		}
		return $found;
	}

	protected function testSimplePatterns() :\WP_Error {
		$found = new \WP_Error;
		foreach ( $this->getFirewallPatterns_Simple() as $term ) {
			foreach ( $this->getItemsToScan() as $param => $value ) {
				if ( stripos( $value, $term ) !== false ) {
					$found = new \WP_Error( 'shield-firewall', '', [
						'name'  => $this->getScanName(),
						'term'  => $term,
						'param' => $param,
						'value' => $value,
						'scan'  => static::SLUG,
						'type'  => static::TYPE,
					] );
					break 2;
				}
			}
		}
		return $found;
	}
}