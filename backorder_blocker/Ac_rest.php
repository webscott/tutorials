<?php

define('AC_STORE_DOMAIN', 'www.yourstoredomain.com');
define('AC_ACCESS_TOKEN', 'your_access_token');

class Ac_rest {

    public function __construct($token = null) {
        // Enables the ability to receive a different token than the default
        if (!is_null($token)) {
            $this->token = (string) $token;
        } else {
            $this->token = AC_ACCESS_TOKEN;
        }
    }

    public function sendGetRequest($resource = 'products', $query = null, $fields = null) {
        // Set the initial url
        $url = 'https://' . AC_STORE_DOMAIN . '/api/v1/' . $resource;
        // Add query parameters
        if (is_array($query) && count($query) > 0) {
            // Check for querystring character in url
            if (FALSE === strpos($url, '?'))
                $url .= '?';
            foreach ($query as $key => $value) {
                $url .= urlencode(trim($key)) . '=' . urlencode(trim($value)) . '&';
            }
        }
        // Add field restrictions
        if (is_array($fields) && count($fields) > 0) {
            // Ensure the last character in the url is either ? or & before adding the fields portion of the url
            if (FALSE === strpos($url, '?')) {
                $url .= '?fields=';
            } elseif (strrpos($url, '?') < (strlen($url) - 1)) {
                if (!strrpos($url, '&') == (strlen($url) - 1)) {
                    $url .= '&fields=';
                } else {
                    $url .= 'fields=';
                }
            }
            foreach ($fields as $fieldName) {
                $url .= urlencode(trim($fieldName)) . ',';
            }
        }
        // Remove & or , from end of url
        if (strrpos($url, '&') == (strlen($url) - 1) || strrpos($url, ',') == (strlen($url) - 1)) {
            $url = substr($url, 0, strlen($url) - 1);
        }
        $returnData = array();
        $returnData['url'] = $url;
        // URL is built at this point. Send it through.
        $this->curl = curl_init($url);
        curl_setopt($this->curl, CURLOPT_HEADER, false);
        curl_setopt($this->curl, CURLOPT_HTTPHEADER, array('X-AC-Auth-Token: ' . $this->token,
            'Cache-Control: no-cache'));
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        timeoutRetry:
        $json = curl_exec($this->curl);
        $status = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
        if ($status != 200) {
            if ($status == 429) {
                // We hit the max API requests in a 10 second window. Take a break & try again.
                sleep(11);
                goto timeoutRetry;
            } else {
                die("Error: call to $url failed with status $status and response content: $json");
            }
        }
        curl_close($this->curl);
        $response = json_decode(utf8_encode($json), true);
        $returnData['response'] = $response;
        return $returnData;
    }

    public function sendPutRequest($resource = null, $id = null, $data = null) {
        if (is_null($resource) || is_null($id) || !is_array($data)) {
            die("Error: Resource, id or data not supplied for PUT operation.");
        }
        // Set the initial url
        $url = 'https://' . AC_STORE_DOMAIN . '/api/v1/' . $resource . '/' . $id;
        // Encode $data into JSON format
        $data = json_encode($data);
        $this->curl = curl_init($url);
        curl_setopt($this->curl, CURLOPT_HEADER, false);
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($this->curl, CURLOPT_HTTPHEADER, array('Content-Type: application/json',
            'X-AC-Auth-Token: ' . $this->token,
            'Content-Length: ' . strlen($data)));
        curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, $data);
        timeoutRetry:
        $json = curl_exec($this->curl);
        $status = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
        if ($status != 200) {
            if ($status == 429) {
                // We hit the max API requests in a 10 second window. Take a break & try again.
                sleep(11);
                goto timeoutRetry;
            } else {
                die("Error: call to /api/v1/$resource failed with status $status and response content: $json");
            }
        }
        curl_close($this->curl);
        $response = json_decode(utf8_encode($json), true);
        $returnData['response'] = $response;
        return $returnData;
    }

}
