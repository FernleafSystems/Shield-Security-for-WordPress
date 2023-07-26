<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class MatchRequestParamFileUploads extends MatchRequestParam {

	public const SLUG = 'match_request_param_file_uploads';

	protected function getRequestParamsToTest() :array {
		return \array_filter( \array_map(
			function ( $file ) {
				return $file[ 'name' ] ?? '';
			},
			( !empty( $_FILES ) && \is_array( $_FILES ) ) ? $_FILES : []
		) );
	}
}