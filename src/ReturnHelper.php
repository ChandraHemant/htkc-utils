<?php

namespace ChandraHemant\HtkcUtils;

class ReturnHelper
{
   /**
     * Return a JSON response for API or raw response based on the format flag.
     *
     * @param mixed $ret The data to return in the response.
     * @param bool $inApiFormat Flag to determine if the response should be in API format. Default is true.
     * @param int $statusCode Custom HTTP status code for the response. Default is 200 (OK).
     * @return \Illuminate\Http\JsonResponse|mixed JSON response or raw data depending on $inApiFormat flag.
     */
    public static function jsonApiReturn($ret, bool $inApiFormat = true, int $statusCode = 200)
    {
        // If we need to return in API format
        if ($inApiFormat) {
            // Check if the result is empty or null and return an appropriate response
            if (empty($ret) || $ret === null) {
                return response()->json([
                    'result' => [],
                    'status' => false,
                ], $statusCode);
            }

            // Return the data with a success status
            return response()->json([
                'result' => $ret,
                'status' => true,
            ], $statusCode);
        }

        // If not in API format, return the raw data
        return $ret;
    }

}