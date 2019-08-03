<?php

namespace MirazMac\FackTube;

// ew, PSR-0!
use DiDom\Document;
use MirazMac\FackTube\Helpers;
use MirazMac\FackTube\Interfaces\ParserInterface;
use MirazMac\FackTube\Parsers\VideosParser;
use \Requests_Cookie_Jar;
use \Requests_Session;

/**
* FackTube
*
* Scrapes YouTube data(videos only currently) with heavy HTML parsing, not reliable but hey! lets fack on YouTube
*
* @author MirazMac <mirazmac@gmail.com>
* @version 0.1 Initial
* @license LICENSE The MIT License
* @link https://mirazmac.info/ Author Homepage
*/
class FackTube
{
    /**
     * Host to search videos
     */
    const YT_SEARCH_HOST = 'https://www.youtube.com/results';

    /**
     * HTTP session
     *
     * @var object
     */
    protected static $session;

    /**
     * Generic key/value pair based options
     *
     * @var array
     */
    protected $options = [];

    /**
     * Create a new instance
     *
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $defaults = [
            // path to cache directory
            // required for some portion of the library
            // defaults to cwd() / Cache
            'cacheDir' => __DIR__ . '/Cache',

            // If you wanna use proxy pass it here as PROXY:PORT (1.1.1.1:8080) format
            // If your proxy needs you to authenticate, the option will become an array like the following:
            // 'proxy' => ['127.0.0.1:3128', 'proxy_username', 'proxy_password']
            'proxy'    => false,

            // connection timeout value
            'timeout' => 80,

            // Custom headers
            'headers' => [],
        ];

        // Merge 'n' set the options
        $this->options = array_merge($defaults, $options);
    }

    /**
     * Search for videos
     *
     * @param  string $query
     * @param  string $pageToken
     * @return array
     */
    public function videos($query, $pageToken = null)
    {
        $params = [
            'search_query' => $query,
        ];

        if ($pageToken) {
            $params['sp'] = $pageToken;
        }

        $document = $this->request(static::YT_SEARCH_HOST, $params);
        $videos = $document->find('.yt-lockup-content');

        $results = [
            'videos' => [],
            'pages' => [],
        ];

        foreach ($videos as $line) {
            // sorry but we only need videos
            if (strpos($line, 'watch?v=') === false) {
                continue;
            }

            $video = [
                'id'          => '',
                'title'       => '',
                'thumbnail'   => '',
                'description' => '',
                'channelName' => '',
                'channelID'   => '',
                'duration'    => '',
                'uploaded'    => '',
                'views'       => '',
                'badges'       => [],
            ];

            if (!count($link = $line->find('h3.yt-lockup-title a'))) {
                continue;
            }

            $channel = $line->find('.yt-lockup-byline a');

            if (isset($channel[0])) {
                $channelID = str_ireplace(['/user/', '/channel/'], '', $channel[0]->attr('href'));
                $video['channelID'] = $channelID;
                $video['channelName'] = $channel[0]->text();
            }

            // Get the title from the attritube
            $title = $link[0]->attr('title');
            $url = $link[0]->attr('href');

            // Grab the video ID
            preg_match('#v=(.*)#', $url, $id);

            // no video ID? wtf
            if (!isset($id[1])) {
                continue;
            }

            // video description
            if (count($description = $line->find('.yt-lockup-description'))) {
                $video['description'] = $description[0]->text();
            }

            // Grab the duration
            preg_match('/Duration:\s?(.*)\./', $line, $duration);

            if (isset($duration[1])) {
                $video['duration'] = $duration[1];
            }

            $meta = $line->find('.yt-lockup-meta-info li');

            foreach ($meta as $li) {
                if (strpos($li, 'ago')) {
                    $video['uploaded'] = $li->text();
                } else {
                    $video['views'] = Helpers::numbersOnly($li->text());
                }
            }

            $badges = $line->find('.yt-badge-item');

            foreach ($badges as $badge) {
                $video['badges'][] = $badge->text();
            }

            $video['id'] = $id[1];
            $video['title'] = $title;
            $video['thumbnail'] = "https://i.ytimg.com/vi/{$video['id']}/default.jpg";

            // Youtube's search will sometime add some featured videos
            // so make sure we have only unique videos
            $results['videos'][] = $video;
        }


        $pagination = $document->find('.search-pager .yt-uix-button');

        foreach ($pagination as $page) {
            $queryStr = $page->attr('href');

            if (empty($queryStr)) {
                $queryStr = $page->attr('data-redirect-url');
            }

            $queryStr = parse_url($queryStr, PHP_URL_QUERY);
            if (!$queryStr) {
                continue;
            }

            $queryStr = str_replace('&amp;', '&', $queryStr);
            parse_str($queryStr, $queryStr);
            if (isset($queryStr['sp'])) {
                $results['pages'][] = [
                    'label' => $page->text(),
                    'pageToken' => urldecode($queryStr['sp'])
                ];
            }
        }


        return $results;
    }

