export class ObjectOps {
	static ObjClone<T>( obj: T ) :T;
	static IsEmpty( obj: Record<string, any> ) :boolean;
	static Merge<T extends Record<string, any>, U extends Record<string, any>>( obj1: T, obj2?: U ) :T&U;
}
