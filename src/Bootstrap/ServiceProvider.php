<?php
/**
 * Service Provider: registers all bindings in the container
 *
 * @package KissPlugins\WooCouponDebugger
 */

namespace KissPlugins\WooCouponDebugger\Bootstrap;

use KissPlugins\WooCouponDebugger\Interfaces\ContainerInterface;
use KissPlugins\WooCouponDebugger\Interfaces\LoggerInterface;
use KissPlugins\WooCouponDebugger\Interfaces\DebuggerInterface;
use KissPlugins\WooCouponDebugger\Interfaces\HookTrackerInterface;
use KissPlugins\WooCouponDebugger\Interfaces\CartSimulatorInterface;
use KissPlugins\WooCouponDebugger\Interfaces\SettingsRepositoryInterface;
use KissPlugins\WooCouponDebugger\Core\Logger;
use KissPlugins\WooCouponDebugger\Core\DebuggerCore;
use KissPlugins\WooCouponDebugger\Hooks\HookTracker;
use KissPlugins\WooCouponDebugger\Cart\CartSimulator;
use KissPlugins\WooCouponDebugger\Settings\SettingsRepository;
use KissPlugins\WooCouponDebugger\Interfaces\AdminInterface as AdminContract;
use KissPlugins\WooCouponDebugger\Admin\AdminUI;
use KissPlugins\WooCouponDebugger\Ajax\AjaxHandler;

class ServiceProvider {
    private string $version;

    public function __construct(string $version) {
        $this->version = $version;
    }

    public function register(ContainerInterface $container): void {
        // Interfaces to concrete singletons
        $container->singleton(LoggerInterface::class, Logger::class);

        $container->singleton(HookTrackerInterface::class, function ($c) {
            return new HookTracker($c->get(LoggerInterface::class));
        });

        $container->singleton(CartSimulatorInterface::class, function ($c) {
            return new CartSimulator($c->get(LoggerInterface::class));
        });

        $container->singleton(DebuggerInterface::class, function ($c) {
            return new DebuggerCore(
                $c->get(LoggerInterface::class),
                $c->get(HookTrackerInterface::class),
                $c->get(CartSimulatorInterface::class)
            );
        });

        // Settings repository
        $container->singleton(SettingsRepositoryInterface::class, function () {
            return new SettingsRepository();
        });

        // Concrete classes
        $container->singleton(AdminContract::class, function () {
            return new AdminUI($this->version);
        });

        $container->singleton(AjaxHandler::class, function ($c) {
            return new AjaxHandler(
                $c->get(DebuggerInterface::class),
                $c->get(SettingsRepositoryInterface::class)
            );
        });
    }
}

