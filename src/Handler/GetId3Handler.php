<?php
declare(strict_types=1);

namespace GetId3\Handler;

use Exception;
use GetId3\Exception\GetId3Exception;
use GetId3\GetId3;
use GetId3\Library\GetId3Library;

/**
 * Class GetId3Handler
 *
 * @package GetId3\Handler
 */
abstract class GetId3Handler
{

    /**
     * @var getID3
     */
    protected $getId3;                       // pointer

    /**
     * Analyzing filepointer or string.
     *
     * @var bool
     */
    protected $data_string_flag = false;

    /**
     * String to analyze.
     *
     * @var string
     */
    protected $data_string = '';

    /**
     * Seek position in string.
     *
     * @var int
     */
    protected $data_string_position = 0;

    /**
     * String length.
     *
     * @var int
     */
    protected $data_string_length = 0;

    /**
     * @var string
     */
    private $dependency_to;

    /**
     * GetId3Handler constructor.
     *
     * @param \GetId3\GetId3 $getid3
     * @param null|string $call_module
     */
    public function __construct(GetId3 $getid3, ?string $call_module = null)
    {
        $this->getId3 = $getid3;

        if ($call_module) {
            $this->dependency_to = str_replace('getid3_', '', $call_module);
        }
    }

    /**
     * Analyze from file pointer.
     *
     * @return bool
     */
    abstract public function Analyze(): bool;

    /**
     * Analyze from string instead.
     *
     * @param string $string
     */
    public function AnalyzeString(string $string): void
    {
        // Enter string mode
        $this->setStringMode($string);

        // Save info
        $saved_avdataoffset = $this->getId3->info['avdataoffset'];
        $saved_avdataend = $this->getId3->info['avdataend'];
        $saved_filesize = (isset($this->getId3->info['filesize']) ? $this->getId3->info['filesize'] : null); // may be not set if called as dependency without openfile() call

        // Reset some info
        $this->getId3->info['avdataoffset'] = 0;
        $this->getId3->info['avdataend'] = $this->getId3->info['filesize'] = $this->data_string_length;

        // Analyze
        $this->Analyze();

        // Restore some info
        $this->getId3->info['avdataoffset'] = $saved_avdataoffset;
        $this->getId3->info['avdataend'] = $saved_avdataend;
        $this->getId3->info['filesize'] = $saved_filesize;

        // Exit string mode
        $this->data_string_flag = false;
    }

    /**
     * @param string $string
     */
    public function setStringMode(string $string): void
    {
        $this->data_string_flag = true;
        $this->data_string = $string;
        $this->data_string_length = strlen($string);
    }

    /**
     * @return int|bool
     */
    protected function ftell()
    {
        if ($this->data_string_flag) {
            return $this->data_string_position;
        }

        return ftell($this->getId3->fp);
    }

    /**
     * @param int $bytes
     *
     * @return string|false
     * @throws \GetId3\Exception\GetId3Exception
     */
    protected function fread(int $bytes)
    {
        if ($this->data_string_flag) {
            $this->data_string_position += $bytes;

            return substr($this->data_string,
              $this->data_string_position - $bytes, $bytes);
        }
        $pos = $this->ftell() + $bytes;
        if (!GetId3Library::intValueSupported($pos)) {
            throw new GetId3Exception(
              'cannot fread('.$bytes.' from '.$this->ftell().') because beyond PHP filesystem limit',
              10
            );
        }

        //return fread($this->getid3->fp, $bytes);
        /*
        * http://www.getid3.org/phpBB3/viewtopic.php?t=1930
        * "I found out that the root cause for the problem was how getID3 uses the PHP system function fread().
        * It seems to assume that fread() would always return as many bytes as were requested.
        * However, according the PHP manual (http://php.net/manual/en/function.fread.php), this is the case only with regular local files, but not e.g. with Linux pipes.
        * The call may return only part of the requested data and a new call is needed to get more."
        */
        $contents = '';
        do {
            $part = fread($this->getId3->fp, $bytes);
            $partLength = strlen($part);
            $bytes -= $partLength;
            $contents .= $part;
        } while (($bytes > 0) && ($partLength > 0));

        return $contents;
    }

