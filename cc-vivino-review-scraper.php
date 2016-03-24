<?php

/*
 * Plugin Name: Vivino Review Scraper
 * Plugin URI: https://cameroncarranza.com
 * Description: Scrape a Vivino Winery for reviews.
 * Version: 0.1
 * Author: Cameron Carranza
 * Author URI: https://cameroncarranza.com
 * License: MIT
 */

if ( ! defined( 'ABSPATH' ) ) {
    die();
}

/**
 * Plugin Activation Steps
 */
register_activation_hook( __FILE__, 'cc_vivino_review_activation_checks' );
function cc_vivino_review_activation_checks()
{
    // Make sure Composer Autoloader exists
    if ( ! realpath(__DIR__ . '/vendor/autoload.php') ) {
        wp_die('<p>Composer Autoloader does not exist. Navigate to this plugins directory and run "composer update"</p>');
        deactivate_plugins( basename(__FILE__) );
    }

    // Set up Cron Task
    wp_schedule_event(time(), 'hourly', 'cc_vivino_review_cron');
}

/**
 * Plugin Deactivation Clean-up
 */
register_deactivation_hook( __FILE__, 'cc_vivino_review_plugin_teardown' );
function cc_vivino_review_plugin_teardown()
{
    wp_clear_scheduled_hook('cc_vivino_review_cron');
    delete_option('cc_vivino_review_reviews');
}

require __DIR__ . '/vendor/autoload.php';

use CCVivinoReviewScraper\Vivino;

add_action( 'admin_init', 'cc_vivino_review_scraper_add_options' );
add_action( 'admin_menu', 'cc_vivino_review_scraper_admin_menu');
add_action( 'cc_vivino_review_cron', 'cc_vivino_review_cron_storeReviews');

/**
 * Create a new Options Page.
 */
function cc_vivino_review_scraper_admin_menu()
{
    add_options_page(
        'Vivino Reviews',
        'Vivino Reviews',
        'manage_options',
        'cc-vivino-review-scraper-options',
        'cc_vivino_review_scraper_options_page'
    );
}

/**
 * Render the Options Page.
 */
function cc_vivino_review_scraper_options_page()
{
    ?>
    <div class="wrap">
        <h2>Vivino Reviews</h2>

        <p>Access Review data within PHP via <code>json_decode(get_option('cc_vivino_review_reviews'))</code></p>

        <form method="post" action="options.php">
            <?php settings_fields( 'cc-vivino-review-scraper-group' ); ?>
            <?php do_settings_sections( 'cc-vivino-review-scraper-group' ); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Winery URL</th>
                    <td><input id="vivinoWineryURI" class="regular-text" placeholder="https://www.vivino.com/wineries/mywineco/wines/" type="text" name="vivinoWineryURI" value="<?php echo esc_attr( get_option('vivinoWineryURI') ); ?>" /></td>
                </tr>

                <tr valign="top">
                    <th scope="row">Lowest Rating Allowed</th>
                    <td><input id="lowestRating" type="text" placeholder="5" name="lowestRating" value="<?php echo esc_attr( get_option('lowestRating') ); ?>" /></td>
                </tr>

                <tr valign="top">
                    <th scope="row">Amount of Reviews to Fetch</th>
                    <td><input id="amountOfReviews" type="text" placeholder="10" name="amountOfReviews" value="<?php echo esc_attr( get_option('amountOfReviews') ); ?>" /></td>
                </tr>
            </table>

            <?php submit_button(); ?>

        </form>
    </div>
    <?php
}

/**
 * Register Setting Variables.
 */
function cc_vivino_review_scraper_add_options()
{
    register_setting('cc-vivino-review-scraper-group', 'vivinoWineryURI', 'cc_vivino_review_validateVivinoWineryURI');
    register_setting('cc-vivino-review-scraper-group', 'lowestRating', 'cc_vivino_review_validateLowestRating');
    register_setting('cc-vivino-review-scraper-group', 'amountOfReviews', 'cc_vivino_review_validateAmountOfReviews');

    register_setting('cc-vivino-review-scraper-group', 'vivinoReviews', 'cc_vivino_review_cron_storeReviews');
}

/**
 * Validate Vivino Winery URI
 *
 * @param $input
 *
 * @return bool
 */
function cc_vivino_review_validateVivinoWineryURI($input)
{
    // Make sure URL is directed at Vivino
    if ( ! strstr(parse_url( $input, PHP_URL_HOST ), 'vivino.com' ) ) {
        add_settings_error('vivinoWineryURI', 'vivinoWineryURI', 'URL Must be directed at Vivino.com', 'error');
        return false;
    }

    // Make sure URL points at a Winery.
    if ( 0 !== strpos(parse_url( $input, PHP_URL_PATH), '/wineries/') ) {
        add_settings_error('vivinoWineryURI', 'vivinoWineryURI', 'URL Must be pointed at a Winery (e.x. https://www.vivino.com/wineries/mywineco/wines/)', 'error');
        return false;
    }

    // Make sure URL ends with wines/ or /wines
    if ( (substr( parse_url( $input, PHP_URL_PATH), -6 ) !== 'wines/') && (substr( parse_url( $input, PHP_URL_PATH), -6 ) !== '/wines') ) {
        add_settings_error('vivinoWineryURI', 'vivinoWineryURI', 'URL Must be pointed at a Wineries Wines Page (e.x. https://www.vivino.com/wineries/mywineco/wines/)', 'error');
        return false;
    }

    return $input;
}

/**
 * Validate Lowest Rating
 *
 * @param $input
 *
 * @return bool
 */
function cc_vivino_review_validateLowestRating($input)
{
    if ( ! is_numeric($input) ) {
        add_settings_error('lowestRating', 'lowestRating', 'Rating Must be a Number', 'error');
        return false;
    }

    if ( ! ((0 <= intval($input)) && (intval($input) <= 5 )) ) {
        add_settings_error('lowestRating', 'lowestRating', 'Rating Must be between 0 and 5', 'error');
        return false;
    }

    return $input;
}

/**
 * Validate Amount of Reviews
 *
 * @param $input
 *
 * @return bool
 */
function cc_vivino_review_validateAmountOfReviews($input)
{
    if ( ! is_numeric($input) ) {
        add_settings_error('amountOfReviews', 'amountOfReviews', 'Amount of Reviews Must be a Number', 'error');
        return false;
    }

    if ( intval($input) < 0 ) {
        add_settings_error('amountOfReviews', 'amountOfReviews', 'Amount of Reviews Must be a Positive Number', 'error');
        return false;
    }

    return $input;
}

/**
 * Cron / Submit Task to get the Reviews and store them
 */
function cc_vivino_review_cron_storeReviews()
{
    $reviews = cc_vivino_review_getReviews();

    if ( ! $reviews ) {
        add_settings_error('vivinoReviews', 'vivinoReviews', 'Failed to Fetch Reviews, please make sure your URL Exists and your settings are valid.', 'error');
        return false;
    }

    update_option('cc_vivino_review_reviews', json_encode($reviews), 'yes');
}

/**
 * Get Reviews based on Options Params
 *
 * @return string
 */
function cc_vivino_review_getReviews()
{
    $url = get_option('vivinoWineryURI');
    $lowestRating = get_option('lowestRating');
    $amountOfReviews = get_option('amountOfReviews');

    if ( !empty($url) && !empty($lowestRating) && !empty($amountOfReviews) ) {

        return (new Vivino($url))->getReviews($lowestRating, $amountOfReviews);

    }
    return false;
}
