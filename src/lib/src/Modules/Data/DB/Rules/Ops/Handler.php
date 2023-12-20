<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Data\DB\Rules\Ops;

use FernleafSystems\Wordpress\Plugin\Shield\Rules\CustomBuilder\RuleFormBuilderVO;
use FernleafSystems\Wordpress\Services\Services;

class Handler extends \FernleafSystems\Wordpress\Plugin\Core\Databases\Base\Handler {

	public const TYPE_SHIELD = 'S';
	public const TYPE_CUSTOM = 'C';

	/**
	 * @throws \Exception
	 */
	public function insertFromForm( RuleFormBuilderVO $form ) :bool {
		$recordData = [
			'slug'             => sanitize_key( \str_replace( ' ', '-', $form->name ) ),
			'name'             => $form->name,
			'description'      => $form->description,
			'type'             => self::TYPE_CUSTOM,
			'user_id'          => Services::WpUsers()->getCurrentWpUserId(),
			'builder_version'  => $form->form_builder_version,
			'is_apply_default' => $form->checks[ 'checkbox_auto_include_bypass' ][ 'value' ] === 'Y',
			'form'             => \base64_encode( \wp_json_encode( $form->getRawData() ) ),
		];

		if ( $form->edit_rule_id >= 0 ) {
			/** @var Record $record */
			$record = $this->getQuerySelector()->byId( (int)$form->edit_rule_id );
			if ( empty( $record ) ) {
				throw new \Exception( "Failed to update rule as it doesn't exist." );
			}
			$success = $this->getQueryUpdater()->updateRecord( $record, $recordData );
		}
		else {
			/** @var Record $record */
			$record = $this->getRecord()->applyFromArray( $recordData );
			$record->is_active = 1;
			$success = $this->getQueryInserter()->insert( $record );
		}

		if ( !$success ) {
			throw new \Exception( "Failed to store the rule in the database." );
		}

		return true;
	}
}