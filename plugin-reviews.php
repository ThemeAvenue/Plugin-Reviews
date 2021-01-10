<?php
/**
 * @package   Plugin Reviews
 * @author    starfishwp <support@starfishwp.com>
 * @license   GPL-2.0+
 * @link      https://starfishwp.com/
 * @copyright 2018 Starfish Plugins
 *
 * @wordpress-plugin
 * Plugin Name:       Plugin Reviews
 * Plugin URI:        https://github.com/StarfishWP/Plugin-Reviews
 * Description:       Fetch the reviews for your plugin on WordPress.org and display them on your site.
 * Version:           0.4.0
 * Author:            Starfish Plugins
 * Author URI:        https://starfishwp.com/
 * Text Domain:       wpascr
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}


/**
 * Plugin constants
 */
define( 'WR_VERSION', '0.4.0' );
define( 'WR_URL',     trailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'WR_PATH',    trailingslashit( plugin_dir_path( __FILE__ ) ) );

/**
 * Load textdomain
 */
add_action( 'plugins_loaded', array( 'WR_Reviews', 'load_plugin_textdomain' ), 10, 0 );

/**
 * Load plugin resources
 */
add_action( 'wp_print_styles',  array( 'WR_Reviews', 'load_style' ) );
add_action( 'wp_print_scripts', array( 'WR_Reviews', 'load_script' ) );

add_shortcode( 'wr_reviews', 'plugin_reviews_shortcode' );

/**
 * Activation/Deactivation Hooks
 */
register_activation_hook( __FILE__, array( 'WR_Reviews', 'activate' ) );

add_action( 'init', array( 'WR_Reviews', 'register_block' ) );
add_action( 'enqueue_block_editor_assets', array( 'WR_Reviews', 'load_assets' ) );

/**
 * Plugin Reviews Shortcode
 *
 * @since 0.3.0
 *
 * @param array $atts Shortcode attributes
 *
 * @return string Shortcode result
 */
function plugin_reviews_shortcode( $atts ) {

	$reviews = new WR_Reviews( $atts );
	$empty 	 = trim( "<div class=' wr-grid'></div>" ); 

	$result = $reviews->get_result();

	if ( $result === $empty ) {

		$result = printf(/* translators: %1$s - WordPress.org plugin reviews page.; */
						__( 'No reviews found. If you think it\'s an error, check the reviews on %1$s', 'wordpress-reviews' ),
						'https://wordpress.org/support/plugin/'. $atts['plugin_slug'] .'/reviews/'
					);
	}

	return $result;
}

class WR_Reviews {

	/**
	 * Instance of this class.
	 *
	 * @since    1.0.0
	 * @var      object
	 */
	protected static $instance = null;

	/**
	 * Shortcode attributes.
	 *
	 * @since  0.1.0
	 * @var array
	 */
	protected $atts;

	/**
	 * Slug of the plugin we're getting reviews from
	 *
	 * @since 0.3.0
	 * @var string
	 */
	public $plugin_slug;

	/**
	 * List of all the reviews.
	 *
	 * @since  0.1.0
	 * @var array
	 */
	protected $reviews = array();

	/**
	 * Holds the stuff generated by the shortcode
	 *
	 * @since 0.3.0
	 * @var string
	 */
	public $result;

	public function __construct( $atts ) {
		$this->parse_attributes( $atts );
		$this->includes();
		$this->init();
	}

	public function includes() {
		require_once( WR_PATH . 'class-wr-wordpress-plugin.php' );
		require_once( WR_PATH . 'class-wr-review.php' );
	}

