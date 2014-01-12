	/* ===============
	   INIT
	   =============== */

	// put all into an object
	var bbWpApi = {};
	
	// get static vars passed from wordpress server for the specific api instance
	bbWpApi.getExternalVars = function(api_Id, property) {
		var objectName =  window['bbWpApiExternalVars_' + api_Id];
		
		//map the vars to be very explicit
		var vars = {
			'ajaxUrl'	: objectName.ajaxUrl,
			'action'	: objectName.action,		
		};
		// return
		if(property)
			return vars[property];
		else 
			return vars;
	}; 
		
		
	/* ===============
	   BACKBONE PROTOTYPE EXTENSION
	   =============== */

	// collect all prototypes in an obj
	bbWpApi.Backbone = {};
	
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
	bbWpApi.Backbone.sync =  function(method, model, options) {
				
		// good for WP
		options.emulateJSON = true;
		
		// allways use good old POST
		options.type = (method == 'read') ? "POST" : "POST";
		
		// get the passed data vars in the jQuery.ajax call
		options.data = options.data || {};
		
		// get ajax url
		options.url = bbWpApi.getExternalVars(model.api_Id, 'ajaxUrl');
	
		// override some options
		options.data = jQuery.extend({ 
		
			// wp action port
			action: bbWpApi.getExternalVars(model.api_Id, 'action'),

			// the original bb methods: read, create, update, delete
			method: method,
			
			// the original model
			model: model.toJSON(),
			
			// the registered model from wp
			model_Id: null,
			
			// can be used by collections
			queryvars: null,
			
			// send the location from where you sen tha xhr
			requesturl: function() {
				var url = window.location.href.match(/[^?]*?([^?]*)/)[0]; // delete all after the questionmark for debugging
					 return url;	
			}
		}, options.data);

	    return Backbone.sync.apply(this, [method, model, options]); 		
	},
	
	// bbWpApi Model prototype
	//
   	bbWpApi.Backbone.Model = Backbone.Model.extend({

		// must be overwritten in the extension	
		// links to the equivalent instance of the api on the server
		api_Id: 'default',
	   	
		// must be overwritten in the extension
		// links to the modelclass on the server
		model_Id: 'post', // this is an identifier for the server
			
		// the response from wp puts the model in an own response object,
		// so we need to reparse backbone		
		parse: function(response) {

		    //return response;		
		        
		    if ( _.isObject(response.model) ) {
		    
		    	// it was single model request
	            return response.model; 
	        } else {
		        // it was a collection request: the collection parsed the response already, so we dont do it again
	            return response; 
	        }   
	    },
		sync: function(method, model, options){
			// pass the model_Id
			options.data = { 'model_Id' : this.model_Id };
		    return bbWpApi.Backbone.sync.apply(this, [method, model, options]);
	    }   
	});
	
	// bbWpApi Collection prototype
	//    
	bbWpApi.Backbone.Collection = Backbone.Collection.extend({

		// must be overwritten in the extension	
		// links to the equivalent instance of the api on the server
		api_Id: 'default',
		
		// must be overwritten in the extension
		// links to the modelclass on the server
		model_Id: 'post',
		
		// can be used to pass a query string to get
		queryVars: {},
			
		// see description at model	    
	    parse: function(response) {
		    return response.model;
	    },
	    // pass some vars to the request		    	    
		sync: function(method, model, options){
			options.data = {
				'model_Id' : this.model_Id,
				'queryvars' : this.queryVars				
			};
		    return bbWpApi.Backbone.sync.apply(this, [method, model, options]);
	    },   
	});