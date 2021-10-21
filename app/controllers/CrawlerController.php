<?php

use Phalcon\Http\Request;
use Phalcon\Mvc\Controller;
use Phalcon\Mvc\View;
use Phalcon\Http\Message\Uri;
use GuzzleHttp\TransferStats;
use GuzzleHttp\Client as GuzzleClient;

class CrawlerController extends Controller
{
    /**
     * Method to crawl Agency Analytics
     */
    public function indexAction()
    {
        //Initialize our guzzle client and our starting URI
        $httpClient = new GuzzleClient();
        $startingUri = "http://agencyanalytics.com";

        //Initialize the metrics we need
        $numPagesCrawled = 0;
        $numUniqueImages = 0;
        $numUniqueInternalLinks = 0;
        $numUniqueExternalLinks = 0;
        $pageLoadTimes = [];
        $wordCount = 0;
        $titleLength = 0;
        $pagesCrawled = [];
        $success = true;
        
        //Generate a random number between 1 to 5 which will represents how many
        //pages we are going to crawl
        $numOfPagestoCrawl = mt_rand(1,5);
        $pagesToCrawl = [];

        //Using guzzle client load the home page and try to get all of the links
        //by doing an xpath query of anchor tags
        //if we encounter any issue, record the error and return right away
        try{
            $response = $httpClient->request('GET', $startingUri);
            if ($response->getStatusCode() == 200) {
                $startingUriContents = $response->getBody()->getContents();
                $dom = new DOMDocument;
                $dom->loadHTML($startingUriContents);
                $xpath = new DOMXPath($dom);
                $pages = $xpath->query("//a/@href");
            }
        } catch (\Exception $error) {
            $this->view->success = false;
            $this->view->error = $error->getMessage();
            return;
        }

        //based on the generated pages to crawl, loop and check if the anchor is internal or external
        //we only care for internal link, so we are going to grab that add it on our list
        $i = 0;
        while ($i < $numOfPagestoCrawl) {
            $randomizeLinkIndex = mt_rand(0, sizeof($pages)-1);
            if (is_null(parse_url($pages[$randomizeLinkIndex]->textContent, PHP_URL_HOST))) {
                $pagesToCrawl[] = $pages[$randomizeLinkIndex]->textContent;
                $i++;
            }
        }

        //based on the generated list, loop and process each page
        for ($i = 0; $i < sizeof($pagesToCrawl); $i++) {
            try{
                $response = $httpClient->request('GET', $startingUri . $pagesToCrawl[$i], [
                    'on_stats' => function (TransferStats $stats) use (&$pageLoadTimes) {
                        // check if we have a response before adding the transfer time to the pageLoadTimes
                        if ($stats->hasResponse()) {
                            array_push($pageLoadTimes, $stats->getTransferTime());
                        }
                    }
                ]);

                //if we have 200 status code, it means we have successfully fetch the data, then begin processsing the data
                if ($response->getStatusCode() == 200) {
                    $contents = $response->getBody()->getContents();
                    $dom = new DOMDocument;
                    $dom->loadHTML($contents);
                    $xpath = new DOMXPath($dom);

                    //grab all of the image by querying img tag and checking it src for uniqueness
                    $imgs = $xpath->query("//img/@src");
                    $images = [];
                    foreach($imgs as $img) {
                        $images[] = $img->textContent;
                    }

                    //grab all of the image by querying img tag and checking it data-src for uniqueness
                    $imgs = $xpath->query("//img/@data-src");
                    $images = [];
                    foreach($imgs as $img) {
                        $images[] = $img->textContent;
                    }
                    //check if we have any duplicates and remove them and add them to the total count of images
                    $numUniqueImages = $numUniqueImages + sizeof(array_values(array_unique($images)));

                    //grab all of the links by querying the anchor tag and checking its href
                    $anchors = $xpath->query("//a/@href");
                    $internalLinks = [];
                    $externalLinks = [];
                    //loop on each anchor and check if its an internal one by parsing it and check if host value is null
                    // otherwise its an external link
                    foreach($anchors as $anchor) {
                        if (is_null(parse_url($anchor->textContent, PHP_URL_HOST))) {
                            $internalLinks[] = $anchor->textContent;
                        } else {
                            $externalLinks[] = $anchor->textContent;
                        }
                    }
                    //remove all of the duplicate links and add them to the total count
                    $internalLinks = array_values(array_unique($internalLinks));
                    $externalLinks = array_values(array_unique($externalLinks));
                    $numUniqueInternalLinks = $numUniqueInternalLinks + sizeof($internalLinks);
                    $numUniqueExternalLinks = $numUniqueExternalLinks + sizeof($externalLinks);
                    
                    //get the word contents count by stripping out the script tags, followed by stripping the body tags
                    //and performing a word count.
                    $sanitizedContents = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', "", $contents);
                    $wordCount = $wordCount + str_word_count(strip_tags($sanitizedContents));
                    
                    //for title since we can assume there is always going to be one title element,
                    //then we can just perform a straight query and grab the first one and perform word count
                    $titleLength = $titleLength + str_word_count($xpath->query("//title")[0]->textContent);
                }
                //last, add the page and its status code that we just crawled to an array and increase the count
                $pagesCrawled[] = array("page" => $startingUri . $pagesToCrawl[$i], "statusCode" => $response->getStatusCode());
                $numPagesCrawled++;


            } catch (\Exception $error) {
                continue;
            }
        }

        //setup all of the variable we are going to return to the view
        $this->view->numPagesCrawled = $numPagesCrawled;
        $this->view->numUniqueImages = $numUniqueImages;
        $this->view->numUniqueInternalLinks = $numUniqueInternalLinks;
        $this->view->numUniqueExternalLinks = $numUniqueExternalLinks;
        $this->view->avgPageLoadTimes = array_sum($pageLoadTimes)/$numOfPagestoCrawl;
        $this->view->avgWordCount = (float) $wordCount/$numOfPagestoCrawl;
        $this->view->avgTitleLength = (float) $titleLength/$numOfPagestoCrawl;
        $this->view->pagesCrawled = $pagesCrawled;
        $this->view->success = $success;
    }
}
