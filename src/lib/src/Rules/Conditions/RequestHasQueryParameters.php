<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

use FernleafSystems\Wordpress\Services\Services;

class RequestHasQueryParameters extends Base {

	use Traits\TypeRequest;

	public const SLUG = 'request_has_query_parameters';

	public function getDescription() :string {
		return __( "Does the request have any QUERY parameters.", 'wp-simple-firewall' );
	}

	protected function execConditionCheck() :bool {
		$query = Services::Request()->query;
		return \is_array( $query ) && !empty( $query );
	}
}