	/**
	 * Enqueue plugin style.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public static function load_style() {
		wp_enqueue_style( 'wr-slick', WR_URL . 'vendor/slick/slick.css', null, '1.5.8', 'all' );
		wp_enqueue_style( 'wr-slick-theme', WR_URL . 'vendor/slick/slick-theme.css', null, '1.5.8', 'all' );
		wp_enqueue_style( 'wr-style', WR_URL . 'plugin-reviews.css', null, WR_VERSION, 'all' );
	}

	/**
	 * Load plugin script.
	 *
	 * @since  0.1.0
	 * @return void
	 */
	public static function load_script() {
		wp_enqueue_script( 'wr-echo', WR_URL . 'vendor/echo/echo.min.js', array( 'jquery' ), '1.7.3', true );
		wp_enqueue_script( 'wr-slick', WR_URL . 'vendor/slick/slick.min.js', array( 'jquery' ), '1.5.8', true );
		wp_enqueue_script( 'wr-script', WR_URL . 'plugin-reviews.js', array( 'jquery', 'wr-echo', 'wr-slick' ), WR_VERSION, true );
	}

	/**
	 * Load the plugin text domain for translation.
	 *
	 * @since    0.1.1
	 * @return boolean True if the language file was loaded, false otherwise
	 */
	public static function load_plugin_textdomain() {
		load_plugin_textdomain( 'wordpress-reviews', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}

	/**
	 * Default attributes.
	 *
	 * @since  0.1.0
	 * @return array Allowed attributes with their default values
	 */
	public static function default_attributes() {

		$defaults = array(
			'plugin_slug'     => 'plugin-reviews',
			'rating'          => 'all',
			'limit'           => 10,
			'sortby'          => 'date',
			'sort'            => 'DESC',
			'truncate'        => 300,
			'gravatar_size'   => 80,
			'container'       => 'div',
			'container_id'    => '',
			'container_class' => '',
			'link_all'        => 'no',
			'link_add'        => 'no',
			'layout'          => 'grid',
			'no_query_string' => '0',
			'exclude'         => '',
		);

		return $defaults;

	}


	/**
	 * Activation action
	 */
	public static function activate() {

		update_option("wr_reviews_flush_transient",true);
	}

	/**
	 * Parse the shortcode attributes.
	 *
	 * Parse the attributes and check for forbidden values.
	 * If some values are not allowed we reset them to default.
	 *
	 * @since  0.1.0
	 * @param  array $atts Custom attributes
	 * @return array       Parsed attributes
	 */
	protected function parse_attributes( $atts ) {

		$defaults       = self::default_attributes();
		$parsed         = shortcode_atts( $defaults, $atts );
		$parsed['sort'] = strtoupper( $parsed['sort'] );

		if ( ! in_array( $parsed['sortby'], array( 'rating', 'date' ) ) ) {
			$parsed['sort'] = 'rating';
		}

		if ( ! in_array( $parsed['sort'], array( 'ASC', 'DESC' ) ) ) {
			$parsed['sortby'] = 'DESC';
		}

		if ( ! in_array( $parsed['layout'], array( 'grid', 'carousel' ) ) ) {
			$parsed['layout'] = 'grid';
		}

		$parsed['container_class'] = (array) $parsed['container_class'];

		if ( 'grid' === $parsed['layout'] ) {
			array_push( $parsed['container_class'], 'wr-grid' );
		} elseif ( 'carousel' === $parsed['layout'] ) {
			array_push( $parsed['container_class'], 'wr-carousel' );
		}

		$parsed['container_class'] = implode( ' ', $parsed['container_class'] );

		$this->atts = $parsed;

		return $parsed;

	}

	/**
	 * WR Reviews Shortcode.
	 *
	 * This shortcode will return a formatted list of reviews
	 * fetched from the requested plugin on WordPress.org.
	 *
	 * @since  0.3.0
	 * @return void
	 */
	public function init() {

		$this->plugin_slug = sanitize_text_field( $this->atts['plugin_slug'] );
		$response          = new WR_WordPress_Plugin( $this->plugin_slug );
		$list              = $response->get_reviews();

		if ( is_wp_error( $list ) ) {
			$this->result = sprintf( __( 'An error occured. You can <a href="%s">check out all the reviews on WordPress.org</a>', 'wordpress-reviews' ), esc_url( "https://wordpress.org/support/view/plugin-reviews/$this->plugin_slug" ) );
		}

		foreach ( $list as $review ) {

			$this_review = new WR_Review( $review, $this->atts['gravatar_size'], $this->atts['truncate'], $this->atts['no_query_string'] );
			$this_output = $this_review->get_review();

			$this->add_review( $this_output );

		}

		$this->result = $this->merge();
	}

	/**
	 * Register gutenber block.
	 *
	 * @return void.
	 */
	public static function register_block() {

		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}

		$attributes = [
			'pluginSlug'       => [
				'type' => 'string',
			],
			'layout' => [
				'type' => 'string',
			],
			'rating'  => [
				'type' => 'string',
			],
			'sortBy'    => [
				'type' => 'string',
			],
			'limit'    => [
				'type' => 'string',
			],
		];

		register_block_type(
			'plugin-reviews/plugin-reviews-content',
			array(
				'attributes' 	  => $attributes,
				'editor_script'   => 'plugin-reviews-gutenberg-block',
				'render_callback' => [ 'WR_Reviews', 'view_reviews' ],
			)
		);
	}

