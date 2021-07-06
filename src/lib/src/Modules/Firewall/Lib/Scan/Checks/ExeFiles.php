<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Lib\Scan\Checks;

use FernleafSystems\Wordpress\Plugin\Shield\Modules\ModConsumer;

class ExeFiles extends Base {

	use ModConsumer;

	/**
	 * @return false|\WP_Error
	 */
	public function run() {
		$found = false;
		foreach ( $this->getFileNames() as $param => $file ) {
			foreach ( $this->getFirewallPatterns_Regex() as $term ) {
				if ( preg_match( $term, $file ) ) {
					$found = new \WP_Error( 'shield-firewall', '', [
						'term'  => $term,
						'param' => $param,
						'value' => $file,
						'check' => $this->check,
						'type'  => 'regex',
					] );
					break 2;
				}
			}
		}
		return $found;
	}

	private function getFileNames() :array {
		return array_filter( array_map(
			function ( $file ) {
				return $file[ 'name' ] ?? '';
			},
			( !empty( $_FILES ) && is_array( $_FILES ) ) ? $_FILES : []
		) );
	}
}