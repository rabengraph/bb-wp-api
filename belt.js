
/*   "use strict"; */



	/* ===============
	   INIT
	   =============== */

	// put all into an object
var belT = {};
(function(window){
	
	var r;
	window.belT = r = {};
	
	// get static vars passed from wordpress server for the specific api instance
	r.getExternalVars = function(apiName, property) {
		var vars =  window['belTExternalVars_' + apiName];
		
		//map the vars to be very explicit
		// var defaultvars = {
		//	'ajaxUrl'		: vars.ajaxUrl,
		//	'action'		: vars.action,
						
		//}; // just for info
		
		// return
		if(property)
			return vars[property];
		else 
			return vars;
	}; 
		
	/* ===============
	   BACKBONE  EXTENSION
	   =============== */

	// collect all in an obj
	r.Backbone = {};
	
	// overrides the ajax protocoll
	// 
	// all requests are sent via POST
	// we transmit the...
	//
	// wp-action port to funnel all requests serverside
	// method = (create, read, delete, update)
	// model (by default only the model would be transmitted)
	// model_Id = the aquivalent for the wp posttype or for the comment
	// requesturl: the current url to get the correct reviews for each site
	r.Backbone.sync =  function(method, model, options) {
				
		// good for WP
		options.emulateJSON = true;
		
		// allways use good old POST
		options.type = (method == 'read') ? "POST" : "POST";
		
		// get the passed data vars in the jQuery.ajax call
		options.data = options.data || {};
		
		// get ajax url
		options.url = r.getExternalVars(model.api.name, 'ajaxUrl');
	
		// override some options
		options.data = jQuery.extend({ 
		
			// wp action port
			action: r.getExternalVars(model.api.name, 'action'),

			// the original bb methods: read, create, update, delete
			method: method,
			
			// the original model
			model: model.toJSON(),
			
			// the registered model from wp
			handler: this.api.handler,
			
			// can be used by collections
			queryvars: this.api.queryVars,
			
			// send the location from where you sen tha xhr
			requesturl: function() {
				var url = window.location.href.match(/[^?]*?([^?]*)/)[0]; // delete all after the questionmark for debugging
					 return url;	
			}
		}, options.data);
		
		
    	if(options.bootstrap && method == 'read') {
 			
 			/* quite messy all   	 */
	    	var response =  r.getExternalVars(model.api.name, options.bootstrap);
	    	
	    	
	    	if(response) {
/* 	    		response = this.parse(response, options); */
		    	model.set(response);     	
				return; // no ajax		    	
	    	} // else fall back to ajax
    	}

	    return Backbone.sync.apply(this, [method, model, options]); 		
	},
	
	// r Model prototype
	//
   	r.Backbone.Model = Backbone.Model.extend({
		
		api: {
			name: 'default', // links to the equivalent instance of the api on the server
			handler: 'post',  //links to the handler on the server
			queryVars: {},	
		}, 

		// the response from wp puts the model in an own response object,
		// so we need to reparse backbone		
		parse: function(response, options) {
			

		    /* return response;		 */
		    
		    // old    
		    if ( _.isObject( response.data ) ) {	
			
			if(response.success == false) {
				this.trigger('notification', this, response.data , options);
				
			}
   
		    	
			console.log('parse model');
			console.log(response.success);
		    	
		    	// it was single model request
	            return response.data; 
	        } else {
		        // it was a collection request: the collection parsed the response already, so we dont do it again
	            return response; 
	        }   
	    },
		sync: function(method, model, options){	    	
		    return r.Backbone.sync.apply(this, [method, model, options]);
	    }   
	});
	
	// r Collection prototype
	//    
	r.Backbone.Collection = Backbone.Collection.extend({

		api: {
			name: 'default', // links to the equivalent instance of the api on the server
			handler: 'post',  //links to the handler on the server
			queryVars: {},	
		}, 
				
		// see description at model	    
	    parse: function(response, options) {
			console.log('parse collection');
			console.log(response.success);		    	
		    return response.data;
/* 			return response; */
	    },
	    // pass some vars to the request		    	    
		sync: function(method, model, options){			
		    return r.Backbone.sync.apply(this, [method, model, options]);
	    }
	});
})(window);