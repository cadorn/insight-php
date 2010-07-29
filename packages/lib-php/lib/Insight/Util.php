<?php

class Insight_Util {

    /**
    * Description not available
    *
    * @param array $a1
    * @param array $a2
    * @param string $MergeTypes A concatenated string of characters indicating the merge type at each level.
    *                           "S" Means a soft merge (merge array indexes) and traverses into the value
    *                           "H" Means a hard merge (overwrite same array indexes) and skips one level of value.
    *                           "A" Means add element only if key does not already exist
    *                           "R" Means a replacement and will stop traversing deeper
    * The last character will be used to determine the merge type for any depper levels if existing.
    */
    public static function array_merge($a1, $a2, $MergeTypes=false, $_Level=0) {
    
        if( (!$a1 || gettype($a1)!='array') && (!$a2 || gettype($a2)!='array') ) return array();
    
        $r = array();
    
        if(gettype($a1)!='array') {
            return $a2;
        } else {
            if(gettype($a2)!='array') return $a1;
        }
    
        $merge_type = false;
        if($MergeTypes!==false) {
            $merge_type = substr($MergeTypes,$_Level,1);
            if(!$merge_type) $merge_type = substr($MergeTypes,-1,1);
        }
    
        foreach( $a1 as $k => $v ) {
            if(isset($a2[$k])) {
                if(gettype($v)=='array') {
                    if($merge_type!='A' || !array_key_exists($k,$a1)) {
                        if($merge_type=='R' || gettype($a2[$k])!='array') {
                            $r[$k] = $a2[$k];
                        } else {
                            $r[$k] = self::array_merge($v,$a2[$k],$MergeTypes,$_Level+1);
                        }
                    } else {
                        $r[$k] = $v;
                    }
                } else {
                    if($merge_type!='A' || !array_key_exists($k,$a1)) {
                        $r[$k] = $a2[$k];
                    } else {
                        $r[$k] = $v;
                    }
                }
                unset($a2[$k]);
            } else {
                $r[$k] = $v;
            }
        }
    
        foreach( $a2 as $k => $v ) {
            $r[$k] = $v;
        }
    
        return $r;
    }


    public static function is_list($array)
    {
        $i = 0;
        foreach( array_keys($array) as $k ) {
            if( $k !== $i++ ) {
                $i = -1;
                break;
            }
        }
        if($i==-1) {
            // Array is a map
            return false;
        } else {
            // Array is a list
            return true;
        }
    }
    
    public static function getallheaders()
    {
        if(function_exists('getallheaders')) {
            return getallheaders();
        }
        $headers = array();
        foreach($_SERVER as $name => $value) {
            if(substr($name, 0, 5) == 'HTTP_') {
                $headers[str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($name, 5)))))] = $value;
            }
        }
        return $headers;
    }
    
    public static function getRequestHeader($Name) {
        $headers = self::getallheaders();
        if(isset($headers[$Name])) {
            return $headers[$Name];
        } else
        // just in case headers got lower-cased in transport
        if(isset($headers[strtolower($Name)])) {
            return $headers[strtolower($Name)];
        }
        return false;
    }    
}
