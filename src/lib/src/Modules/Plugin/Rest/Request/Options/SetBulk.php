<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Plugin\Rest\Request\Options;

use FernleafSystems\Wordpress\Plugin\Core\Rest\Exceptions\ApiException;

class SetBulk extends Base {

	protected function process() :array {
		$con = self::con();
		/** @var RequestVO $req */
		$req = $this->getRequestVO();

		$filterKeys = [];
		foreach ( $req->options as $opt ) {
			$def = $this->getOptionData( $opt[ 'key' ] );
			if ( !empty( $def ) ) {
				$filterKeys[] = $opt[ 'key' ];
				$opts = $con->modules[ $def[ 'module' ] ]->getOptions();
				if ( is_null( $opt[ 'value' ] ) ) {
					$opts->resetOptToDefault( $opt[ 'key' ] );
				}
				else {
					/**
					 * It turns out JSON-encoded integers come out as type:double, so we have to convert it,
					 * so we can validate it after the fact using serialize, or we'll get i:0 vs d:0.
					 */
					if ( $def[ 'type' ] === 'integer' ) {
						$opt[ 'value' ] = (int)$opt[ 'value' ];
					}

					$opts->setOpt( $opt[ 'key' ], $opt[ 'value' ] );

					if ( serialize( $opt[ 'value' ] ) !== serialize( $opts->getOpt( $opt[ 'key' ] ) ) ) {
						throw new ApiException( sprintf( 'Failed to update option (%s). Value may be of an incorrect type.', $opt[ 'key' ] ) );
					}
				}
			}
		}
		$req->filter_keys = $filterKeys;
		return $this->getAllOptions();
	}
}