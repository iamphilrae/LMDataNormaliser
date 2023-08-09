<?php

namespace App\Helpers;

use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;
use App\Helpers\Luhn;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use PHPHtmlParser\Dom;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Class Utilities
 * @package App\Helpers
 *
 * Utilities helpers to use throughout the application.
 */
class Utilities
{
    /**
     * Returns the striped down content of a post.
     *
     * @param string $text
     * @param array $post_processing_replacements
     * @return string
     * @throws \PHPHtmlParser\Exceptions\ChildNotFoundException
     * @throws \PHPHtmlParser\Exceptions\CircularException
     * @throws \PHPHtmlParser\Exceptions\ContentLengthException
     * @throws \PHPHtmlParser\Exceptions\LogicalException
     * @throws \PHPHtmlParser\Exceptions\NotLoadedException
     * @throws \PHPHtmlParser\Exceptions\StrictException
     */
    public static function clean_html(string $text, $non_default_allowed_tags=false, array $post_processing_replacements=[]): string {

        if ( trim($text) === '' )
            return '';
        else
            $text = trim($text);

        $allowed_tags =  is_array($non_default_allowed_tags) ? $non_default_allowed_tags : [
            'a', 'img', 'figure', 'picture', 'source',
            'table', 'tr', 'td', 'th', 'thead', 'tbody', 'col', 'colgroup',
            'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote',
            'ul', 'ol', 'li'
        ];

        // Replace various UTF-8 codes for spaces or remove altogether
        $text = str_replace(["&nbsp;", " "], ' ', $text);
        $text = str_replace(["​"], '', $text);

        // Replace UTF-8 character codes for real characters
        $text = str_replace(["&#x27;", "&apos;"], "’", $text);
        $text = str_replace(["&#039;"], "'", $text);

        // Replace double BR tags
        $text = str_replace(
            ["<br/><br/>", "<br><br>", "<br></br>"],
            ["\n\n", "\n\n", "\n\n"],
            $text
        );

        // Remove P tags of entirely whitespace
        $text = preg_replace( '|<p>\s*</p>|', '', $text);

        // Strip all tags, except for allowed, and except for <p>
        $text = strip_tags( $text, array_merge( $allowed_tags, ['p'] ) );

        // Convert HTML entities into their respective characters
        $text = html_entity_decode($text);

        // Add new lines under each block level tag
        $text = str_replace(
            ["<ul>", "</ul>", "</li>", "</h1>", "</h2>", "</h3>", "</h4>", "</h5>", "</h6>", "</p>", "</table>", "</blockquote>", "</figure>"],
            ["<ul>\n","</ul>\n\n", "</li>\n", "</h1>\n\n", "</h2>\n\n", "</h3>\n\n", "</h4>\n\n", "</h5>\n\n", "</h6>\n\n", "</p>\n\n", "</table>\n\n", "</blockquote>\n\n", "</figure>\n\n"],
            $text
        );

        // Strip out the P tags to prevent nested <p> tags and <p>&nbsp;</p> line break occurrences
        $text = strip_tags($text , $allowed_tags);

        // Replace multiple new lines with a double one
        $text = preg_replace( "/\n\n+/", "\n\n", $text);

        // Remove space character if immediately following a new line
        $text = str_replace("\n ", "\n", $text);

        // Add a new lines above head heading tag
        $text = str_replace(
            ["<h1>", "<h2>", "<h3>", "<h4>", "<h5>", "<h6>"],
            ["\n\n<h1>", "\n\n<h2>", "\n\n<h3>", "\n\n<h4>", "\n\n<h5>", "\n\n<h6>"],
            $text
        );

        // Remove empty heading tags
        $text = str_replace(
            ["<h1></h1>", "<h2></h2>", "<h3></h3>", "<h4></h4>", "<h5></h5>", "<h6></h6>"],
            "",
            $text
        );

        $text = trim($text, " \n\r\t\v\0");

        // Perform any post-processing text replacements
        foreach($post_processing_replacements as $replacement) {
            if(isset($replacement['search']) && $replacement['replace'])
                $text = str_replace($replacement['search'], $replacement['replace'], $text);
        }

        return $text;
    }


    /**
     * Returns the datetime when called in ISO format.
     * @return String Datetime as "Y-m-d H:i:s".
     * @throws Exception
     */
    public static function datetime_now(): String {
        return (new DateTime('now', new DateTimeZone('UTC') ))
            ->format("Y-m-d H:i:s");
    }


