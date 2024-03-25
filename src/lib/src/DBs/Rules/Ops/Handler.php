<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\DBs\Rules\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\CustomBuilder\RuleFormBuilderVO;
use FernleafSystems\Wordpress\Services\Services;
use FernleafSystems\Wordpress\Services\Utilities\Uuid;

class Handler extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Handler {

	public const TYPE_SHIELD = 'S';
	public const TYPE_CUSTOM = 'C';

	/**
	 * @throws \Exception
	 */
	public function insertFromForm( RuleFormBuilderVO $form, bool $saveAsDraft = false ) :Record {
		$recordData = [
			'slug' => sanitize_key( \str_replace( ' ', '-', $form->name ) ),
			'type' => self::TYPE_CUSTOM,
		];

		if ( $saveAsDraft ) {
			$recordData[ 'form_draft' ] = \base64_encode( \wp_json_encode( $form->getRawData() ) );
		}
		else {
			$recordData[ 'form' ] = \base64_encode( \wp_json_encode( $form->getRawData() ) );
			$recordData[ 'form_draft' ] = '';

			$recordData[ 'name' ] = $form->name;
			$recordData[ 'description' ] = $form->description;
			$recordData[ 'builder_version' ] = $form->form_builder_version;
			$recordData[ 'user_id' ] = Services::WpUsers()->getCurrentWpUserId();
			$recordData[ 'is_apply_default' ] = $form->checks[ 'checkbox_auto_include_bypass' ][ 'value' ] === 'Y';
		}

		/** @var Record $record */
		if ( $form->edit_rule_id >= 0 ) {
			$record = $this->getQuerySelector()->byId( (int)$form->edit_rule_id );
			if ( empty( $record ) ) {
				throw new \Exception( "Failed to update rule as it doesn't exist." );
			}

			// when moving a rule from early draft to complete we activate it.
			if ( empty( $record->form ) && !$saveAsDraft ) {
				$recordData[ 'is_active' ] = 1;
			}

			$success = $this->getQueryUpdater()->updateRecord( $record, $recordData );

			$record = $this->getQuerySelector()->byId( $form->edit_rule_id );
		}
		else {
			$record = $this->getRecord()->applyFromArray( $recordData );
			if ( !isset( $record->uuid ) ) {
				$record->uuid = ( new Uuid() )->V4();
			}
			$success = $this->getQueryInserter()->insert( $record );

			$record = $this->getQuerySelector()
						   ->setOrderBy( 'id' )
						   ->setLimit( 1 )
						   ->first();
		}

		if ( !$success ) {
			throw new \Exception( "Failed to store the rule in the database." );
		}

		return $record;
	}
}