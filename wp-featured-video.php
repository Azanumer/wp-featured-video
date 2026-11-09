<?php
/**
 * Plugin Name: WP Featured Video
 * Plugin URI: https://github.com/Azanumer/wp-featured-video
 * Description: Add a featured video (YouTube, Vimeo or an uploaded MP4) to any post. On single posts the video replaces the featured image with a lazy-loading facade, so no player loads until the visitor presses play.
 * Version: 1.0.0
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: Azan Umer
 * Author URI: https://azanumer.cv
 * License: MIT
 * License URI: https://opensource.org/licenses/MIT
 * Text Domain: wp-featured-video
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WFV_VERSION', '1.0.0' );
define( 'WFV_OPTION', 'wfv_settings' );
define( 'WFV_VIDEO_META', '_wfv_video_url' );

/**
 * Default settings.
 *
 * @return array
 */
function wfv_defaults() {
	return array(
		'replace_thumbnail' => 1,
		'autoplay'          => 0,
		'facade'            => 1,
	);
}

/**
 * Get merged settings.
 *
 * @return array
 */
function wfv_get_settings() {
	$saved = get_option( WFV_OPTION, array() );
	if ( ! is_array( $saved ) ) {
		$saved = array();
	}
	return array_merge( wfv_defaults(), $saved );
}

/**
 * Detect the video type and ID/URL from a user-supplied URL.
 *
 * @param string $url Raw URL.
 * @return array|false Array with type + id/src, or false when unrecognized.
 */
function wfv_parse_url( $url ) {
	$url = trim( $url );
	if ( '' === $url ) {
		return false;
	}

	// YouTube: watch, embed, shorts and youtu.be links.
	if ( preg_match( '#(?:youtube\.com/(?:watch\?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})#', $url, $m ) ) {
		return array( 'type' => 'youtube', 'id' => $m[1] );
	}

	// Vimeo: numeric video ID.
	if ( preg_match( '#vimeo\.com/(\d+)#', $url, $m ) ) {
		return array( 'type' => 'vimeo', 'id' => $m[1] );
	}

	// Direct video file.
	if ( preg_match( '#\.(mp4|webm|ogv)(\?.*)?$#i', $url ) ) {
		return array( 'type' => 'file', 'src' => esc_url_raw( $url ) );
	}

	return false;
}

/**
 * Build the embed URL for a parsed video.
 *
 * @param array $video Parsed video array.
 * @param bool  $autoplay Whether to autoplay (muted).
 * @return string
 */
function wfv_embed_url( $video, $autoplay ) {
	$auto = $autoplay ? '&autoplay=1&mute=1' : '';
	if ( 'youtube' === $video['type'] ) {
		return 'https://www.youtube-nocookie.com/embed/' . $video['id'] . '?rel=0' . $auto;
	}
	if ( 'vimeo' === $video['type'] ) {
		return 'https://player.vimeo.com/video/' . $video['id'] . '?dnt=1' . $auto;
	}
	return '';
}

/**
 * Render the featured video markup for a post.
 *
 * With the facade on, the featured image (or a plain dark box) is shown with
 * a play button; the real player is injected only on click. With the facade
 * off, the player renders immediately.
 *
 * @param int $post_id Post ID.
 * @return string HTML, or empty string when no video is set.
 */
