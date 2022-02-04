<?php
/**
 * Plugin Name:     Blurhash
 * Plugin URI:      PLUGIN SITE HERE
 * Description:     PLUGIN DESCRIPTION HERE
 * Author:          YOUR NAME HERE
 * Author URI:      YOUR SITE HERE
 * Text Domain:     blurhash
 * Domain Path:     /languages
 * Version:         0.1.0
 *
 * @package         Blurhash
 */

// Your code starts here.
require_once 'vendor/autoload.php';

use kornrunner\Blurhash\Blurhash;

add_filter( 'wp_generate_attachment_metadata', 'wp_generate_blurhash_metadata', 10, 2 );
//add_filter( 'wp_get_attachment_image_attributes', 'wp_get_blurhash_image_attributes', 10, 2 );
add_filter( 'wp_img_tag_add_adjust', 'wp_blurhash_tag_add_adjust', 10, 3 );


function wp_generate_blurhash_metadata( $metadata, $attachment_id ) {
	$file   = get_attached_file( $attachment_id );
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

function wp_blurhash_tag_add_adjust( $filtered_image, $context, $attachment_id ) {
//	var_dump($filtered_image);
	$image_meta = wp_get_attachment_metadata( $attachment_id );
//	var_dump( $image_meta );
	if ( isset( $image_meta['blurhash'] ) ) {
//		var_dump( $image_meta );
		$blurhash = $image_meta['blurhash'];
		$width    = $image_meta['width'];
		$height   = $image_meta['height'];

		$pixels = Blurhash::decode( $blurhash, $width, $height );
		$image  = imagecreatetruecolor( $width, $height );
		for ( $y = 0; $y < $height; ++ $y ) {
			for ( $x = 0; $x < $width; ++ $x ) {
				[ $r, $g, $b ] = $pixels[ $y ][ $x ];
				imagesetpixel( $image, $x, $y, imagecolorallocate( $image, $r, $g, $b ) );
			}
		}
		imagepng( $image, $blurhash.'.png' );

//		die();
		$style = sprintf( 'onload="this.style.removeProperty(\'background\');" style="background-size: cover; background-image: url(data:image/png;base64,%s)"',  base64_encode( file_get_contents( $blurhash.'.png'  ) ) );
		unlink( $blurhash.'.png' );
		$filtered_image = str_replace( '<img ', '<img ' . $style, $filtered_image );
		$filtered_image= str_replace( '.png', 'XXXX' . $style, $filtered_image );
	}

	return $filtered_image;
}
