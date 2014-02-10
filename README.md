Backbone Wordpress API
====================

Backbone Wordpress API, to be used as a wp plugin, provides an api to sychronice backbone models/collections with wordpress objects (posts, pages, WP_User, custom post types, attachments,...)
 
Minimal configuration
=======

Set up an instance of the API: 
			
	$api = new BB_WP_API;
	$api->set_name('my_api_identifier_string');
	$api->set_actionname('my_wordpress_ajax_action_string');
	$api->start();
			   
 
Set up a backbone model: 

    WordpressPage = belT.Backbone.Model.extend({
    		
    	// sent to the server to identify the api instance and api handler
    	api: {
    		name: 'my_api_identifier_string',
    		handler: 'review'
    	},		
    	
    	defaults: {
    		title:				'',
    		content:			'',
    		date:				'',
    		myMetaValue:		''
    	}
    });

