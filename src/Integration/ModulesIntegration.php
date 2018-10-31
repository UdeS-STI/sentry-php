<?php

namespace Sentry\Integration;

use Composer\Composer;
use Composer\Factory;
use Composer\IO\NullIO;
use Sentry\Event;
use Sentry\Options;
use Sentry\State\Hub;
use Sentry\State\Scope;

/**
 * This middleware logs with the event details all the versions of the packages
 * installed with Composer, if any.
 *
 * @author Stefano Arlandini <sarlandini@alice.it>
 */
final class ModulesIntegration implements Integration
{
    /**
     * @var Options The client option
     */
    private $options;

    /**
     * @var array
     */
    private static $loadedModules = [];

    /**
     * Constructor.
     *
     * @param Options $options The Raven client configuration
     */
    public function __construct(Options $options)
    {
        if (!class_exists(Composer::class)) {
            throw new \LogicException('You need the "composer/composer" package in order to use this middleware.');
        }

        $this->options = $options;
    }

    /**
     *
     */
    public function setupOnce(): void
    {
        Scope::addGlobalEventProcessor(function (Event $event) {
            if ($self = Hub::getCurrent()->getIntegration($this)) {
                /** @var ModulesIntegration $self */
                $composerFilePath = $self->options->getProjectRoot() . \DIRECTORY_SEPARATOR . 'composer.json';

                if (file_exists($composerFilePath) && \count(self::$loadedModules) == 0) {
                    $composer = Factory::create(new NullIO(), $composerFilePath, true);
                    $locker = $composer->getLocker();

                    if ($locker->isLocked()) {
                        foreach ($locker->getLockedRepository()->getPackages() as $package) {
                            self::$loadedModules[$package->getName()] = $package->getVersion();
                        }
                    }
                }

                $event->setModules(self::$loadedModules);
                return $event;
            }
            return $event;
        });
    }
}