	/**
	 * Get reviews in the Gutenberg block.
	 *
	 * @param array $attr Attributes passed by the Gutenberg block.
	 *
	 * @return string
	 */
	public static function view_reviews( $attr ) {

		$attr = array( 
			'plugin_slug' => isset( $attr['pluginSlug'] ) ? $attr['pluginSlug'] : '',
			'limit' 	  => isset( $attr['limit'] ) ? $attr['limit'] : 10,
			'sort' 		  => isset( $attr['sortBy'] ) ? $attr['sortBy'] : 'DESC',
			'rating'      => isset( $attr['rating'] ) ? $attr['rating'] : 'all',
		);

		return plugin_reviews_shortcode( $attr );
	}

	/**
	 * Load assets on gutenberg area.
	 *
	 * @return void.
	 */
	public static function load_assets() {
		wp_enqueue_script(
			'plugin-reviews-gutenberg-block',
			WR_URL . 'assets/block.js',
			array( 'wp-blocks', 'wp-editor' ),
			true
		);

		wp_enqueue_style( 'wr-slick', WR_URL . 'vendor/slick/slick.css', null, '1.5.8', 'all' );
		wp_enqueue_style( 'wr-slick-theme', WR_URL . 'vendor/slick/slick-theme.css', null, '1.5.8', 'all' );
		wp_enqueue_style( 'wr-style', WR_URL . 'plugin-reviews.css', null, WR_VERSION, 'all' );

		wp_localize_script( 'plugin-reviews-gutenberg-block', 'plugin_reviews_params', array(
			'preview_url' => WR_URL . 'images/spinner.gif'
		) );
	}

	/**
	 * Add a review in the list.
	 *
	 * @since  0.1.0
	 * @param  array $review Review to add
	 * @return void
	 */
	protected function add_review( $review ) {
		array_push( $this->reviews, $review );
	}

	/**
	 * Filter reviews.
	 *
	 * Filter reviews by rating. Get rid of reviews with a rating
	 * too low.
	 *
	 * @since  0.1.0
	 * @return array Filtered reviews
	 */
	protected function filter() {

		if ( 'all' === $this->atts['rating'] ) {
			return $this->reviews;
		}

		$stars = intval( $this->atts['rating'] );

		if ( $stars >= 1 && $stars <= 5 ) {

			$new = array();

			foreach ( $this->reviews as $key => $review ) {
				if ( intval( $review['rating'] ) >= $stars ) {
					$new[] = $review;
				}
			}

			$this->reviews = $new;

			return $new;

		} else {
			return $this->reviews;
		}

	}

	/**
	 * Remove reviews from excluded users
	 *
	 * @since 0.1.1
	 * @return array Reviews
	 */
	protected function exclude() {

		if ( empty( $this->atts['exclude'] ) ) {
			return $this->reviews;
		}

		$excludes = explode( ',', $this->atts['exclude'] );
		$new      = array();

		foreach ( $this->reviews as $key => $review ) {

			$username = $this->get_username( $review );

			if ( ! in_array( $username, $excludes ) ) {
				$new[] = $review;
			}

		}

		$this->reviews = $new;

		return $new;

	}

