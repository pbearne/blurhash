<?php
/**
 * Plugin Name:     WP Dominant Color
 * Plugin URI:      XWP.io
 * Description:     Adds Dominant Color preload support to WordPress.
 * Author:          pbearne, XWP
 * Author URI:      XWP.io
 * Text Domain:     wp-dominant-color
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         wp-dominan-color
 */


/**
 * Add Dominant Color preload support to WordPress.
 *
 * TODO: Add settings to turn on and select method used client-side bg image or canvas.
 * TODO: remove direct calls to GD li / support imagick.
 *       Look at the load function in these files src/wp-includes/class-wp-image-editor.php and src/wp-includes/class-wp-image-editor-imagick.php
 * TODO: Add tests
 */
class wp_Dominant_Color {


	public function __construct() {

		add_filter( 'wp_generate_attachment_metadata', [ $this, 'dominant_color_metadata' ], 10, 2 );
//		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_plugin_scripts' ] );
//		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

		// do we have the new filter or are duplicating core the functions?
		if ( has_filter( 'wp_img_tag_add_adjust' ) ) {
			add_filter( 'wp_img_tag_add_adjust', [ $this, 'tag_add_adjust' ], 10, 3 );
		} else {
			add_filter( 'the_content', [ $this, 'filter_content_tags' ] );
			add_filter( 'wp_dominant_color_img_tag_add_adjust', [ $this, 'tag_add_adjust' ], 10, 3 );
		}

		add_filter( 'attachment_fields_to_edit', [ $this, 'add_dominant_color_media_setting' ], 10, 2 );
		add_filter( 'attachment_fields_to_save', [ $this, 'save_dominant_color_media_setting' ], 10, 2);
	}

	/**
	 * TODO: this needs to wired up to so that attachment_image have the data hash added
	 *
	 * @param $html
	 * @param $attachment_id
	 *
	 * @return string
	 */

//	public function wp_blurhash_wp_get_attachment_image( $html, $attachment_id ) {
//		$image_meta = wp_get_attachment_metadata( $attachment_id );
//		if ( ! empty( $image_meta['blurhash'] ) ) {
//			$html = str_replace( '<img', '<img data-blurhash="' . $image_meta['blurhash'] . '"', $html );
//		}
//
//		return $html;
//	}


	/**
	 * @return void
	 */
//	public function register_rest_routes() {
//		$d = register_rest_route( 'blurhash/v1', '/get/(?P<id>\d+)', array(
//			'methods' => WP_REST_Server::READABLE,
//			'callback' => [ $this, 'handle_rest_call' ],
//			'permission_callback' => [ $this, 'rest_permission_callback' ],
//		) );
//
//	}

	/**
	 * @return bool
	 */
	// TDOD: fix permission check
//	public function rest_permission_callback() {
//
//		return true;
//		return current_user_can( 'upload_files' );
//	}

//	public function handle_rest_call( $request ) {
//		$id = $request->get_param( 'id' );
//
//		return $this->get_blurhash_by_id( $id );
//	}

//	public function get_blurhash_by_id( $id ) {
//		$file = get_attached_file( $id );
//		wp_raise_memory_limit( 'image' );
//
//		$image_editor = wp_get_image_editor( $file );
//
//		if ( is_wp_error( $image_editor ) ) {
//			// This image cannot be edited.
//			return;
//		}
//
//		$size = $image_editor->get_size();
//		$width = $size['width'];
//		$height = $size['height'];
//		$skip_factor = 1;//(int) ( $width + $height ) / 10;
//
//		$pixels = [];
//		if ( 'WP_Image_Editor_Imagick' === get_class( $image_editor ) ) {
//			//TODO: create imagick version of blurhash
//			$imagick = new Imagick($file);
//			for ( $y = 0; $y < $height; $y=$y+$skip_factor ) {
//				$row = [];
//				for ( $x = 0; $x < $width; $x=$x+$skip_factor ) {
//					$pixel  = $imagick->getImagePixelColor( $x, $y );
//					$colors = $pixel->getColor();
//					$row[] = [ $colors['r'], $colors['g'], $colors['b'] ];
//				}
//				$pixels[] = $row;
//			}
//		} else {
//			if ( function_exists( 'imagecreatefromwebp' ) && 'image/webp' === wp_get_image_mime( $file ) ) {
//				$image = imagecreatefromwebp( $file );
//			} else {
//				$image = imagecreatefromstring( file_get_contents( $file ) );
//			}
//
//			for ( $y = 0; $y < $height; $y=$y+$skip_factor ) {
//				$row = [];
//				for ( $x = 0; $x < $width; $x=$x+$skip_factor ) {
//					$index  = imagecolorat( $image, $x, $y );
//					$colors = imagecolorsforindex( $image, $index );
//
//					$row[] = [ $colors['red'], $colors['green'], $colors['blue'] ];
//				}
//				$pixels[] = $row;
//			}
//		}
//
//
//		$components_x         = 7;
//		$components_y         = 7;
//
//		return Blurhash::encode( $pixels, $components_x, $components_y );
//	}

