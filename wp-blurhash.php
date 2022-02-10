<?php
/**
 * Plugin Name:     WP Blurhash
 * Plugin URI:      XWP.io
 * Description:     Adds Blurhash preload support to WordPress.
 * Author:          pbearne, XWP
 * Author URI:      XWP.io
 * Text Domain:     wp-blurhash
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         wp-Blurhash
 */

// Your code starts here.
require_once 'vendor/autoload.php';

use kornrunner\Blurhash\Blurhash;

add_filter( 'wp_generate_attachment_metadata', 'wp_generate_blurhash_metadata', 10, 2 );
add_action(  'wp_print_scripts', 'wp_blurhash_print_scripts' );

// do we have new filter or are duplicating core the functions?
if( has_filter( 'wp_img_tag_add_adjust' ) ) {
	add_filter( 'wp_img_tag_add_adjust', 'wp_blurhash_tag_add_adjust', 10, 3 );
} else {
	add_filter( 'the_content', 'wp_blurhash_filter_content_tags' );
	add_filter( 'wp_blurhash_img_tag_add_adjust', 'wp_blurhash_tag_add_adjust', 10, 3 );
}




function wp_blurhash_wp_get_attachment_image( $html, $attachment_id ) {
	$image_meta = wp_get_attachment_metadata( $attachment_id );
	if ( ! empty( $image_meta['blurhash'] ) ) {
		$html = str_replace( '<img', '<img data-blurhash="' . $image_meta['blurhash'] . '"', $html );
	}

	return $html;
}

// TODO: off-load this to api call
function wp_generate_blurhash_metadata( $metadata, $attachment_id ) {

	//try for thumbnail first
	$file   = ( wp_get_attachment_thumb_file( $attachment_id ) ) ?  wp_get_attachment_thumb_file( $attachment_id ) : get_attached_file( $attachment_id );
	$image  = imagecreatefromstring( file_get_contents( $file ) );
	$width  = imagesx( $image );
	$height = imagesy( $image );

	$pixels = [];
	for ( $y = 0; $y < $height; ++ $y ) {
		$row = [];
		for ( $x = 0; $x < $width; ++ $x ) {
			$index  = imagecolorat( $image, $x, $y );
			$colors = imagecolorsforindex( $image, $index );

			$row[] = [ $colors['red'], $colors['green'], $colors['blue'] ];
		}
		$pixels[] = $row;
	}

	$components_x         = 4;
	$components_y         = 3;
	$blurhash             = Blurhash::encode( $pixels, $components_x, $components_y );
	$metadata['blurhash'] = $blurhash;

	return $metadata;
}

function wp_get_blurhash_image_attributes( $attr, $attachment ) {
	if ( isset( $attachment->blurhash ) ) {
		$attr['style'] = $attachment->blurhash;
	}

	return $attr;
}

/**
 * @param $filtered_image
 * @param $context
 * @param $attachment_id
 *
 * @return array|mixed|string|string[]
 *
 * TODO: replace data-blus has
 */
function wp_blurhash_tag_add_adjust( $filtered_image, $context, $attachment_id ) {

	$image_meta = wp_get_attachment_metadata( $attachment_id );

	if ( isset( $image_meta['blurhash'] ) ) {
		$data = sprintf( 'data-blurhash=%s ',  $image_meta['blurhash'] );

		$filtered_image = str_replace( '<img ', '<img ' . $data, $filtered_image );
	}

	return $filtered_image;
}

function wp_blurhash_print_scripts(){
	?>
<script type="module" >

</script>
<?php
}


function wp_blurhash_enqueue_plugin_scripts() {
	wp_enqueue_script('blurhash', plugin_dir_url(__FILE__) . 'dist/blurhash.js');
}
add_action( 'wp_enqueue_scripts', 'wp_blurhash_enqueue_plugin_scripts' );


function wp_blurhash_filter_content_tags( $content, $context = null ) {
	if ( null === $context ) {
		$context = current_filter();
	}

	$add_img_loading_attr    = wp_lazy_loading_enabled( 'img', $context );
	$add_iframe_loading_attr = wp_lazy_loading_enabled( 'iframe', $context );

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
			 * @since 6.0.0
			 *
			 * @param string $filtered_image  the img tag with attributes being created that will
			 * 									replace the source img tag in the content.
			 * @param string $context Optional. Additional context to pass to the filters.
			 *                        Defaults to `current_filter()` when not set.
			 * @param int    $attachment_id the ID of the image attachment.
			 */
			$filtered_image = apply_filters( 'wp_blurhash_img_tag_add_adjust', $filtered_image, $context, $attachment_id );

			if ( $filtered_image !== $match[0] ) {
				$content = str_replace( $match[0], $filtered_image, $content );
			}
		}
	}

	return $content;
}

