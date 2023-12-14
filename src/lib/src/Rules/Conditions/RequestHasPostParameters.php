<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Services\Services;

class RequestHasPostParameters extends Base {

	use Traits\TypeRequest;

	public const SLUG = 'request_has_post_parameters';

	public function getDescription() :string {
		return __( "Does the request have any POST parameters.", 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		$post = Services::Request()->post;
		return Services::Request()->isPost() && \is_array( $post ) && !empty( $post );
	}
}