	// TODO: off-load this to api call
	/**
	 * @param $metadata
	 * @param $attachment_id
	 *
	 * @return array $metadata
	 */
	public function dominant_color_metadata( $metadata, $attachment_id ) {
		// this is failing when calling local host but works in browser
		// $burhash = wp_remote_get( get_rest_url() . 'blurhash/v1/get' . $attachment_id );
		// so calling directly for now
		$dominant_color = $this->get_dominant_color( $attachment_id );

		if( ! empty( $dominant_color ) ) {
			$metadata['dominant_color'] = $dominant_color;
		}

		return $metadata;
	}

	/**
	 * @param $filtered_image
	 * @param $context
	 * @param $attachment_id
	 *
	 * @return string image tag
	 */
	public function tag_add_adjust( $filtered_image, $context, $attachment_id ) {

		$image_meta = wp_get_attachment_metadata( $attachment_id );

		if ( isset( $image_meta['dominant_color'] ) ) {
			$data = sprintf( 'data-dominantColor="%s"', $image_meta['dominant_color'] );
			$style =  ' style="background-color: #' . $image_meta['dominant_color'] . '; " ';
			$js = ' onload="this.style.backgroundColor = ``" ';
			$filtered_image = str_replace( '<img ', '<img ' . $data . $style . $js, $filtered_image );

			$extra_class = ( $this->colorislight( $image_meta['dominant_color'] ) ) ? 'dominant-color-light' : 'dominant-color-dark';
			$filtered_image = str_replace( 'class="', 'class="' . $extra_class . ' ', $filtered_image );
		}

		return $filtered_image;
	}


	/**
	 * @return void
	 */
//	public function enqueue_plugin_scripts() {
//		wp_enqueue_script( 'blurhash', plugin_dir_url( __FILE__ ) . 'dist/blurhash.js' );
//	}

	/**
	 * @param $content
	 * @param $context
	 *
	 * @return string content
	 */
	public function filter_content_tags( $content, $context = null ) {
		if ( null === $context ) {
			$context = current_filter();
		}

		if ( ! preg_match_all( '/<(img|iframe)\s[^>]+>/', $content, $matches, PREG_SET_ORDER ) ) {
			return $content;
		}

		// List of the unique `img` tags found in $content.
		$images = array();

		// List of the unique `iframe` tags found in $content.
		$iframes = array();

		foreach ( $matches as $match ) {
			list( $tag, $tag_name ) = $match;

			switch ( $tag_name ) {
				case 'img':
					if ( preg_match( '/wp-image-([0-9]+)/i', $tag, $class_id ) ) {
						$attachment_id = absint( $class_id[1] );

						if ( $attachment_id ) {
							// If exactly the same image tag is used more than once, overwrite it.
							// All identical tags will be replaced later with 'str_replace()'.
							$images[ $tag ] = $attachment_id;
							break;
						}
					}
					$images[ $tag ] = 0;
					break;
			}
		}

		// Reduce the array to unique attachment IDs.
		$attachment_ids = array_unique( array_filter( array_values( $images ) ) );

		if ( count( $attachment_ids ) > 1 ) {
			/*
			 * Warm the object cache with post and meta information for all found
			 * images to avoid making individual database calls.
			 */
			_prime_post_caches( $attachment_ids, false, true );
		}

		// Iterate through the matches in order of occurrence as it is relevant for whether or not to lazy-load.
		foreach ( $matches as $match ) {
			// Filter an image match.
			if ( isset( $images[ $match[0] ] ) ) {
				$filtered_image = $match[0];
				$attachment_id  = $images[ $match[0] ];
				/**
				 * Filters img tag that will be injected into the content.
				 *
				 * @param string $filtered_image the img tag with attributes being created that will
				 *                                    replace the source img tag in the content.
				 * @param string $context Optional. Additional context to pass to the filters.
				 *                        Defaults to `current_filter()` when not set.
				 * @param int $attachment_id the ID of the image attachment.
				 *
				 * @since 1.0.0
				 *
				 */
				$filtered_image = apply_filters( 'wp_dominant_color_img_tag_add_adjust', $filtered_image, $context, $attachment_id );

				if ( $filtered_image !== $match[0] ) {
					$content = str_replace( $match[0], $filtered_image, $content );
				}
			}
		}

		return $content;
	}

