<?php
/**
 * Plugin Name: Advanced Search and Filter - Brandon
 * Description: Provides advanced search functionality across Toolset custom post types.
 * Version: 0.1.0
 * Author: Brandon
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class ASF_Brandon_Plugin {

    private $option_name = 'asfb_settings';

    public function __construct() {
        add_action( 'admin_menu', [ $this, 'register_settings_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_shortcode( 'asf_search_form', [ $this, 'search_form_shortcode' ] );
        add_shortcode( 'asf_search_results', [ $this, 'results_shortcode' ] );
    }

    public function register_settings_page() {
        add_options_page( 'Advanced Search & Filter Plugin - Brandon', 'Advanced Search & Filter - Brandon', 'manage_options', 'asfb-settings', [ $this, 'settings_page' ] );
    }

    public function register_settings() {
        register_setting( 'asfb_settings_group', $this->option_name );
    }

    public function settings_page() {
        $options = get_option( $this->option_name );
        $post_types = get_post_types( [ 'public' => true ], 'objects' );
        ?>
        <div class="wrap">
            <h1>Advanced Search &amp; Filter - Brandon</h1>
            <form method="post" action="options.php">
                <?php settings_fields( 'asfb_settings_group' ); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="post_type">Post Type</label></th>
                        <td>
                            <select name="<?php echo $this->option_name; ?>[post_type]">
                                <?php foreach ( $post_types as $slug => $obj ) : ?>
                                    <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $options['post_type'] ?? '', $slug ); ?>><?php echo esc_html( $obj->labels->singular_name ); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="fields">Fields to Display (comma separated meta keys)</label></th>
                        <td>
                            <input type="text" name="<?php echo $this->option_name; ?>[fields]" value="<?php echo esc_attr( $options['fields'] ?? '' ); ?>" class="regular-text" />
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }

    public function search_form_shortcode() {
        $radius_options = [ 0 => 'Exact', 5 => '5 miles', 10 => '10 miles', 25 => '25 miles', 50 => '50 miles' ];
        $states = $this->get_states();
        ob_start();
        ?>
        <form method="get" class="asfb-search-form">
            <input type="text" name="asfb_name" placeholder="Name" value="<?php echo esc_attr( $_GET['asfb_name'] ?? '' ); ?>" />
            <input type="text" name="asfb_zip" placeholder="Zip Code" value="<?php echo esc_attr( $_GET['asfb_zip'] ?? '' ); ?>" />
            <select name="asfb_radius">
                <?php foreach ( $radius_options as $val => $label ) : ?>
                    <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $_GET['asfb_radius'] ?? '', $val ); ?>><?php echo esc_html( $label ); ?></option>
                <?php endforeach; ?>
            </select>
            <select name="asfb_state">
                <option value="">State</option>
                <?php foreach ( $states as $abbr => $name ) : ?>
                    <option value="<?php echo esc_attr( $abbr ); ?>" <?php selected( $_GET['asfb_state'] ?? '', $abbr ); ?>><?php echo esc_html( "$name ($abbr)" ); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Search</button>
            <a href="<?php echo esc_url( remove_query_arg( [ 'asfb_name', 'asfb_zip', 'asfb_radius', 'asfb_state', 'paged' ] ) ); ?>">Clear All</a>
        </form>
        <?php
        return ob_get_clean();
    }

    public function results_shortcode( $atts ) {
        $options = get_option( $this->option_name );
        $post_type = $options['post_type'] ?? 'post';
        $fields = array_map( 'trim', explode( ',', $options['fields'] ?? '' ) );
        $paged = max( 1, intval( $_GET['paged'] ?? 1 ) );
        $args = [
            'post_type' => $post_type,
            'posts_per_page' => 10,
            'paged' => $paged,
            'meta_query' => [ 'relation' => 'AND' ]
        ];

        $meta_query = [];
        $name = sanitize_text_field( $_GET['asfb_name'] ?? '' );
        if ( $name ) {
            $meta_query[] = [
                'relation' => 'OR',
                [
                    'key' => 'wpcf-first-name',
                    'value' => $name,
                    'compare' => 'LIKE'
                ],
                [
                    'key' => 'wpcf-middle-name',
                    'value' => $name,
                    'compare' => 'LIKE'
                ],
                [
                    'key' => 'wpcf-last-name',
                    'value' => $name,
                    'compare' => 'LIKE'
                ],
            ];
        }

        $state = sanitize_text_field( $_GET['asfb_state'] ?? '' );
        if ( $state ) {
            $meta_query[] = [
                'key' => 'wpcf-state',
                'value' => $state,
                'compare' => 'LIKE'
            ];
        }

        $args['meta_query'] = array_merge( $args['meta_query'], $meta_query );

        $query = new WP_Query( $args );
        $results = [];
        $search_zip = sanitize_text_field( $_GET['asfb_zip'] ?? '' );
        $radius = floatval( $_GET['asfb_radius'] ?? 0 );
        if ( $search_zip ) {
            $search_coords = $this->get_coords_for_zip( $search_zip );
        } else {
            $search_coords = false;
        }

        while ( $query->have_posts() ) :
            $query->the_post();
            $include = true;
            if ( $search_coords && $radius > 0 ) {
                $post_zip = get_post_meta( get_the_ID(), 'wpcf-zip-code', true );
                $coords = $this->get_coords_for_zip( $post_zip );
                if ( $coords ) {
                    $distance = $this->haversine_distance( $search_coords['lat'], $search_coords['lng'], $coords['lat'], $coords['lng'] );
                    if ( $distance > $radius ) {
                        $include = false;
                    }
                }
            }
            if ( $include ) {
                $results[] = get_the_ID();
            }
        endwhile;
        wp_reset_postdata();

        ob_start();
        if ( $results ) {
            echo '<div class="asfb-grid">';
            foreach ( $results as $post_id ) {
                echo '<div class="asfb-item">';
                if ( has_post_thumbnail( $post_id ) ) {
                    echo get_the_post_thumbnail( $post_id, 'medium' );
                }
                $first = get_post_meta( $post_id, 'wpcf-first-name', true );
                $middle = get_post_meta( $post_id, 'wpcf-middle-name', true );
                $last = get_post_meta( $post_id, 'wpcf-last-name', true );
                $email = get_post_meta( $post_id, 'wpcf-email', true );
                $phone = get_post_meta( $post_id, 'wpcf-phone', true );
                echo '<p>' . esc_html( trim( "$first $middle $last" ) ) . '</p>';
                echo '<p>' . esc_html( $email ) . '</p>';
                echo '<p>' . esc_html( $phone ) . '</p>';
                echo '</div>';
            }
            echo '</div>';
            $big = 999999999; // need an unlikely integer
            echo paginate_links( [
                'base'    => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
                'format'  => '?paged=%#%',
                'current' => max( 1, $paged ),
                'total'   => $query->max_num_pages
            ] );
        } else {
            echo '<p>No results found.</p>';
        }
        return ob_get_clean();
    }

    private function get_coords_for_zip( $zip ) {
        $cache = get_transient( 'asfb_zip_' . $zip );
        if ( $cache ) {
            return $cache;
        }
        $response = wp_remote_get( 'https://api.zippopotam.us/us/' . urlencode( $zip ) );
        if ( is_wp_error( $response ) ) {
            return false;
        }
        $body = json_decode( wp_remote_retrieve_body( $response ), true );
        if ( ! empty( $body['places'][0]['latitude'] ) ) {
            $coords = [
                'lat' => floatval( $body['places'][0]['latitude'] ),
                'lng' => floatval( $body['places'][0]['longitude'] ),
            ];
            set_transient( 'asfb_zip_' . $zip, $coords, DAY_IN_SECONDS );
            return $coords;
        }
        return false;
    }

    private function haversine_distance( $lat1, $lon1, $lat2, $lon2 ) {
        $earth_radius = 3959; // in miles
        $lat_delta = deg2rad( $lat2 - $lat1 );
        $lon_delta = deg2rad( $lon2 - $lon1 );
        $a = sin( $lat_delta / 2 ) * sin( $lat_delta / 2 ) +
            cos( deg2rad( $lat1 ) ) * cos( deg2rad( $lat2 ) ) *
            sin( $lon_delta / 2 ) * sin( $lon_delta / 2 );
        $c = 2 * atan2( sqrt( $a ), sqrt( 1 - $a ) );
        return $earth_radius * $c;
    }

    private function get_states() {
        return [
            'AL' => 'Alabama',
            'AK' => 'Alaska',
            'AZ' => 'Arizona',
            'AR' => 'Arkansas',
            'CA' => 'California',
            'CO' => 'Colorado',
            'CT' => 'Connecticut',
            'DE' => 'Delaware',
            'FL' => 'Florida',
            'GA' => 'Georgia',
            'HI' => 'Hawaii',
            'ID' => 'Idaho',
            'IL' => 'Illinois',
            'IN' => 'Indiana',
            'IA' => 'Iowa',
            'KS' => 'Kansas',
            'KY' => 'Kentucky',
            'LA' => 'Louisiana',
            'ME' => 'Maine',
            'MD' => 'Maryland',
            'MA' => 'Massachusetts',
            'MI' => 'Michigan',
            'MN' => 'Minnesota',
            'MS' => 'Mississippi',
            'MO' => 'Missouri',
            'MT' => 'Montana',
            'NE' => 'Nebraska',
            'NV' => 'Nevada',
            'NH' => 'New Hampshire',
            'NJ' => 'New Jersey',
            'NM' => 'New Mexico',
            'NY' => 'New York',
            'NC' => 'North Carolina',
            'ND' => 'North Dakota',
            'OH' => 'Ohio',
            'OK' => 'Oklahoma',
            'OR' => 'Oregon',
            'PA' => 'Pennsylvania',
            'RI' => 'Rhode Island',
            'SC' => 'South Carolina',
            'SD' => 'South Dakota',
            'TN' => 'Tennessee',
            'TX' => 'Texas',
            'UT' => 'Utah',
            'VT' => 'Vermont',
            'VA' => 'Virginia',
            'WA' => 'Washington',
            'WV' => 'West Virginia',
            'WI' => 'Wisconsin',
            'WY' => 'Wyoming',
        ];
    }
}

new ASF_Brandon_Plugin();

/**
 * Basic styles for grid layout
 */
function asfb_enqueue_styles() {
    wp_enqueue_style( 'asfb-styles', plugin_dir_url( __FILE__ ) . 'asfb-styles.css' );
}
add_action( 'wp_enqueue_scripts', 'asfb_enqueue_styles' );

?>
