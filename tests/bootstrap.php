<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package SilverAssist\SettingsHub
 */

declare(strict_types=1);

// Composer autoloader.
require_once __DIR__ . '/../vendor/autoload.php';

// Initialize Brain Monkey for WordPress function mocking.
Brain\Monkey\setUp();
