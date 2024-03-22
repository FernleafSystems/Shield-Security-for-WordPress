<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rest\Request\Options;

use FernleafSystems\Wordpress\Plugin\Core\Rest\Exceptions\ApiException;

class SetBulk extends Base {

	protected function process() :array {
		$opts = self::con()->opts;
		/** @var RequestVO $req */
		$req = $this->getRequestVO();

		$filterKeys = [];
		foreach ( $req->options as $opt ) {
			$key = $opt[ 'key' ];
			if ( $opts->optExists( $key ) ) {
				$filterKeys[] = $key;
				if ( \is_null( $opt[ 'value' ] ) ) {
					$opts->optReset( $key );
				}
				else {
					/**
					 * It turns out JSON-encoded integers come out as type:double, so we have to convert it,
					 * so we can validate it after the fact using serialize, or we'll get i:0 vs d:0.
					 */
					if ( $opts->optType( $key ) === 'integer' ) {
						$opt[ 'value' ] = (int)$opt[ 'value' ];
					}

					$opts->optSet( $key, $opt[ 'value' ] );

					if ( \serialize( $opt[ 'value' ] ) !== \serialize( $opts->optGet( $key ) ) ) {
						throw new ApiException( sprintf( 'Failed to update option (%s). Value may be of an incorrect type.', $key ) );
					}
				}
			}
		}
		$req->filter_keys = $filterKeys;
		return $this->getAllOptions();
	}
}