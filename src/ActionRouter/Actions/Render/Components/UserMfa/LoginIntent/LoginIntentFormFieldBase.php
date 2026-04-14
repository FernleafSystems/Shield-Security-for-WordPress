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
			throw new ActionException( __( 'Invalid Slug', 'wp-simple-firewall' ) );
		}
		if ( empty( $this->action_data[ 'vars' ][ 'field' ] ) ) {
			throw new ActionException( __( 'No field data provided', 'wp-simple-firewall' ) );
		}
	}

	protected function getRenderData() :array {
		return [
			'field' => $this->normalizeField( $this->action_data[ 'vars' ][ 'field' ] ),
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

	private function normalizeField( array $field ) :array {
		$field[ 'slug' ] = (string)( $field[ 'slug' ] ?? '' );
		$field[ 'name' ] = (string)( $field[ 'name' ] ?? '' );
		$field[ 'type' ] = (string)( $field[ 'type' ] ?? '' );
		$field[ 'text' ] = (string)( $field[ 'text' ] ?? '' );
		$field[ 'element' ] = $field[ 'element' ] ?? 'input';
		$field[ 'hidden_input_name' ] = (string)( $field[ 'hidden_input_name' ] ?? '' );
		$field[ 'id' ] = $field[ 'id' ] ?? (string)( $field[ 'name' ] ?? '' );
		$field[ 'value' ] = (string)( $field[ 'value' ] ?? '' );
		$field[ 'placeholder' ] = (string)( $field[ 'placeholder' ] ?? '' );
		$field[ 'description' ] = (string)( $field[ 'description' ] ?? '' );
		$field[ 'help_link' ] = (string)( $field[ 'help_link' ] ?? '' );
		$field[ 'classes' ] = \array_values( \array_filter( \array_map(
			'strval',
			\is_array( $field[ 'classes' ] ?? null ) ? $field[ 'classes' ] : []
		) ) );
		$field[ 'datas' ] = \array_map(
			'strval',
			\array_filter(
				\is_array( $field[ 'datas' ] ?? null ) ? $field[ 'datas' ] : [],
				static fn( $key ) :bool => \is_string( $key ) && $key !== '',
				\ARRAY_FILTER_USE_KEY
			)
		);
		$field[ 'supp' ] = \array_map(
			'strval',
			\is_array( $field[ 'supp' ] ?? null ) ? $field[ 'supp' ] : []
		);

		return $field;
	}
}
