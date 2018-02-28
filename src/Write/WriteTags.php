<?php
declare(strict_types=1);

namespace GetId3\Write;

/////////////////////////////////////////////////////////////////
/// getID3() by James Heinrich <info@getid3.org>               //
//  available at http://getid3.sourceforge.net                 //
//            or http://www.getid3.org                         //
//          also https://github.com/JamesHeinrich/getID3       //
/////////////////////////////////////////////////////////////////
// See readme.txt for more details                             //
/////////////////////////////////////////////////////////////////
///                                                            //
// write.php                                                   //
// module for writing tags (APEv2, ID3v1, ID3v2)               //
// dependencies: getid3.lib.php                                //
//               write.apetag.php (optional)                   //
//               write.id3v1.php (optional)                    //
//               write.id3v2.php (optional)                    //
//               write.vorbiscomment.php (optional)            //
//               write.metaflac.php (optional)                 //
//               write.lyrics3.php (optional)                  //
//                                                            ///
/////////////////////////////////////////////////////////////////

use function collect;
use const ENT_QUOTES;
use Exception;
use GetId3\GetId3;
use GetId3\Library\GetId3Library;
use GetId3\Module\Tag\Id3v1;
use function mb_strtoupper;

/**
 * NOTES:
 * You should pass data here with standard field names as follows:
 * * TITLE
 * * ARTIST
 * * ALBUM
 * * TRACKNUMBER
 * * COMMENT
 * * GENRE
 * * YEAR
 * * ATTACHED_PICTURE (ID3v2 only)
 * The APEv2 Tag Items Keys definition says "TRACK" is correct but foobar2000
 * uses "TRACKNUMBER" instead Pass data here as "TRACKNUMBER" for compatability
 * with all formats
 *
 * @link http://www.personal.uni-jena.de/~pfk/mpp/sv8/apekey.html
 */
class WriteTags
{

    /**
     * Absolute filename of file to write tags to.
     *
     * @var string
     */
    public $filename;

    /**
     * Array of tag formats to write ('id3v1', 'id3v2.2', 'id2v2.3', 'id3v2.4',
     * 'ape', 'vorbiscomment',
     * 'metaflac', 'real').
     *
     * @var array
     */
    public $tagformats = [];

    /**
     * 2-dimensional array of tag data (ex: $data['ARTIST'][0] = 'Elvis').
     *
     * @var array
     */
    public $tagData = [[]];

    /**
     * Text encoding used for tag data ('ISO-8859-1', 'UTF-8', 'UTF-16',
     * 'UTF-16LE', 'UTF-16BE', ).
     *
     * @var string
     */
    public $tagEncoding = 'ISO-8859-1';

    /**
     * If true will erase existing tag data and write only passed data; if
     * false will merge passed data with existing tag data.
     *
     * @var bool
     */
    public $overwriteTags = true;

    /**
     * If true will erase remove all existing tags and only write those passed
     * in $tagformats; If false will ignore any tags not mentioned in
     * $tagformats.
     *
     * @var bool
     */
    public $removeOtherTags = false;

    /**
     * ISO-639-2 3-character language code needed for some ID3v2 frames.
     *
     * @link http://www.id3.org/iso639-2.html
     * @var string
     */
    public $id3v2TagLanguage = 'eng';

    /**
     * Minimum length of ID3v2 tags (will be padded to this length if tag data
     * is shorter).
     *
     * @var int
     */
    public $id3v2Paddedlength = 4096;

    /**
     * Any non-critical errors will be stored here.
     *
     * @var array
     */
    public $warnings = [];

    /**
     * Any critical errors will be stored here.
     *
     * @var array
     */
    public $errors = [];

    /**
     * Analysis of file before writing.
     *
     * @var array
     */
    private $thisFileInfo;

    public function __construct()
    {
    }

