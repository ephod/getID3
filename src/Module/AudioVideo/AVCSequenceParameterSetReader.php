<?php
declare(strict_types=1);

namespace GetId3\Module\AudioVideo;

class AVCSequenceParameterSetReader
{
    /**
     * @var string
     */
    public $sps;
    public $start = 0;
    public $currentBytes = 0;
    public $currentBits = 0;

    /**
     * @var int
     */
    public $width;

    /**
     * @var int
     */
    public $height;

    /**
     * @param string $sps
     */
    public function __construct($sps) {
        $this->sps = $sps;
    }

    public function readData() {
        $this->skipBits(8);
        $this->skipBits(8);
        $profile = $this->getBits(8);                               // read profile
        if ($profile > 0) {
            $this->skipBits(8);
            $level_idc = $this->getBits(8);                         // level_idc
            $this->expGolombUe();                                   // seq_parameter_set_id // sps
            $this->expGolombUe();                                   // log2_max_frame_num_minus4
            $picOrderType = $this->expGolombUe();                   // pic_order_cnt_type
            if ($picOrderType == 0) {
                $this->expGolombUe();                               // log2_max_pic_order_cnt_lsb_minus4
            } elseif ($picOrderType == 1) {
                $this->skipBits(1);                                 // delta_pic_order_always_zero_flag
                $this->expGolombSe();                               // offset_for_non_ref_pic
                $this->expGolombSe();                               // offset_for_top_to_bottom_field
                $num_ref_frames_in_pic_order_cnt_cycle = $this->expGolombUe(); // num_ref_frames_in_pic_order_cnt_cycle
                for ($i = 0; $i < $num_ref_frames_in_pic_order_cnt_cycle; $i++) {
                    $this->expGolombSe();                           // offset_for_ref_frame[ i ]
                }
            }
            $this->expGolombUe();                                   // num_ref_frames
            $this->skipBits(1);                                     // gaps_in_frame_num_value_allowed_flag
            $pic_width_in_mbs_minus1 = $this->expGolombUe();        // pic_width_in_mbs_minus1
            $pic_height_in_map_units_minus1 = $this->expGolombUe(); // pic_height_in_map_units_minus1

            $frame_mbs_only_flag = $this->getBits(1);               // frame_mbs_only_flag
            if ($frame_mbs_only_flag == 0) {
                $this->skipBits(1);                                 // mb_adaptive_frame_field_flag
            }
            $this->skipBits(1);                                     // direct_8x8_inference_flag
            $frame_cropping_flag = $this->getBits(1);               // frame_cropping_flag

            $frame_crop_left_offset   = 0;
            $frame_crop_right_offset  = 0;
            $frame_crop_top_offset    = 0;
            $frame_crop_bottom_offset = 0;

            if ($frame_cropping_flag) {
                $frame_crop_left_offset   = $this->expGolombUe();   // frame_crop_left_offset
                $frame_crop_right_offset  = $this->expGolombUe();   // frame_crop_right_offset
                $frame_crop_top_offset    = $this->expGolombUe();   // frame_crop_top_offset
                $frame_crop_bottom_offset = $this->expGolombUe();   // frame_crop_bottom_offset
            }
            $this->skipBits(1);                                     // vui_parameters_present_flag
            // etc

            $this->width  = (($pic_width_in_mbs_minus1 + 1) * 16) - ($frame_crop_left_offset * 2) - ($frame_crop_right_offset * 2);
            $this->height = ((2 - $frame_mbs_only_flag) * ($pic_height_in_map_units_minus1 + 1) * 16) - ($frame_crop_top_offset * 2) - ($frame_crop_bottom_offset * 2);
        }
    }

    /**
     * @param int $bits
     */
    public function skipBits($bits) {
        $newBits = $this->currentBits + $bits;
        $this->currentBytes += (int)floor($newBits / 8);
        $this->currentBits = $newBits % 8;
    }

    /**
     * @return int
     */
    public function getBit() {
        $result = (getid3_lib::BigEndian2Int(substr($this->sps, $this->currentBytes, 1)) >> (7 - $this->currentBits)) & 0x01;
        $this->skipBits(1);
        return $result;
    }

    /**
     * @param int $bits
     *
     * @return int
     */
    public function getBits($bits) {
        $result = 0;
        for ($i = 0; $i < $bits; $i++) {
            $result = ($result << 1) + $this->getBit();
        }
        return $result;
    }

    /**
     * @return int
     */
    public function expGolombUe() {
        $significantBits = 0;
        $bit = $this->getBit();
        while ($bit == 0) {
            $significantBits++;
            $bit = $this->getBit();

            if ($significantBits > 31) {
                // something is broken, this is an emergency escape to prevent infinite loops
                return 0;
            }
        }
        return (1 << $significantBits) + $this->getBits($significantBits) - 1;
    }

    /**
     * @return int
     */
    public function expGolombSe() {
        $result = $this->expGolombUe();
        if (($result & 0x01) == 0) {
            return -($result >> 1);
        } else {
            return ($result + 1) >> 1;
        }
    }

    /**
     * @return int
     */
    public function getWidth() {
        return $this->width;
    }

    /**
     * @return int
     */
    public function getHeight() {
        return $this->height;
    }
}
