<?php
/*
Plugin Name: Processing for wordpress
Plugin URI: http://tutorpocessing.com
Description: Processing maanger allow you to simply add and integrate processing sketches to your website.
Version: 1.0
Author: Fabax
Author URI: http://tutoprocessing.com
License: A "Slug" license name e.g. GPL2
*/

class FB_Processing_Post_Type{
	public function __construct(){	
		$this->register_post_type();
		$this->metaboxes();

	}

	public function register_post_type(){
		$args = array(
			'labels' => array(
				'name' => 'Sketches',
				'singular_name' => 'Sketches',
				'add_new' => 'add a new sketch',
				'add_new_item' => 'add a new sketch',
				'edit_item' => 'edit a sketch',
				'new_item' => 'add new sketch',
				'view_item' => 'View sketch',
				'search_items' => 'Search sketch',
				'not_found' => 'No sketch found',
				'not_found_in_trash' => 'No sketch found in trash'
			),
			'query_var' => 'sketches',
			'rewrite' => array(
				'slug' => 'sketches/',
			),
			'public' => true,
			'menu_position' => 5,
			'menu_icon' => content_url(). '/plugins/processing4wp/img/icon-grey.png',
			'supports' => array(
				'title',
				'thumbnail',
				'editor'
			)
		);

		register_post_type('fb_sketch', $args);
	}

