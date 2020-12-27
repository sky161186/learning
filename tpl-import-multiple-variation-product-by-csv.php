<?php 
/*

Template Name: Import multiple Variation Product by CSV

*/


get_header();

if (isset($_FILES["csv"]["size"])) {

$file = $_FILES["csv"]["tmp_name"];
$handle = fopen($file,"r");

$row = 1;
$fields_name = array();
$product_data = array();

while (($data = fgetcsv($handle)) !== FALSE) {

$num = count($data);

if($row == 1){
    for($i=1; $i< $num ; $i++){
         $fields_name[$i]=$data[$i];

    }
   
}
else {

foreach($fields_name as $key => $value) {
        $product_data[$value] = $data[$key];
       
    }



insert_product($product_data,$variations);

}



$row++;

}
fclose($handle);

}

 //print_r($product_data);
?>



 

<style type="text/css">
#csv {border: 1px solid gainsboro;}
#importCsvFile{
background-color:white;
width: 100%;
height: 300px;
text-align: center;
border: 1px solid grey;
}
#importCsvFile h2{margin-bottom: 40px;margin-top: 40px;}

.bgimg {width: 100px ! important;display: inline ! important;margin-top: 0px ! important;}
</style>

 

<section id="promo"><div class="contentpane">
<article>

<div id="importCsvFile">
<form action="" method="post" enctype="multipart/form-data" name="form" id="form1">
<h2>Import Product csv file</h2>
<input accept="csv" name="csv" type="file" id="csv" />
<input type="submit" name="Submit" class="bgimg"/><br />

</form>
</div>

</article>

 
 

</div>
</section>

<?php





function insert_product ($product_data,$variations)  
{
    $post = array( // Set up the basic post data to insert for our product

        'post_author'  => 1,
        'post_content' => $product_data['Description'],
        "post_excerpt" => $product_data['short_description'],
        'post_status'  => 'publish',
        'post_title'   => $product_data['Name'],
        'post_parent'  => '',
        'post_type'    => 'product'
    );

    $post_id = wp_insert_post($post); // Insert the post returning the new post id
    $product = new WC_Product_Variable($post_id);
    $product->save();
    if (!$post_id) // If there is no post id something has gone wrong so don't proceed
    {
        return false;
    }


    
    update_post_meta( $new_post_id, "_stock_status", "instock");
    update_post_meta( $new_post_id, "_sku", $product_data['SKU']);
    update_post_meta( $new_post_id, "_tax_status", "taxable" );
    update_post_meta( $new_post_id, "_manage_stock", "no" );

    update_post_meta( $new_post_id, "_stock", 100 );
   
   

  

    
    if($product_data['Categories'])
        create_category($post_id, $product_data['Categories']);
    

    
    wp_set_object_terms($post_id, 'variable', 'product_type'); // Set it to a variable product type


    $available_attributes = array( "eo_metal_attr", "shape-view");
    $variations = array();
    for($i=1;$i<=20;$i++){
        $metal_field_name = 'metal_name'.$i;
        $shape_field_name = 'shape_name'.$i;
        $metal_shape_price_field_name = 'metal_shape_price'.$i;
        $metal_shape_img_field_name = 'metal_shape_image'.$i;
        if($product_data[$metal_field_name] && $product_data[$shape_field_name] && $product_data[$metal_shape_price_field_name] && $product_data[$metal_shape_img_field_name]){
           $variations[] = array("attributes" => array(
                    "eo_metal_attr"  => $product_data[$metal_field_name],
                    "shape-view" => $product_data[$shape_field_name]
                ),
                "price" => $product_data[$metal_shape_price_field_name],
                "image" =>  $product_data[$metal_shape_img_field_name]
            ); 
        }

      }


    insert_product_attributes($post_id, $available_attributes,$variations); // Add attributes passing the new post id, attributes & variations
    insert_product_variations($product, $post_id, $variations); // Insert variations passing the new post id & variations   
}

