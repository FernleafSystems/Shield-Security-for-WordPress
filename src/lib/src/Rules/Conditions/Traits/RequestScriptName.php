<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Conditions\Traits;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\Exceptions\RequestScriptNameUnavailableException;
use FernleafSystems\Wordpress\Services\Services;

/**
 * @property string $request_script_name
 */
trait RequestScriptName {

	/**
	 * @throws RequestScriptNameUnavailableException
	 */
	protected function getRequestScriptName() :string {
		$value = $this->request_script_name;
		if ( empty( $value ) ) {
			$req = Services::Request();
			$possible = \array_values( \array_unique( \array_map( '\basename', \array_filter( [
				$req->server( 'SCRIPT_NAME' ),
				$req->server( 'SCRIPT_FILENAME' ),
				$req->server( 'PHP_SELF' )
			] ) ) ) );
			if ( \count( $possible ) === 1 ) {
				$value = current( $possible );
			}
			else {
				throw new RequestScriptNameUnavailableException( 'Request script name is unavailable.' );
			}
		}
		return $value;
	}
}