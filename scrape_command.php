<?php

exit();

// set time zone
date_default_timezone_set('America/Los_Angeles');

// access from web browser.. a little security
$sapi_type = php_sapi_name();
if (substr($sapi_type, 0, 3) == 'cgi'){ exit;}

// Error Checking
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Load Magento
define('DOC_ROOT', '/var/www/vhosts/yardfreaks.com/httpdocs');
require_once DOC_ROOT . '/app/Mage.php';
Mage::app(Mage::app()->getStore()->getName()); // Load Magento
Mage::setIsDeveloperMode(true); // dev mode (true/false)

register_shutdown_function('shutdown');

/**
 * Manually set variable
 */
$ip_start = 0;
$ip_length = 0;
$my_ips = array('50.97.234.170','50.97.225.2','50.97.225.3','50.97.224.9');
$cat_id_array = array('3','4','5','6','7','8','9','10','11','12','13','14','15','16','17','18','19','21','23','24','25','26','27','28','29','30','31','32','33','34','35','36','37','38','39','40','41','42','43','44','45','46','47','48','49','50','51','52','53','54','55','56','57','58','59','60','61','62','63','64','65','66','67','68','69','70','71','72','73','74','75','76','77','78','79','80','81','82','83','84','85','86','87','88','89','90','91','92','93','94','95','96','97','98','99','100','101','102','103','104','105','106','107','108','109','110','111','112','113','114','115','116','117','118','119','120','121','122','123','124','125','126','127','128','129','130','131','132','133','134','135','136','137','138','139','140','141','142','143','144','145','146','147','148','149','150','151','152','153','154','155','156','157','158','159','160','161','162','163','164','165','166','168','169','170','171','172','173','174','175','176','177','178');
// $cat_id_array = array('8','63','64','65','66','67','71','72','73','74','162','187');
// $cat_id_array = array('124','34','125','127','128','129','37','38','40','41','158','164'); // decor
// $cat_id_array = array('3','166','13','14','15','16','17','18','19','21','23','24','25'); // furniture

foreach($cat_id_array as $i)
{
    $cat_id = $i;
    $product_ids = getProductIdsInCat($cat_id);
}

print_r($product_ids);

echo 'DONE' . "\n";



/**
 * @param $cat_id
 * @return mixed
 */
function getProductIdsInCat($cat_id)
{
    $category = Mage::getModel('catalog/category')->load($cat_id); /// this is the category
    // $collection = Mage::getResourceModel('catalog/product_collection')->addAttributeToSelect(array('upc','*'))->addCategoryFilter($category); //setPageSize(100)->
    $collection = Mage::getResourceModel('catalog/product_collection')->addAttributeToSelect('*')->addCategoryFilter($category); //setPageSize(100)->
    $google_scraped_price = '';
    foreach($collection as $item)
    {
        $gsp = Mage::getModel('catalog/product')->load($item->getId())->getGoogle_shopping_price();
        $upc = Mage::getModel('catalog/product')->load($item->getId())->getUpc();

        $check_upc = isValidBarcode($upc);

        if ($check_upc)
        {
            $google_scraped_price = $gsp;
            if($gsp < 1)
            {
                //echo gmdate('D, d M Y H:i:s T', time()) . "\n";
                echo date('F j, Y, g:i:s a',strtotime("-7 hour")) . "\n";
                echo "Searching for UPC code: $upc  \n";
                $google_scraped_price = scrapeGoogleShopping($upc);
                if($google_scraped_price != 'PRICE NOT FOUND'){
                    echo 'Setting google price to: ' . $google_scraped_price . "\n\n";
                    setGooglePrice($item->getId(), $google_scraped_price);
                }
                else{
                    setGooglePrice($item->getId(), '1');
                    echo 'Price not found: Setting to 1 and skipping...' . "\n\n";
                }
            }
        }
        if($google_scraped_price != '') {
            $product_ids[$item->getId()] = array($upc, $item->getUrlPath(), $category->getName(), $item->getName(), $item->getPrice(), $item->getCost(), $item->getMsrp(), $google_scraped_price);
        }
    }
    if($google_scraped_price != '') { return $product_ids; }
}

/**
 * @param $item_id
 * @param $comp_price
 */
function setGooglePrice($item_id, $comp_price)
{
    $product =  Mage::getModel('catalog/product')->load($item_id);
    $product->setGoogleShoppingPrice($comp_price);
    $product->getResource()->saveAttribute($product, 'google_shopping_price');
}

/**
 * @param $upc
 * @return bool|mixed|string
 */