function insert_product_attributes ($post_id, $available_attributes, $variations)  
{

  

    foreach ($available_attributes as $attribute) // Go through each attribute
    {   
        $values = array(); // Set up an array to store the current attributes values.

        foreach ($variations as $variation) // Loop each variation in the file
        {
            $attribute_keys = array_keys($variation['attributes']); // Get the keys for the current variations attributes

            foreach ($attribute_keys as $key) // Loop through each key
            {
                if ($key === $attribute) // If this attributes key is the top level attribute add the value to the $values array
                {
                    $values[] = $variation['attributes'][$key];
                }
            }
        }

        // Essentially we want to end up with something like this for each attribute:
        // $values would contain: array('small', 'medium', 'medium', 'large');

        $values = array_unique($values); // Filter out duplicate values

        // Store the values to the attribute on the new post, for example without variables:
        // wp_set_object_terms(23, array('small', 'medium', 'large'), 'pa_size');
        wp_set_object_terms($post_id, $values, 'pa_' . $attribute);
    }

    $product_attributes_data = array(); // Setup array to hold our product attributes data

    foreach ($available_attributes as $attribute) // Loop round each attribute
    {
        $product_attributes_data['pa_'.$attribute] = array( // Set this attributes array to a key to using the prefix 'pa'

            'name'         => 'pa_'.$attribute,
            'value'        => '',
            'is_visible'   => '1',
            'is_variation' => '1',
            'is_taxonomy'  => '1'

        );
    }

    update_post_meta($post_id, '_product_attributes', $product_attributes_data); // Attach the above array to the new posts meta data key '_product_attributes'
}

