<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions;

class MatchRequestParamFileUploads extends MatchRequestParam {

	use Traits\TypeRequest;

	public const SLUG = 'match_request_param_file_uploads';

	public function getDescription() :string {
		return __( 'Do any files uploaded in the request match the given set of filenames.', 'wp-simple-firewall' );
	}

	protected function getRequestParamsToTest() :array {
		return \array_filter( \array_map(
			fn ( $file ) => $file[ 'name' ] ?? '',
			!empty( $_FILES ) ? $_FILES : [] // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Firewall rule condition that matches the names of uploaded files on any request, so there's no form nonce to check; the files themselves aren't touched.
		) );
	}
}
