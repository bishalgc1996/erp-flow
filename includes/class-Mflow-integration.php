<?php

/* class to integrate mflow api and update woocommerce product */

class MFlow_ERP_Integration
{
    private $api_url;
    private $public_key;
    private $secret_key;

    public function __construct()
    {
        // Initialize class properties
        $this->api_url = "https://stage.mflow.co.il/api/v1/products/listAll";

        $this->public_key = "pk_dd9da640f0e6174fd3819fc642557ed7";
        $this->secret_key = "sk_04ddbf3e6702ace9a24064ed0bd42f1";

        // Hook into WordPress initialization
        add_action("init", [$this, "init"]);
    }

    public function init()
    {
        // Check if WC_API class exists and register the callback function
        if (class_exists("WC_API")) {
            add_action("woocommerce_api_mflow_erp_integration", [
                $this,
                "callback",
            ]);
        }
    }

    public function callback()
    {
        // Prepare request arguments with API keys
        $request_args = [
            "headers" => [
                "x-mflow-public-key" => $this->public_key,
                "x-mflow-secret-key" => $this->secret_key,
            ],
        ];

        // Make API request to retrieve products

        $response = wp_remote_get($this->api_url, $request_args);

        if (
            !is_wp_error($response) &&
            wp_remote_retrieve_response_code($response) === 200
        ) {
            // Retrieve products from the API response
            $products = json_decode(wp_remote_retrieve_body($response), true);

            foreach ($products as $product_data) {
                // Check if the product already exists in WooCommerce
                $product_id = wc_get_product_id_by_sku($product_data["sku"]);
                $product_type = isset($product_data["variation_attributes"])
                    ? "variable"
                    : "simple";

                if (!$product_id) {
                    // Create a new product if it doesn't exist
                    $new_product_id = $this->createProduct($product_data);
                    update_post_meta(
                        $new_product_id,
                        "mflow_erp_product_id",
                        $product_data["id"]
                    );
                } else {
                    // Update the existing product
                    $this->updateProduct($product_id, $product_data);
                }

                if ($product_type === "variable") {
                    // Create variations for variable products
                    $this->createVariations($product_data);
                }
            }
        } else {
            // Log errors if the API request fails
            $error_message = is_wp_error($response)
                ? $response->get_error_message()
                : "Request failed";
            error_log("mflow ERP API integration error: " . $error_message);
        }
    }

    private function createProduct($product_data)
    {
        // Create a new product instance
        $new_product = new WC_Product();
        $new_product->set_name($product_data["name"]);
        $new_product->set_sku($product_data["sku"]);
        $new_product->set_price($product_data["price"]);
        $new_product->set_manage_stock(true);
        $new_product->set_stock_quantity($product_data["stock"]);

        $product_images = [];
        // Upload product images and retrieve attachment IDs
        foreach ($product_data["images"] as $image) {
            $attachment_id = $this->uploadImage($image["url"]);
            if ($attachment_id) {
                $product_images[] = $attachment_id;
            }
        }

        // Set the primary image and gallery images for the product

        $new_product->set_image_id($product_images[0]);
        $new_product->set_gallery_image_ids($product_images);

        // save the product using WooCommerce function

        $new_product_id = $new_product->save();

        return $new_product_id;
    }

    private function updateProduct($product_id, $product_data)
    {
        $existing_product = wc_get_product($product_id);
        $existing_product->set_price($product_data["price"]);
        $existing_product->set_manage_stock(true);
        $existing_product->set_stock_quantity($product_data["stock"]);
        // save the product using WooCommerce function
        $existing_product->save();
    }

    private function createVariations($product_data)
    {
        $variation_attributes = $product_data["variation_attributes"];
        $variations = $product_data["variations"];

        foreach ($variations as $variation) {
            $variation_product_id = wc_get_product_id_by_sku($variation["sku"]);

            if (!$variation_product_id) {
                $variation_product = new WC_Product_Variable();
                $variation_product->set_name($product_data["name"]);
                $variation_product->set_sku($variation["sku"]);
                $variation_product->set_manage_stock(true);
                $variation_product->set_stock_quantity($variation["stock"]);

                foreach ($variation_attributes as $attribute) {
                    $attribute_name = $attribute["name"];
                    $attribute_slug = sanitize_title($attribute_name);
                    $attribute_value =
                        $variation["attributes"][$attribute_slug];

                    $variation_product->set_attribute(
                        $attribute_slug,
                        $attribute_value
                    );
                }

                if (isset($variation["price"])) {
                    $variation_product->set_price($variation["price"]);
                }

                $variation_product_id = $variation_product->save();
            }

            $variation_product = wc_get_product($variation_product_id);
            $variation_product->set_parent_id($new_product_id);
            $variation_product->save();
        }
    }

    private function uploadImage($image_url)
    {
        $upload_dir = wp_upload_dir();
        $image_name = basename($image_url);
        $image_path = $upload_dir["path"] . "/" . $image_name;

        $response = wp_remote_get($image_url);
        if (
            !is_wp_error($response) &&
            wp_remote_retrieve_response_code($response) === 200
        ) {
            $image_data = wp_remote_retrieve_body($response);
            wp_upload_bits($image_name, null, $image_data);

            $attachment = $upload_dir["url"] . "/" . $image_name;
            $attachment_id = attachment_url_to_postid($attachment);

            return $attachment_id;
        }

        return false;
    }
}