	// Appearance of the control panel for the sketches
	public function metaboxes(){
		//Metabox pour les infos du sketch ----------------------------
		function fb_add_sketch_options_metabox(){
			add_meta_box('fb_sketch_height','Sketch Options', 'fb_display_sketch_option_form', 'fb_sketch', 'side');
		}
		function fb_save_infos_sketch_options($id){
			if(isset($_POST['fb_sketch_height']) || isset($_POST['fb_sketch_width']) || isset($_POST['fb_sketch_author']) || isset($_POST['fb_sketch_author_website'])){
				update_post_meta($id,'fb_sketch_author',strip_tags($_POST['fb_sketch_author']));
				update_post_meta($id,'fb_sketch_author_website',strip_tags($_POST['fb_sketch_author_website']));
				update_post_meta($id,'fb_sketch_height',strip_tags($_POST['fb_sketch_height']));
				update_post_meta($id,'fb_sketch_width',strip_tags($_POST['fb_sketch_width']));
			}
		}
		function fb_display_sketch_option_form($post){
			$height = get_post_meta($post->ID, 'fb_sketch_height', true);
			$width = get_post_meta($post->ID, 'fb_sketch_width', true);
			$author = get_post_meta($post->ID, 'fb_sketch_author', true);
			$author_website = get_post_meta($post->ID, 'fb_sketch_author_website', true);
			$checkbox = get_post_meta($post->ID, 'fb_display_sketch_infos', true);
			?>
			<p>
				<label for="fb_sketch_author">author </label>
				<input type="text" class="widefat" name="fb_sketch_author" id="fb_sketch_author" value="<?php echo $author; ?>"/>
				<label for="fb_sketch_width">author website </label>
				<input type="text" class="widefat" name="fb_sketch_author_website" id="fb_sketch_author_website" value="<?php echo $author_website; ?>"/>
				<label for="fb_sketch_width">width </label>
				<input type="text" class="widefat" name="fb_sketch_width" id="fb_sketch_width" value="<?php echo $width; ?>"/>
				<label for="fb_sketch_height">Height </label>
				<input type="text" class="widefat" name="fb_sketch_height" id="fb_sketch_height" value="<?php echo $height; ?>"/>
			</p>
			<?php
		}
	
		//Code pour l'upload de fichiers ----------------------------
		function fb_add_upload_metabox(){
			add_meta_box('fb_sketch_uploads','Sketch Upload', 'fb_display_sketch_upload_form', 'fb_sketch', 'side');
		}
		function fb_display_sketch_upload_form($post){
			wp_nonce_field(plugin_basename(__FILE__), 'fb_upload_nonce_field');
			$html ="";

			//if the uploaded file is invalid, make a box apear
			if('invalid-file-name' == get_post_meta($post->ID,'zip',true)){
				$html .='<div id="invalid-file-name" class="error">';
					$html .='<p>You are trying to upload a file other than a zip file</p>';
				$html .='</div>';

			}
			//display the form
			$html .='<p>Make sure you upload a complete processing project as a zip file</p>';
			$html .='<input type="file" id="fb_zip_file" name="fb_zip_file" value="">';


			echo $html;
		}
		function fb_save_infos_sketch_uploads($id){

			if(fb_user_can_save($id, 'fb_upload_nonce_field')){

				if(isset($_POST['fb_sketch_title_good']) && 0 < count(strlen(trim($_POST['fb_sketch_title_good'])))){
					$fb_sketch_title_good = stripcslashes(strip_tags($_POST['fb_sketch_title_good']));
					update_post_meta($id,'fb_sketch_title_good',strip_tags($_POST['fb_sketch_title_good']));
				}
				//upload happens here
				$zipFile = $_FILES['fb_zip_file'];

				if(isset($zipFile) && ! empty($zipFile)){
					//check if file is a zip
					if(fb_is_valid_zip($zipFile['name'])){
						$response = wp_upload_bits($zipFile['name'],null,file_get_contents($zipFile['tmp_name'])); 
						if(0 == strlen(trim($response['error']))){
							update_post_meta($id,'zip',$response['url']);
							$url = get_home_path() . 'wp-content/uploads/sketches/';
							fb_unzip($zipFile, $url);
						}
					}else{
						update_post_meta($id,'zip','invalid-file-name');
					}
				}
			}
		}

		//helpers ----------------------------
		function fb_is_valid_zip($filename){
			$path_parts = pathinfo($filename);
			$response = false;

			if('zip' == strtolower($path_parts['extension'])){
				$response = true;
			}
			return $response;
		}
		//helper that check if the user is entitled to do stuff pretty much
		function fb_user_can_save($id, $nonce){
			$isAutoSave = wp_is_post_autosave( $id );
			$isRevision = wp_is_post_revision( $id );
			$isValidNonce = (isset($_POST[$nonce]) && wp_verify_nonce($_POST[$nonce],plugin_basename(__FILE__ )));
			return ! ($isAutoSave || $isRevision) && $isValidNonce;
		}

		function fb_unzip($zipFile,$newFolderLocation){
			$fileToUnzip = $zipFile['tmp_name'];

			$zip = new ZipArchive;
			$res = $zip->open($fileToUnzip);

			if(!is_int($res)){
    			$zip->extractTo($newFolderLocation);
		    	$zip->close();
		    	$path = wp_upload_dir();
		    	$path = $path['path'];
		    	$pathToZip = $path.'/'.$zipFile['name'];

		    	if(file_exists($pathToZip)){
				    unlink($pathToZip);
				}
			} 
		
		}

		// ADD NEW COLUMN
		function fb_columns_head($defaults) {
		    $defaults['shortcode'] = 'shortcode';
		    return $defaults;
		}
		 
		// SHOW THE FEATURED IMAGE
		function fb_columns_content($column_name, $post_ID) {
		    if ($column_name == 'shortcode') {
		    	$title = get_post_meta($post_ID, 'title', true);
		    	echo $shortcode = '[processing sketch="'.get_the_title($post_ID).'"]';
		    }
		}

		// Remove Featured Image Metabox from Custom Post Type Edit Screens
		function remove_image_box() {
		 if ($current_user->user_level < 10){
		   remove_meta_box('postimagediv','fb_sketch','side');
		 }
		}
		add_action('do_meta_boxes', 'remove_image_box');
		//----------------------
		add_filter('manage_posts_columns', 'fb_columns_head');
		add_action('manage_posts_custom_column', 'fb_columns_content', 10, 2);
		
		//All the action thing ----------------------------
		//link metaboxes to the wordpress admin 		
		add_action('add_meta_boxes', 'fb_add_sketch_options_metabox');
		add_action('save_post','fb_save_infos_sketch_options' );

		add_action('add_meta_boxes','fb_add_upload_metabox' );
		add_action('save_post','fb_save_infos_sketch_uploads' );
	}

}

function fb_add_admin_script(){
	wp_enqueue_script('fb_admin', plugins_url('processing4wp/js/admin.js'));
}

function fb_init(){
	new FB_Processing_Post_Type();
	include dirname(__FILE__) . '/processing4wp-shortcode.php';
	include dirname(__FILE__) . '/add-processing.php';

}

add_action('admin_enqueue_scripts','fb_add_admin_script');
add_action('init','fb_init');


?>