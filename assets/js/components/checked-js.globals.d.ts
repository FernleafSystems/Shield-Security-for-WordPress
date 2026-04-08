declare const ajaxurl: string;

declare const shieldServices: {
	notification() :{
		showMessage( message: string, success?: boolean ) :void;
	};
};

declare const shieldAppMain: {
	components?: Record<string, any>;
}|undefined;

declare const shieldEventsHandler_Main: {
	add_Click( selector: string, callback: ( targetEl: Element, evt: Event ) => void, suppress?: boolean ) :void;
};
