export class AjaxService {
	bg( data: Record<string, any> ) :Promise<any>;
	send( data: Record<string, any>, showOverlay?: boolean, quiet?: boolean ) :Promise<any>;
}
