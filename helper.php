<?php


class Helper
{
    public function isOnStock($quantity)
    {
        if ($quantity > 0) {
            $status = 1;
            return $status;
        }

        if ($quantity == 0) {
            $status = 0;
            return $status;
        }
    }

    public function setEmptyXmlFile($file_name)
    {
        $xml = new SimpleXMLElement('<Products/>');
        $xml->asXML($file_name);
    }


    public function getPrice($net_price, $overcharge) // return price without VAT
    {
        $price_excl_vat = $net_price / 1.2;
        $priceWithOverchargeWithoutVat = $price_excl_vat * $overcharge;
        $overchargeWithVAT = ($priceWithOverchargeWithoutVat - $price_excl_vat) * 1.2;
        return round($price_excl_vat + $overchargeWithVAT, 2);
    }


    public function getSpecialPrice($update, $overcharge) // return price without VAT
    {
        if (!empty($update['special_price'])) {
            $specialPrice_excl_vat = $update['special_price'] / 1.2;
            $priceWithOverchargeWithoutVat = $specialPrice_excl_vat * $overcharge;
            $overchargeWithVAT = ($priceWithOverchargeWithoutVat - $specialPrice_excl_vat) * 1.2;
            return round($specialPrice_excl_vat + $overchargeWithVAT, 2);
        } else {
            return false;
        }
    }


    public function fileSizeConvert($bytes)
    {
        $bytes = floatval($bytes);
        $arBytes = array(
            0 => array(
                "UNIT" => "TB",
                "VALUE" => pow(1024, 4)
            ),
            1 => array(
                "UNIT" => "GB",
                "VALUE" => pow(1024, 3)
            ),
            2 => array(
                "UNIT" => "MB",
                "VALUE" => pow(1024, 2)
            ),
            3 => array(
                "UNIT" => "KB",
                "VALUE" => 1024
            ),
            4 => array(
                "UNIT" => "B",
                "VALUE" => 1
            ),
        );

        foreach ($arBytes as $arItem) {
            if ($bytes >= $arItem["VALUE"]) {
                $result = $bytes / $arItem["VALUE"];
                $result = str_replace(".", ",", strval(round($result, 2))) . " " . $arItem["UNIT"];
                break;
            }
        }
        return $result;
    }
}
