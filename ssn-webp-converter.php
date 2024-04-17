<?php
/**
 * Ssn-Webp-Converter
 *
 * @package             ssn-webp-converter
 * @author              SSN Team
 * @license             GPL-2.0-or-later
 *
 * Plugin Name:         SNN WebP Converter
 * Plugin URI:          https://voron-porto.com
 * Description:         SNN WebP Converter - convert images to webp format from WP media library. Support images jpg, jpeg, png
 * Version:             1.0
 * Requires at least:   6.5.1
 * Requires PHP:        8.1
 * Author:              SSN Team
 * Author URI:          https://voron-porto.com
 * Licence:             GPL v2 or later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:         ssn-webp-converter
 *  Domain Path:        /lang/
 *
 * To DO:
 * 1. https://profiles.wordpress.org/ - create
 * 2. Details
 * 3. https://wordpress.org/plugins - create
 * 4. voron-porto - end the web page, add cases
 * 5. test:
 *      wordpress for 5.1
 *      PHP from 7.0
 *
 * */

if (!defined( 'ABSPATH' ) ) {
	exit;
}

function resize_image_conditional_crop_with_alpha( $image, $new_width, $new_height ) {
	$old_width  = imagesx( $image );
	$old_height = imagesy( $image );

	$src_aspect_ratio  = $old_width / $old_height;
	$dest_aspect_ratio = $new_width / $new_height;

	if ( $src_aspect_ratio > $dest_aspect_ratio ) {
		$src_width  = round( $old_height * $new_width / $new_height );
		$src_height = $old_height;
		$src_x      = round( ( $old_width - $src_width ) / 2 );
		$src_y      = 0;
	} else {
		$src_width  = $old_width;
		$src_height = round( $old_width * $new_height / $new_width );
		$src_x      = 0;
		$src_y      = round( ( $old_height - $src_height ) / 2 );
	}
	$resized_image = imagecreatetruecolor( $new_width, $new_height );
	imagesavealpha( $resized_image, true );
	$transparent = imagecolorallocatealpha( $resized_image, 0, 0, 0, 127 );
	imagefill( $resized_image, 0, 0, $transparent );

	imagecopyresampled( $resized_image, $image, 0, 0, $src_x, $src_y, $new_width, $new_height, $src_width, $src_height );

	return $resized_image;
}

function convert_img_to_webp( $path, $metadata, $isFullSize = false, $isOriginal = false, $quality = 75 ) {
	$path_parts  = pathinfo( $path );
	$meta_parts  = pathinfo( $metadata['file'] );
	$destination = $path_parts['dirname'] . '/' . $meta_parts['filename'] . ".webp";


	$metadata['file']      = $metadata['file'] . ".webp";
	$metadata['mime-type'] = 'image/webp';

	if ( ! $isFullSize && ! $isOriginal ) {
		$metadata['file'] = $meta_parts['filename'] . ".webp";
	}

	$info    = getimagesize( $path );
	$isAlpha = false;

	if ( $info['mime'] == 'image/jpeg' ) {
		$image = imagecreatefromjpeg( $path );
	} elseif ( $isAlpha = $info['mime'] == 'image/gif' ) {
		$image = imagecreatefromgif( $path );
	} elseif ( $isAlpha = $info['mime'] == 'image/png' ) {
		$image = imagecreatefrompng( $path );
	} else {
		return $path;
	}
	if ( $isAlpha ) {
		imagepalettetotruecolor( $image );
		imagealphablending( $image, true );
		imagesavealpha( $image, true );
	}
	if ( ! $isFullSize && ! $isOriginal ) {
		$image = resize_image_conditional_crop_with_alpha( $image, $metadata['width'], $metadata['height'] );
	}

	imagewebp( $image, $destination, $quality );
	$source_file = $path_parts['dirname'] . '/' . $meta_parts['basename'];


	$metadata['filesize'] = filesize( $destination );

	return [ 'meta' => $metadata, 'source_file' => $source_file ];
}
function convert_to_webp( $metadata, $attachment_id ) {
	$source_path_array = [];
	$file              = get_attached_file( $attachment_id );
	$file_type         = wp_check_filetype( $file );

	if ( in_array( $file_type['ext'], [ 'png', 'gif', 'jpeg', 'jpg' ] ) ) {
		$path_parts = pathinfo( $file );
		$meta_parts = pathinfo( $metadata['file'] );
		$data                = convert_img_to_webp( $file, $metadata, true );
		$source_path_array[] = $data['source_file'];
		$metadata['filesize'] = filesize( $path_parts['dirname'] . '/' . $path_parts['filename'] . '.webp' );
		$metadata['file']     = $meta_parts['dirname'] . '/' . $path_parts['filename'] . '.webp';



		foreach ( $metadata['sizes'] as $key => $size ) {
			$data                      = convert_img_to_webp( $file, $metadata['sizes'][ $key ] );
			$metadata['sizes'][ $key ] = $data['meta'];
			$source_path_array[]       = $data['source_file'];
		}

		if ( isset( $metadata["original_image"] ) ) {
			$original_image_data        = [
				"file" => $metadata["original_image"]
			];
			$meta_original_parts        = pathinfo( $original_image_data['file'] );
			$metadata["original_image"] = $meta_original_parts['filename'] . '.webp';
			$data                       = convert_img_to_webp( $file, $original_image_data, false, true );
			$source_path_array[]        = $data['source_file'];
		}
		$new_path = $path_parts['dirname'] . "/" . $path_parts['filename'] . '.webp';

		update_attached_file( $attachment_id, $new_path );
		wp_update_post( [
			'ID'             => $attachment_id,
			'guid'           => $new_path,
			'post_mime_type' => 'image/webp'
		] );

		$guid       = get_post_field( 'guid', $attachment_id );
		$guid_parts = pathinfo( $guid );

		global $wpdb;
		$wpdb->update(
			$wpdb->posts,
			array( 'guid' => $guid_parts['dirname'] . '/' . $guid_parts['filename'] . '.webp' ),
			array( 'ID' => $attachment_id )
		);

//		file_put_contents( WP_PLUGIN_DIR . '/ssn-webp-converter/voron-debug.json', json_encode($source_path_array ), );
		foreach ( $source_path_array as $path ) {
			unlink( $path );
		}
	}

	return $metadata;
}

add_filter( 'wp_generate_attachment_metadata', 'convert_to_webp', 10, 2 );
?>