    /**
     * @param int $bytes
     * @param int $whence
     *
     * @return int
     * @throws \GetId3\Exception\GetId3Exception
     */
    protected function fseek(int $bytes, ?int $whence = SEEK_SET): int
    {
        if ($this->data_string_flag) {
            switch ($whence) {
                case SEEK_SET:
                    $this->data_string_position = $bytes;
                    break;

                case SEEK_CUR:
                    $this->data_string_position += $bytes;
                    break;

                case SEEK_END:
                    $this->data_string_position = $this->data_string_length + $bytes;
                    break;
            }

            return 0;
        } else {
            $pos = $bytes;
            if ($whence == SEEK_CUR) {
                $pos = $this->ftell() + $bytes;
            } elseif ($whence == SEEK_END) {
                $pos = $this->getId3->info['filesize'] + $bytes;
            }
            if (!GetId3Library::intValueSupported($pos)) {
                throw new GetId3Exception('cannot fseek('.$pos.') because beyond PHP filesystem limit',
                  10);
            }
        }

        return fseek($this->getId3->fp, $bytes, $whence);
    }

    /**
     * @return bool
     */
    protected function feof(): bool
    {
        if ($this->data_string_flag) {
            return $this->data_string_position >= $this->data_string_length;
        }

        return feof($this->getId3->fp);
    }

    /**
     * @param string $module
     *
     * @return bool
     */
    final protected function isDependencyFor(string $module): bool
    {
        return $this->dependency_to == $module;
    }

    /**
     * @param string $text
     *
     * @return bool
     */
    protected function error(string $text): bool
    {
        $this->getId3->info['error'][] = $text;

        return false;
    }

    /**
     * @param string $text
     *
     * @return bool
     */
    protected function warning(string $text): bool
    {
        return $this->getId3->warning($text);
    }

    /**
     * @param string $text
     */
    protected function notice($text)
    {
        // does nothing for now
    }

    /**
     * @param string $name
     * @param int $offset
     * @param int $length
     * @param string $image_mime
     *
     * @return string|null
     * @throws Exception
     * @throws \GetId3\Exception\GetId3Exception
     */
    public function saveAttachment(
      string $name,
      int $offset,
      int $length,
      ?string $image_mime = null
    ) {
        try {

            // do not extract at all
            if ($this->getId3->option_save_attachments === getID3::ATTACHMENTS_NONE) {

                $attachment = null; // do not set any

                // extract to return array
            } elseif ($this->getId3->option_save_attachments === getID3::ATTACHMENTS_INLINE) {

                $this->fseek($offset);
                $attachment = $this->fread($length); // get whole data in one pass, till it is anyway stored in memory
                if ($attachment === false || strlen($attachment) != $length) {
                    throw new Exception('failed to read attachment data');
                }

                // assume directory path is given
            } else {

                // set up destination path
                $dir = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR,
                  $this->getId3->option_save_attachments), DIRECTORY_SEPARATOR);
                if (!is_dir($dir) || !getID3::is_writable($dir)) { // check supplied directory
                    throw new Exception('supplied path ('.$dir.') does not exist, or is not writable');
                }
                $dest = $dir.DIRECTORY_SEPARATOR.$name.($image_mime ? '.'.GetId3Library::ImageExtFromMime($image_mime) : '');

                // create dest file
                if (($fp_dest = fopen($dest, 'wb')) == false) {
                    throw new Exception('failed to create file '.$dest);
                }

                // copy data
                $this->fseek($offset);
                $buffersize = ($this->data_string_flag ? $length : $this->getId3->fread_buffer_size());
                $bytesleft = $length;
                while ($bytesleft > 0) {
                    if (($buffer = $this->fread(min($buffersize,
                        $bytesleft))) === false || ($byteswritten = fwrite($fp_dest,
                        $buffer)) === false || ($byteswritten === 0)) {
                        throw new Exception($buffer === false ? 'not enough data to read' : 'failed to write to destination file, may be not enough disk space');
                    }
                    $bytesleft -= $byteswritten;
                }

                fclose($fp_dest);
                $attachment = $dest;

            }

        } catch (Exception $e) {

            // close and remove dest file if created
            if (isset($fp_dest) && is_resource($fp_dest)) {
                fclose($fp_dest);
            }

            if (isset($dest) && file_exists($dest)) {
                unlink($dest);
            }

            // do not set any is case of error
            $attachment = null;
            $this->warning('Failed to extract attachment '.$name.': '.$e->getMessage());

        }

        // seek to the end of attachment
        $this->fseek($offset + $length);

        return $attachment;
    }

}