	/**
	 * Add checkbox setting to enable/disable blurhash for a given media.
	 *
	 * @param array $form_fields
	 * @param WP_Post $post
	 *
	 * @return array
	 */
	public function add_dominant_color_media_setting( array $form_fields, WP_Post $post ) {
		$image_meta   = wp_get_attachment_metadata( $post->ID );
		$checked_text = isset( $image_meta['dominant_color'] ) ? 'checked' : '';
		$html_input   = "<input type='checkbox' $checked_text value='1'
			name='attachments[{$post->ID}][dominant_color]' id='attachments[{$post->ID}][dominant_color]' />";

		$form_fields['dominant_color'] = array(
			'label' => __( 'Dominant Color',  'wp-dominant-color' ),
			'input' => 'html',
			'html'  => $html_input,
		);

		return $form_fields;
	}

	/**
	 * Save blurhash setting value for a media post.
	 *
	 * @param array $post
	 * @param array $attachment
	 *
	 * @return array
	 */
	public function save_dominant_color_media_setting( array $post, array $attachment ) {
		$attachment_id = $post['ID'];
		$image_meta    = wp_get_attachment_metadata( $attachment_id );

		if ( isset( $attachment['dominant_color'] ) ) {
			if ( ! isset( $image_meta['dominant_color'] ) ) {
				// If enabling blurhash from media setting and image meta doesn't have any, generate a new one.
				$image_meta = $this->dominant_color_metadata( $image_meta, $attachment_id );
				wp_update_attachment_metadata( $attachment_id, $image_meta );
			}
		} elseif ( isset( $image_meta['dominant_color'] ) ) {
			// If disabling blurhash from media setting and blurhash set in image meta, unset it.
			unset( $image_meta['dominant_color'] );
			wp_update_attachment_metadata( $attachment_id, $image_meta );
		}

		return $post;
	}

	/*
     *
	 * Detect the Dominant Color used in an Image
	 */

	function get_dominant_color($id, $default_color='eee') {
		$file = get_attached_file( $id );

		wp_raise_memory_limit( 'image' );
		if ( $original_image = @imagecreatefromstring( @file_get_contents( $file ) ) ) {
			$shortend_image = imagecreatetruecolor( 1, 1 );
			imagecopyresampled( $shortend_image, $original_image, 0, 0, 0, 0, 1, 1, imagesx( $original_image ), imagesy( $original_image ) );
			return dechex( imagecolorat( $shortend_image, 0, 0 ));
		} else {
			return $default_color;
		}
	}

	function colorislight($hex) {
		$hex       = str_replace('#', '', $hex);
		$r         = (hexdec(substr($hex, 0, 2)) / 255);
		$g         = (hexdec(substr($hex, 2, 2)) / 255);
		$b         = (hexdec(substr($hex, 4, 2)) / 255);
		$lightness = round((((max($r, $g, $b) + min($r, $g, $b)) / 2) * 100));
		return ($lightness >= 50 ? true : false);
	}

}

new wp_Dominant_Color();

