<?php
declare(strict_types=1);

/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at http://getid3.sourceforge.net                 //
//            or http://www.getid3.org                         //
//          also https://github.com/JamesHeinrich/getID3       //
/////////////////////////////////////////////////////////////////
//                                                             //
// Please see readme.txt for more information                  //
//                                                            ///
/////////////////////////////////////////////////////////////////

// define a constant rather than looking up every time it is needed
if (!defined('GETID3_OS_ISWINDOWS')) {
    define('GETID3_OS_ISWINDOWS', (stripos(PHP_OS, 'WIN') === 0));
}
// Get base path of getID3() - ONCE
if (!defined('GETID3_INCLUDEPATH')) {
    define('GETID3_INCLUDEPATH', dirname(__FILE__).DIRECTORY_SEPARATOR);
}
// Workaround Bug #39923 (https://bugs.php.net/bug.php?id=39923)
if (!defined('IMG_JPG') && defined('IMAGETYPE_JPEG')) {
    define('IMG_JPG', IMAGETYPE_JPEG);
}
if (!defined('ENT_SUBSTITUTE')) { // PHP5.3 adds ENT_IGNORE, PHP5.4 adds ENT_SUBSTITUTE
    define('ENT_SUBSTITUTE', (defined('ENT_IGNORE') ? ENT_IGNORE : 8));
}

/*
http://www.getid3.org/phpBB3/viewtopic.php?t=2114
If you are running into a the problem where filenames with special characters are being handled
incorrectly by external helper programs (e.g. metaflac), notably with the special characters removed,
and you are passing in the filename in UTF8 (typically via a HTML form), try uncommenting this line:
*/
//setlocale(LC_CTYPE, 'en_US.UTF-8');

// attempt to define temp dir as something flexible but reliable
$temp_dir = ini_get('upload_tmp_dir');
if ($temp_dir && (!is_dir($temp_dir) || !is_readable($temp_dir))) {
    $temp_dir = '';
}
if (!$temp_dir && function_exists('sys_get_temp_dir')) { // sys_get_temp_dir added in PHP v5.2.1
    // sys_get_temp_dir() may give inaccessible temp dir, e.g. with open_basedir on virtual hosts
    $temp_dir = sys_get_temp_dir();
}
$temp_dir = @realpath($temp_dir); // see https://github.com/JamesHeinrich/getID3/pull/10
$open_basedir = ini_get('open_basedir');
if ($open_basedir) {
    // e.g. "/var/www/vhosts/getid3.org/httpdocs/:/tmp/"
    $temp_dir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $temp_dir);
    $open_basedir = str_replace(['/', '\\'], DIRECTORY_SEPARATOR,
      $open_basedir);
    if (substr($temp_dir, -1, 1) != DIRECTORY_SEPARATOR) {
        $temp_dir .= DIRECTORY_SEPARATOR;
    }
    $found_valid_tempdir = false;
    $open_basedirs = explode(PATH_SEPARATOR, $open_basedir);
    foreach ($open_basedirs as $basedir) {
        if (substr($basedir, -1, 1) != DIRECTORY_SEPARATOR) {
            $basedir .= DIRECTORY_SEPARATOR;
        }
        if (preg_match('#^'.preg_quote($basedir).'#', $temp_dir)) {
            $found_valid_tempdir = true;
            break;
        }
    }
    if (!$found_valid_tempdir) {
        $temp_dir = '';
    }
    unset($open_basedirs, $found_valid_tempdir, $basedir);
}
if (!$temp_dir) {
    $temp_dir = '*'; // invalid directory name should force tempnam() to use system default temp dir
}
// $temp_dir = '/something/else/';  // feel free to override temp dir here if it works better for your system
if (!defined('GETID3_TEMP_DIR')) {
    define('GETID3_TEMP_DIR', $temp_dir);
}
unset($open_basedir, $temp_dir);

// Populate phpunit.xml constants.
constant('GETID3_OS_ISWINDOWS');
constant('GETID3_INCLUDEPATH');
constant('IMG_JPG');
constant('ENT_SUBSTITUTE');
constant('GETID3_TEMP_DIR');