	/**
	 * Get reviewer username
	 *
	 * @since 0.1.1
	 *
	 * @param array $review Review to get username from
	 *
	 * @return string Username
	 */
	protected function get_username( $review ) {

		$url    = $review['username']['href']; // Get username from URL as the actual username returned by WordPress is a nice name and not the username
		$pieces = explode( '/', $url );

		return sanitize_text_field( $pieces[ count( $pieces ) - 1 ] );

	}

	/**
	 * Sort the reviews.
	 *
	 * @since  0.1.0
	 * @return array Sorted reviews
	 */
	protected function sort_reviews() {

		$index   = array();
		$ordered = array();

		foreach ( $this->reviews as $key => $review ) {
			$value       = 'rating' === $this->atts['sortby'] ? $review['rating'] : $review['timestamp'];
			$index[$key] = $value;
		}

		switch ( $this->atts['sort'] ) {

			case 'DESC':
				arsort( $index );
				break;

			case 'ASC':
				asort( $index );
				break;

		}

		foreach ( $index as $key => $value ) {
			$ordered[] = $this->reviews[$key];
		}

		$this->reviews = $ordered;

		return $ordered;

	}

	/**
	 * Limit the number of reviews.
	 *
	 * @since  0.1.0
	 * @return array Reviews
	 */
	protected function limit() {

		if ( empty( $this->atts['limit'] ) || 'none' === $this->atts['limit'] ) {
			return $this->reviews;
		}

		$slice = array_slice( $this->reviews, 0, intval( $this->atts['limit'] ) );

		$this->reviews = $slice;

		return $slice;

	}

	/**
	 * Get all the reviews with final markup.
	 *
	 * This function returns an array of all the reviews with the final
	 * markup (including converted template tags).
	 *
	 * @since  0.1.0
	 * @return array List of all the reviews
	 */
	public function get_reviews() {

		$this->filter();
		$this->exclude();
		$this->sort_reviews();
		$this->limit();

		return $this->reviews;

	}

	/**
	 * Get the formatted reviews
	 *
	 * @since 0.3.0
	 * @return string
	 */
	public function get_result() {
		return $this->result;
	}

	/**
	 * Merge the reviews array into a echoable string.
	 *
	 * @since  0.1.0
	 * @return string Shortcode output
	 */
	protected function merge() {

		$output            = '';
		$links             = array();
		$label_all_reviews = apply_filters( 'wr_label_all_reviews', __( 'See all reviews', 'wordpress-reviews') );
		$label_add_review  = apply_filters( 'wr_label_add_review', __( 'Add a review', 'wordpress-reviews') );

		foreach ( $this->get_reviews() as $review ) {
			$output .= $review['output'];
		}

		if ( !empty( $this->atts['container'] ) ) {

			$attributes = array();


			if ( !empty( $this->atts['container_class'] ) ) {
				$attributes[] = "class='{$this->atts['container_class']}'";
			}

			if ( !empty( $this->atts['container_id'] ) ) {
				$attributes[] = "id='{$this->atts['container_id']}'";
			}

			$attributes = implode( ' ', $attributes );
			$output     = "<{$this->atts['container']} $attributes>$output</{$this->atts['container']}>";

		}

		if ( 'yes' == $this->atts['link_all'] ) {
			$links[] = "<a href='https://wordpress.org/support/view/plugin-reviews/{$this->atts['plugin_slug']}' target='_blank' class='wr-reviews-link-all'>$label_all_reviews</a>";
		}

		if ( 'yes' == $this->atts['link_add'] ) {
			$links[] = "<a href='https://wordpress.org/support/view/plugin-reviews/{$this->atts['plugin_slug']}#postform' target='_blank' class='wr-reviews-link-add'>$label_add_review</a>";
		}

		if ( !empty( $links ) ) {
			$links = implode( ' | ', $links );
			$links = "<p class='wr-reviews-link'>$links</p>";
			$output .= $links;
		}

		return $output;

	}

}
