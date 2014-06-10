<?php

/**
 * Abstract BB_WP_API_Handler_Data_Package class.
 *
 * creates a custom data_package
 * 
 * @abstract
 */
abstract class BB_WP_API_Handler_Data_Package {
	
	/* set in which modelclasses this datapackage is allowed */
	protected $modelclasses = array();


	public function __construct() {
		
	}
			
	/**
	 * is_modellclass_allowed function.
	 * 
	 * test this before you apply any data package to an api handler
	 *
	 * @access public
	 * @param mixed $modelclass
	 * @return bool
	 */
	public function is_modellclass_allowed( $modelclass ) {
		if( in_array($modelclass, $this->modelclasses))
			return true;
		return false;
	}
	
	/**
	 * read function.
	 * 
	 * must be supplied by the child class
	 *
	 * @access public
	 * @abstract
	 * @param mixed $object
	 * @return void
	 */
	abstract function read($object);
	
	/**
	 * create function.
	 * 
	 * can be supplied by the child class
	 *	  
	 * @access public
	 * @param mixed $object_id
	 * @param array $data (default: array())
	 * @return void
	 */
	public function create($object_id, $data = array()) {}

	/**
	 * update function.
	 * 
	 * can be supplied by the child class
	 *	  
	 * @access public
	 * @param mixed $object_id
	 * @param array $data (default: array())
	 * @return void
	 */
	public function update($object_id, $data = array()) {}

	/**
	 * delete function.
	 * 
	 * can be supplied by the child class
	 *	  
	 * @access public
	 * @param mixed $object_id
	 * @param array $data (default: array())
	 * @return void
	 */
	public function delete($object_id, $data = array()) {}

}



/* ADDITIONAL DATAPACKAGES */



/**
 * BB_WP_API_Handler_Data_Package_Postmeta class.
 * 
 * @extends BB_WP_API_Handler_Data_Package
 */
class BB_WP_API_Handler_Data_Package_Postmeta extends BB_WP_API_Handler_Data_Package {
	
	protected $modelclasses = array('post', 'attachment');
	
	/**
	 * read function.
	 * 
	 * reads the postmeta
	 *
	 * @access public
	 * @param mixed $object
	 * @return array
	 */
	public function read($object) {
		$meta = get_post_custom( $object->ID );
		if( ! empty($meta)) {
			foreach ($meta as $k => $v) {
				$meta[$k] = maybe_unserialize($v[0]) ;
			}
		}
		return $meta;
	}
	
	/**
	 * create function.
	 * 
	 * adds postmeta to the database
	 *
	 * @access public
	 * @param mixed $object_id
	 * @param array $data (default: array())
	 * @return void
	 */
	public function create($object_id, $data = array()) {
		foreach($data as $key => $value) {
			add_post_meta($object_id, $key, $value );
		}		
	}
	
	/**
	 * update function.
	 * 
	 * updates postmeta in the database
	 *
	 * @access public
	 * @param mixed $object_id
	 * @param array $data (default: array())
	 * @return void
	 */
	public function update($object_id, $data = array()) {
		foreach($data as $key => $value) {
			update_post_meta($object_id, $key, $value );
		}		
	}
}

/**
 * BB_WP_API_Handler_Data_Package_Author class.
 * 
 * @extends BB_WP_API_Handler_Data_Package
 */
class BB_WP_API_Handler_Data_Package_Author extends BB_WP_API_Handler_Data_Package {
	
	protected $modelclasses = array('post', 'comment', 'user', 'attachment');
	
	/**
	 * read function.
	 * 
	 * retrieves the user of a post, comment, WP_User
	 *
	 * @access public
	 * @param mixed $object
	 * @return array
	 */
	public function read($object) {
		
		$author = array();	
		if ( $object instanceof WP_Post ) 
			$id = $object->post_author;			
		elseif ( isset($object->comment_ID )) // a WP Comment 
			$id = $object->user_id;
		elseif ( $object instanceof WP_User ) 
			$id = $object->ID;			
		elseif ( is_numeric($object)  ) 
			$id = absint($object);
		else
			return array(); // exit
		
		
		//@TODO doesnt go through by comments
		
		
    	$avatar =  str_replace("avatar ", "avatar media-object pull-left ", get_avatar( $id, 32 ));	
	    $author['ID'] = $id;
	    $author['display_name'] = get_the_author_meta( 'display_name', $id );
	    $author['avatar'] = $avatar;
	    $author['email'] =  get_the_author_meta( 'user_email', $id );		   
	    
	    return $author;    			
	}
}

