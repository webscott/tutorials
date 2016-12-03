<?php

require_once 'Ac_rest.php';

class Backorder_blocker {

    protected $ACRest;
    protected $orderIds = array();
    protected $variantIds = array();
    protected $productIds = array();
    protected $discontinuedProducts = array();
    protected $discontinuedStatus = false;

    public function __construct() {
        $this->ACRest = new Ac_rest;
    }

    public function runDiscontinuedCheck() {
        $statusId = $this->getOrderStatusByName('Submitted');
        if ($statusId) {
            $orders = $this->getOrdersByStatus($statusId);
            if (false !== $orders) {
                $this->getItems($orders);

                foreach ($this->discontinuedProducts as $product) {
                    if ($product['use_variant_inventory'] == false) {
                        if ($product['quantity_on_hand'] <= 0 
                                && $product['product_status_id'] != $this->discontinuedStatus) {
                            $this->productIds[] = $product['id'];
                        }
                    } else {
                        $this->getVariants($product['id']);
                    }
                }

                foreach ($this->variantIds as $variantId) {
                    $this->updateVariant($variantId);
                }
                foreach ($this->productIds as $productId) {
                    $this->updateProduct($productId);
                }
                if (count($this->orderIds)) {
                    $statusId = $this->getOrderStatusByName('Pending Processing');
                    if ($statusId) {
                        foreach ($this->orderIds as $orderId) {
                            $this->updateOrder($orderId, $statusId);
                        }
                    }
                }
            }
        }
    }

    protected function getOrdersByStatus($statusId = '1') {
        $resource = 'orders';
        $query = array(
            'order_status_id' => "$statusId",
            'expand' => 'items'
        );
        $fields = array('id', 'items');
        $result = $this->ACRest->sendGetRequest($resource, $query, $fields);
        if (isset($result['response']['total_count']) 
                && $result['response']['total_count'] > 0) {
            $orders = $result['response']['orders'];
            return $orders;
        } else {
            return false;
        }
    }

    protected function getOrderStatusByName($statusName = 'Submitted') {
        $resource = 'order_statuses';
        $query = array('name' => $statusName);
        $fields = array('id');
        $result = $this->ACRest->sendGetRequest($resource, $query, $fields);
        if (isset($result['response']['total_count']) 
                && $result['response']['total_count'] > 0) {
            $statusId = $result['response']['order_statuses'][0]['id'];
            return $statusId;
        } else {
            return false;
        }
    }

    protected function getProductStatusByName($statusName = 'Discontinued') {
        $resource = 'product_statuses';
        $query = array('name' => $statusName);
        $fields = array('id');
        $result = $this->ACRest->sendGetRequest($resource, $query, $fields);
        if (isset($result['response']['total_count']) 
                && $result['response']['total_count'] > 0) {
            $statusId = $result['response']['product_statuses'][0]['id'];
            return $statusId;
        } else {
            return false;
        }
    }

    protected function getItems($orders) {
        $itemIds = array(); // Contains processed items to prevent duplicate api calls
        foreach ($orders as $order) {
            // Add order Id to array for changing order status later
            $this->orderIds[] = $order['id'];
            foreach ($order['items'] as $item) {
                // Ensure the item hasn't already been called.
                if (!in_array($item['product_id'], $itemIds)) {
                    $itemIds[] = $item['product_id'];
                    // Check to see if the product is discontinued.
                    $resource = 'products';
                    $query = array(
                        'id' => "{$item['product_id']}",
                        'is_discontinued' => 'true'
                    );
                    $fields = array('id',
                        'product_status_id',
                        'use_variant_inventory',
                        'quantity_on_hand'
                    );
                    $result = $this->ACRest->sendGetRequest($resource, $query,
                            $fields);
                    if (isset($result['response']['total_count']) 
                            && $result['response']['total_count'] == 1) {
                        $this->discontinuedProducts[] = $result['response']['products'][0];
                    }
                }
            }
        }
        // Get the Discontinued Product Status to use for updating products and variants
        if (count($this->discontinuedProducts) && false === $this->discontinuedStatus) {
            $this->discontinuedStatus = $this->getProductStatusByName('Discontinued');
        }
    }

    protected function getVariants($itemId) {
        $resource = 'products';
        $query = array(
            'id' => "$itemId",
            'is_discontinued' => 'true',
            'expand' => 'variant_inventory'
        );
        $fields = array('product_status_id', 'variant_inventory');
        $result = $this->ACRest->sendGetRequest($resource, $query, $fields);
        if (isset($result['response']['total_count']) 
                && $result['response']['total_count'] == 1) {
            $variants = $result['response']['products'][0]['variant_inventory'];
            // Set flag to determine if the product status needs to change to Discontinued
            $inventoryDepleted = true;
            foreach ($variants as $variant) {
                // Determine if the variant's status needs to be changed
                if ($variant['inventory'] <= 0) {
                    if ($variant['product_status_id'] != $this->discontinuedStatus) {
                        if (!in_array($variant['id'], $this->variantIds)) {
                            $this->variantIds[] = $variant['id'];
                        }
                    }
                } else {
                    // There's still stock left in one or more variants
                    $inventoryDepleted = false;
                }
            }
            if ($inventoryDepleted) {
                // All variants are out of stock. Add product Id to the list for updating status.
                if (!in_array($itemId, $this->productIds)) {
                    $this->productIds[] = $itemId;
                }
            }
        }
    }

    protected function updateProduct($productId) {
        $resource = 'products';
        $data = array('product_status_id' => "{$this->discontinuedStatus}");
        $result = $this->ACRest->sendPutRequest($resource, $productId, $data);
    }

    protected function updateVariant($variantId) {
        $resource = 'variant_inventory';
        $data = array('product_status_id' => "{$this->discontinuedStatus}");
        $result = $this->ACRest->sendPutRequest($resource, $variantId, $data);
    }

    protected function updateOrder($orderId, $statusId) {
        $resource = 'orders';
        $data = array('order_status_id' => "$statusId");
        $result = $this->ACRest->sendPutRequest($resource, $orderId, $data);
    }

}