function wfv_render_video( $post_id ) {
	$url   = get_post_meta( $post_id, WFV_VIDEO_META, true );
	$video = wfv_parse_url( is_string( $url ) ? $url : '' );
	if ( false === $video ) {
		return '';
	}

	$s        = wfv_get_settings();
	$autoplay = ! empty( $s['autoplay'] );

	if ( 'file' === $video['type'] ) {
		$poster = has_post_thumbnail( $post_id ) ? get_the_post_thumbnail_url( $post_id, 'large' ) : '';
		return sprintf(
			'<div class="wfv-video" style="position:relative;padding-top:56.25%%;height:0;overflow:hidden;background:#000;"><video controls%s preload="none" poster="%s" style="position:absolute;top:0;left:0;width:100%%;height:100%%;"><source src="%s" type="video/mp4"></video></div>',
			$autoplay ? ' autoplay muted' : '',
			esc_url( $poster ),
			esc_url( $video['src'] )
		);
	}

	$embed = wfv_embed_url( $video, $autoplay );

	if ( empty( $s['facade'] ) ) {
		return sprintf(
			'<div class="wfv-video" style="position:relative;padding-top:56.25%%;height:0;overflow:hidden;"><iframe src="%s" style="position:absolute;top:0;left:0;width:100%%;height:100%%;border:0;" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy" title="%s"></iframe></div>',
			esc_url( $embed ),
			esc_attr__( 'Featured video', 'wp-featured-video' )
		);
	}

	// Facade: poster image + play button; the iframe is injected on click.
	$poster = has_post_thumbnail( $post_id ) ? get_the_post_thumbnail_url( $post_id, 'large' ) : '';
	$uid    = 'wfv-' . (int) $post_id . '-' . substr( md5( $embed ), 0, 8 );
	$style  = 'position:relative;padding-top:56.25%;height:0;overflow:hidden;background:#000;cursor:pointer;';
	if ( '' !== $poster ) {
		$style .= 'background-image:url(' . esc_url( $poster ) . ');background-size:cover;background-position:center;';
	}

	return sprintf(
		'<div class="wfv-video wfv-facade" id="%s" data-embed="%s" style="%s" role="button" tabindex="0" aria-label="%s"><span style="position:absolute;top:50%%;left:50%%;transform:translate(-50%%,-50%%);width:68px;height:48px;background:rgba(0,0,0,.7);border-radius:12px;display:flex;align-items:center;justify-content:center;"><span style="border-style:solid;border-width:10px 0 10px 18px;border-color:transparent transparent transparent #fff;"></span></span></div><script>(function(){var el=document.getElementById("%s");if(!el)return;var load=function(){var f=document.createElement("iframe");f.src=el.getAttribute("data-embed")+"&autoplay=1&mute=1";f.style.cssText="position:absolute;top:0;left:0;width:100%%;height:100%%;border:0;";f.allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture";f.allowFullscreen=true;f.title="Featured video";el.innerHTML="";el.appendChild(f);el.style.cursor="default";};el.addEventListener("click",load);el.addEventListener("keydown",function(e){if(e.key==="Enter"||e.key===" "){e.preventDefault();load();}});})();</script>',
		esc_attr( $uid ),
		esc_attr( $embed ),
		$style,
		esc_attr__( 'Play featured video', 'wp-featured-video' ),
		esc_attr( $uid )
	);
}

/**
 * Replace the featured image with the video on single posts.
 *
 * @param string $html    Featured image HTML.
 * @param int    $post_id Post ID.
 * @return string
 */
function wfv_maybe_replace_thumbnail( $html, $post_id ) {
	$s = wfv_get_settings();
	if ( empty( $s['replace_thumbnail'] ) ) {
		return $html;
	}
	if ( ! is_singular() || is_admin() ) {
		return $html;
	}
	if ( ! apply_filters( 'wfv_replace_thumbnail', true, $post_id ) ) {
		return $html;
	}

	$video = wfv_render_video( $post_id );
	return '' !== $video ? $video : $html;
}
add_filter( 'post_thumbnail_html', 'wfv_maybe_replace_thumbnail', 10, 2 );

/**
 * Shortcode: [featured_video] or [featured_video id="123"].
 *
 * @param array $atts Shortcode attributes.
 * @return string
 */
function wfv_shortcode( $atts ) {
	$atts    = shortcode_atts( array( 'id' => get_the_ID() ), $atts, 'featured_video' );
	$post_id = (int) $atts['id'];
	if ( $post_id <= 0 ) {
		return '';
	}
	return wfv_render_video( $post_id );
}
add_shortcode( 'featured_video', 'wfv_shortcode' );

// ---------------------------------------------------------------------------
// Meta box
// ---------------------------------------------------------------------------

function wfv_add_meta_box() {
	$types = get_post_types( array( 'public' => true ), 'names' );
	foreach ( $types as $type ) {
		add_meta_box(
			'wfv-meta-box',
			__( 'Featured Video', 'wp-featured-video' ),
			'wfv_meta_box_html',
			$type,
			'side',
			'default'
		);
	}
}
add_action( 'add_meta_boxes', 'wfv_add_meta_box' );

