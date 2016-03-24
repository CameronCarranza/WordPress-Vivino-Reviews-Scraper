# WordPress Vivino Reviews Scraper Plugin

This plugin is designed to scrape reviews and review data from Vivino 
as they currently lack any form of API. All this plugin does is leverage 
[Simple HTML DOM Parser](https://github.com/sunra/php-simple-html-dom-parser) 
to extract data from Vivino. It does **not** provide any front-end and exists 
solely to provide you with a clean array of data to manipulate.

This plugin has been tested to scrape a handful of wineries /wines/ pages without issue, though if you do encounter any issues feel free to open a new Github Issue.

# Important note about deployments
As this plugin leverages composer and has the `/vendor` directory excluded via the gitignore file, it is possible that if you use git-based deployments/builds you will receive some sort of error getting the plugin to deploy. It is recommended that you run `composer update` on deployment and/or add `composer update` as a build-step in your automated deployment process.

## Requirements
* PHP >= 5.6 (Tested on 5.6 / 7, may work on others)
* php-curl (for Simple DOM Parser)
* A WordPress installation
* [Composer](https://getcomposer.org/) (to install plugin dependencies)

## Installation
* Clone this repo into your plugins directory (`git clone git@github.com:Radau/WordPress-Vivino-Reviews-Scraper.git cc-vivino-review-scraper`), then run `composer update` inside of 
the newly downloaded plugin folder.
* Activate the plugin via the WordPress plugins menu.
* Go to `Settings > Vivino Reviews` and fill out your options data.

## How does it work?
#### Cron
This plugin leverages WP-Cron which triggers every hour provided there is a visitor, which allows for a non-blocking way to scrape the data (so your visitors don't end up waiting 20 seconds to load a page). If you deactivate, then reactivate this plugin it will immediately attempt to scrape data with the options you had when it was deactivated.

#### Manually execute the scraping job
If for any reason you need to re-run the scraping job. Simply go to the options page and click "Save Changes". This will take anywhere from a second to a few minutes depending on how much data it will scrape, so be patient.

## Usage in templates, WordPress PHP in general.
This plugin serializes the scraped data into a WP Options field as JSON to maintain relationships. When attempting to access the data, simply use `json_decode(get_option('cc_vivino_review_reviews'))` to get the array of all of your data. From there, it is up to you to sort it out as you see fit. This plugin exists solely to get you the data.

## Loading into Timber
Under your `StarterSite extends TimberSite`, have a filter for `timber_context` that points to a method within the class that grabs the options data. The example below will let you access `{{ options.cc_vivino_reviews_data }}` within Twig templates in Timber. If you want a different name, simply change the context name.
```php
// Example
class StarterSite extends TimberSite {
    function __construct()
    {
        add_filter('timber_context', array( $this, 'add_to_context' )); // Make sure you have this...
        parent::__construct();
    }
    
    function add_to_context( $context )
    {
        $context['cc_vivino_reviews_data'] = json_decode(get_option('cc_vivino_review_reviews')); // And this
        return $context;
    }
    
}
```

<img src="http://i.imgur.com/4uwaf07.png">
