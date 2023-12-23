<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\UserMfa\LoginIntent;

use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Traits\AuthNotRequired;
use FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Exceptions\ActionException;

class LoginIntentFormFieldBase extends \FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\BaseRender {

	use AuthNotRequired;

	/**
	 * @throws ActionException
	 */
	protected function checkAvailableData() {
		parent::checkAvailableData();

		$slug = $this->action_data[ 'vars' ][ 'provider_slug' ] ?? null;
		if ( !\preg_match( '#^[a-z0-9_]+$#', (string)$slug ) ) {
			throw new ActionException( 'Invalid Slug' );
		}
		if ( empty( $this->action_data[ 'vars' ][ 'field' ] ) ) {
			throw new ActionException( 'No field data provided' );
		}
	}

	protected function getRenderData() :array {
		return [
			'field' => $this->action_data[ 'vars' ][ 'field' ],
		];
	}

	protected function getRenderTemplate() :string {
		return sprintf( parent::getRenderTemplate(), $this->action_data[ 'vars' ][ 'provider_slug' ] );
	}

	protected function getRequiredDataKeys() :array {
		return [
			'vars',
		];
	}
}