    /**
     * Fetch information about a single video
     *
     * @param  [type] $videoID
     * @return [type]
     */
    public function watch($videoID)
    {
        $http = $this->getHttpSession();
        // Change the user agent for this endpoint
        $http->options['useragent'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/75.0.3770.142 Safari/537.36';

        $body = $http->get("https://www.youtube.com/get_video_info?&video_id={$videoID}&asv=3&hl=en_US")->body;

        parse_str($body, $video);

        if (!isset($video['player_response'])) {
            throw new \LogicException("Invalid response from endpoint!");
        }

        $playerResponse = json_decode($video['player_response'], true);

        if (!isset($playerResponse['videoDetails'])) {
            throw new \LogicException("No videoDetails found in player_response!");
        }

        // A structure for our data
        $videoInfo = [
            'title' => null,
            'author' => null,
            'channelId' => null,
            'videoId' => null,
            'shortDescription' => null,
            'lengthSeconds' => 0,
            'viewCount' => 0,
            'keywords' => [],
            'averageRating' => 0,
        ];

        // Loop through and store the values if present
        foreach ($videoInfo as $key => $value) {
            if (isset($playerResponse['videoDetails'][$key])) {
                $videoInfo[$key] = $playerResponse['videoDetails'][$key];
            }
        }

        // Manually add the thumbnail
        $videoInfo['thumb'] = "https://i.ytimg.com/vi/{$videoID}/mqdefault.jpg";

        return $videoInfo;     
    }

    protected function request($host, array $params = [])
    {
        $defaults = [
            'client' => 'mv-google',
            'hl' => 'en',
            'gl' => 'US',
            'app' => 'desktop',
            'persist_app' => 1
        ];

        $params = array_merge($defaults, $params);

        $query = http_build_query($params);

        $endpoint = $host. "?{$query}";

        $http = $this::getHttpSession();
        $request = $http->get($endpoint);
        return new Document($request->body);
    }

    /**
     * Get HTTP session instance
     *
     * @return Requests_Session
     */
    public function getHttpSession()
    {
        if (!self::$session) {
            // Build a new session
            self::$session = new Requests_Session();

            // Look we're using an Old af Opera Mini on a Java device
            // please ignore us Google :3
            self::$session->options['useragent'] = 'Opera/9.80 (J2ME/MIDP; Opera Mini/9 (Compatible; MSIE:9.0; iPhone; BlackBerry9700; AppleWebKit/24.746; U; en) Presto/2.5.25 Version/10.54';
            self::$session->headers['Accept'] = 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8';
            self::$session->headers['Accept-Language'] = 'en-US,en;q=0.5';

            // Obvious
            self::$session->headers['Referer'] = "https://m.youtube.com/";
            self::$session->headers['Origin'] = "https://m.youtube.com/";

            // Reportedly Not works, still
            if (isset($_SERVER['REMOTE_ADDR'])) {
                self::$session->headers['X-Forwarded-For'] = $_SERVER['REMOTE_ADDR'];
            }

            // Custom HTTP headers
            if (is_array($this->options['headers'])) {
                self::$session->headers = array_merge(self::$session->headers, $this->options['headers']);
            }

            // Adjust timeout values
            self::$session->options['timeout'] = (int) $this->options['timeout'];
            self::$session->options['connect_timeout'] = (int) $this->options['timeout'];
            self::$session->options['follow_redirects'] = true;

            // Set proxy
            self::$session->options['proxy'] = $this->options['proxy'];

            // This will make us look like more of an asshole to YouTube :|
            self::$session->options['cookies'] = new Requests_Cookie_Jar();
        }

        return self::$session;
    }
}
