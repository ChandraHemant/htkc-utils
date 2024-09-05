<?php

namespace ChandraHemant\HtkcUtils;

class ReturnHelper
{
    public static function jsonApiReturn($ret, $inApiFormat = true) {
        if($inApiFormat) {
            if(empty($ret) or ($ret == NULL) or !$ret) {
                return response()->json([
                    'result' => [],
                    'status' => false
                ]);
            } else {
                return response()->json([
                    'result' => $ret,
                    'status' => true
                ]);
            }
        }
        return $ret;
    }
}