    /**
     * @return bool
     * @throws \GetId3\Exception\GetId3Exception
     * @throws \Exception
     */
    public function writeTags(): bool
    {

        if (empty($this->filename)) {
            $this->errors[] = 'filename is undefined in getid3_writetags';

            return false;
        } elseif (!file_exists($this->filename)) {
            $this->errors[] = 'filename set to non-existant file "'.$this->filename.'" in getid3_writetags';

            return false;
        }

        if (!\is_array($this->tagformats)) {
            $this->errors[] = 'tagformats must be an array in getid3_writetags';

            return false;
        }

        $TagFormatsToRemove = [];
        $AllowedTagFormats = [];
        if (filesize($this->filename) === 0) {
            // empty file special case - allow any tag format, don't check existing format
            // could be useful if you want to generate tag data for a non-existant file
            $this->thisFileInfo = ['fileformat' => ''];
            $AllowedTagFormats = [
              'id3v1',
              'id3v2.2',
              'id3v2.3',
              'id3v2.4',
              'ape',
              'lyrics3',
            ];

        } else {
            $getID3 = new GetId3();
            $getID3->encoding = $this->tagEncoding;
            $this->thisFileInfo = $getID3->analyze($this->filename);

            // check for what file types are allowed on this fileformat
            switch ($this->thisFileInfo['fileformat'] ?? '') {
                case 'mp3':
                case 'mp2':
                case 'mp1':
                case 'riff': // maybe not officially, but people do it anyway
                    $AllowedTagFormats = [
                      'id3v1',
                      'id3v2.2',
                      'id3v2.3',
                      'id3v2.4',
                      'ape',
                      'lyrics3',
                    ];
                    break;

                case 'mpc':
                    $AllowedTagFormats = ['ape'];
                    break;

                case 'flac':
                    $AllowedTagFormats = ['metaflac'];
                    break;

                case 'real':
                    $AllowedTagFormats = ['real'];
                    break;

                case 'ogg':
                    switch ($this->thisFileInfo['audio']['dataformat'] ?? '') {
                        case 'flac':
                            //$AllowedTagFormats = array('metaflac');
                            $this->errors[] = 'metaflac is not (yet) compatible with OggFLAC files';

                            return false;
                            break;
                        case 'vorbis':
                            $AllowedTagFormats = ['vorbiscomment'];
                            break;
                        default:
                            $this->errors[] = 'metaflac is not (yet) compatible with Ogg files other than OggVorbis';

                            return false;
                            break;
                    }
                    break;

                default:
                    $AllowedTagFormats = [];
                    break;
            }
            foreach ($this->tagformats as $requested_tag_format) {
                if (!\in_array($requested_tag_format, $AllowedTagFormats,
                  true)) {
                    $errormessage = 'Tag format "'.$requested_tag_format.'" is not allowed on "'.($this->thisFileInfo['fileformat'] ?? '');
                    $errormessage .= (isset($this->thisFileInfo['audio']['dataformat']) ? '.'.$this->thisFileInfo['audio']['dataformat'] : '');
                    $errormessage .= '" files';
                    $this->errors[] = $errormessage;

                    return false;
                }
            }

            // List of other tag formats, removed if requested
            if ($this->removeOtherTags) {
                foreach ($AllowedTagFormats as $AllowedTagFormat) {
                    switch ($AllowedTagFormat) {
                        case 'id3v2.2':
                        case 'id3v2.3':
                        case 'id3v2.4':
                            if (
                              !\in_array('id3v2', $TagFormatsToRemove, true) &&
                              !\in_array('id3v2.2', $this->tagformats, true) &&
                              !\in_array('id3v2.3', $this->tagformats, true) &&
                              !\in_array('id3v2.4', $this->tagformats, true)
                            ) {
                                $TagFormatsToRemove[] = 'id3v2';
                            }
                            break;

                        default:
                            if (!\in_array($AllowedTagFormat, $this->tagformats,
                              true)) {
                                $TagFormatsToRemove[] = $AllowedTagFormat;
                            }
                            break;
                    }
                }
            }
        }

        $WritingFilesToInclude = array_merge($this->tagformats,
          $TagFormatsToRemove);

        // Check for required include files and include them
        foreach ($WritingFilesToInclude as $tagformat) {
            switch ($tagformat) {
                case 'ape':
                    $GETID3_ERRORARRAY = &$this->errors;
                    GetId3Library::IncludeDependency(GETID3_INCLUDEPATH.'write.apetag.php',
                      __FILE__, true);
                    break;

                case 'id3v1':
                case 'lyrics3':
                case 'vorbiscomment':
                case 'metaflac':
                case 'real':
                    $GETID3_ERRORARRAY = &$this->errors;
                    GetId3Library::IncludeDependency(GETID3_INCLUDEPATH.'write.'.$tagformat.'.php',
                      __FILE__, true);
                    break;

                case 'id3v2.2':
                case 'id3v2.3':
                case 'id3v2.4':
                case 'id3v2':
                    $GETID3_ERRORARRAY = &$this->errors;
                    GetId3Library::IncludeDependency(GETID3_INCLUDEPATH.'write.id3v2.php',
                      __FILE__, true);
                    break;

                default:
                    $this->errors[] = 'unknown tag format "'.$tagformat.'" in $tagformats in WriteTags()';

                    return false;
                    break;
            }

        }

        // Validation of supplied data
        if (!\is_array($this->tagData)) {
            $this->errors[] = '$this->tag_data is not an array in WriteTags()';

            return false;
        }
        // convert supplied data array keys to upper case, if they're not already
        foreach ($this->tagData as $tag_key => $tag_array) {
            if (strtoupper($tag_key) !== $tag_key) {
                $this->tagData[strtoupper($tag_key)] = $this->tagData[$tag_key];
                unset($this->tagData[$tag_key]);
            }
        }
        // convert source data array keys to upper case, if they're not already
        if (!empty($this->thisFileInfo['tags'])) {
            foreach ($this->thisFileInfo['tags'] as $tag_format => $tag_data_array) {
                foreach ($tag_data_array as $tag_key => $tag_array) {
                    if (strtoupper($tag_key) !== $tag_key) {
                        $this->thisFileInfo['tags'][$tag_format][strtoupper($tag_key)] = $this->thisFileInfo['tags'][$tag_format][$tag_key];
                        unset($this->thisFileInfo['tags'][$tag_format][$tag_key]);
                    }
                }
            }
        }

        // Convert "TRACK" to "TRACKNUMBER" (if needed) for compatability with all formats
        if (isset($this->tagData['TRACK']) && !isset($this->tagData['TRACKNUMBER'])) {
            $this->tagData['TRACKNUMBER'] = $this->tagData['TRACK'];
            unset($this->tagData['TRACK']);
        }

        // Remove all other tag formats, if requested
        if ($this->removeOtherTags) {
            $this->deleteTags($TagFormatsToRemove);
        }

        $errorMessage = function (string $prefix, array $errors) {
            $subject = htmlentities(trim(implode("\n", $errors)), ENT_QUOTES);
            $subject = str_replace("\n", '</li><li>', $subject);

            return "{$prefix}<pre><ul><li>{$subject}</li></ul></pre>";
        };

        // Write data for each tag format
        foreach ($this->tagformats as $tagformat) {
            $success = false; // overridden if tag writing is successful
            switch ($tagformat) {
                case 'ape':
                    $ape_writer = new Apetag();
                    if ($ape_writer->tag_data = $this->formatDataForAPE()) {
                        $ape_writer->filename = $this->filename;
                        if (($success = $ape_writer->WriteAPEtag()) === false) {
                            $this->errors[] = $errorMessage(
                              'WriteAPEtag() failed with message(s):',
                              $ape_writer->errors
                            );
                        }
                    } else {
                        $this->errors[] = 'FormatDataForAPE() failed';
                    }
                    break;

                case 'id3v1':
                    $id3v1_writer = new Id3v1();
                    if ($id3v1_writer->tag_data = $this->formatDataForID3v1()) {
                        $id3v1_writer->filename = $this->filename;
                        if (($success = $id3v1_writer->WriteID3v1()) === false) {
                            $this->errors[] = $errorMessage(
                              'WriteID3v1() failed with message(s):',
                              $id3v1_writer->errors
                            );
                        }
                    } else {
                        $this->errors[] = 'FormatDataForID3v1() failed';
                    }
                    break;

                case 'id3v2.2':
                case 'id3v2.3':
                case 'id3v2.4':
                    $id3v2_writer = new Id3v2();
                    $id3v2_writer->majorversion = (int)substr($tagformat, -1);
                    $id3v2_writer->paddedlength = $this->id3v2Paddedlength;
                    $id3v2_writer_tag_data = $this->formatDataForID3v2($id3v2_writer->majorversion);
                    if ($id3v2_writer_tag_data !== false) {
                        $id3v2_writer->tag_data = $id3v2_writer_tag_data;
                        unset($id3v2_writer_tag_data);
                        $id3v2_writer->filename = $this->filename;
                        if (($success = $id3v2_writer->WriteID3v2()) === false) {
                            $this->errors[] = $errorMessage(
                              'WriteID3v2() failed with message(s):',
                              $id3v2_writer->errors
                            );
                        }
                    } else {
                        $this->errors[] = 'FormatDataForID3v2() failed';
                    }
                    break;

                case 'vorbiscomment':
                    $vorbiscomment_writer = new VorbisComment();
                    if ($vorbiscomment_writer->tag_data = $this->formatDataForVorbisComment()) {
                        $vorbiscomment_writer->filename = $this->filename;
                        if (($success = $vorbiscomment_writer->WriteVorbisComment()) === false) {
                            $this->errors[] = $errorMessage(
                              'WriteVorbisComment() failed with message(s):',
                              $vorbiscomment_writer->errors
                            );
                        }
                    } else {
                        $this->errors[] = 'FormatDataForVorbisComment() failed';
                    }
                    break;

                case 'metaflac':
                    $metaflac_writer = new Metaflac();
                    if ($metaflac_writer->tag_data = $this->formatDataForMetaFLAC()) {
                        $metaflac_writer->filename = $this->filename;
                        if (($success = $metaflac_writer->WriteMetaFLAC()) === false) {
                            $this->errors[] = $errorMessage(
                              'WriteMetaFLAC() failed with message(s):',
                              $metaflac_writer->errors
                            );
                        }
                    } else {
                        $this->errors[] = 'FormatDataForMetaFLAC() failed';
                    }
                    break;

                case 'real':
                    $real_writer = new Real();
                    if ($real_writer->tag_data = $this->formatDataForReal()) {
                        $real_writer->filename = $this->filename;
                        if (($success = $real_writer->WriteReal()) === false) {
                            $this->errors[] = $errorMessage(
                              'WriteReal() failed with message(s):',
                              $real_writer->errors
                            );
                        }
                    } else {
                        $this->errors[] = 'FormatDataForReal() failed';
                    }
                    break;

                default:
                    $this->errors[] = 'Invalid tag format to write: "'.$tagformat.'"';

                    return false;
                    break;
            }
            if (!$success) {
                return false;
            }
        }

        return true;

    }

