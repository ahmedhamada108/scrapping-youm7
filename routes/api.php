<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpClient\HttpClient;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/news_categories', function (Request $request) {

    $client = HttpClient::create();
    $response = $client->request('GET', 'https://www.youm7.com');
    $html = $response->getContent();

    $crawler = new Crawler($html);
    $mainLinks = $crawler->filter('body > header > div.row.marigin0.headerNewNew > div > nav > div > ul > li > a');
    $subLinks = $crawler->filter('body > header > div.row.marigin0.headerNewNew > div > nav > div > ul > li > ul > li > a');

    $results = [];

    $mainLinks->each(function (Crawler $node, $i) use (&$results) {
        $results[] = [
            'id' => $i + 1,
            'title' => $node->filter('h2')->text(),
            'href' => $node->attr('href'),
        ];
    });

    $subLinks->each(function (Crawler $node, $i) use (&$results) {
        $results[] = [
            'id' => $i + 1,
            'title' => $node->filter('h2')->text(),
            'href' => $node->attr('href'),
        ];
    });

    // Output the extracted data
    $cachedData = collect($results);
    Cache::put('extracted_data_categories', $results, now()->addHours(1));

    // Retrieve the cached data
    $cachedData = Cache::get('extracted_data_categories');
    return $cachedData;
});


Route::get('/news_categories/{category_id}', function ($category_id) {

    if(isset($category_id)){
        // Find the href by title
        $cachedData = Cache::get('extracted_data_categories');

        $id = $category_id; // The title you want to query
        $foundItems = array_filter($cachedData, function ($item) use ($id) {
            return $item["id"] == $id;
        });
        $firstFoundItem = reset($foundItems);
        $sectionURL = 'https://www.youm7.com'.$firstFoundItem['href'];



        $client = HttpClient::create();
        $response = $client->request('GET', $sectionURL);
        $html = $response->getContent();

        $crawler = new Crawler($html);
        $mainNews = $crawler->filter('#paging > .bigOneSec');
        $results_news = [];

        $mainNews->each(function (Crawler $node, $i) use (&$results_news) {
            $results_news[] = [
                'id' => $i + 1,
                'title' => $node->filter('div > h3 > a')->text(),
                'image' => $node->filter('a > img')->attr('src'),
                'date' => $node->filter('div > span')->text(),
                'description' => $node->filter('div > p')->text(),
                'href' => $node->filter('div > h3 > a')->attr('href')
            ];
        });

        Cache::put('extracted_data_'.$category_id, $results_news, now()->addHours(1));
        // Retrieve the cached data
        $categoryResultsCached = Cache::get('extracted_data_'.$category_id);
        return $categoryResultsCached;
    }
});

Route::get('/news_categories/{category_id}/{news_id}', function ($category_id, $news_id) {

        if($news_id){
            $categoryResultsCached = Cache::get('extracted_data_'.$category_id);
            // Find the href by title
            $id = $news_id ; // The title you want to query
            // $single_news = $categoryResultsCached->get($id);
            $foundNews = array_filter($categoryResultsCached, function ($item) use ($id) {
                return $item["id"] == $id;
            });
            $firstFoundNews = reset($foundNews);
            $NewsURL = 'https://www.youm7.com'.$firstFoundNews['href'];
            // return $NewsURL;
    
    
            $client = HttpClient::create();
            $response = $client->request('GET', $NewsURL);
            $html = $response->getContent();
    
            $crawler = new Crawler($html);
            $MainArticle = $crawler->filter('#divcont > article');
            $results_article = [];
    
            $MainArticle->each(function (Crawler $node, $i) use (&$results_article) {
                $description = $node->filter('#articleBody > div, p')->each(function ($div) {
                    return $div->text();
                });
                
                $description = implode("<br>", $description);
                $results_article[] = [
                    'id' => $i + 1,
                    'title' => $node->filter('.articleHeader > h1')->text(),
                    'image' => $node->filter('.img-cont > img')->attr('src'),
                    'date' => $node->filter('.articleHeader > span')->text(),
                    'description' => $description,
                    // 'href' => $node->filter('div > h3 > a')->attr('href')
                ];
            });
    
            return $results_article;
        }
});

