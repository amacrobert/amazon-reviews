<?php
/**
 * Find all 5-star reviews of an Amazon product
 * 
 * HTTP request: POST /amazon-reviews/index.php
 * Parameters:
 *   name: amazon_url
 *   value: string
 *   description: a link to the main product page of an Amazon product.
 *     Example: http://www.amazon.com/dp/B00ATL6OOG
 */

if (empty($_POST['amazon_url'])) {
  exit(json_encode(array(
    'code' => 0,
    'message' => 'amazon_url not specified in POST body',
  )));
}
else {
  $product_url = $_POST['amazon_url'];
}

echo amazon_reviews($product_url);

/**
 * Finds all 5-star reviews for an Amazon product given the product's URL
 *
 * @param string $url
 *   The URL of an Amazon product
 * @return JSON
 *   A JSON-encoded list of reviews
 */
function amazon_reviews($product_url) {
  $product_dom = @DOMDocument::loadHTMLFile($product_url);

  // Unable to load file
  if (!$product_dom) {
    return json_encode(array(
      'code' => 0,
      'message' => 'Unable to load page at ' . $product_url,
    ));
  }

  $xpath = new DOMXpath($product_dom);
  $all_reviews_dom = $xpath->query('//*[@id="revF"]/div/a[@href]');

  if ($all_reviews_dom->length == 0) {
    return json_encode(array(
      'code' => 0,
      'message' => 'Unable to find link to reviews',
    ));
  }

  // Load the first page of reviews using the "See all X reviews" link
  $all_reviews_url = $all_reviews_dom->item(0)->getAttribute('href');
  $reviews_list = array();
  $reviews_dom = @DOMDocument::loadHTMLFile($all_reviews_url);

  // Step through each page of reviews, collecting 5-star reviews
  $reviews_list = array();
  while (($next_page_url = get_reviews_from_dom($reviews_dom, $reviews_list)) !== FALSE) {
    $reviews_dom = @DOMDocument::loadHTMLFile($next_page_url);
  }
  
  return json_encode(array(
    'code' => 200,
    'message' => 'Success',
    'reviews' => $reviews_list
  ));
}

/**
 * Finds all 5-star reviews on a page and the URL of the next page of reviews
 *
 * @param DOMDocument $dom
 *   The DOM of a page of reviews
 * @param array $reviews_list
 *   An array to fill with reviews
 * @return string
 *   The link to the next page of reviews, or FALSE if $dom is the last one
 */
function get_reviews_from_dom($dom, &$reviews_list) {
  $xpath = new DOMXpath($dom);

  // Get all 5-star ratings in $dom and collect their title, date, author, and body
  $five_star_reviews = $xpath->query('//table[@id="productReviews"]//span[@title="5.0 out of 5 stars"]/../../..');

  foreach ($five_star_reviews as $review) {
    // Test for extra children that offset the location of review information
    $extra_divs = 0;
    if (strstr($review->childNodes->item(1)->nodeValue, 'people found the following review helpful') !== FALSE) {
      $extra_divs = 2;
    }

    $reviews_list[] = array(
      'title'  => $review->childNodes->item(1 + $extra_divs)->childNodes->item(3)->childNodes->item(0)->textContent,
      'date'   => $review->childNodes->item(1 + $extra_divs)->childNodes->item(3)->childNodes->item(2)->textContent,
      'author' => $review->childNodes->item(3 + $extra_divs)->childNodes->item(1)->childNodes->item(1)->childNodes->item(0)->textContent,
      'body'   => $review->childNodes->item(9 + $extra_divs)->textContent,
    );
  }

  // Return the link to the next page of reviews, or FALSE if we're on the last page
  $next_links = $xpath->query('//span[@class="paging"]/a[last()]');
  if ($next_links->length == 0) {
    $next_page_url = FALSE;
  }
  elseif (strstr($next_links->item(0)->textContent, 'Next') !== FALSE) {
    $next_page_url = $next_links->item(0)->getAttribute('href');
  }
  else {
    $next_page_url = FALSE;
  }

  return $next_page_url;
}
