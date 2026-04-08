declare const ajaxurl: string;

declare const shieldServices: {
	notification() :{
		showMessage( message: string, success?: boolean ) :void;
	};
};

declare const shieldStrings: {
	string( key: string ) :string;
};

declare const shieldAppMain: {
	components?: Record<string, any>;
}|undefined;

declare const shieldEventsHandler_Main: {
	add_Click( selector: string, callback: ( targetEl: Element, evt: Event ) => void, suppress?: boolean ) :void;
	add_Mouseover( selector: string, callback: ( targetEl: Element, evt: Event ) => void, suppress?: boolean ) :void;
};

interface Window {
	shieldAppMain?: {
		components?: Record<string, any>;
	};
}

interface JQuery<TElement = HTMLElement> {
	select2( ...args: any[] ) :JQuery<TElement>;
}

interface JQueryStatic {
	fn: {
		select2?: ( ...args: any[] ) => any;
		[key: string]: any;
	};
}