function insert_product_variations ($product,$post_id, $variations)  
{
    foreach ($variations as $index => $variation)
    {
        $variation_post = array( // Setup the post data for the variation

            'post_title'  => 'Variation #'.$index.' of '.count($variations).' for product#'. $post_id,
            'post_name'   => 'product-'.$post_id.'-variation-'.$index,
            'post_status' => 'publish',
            'post_parent' => $post_id,
            'post_type'   => 'product_variation',
            'guid'        => home_url() . '/?product_variation=product-' . $post_id . '-variation-' . $index
        );

        $variation_post_id = wp_insert_post($variation_post); // Insert the variation

        foreach ($variation['attributes'] as $attribute => $value) // Loop through the variations attributes
        {   
            $attribute_term = get_term_by('name', $value, 'pa_'.$attribute); // We need to insert the slug not the name into the variation post meta

            update_post_meta($variation_post_id, 'attribute_pa_'.$attribute, $attribute_term->slug);
          // Again without variables: update_post_meta(25, 'attribute_pa_size', 'small')
        }

        update_post_meta($variation_post_id, '_price', $variation['price']);
        update_post_meta($variation_post_id, '_regular_price', $variation['price']);

        $imageUrl = $variation['image'];
        if($variation['image'])
            getImage($product, $variation_post_id, $imageUrl, 'Gallery Description');
      

    }
}





       
  function getImage($product, $postId,$thumb_url,$imageDescription){
        // add these to work add image function


        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $tmp = download_url($thumb_url);
        preg_match('/[^\?]+\.(jpg|JPG|jpe|JPE|jpeg|JPEG|gif|GIF|png|PNG)/', $thumb_url, $matches);
        $file_array['name'] = basename($matches[0]);
        $file_array['tmp_name'] = $tmp;
        // If error storing temporarily, unlink
        $logtxt = '';
        if (is_wp_error($tmp)) {
        @unlink($file_array['tmp_name']);
        $file_array['tmp_name'] = '';
        return;
        }else{
        $logtxt .= "download_url: $tmp\n";
        }

        //use media_handle_sideload to upload img:
        $thumbid = media_handle_sideload( $file_array, $postId, $imageName ); //'gallery desc'


        // If error storing permanently, unlink
        if (is_wp_error($thumbid)) {
        @unlink($file_array['tmp_name']);
        $thumbid = (string)$thumbid;
        $logtxt .= "Error: media_handle_sideload error - $thumbid\n";
        }else{
        $logtxt .= "ThumbID: $thumbid\n";
        }
        set_post_thumbnail($postId, $thumbid);
        update_post_meta($postId,'variation_image_gallery', $thumbid);
        $gallery = array($thumbid);
        $product->set_gallery_image_ids($gallery);
}


  function create_category($post_id,$categories){
    if($categories){
  
        $categoryID = array();
      
        $category_arr = explode(',',$categories);
        foreach($category_arr as $value) {
             $category_arr = explode('>',$value);
            if(count($category_arr)>1){
                foreach ($category_arr as $value) {
                    $term = term_exists( $value, 'product_cat' );
                    if ( $term !== 0 && $term !== null ) {
                        $term = get_term_by('name', $value, 'product_cat');
                        $categoryID[] = $term->term_id;
                    } else {

                                    $term = term_exists($category_arr[0], 'product_cat' );
                                    if ( $term !== 0 && $term !== null ) {
                                        $term = get_term_by('name', $category_arr[0], 'product_cat');
                                        $categoryID[] = $term->term_id;
                                    } else {
                                    // replace non letter or digits by -
                                      $cat_name = preg_replace('~[^\pL\d]+~u', '-', $category_arr[0]);

                                      // transliterate
                                      $cat_name = iconv('utf-8', 'us-ascii//TRANSLIT', $cat_name);

                                      // remove unwanted characters
                                      $cat_name = preg_replace('~[^-\w]+~', '', $cat_name);

                                      // trim
                                      $cat_name = trim($cat_name, '-');

                                       // remove space
                                      $cat_name = str_replace(' ', '-', $cat_name);

                                      // remove duplicate -
                                      $cat_name = preg_replace('~-+~', '-', $cat_name);

                                      // lowercase
                                      $cat_name = strtolower($cat_name);

                                    $parent = wp_insert_term(
                                        $category_arr[0], // category name
                                        'product_cat', // taxonomy
                                        array(                                            
                                            'slug' => $cat_name, // optional
                                        )
                                    );
                                    
                                     $categoryID[] = $parent['term_id'];

                                    }

                                     $term = term_exists($category_arr[1], 'product_cat' );
                                    if ( $term !== 0 && $term !== null ) {
                                        $term = get_term_by('name', $category_arr[1], 'product_cat');
                                        $categoryID[] = $term->term_id;
                                    } else {

                                     // replace non letter or digits by -
                                      $cat_name1 = preg_replace('~[^\pL\d]+~u', '-', $category_arr[1]);

                                      // transliterate
                                      $cat_name1 = iconv('utf-8', 'us-ascii//TRANSLIT', $cat_name1);

                                      // remove unwanted characters
                                      $cat_name1 = preg_replace('~[^-\w]+~', '', $cat_name1);

                                      // trim
                                      $cat_name1 = trim($cat_name1, '-');

                                       // remove space
                                      $cat_name1 = str_replace(' ', '-', $cat_name1);

                                      // remove duplicate -
                                      $cat_name1 = preg_replace('~-+~', '-', $cat_name1);

                                      // lowercase
                                      $cat_name1 = strtolower($cat_name1);

                                      $child = wp_insert_term(
                                            $category_arr[1], // category name
                                            'product_cat', // taxonomy
                                            array(
                                              
                                                'slug' => $cat_name1, // optional
                                                'parent' => $parent['term_id'], // set it as a sub-category
                                            )
                                        );
                                      
                                      $categoryID[] = $child['term_id'];
                              }

                    }

                }
            } else{

                                $term = term_exists( $value, 'product_cat' );
                                if ( $term !== 0 && $term !== null ) {
                                    $term = get_term_by('name', $value, 'product_cat');
                                    $categoryID[] = $term->term_id;
                                } else {


                                 // replace non letter or digits by -
                                      $cat_name = preg_replace('~[^\pL\d]+~u', '-', $category_arr[0]);

                                      // transliterate
                                      $cat_name = iconv('utf-8', 'us-ascii//TRANSLIT', $cat_name);

                                      // remove unwanted characters
                                      $cat_name = preg_replace('~[^-\w]+~', '', $cat_name);

                                      // trim
                                      $cat_name = trim($cat_name, '-');

                                       // remove space
                                      $cat_name = str_replace(' ', '-', $cat_name);

                                      // remove duplicate -
                                      $cat_name = preg_replace('~-+~', '-', $cat_name);

                                      // lowercase
                                      $cat_name = strtolower($cat_name);

                                        $parent = wp_insert_term(
                                            $category_arr[0], // category name
                                            'product_cat', // taxonomy
                                            array(                                            
                                                'slug' => $cat_name, // optional
                                            )
                                        );
                                }        
                                     if($parent['term_id'])   
                                      $categoryID[] = $parent['term_id'];   

            }
            


        }

        wp_set_object_terms($post_id,  array_unique($categoryID), 'product_cat'); // Set up its categories
    }

   
  }      

?>

<?php get_footer(); ?>