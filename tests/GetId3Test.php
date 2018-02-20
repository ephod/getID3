<?php
declare(strict_types=1);

namespace GetId3;

use PHPUnit\Framework\TestCase;

class GetId3Test extends TestCase
{

    public function testFread_buffer_size()
    {

    }

    public function testGetHashdata()
    {

    }

    public function testInclude_module()
    {

    }

    public function testSetOption()
    {

    }

    public function testOpenfile()
    {

    }

    public function testGetFileFormatArray()
    {

    }

    public function testCalculateCompressionRatioAudio()
    {

    }

    public function testProcessAudioStreams()
    {

    }

    public function testError()
    {

    }

    public function testCalculateCompressionRatioVideo()
    {

    }

    /**
     * @throws \GetID3\Exception\GetId3Exception
     */
    public function testVersion()
    {
        $getId3 = new GetId3();

        $expected = '1.9.15-201802151809';
        $actual = $getId3->version();
        $this->assertEquals($expected, $actual);
    }

    public function test__construct()
    {

    }

    public function testCharConvert()
    {

    }

    /**
     * @throws \GetId3\Exception\GetId3Exception
     */
    public function testIs_writable()
    {
        $expected = true;
        $actual = GetId3::is_writable(__DIR__.'/../.travis.yml');
        $this->assertEquals($expected, $actual);
    }

    /**
     * @throws \GetId3\Exception\GetId3Exception
     *
     * @expectedException \GetId3\Exception\GetId3Exception
     */
    public function testIs_writableNoFile()
    {
        $expected = true;
        $actual = GetId3::is_writable(__DIR__.'/../.travis.yml.');
        $this->assertEquals($expected, $actual);
    }

    public function testHandleAllTags()
    {

    }

    public function testChannelsBitratePlaytimeCalculations()
    {

    }

    public function testGetFileFormat()
    {

    }

    public function testAnalyze()
    {

    }

    public function testWarning()
    {

    }

    public function testCalculateReplayGain()
    {

    }

    public function testGetid3_tempnam()
    {

    }
}