    /**
     * @param array<int, string> $TagFormatsToDelete
     *
     * @return bool
     */
    public function deleteTags(array $TagFormatsToDelete): bool
    {
        foreach ($TagFormatsToDelete as $DeleteTagFormat) {
            $success = false; // overridden if tag deletion is successful
            switch ($DeleteTagFormat) {
                case 'id3v1':
                    $id3v1_writer = new Id3v1();
                    $id3v1_writer->filename = $this->filename;
                    if (($success = $id3v1_writer->RemoveID3v1()) === false) {
                        $this->errors[] = 'RemoveID3v1() failed with message(s):<PRE><UL><LI>'.trim(implode('</LI><LI>',
                            $id3v1_writer->errors)).'</LI></UL></PRE>';
                    }
                    break;

                case 'id3v2':
                    $id3v2_writer = new Id3v2();
                    $id3v2_writer->filename = $this->filename;
                    if (($success = $id3v2_writer->RemoveID3v2()) === false) {
                        $this->errors[] = 'RemoveID3v2() failed with message(s):<PRE><UL><LI>'.trim(implode('</LI><LI>',
                            $id3v2_writer->errors)).'</LI></UL></PRE>';
                    }
                    break;

                case 'ape':
                    $ape_writer = new Apetag();
                    $ape_writer->filename = $this->filename;
                    if (($success = $ape_writer->DeleteAPEtag()) === false) {
                        $this->errors[] = 'DeleteAPEtag() failed with message(s):<PRE><UL><LI>'.trim(implode('</LI><LI>',
                            $ape_writer->errors)).'</LI></UL></PRE>';
                    }
                    break;

                case 'vorbiscomment':
                    $vorbiscomment_writer = new VorbisComment();
                    $vorbiscomment_writer->filename = $this->filename;
                    if (($success = $vorbiscomment_writer->DeleteVorbisComment()) === false) {
                        $this->errors[] = 'DeleteVorbisComment() failed with message(s):<PRE><UL><LI>'.trim(implode('</LI><LI>',
                            $vorbiscomment_writer->errors)).'</LI></UL></PRE>';
                    }
                    break;

                case 'metaflac':
                    $metaflac_writer = new Metaflac();
                    $metaflac_writer->filename = $this->filename;
                    if (($success = $metaflac_writer->DeleteMetaFLAC()) === false) {
                        $this->errors[] = 'DeleteMetaFLAC() failed with message(s):<PRE><UL><LI>'.trim(implode('</LI><LI>',
                            $metaflac_writer->errors)).'</LI></UL></PRE>';
                    }
                    break;

                case 'lyrics3':
                    $lyrics3_writer = new Lyrics3();
                    $lyrics3_writer->filename = $this->filename;
                    if (($success = $lyrics3_writer->DeleteLyrics3()) === false) {
                        $this->errors[] = 'DeleteLyrics3() failed with message(s):<PRE><UL><LI>'.trim(implode('</LI><LI>',
                            $lyrics3_writer->errors)).'</LI></UL></PRE>';
                    }
                    break;

                case 'real':
                    $real_writer = new Real();
                    $real_writer->filename = $this->filename;
                    if (($success = $real_writer->RemoveReal()) === false) {
                        $this->errors[] = 'RemoveReal() failed with message(s):<PRE><UL><LI>'.trim(implode('</LI><LI>',
                            $real_writer->errors)).'</LI></UL></PRE>';
                    }
                    break;

                default:
                    $this->errors[] = 'Invalid tag format to delete: "'.$DeleteTagFormat.'"';

                    return false;
                    break;
            }
            if (!$success) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $TagFormat
     * @param array $tag_data
     *
     * @return bool
     * @throws Exception
     */
    public function mergeExistingTagData(
      string $TagFormat,
      array &$tag_data
    ): bool {
        // Merge supplied data with existing data, if requested
        if ($this->overwriteTags) {
            // do nothing - ignore previous data
        } else {
            throw new Exception('$this->overwrite_tags=false is known to be buggy in this version of getID3. Check http://github.com/JamesHeinrich/getID3 for a newer version.');
            if (!isset($this->thisFileInfo['tags'][$TagFormat])) {
                return false;
            }
            $tag_data = array_merge_recursive($tag_data,
              $this->thisFileInfo['tags'][$TagFormat]);
        }

        return true;
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function formatDataForAPE(): array
    {
        $ape_tag_data = [];
        foreach ($this->tagData as $tag_key => $valuearray) {
            switch ($tag_key) {
                case 'ATTACHED_PICTURE':
                    // ATTACHED_PICTURE is ID3v2 only - ignore
                    $this->warnings[] = '$data['.$tag_key.'] is assumed to be ID3v2 APIC data - NOT written to APE tag';
                    break;

                default:
                    foreach ($valuearray as $key => $value) {
                        if (\is_string($value) || is_numeric($value)) {
                            $ape_tag_data[$tag_key][$key] = GetId3Library::iconv_fallback($this->tagEncoding,
                              'UTF-8', $value);
                        } else {
                            $this->warnings[] = '$data['.$tag_key.']['.$key.'] is not a string value - all of $data['.$tag_key.'] NOT written to APE tag';
                            unset($ape_tag_data[$tag_key]);
                            break;
                        }
                    }
                    break;
            }
        }
        $this->mergeExistingTagData('ape', $ape_tag_data);

        return $ape_tag_data;
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function formatDataForID3v1(): array
    {
        $tag_data_id3v1 = [];
        $tag_data_id3v1['genreid'] = 255;
        if (!empty($this->tagData['GENRE'])) {
            foreach ($this->tagData['GENRE'] as $key => $value) {
                if (Id3v1::LookupGenreID($value) !== false) {
                    $tag_data_id3v1['genreid'] = Id3v1::LookupGenreID($value);
                    break;
                }
            }
        }
        $tag_data_id3v1['title'] = GetId3Library::iconv_fallback($this->tagEncoding,
          'ISO-8859-1', implode(' ', $this->tagData['TITLE'] ?? []));
        $tag_data_id3v1['artist'] = GetId3Library::iconv_fallback($this->tagEncoding,
          'ISO-8859-1', implode(' ', $this->tagData['ARTIST'] ?? []));
        $tag_data_id3v1['album'] = GetId3Library::iconv_fallback($this->tagEncoding,
          'ISO-8859-1', implode(' ', $this->tagData['ALBUM'] ?? []));
        $tag_data_id3v1['year'] = GetId3Library::iconv_fallback($this->tagEncoding,
          'ISO-8859-1', implode(' ', $this->tagData['YEAR'] ?? []));
        $tag_data_id3v1['comment'] = GetId3Library::iconv_fallback($this->tagEncoding,
          'ISO-8859-1', implode(' ', $this->tagData['COMMENT'] ?? []));
        $tag_data_id3v1['track'] = (int)GetId3Library::iconv_fallback($this->tagEncoding,
          'ISO-8859-1', implode(' ', $this->tagData['TRACKNUMBER'] ?? []));
        if ($tag_data_id3v1['track'] <= 0) {
            $tag_data_id3v1['track'] = '';
        }

        $this->mergeExistingTagData('id3v1', $tag_data_id3v1);

        return $tag_data_id3v1;
    }

    /**
     * @param int $id3v2_majorversion
     *
     * @return array|false
     * @throws \Exception
     */
    public function formatDataForID3v2(int $id3v2_majorversion)
    {
        $tag_data_id3v2 = [];

        $ID3v2_text_encoding_lookup[2] = ['ISO-8859-1' => 0, 'UTF-16' => 1];
        $ID3v2_text_encoding_lookup[3] = ['ISO-8859-1' => 0, 'UTF-16' => 1];
        $ID3v2_text_encoding_lookup[4] = [
          'ISO-8859-1' => 0,
          'UTF-16'     => 1,
          'UTF-16BE'   => 2,
          'UTF-8'      => 3,
        ];
        foreach ($this->tagData as $tag_key => $valuearray) {
            $ID3v2_framename = Id3v2::ID3v2ShortFrameNameLookup($id3v2_majorversion,
              $tag_key);
            switch ($ID3v2_framename) {
                case 'APIC':
                    foreach ($valuearray as $key => $apic_data_array) {
                        if (isset($apic_data_array['data'], $apic_data_array['picturetypeid'], $apic_data_array['description'], $apic_data_array['mime'])) {
                            $tag_data_id3v2['APIC'][] = $apic_data_array;
                        } else {
                            $this->errors[] = 'ID3v2 APIC data is not properly structured';

                            return false;
                        }
                    }
                    break;

                case 'POPM':
                    if (isset($valuearray['email'], $valuearray['rating'], $valuearray['data'])) {
                        $tag_data_id3v2['POPM'][] = $valuearray;
                    } else {
                        $this->errors[] = 'ID3v2 POPM data is not properly structured';

                        return false;
                    }
                    break;

                case 'GRID':
                    if (
                    isset($valuearray['groupsymbol'], $valuearray['ownerid'], $valuearray['data'])
                    ) {
                        $tag_data_id3v2['GRID'][] = $valuearray;
                    } else {
                        $this->errors[] = 'ID3v2 GRID data is not properly structured';

                        return false;
                    }
                    break;

                case 'UFID':
                    if (isset($valuearray['ownerid'], $valuearray['data'])) {
                        $tag_data_id3v2['UFID'][] = $valuearray;
                    } else {
                        $this->errors[] = 'ID3v2 UFID data is not properly structured';

                        return false;
                    }
                    break;

                case 'TXXX':
                    foreach ($valuearray as $key => $txxx_data_array) {
                        if (isset($txxx_data_array['description'], $txxx_data_array['data'])) {
                            $tag_data_id3v2['TXXX'][] = $txxx_data_array;
                        } else {
                            $this->errors[] = 'ID3v2 TXXX data is not properly structured';

                            return false;
                        }
                    }
                    break;

                case '':
                    $this->errors[] = 'ID3v2: Skipping "'.$tag_key.'" because cannot match it to a known ID3v2 frame type';
                    // some other data type, don't know how to handle it, ignore it
                    break;

                default:
                    // most other (text) frames can be copied over as-is
                    foreach ($valuearray as $key => $value) {
                        if (isset($ID3v2_text_encoding_lookup[$id3v2_majorversion][$this->tagEncoding])) {
                            // source encoding is valid in ID3v2 - use it with no conversion
                            $tag_data_id3v2[$ID3v2_framename][$key]['encodingid'] = $ID3v2_text_encoding_lookup[$id3v2_majorversion][$this->tagEncoding];
                            $tag_data_id3v2[$ID3v2_framename][$key]['data'] = $value;
                        } else {
                            // source encoding is NOT valid in ID3v2 - convert it to an ID3v2-valid encoding first
                            if ($id3v2_majorversion < 4) {
                                // convert data from other encoding to UTF-16 (with BOM)
                                // note: some software, notably Windows Media Player and iTunes are broken and treat files tagged with UTF-16BE (with BOM) as corrupt
                                // therefore we force data to UTF-16LE and manually prepend the BOM
                                $ID3v2_tag_data_converted = false;
                                if (!$ID3v2_tag_data_converted && ($this->tagEncoding === 'ISO-8859-1')) {
                                    // great, leave data as-is for minimum compatability problems
                                    $tag_data_id3v2[$ID3v2_framename][$key]['encodingid'] = 0;
                                    $tag_data_id3v2[$ID3v2_framename][$key]['data'] = $value;
                                    $ID3v2_tag_data_converted = true;
                                }
                                if (!$ID3v2_tag_data_converted && ($this->tagEncoding === 'UTF-8')) {
                                    do {
                                        // if UTF-8 string does not include any characters above chr(127) then it is identical to ISO-8859-1
                                        for ($i = 0, $iMax = \strlen($value); $i < $iMax; $i++) {
                                            if (\ord($value{$i}) > 127) {
                                                break 2;
                                            }
                                        }
                                        $tag_data_id3v2[$ID3v2_framename][$key]['encodingid'] = 0;
                                        $tag_data_id3v2[$ID3v2_framename][$key]['data'] = $value;
                                        $ID3v2_tag_data_converted = true;
                                    } while (false);
                                }
                                if (!$ID3v2_tag_data_converted) {
                                    $tag_data_id3v2[$ID3v2_framename][$key]['encodingid'] = 1;
                                    // $tag_data_id3v2[$ID3v2_framename][$key]['data']       = GetId3Library::iconv_fallback($this->tag_encoding, 'UTF-16', $value); // output is UTF-16LE+BOM or UTF-16BE+BOM depending on system architecture
                                    $tag_data_id3v2[$ID3v2_framename][$key]['data'] = "\xFF\xFE".GetId3Library::iconv_fallback($this->tagEncoding,
                                        'UTF-16LE',
                                        $value); // force LittleEndian order version of UTF-16
                                    $ID3v2_tag_data_converted = true;
                                }

                            } else {
                                // convert data from other encoding to UTF-8
                                $tag_data_id3v2[$ID3v2_framename][$key]['encodingid'] = 3;
                                $tag_data_id3v2[$ID3v2_framename][$key]['data'] = GetId3Library::iconv_fallback($this->tagEncoding,
                                  'UTF-8', $value);
                            }
                        }

                        // These values are not needed for all frame types, but if they're not used no matter
                        $tag_data_id3v2[$ID3v2_framename][$key]['description'] = '';
                        $tag_data_id3v2[$ID3v2_framename][$key]['language'] = $this->id3v2TagLanguage;
                    }
                    break;
            }
        }
        $this->mergeExistingTagData('id3v2', $tag_data_id3v2);

        return $tag_data_id3v2;
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function formatDataForVorbisComment(): array
    {
        $tag_data_vorbiscomment = $this->tagData;

        // check for multi-line comment values - split out to multiple comments if neccesary
        // and convert data to UTF-8 strings
        foreach ($tag_data_vorbiscomment as $tag_key => $valuearray) {
            foreach ($valuearray as $key => $value) {
                if (($tag_key === 'ATTACHED_PICTURE') && \is_array($value)) {
                    continue; // handled separately in write.metaflac.php
                } else {
                    str_replace("\r", "\n", $value);
                    if (strpos($value, "\n") !== false) {
                        unset($tag_data_vorbiscomment[$tag_key][$key]);
                        $multilineexploded = explode("\n", $value);
                        foreach ($multilineexploded as $newcomment) {
                            if (\strlen(trim($newcomment)) > 0) {
                                $tag_data_vorbiscomment[$tag_key][] = GetId3Library::iconv_fallback($this->tagEncoding,
                                  'UTF-8', $newcomment);
                            }
                        }
                    } elseif (\is_string($value) || is_numeric($value)) {
                        $tag_data_vorbiscomment[$tag_key][$key] = GetId3Library::iconv_fallback($this->tagEncoding,
                          'UTF-8', $value);
                    } else {
                        $this->warnings[] = '$data['.$tag_key.']['.$key.'] is not a string value - all of $data['.$tag_key.'] NOT written to VorbisComment tag';
                        unset($tag_data_vorbiscomment[$tag_key]);
                        break;
                    }
                }
            }
        }
        $this->mergeExistingTagData('vorbiscomment', $tag_data_vorbiscomment);

        return $tag_data_vorbiscomment;
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function formatDataForMetaFLAC(): array
    {
        // FLAC & OggFLAC use VorbisComments same as OggVorbis
        // but require metaflac to do the writing rather than vorbiscomment
        return $this->formatDataForVorbisComment();
    }

    /**
     * @return array<string, string>
     * @throws \Exception
     */
    public function formatDataForReal(): array
    {
        $map = function (string $item, string $key) {
            $pieces = $this->tagData[mb_strtoupper($key)] ?? [];
            $value = implode(' ', $pieces);

            return GetId3Library::iconv_fallback(
              $this->tagEncoding,
              'ISO-8859-1',
              $value
            );
        };

        $collection = collect([
          'title'     => '',
          'artist'    => '',
          'copyright' => '',
          'comment'   => '',
        ]);

        $tagDataReal = $collection->map($map)->all();

        $this->mergeExistingTagData('real', $tagDataReal);

        return $tagDataReal;
    }

}
