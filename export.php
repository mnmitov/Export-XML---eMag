<?php
require 'export.interface.php';
include_once 'config.php';
include_once 'database.php';
include_once 'helper.php';

class Export implements ExportableInterface
{
    private $db;
    private $categories;
    private $file;
    private $helper;
    private $type;

    public function __construct($db, $categories, $helper)
    {
        $this->db = $db;
        $this->categories = $categories;
        $this->helper = $helper;

        $this->getProducts();
    }


    public function getProducts()
    {
        foreach ($this->categories as $category => $type) {
            $this->category = $category;
            $this->type = $type;

            $this->file = 'xml/' . $category . '_digitbg_emag.xml';

            $sql =
                "SELECT p.product_id, p.status, p.quantity, p.model as part_num, p.price as price, p.ean, p.image,
                pd.name as product_name, pd.meta_title as name, pd.meta_description as meta_description, pd.description as description,
                m.name as manufacturer, 
                ccc.name as base_name,
                cd.name as parent_name,
                cdc.name as child_name,
                p.product_type
            FROM oc_product p
            LEFT JOIN oc_product_description pd     ON p.product_id = pd.product_id
            LEFT JOIN oc_manufacturer m             ON p.manufacturer_id = m.manufacturer_id
            LEFT JOIN oc_product_to_category ptc    ON p.product_id = ptc.product_id
            LEFT JOIN oc_category c                 ON ptc.category_id = c.category_id
            LEFT JOIN oc_category_description cd    ON c.parent_id = cd.category_id
            LEFT JOIN oc_category_description cdc   ON ptc.category_id = cdc.category_id
            LEFT JOIN oc_category cat               ON c.parent_id = cat.category_id
            LEFT JOIN oc_category_description ccc   ON cat.parent_id = ccc.category_id
            WHERE p.product_type = '$category' 
            AND pd.language_id = 1 
            AND cdc.language_id = 1 ";

            if ($type['haveEAN']) {
                $sql .= "AND p.ean NOT LIKE '%-%' ";
            }

            if (!$type['filter_language']) {
                $sql .= "AND cd.language_id = 1 AND ccc.language_id = 1 ";
            }

            if ($type['isNew']) {
                $sql .= "AND p.date_added > NOW() - INTERVAL 10 DAY ";
            }


            // echo "<pre>", print_r($sql), "</pre>";



            $results = $this->db->getData($sql);
            if ($results) {
                $this->addProducts($results);
            } else {
                $this->helper->setEmptyXmlFile($this->file);
                echo 'There are no new products to export for ' . $category;
                echo "<pre>", "Successfully generated file: " . "<a href='https://" . $_SERVER['SERVER_NAME'] . "/xml_export_emag/" . $this->file . "' target='_blank'>" . $this->file . "</a>" . ", File size: " . $this->helper->fileSizeConvert(filesize($this->file)), "</pre>";
                echo "<pre>", "<hr>", "</pre>";
            }
        }
    }