function scrapeGoogleShopping($upc)
{
    libxml_use_internal_errors(true);

    $urls = array(
        "https://www.google.com/search?output=search&tbm=shop&q=$upc",
        "https://www.google.com/search?hl=en&output=search&tbm=shop&q=$upc&btnG=",
        "https://www.google.com/search?hl=en&output=search&tbm=shop&q=$upc&oq=$upc&gs_l=products-cc.3...4619.4946.0.5107.4.3.0.0.0.0.58.164.3.3.0....0...1ac.1.64.products-cc..1.2.110...0.YLBtDZ_O4A0#hl=en&tbm=shop&q=$upc&*",
        "https://www.google.com/search?tbm=shop&btnG=Search&q=$upc",
        "https://www.google.com/search?tbm=shop&q=$upc&oq=$upc&gs_l=serp.12...384956.385522.0.386036.3.3.0.0.0.0.89.191.3.3.0....0...1c.1.64.serp..0.0.0.TI4aVam32g4"
    );

    $url = $urls[array_rand($urls)];
    echo 'Fetch URL:  ' . $url . "\n";

    $html = get_url_contents($url);

    if($html != 'ERROR')
    {
        $dom = new DOMDocument();
        $dom->loadHTML($html);

        $xpath  = new DomXPath($dom);
        $nodes = $xpath->query('//div[@id="search"]');

        $value = $nodes->item(0)->getElementsByTagName("div")->item(0)->nodeValue;
        $value = substr($value, 0, 9);
        $value = preg_replace("/[^0-9,.]/", "", $value);
        if($value != '') { return $value; }
        else { return 'PRICE NOT FOUND'; }
    }
    else { return 'PRICE NOT FOUND'; }
}

/**
 * @param $url
 * @return mixed
 */
function get_url_contents($url)
{
    global $ip_start, $ip_length, $my_ips;

    $sleepInt = array(3,4,5,6,7,8,8,10,11,12);
    $argInt = $sleepInt[array_rand($sleepInt)];
    echo 'Request url in: ' . $argInt . ' seconds...' . "\n";
    sleep($argInt);

    $agents[] = "Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 6.0; WOW64; SLCC1; .NET CLR 2.0.50727; .NET CLR 3.0.04506; Media Center PC 5.0)";
    $agents[] = "Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.0)";
    $agents[] = "Mozilla/5.0 (Windows; U; Windows NT 6.0; en-US; rv:1.9.0.5) Gecko/2008120122 Firefox/3.0.5?";
    $agents[] = "Mozilla/5.0 (X11; U; Linux i686 (x86_64); en-US; rv:1.8.1.18) Gecko/20081203 Firefox/2.0.0.18";
    $agents[] = "Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.8.1.16) Gecko/20080702 Firefox/2.0.0.16";
    $agents[] = "Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_5_6; en-us) AppleWebKit/525.27.1 (KHTML, like Gecko) Version/3.2.1 Safari/525.27.1";

    $ch = curl_init();

    // mix up outgoing ip

    $ip_length = count($my_ips) - 1;

    // $ip_request = $my_ips[array_rand($my_ips)];
    $ip_request =  $my_ips[$ip_start];

    echo 'Using IP to Fetch Url: ' . $ip_request . "\n";

    curl_setopt($ch, CURLOPT_INTERFACE, "$ip_request");

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    curl_setopt($ch, CURLOPT_COOKIESESSION, 1); // added for yahoo
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // added for yahoo
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // added for yahoo

    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, $agents[rand(0,(count($agents)-1))]); // I'm a browser too!

    $result = curl_exec($ch);

    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    echo 'HTTP/1.1: Status Code OK: ' . $httpcode . "\n";

    curl_close($ch);

    if($httpcode == '200')
    {
        $result = str_replace("\n", "", $result); // remove new lines
        $result = str_replace("\r", "", $result); // remove carriage returns
    }
    else
    {
        unset($my_ips[$ip_start]);
        echo 'Disable IP: ' . $my_ips[$ip_start] . "\n";
        $result = 'ERROR';
    }

    $ip_start ++;
    if ($ip_start > $ip_length) { $ip_start = 0; }

    return $result;
}

/**
 *
 */
function isValidBarcode($barcode) {
    //checks validity of: GTIN-8, GTIN-12, GTIN-13, GTIN-14, GSIN, SSCC
    //see: http://www.gs1.org/how-calculate-check-digit-manually
    $barcode = (string) $barcode;
    //we accept only digits
    if (!preg_match("/^[0-9]+$/", $barcode)) {
        return false;
    }
    //check valid lengths:
    $l = strlen($barcode);
    if(!in_array($l, [8,12,13,14,17,18]))
        return false;
    //get check digit
    $check = substr($barcode, -1);
    $barcode = substr($barcode, 0, -1);
    $sum_even = $sum_odd = 0;
    $even = true;
    while(strlen($barcode)>0) {
        $digit = substr($barcode, -1);
        if($even)
            $sum_even += 3 * $digit;
        else
            $sum_odd += $digit;
        $even = !$even;
        $barcode = substr($barcode, 0, -1);
    }
    $sum = $sum_even + $sum_odd;
    $sum_rounded_up = ceil($sum/10) * 10;
    return ($check == ($sum_rounded_up - $sum));
}
/**
 *
 */
function shutdown()
{
    $command = '/opt/plesk/php/5.6/bin/php -d memory_limit=128M /var/www/vhosts/yardfreaks.com/httpdocs/DEV/scrape_command.php >> /var/www/vhosts/yardfreaks.com/httpdocs/DEV/data.txt &';
    echo "\n\n\n Running again after error....\n";
    echo $command . "\n\n\n";
    system($command);
}