    /**
     * Combines SQL and its bindings
     *
     * @param $query
     * @return string
     */
    public static function get_eloquent_query($query): string
    {
        return vsprintf(str_replace('?', '%s', $query->toSql()), collect($query->getBindings())->map(function ($binding) {
            return is_numeric($binding) ? $binding : "'{$binding}'";
        })->toArray());
    }


    /**
     * Uses cURL to return the content of a URL.
     * @param string $url
     * @return string
     */
    public static function http_get_content(string $url): string
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, env('APP_CURL_TIMEOUT', 10));

        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }


    /**
     * For a given string, remove everything except alphanumeric, hyphen and underscore.
     *
     * @param string $string
     * @return string
     */
    public static function str_sanitize_as_slug(string $string): string
    {
        return preg_replace("/[^A-Za-z0-9-_]/", '', $string);
    }


    /**
     * Check if expression is an integer, either a type String or int.
     *
     * @param string|int $value
     * @return bool
     */
    public static function is_integer( $value ): bool
    {
        if(gettype($value) == 'integer')
            return true;

        if(gettype($value) == 'string') {
            if (substr($value, 0, 1) == '-') {
                $value = substr($value, 1);
            }
            return (is_int($value) || ctype_digit($value));
        }

        return false;
    }

    /**
     * Check if expression is a positive integer, either a type String or int.
     *
     * @param string|int $value
     * @return bool
     */
    public static function is_positive_integer($value): bool
    {
        if(gettype($value) == 'integer')
            return true;

        if(gettype($value) == 'string')
            return ((is_int($value) || ctype_digit($value)) && (int)$value > 0);

        return false;
    }

    /**
     * Validates whether the supplied number has a valid check digit on the end.
     *
     * @param string $number
     * @return bool
     */
    public static function is_valid_check_digit(string $number): bool
    {
        return (new Luhn())->validateCheckDigit($number);
    }

    /**
     * Validates whether the supplied string has a valid checksum on the end.
     *
     * @param String $str
     * @param bool $is_lowercase
     * @return bool
     */
    public static function is_valid_checksum(string $str, bool $is_lowercase = true): bool
    {
        return (new LuhnModN())->validateChecksum($str, $is_lowercase);
    }

    /**
     * Validates a given latitude $lat
     *
     * @param float|int|string $lat Latitude
     * @return bool `true` if $lat is valid, `false` if not
     */
    public static function is_valid_latitude($lat)
    {
        return preg_match('/^(\+|-)?(?:90(?:(?:\.0{1,6})?)|(?:[0-9]|[1-8][0-9])(?:(?:\.[0-9]{1,6})?))$/', $lat);
    }

    /**
     * Validates a given longitude $long
     *
     * @param float|int|string $long Longitude
     * @return bool `true` if $long is valid, `false` if not
     */
    public static function is_valid_longitude($long)
    {
        return preg_match('/^(\+|-)?(?:180(?:(?:\.0{1,6})?)|(?:[0-9]|[1-9][0-9]|1[0-7][0-9])(?:(?:\.[0-9]{1,6})?))$/', $long);
    }

    /**
     * @param String $str The sorting array in a format: "+field1,-field2" – where + and - dictates
     * ascending and descending respectively.
     * @return array The indexed sorting array in format [ 0 => [ column, direction ], 1 => [ column, direction ] ].
     */
    public static function format_sort_array(string $str)
    {
        $sort_arr = [];
        $sort_fields = explode(',', $str);

        foreach( $sort_fields as $field ) {

            $order = substr( $field, 0, 1 ) == '-' ? 'desc' : 'asc'; // no check for + as that's default
            $field = trim( str_replace( ['+','-'], ['',''], $field ) );

            $sort_arr[] = [
                'column' => $field,
                'direction' => $order
            ];
        }

        return $sort_arr;
    }


    /**
     * This will delete a path and sub-folders if they are found to be empty. Note the passed
     * directory will be deleted if all sub-directories and itself are empty.
     *
     * If the folder contains hidden files, it will _not_ be deleted. Watch out if using MacOS
     * as it has a habit of creating .DS_Store files in folders.
     *
     * Originally borrowed and modified from: https://stackoverflow.com/a/1833681
     *
     * @param string $path The initial path to begin the search and remove from.
     * @return bool Whether the folder is empty (true) or not (false).
     */
    public static function remove_empty_folders_recursive(string $path): bool
    {
        $is_empty = true;

        foreach( glob($path.DIRECTORY_SEPARATOR."*", GLOB_BRACE) as $sub_path )
        {
            if( is_dir($sub_path) ) {
                if( !self::remove_empty_folders_recursive($sub_path) )
                    $is_empty = false;
            }
            else {
                $is_empty = false;
            }
        }

        if( $is_empty ) {
            Log::info('Deleting: ' . $path);
            rmdir( $path );
        }

        return $is_empty;
    }

    /**
     * Recursively delete unwanted files from a path and its sub-folders.
     *
     * @param string $path The initial path to begin the search and remove from.
     * @param array $unwanted_list Files to remove, e.g. [".DS_Store", ".gitignore"]
     */
    public static function remove_unwanted_files_recursive(string $path, array $unwanted_list): void
    {
        foreach( glob($path . DIRECTORY_SEPARATOR . "{*,.[!.]*,..?*}", GLOB_BRACE) as $sub_path )
        {
            if( is_dir($sub_path) ) {
                self::remove_unwanted_files_recursive($sub_path, $unwanted_list);
            }
            else if( in_array( basename($sub_path), $unwanted_list ) ) {
                Log::info('Deleting: ' . $sub_path );
                unlink($sub_path);
            }
        }
    }


    /**
     * Checks whether a file at a given URL exists or not.
     * @param string $url
     * @return bool
     */
    public static function remote_file_exists(string $url): bool {

        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_NOBODY, true);

        $result = curl_exec($curl);
        $exists = false;

        //if request did not fail
        if($result !== false) {
            if( curl_getinfo($curl, CURLINFO_HTTP_CODE) == 200 )
                $exists = true;
        }

        curl_close($curl);
        return $exists;
    }

    /**
     * Creates a short lowercase hash from an integer. E.g. `1234` returns `ax`
     * @param int $val
     * @return string
     */
    public static function short_hash( int $val ): string {
        return base_convert($val, 10, 36);
    }

    /**
     * Scans a directory and sub directories for number of files and directories.
     * @param $path
     * @return array
     */
    public static function scan_dir(string $path): array {

        $iterator = new RecursiveDirectoryIterator($path);

        $bytes_total = 0;
        $files_list = [];
        $number_files = 0;
        $number_directories = 0;

        foreach( new RecursiveIteratorIterator($iterator) as $file_path => $current ) {

            if( !is_dir( $file_path ) && strpos($file_path, '.DS_Store') === false ) {
                $file_size = $current->getSize();
                $bytes_total += $file_size;
                $number_files++;
                $files_list[] = $file_path;
            }
            else if( is_dir( $file_path ) ) {
                $number_directories++; // TODO I think this number is incorrect
            }
        }

        return [
            'total_files' => (int)$number_files,
            'total_directories' => (int)$number_directories,
            'total_size' => (int)$bytes_total,
            'total_size_mb' => number_format( $bytes_total / 1000000, 1 ) . 'MB',
            'files_list' => $files_list
        ];
    }

    /**
     * Returns a string with the latter portion obfuscated, e.g.
     * obfuscate_string("abcdefghijkl", 25) will return "abc*********"
     *
     * @param string $str
     * @param int $percentage_to_keep
     * @param string $obfuscate_character
     * @return string
     */
    public static function obfuscate_string(string $str, int $percentage_to_keep=25, string $obfuscate_character="*"): string
    {
        $chars_to_keep = ceil(($percentage_to_keep/100) * strlen($str));
        return str_pad(substr($str, 0, $chars_to_keep), strlen($str), $obfuscate_character);
    }

    /**
     * Returns a time, synced to play at the same time as it would UTC. For example, if the time is 10:00:00 UTC,
     * and the timezone is CEST (UTC+1); the time returned from this function would be '09:00:00 UTC'. This then
     * enables a player, set to use CEST, to play the schedule at 10:00:00 CEST.
     *
     * @param DateTime $datetime_utc
     * @param DateTimeZone $sync_tz
     * @return DateTime
     * @throws Exception
     */
    public static function timezone_sync( DateTime $datetime_utc, DateTimeZone $sync_tz ): DateTime {

        $datetime_utc = clone $datetime_utc;
        $utc_offset = $sync_tz->getOffset($datetime_utc);

        if( $utc_offset == 0 )
            return $datetime_utc;

        $tz_math_mode = $utc_offset < 0 ? 'add' : 'sub';
        $tz_interval = new DateInterval( 'PT' . $utc_offset * ($utc_offset < 0 ? -1 : 1). 'S' );

        $datetime_utc->$tz_math_mode($tz_interval);

        return $datetime_utc;
    }



    public static function str_putcsv($input, $delimiter = ',', $enclosure = '"') {
        $fp = fopen('php://temp', 'r+b');
        fputcsv($fp, $input, $delimiter, $enclosure);
        rewind($fp);
        $data = rtrim(stream_get_contents($fp), PHP_EOL);
        fclose($fp);
        return $data;
    }

}

