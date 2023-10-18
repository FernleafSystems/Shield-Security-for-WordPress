import $ from 'jquery';
import { BaseService } from "../BaseService";
import { AjaxService } from "../AjaxService";
import { ObjectOps } from "../ObjectOps";

export class MainwpServer extends BaseService {

	siteFrame;

	init() {
		this.bindEvents();
	}

	Ob_TableActions( element, options ) {
		this.element = element;
		this._name = pluginName;
		this._defaults = $.fn.icwpWpsfMainwpExt.defaults;
		this.options = $.extend(
			{
				'forms': {
					'insert': ''
				}
			},
			this._defaults,
			options
		);
		this.init();
	}

	destroy() {
		this.unbindEvents();
		this.$element.removeData();
	}

	bindEvents() {
		var plugin = this;

		plugin.$element.on(
			'click' + '.' + plugin._name,
			'.site-dropdown a.site_action',
			function ( evt ) {
				evt.preventDefault();

				plugin.options[ 'req_params' ] = $.extend(
					plugin.options.ajax_actions[ 'site_action' ],
					{
						client_site_id: $( this ).parent().data( 'sid' ),
						client_site_action_data: $( this ).data( 'site_action' )
					}
				);
				plugin.site_action.call( plugin );
			}
		);

		plugin.$element.on(
			'click' + '.' + plugin._name,
			'button.action.item_action',
			function ( evt ) {
				evt.preventDefault();
				plugin.options[ 'working_rid' ] = $( this ).data( 'rid' );
				plugin.options[ 'working_item_action' ] = $( this ).data( 'item_action' );
				plugin.itemAction.call( plugin );
			}
		);

		plugin.$element.on(
			'click' + '.' + plugin._name,
			'.tablenav.top input[type=submit].button.action',
			function ( evt ) {
				evt.preventDefault();
				let action = $( '#bulk-action-selector-top', plugin.$element ).find( ":selected" ).val();

				if ( action === "-1" ) {
					alert( icwp_wpsf_vars_plugin.strings.select_action );
				}
				else {
					let checkedIds = $( "input:checkbox[name=ids]:checked", plugin.$element ).map(
						function () {
							return $( this ).val()
						} ).get();

					if ( checkedIds.length < 1 ) {
						alert( 'No rows currently selected' );
					}
					else {
						plugin.options[ 'req_params' ][ 'bulk_action' ] = action;
						plugin.options[ 'req_params' ][ 'ids' ] = checkedIds;
						plugin.bulkAction.call( plugin );
					}
				}
				return false;
			}
		);

		plugin.$element.on(
			'click' + '.' + plugin._name,
			'button.action.custom-action',
			function ( evt ) {
				evt.preventDefault();
				let $button = $( this );
				let customAction = $button.data( 'custom-action' );
				if ( customAction in plugin.options[ 'custom_actions_ajax' ] ) {
					plugin.options[ 'working_custom_action' ] = plugin.options[ 'custom_actions_ajax' ][ customAction ];
					plugin.options[ 'working_custom_action' ][ 'rid' ] = $button.data( 'rid' );
					plugin.customAction.call( plugin );
				}
				else {
					/** This should never be reached live: **/
					alert( 'custom action not supported: ' + customAction );
				}
			}
		);

	}

	unbindEvents() {
		/*
			Unbind all events in our plugin's namespace that are attached
			to "this.$element".
		*/
		this.$element.off( '.' + this._name );
	}

	bulkAction() {
		let reqData = this.options[ 'ajax_bulk_action' ];
		this.sendReq( reqData );
	}

	site_action() {
		this.sendReq();
	}

	customAction() {
		this.sendReq( this.options[ 'working_custom_action' ] );
	}

	sendReq( reqData = {} ) {
		( new AjaxService() )
		.send( ObjectOps.Merge( reqData, this.options[ 'req_params' ] ) )
		.finally();
	}

	callback() {
	}
}