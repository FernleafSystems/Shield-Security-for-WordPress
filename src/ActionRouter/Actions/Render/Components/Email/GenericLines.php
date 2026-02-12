<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\ActionRouter\Actions\Render\Components\Email;

class GenericLines extends EmailBase {

	public const SLUG = 'email_generic_lines';
	public const TEMPLATE = '/email/base_email.twig';

	protected function getBodyData() :array {
		return \array_map(
			static fn( $line ) => \is_string( $line ) ? $line : '',
			\is_array( $this->action_data[ 'lines' ] ) ? $this->action_data[ 'lines' ] : []
		);
	}

	protected function getRequiredDataKeys() :array {
		return [
			'lines',
		];
	}
}