/**
 * BB_WP_API_Handler_Data_Package_Cap class.
 * 
 * @extends BB_WP_API_Handler_Data_Package
 */
class BB_WP_API_Handler_Data_Package_Cap extends BB_WP_API_Handler_Data_Package {
	
	protected $modelclasses = array('post', 'comment', 'attachment');
	
	/**
	 * read function.
	 * 
	 * retrieves the permission to update, delete a model
	 *
	 * @access public
	 * @param mixed $object
	 * @return array
	 */
	public function read($object) {
		
		$permissions = array();	
	    if ( $object instanceof WP_Post ) {
		        $permissions['edit'] = ( current_user_can( 'edit_post', $object->ID ) ) ? 1 : 0 ;
		        $permissions['delete'] = ( current_user_can( 'delete_post', $object->ID ) ) ? 1 : 0 ;
	    }	    
	    if ( isset($object->comment_ID ) ) { // a comment
		    //@TODO better
			$comment_author = $object->user_id;
				$permissions['edit'] = ( $comment_author == wp_get_current_user()->ID ) ? 1 : 0;
				$permissions['delete'] = ( $comment_author == wp_get_current_user()->ID ) ? 1 : 0;
	    }
	   return $permissions;	
	}
}


class BB_WP_API_Handler_Data_Package_Nonces extends BB_WP_API_Handler_Data_Package {
	
	protected $modelclasses = array('post', 'attachment', 'comment');
	
	/**
	 * read function.
	 * 
	 * retrieves different imagessizes for the attachment
	 *
	 * @access public
	 * @param mixed $object
	 * @return array
	 */
	public function read($object) {
		
    	$nonces = array();
		if ( $object instanceof WP_Post ) {
		    $nonces['nonce_delete'] = wp_create_nonce( 'delete' . $object->ID );
		    $nonces['nonce_create'] = wp_create_nonce( 'create' . $object->ID );
		    $nonces['nonce_update'] = wp_create_nonce( 'update' . $object->ID );		    
		}
	    return $nonces;
	}
}


class BB_WP_API_Handler_Data_Package_Embedder extends BB_WP_API_Handler_Data_Package {
	
	protected $modelclasses = array('attachment');

	public function read($object) {
		$array = array();
		
		$mime = $object->post_mime_type;
		$icon = wp_mime_type_icon($mime);
		$metadata = wp_get_attachment_metadata( $object->ID );
		$embed = '';
		$html = '';
		
		$type = (wp_attachment_is_image($object->ID )) ? 'image' : 'misc';
		$frame = ($type =='image') ? "image" : "inline";
		
		switch($type) {
			case('misc'):
				$embed = '<embed class="media" src="' . $object->guid . '">';
				$html = '<a href="#" class="magnific"><img src="' . $icon .'"></a>';						

			break;
			case('image'):
				$embed = '';
				$html = '<a href="#" class="magnific"><img src="' . wp_get_attachment_thumb_url($object->ID) .'"></a>';						
			break;
			
		}
		$array['type'] = $type;
		$array['html'] = $html;		
		$array['embed'] = $embed;
		$array['frame'] = $frame;

		return $array;
	}
	
}

class BB_WP_API_Handler_Data_Package_Loginuser_Meta extends BB_WP_API_Handler_Data_Package {
	
	protected $modelclasses = array('idone');

	public function read($id, $data = array() ) {

	/* try to login */

	    $user_signon = wp_signon( $data, false );
	    
	    if ( is_wp_error($user_signon) ) {
	    	return $user_signon;	    
	    } else {
		    return array('user_login' => 'ok');
	    }
	    
	    // no return on update, only errors
		
	}	
}

