<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\Rules\Ops;

/**
 * @property string $uuid
 * @property string $slug
 * @property string $name
 * @property string $description
 * @property string $type
 * @property bool   $is_active
 * @property bool   $is_apply_default
 * @property int    $user_id
 * @property bool   $can_export
 * @property string $builder_version
 * @property array  $rules_as_json
 * @property array  $form
 * @property array  $form_draft
 * @property int    $updated_at
 */
class Record extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Record {

	public function __get( string $key ) {
		$value = parent::__get( $key );

		switch ( $key ) {
			case 'form':
			case 'form_draft':
			case 'rules_as_json':
				$value = @\json_decode( @\base64_decode( (string)$value ), true );
				if ( !\is_array( $value ) ) {
					$value = [];
				}
				break;
			default:
				break;
		}

		return $value;
	}

	public function __set( string $key, $value ) {
		switch ( $key ) {
			case 'form':
			case 'form_draft':
			case 'rules_as_json':
				if ( !\is_array( $value ) ) {
					$value = [];
				}
				$value = \base64_encode( \wp_json_encode( $value ) );
				break;
			default:
				break;
		}
		parent::__set( $key, $value );
	}
}