    public function addProducts($results)
    {
        $count = 0;
        $xml = new SimpleXMLElement('<Products/>');

        // echo "<pre>", print_r($results), "</pre>";

        foreach ($results as $data) {
            $product_id = $data['product_id'];

            $sql = "SELECT pimg.product_id, pa.attribute_id, pa.text as warranty, ps.price AS special_price, group_CONCAT(distinct image ORDER BY image SEPARATOR '|') AS images
            FROM oc_product_image pimg
            LEFT JOIN oc_product_attribute pa
            ON pa.product_id = $product_id AND pa.attribute_id = 45
            LEFT JOIN oc_product_special ps 
            ON pimg.product_id = ps.product_id AND ps.date_end > CURDATE() 
		    OR pimg.product_id = ps.product_id AND ps.date_end = '0000-00-00'
            WHERE pimg.product_id = $product_id";

            $query = $this->db->getData($sql);

            // echo "<pre>", print_r($query[0]['warranty']), "</pre>";

            $overcharge = $this->type['overcharge'];
            $overridePromo = $this->type['overridePromo'];
            $promo = $this->type['promo'];

            $product = $xml->addChild('product');
            $product->addChild('Category', $data['base_name'] . " > " . $data['parent_name'] . " > " . $data['child_name']);
            $product->addChild('Name', htmlspecialchars(substr($this->type['name'] . ' ' . $data['meta_description'], 0, 254)));
            if ($data['manufacturer'] != '') {
                $product->addChild('Brand', htmlspecialchars(substr($data['manufacturer'], 0, 254)));
            } else {
                $product->addChild('Brand', 'OEM');
            }
            $product->addChild('Product_ID', $data['product_id']);
            $product->addChild('Product_url', htmlspecialchars('https://digit.bg/index.php?route=product/product&product_id=' . $data['product_id']));
            $product->addChild('image_url_1', 'https://digit.bg/image/' . $data['image']);

            // echo "<pre>", print_r($imagesArray), "</pre>";
            $img_count = 2;
            $imagesArray = explode("|", $query[0]['images']);
            foreach ($imagesArray as $image) {
                if ($img_count < 6) {
                    $product->addChild('image_url_' . $img_count, 'https://digit.bg/image/' . $image);
                    $img_count++;
                }
            }

            if (!$overridePromo) {
                if ($this->helper->getSpecialPrice($query[0], $overcharge) != false) {
                    $product->addChild('net_price', $this->helper->getSpecialPrice($query[0], $overcharge));
                    $product->addChild('net_price_VAT', ($this->helper->getSpecialPrice($query[0], $overcharge) * 1.2));
                    $product->addChild('base_price', $this->helper->getPrice($data['price'], $overcharge));
                    $product->addChild('base_price_VAT', ($this->helper->getPrice($data['price'], $overcharge) * 1.2));
                    $product->addChild('digit_special_price', ($query[0]['special_price'] / 1.2));
                    $product->addChild('digit_special_price_VAT', $query[0]['special_price']);
                } else {
                    $product->addChild('net_price', $this->helper->getPrice($data['price'], $overcharge));
                    $product->addChild('net_price_VAT', ($this->helper->getPrice($data['price'], $overcharge) * 1.2));
                    $product->addChild('base_price', $this->helper->getPrice($data['price'], $overcharge));
                    $product->addChild('base_price_VAT', ($this->helper->getPrice($data['price'], $overcharge) * 1.2));
                }
            }

            if ($overridePromo) {
                $product->addChild('net_price', $this->helper->getPrice($data['price'], $overcharge));
                $product->addChild('net_price_VAT', ($this->helper->getPrice($data['price'], $overcharge) * 1.2));
            }

            $product->addChild('digit_price', ($data['price']) / 1.2);
            $product->addChild('digit_price_vat', $data['price']);

            $product->addChild('on_stock', $data['quantity']);
            $product->addChild('status', $this->helper->isOnStock($data['status']));
            if ($this->categories[$data['product_type']]['removeTableTag']) {
                $regex = '/<table id="productDescription">.*?<\/table>/s';
                $replacement = '';
                $res = preg_replace($regex, $replacement, html_entity_decode($data['description']));
                $product->addChild('Description', htmlspecialchars($res));
            }
            if (!$this->categories[$data['product_type']]['removeTableTag']) {
                $product->addChild('Description', htmlspecialchars($data['description']));
            }
            $product->addChild('Code_of_product', $data['part_num']);
            $product->addChild('VAT', '20%');
            $product->addChild('Handling_time', 3);

            // echo "<pre>", print_r($query['text']), "</pre>";

            if (isset($query[0]['warranty'])) {
                $product->addChild('Warranty', $query[0]['warranty']);
            }

            // if ($data['ean'] == '-') {
            //     $product->addChild('EAN_code', 9999999999999);
            // } else {
            $product->addChild('EAN_code', $data['ean']);
            // }

            $count++;
        }

        $xml->asXML($this->file);

        if ($xml) {
            echo "<pre>", "<a href='https://marketplace.emag.bg/dashboard'>" . "XML EXPORT FOR eMAG Marketplace - EXPORT NEW PRODUCTS" . "</a>", "</pre>";
            echo "<pre>", "Successfully generated file: " . "<a href='https://" . $_SERVER['SERVER_NAME'] . "/xml_export_emag/" . $this->file . "' target='_blank'>" . $this->file . "</a>" . ", File size: " . $this->helper->fileSizeConvert(filesize($this->file)), "</pre>";
            echo "<pre>", "Number of products: " . $count, "</pre>";
            echo "<pre>", "XML Created on: " . date('D, d M Y H:i:s'), "</pre>";
            echo "<pre>", "Last update (export.php): " . date('d M Y H:i:s', filectime('export.php')), "</pre>";
            echo "<pre>", "<hr>", "</pre>";
        } else {
            throw new Error('Big problem...');
        }
    }
}

$db = new Database($host, $db_name, $db_username, $db_password, $charset);
$helper = new Helper();
$export = new Export($db, $categories, $helper);
