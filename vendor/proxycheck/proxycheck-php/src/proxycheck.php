<?php

namespace proxycheck;

class proxycheck
{
    const OPTION_API_KEY = 'API_KEY';
    const OPTION_ASN_DATA = 'ASN_DATA';
    const OPTION_ALLOWED_COUNTRIES = 'ALLOWED_COUNTRIES';
    const OPTION_BLOCKED_COUNTRIES = 'BLOCKED_COUNTRIES';
    const OPTION_TLS_SECURITY = 'TLS_SECURITY';
    const OPTION_INF_ENGINE = 'INF_ENGINE';
    const OPTION_RISK_DATA = 'RISK_DATA';
    const OPTION_VPN_DETECTION = 'VPN_DETECTION';
    const OPTION_DAY_RESTRICTOR = 'DAY_RESTRICTOR';
    const OPTION_QUERY_TAGGING = 'QUERY_TAGGING';
    const OPTION_CUSTOM_TAG = 'CUSTOM_TAG';
    const OPTION_LIST_SELECTION = 'LIST_SELECTION';
    const OPTION_LIST_ACTION = 'LIST_ACTION';
    const OPTION_LIMIT = 'LIMIT';
    const OPTION_OFFSET = 'OFFSET';
    const OPTION_STAT_SELECTION = 'STAT_SELECTION';


    public static function check($visitor_ip, $options)
    {
        // Setup the correct querying string for the transport security selected.
        if (isset($options['TLS_SECURITY']) && $options['TLS_SECURITY'] == true) {
            $url = "https://";
        } else {
            $url = "http://";
        }

        // Check if they have enabled blocking or allowing countries and if so, enable ASN checking.
        if (isset($options['BLOCKED_COUNTRIES']) && !empty($options['BLOCKED_COUNTRIES'][0])) {
            $options['ASN_DATA'] = 1;
        } else {
            if (isset($options['ALLOWED_COUNTRIES']) && !empty($options['ALLOWED_COUNTRIES'][0])) {
                $options['ASN_DATA'] = 1;
            }
        }

        // Build up the URL string with the selected flags.
        $url .= "proxycheck.io/v2/" . $visitor_ip;
        if (isset($options['API_KEY'])) {
            $url .= "?key=" . $options['API_KEY'];
        } else {
            $url .= "?key=";
        }
        if (isset($options['DAY_RESTRICTOR'])) {
            $url .= "&days=" . $options['DAY_RESTRICTOR'];
        }
        if (isset($options['VPN_DETECTION'])) {
            $url .= "&vpn=" . $options['VPN_DETECTION'];
        }
        if (isset($options['INF_ENGINE'])) {
            $url .= "&inf=" . $options['INF_ENGINE'];
        }
        if (isset($options['ASN_DATA'])) {
            $url .= "&asn=" . $options['ASN_DATA'];
        }
        if (isset($options['RISK_DATA'])) {
            $url .= "&risk=" . $options['RISK_DATA'];
        }
        $url .= "&node=1";
        $url .= "&port=1";
        $url .= "&seen=1";

        // By default the tag used is your querying domain and the webpage being accessed
        // However you can supply your own descriptive tag or disable tagging altogether.
        if (isset($options['QUERY_TAGGING']) && $options['QUERY_TAGGING'] == true && empty($options['CUSTOM_TAG'])) {
            $post_fields = "tag=" . $_SERVER['SERVER_NAME'] . $_SERVER['REQUEST_URI'];
        } else {
            if (isset($options['QUERY_TAGGING']) && $options['QUERY_TAGGING'] == true && !empty($options['CUSTOM_TAG'])) {
                $post_fields = "tag=" . $options['CUSTOM_TAG'];
            } else {
                $post_fields = "";
            }
        }

        // Performing the API query to proxycheck.io/v2/ using cURL
        if ( isset($post_fields) && !empty($post_fields) ) {
            $decoded_json = self::makeRequest('POST', $url, $post_fields);
        } else {
            $decoded_json = self::makeRequest('GET', $url, $post_fields);
        }

        // Output the clear block and block reasons for the IP we're checking.
        if (isset($decoded_json[$visitor_ip]["proxy"]) && $decoded_json[$visitor_ip]["proxy"] == "yes" && $decoded_json[$visitor_ip]["type"] == "VPN") {
            $decoded_json["block"] = "yes";
            $decoded_json["block_reason"] = "vpn";
        } else {
            if (isset($decoded_json[$visitor_ip]["proxy"]) && $decoded_json[$visitor_ip]["proxy"] == "yes") {
                $decoded_json["block"] = "yes";
                $decoded_json["block_reason"] = "proxy";
            } else {
                $decoded_json["block"] = "no";
                $decoded_json["block_reason"] = "na";
            }
        }

        // Country checking for blocking and allowing specific countries by name or isocode.
        if ($decoded_json["block"] == "no" && isset($options['BLOCKED_COUNTRIES']) && !empty($options['BLOCKED_COUNTRIES'][0])) {
            if (in_array($decoded_json[$visitor_ip]["country"], $options['BLOCKED_COUNTRIES']) or in_array(
                    $decoded_json[$visitor_ip]["isocode"],
                    $options['BLOCKED_COUNTRIES']
                )) {
                $decoded_json["block"] = "yes";
                $decoded_json["block_reason"] = "country";
            }
        } else {
            if ($decoded_json["block"] == "yes" && isset($options['ALLOWED_COUNTRIES']) && !empty($options['ALLOWED_COUNTRIES'][0])) {
                if (in_array($decoded_json[$visitor_ip]["country"], $options['ALLOWED_COUNTRIES']) or in_array(
                        $decoded_json[$visitor_ip]["isocode"],
                        $options['ALLOWED_COUNTRIES']
                    )) {
                    $decoded_json["block"] = "no";
                    $decoded_json["block_reason"] = "na";
                }
            }
        }

        return $decoded_json;
    }

