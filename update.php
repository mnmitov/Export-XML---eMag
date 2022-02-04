<?php
require 'update.interface.php';
include_once 'config.php';
include_once 'database.php';
include_once 'helper.php';

class Update implements UpdatebleProductsInterface
{
    private $db;
    private $categories;
    private $helper;

    public function __construct($db, $categories, $helper)
    {
        $this->db = $db;
        $this->categories = $categories;
        $this->helper = $helper;


        $this->updateProducts();
    }


    public function updateProducts()
    {

        $rows_count = "SELECT MAX(product_id) as prod_id FROM oc_product";
        $rows = $this->db->getData($rows_count);

        $sqlUpdates = "SELECT p.product_id, p.model as part_num, p.price, `status`, p.quantity, p.product_type, ps.price as special_price, ps.date_start, ps.date_end
        FROM oc_product p
        LEFT JOIN oc_product_special ps 
        ON p.product_id = ps.product_id AND ps.date_end > CURDATE() 
		OR p.product_id = ps.product_id AND ps.date_end = '0000-00-00'
        WHERE ";
        $isFirst = false;
        foreach ($this->categories as $key => $value) {
            if ($isFirst) {
                $sqlUpdates .= " OR ";
            }
            $sqlUpdates .= "product_type = '" . $key . "'";
            $isFirst = true;
        }
        $sqlUpdates .= ";";


        $updates = $this->db->getData($sqlUpdates);

        // echo "<pre>", print_r($updates), "</pre>";

        $ranges = range(0, $rows[0]['prod_id']);

        $xml = new SimpleXMLElement('<Products/>');

        $countIn = 0;
        foreach ($updates as $update) {
            if (array_key_exists($update['product_id'], $ranges)) {
                $overcharge = $this->categories[$update['product_type']]['overcharge'];
                $overridePromo = $this->categories[$update['product_type']]['overridePromo'];
                $promo = $this->categories[$update['product_type']]['promo'];

                $product = $xml->addChild('Product');
                $product->addChild('product_ID', $update['product_id']);
                

                if (!$overridePromo) {
                    if ($this->helper->getSpecialPrice($update, $overcharge) != false) {
                        $product->addChild('net_price', $this->helper->getSpecialPrice($update, $overcharge));
                        $product->addChild('net_price_VAT', ($this->helper->getSpecialPrice($update, $overcharge) * 1.2));
                        $product->addChild('base_price', $this->helper->getPrice($update['price'], $overcharge));
                        $product->addChild('base_price_VAT', ($this->helper->getPrice($update['price'], $overcharge) * 1.2));
                        $product->addChild('digit_special_price', ($update['special_price'] / 1.2));
                        $product->addChild('digit_special_price_VAT', $update['special_price']);
                    } else {
                        $product->addChild('net_price', $this->helper->getPrice($update['price'], $overcharge));
                        $product->addChild('net_price_VAT', ($this->helper->getPrice($update['price'], $overcharge) * 1.2));
                        $product->addChild('base_price', $this->helper->getPrice($update['price'], $overcharge));
                        $product->addChild('base_price_VAT', ($this->helper->getPrice($update['price'], $overcharge) * 1.2));
                    }
                }

                if ($overridePromo) {
                    $product->addChild('net_price', $this->helper->getPrice($update['price'], $overcharge));
                    $product->addChild('net_price_VAT', ($this->helper->getPrice($update['price'], $overcharge) * 1.2));
                }

                $product->addChild('digit_price', ($update['price']) / 1.2);
                $product->addChild('digit_price_vat', $update['price']);
                $product->addChild('on_stock', $update['quantity']);
                $product->addChild('status', $this->helper->isOnStock($update['quantity']));
                $product->addChild('handling_time', 3);
                $product->addChild('product_number', $update['part_num']);
                unset($ranges[$update['product_id']]);
                $countIn++;
                continue;
            }
        }

        $countOut = 0;
        foreach ($ranges as $range) {
            $product = $xml->addChild('Product');
            $product->addChild('product_ID', $range);
            $product->addChild('net_price', null);
            $product->addChild('base_price', null);
            $product->addChild('on_stock', 0);
            $product->addChild('status', 0);
            $product->addChild('handling_time', 0);
            $countOut++;
        }

        $file_update = 'xml/Update_digitbg_emag.xml';
        $xml->asXML($file_update);

        // echo "<pre>", print_r($xml), "</pre>";

        if ($xml) {
            echo "<pre>", "<a href='https://marketplace.emag.bg/dashboard'>" . "XML EXPORT FOR eMAG Marketplace - UPDATE" . "</a>", "</pre>";
            echo "<pre>", "Successfully generated file: " . "<a href='https://" . $_SERVER['SERVER_NAME'] . "/xml_export_emag/" . $file_update . "' target='_blank'>" . $file_update . "</a>" . ", File size: " . $this->helper->fileSizeConvert(filesize($file_update)), "</pre>";
            echo "<pre>", "Number of products: " . $countIn, "</pre>";
            echo "<pre>", "Number of empty products: " . $countOut, "</pre>";
            $totalCount = $countIn + $countOut;
            echo "<pre>", "Number of products (TOTAL): " . $totalCount, "</pre>";
            echo "<pre>", "Number of products (TOTAL by SQL MAX): " . $rows[0]['prod_id'], "</pre>";
            echo "<pre>", "XML Created on: " . date('D, d M Y H:i:s'), "</pre>";
            echo "<pre>", "Last update (update.php): " . date('d M Y H:i:s', filectime('export.php')), "</pre>";
            echo "<pre>", "<hr>", "</pre>";
        } else {
            throw new Error('Big problem...');
        }
    }
}


$db = new Database($host, $db_name, $db_username, $db_password, $charset);
$helper = new Helper();
$update = new Update($db, $categories, $helper);
