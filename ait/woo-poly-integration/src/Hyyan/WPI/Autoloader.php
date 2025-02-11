<?php

/**
 * This file is part of the hyyan/woo-poly-integration plugin.
 * (c) Hyyan Abo Fakher <hyyanaf@gmail.com>.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Hyyan\WPI;

/**
 * Plugin Namespace Autoloader.
 *
 * @author Hyyan Abo Fakher <hyyanaf@gmail.com>
 */
final class Autoloader
{
    /**
     * @var string
     */
    private string $base;

    /**
     * Construct the autoloader class.
     *
     * @param string $base the base path to use
     *
     * @throws \RuntimeException when the autoloader can not register itself
     */
    public function __construct(string $base)
    {
        $this->base = $base;
        if (!spl_autoload_register([$this, 'handle'], true, true)) {
            throw new \RuntimeException('Unable to register Autoloader');
        }
    }

    /**
     * Handle autoloading.
     *
     * @param string $className class or interface name
     *
     * @return bool true if class or interface exists, false otherwise
     */
    public function handle(string $className): bool
    {
        if (strpos($className, "Hyyan\\WPI") !== 0) {
            return false;
        }

        $filename = $this->base . str_replace('\\', '/', $className) . '.php';
        if (file_exists($filename)) {
            require_once $filename;
            return class_exists($className, false) || interface_exists($className, false);
        }

        return false;
    }
}