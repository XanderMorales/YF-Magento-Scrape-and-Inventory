<?php
/*
 * [* / 5  *  *  *  *  /opt/plesk/php/5.6/bin/php -d memory_limit=128M -f /var/www/vhosts/yardfreaks.com/httpdocs/DEV/inventory_command.php]
 *
 * this command line script is executed at root cron every 5 minutes.
 * if $run_file exist, the script will update inventory in csv file.
 * column a = sku, column b = inventory.... no header in csv.
 * if inventory is 0 - the script will take product out of stock.
 * after inventory update, email report is generated to inv@yardfreaks.com
 */

// set time zone
date_default_timezone_set('America/Los_Angeles');

// access from web browser.. a little security
$sapi_type = php_sapi_name();
if (substr($sapi_type, 0, 3) == 'cgi'){ echo "die"; exit;}

// Error Checking
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Load Magento
define('DOC_ROOT', '/var/www/vhosts/yardfreaks.com/httpdocs');
require_once DOC_ROOT . '/app/Mage.php';
Mage::app(Mage::app()->getStore()->getName()); // Load Magento
Mage::setIsDeveloperMode(true); // dev mode (true/false)

// global variables
$run_file = DOC_ROOT . '/var/yfteam/_INVENTORY_UPDATES/run.txt';
$csv_file = DOC_ROOT . '/var/yfteam/_INVENTORY_UPDATES/inventory.csv';
$email_report = '';

doRun();

function doRun()
{
    global $csv_file, $run_file;

    if(file_exists($run_file) && file_exists($csv_file))
    {
        unlink($run_file);
        getAndSetInventory();
        mailReport();
    }
}

function getAndSetInventory()
{
    global $csv_file;

    $file = fopen($csv_file, 'r');
    while (($line = fgetcsv($file)) !== FALSE)
    {
        #echo $line[0] . ' ... ' . $line[1] . "\n\n";
        setInventory($line[0],$line[1]);
    }
    fclose($file);
}

function setInventory($sku, $new_inventory)
{
    global $email_report;

    $product_id = Mage::getModel('catalog/product')->getIdBySku($sku);
    $stock_item = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product_id);
    if($stock_item->getId() > 0 and $stock_item->getManageStock())
    {
        $stock_item->setQty($new_inventory);
        if($new_inventory > 0) { $is_in_stock = 1; }
        else { $is_in_stock = 0; }
        $stock_item->setIsInStock($is_in_stock);
        $stock_item->save();
        $email_report .= "SKU updated: $sku \n";
    }
    else { $email_report .= "SKU not found: $sku \n"; }
}

function mailReport()
{
    global $email_report;

    $headers="From: alex@yardfreaks.com\r\nReply-To: alex@yardfreaks.com";
    mail('send@report-here.com','inventory report',$email_report,$headers);
}
