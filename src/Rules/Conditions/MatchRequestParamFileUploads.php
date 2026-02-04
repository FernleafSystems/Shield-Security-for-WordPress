<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class MatchRequestParamFileUploads extends MatchRequestParam {

	use Traits\TypeRequest;

	public const SLUG = 'match_request_param_file_uploads';

	public function getDescription() :string {
		return __( "Do any files uploaded in the request match the given set of filenames.", 'wp-simple-firewall' );
	}

	protected function getRequestParamsToTest() :array {
		return \array_filter( \array_map(
			function ( $file ) {
				return $file[ 'name' ] ?? '';
			},
			( !empty( $_FILES ) && \is_array( $_FILES ) ) ? $_FILES : []
		) );
	}
}