<?
require '../scat.php';

$vendor_id= (int)$_REQUEST['vendor'];

if (!$vendor_id)
  die_jsonp("No vendor specified.");

$fn= $_FILES['src']['tmp_name'];

if (!$fn)
  die_jsonp("No file uploaded");

ob_start();

$q= "CREATE TEMPORARY TABLE macitem (
  item_no VARCHAR(32),
  sku VARCHAR(10),
  name VARCHAR(255),
  retail_price DECIMAL(9,2),
  net_price DECIMAL(9,2),
  barcode VARCHAR(32),
  purchase_quantity INT,
  category VARCHAR(64))";

$db->query($q)
  or die_query($db, $q);

$base= basename($_FILES['src']['name'], '.zip');

$q= "LOAD DATA LOCAL INFILE 'zip://$fn#$base.txt'
          INTO TABLE macitem
        FIELDS TERMINATED BY '\t'
        IGNORE 1 LINES
        (@changed, @change_date, item_no, sku, name, @unit_of_sale,
         retail_price, net_price, @customer, @product_code_type,
         barcode, @reno, @elgin, @atl, @catalog_code,
         @purchase_unit, purchase_quantity,
         @customer_item_no, @pending_msrp, @pending_date, @pending_net,
         @promo_price, @promo_name,
         @abc_flag, @vendor, @group_code, category)";

$r= $db->query($q)
  or die_query($db, $q);

$q= "DELETE FROM vendor_item WHERE vendor = $vendor_id";

$r= $db->query($q)
  or die_query($db, $q);

$q= "INSERT INTO vendor_item
            (vendor, item, code, name, retail_price, net_price,
             barcode, purchase_quantity, category)
     SELECT
            $vendor_id AS vendor,
            0 AS item,
            item_no AS code,
            name,
            retail_price,
            net_price,
            REPLACE(REPLACE(barcode, 'E-', ''), 'U-', '') AS barcode,
            purchase_quantity,
            category
       FROM macitem";

$r= $db->query($q)
  or die_query($db, $q);

// Find by code/item_no
$q= "UPDATE vendor_item
        SET item = IFNULL((SELECT id FROM item
                            WHERE vendor_item.code = item.code),
                          0)
     WHERE vendor = $vendor_id AND item = 0";
$r= $db->query($q)
  or die_query($db, $q);

// Find by barcode
$q= "UPDATE vendor_item
        SET item = (SELECT item FROM barcode
                     WHERE barcode.code = barcode
                     LIMIT 1)
     WHERE vendor = $vendor_id AND item = 0";
$r= $db->query($q)
  or die_query($db, $q);

echo jsonp(array("result" => "Added " . $db->affected_rows . " items."));