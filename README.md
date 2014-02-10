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

Set up a custom handler:

	/**
	 * BB_WP_API_Handler_Mypages class.
	 *
	 * A custom handler for Wordpress pages
	 * 
	 * @extends BB_WP_API_Handler
	 */
	class BB_WP_API_Handler_Mypages extends BB_WP_API_Handler {
		
		/**
		 * register_data function.
		 * 
		 * register all data fields	
		 */
		public function register_data($register) {
		
			$register->modelclass('post');
			$register->data_package('postmeta');
					
			$register->field_id('id', 'ID', 'post');
			$register->field('title', 'post_title', 'post', array('validate' => 'esc_attr'));
			$register->field('content', 'post_content', 'post');
			$register->field('date', 'post_date', 'post', array('readonly' => true ));
			$register->field('myMetaValue', 'my_wp_meta_field', 'postmeta');
		}
		
		/**
		 * filter_pre_parse_model_request function.
		 * 
		 * default values
		 */
		protected function filter_pre_parse_model_request( $parsed, $modelmethod ) {
			
			switch( $modelmethod ) {		
				case('create'):
					$parsed['post']['post_status'] = 'publish'; // publish immediately
					$parsed['post']['post_type'] = 'page'; // saved as "page" post type	
				break;
				case('update'):
				case('delete'):				
				break;
			}		
			return $parsed;
		}
	}

Example usage via Backbone:
Save a model serverside:

	var model, savedata;
	model = new WordpressPage();
	savedata = {
		title: 'My Page Title',
		content: 'My page content',
		myMetaValue: 'My meta value'
	};
	model.save(savedata);

