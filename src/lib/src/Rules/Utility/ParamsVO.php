<?php declare( strict_types=1 );

namespace FernleafSystems\Wordpress\Plugin\Shield\Rules\Utility;

use FernleafSystems\Utilities\Data\Adapter\DynPropertiesClass;
use FernleafSystems\Wordpress\Plugin\Shield\Request\ThisRequestConsumer;

/**
 * -- Conditions --
 * @property array[]  $excluded_params
 * @property string   $is_fuzzy_search
 * @property string   $key
 * @property int      $limit_count
 * @property int      $limit_time_span
 * @property string   $path_basename
 * @property string   $path_dir
 * @property string   $name
 * @property int      $sys_load_range
 * @property int      $match_sys_load
 * @property int      $match_visitor_ade_score
 * @property string   $match_category
 * @property string   $match_code
 * @property string   $match_hostname
 * @property string   $match_ip
 * @property string   $match_ip_id
 * @property string   $match_method
 * @property string   $match_path
 * @property string   $match_pattern
 * @property string[] $match_patterns
 * @property string   $match_script_name
 * @property string   $match_type
 * @property string   $match_value
 * @property string   $match_useragent
 * @property string   $match_userid
 * @property string   $match_username
 * @property string   $match_email
 * @property string   $param_name
 * @property string   $pattern
 * @property string   $provider
 * @property string   $plugin_file
 * @property string   $theme_dir
 * @property string   $req_param_source
 * @property string   $user_cap
 * @property string   $user_role
 * -- Responses --
 * @property array    $args
 * @property string   $block_page_slug
 * @property string   $callback
 * @property int      $count
 * @property string   $duration
 * @property string   $event
 * @property string   $header
 * @property string   $hook
 * @property string   $message
 * @property string   $priority
 * @property string   $redirect_url
 * @property string   $rule_slug
 * @property int      $status_code
 * @property string   $value
 * @property int      $offense_count
 * @property bool     $use_cloudflare
 * @property bool     $block
 * @property bool     $do_log
 * @property bool     $is_trusted
 */
class ParamsVO extends DynPropertiesClass {

	use ThisRequestConsumer;

	private array $def;

	public function __construct( array $def ) {
		$this->def = $def;
	}

	public function applyFromArray( array $data, array $restrictedKeys = [] ) :ParamsVO {
		$unrecongizedKeys = \array_keys( \array_diff_key( $data, $this->def ) );
		if ( !empty( $unrecongizedKeys ) ) {
//			error_log( 'ParamsVO: Unrecognised keys: '.var_export( $unrecongizedKeys, true ) );
		}
		else {
			$missing = [];
			foreach ( \array_intersect_key( $this->def, $data ) as $paramKey => $paramDef ) {
				if ( !isset( $data[ $paramKey ] ) && !isset( $paramDef[ 'default' ] ) ) {
					$missing[] = $paramKey;
				}
			}
			if ( !empty( $missing ) ) {
				error_log( 'ParamsVO: Missing Param keys: '.var_export( $missing, true ) );
			}
		}
		return parent::applyFromArray( $data, \array_keys( $this->def ) );
	}

	public function __get( string $key ) {
		$value = parent::__get( $key );

		if ( \is_string( $value ) && \preg_match( '#\{\{request\.([a-z0-9]+)}}#', $value, $matches ) ) {
			$value = $this->req->{$matches[ 1 ]} ?? $value;
		}

		return $value;
	}
}