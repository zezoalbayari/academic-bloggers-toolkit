<?php

namespace ABT;

defined( 'ABSPATH' ) || exit;

require_once __DIR__ . '/i18n.php';

if ( is_admin() ) {
	add_action( 'load-post.php', [ 'ABT\Backend', 'init' ] );
	add_action( 'load-post-new.php', [ 'ABT\Backend', 'init' ] );
}

/**
 * Main Backend Class.
 */
class Backend {
	/**
	 * Sets up all actions and filters for the backend class.
	 */
	public function __construct() {
		$post_type = get_current_screen()->post_type;
		$disabled_post_types = apply_filters( 'abt_disabled_post_types', [ 'acf', 'um_form' ] );
		$is_invalid_post_type = in_array(
			$post_type,
			array_merge(
				[ 'attachment' ],
				is_array( $disabled_post_types ) ? $disabled_post_types : []
			)
		);

		if ( $is_invalid_post_type ) {
			return;
		}
		add_action( 'add_meta_boxes', [ $this, 'add_metaboxes' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'register_scripts' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'admin_head', [ $this, 'init_tinymce' ] );
		add_action( 'admin_notices', [ $this, 'user_alert' ] );
		add_action( 'save_post', [ $this, 'save_meta' ] );
		add_filter( 'mce_css', [ $this, 'load_tinymce_css' ] );
	}

	/**
	 * Instantiates the class and calls hooks on load.
	 */
	public static function init() {
		$class = __CLASS__;
		new $class();
	}

	/**
	 * Alerts the user that the plugin will not work if he/she doesn't have 'Rich Editing' enabled.
	 */
	public function user_alert() {
		if ( 'true' === get_user_option( 'rich_editing' ) ) {
			return;
		}
		$class = 'notice notice-warning is-dismissible';
		$message = __( "<strong>Notice:</strong> Rich editing must be enabled to use the Academic Blogger's Toolkit plugin", 'academic-bloggers-toolkit' );
		printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message );
	}

	/**
	 * Instantiates the TinyMCE plugin.
	 */
	public function init_tinymce() {
		if ( 'true' === get_user_option( 'rich_editing' ) ) {
			add_filter( 'mce_external_plugins', [ $this, 'register_tinymce_plugins' ] );
			echo '<link href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700&subset=cyrillic,cyrillic-ext,greek,greek-ext,latin-ext,vietnamese" rel="stylesheet">';
		}
	}

	/**
	 * Registers the TinyMCE plugins + loads fonts.
	 *
	 * @param string[] $plugin_array array of TinyMCE plugins.
	 *
	 * @return string[] Array of TinyMCE plugins with plugins added
	 */
	public function register_tinymce_plugins( $plugin_array ) {
		$plugin_array['noneditable'] = ABT_ROOT_URI . 'vendor/noneditable.js';
		return $plugin_array;
	}

	/**
	 * Loads the required stylesheet into TinyMCE ( required for proper citation parsing ).
	 *
	 * @param string $mce_css CSS string.
	 *
	 * @return string CSS string + custom CSS appended
	 */
	public function load_tinymce_css( $mce_css ) {
		if ( ! empty( $mce_css ) ) {
			$mce_css .= ',';
		}
		$mce_css .= ABT_ROOT_URI . 'css/tinymce.css';
		return $mce_css;
	}

	/**
	 * Adds metaboxes to posts and pages.
	 *
	 * @param string $post_type The post type.
	 */
	public function add_metaboxes( $post_type ) {
		$disabled_post_types = apply_filters( 'abt_disabled_post_types', [ 'acf', 'um_form' ] );
		$all_types = get_post_types();
		add_meta_box(
			'abt-reflist',
			__( 'Reference List', 'academic-bloggers-toolkit' ),
			[ $this, 'render_reference_list' ],
			$all_types,
			'side',
			'high'
		);
	}

	/**
	 * Renders the HTML for React to mount into.
	 */
	public function render_reference_list() {
		wp_nonce_field( basename( __FILE__ ), 'abt_nonce' );
		include __DIR__ . '/views/reference-list.php';
	}

	/**
	 * Saves the Peer Review meta fields to the database.
	 *
	 * @param string $post_id The post ID.
	 */
	public function save_meta( $post_id ) {
		$is_autosave = wp_is_post_autosave( $post_id );
		$is_revision = wp_is_post_revision( $post_id );
		$is_valid_nonce = ( isset( $_POST['abt_nonce'] ) && wp_verify_nonce( $_POST['abt_nonce'], basename( __FILE__ ) ) ) ? true : false;

		if ( $is_autosave || $is_revision || ! $is_valid_nonce ) {
			return;
		}

		$reflist_state = $_POST['abt-reflist-state'];
		update_post_meta( $post_id, '_abt-reflist-state', $reflist_state );
	}

