<?php

namespace Charcoal\Search;

// from charcoal-app
use Charcoal\App\Module\AbstractModule;

/**
 * Search Module
 */
class SearchModule extends AbstractModule
{
    const ADMIN_CONFIG = 'vendor/locomotivemtl/charcoal-contrib-notification/config/admin.json';
    const APP_CONFIG = 'vendor/locomotivemtl/charcoal-contrib-notification/config/config.json';

    /**
     * Setup the module's dependencies.
     *
     * @return AbstractModule
     */
    public function setup()
    {
        $container = $this->app()->getContainer();

        $notificationServiceProvider = new SearchServiceProvider();
        $container->register($notificationServiceProvider);

        $notificationConfig = $container['notification/config'];
        $this->setConfig($notificationConfig);

        return $this;
    }

}
