<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Modules\Integrations\Lib\Bots\Spam\Handlers;

class WpForo extends Base {

	protected function run() {
		foreach ( $this->getFiltersToMonitor() as $filter ) {
			add_filter( $filter, function ( $args = [] ) {

				// It should be an array, but customer reported fatal error with a boolean passed
				if ( \is_array( $args ) ) {
					$status = $args[ 'status' ] ?? null;
					if ( $status !== 1 && $this->isBotBlockRequired() ) {
						if ( !empty( \WPF()->current_userid ) ) {
							\WPF()->moderation->ban_for_spam( \WPF()->current_userid );
						}
						$args[ 'status' ] = 1; // 1 signifies not approved
					}
				}

				return $args;
			}, 1000 );
		}
	}

	private function getFiltersToMonitor() :array {
		return [
			'wpforo_add_topic_data_filter',
			'wpforo_edit_topic_data_filter',
			'wpforo_add_post_data_filter',
			'wpforo_edit_post_data_filter',
		];
	}

	protected static function ProviderMeetsRequirements() :bool {
		return \function_exists( '\WPF' ) && !empty( \WPF()->tools_antispam[ 'spam_filter' ] );
	}
}