    public static function listing($options)
    {
        // Setup the correct querying string for the transport security selected.
        if (isset($options['TLS_SECURITY']) && $options['TLS_SECURITY'] == true) {
            $url = "https://";
        } else {
            $url = "http://";
        }

        // Build up the URL string for the selected list and action.
        $url .= "proxycheck.io/dashboard/" . $options['LIST_SELECTION'] . "/" . $options['LIST_ACTION'] . "/";
        $url .= "?key=" . $options['API_KEY'];

        if ($options['LIST_ACTION'] == "add" or $options['LIST_ACTION'] == "remove" or $options['LIST_ACTION'] == "set") {
            if (!empty($options['LIST_ENTRIES'])) {
                $post_fields = "data=" . implode("\r\n", $options['LIST_ENTRIES']);
            } else {
                $post_fields = "";
            }
        } else {
            $post_fields = "";
        }

        // Performing the API query to proxycheck.io/dashboard/ using cURL
        $decoded_json = self::makeRequest('POST', $url, $post_fields);

        return $decoded_json;
    }

    public static function stats($options)
    {
        // Setup the correct querying string for the transport security selected.
        if (isset($options['TLS_SECURITY']) && $options['TLS_SECURITY'] == true) {
            $url = "https://";
        } else {
            $url = "http://";
        }

        // Build up the URL string for the selected export stat.
        $url .= "proxycheck.io/dashboard/export/" . $options['STAT_SELECTION'] . "/";
        $url .= "?key=" . $options['API_KEY'];

        if (strcasecmp($options['STAT_SELECTION'], "detections") == 0 or strcasecmp(
                $options['STAT_SELECTION'],
                "queries"
            ) == 0) {
            $url .= "&json=1";
        }

        if (strcasecmp($options['STAT_SELECTION'], "detections") == 0) {
            if (empty($options['LIMIT'])) {
                $options['LIMIT'] = 100;
            }
            if (empty($options['OFFSET'])) {
                $options['OFFSET'] = 0;
            }
            $url .= "&limit=" . $options['LIMIT'];
            $url .= "&offset=" . $options['OFFSET'];
        }

        // Performing the API query to proxycheck.io/dashboard/ using cURL
        $decoded_json = self::makeRequest('GET', $url);

        return $decoded_json;
    }

    public static function makeRequest($method = 'GET', $url, $params = [])
    {
        $ch = curl_init($url);

        $curl_options = array(
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_RETURNTRANSFER => true
        );

        if($method === 'POST') {
            $curl_options[CURLOPT_POST] = 1;
            $curl_options[CURLOPT_POSTFIELDS] = $params;
        }

        curl_setopt_array($ch, $curl_options);
        $api_json_result = curl_exec($ch);
        curl_close($ch);

        return json_decode($api_json_result, true);

    }

}