	/**
	 * Registers all styles and scripts.
	 */
	public function register_scripts() {
		wp_register_style( 'abt-reference-list', ABT_ROOT_URI . 'css/reference-list.css', [], ABT_VERSION );
		wp_register_script( 'abt-reference-list', ABT_ROOT_URI . 'js/reference-list/index.js', [], ABT_VERSION );
	}

	/**
	 * Registers and enqueues all required scripts.
	 */
	public function enqueue_scripts() {
		global $post;

		$ABT_i18n = i18n\generate_translations();
		$state = json_decode( get_post_meta( $post->ID, '_abt-reflist-state', true ), true );
		$opts = get_option( 'abt_options' );

		$custom_preferred = $opts['citation_style']['prefer_custom'] === true;
		$custom_valid = file_exists( $opts['citation_style']['custom_url'] );

		$style = $custom_preferred && $custom_valid ? 'abt-user-defined' : $opts['citation_style']['style'];

		if ( empty( $state ) ) {
			$state = [
				'cache' => [
					'style' => $style,
					'locale' => get_locale(),
				],
				'citationByIndex' => [],
				'CSL' => (object) [],
			];
		}

		$state['bibOptions'] = [
			'heading' => $opts['display_options']['bib_heading'],
			'headingLevel' => $opts['display_options']['bib_heading_level'],
			'links' => $opts['display_options']['links'],
			'style' => $opts['display_options']['bibliography'],
		];

		// Fix legacy post meta.
		if ( array_key_exists( 'processorState', $state ) ) {
			$state['CSL'] = $state['processorState'];
			unset( $state['processorState'] );
		}

		if ( array_key_exists( 'citations', $state ) ) {
			$state['citationByIndex'] = $state['citations']['citationByIndex'];
			unset( $state['citations'] );
		}

		wp_localize_script( 'abt-reference-list', 'ABT', [
			'state' => $state,
			'i18n' => $ABT_i18n,
			'styles' => $this->get_citation_styles(),
			'wp' => $this->localize_wordpress_constants(),
			'custom_csl' => $this->get_user_defined_csl( $opts['citation_style']['custom_url'] ),
		] );

		wp_dequeue_script( 'autosave' );
		wp_enqueue_style( 'dashicons' );
		wp_enqueue_style( 'abt-reference-list' );
		wp_enqueue_script( 'abt-reference-list' );
	}

	/**
	 * Returns an array of citation styles from citationstyles.php.
	 */
	private function get_citation_styles() {
		$json = json_decode( file_get_contents( ABT_ROOT_PATH . '/vendor/citation-styles.json' ), true );
		return $json;
	}

	/**
	 * Returns an array of a few select wordpress constants ( for use in JS ).
	 */
	private function localize_wordpress_constants() {
		return [
			'abt_url' => ABT_ROOT_URI,
			'home_url' => home_url(),
			'plugins_url' => plugins_url(),
			'wp_upload_dir' => wp_get_upload_dir(),
			'info' => [
				'site' => [
					'language' => get_bloginfo( 'language' ),
					'name' => get_bloginfo( 'name' ),
					'plugins' => get_option( 'active_plugins' ),
					'theme' => get_template(),
					'url' => get_bloginfo( 'url' ),
				],
				'versions' => [
					'abt' => ABT_VERSION,
					'php' => PHP_VERSION,
					'wordpress' => get_bloginfo( 'version' ),
				],
			],
		];
	}

	/**
	 * Checks to see if custom CSL XML is saved and available. If so, returns an
	 * array containing the XML, label, and value. If not, returns an array
	 * containing only the key 'value' with the value of null.
	 *
	 * @param string $path path to CSL XML file.
	 *
	 * @return mixed[] array as described above
	 */
	private function get_user_defined_csl( $path ) {
		if ( ! file_exists( $path ) ) {
			return [ 'value' => null ];
		}

		$contents = file_get_contents( $path );
		$xml = new SimpleXMLElement( $contents );
		$label = $xml->info->title->__toString() !== ''
			? $xml->info->title->__toString()
			: 'ABT Custom Style';

		return [
			'label' => $label,
			'value' => 'abt-user-defined',
			'CSL' => $contents,
		];
	}
}
