<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Firewall\Lib\Scan\Handlers;

class DirTraversal extends BaseRequestParams {

	const SLUG = 'dirtraversal';

	protected function getScanName() :string {
		return __( 'Directory Traversal', 'wp-simple-firewall' );
	}
}