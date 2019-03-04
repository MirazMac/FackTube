# FackTube
YouTube Public Data Scrapper that parses HTML for the data

**FackTube** is a scrapper for retrieving YouTube public data. Currently it can only scrape videos.
Channels, playlists and single video will be added in future. Feel free to fork and add.

It's not a reliable method for fetching data unless you use tons of paid proxies. And I wouldn't recommend using it on Production either. But hey, YouTube's been a real jerk lately with their Data API v3. It's the least we can do, so lets fack on YouTube.

### Install via Composer

```shell
composer require mirazmac/facktube
```

### Limitations
* You can't limit or change the amount of results per page
* It depends on YouTube's internal html output, and since YouTube heavily relies on JavaScript it can only show the results YouTube provides with no filter whatsoever
* Its illegal and YouTube may block your IP if you send too many requests


## Usage
Check **usage** folder for complete usage examples.


### Searching Videos
```php
use MirazMac\FackTube\FackTube;

$fack = new FackTube($options);

try {
    $results = $fack->videos('Honest Trailer');
} catch (\Exception $e) {
    echo $e->getMessage();
    exit();
}

print_r($results);

```

#### But hey, if html parsing is so messy, Why not AJAX endpoints?

Well, yeah I did that previously with [https://github.com/MirazMac/YouScrape](YouScrape) and now YouTube completely revamped their AJAX endpoints with session based Tokens and other stuff. So, thats a no no. But if you find a way to bypass those create a new repo and let me know, I'll be happy to contribute.