function wfv_meta_box_html( $post ) {
	wp_nonce_field( 'wfv_save_meta', 'wfv_meta_nonce' );
	$url = get_post_meta( $post->ID, WFV_VIDEO_META, true );
	?>
	<p>
		<label for="wfv-url"><strong><?php esc_html_e( 'Video URL', 'wp-featured-video' ); ?></strong></label><br>
		<input type="url" id="wfv-url" name="wfv_video_url" value="<?php echo esc_attr( is_string( $url ) ? $url : '' ); ?>" class="widefat" placeholder="https://www.youtube.com/watch?v=...">
	</p>
	<p class="description"><?php esc_html_e( 'YouTube, Vimeo or a direct MP4/WebM link. Leave empty to use the featured image only.', 'wp-featured-video' ); ?></p>
	<?php
}

/**
 * Save the featured video URL.
 *
 * @param int $post_id Post ID.
 */
function wfv_save_meta( $post_id ) {
	if ( ! isset( $_POST['wfv_meta_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['wfv_meta_nonce'] ), 'wfv_save_meta' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$url = isset( $_POST['wfv_video_url'] ) ? esc_url_raw( wp_unslash( $_POST['wfv_video_url'] ) ) : '';
	if ( '' === $url ) {
		delete_post_meta( $post_id, WFV_VIDEO_META );
	} else {
		update_post_meta( $post_id, WFV_VIDEO_META, $url );
	}
}
add_action( 'save_post', 'wfv_save_meta' );

// ---------------------------------------------------------------------------
// Settings page
// ---------------------------------------------------------------------------

function wfv_register_settings() {
	register_setting(
		'wfv_settings_group',
		WFV_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'wfv_sanitize_settings',
			'default'           => wfv_defaults(),
		)
	);

	add_settings_section( 'wfv_main', __( 'Featured Video', 'wp-featured-video' ), '__return_null', 'wfv-settings' );
	add_settings_field( 'wfv_replace', __( 'Replace featured image with video on single posts', 'wp-featured-video' ), 'wfv_field_checkbox', 'wfv-settings', 'wfv_main', array( 'key' => 'replace_thumbnail' ) );
	add_settings_field( 'wfv_facade', __( 'Lazy-load with a play-button facade', 'wp-featured-video' ), 'wfv_field_checkbox', 'wfv-settings', 'wfv_main', array( 'key' => 'facade' ) );
	add_settings_field( 'wfv_autoplay', __( 'Autoplay (muted)', 'wp-featured-video' ), 'wfv_field_checkbox', 'wfv-settings', 'wfv_main', array( 'key' => 'autoplay' ) );
}
add_action( 'admin_init', 'wfv_register_settings' );

function wfv_add_menu() {
	add_options_page(
		__( 'Featured Video', 'wp-featured-video' ),
		__( 'Featured Video', 'wp-featured-video' ),
		'manage_options',
		'wfv-settings',
		'wfv_settings_page'
	);
}
add_action( 'admin_menu', 'wfv_add_menu' );

function wfv_settings_page() {
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Featured Video', 'wp-featured-video' ); ?></h1>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'wfv_settings_group' );
			do_settings_sections( 'wfv-settings' );
			submit_button();
			?>
		</form>
		<p class="description"><?php esc_html_e( 'Use the [featured_video] shortcode to place the video anywhere in your content.', 'wp-featured-video' ); ?></p>
	</div>
	<?php
}

/**
 * Sanitize settings.
 *
 * @param array $input Raw input.
 * @return array
 */
function wfv_sanitize_settings( $input ) {
	$out   = wfv_defaults();
	$input = is_array( $input ) ? $input : array();

	$out['replace_thumbnail'] = ! empty( $input['replace_thumbnail'] ) ? 1 : 0;
	$out['facade']            = ! empty( $input['facade'] ) ? 1 : 0;
	$out['autoplay']          = ! empty( $input['autoplay'] ) ? 1 : 0;

	return $out;
}

function wfv_field_checkbox( $args ) {
	$s = wfv_get_settings();
	printf(
		'<label><input type="checkbox" name="%s[%s]" value="1" %s> %s</label>',
		esc_attr( WFV_OPTION ),
		esc_attr( $args['key'] ),
		checked( ! empty( $s[ $args['key'] ] ), true, false ),
		esc_html__( 'Yes', 'wp-featured-video' )
	);
}
