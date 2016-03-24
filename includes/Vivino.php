<?php

namespace CCVivinoReviewScraper;

use Sunra\PhpSimple\HtmlDomParser;

class Vivino {

    /**
     * Base URI for Vivino, should not need to be changed.
     *
     * @var string
     */
    protected $vivino_uri = 'https://www.vivino.com';

    /**
     * Stores the URI to scrape for Wines.
     *
     * @var string
     */
    protected $vivinoWineryURI;

    /**
     * Stores Array of Wines extracted from getVivinoWines()
     *
     * @var array
     */
    protected $wineData;

    /**
     * Vivino constructor.
     *
     * @param $vivinoWineryURI
     */
    public function __construct( $vivinoWineryURI )
    {
        $this->vivinoWineryURI = $vivinoWineryURI;
        $this->wineData = $this->getVivinoWines($vivinoWineryURI);
    }

    /**
     * Get Individual Reviews from Vivino.
     *
     * @param int $lowestRating
     * @param int $amountOfReviews
     *
     * @return string
     */
    public function getReviews( $lowestRating=5, $amountOfReviews=1 )
    {
        return $this->loadVivinoReviewsIntoData( $this->wineData, $lowestRating, $amountOfReviews );
    }

    /**
     * Scrape Vivino for all Wines associated with a $vivinoWineryURI.
     *
     * @param $vivinoWineryURI
     *
     * @return array
     */
    protected function getVivinoWines( $vivinoWineryURI )
    {
        if ( ! $this->urlExists($vivinoWineryURI) ) {
            return false;
        }

        $dom = HtmlDomParser::file_get_html($vivinoWineryURI);

        $cards = array();

        forEach ( $dom->find('#wine-list .wine-card-box') as $wineCard ) {

            $cards[] = [
                'title' => ( isset($wineCard->find('.wine-name a', 0)->plaintext) ? $wineCard->find('.wine-name a', 0)->plaintext : '' ),
                'title_url' => ( isset($wineCard->find('.wine-name a', 0)->href) ? $wineCard->find('.wine-name a', 0)->href : '' ),
                'location_district' => ( isset($wineCard->find('.wine-country .district a', 0)->plaintext) ? $wineCard->find('.wine-country .district a', 0)->plaintext : '' ),
                'location_district_url' => ( isset($wineCard->find('.wine-country .district a', 0)->href) ? $wineCard->find('.wine-country .district a', 0)->href : '' ),
                'location_country' => ( isset($wineCard->find('.wine-country .country a', 0)->plaintext) ? $wineCard->find('.wine-country .country a', 0)->plaintext : '' ),
                'location_country_url' => ( isset($wineCard->find('.wine-country .country a', 0)->href) ? $wineCard->find('.wine-country .country a', 0)->href : '' ),
                'rating_average' => ( isset($wineCard->find('.key-figure-item[itemprop=ratingValue]', 0)->plaintext) ? $wineCard->find('.key-figure-item[itemprop=ratingValue]', 0)->plaintext : '' ),
                'rating_count' => ( isset($wineCard->find('meta[itemprop=reviewCount]', 0)->content) ? $wineCard->find('meta[itemprop=reviewCount]', 0)->content : '' )
            ];

        }

        return $cards;
    }

    /**
     * Ensure URL Actually exists.
     *
     * @param $url
     *
     * @return bool
     */
    protected function urlExists( $url )
    {
        $headers = @get_headers($url);
        if (is_array($headers)){
            if(strpos($headers[0], '404 Not Found'))
                return false;
            else
                return true;
        }
        else {
            return false;
        }
    }

    /**
     * Load reviews scraped into $wineData[key]['reviews']
     *
     * @param $wineData
     * @param $lowestRating
     * @param $amountOfReviews
     *
     * @return mixed
     */
    protected function loadVivinoReviewsIntoData( $wineData, $lowestRating, $amountOfReviews )
    {
        $reviews = array();

        if ( ! $wineData ) {
            return false;
        }

        // Load Reviews into WineData
        forEach ( $wineData as $key => $value ) {
            if ( !empty( $wineData[$key]['title_url'] ) ) {
                $wineData[$key]['reviews'] = array();
                $reviews = $this->scrapeReviews( $wineData[$key]['title_url'], $lowestRating, $amountOfReviews );

                if ( !empty($reviews) ) {
                    $wineData[$key]['reviews'][] = $reviews;
                }
            }
        }

        return $wineData;
    }

    /**
     * Scrape Reviews from Review URI and load into array.
     *
     * @param $reviewURI
     *
     * @return array
     */
    protected function scrapeReviews( $reviewURI, $lowestRating, $amountOfReviews )
    {
        $dom = HtmlDomParser::file_get_html( $this->vivino_uri . $reviewURI);
        $reviews = array();

        $counter = 0;

        forEach( $dom->find('.user-reviews .review') as $review ) {

            $rating = $review->find('[itemprop=ratingValue]', 0)->content;

            if ( intval($rating) < $lowestRating ) {
                continue;
            }

            $counter++;

            $reviews[] = [
                'author' => $review->find('[itemprop=author]', 0)->plaintext,
                'rating' => $rating,
                'description' => $review->find('[itemprop=description]', 0)->plaintext,
            ];

            if ( $counter >= $amountOfReviews) {
                break;
            }
        }

        return $reviews;
    }


}