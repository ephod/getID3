<?php
declare(strict_types=1);

namespace GetId3\Module\AudioVideo;

class AMFStream
{
    /**
     * @var string
     */
    public $bytes;

    /**
     * @var int
     */
    public $pos;

    /**
     * @param string $bytes
     */
    public function __construct(&$bytes) {
        $this->bytes =& $bytes;
        $this->pos = 0;
    }

    /**
     * @return int
     */
    public function readByte() { //  8-bit
        return ord(substr($this->bytes, $this->pos++, 1));
    }

    /**
     * @return int
     */
    public function readInt() { // 16-bit
        return ($this->readByte() << 8) + $this->readByte();
    }

    /**
     * @return int
     */
    public function readLong() { // 32-bit
        return ($this->readByte() << 24) + ($this->readByte() << 16) + ($this->readByte() << 8) + $this->readByte();
    }

    /**
     * @return float|false
     */
    public function readDouble() {
        return getid3_lib::BigEndian2Float($this->read(8));
    }

    /**
     * @return string
     */
    public function readUTF() {
        $length = $this->readInt();
        return $this->read($length);
    }

    /**
     * @return string
     */
    public function readLongUTF() {
        $length = $this->readLong();
        return $this->read($length);
    }

    /**
     * @param int $length
     *
     * @return string
     */
    public function read($length) {
        $val = substr($this->bytes, $this->pos, $length);
        $this->pos += $length;
        return $val;
    }

    /**
     * @return int
     */
    public function peekByte() {
        $pos = $this->pos;
        $val = $this->readByte();
        $this->pos = $pos;
        return $val;
    }

    /**
     * @return int
     */
    public function peekInt() {
        $pos = $this->pos;
        $val = $this->readInt();
        $this->pos = $pos;
        return $val;
    }

    /**
     * @return int
     */
    public function peekLong() {
        $pos = $this->pos;
        $val = $this->readLong();
        $this->pos = $pos;
        return $val;
    }

    /**
     * @return float|false
     */
    public function peekDouble() {
        $pos = $this->pos;
        $val = $this->readDouble();
        $this->pos = $pos;
        return $val;
    }

    /**
     * @return string
     */
    public function peekUTF() {
        $pos = $this->pos;
        $val = $this->readUTF();
        $this->pos = $pos;
        return $val;
    }

    /**
     * @return string
     */
    public function peekLongUTF() {
        $pos = $this->pos;
        $val = $this->readLongUTF();
        $this->pos = $pos;
        return $val;
    }
}
