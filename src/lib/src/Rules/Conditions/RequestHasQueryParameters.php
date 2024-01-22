<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class RequestHasQueryParameters extends Base {

	use Traits\TypeRequest;

	public const SLUG = 'request_has_query_parameters';

	protected function execConditionCheck() :bool {
		$query = $this->req->request->query;
		return \is_array( $query ) && !empty( $query );
	}

	public function getDescription() :string {
		return __( 'Does the request have any QUERY parameters.', 'wp-simple-firewall' );
	}
}