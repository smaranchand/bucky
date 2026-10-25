<?php

namespace Bucky\Tests;

use Bucky\Settings;
use PHPUnit\Framework\TestCase;

class SettingsTest extends TestCase
{
    public function testAutoWriteIsOnByDefault(): void
    {
        $this->assertTrue(Settings::autoWriteEnabled([]));
    }

    public function testAutoWriteCanBeTurnedOff(): void
    {
        foreach (['false', 'FALSE', '0', 'off', 'no', 'No'] as $value) {
            $this->assertFalse(
                Settings::autoWriteEnabled(['BUCKY_AUTO_WRITE' => $value]),
                "'$value' should disable auto-write"
            );
        }
    }

    public function testAutoWriteStaysOnForAffirmativeValues(): void
    {
        foreach (['true', 'TRUE', '1', 'on', 'yes'] as $value) {
            $this->assertTrue(
                Settings::autoWriteEnabled(['BUCKY_AUTO_WRITE' => $value]),
                "'$value' should enable auto-write"
            );
        }
    }

    public function testUnrecognisedValueDoesNotSilentlyDisable(): void
    {
        // An operator who typo'd the value should keep the documented default rather
        // than have the tool quietly stop doing its main job.
        $this->assertTrue(Settings::autoWriteEnabled(['BUCKY_AUTO_WRITE' => 'maybe']));
    }

    public function testEmptyValueUsesTheDefault(): void
    {
        $this->assertTrue(Settings::autoWriteEnabled(['BUCKY_AUTO_WRITE' => '']));
    }
}
